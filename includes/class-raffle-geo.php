<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Raffle_Geo {

    /**
     * Check if a user can access a raffle based on geo restrictions.
     */
    public static function check_eligibility( $raffle ) {
        if ( ! $raffle || ! $raffle->geo_restricted ) {
            return true;
        }

        $allowed_countries = json_decode( $raffle->geo_allowed_countries ?? '[]', true );
        if ( empty( $allowed_countries ) ) {
            return true;
        }

        $user_country = self::get_user_country();

        if ( ! $user_country ) {
            // If we can't determine country, allow access (fail open)
            return true;
        }

        return in_array( $user_country, $allowed_countries, true );
    }

    /**
     * Get user's country code from IP or WooCommerce billing address.
     */
    public static function get_user_country() {
        // Try WooCommerce billing country first (logged in users)
        if ( function_exists( 'WC' ) && is_user_logged_in() ) {
            $customer = WC()->customer;
            if ( $customer ) {
                $billing_country = $customer->get_billing_country();
                if ( $billing_country ) {
                    return $billing_country;
                }
            }
        }

        // Try IP geolocation
        $ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) );
        if ( empty( $ip ) || $ip === '127.0.0.1' ) {
            return false;
        }

        return self::geo_lookup( $ip );
    }

    /**
     * IP geolocation lookup (uses free ip-api.com).
     */
    private static function geo_lookup( $ip ) {
        $cached = get_transient( 'raffle_geo_' . md5( $ip ) );
        if ( $cached ) {
            return $cached;
        }

        $response = wp_remote_get( 'http://ip-api.com/json/' . $ip . '?fields=countryCode', array(
            'timeout' => 3,
        ) );

        if ( is_wp_error( $response ) ) {
            return false;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        $country = $body['countryCode'] ?? false;

        if ( $country ) {
            set_transient( 'raffle_geo_' . md5( $ip ), $country, WEEK_IN_SECONDS );
        }

        return $country;
    }

    /**
     * Get list of all countries for the admin form.
     */
    public static function get_countries_list() {
        $countries = array(
            'AF' => 'Afghanistan', 'AL' => 'Albania', 'DZ' => 'Algeria', 'AD' => 'Andorra',
            'AO' => 'Angola', 'AG' => 'Antigua and Barbuda', 'AR' => 'Argentina', 'AM' => 'Armenia',
            'AU' => 'Australia', 'AT' => 'Austria', 'AZ' => 'Azerbaijan', 'BS' => 'Bahamas',
            'BH' => 'Bahrain', 'BD' => 'Bangladesh', 'BB' => 'Barbados', 'BY' => 'Belarus',
            'BE' => 'Belgium', 'BZ' => 'Belize', 'BJ' => 'Benin', 'BT' => 'Bhutan',
            'BO' => 'Bolivia', 'BA' => 'Bosnia and Herzegovina', 'BW' => 'Botswana', 'BR' => 'Brazil',
            'BN' => 'Brunei', 'BG' => 'Bulgaria', 'BF' => 'Burkina Faso', 'BI' => 'Burundi',
            'KH' => 'Cambodia', 'CM' => 'Cameroon', 'CA' => 'Canada', 'CF' => 'Central African Republic',
            'TD' => 'Chad', 'CL' => 'Chile', 'CN' => 'China', 'CO' => 'Colombia',
            'CD' => 'Congo (DRC)', 'CR' => 'Costa Rica', 'HR' => 'Croatia', 'CU' => 'Cuba',
            'CY' => 'Cyprus', 'CZ' => 'Czech Republic', 'DK' => 'Denmark', 'DO' => 'Dominican Republic',
            'EC' => 'Ecuador', 'EG' => 'Egypt', 'SV' => 'El Salvador', 'EE' => 'Estonia',
            'ET' => 'Ethiopia', 'FI' => 'Finland', 'FR' => 'France', 'GA' => 'Gabon',
            'GE' => 'Georgia', 'DE' => 'Germany', 'GH' => 'Ghana', 'GR' => 'Greece',
            'GT' => 'Guatemala', 'GN' => 'Guinea', 'GY' => 'Guyana', 'HT' => 'Haiti',
            'HN' => 'Honduras', 'HK' => 'Hong Kong', 'HU' => 'Hungary', 'IS' => 'Iceland',
            'IN' => 'India', 'ID' => 'Indonesia', 'IR' => 'Iran', 'IQ' => 'Iraq',
            'IE' => 'Ireland', 'IL' => 'Israel', 'IT' => 'Italy', 'JM' => 'Jamaica',
            'JP' => 'Japan', 'JO' => 'Jordan', 'KZ' => 'Kazakhstan', 'KE' => 'Kenya',
            'KW' => 'Kuwait', 'KG' => 'Kyrgyzstan', 'LA' => 'Laos', 'LV' => 'Latvia',
            'LB' => 'Lebanon', 'LY' => 'Libya', 'LI' => 'Liechtenstein', 'LT' => 'Lithuania',
            'LU' => 'Luxembourg', 'MO' => 'Macao', 'MK' => 'North Macedonia', 'MG' => 'Madagascar',
            'MY' => 'Malaysia', 'ML' => 'Mali', 'MT' => 'Malta', 'MX' => 'Mexico',
            'MD' => 'Moldova', 'MC' => 'Monaco', 'MN' => 'Mongolia', 'ME' => 'Montenegro',
            'MA' => 'Morocco', 'MZ' => 'Mozambique', 'MM' => 'Myanmar', 'NA' => 'Namibia',
            'NP' => 'Nepal', 'NL' => 'Netherlands', 'NZ' => 'New Zealand', 'NI' => 'Nicaragua',
            'NE' => 'Niger', 'NG' => 'Nigeria', 'KP' => 'North Korea', 'NO' => 'Norway',
            'OM' => 'Oman', 'PK' => 'Pakistan', 'PA' => 'Panama', 'PY' => 'Paraguay',
            'PE' => 'Peru', 'PH' => 'Philippines', 'PL' => 'Poland', 'PT' => 'Portugal',
            'QA' => 'Qatar', 'RO' => 'Romania', 'RU' => 'Russia', 'RW' => 'Rwanda',
            'SA' => 'Saudi Arabia', 'SN' => 'Senegal', 'RS' => 'Serbia', 'SG' => 'Singapore',
            'SK' => 'Slovakia', 'SI' => 'Slovenia', 'SO' => 'Somalia', 'ZA' => 'South Africa',
            'KR' => 'South Korea', 'ES' => 'Spain', 'LK' => 'Sri Lanka', 'SD' => 'Sudan',
            'SE' => 'Sweden', 'CH' => 'Switzerland', 'SY' => 'Syria', 'TW' => 'Taiwan',
            'TJ' => 'Tajikistan', 'TZ' => 'Tanzania', 'TH' => 'Thailand', 'TN' => 'Tunisia',
            'TR' => 'Turkey', 'TM' => 'Turkmenistan', 'UG' => 'Uganda', 'UA' => 'Ukraine',
            'AE' => 'United Arab Emirates', 'GB' => 'United Kingdom', 'US' => 'United States',
            'UY' => 'Uruguay', 'UZ' => 'Uzbekistan', 'VE' => 'Venezuela', 'VN' => 'Vietnam',
            'YE' => 'Yemen', 'ZM' => 'Zambia', 'ZW' => 'Zimbabwe',
        );

        return $countries;
    }
}