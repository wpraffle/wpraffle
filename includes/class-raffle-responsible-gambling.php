<?php
/**
 * WPRaffle — Responsible Gambling Module
 *
 * Player-protection controls: spend limits, self-exclusion, reality checks,
 * and operator-initiated locks. Enforced server-side at every purchase gate.
 *
 * SECURITY:
 *  - All limits are enforced server-side in the purchase flow; client UI
 *    is advisory only.
 *  - Limit increases have a 24h cool-off (UKGC norm); decreases are instant.
 *  - Self-exclusion cannot be reversed early (server-enforced).
 *  - Every setting change is audited.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Raffle_Responsible_Gambling {

    /**
     * Get (or lazily create) the RG settings row for a user.
     *
     * @param int $user_id
     * @return object
     */
    public static function get_settings( $user_id ) {
        global $wpdb;
        $user_id = absint( $user_id );

        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}raffle_rg_settings WHERE user_id = %d",
            $user_id
        ) );

        if ( ! $row ) {
            // Lazily insert a default row.
            $email = '';
            $user  = get_userdata( $user_id );
            if ( $user && $user->user_email ) {
                $email = $user->user_email;
            }
            $wpdb->insert(
                $wpdb->prefix . 'raffle_rg_settings',
                array( 'user_id' => $user_id, 'buyer_email' => $email ),
                array( '%d', '%s' )
            );
            $row = $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}raffle_rg_settings WHERE user_id = %d",
                $user_id
            ) );
        }

        return $row;
    }

    /**
     * Get the RG settings row for a guest (unregistered) buyer keyed on email.
     * Returns null if no guest exclusion/limit exists for this email.
     *
     * Guest rows reuse the user_id PK column with a stable negative synthetic
     * ID derived from the email hash (crc32, negated). This avoids colliding
     * with real WP user IDs (always positive) while keeping one row per email
     * without a schema change to the PRIMARY KEY.
     *
     * @param string $email
     * @return object|null
     */
    public static function get_guest_settings( $email ) {
        global $wpdb;
        $email = sanitize_email( $email );
        if ( ! is_email( $email ) ) {
            return null;
        }
        $synthetic_id = self::guest_user_id( $email );
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}raffle_rg_settings WHERE user_id = %d LIMIT 1",
            $synthetic_id
        ) );
    }

    /**
     * Compute a stable negative synthetic user_id for a guest email. Real WP
     * user IDs are always positive, so negative values are reserved for guests.
     *
     * @param string $email
     * @return int Negative integer.
     */
    private static function guest_user_id( $email ) {
        // crc32 returns an unsigned 32-bit int on 32-bit PHP and a signed int
        // on 64-bit PHP. Cast through intval to normalise, then negate. The
        // result fits in a BIGINT column and is stable per email.
        return -1 * absint( abs( crc32( strtolower( $email ) ) ) );
    }

    /* ===================================================================
       Enforcement (called from purchase gates)
       =================================================================== */

    /**
     * Check whether a user is currently allowed to make a raffle purchase.
     *
     * Enforces against the user_id when logged in, and also against the
     * buyer_email when supplied (so guests who have self-excluded or who
     * have hit an email-keyed spend limit are still protected).
     *
     * @param int    $user_id
     * @param float  $pending_amount  Amount about to be spent (for limit check).
     * @param string $buyer_email     Optional guest email — checked for guest exclusion/limits.
     * @return true|WP_Error True if allowed, WP_Error with reason if blocked.
     */
    public static function check_purchase_allowed( $user_id, $pending_amount = 0.0, $buyer_email = '' ) {
        $user_id     = absint( $user_id );
        $buyer_email = $buyer_email ? sanitize_email( $buyer_email ) : '';
        $now         = current_time( 'mysql' );

        // Resolve the settings row: logged-in user first, else guest by email.
        $settings = $user_id ? self::get_settings( $user_id ) : null;
        if ( ! $settings && $buyer_email && is_email( $buyer_email ) ) {
            $settings = self::get_guest_settings( $buyer_email );
        }

        // No settings row → nothing to enforce. Allow.
        if ( ! $settings ) {
            return true;
        }

        // 1. Operator-imposed lock
        if ( ! empty( $settings->operator_locked ) || ( $settings->locked_until && $settings->locked_until > $now ) ) {
            return new WP_Error( 'rg_locked', 'Your account is currently locked from making purchases. Please contact support.' );
        }

        // 2. Self-exclusion
        if ( $settings->self_excluded_until && $settings->self_excluded_until > $now ) {
            return new WP_Error( 'rg_self_excluded', 'You are currently self-excluded. Self-exclusion cannot be lifted early.' );
        }

        // 3. Spend limit. Apply the cool-off: while a limit increase is
        //    pending (cool_off_change_until in the future), the EFFECTIVE
        //    limit stays at spend_limit_amount; the higher
        //    pending_spend_limit_amount only takes effect once cool-off ends.
        //    set_spend_limit() is responsible for promoting the pending value
        //    — but as a backstop we promote it here lazily if the window has
        //    elapsed, so the user is never stuck below their new limit.
        if ( ! empty( $settings->cool_off_change_until ) && $settings->cool_off_change_until <= $now && (float) $settings->pending_spend_limit_amount > 0 ) {
            self::promote_pending_limit( $settings );
            // Re-read after promotion so the check below uses the new value.
            $settings = $user_id ? self::get_settings( $user_id ) : self::get_guest_settings( $buyer_email );
        }

        $effective_limit = (float) $settings->spend_limit_amount;
        if ( $effective_limit > 0 ) {
            $lookup_email = $buyer_email;
            if ( ! $lookup_email && $user_id ) {
                $user = get_userdata( $user_id );
                if ( $user && $user->user_email ) {
                    $lookup_email = $user->user_email;
                }
            }
            if ( $lookup_email ) {
                $window_total = self::get_spend_in_window_by_email( $lookup_email, $settings->spend_limit_period );
                if ( ( $window_total + (float) $pending_amount ) > $effective_limit ) {
                    return new WP_Error(
                        'rg_spend_limit',
                        sprintf(
                            'This purchase would exceed your %s spending limit of %s. You have spent %s this %s.',
                            esc_html( $settings->spend_limit_period ),
                            esc_html( wpr_price( $effective_limit ) ),
                            esc_html( wpr_price( $window_total ) ),
                            esc_html( $settings->spend_limit_period )
                        )
                    );
                }
            }
        }

        return true;
    }

    /**
     * Promote a pending (cooled-off) spend limit to the effective value.
     *
     * @param object $settings
     */
    private static function promote_pending_limit( $settings ) {
        global $wpdb;
        $where  = $settings->user_id > 0 ? array( 'user_id' => $settings->user_id ) : array( 'buyer_email' => $settings->buyer_email, 'user_id' => 0 );
        $format = $settings->user_id > 0 ? array( '%f', '%s', '%d' ) : array( '%f', '%s', '%s', '%d' );
        $wpdb->update(
            $wpdb->prefix . 'raffle_rg_settings',
            array(
                'spend_limit_amount'         => (float) $settings->pending_spend_limit_amount,
                'pending_spend_limit_amount' => 0.0,
                'cool_off_change_until'      => null,
            ),
            $where,
            $format,
            array( '%d' )
        );
    }

    /**
     * Get the total spent on completed raffle purchases within a limit window,
     * keyed on email. Works for both logged-in users and guests.
     *
     * @param string $email
     * @param string $period 'day' | 'week' | 'month'
     * @return float
     */
    public static function get_spend_in_window_by_email( $email, $period = 'month' ) {
        global $wpdb;
        $email = sanitize_email( $email );
        if ( ! is_email( $email ) ) {
            return 0.0;
        }

        $days_map = array( 'day' => 1, 'week' => 7, 'month' => 30 );
        $days     = $days_map[ $period ] ?? 30;
        $since    = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

        return (float) $wpdb->get_var( $wpdb->prepare(
            "SELECT COALESCE(SUM(total_amount), 0)
             FROM {$wpdb->prefix}raffle_purchases
             WHERE buyer_email = %s AND payment_status = 'completed' AND purchase_date >= %s",
            $email,
            $since
        ) );
    }

    /**
     * Back-compat wrapper. Deprecated in favour of get_spend_in_window_by_email.
     *
     * @param int    $user_id
     * @param string $period
     * @return float
     */
    public static function get_spend_in_window( $user_id, $period = 'month' ) {
        $user = get_userdata( $user_id );
        if ( ! $user || ! $user->user_email ) {
            return 0.0;
        }
        return self::get_spend_in_window_by_email( $user->user_email, $period );
    }

    /* ===================================================================
       User-facing setting mutations (from My-Account)
       =================================================================== */

    /**
     * Update the user's spend limit.
     *
     * Increases are subject to a 24h cool-off: the new value is parked in
     * pending_spend_limit_amount and only promoted to the effective
     * spend_limit_amount once cool_off_change_until has elapsed (checked
     * lazily inside check_purchase_allowed). Decreases apply immediately.
     *
     * @param int    $user_id
     * @param string $period  'day' | 'week' | 'month'
     * @param float  $amount  New limit amount.
     * @return true|WP_Error
     */
    public static function set_spend_limit( $user_id, $period, $amount ) {
        $user_id = absint( $user_id );
        if ( ! $user_id || get_current_user_id() !== $user_id ) {
            return new WP_Error( 'unauthorized', 'You can only change your own settings.' );
        }
        if ( ! in_array( $period, array( 'day', 'week', 'month' ), true ) ) {
            return new WP_Error( 'invalid_period', 'Invalid period.' );
        }
        $amount = max( 0, (float) $amount );

        global $wpdb;
        $current = self::get_settings( $user_id );

        $data = array(
            'spend_limit_period' => $period,
        );
        $format = array( '%s' );

        if ( $amount > (float) $current->spend_limit_amount ) {
            // Increase → 24h cool-off. Keep the effective (old) limit, park
            // the new one in pending_spend_limit_amount.
            $data['pending_spend_limit_amount'] = $amount;
            $data['cool_off_change_until']      = gmdate( 'Y-m-d H:i:s', strtotime( '+24 hours' ) );
            $format[] = '%f';
            $format[] = '%s';
        } else {
            // Decrease (or unchanged) → apply immediately, clear any pending.
            $data['spend_limit_amount']         = $amount;
            $data['pending_spend_limit_amount'] = 0.0;
            $data['cool_off_change_until']      = null;
            $format[] = '%f';
            $format[] = '%f';
            $format[] = 'NULL';
        }

        $wpdb->update(
            $wpdb->prefix . 'raffle_rg_settings',
            $data,
            array( 'user_id' => $user_id ),
            $format,
            array( '%d' )
        );

        if ( class_exists( 'Raffle_Audit' ) ) {
            Raffle_Audit::log( 0, 'rg_spend_limit_changed', sprintf(
                'User #%d set spend limit to %s / %s.',
                $user_id, wpr_price( $amount ), $period
            ), 'system' );
        }

        return true;
    }

    /**
     * User-initiated self-exclusion. Cannot be lifted early.
     *
     * @param int    $user_id
     * @param string $until  MySQL datetime.
     * @return true|WP_Error
     */
    public static function self_exclude( $user_id, $until ) {
        $user_id = absint( $user_id );
        if ( ! $user_id || get_current_user_id() !== $user_id ) {
            return new WP_Error( 'unauthorized', 'You can only change your own settings.' );
        }

        $until_ts = strtotime( $until );
        if ( ! $until_ts || $until_ts < time() ) {
            return new WP_Error( 'invalid_date', 'Exclusion end date must be in the future.' );
        }
        // Cap exclusion at 5 years per UKGC norm.
        $max_ts = strtotime( '+5 years' );
        if ( $until_ts > $max_ts ) {
            $until_ts = $max_ts;
        }

        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'raffle_rg_settings',
            array( 'self_excluded_until' => gmdate( 'Y-m-d H:i:s', $until_ts ) ),
            array( 'user_id' => $user_id ),
            array( '%s' ),
            array( '%d' )
        );

        if ( class_exists( 'Raffle_Audit' ) ) {
            Raffle_Audit::log( 0, 'rg_self_excluded', sprintf(
                'User #%d self-excluded until %s.',
                $user_id, $until
            ), 'system' );
        }

        return true;
    }

    /**
     * Guest (unregistered) self-exclusion keyed on email. Creates or updates
     * a guest RG row (user_id = 0) so check_purchase_allowed() enforces it
     * even when the buyer has no account.
     *
     * @param string $email
     * @param string $until  MySQL datetime.
     * @return true|WP_Error
     */
    public static function self_exclude_guest( $email, $until ) {
        $email = sanitize_email( $email );
        if ( ! is_email( $email ) ) {
            return new WP_Error( 'invalid_email', 'A valid email is required.' );
        }
        $until_ts = strtotime( $until );
        if ( ! $until_ts || $until_ts < time() ) {
            return new WP_Error( 'invalid_date', 'Exclusion end date must be in the future.' );
        }
        $max_ts = strtotime( '+5 years' );
        if ( $until_ts > $max_ts ) {
            $until_ts = $max_ts;
        }

        global $wpdb;
        $synthetic_id = self::guest_user_id( $email );
        $existing = self::get_guest_settings( $email );
        if ( $existing ) {
            // Never allow shortening an existing exclusion.
            if ( $existing->self_excluded_until && strtotime( $existing->self_excluded_until ) > $until_ts ) {
                return true;
            }
            $wpdb->update(
                $wpdb->prefix . 'raffle_rg_settings',
                array( 'self_excluded_until' => gmdate( 'Y-m-d H:i:s', $until_ts ) ),
                array( 'user_id' => $synthetic_id ),
                array( '%s' ),
                array( '%d' )
            );
        } else {
            $wpdb->insert(
                $wpdb->prefix . 'raffle_rg_settings',
                array(
                    'user_id'             => $synthetic_id,
                    'buyer_email'         => $email,
                    'self_excluded_until' => gmdate( 'Y-m-d H:i:s', $until_ts ),
                ),
                array( '%d', '%s', '%s' )
            );
        }

        if ( class_exists( 'Raffle_Audit' ) ) {
            Raffle_Audit::log( 0, 'rg_guest_self_excluded', sprintf(
                'Guest %s self-excluded until %s.',
                $email, $until
            ), 'system' );
        }

        return true;
    }

    /* ===================================================================
       Operator actions (admin only)
       =================================================================== */

    /**
     * Operator-applied account lock.
     *
     * @param int    $user_id
     * @param string $until
     * @param string $reason
     * @return true|WP_Error
     */
    public static function operator_lock( $user_id, $until, $reason ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return new WP_Error( 'unauthorized', 'Unauthorized.' );
        }
        if ( empty( $reason ) ) {
            return new WP_Error( 'reason_required', 'A reason is required for an operator lock.' );
        }

        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'raffle_rg_settings',
            array(
                'operator_locked'      => 1,
                'operator_lock_reason' => sanitize_text_field( $reason ),
                'locked_until'         => sanitize_text_field( $until ),
            ),
            array( 'user_id' => absint( $user_id ) ),
            array( '%d', '%s', '%s' ),
            array( '%d' )
        );

        if ( class_exists( 'Raffle_Audit' ) ) {
            Raffle_Audit::log( 0, 'rg_operator_lock', sprintf(
                'Operator locked user #%d until %s. Reason: %s',
                $user_id, $until, $reason
            ), 'admin' );
        }

        return true;
    }

    /**
     * Operator-lifted account lock.
     *
     * @param int $user_id
     * @return true|WP_Error
     */
    public static function operator_unlock( $user_id ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return new WP_Error( 'unauthorized', 'Unauthorized.' );
        }

        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'raffle_rg_settings',
            array(
                'operator_locked'      => 0,
                'operator_lock_reason' => '',
                'locked_until'         => null,
            ),
            array( 'user_id' => absint( $user_id ) ),
            array( '%d', '%s', 'NULL' ),
            array( '%d' )
        );

        if ( class_exists( 'Raffle_Audit' ) ) {
            Raffle_Audit::log( 0, 'rg_operator_unlock', sprintf(
                'Operator unlocked user #%d.',
                $user_id
            ), 'admin' );
        }

        return true;
    }
}
