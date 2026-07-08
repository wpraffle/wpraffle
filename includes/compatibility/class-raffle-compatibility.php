<?php
/**
 * WPRaffle — Compatibility base class
 *
 * Standardises the conditional-load convention used by every adapter. Each
 * concrete adapter extends this class: is_available() detects whether its
 * target plugin is active; register_hooks() wires the integration. The loader
 * only calls register_hooks() when is_available() returns true, so inactive
 * adapters impose zero overhead.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

abstract class Raffle_Compatibility {

    /**
     * Human-readable name shown in the Compatibility settings tab.
     */
    abstract public static function name();

    /**
     * Whether the target plugin is active. Must be cheap (class_exists /
     * defined / function_exists only — no DB or remote calls).
     */
    abstract public static function is_available();

    /**
     * Wire the integration hooks. Only called by the loader when
     * is_available() is true.
     */
    abstract public function register_hooks();

    /**
     * Convenience for the Compatibility tab: returns the active/inactive
     * state plus the name for the status report.
     */
    public static function status() {
        return array(
            'name'    => static::name(),
            'active'  => (bool) static::is_available(),
        );
    }
}
