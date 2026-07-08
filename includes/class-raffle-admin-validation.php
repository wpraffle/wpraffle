<?php
/**
 * Server-side validation for WPRaffle admin forms.
 *
 * Pure, side-effect-free validators. They take a sanitised data array
 * (and optional context) and return a map of `[ 'field_key' => 'message' ]`.
 * An empty return value means the data is valid.
 *
 * @package WPRaffle
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Raffle_Admin_Validation {

    /**
     * Validate the raffle create/edit submission.
     *
     * @param array       $data    Sanitised data array (the same shape built in
     *                             Raffle_Admin::handle_form_submission()).
     * @param bool        $is_edit True on edit, false on create.
     * @param object|null $existing The existing raffle row on edit (for
     *                              sold-tickets locking checks), null on create.
     * @return array `['field' => 'message']`, empty when valid.
     */
    public static function validate_raffle( array $data, $is_edit = false, $existing = null ) {
        $errors = array();

        // ── Title ────────────────────────────────────────────────────────
        $title = isset( $data['title'] ) ? trim( (string) $data['title'] ) : '';
        if ( $title === '' ) {
            $errors['title'] = __( 'Title is required.', 'wpraffle' );
        } elseif ( mb_strlen( $title ) > 255 ) {
            $errors['title'] = __( 'Title must be 255 characters or fewer.', 'wpraffle' );
        }

        // ── Numeric core fields ──────────────────────────────────────────
        if ( ! isset( $data['prize_value'] ) || $data['prize_value'] === '' || $data['prize_value'] === null ) {
            $errors['prize_value'] = __( 'Prize value is required.', 'wpraffle' );
        } elseif ( ! is_numeric( $data['prize_value'] ) || (float) $data['prize_value'] < 0 ) {
            $errors['prize_value'] = __( 'Prize value must be a number of 0 or more.', 'wpraffle' );
        }

        if ( ! isset( $data['ticket_price'] ) || $data['ticket_price'] === '' || $data['ticket_price'] === null ) {
            $errors['ticket_price'] = __( 'Ticket price is required.', 'wpraffle' );
        } elseif ( ! is_numeric( $data['ticket_price'] ) || (float) $data['ticket_price'] <= 0 ) {
            $errors['ticket_price'] = __( 'Ticket price must be greater than 0.', 'wpraffle' );
        }

        $total_tickets = isset( $data['total_tickets'] ) ? (int) $data['total_tickets'] : 0;
        if ( $total_tickets < 1 ) {
            $errors['total_tickets'] = __( 'Total tickets must be at least 1.', 'wpraffle' );
        }

        // Lock total_tickets once sales exist (the field is readonly, but
        // defend against a crafted request that removes the attribute).
        if ( $is_edit && $existing && isset( $existing->sold_tickets ) && (int) $existing->sold_tickets > 0 ) {
            if ( $total_tickets !== (int) $existing->total_tickets ) {
                $errors['total_tickets'] = __( 'Total tickets cannot be changed after sales have started.', 'wpraffle' );
            }
        }

        // ── Dates ────────────────────────────────────────────────────────
        $start_ts = self::parse_datetime( $data['start_date'] ?? null );
        $draw_ts  = self::parse_datetime( $data['draw_date'] ?? null );

        if ( ! empty( $data['draw_date'] ) && $draw_ts === false ) {
            $errors['draw_date'] = __( 'Draw date is not a valid date and time.', 'wpraffle' );
        }
        if ( ! empty( $data['start_date'] ) && $start_ts === false ) {
            $errors['start_date'] = __( 'Start date is not a valid date and time.', 'wpraffle' );
        }
        // Draw must be after start when both are set and valid.
        if ( $start_ts && $draw_ts && $draw_ts <= $start_ts ) {
            $errors['draw_date'] = __( 'Draw date must be after the start date.', 'wpraffle' );
        }

        // ── Jackpot percentage (only meaningful for percent type) ────────
        $jackpot_type = $data['jackpot_type'] ?? 'fixed';
        if ( $jackpot_type === 'percent' ) {
            $jp = isset( $data['jackpot_percent'] ) ? (int) $data['jackpot_percent'] : -1;
            if ( $jp < 0 || $jp > 100 ) {
                $errors['jackpot_percent'] = __( 'Jackpot percentage must be between 0 and 100.', 'wpraffle' );
            }
        }

        // ── Max tickets per user ─────────────────────────────────────────
        $max_per_user = isset( $data['max_tickets_per_user'] ) ? (int) $data['max_tickets_per_user'] : 0;
        if ( $max_per_user < 1 ) {
            $errors['max_tickets_per_user'] = __( 'Max tickets per user must be at least 1.', 'wpraffle' );
        } elseif ( $total_tickets >= 1 && $max_per_user > $total_tickets ) {
            $errors['max_tickets_per_user'] = __( 'Max tickets per user cannot exceed the total tickets.', 'wpraffle' );
        }

        // ── Multi-winner ─────────────────────────────────────────────────
        if ( ! empty( $data['multi_winner'] ) ) {
            $winners = isset( $data['number_of_winners'] ) ? (int) $data['number_of_winners'] : 0;
            if ( $winners < 2 ) {
                $errors['number_of_winners'] = __( 'Select at least 2 winners when multi-winner is enabled.', 'wpraffle' );
            } elseif ( $total_tickets >= 1 && $winners > $total_tickets ) {
                $errors['number_of_winners'] = __( 'Number of winners cannot exceed the total tickets.', 'wpraffle' );
            }
        }

        // ── Consolation coupon ───────────────────────────────────────────
        if ( ! empty( $data['enable_consolation_coupon'] ) ) {
            $config = isset( $data['consolation_config'] ) ? json_decode( $data['consolation_config'], true ) : array();
            $amount = isset( $config['amount'] ) ? (float) $config['amount'] : 0;
            $days   = isset( $config['expiry_days'] ) ? (int) $config['expiry_days'] : 0;
            if ( $amount <= 0 ) {
                $errors['consolation_amount'] = __( 'Consolation amount must be greater than 0.', 'wpraffle' );
            }
            if ( $days < 1 ) {
                $errors['consolation_expiry_days'] = __( 'Consolation expiry must be at least 1 day.', 'wpraffle' );
            }
        }

        // ── Packages / bundles ───────────────────────────────────────────
        if ( ! empty( $data['enable_bundles'] ) ) {
            $bundles = isset( $data['packages'] ) ? json_decode( $data['packages'], true ) : null;
            if ( ! is_array( $bundles ) || empty( $bundles ) ) {
                $errors['packages'] = __( 'Add at least one bundle when bundles are enabled.', 'wpraffle' );
            } else {
                $bundle_errors = array();
                foreach ( $bundles as $i => $b ) {
                    $qty = isset( $b['qty'] ) ? (int) $b['qty'] : 0;
                    if ( $qty < 1 ) {
                        /* translators: %d: bundle position (1-based). */
                        $bundle_errors[] = sprintf( __( 'Bundle %d must have a quantity of at least 1.', 'wpraffle' ), $i + 1 );
                    }
                    if ( isset( $b['price'] ) && (float) $b['price'] < 0 ) {
                        /* translators: %d: bundle position (1-based). */
                        $bundle_errors[] = sprintf( __( 'Bundle %d price cannot be negative.', 'wpraffle' ), $i + 1 );
                    }
                }
                if ( $bundle_errors ) {
                    $errors['packages'] = implode( ' ', $bundle_errors );
                }
            }
        }

        // ── Charity ──────────────────────────────────────────────────────
        $charity_mode = $data['charity_mode'] ?? 'none';
        if ( $charity_mode !== 'none' ) {
            $cp = isset( $data['charity_percent'] ) ? (int) $data['charity_percent'] : -1;
            if ( $cp < 0 || $cp > 100 ) {
                $errors['charity_percent'] = __( 'Charity percentage must be between 0 and 100.', 'wpraffle' );
            }
        }

        // ── Cash alternative ─────────────────────────────────────────────
        if ( ! empty( $data['enable_cash_alternative'] ) ) {
            $cash = isset( $data['cash_alternative_amount'] ) ? (float) $data['cash_alternative_amount'] : -1;
            if ( $cash < 0 ) {
                $errors['cash_alternative_amount'] = __( 'Cash alternative amount cannot be negative.', 'wpraffle' );
            }
        }

        // ── 1.3.0 — Lifecycle thresholds ─────────────────────────────────
        $min_t = isset( $data['min_tickets'] ) ? (int) $data['min_tickets'] : 0;
        if ( $min_t < 0 ) {
            $errors['min_tickets'] = __( 'Minimum tickets cannot be negative.', 'wpraffle' );
        } elseif ( $min_t > $total_tickets ) {
            $errors['min_tickets'] = __( 'Minimum tickets cannot exceed the total ticket count.', 'wpraffle' );
        }
        $min_u = isset( $data['min_unique_users'] ) ? (int) $data['min_unique_users'] : 0;
        if ( $min_u < 0 ) {
            $errors['min_unique_users'] = __( 'Minimum unique entrants cannot be negative.', 'wpraffle' );
        }

        // ── 1.3.0 — Q&A limits ───────────────────────────────────────────
        if ( ! empty( $data['enable_question'] ) ) {
            $qa_time = isset( $data['qa_time_limit'] ) ? (int) $data['qa_time_limit'] : 0;
            if ( $qa_time < 0 ) {
                $errors['qa_time_limit'] = __( 'Question time limit cannot be negative.', 'wpraffle' );
            }
            $qa_att = isset( $data['qa_max_attempts'] ) ? (int) $data['qa_max_attempts'] : 0;
            if ( $qa_att < 0 ) {
                $errors['qa_max_attempts'] = __( 'Max wrong attempts cannot be negative.', 'wpraffle' );
            }
        }

        // ── 1.3.0 — Ticket numbering ─────────────────────────────────────
        $numbering = isset( $data['ticket_numbering'] ) ? $data['ticket_numbering'] : 'random';
        if ( ! in_array( $numbering, array( 'random', 'sequential', 'shuffled' ), true ) ) {
            $errors['ticket_numbering'] = __( 'Ticket numbering mode must be random, sequential, or shuffled.', 'wpraffle' );
        }
        $prefix_len = isset( $data['ticket_prefix'] ) ? strlen( (string) $data['ticket_prefix'] ) : 0;
        $suffix_len = isset( $data['ticket_suffix'] ) ? strlen( (string) $data['ticket_suffix'] ) : 0;
        if ( $prefix_len > 20 || $suffix_len > 20 ) {
            $errors['ticket_prefix'] = __( 'Ticket prefix/suffix must be 20 characters or fewer.', 'wpraffle' );
        }
        $start_num = isset( $data['ticket_start_number'] ) ? (int) $data['ticket_start_number'] : 1;
        if ( $start_num < 1 ) {
            $errors['ticket_start_number'] = __( 'Sequential start number must be at least 1.', 'wpraffle' );
        }

        // ── 1.3.0 — Auto-relist config ───────────────────────────────────
        $relist_cfg = isset( $data['relist_config'] ) ? json_decode( $data['relist_config'], true ) : null;
        if ( is_array( $relist_cfg ) && ! empty( $relist_cfg['auto_relist'] ) ) {
            if ( isset( $relist_cfg['relist_days'] ) && (int) $relist_cfg['relist_days'] < 1 ) {
                $errors['relist_days'] = __( 'Relist duration must be at least 1 day.', 'wpraffle' );
            }
            if ( isset( $relist_cfg['relist_pause_days'] ) && (int) $relist_cfg['relist_pause_days'] < 0 ) {
                $errors['relist_pause_days'] = __( 'Pause between runs cannot be negative.', 'wpraffle' );
            }
            if ( isset( $relist_cfg['relist_count'] ) && (int) $relist_cfg['relist_count'] < 0 ) {
                $errors['relist_count'] = __( 'Max relists cannot be negative.', 'wpraffle' );
            }
        }

        return $errors;
    }

    /**
     * Validate a settings tab submission.
     *
     * @param string $tab   Settings tab slug (general, email, legal, advanced,
     *                       styling, pages, shortcode).
     * @param array  $input Raw (unsanitised) $_POST input.
     * @return array `['code' => 'message']`, empty when valid. Codes are
     *               stable identifiers used to key the error banner.
     */
    public static function validate_settings( $tab, array $input ) {
        $errors = array();

        switch ( $tab ) {
            case 'email':
                $email = trim( (string) ( $input['from_email'] ?? '' ) );
                if ( $email === '' ) {
                    $errors['email_from'] = __( 'From email address is required.', 'wpraffle' );
                } elseif ( ! is_email( $email ) ) {
                    $errors['email_from'] = __( 'From email address is not valid.', 'wpraffle' );
                }
                if ( trim( (string) ( $input['from_name'] ?? '' ) ) === '' ) {
                    $errors['email_name'] = __( 'From name is required.', 'wpraffle' );
                }
                break;

            case 'advanced':
                $retention = (int) ( $input['audit_log_days'] ?? 0 );
                if ( $retention > 0 && $retention < 30 ) {
                    $errors['retention'] = __( 'Audit log retention must be at least 30 days.', 'wpraffle' );
                }
                $rate_limit = (int) ( $input['rate_limit_per_minute'] ?? 0 );
                if ( $rate_limit > 0 && $rate_limit < 1 ) {
                    $errors['rate_limit'] = __( 'Rate limit must be at least 1 per minute.', 'wpraffle' );
                }
                break;

            case 'styling':
                $color_fields = array( 'primary_color', 'accent_color', 'bg_color', 'text_color' );
                foreach ( $color_fields as $field ) {
                    if ( isset( $input[ $field ] ) && $input[ $field ] !== '' ) {
                        if ( ! self::is_valid_hex_color( $input[ $field ] ) ) {
                            $errors[ 'style_' . $field ] = sprintf(
                                /* translators: %s: field name */
                                __( '%s must be a valid hex colour (e.g. #1e40af).', 'wpraffle' ),
                                $field
                            );
                        }
                    }
                }
                break;

            case 'pages':
            case 'shortcode':
                $page_fields = array( 'shop_page', 'my_account_page', 'terms_page', 'lookup_page' );
                foreach ( $page_fields as $field ) {
                    $pid = (int) ( $input[ $field ] ?? 0 );
                    if ( $pid > 0 && ! get_post( $pid ) ) {
                        $errors[ 'page_' . $field ] = __( 'A selected page no longer exists.', 'wpraffle' );
                    }
                }
                break;
        }

        return $errors;
    }

    /**
     * Parse a datetime-local / MySQL datetime string into a Unix timestamp.
     * Returns false when the value is not a parseable date. Empty/null → 0.
     *
     * @param mixed $value
     * @return int|false
     */
    private static function parse_datetime( $value ) {
        if ( $value === null || $value === '' ) {
            return 0;
        }
        // Accept "Y-m-d\TH:i", "Y-m-d H:i", "Y-m-d H:i:s".
        $normalised = str_replace( 'T', ' ', (string) $value );
        $ts = strtotime( $normalised );
        return $ts === false ? false : $ts;
    }

    /**
     * Validate a hex colour string (#rgb or #rrggbb).
     *
     * @param string $color
     * @return bool
     */
    private static function is_valid_hex_color( $color ) {
        return (bool) preg_match( '/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', trim( $color ) );
    }
}
