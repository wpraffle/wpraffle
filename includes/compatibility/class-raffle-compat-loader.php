<?php
/**
 * WPRaffle — Compatibility loader
 *
 * Requires each adapter and instantiates the active ones. Loaded from
 * raffle-system.php in the plugins_loaded block. The list of adapters is the
 * single source of truth for which integrations ship; add a filename here to
 * enable a new adapter.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/class-raffle-compatibility.php';

class Raffle_Compat_Loader {

    /**
     * Adapter filenames (class-*.php) in includes/compatibility/. Order is
     * informational — adapters are independent.
     */
    private static $adapters = array(
        'class-raffle-compat-wpml.php',
        'class-raffle-compat-multi-currency.php',
        'class-raffle-compat-polylang.php',
        'class-raffle-compat-stripe.php',
        'class-raffle-compat-square.php',
        'class-raffle-compat-woopayments.php',
        'class-raffle-compat-smart-coupons.php',
        'class-raffle-compat-dokan.php',
        'class-raffle-compat-seo.php',
        'class-raffle-compat-cache.php',
    );

    /**
     * Require + activate every adapter whose target plugin is present.
     */
    public static function load() {
        foreach ( self::$adapters as $file ) {
            $path = __DIR__ . '/' . $file;
            if ( ! file_exists( $path ) ) {
                continue;
            }
            require_once $path;

            // Derive the class name from the filename.
            $class = 'Raffle_Compat_' . str_replace( ' ', '_', ucwords( str_replace( '-', ' ', substr( $file, strlen( 'class-raffle-compat-' ), -strlen( '.php' ) ) ) ) );

            if ( ! class_exists( $class ) ) {
                continue;
            }
            // is_available() is static so we can check before instantiating.
            if ( ! call_user_func( array( $class, 'is_available' ) ) ) {
                continue;
            }
            $instance = new $class();
            $instance->register_hooks();
        }
    }

    /**
     * Status report for the Compatibility settings tab. Returns each adapter's
     * name + active flag regardless of whether it loaded.
     */
    public static function status_report() {
        $report = array();
        foreach ( self::$adapters as $file ) {
            $path = __DIR__ . '/' . $file;
            if ( ! file_exists( $path ) ) {
                continue;
            }
            require_once $path;
            $class = 'Raffle_Compat_' . str_replace( ' ', '_', ucwords( str_replace( '-', ' ', substr( $file, strlen( 'class-raffle-compat-' ), -strlen( '.php' ) ) ) ) );
            if ( ! class_exists( $class ) ) {
                continue;
            }
            $report[] = call_user_func( array( $class, 'status' ) );
        }
        return $report;
    }
}
