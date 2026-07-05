<?php
/**
 * WPRaffle — Styling & Theme Presets
 *
 * Provides 5 built-in colour themes + custom overrides, all using CSS custom
 * properties (--wpr-*). The selected preset is output as an inline <style>
 * block in wp_head, overriding the :root defaults in public.css.
 *
 * Theme developers can still override any --wpr-* variable in their own CSS
 * with higher specificity.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Raffle_Styling {

    /**
     * Get all available preset themes.
     * Each preset is an array of CSS variable => value pairs.
     */
    public static function get_presets() {
        return array(
            'diamonds' => array(
                'name'        => 'Diamonds',
                'description' => 'Luxury teal, glassy surfaces and soft depth — jewellery & high-value prizes.',
                'vars' => array(
                    // Brand
                    '--wpr-accent'                => '#00bcd4',
                    '--wpr-accent-dark'           => '#0097a7',
                    '--wpr-accent-bg'             => '#e0f7fa',
                    '--wpr-accent-border'         => '#b2ebf2',
                    '--wpr-accent-text'           => '#00838f',
                    '--wpr-accent-text-dark'      => '#006064',
                    '--wpr-accent-secondary'      => '#26c6da',
                    '--wpr-accent-secondary-dark' => '#00acc1',
                    // Neutrals (override the inherits)
                    '--wpr-text-primary'          => '#0f2027',
                    '--wpr-text-secondary'        => '#2c5364',
                    '--wpr-bg-surface'            => '#ffffff',
                    '--wpr-bg-subtle'             => '#f0fdfa',
                    '--wpr-border-color'          => '#cfdbf5',
                    // Shape & motion — large radius, soft shadow, airy cards
                    '--wpr-radius'                => '20px',
                    '--wpr-radius-sm'             => '12px',
                    '--wpr-shadow'                => '0 10px 30px rgba(0, 188, 212, 0.10)',
                    '--wpr-shadow-lg'             => '0 24px 60px rgba(0, 188, 212, 0.18)',
                    '--wpr-card-padding'          => '24px',
                    '--wpr-card-hover-transform'  => 'translateY(-4px)',
                    // Buttons — pill, lighter weight
                    '--wpr-btn-padding-x'         => '26px',
                    '--wpr-btn-padding-y'         => '14px',
                    '--wpr-btn-weight'            => '500',
                    '--wpr-btn-letter-spacing'    => '0.04em',
                    // Status tuned to the palette
                    '--wpr-live-color'            => '#00bcd4',
                    '--wpr-progress-high'         => '#00bcd4',
                ),
            ),
            'golf' => array(
                'name'        => 'Golf',
                'description' => 'Fresh fairway green on white — clean, sporty, lots of air.',
                'vars' => array(
                    '--wpr-accent'                => '#2e7d32',
                    '--wpr-accent-dark'           => '#1b5e20',
                    '--wpr-accent-bg'             => '#e8f5e9',
                    '--wpr-accent-border'         => '#a5d6a7',
                    '--wpr-accent-text'           => '#2e7d32',
                    '--wpr-accent-text-dark'      => '#1b5e20',
                    '--wpr-accent-secondary'      => '#43a047',
                    '--wpr-accent-secondary-dark' => '#388e3c',
                    '--wpr-text-primary'          => '#1b2e1c',
                    '--wpr-text-secondary'        => '#3e5641',
                    '--wpr-bg-surface'            => '#ffffff',
                    '--wpr-bg-subtle'             => '#f3faf3',
                    '--wpr-border-color'          => '#d7e9d7',
                    '--wpr-radius'                => '14px',
                    '--wpr-radius-sm'             => '10px',
                    '--wpr-shadow'                => '0 4px 14px rgba(46, 125, 50, 0.08)',
                    '--wpr-shadow-lg'             => '0 14px 36px rgba(46, 125, 50, 0.14)',
                    '--wpr-card-padding'          => '22px',
                    '--wpr-btn-padding-x'         => '22px',
                    '--wpr-btn-padding-y'         => '13px',
                    '--wpr-btn-weight'            => '600',
                    '--wpr-live-color'            => '#2e7d32',
                    '--wpr-progress-high'         => '#2e7d32',
                ),
            ),
            'car' => array(
                'name'        => 'Car',
                'description' => 'Bold crimson with strong shadows — energetic, automotive.',
                'vars' => array(
                    '--wpr-accent'                => '#d32f2f',
                    '--wpr-accent-dark'           => '#b71c1c',
                    '--wpr-accent-bg'             => '#ffebee',
                    '--wpr-accent-border'         => '#ef9a9a',
                    '--wpr-accent-text'           => '#c62828',
                    '--wpr-accent-text-dark'      => '#b71c1c',
                    '--wpr-accent-secondary'      => '#e53935',
                    '--wpr-accent-secondary-dark' => '#c62828',
                    '--wpr-text-primary'          => '#1a0606',
                    '--wpr-text-secondary'        => '#4a1414',
                    '--wpr-bg-surface'            => '#ffffff',
                    '--wpr-bg-subtle'             => '#fff5f5',
                    '--wpr-border-color'          => '#f5d6d6',
                    '--wpr-radius'                => '14px',
                    '--wpr-radius-sm'             => '10px',
                    '--wpr-shadow'                => '0 8px 22px rgba(211, 47, 47, 0.16)',
                    '--wpr-shadow-lg'             => '0 20px 48px rgba(211, 47, 47, 0.24)',
                    '--wpr-card-padding'          => '20px',
                    '--wpr-card-hover-transform'  => 'translateY(-5px)',
                    '--wpr-btn-padding-x'         => '24px',
                    '--wpr-btn-padding-y'         => '14px',
                    '--wpr-btn-weight'            => '700',
                    '--wpr-btn-hover-transform'   => 'translateY(-2px)',
                    '--wpr-live-color'            => '#d32f2f',
                    '--wpr-progress-high'         => '#d32f2f',
                ),
            ),
            'retro' => array(
                'name'        => 'Retro',
                'description' => 'Electric purple, chunky shapes, hard shadows — gaming, tech, retro prizes.',
                'vars' => array(
                    '--wpr-accent'                => '#7c3aed',
                    '--wpr-accent-dark'           => '#6d28d9',
                    '--wpr-accent-bg'             => '#f5f3ff',
                    '--wpr-accent-border'         => '#c4b5fd',
                    '--wpr-accent-text'           => '#6d28d9',
                    '--wpr-accent-text-dark'      => '#4c1d95',
                    '--wpr-accent-secondary'      => '#a855f7',
                    '--wpr-accent-secondary-dark' => '#8b5cf6',
                    '--wpr-text-primary'          => '#1e1033',
                    '--wpr-text-secondary'        => '#3b2960',
                    '--wpr-bg-surface'            => '#ffffff',
                    '--wpr-bg-subtle'             => '#faf5ff',
                    '--wpr-border-color'          => '#e9d5ff',
                    // Chunky: small radius, hard offset shadow (8-bit vibe)
                    '--wpr-radius'                => '8px',
                    '--wpr-radius-sm'             => '4px',
                    '--wpr-shadow'                => '4px 4px 0 #1e1033',
                    '--wpr-shadow-lg'             => '6px 6px 0 #4c1d95',
                    '--wpr-card-padding'          => '18px',
                    '--wpr-card-hover-transform'  => 'translate(-2px, -2px)',
                    '--wpr-btn-padding-x'         => '22px',
                    '--wpr-btn-padding-y'         => '14px',
                    '--wpr-btn-weight'            => '800',
                    '--wpr-btn-letter-spacing'    => '0.06em',
                    '--wpr-btn-hover-transform'   => 'translate(-2px, -2px)',
                    '--wpr-letter-spacing'        => '0.04em',
                    '--wpr-font-heading-weight'   => '800',
                    '--wpr-live-color'            => '#a855f7',
                    '--wpr-progress-high'         => '#7c3aed',
                ),
            ),
            'elite' => array(
                'name'        => 'Elite',
                'description' => 'Premium red on black with sharp edges and gold accents — luxury & high-end.',
                'vars' => array(
                    '--wpr-accent'                => '#c80a0a',
                    '--wpr-accent-dark'           => '#a00808',
                    '--wpr-accent-bg'             => '#2a0606',
                    '--wpr-accent-border'         => '#5a0c0c',
                    '--wpr-accent-text'           => '#fca5a5',
                    '--wpr-accent-text-dark'      => '#fecaca',
                    '--wpr-accent-secondary'      => '#ef5350',
                    '--wpr-accent-secondary-dark' => '#e53935',
                    // Dark surface + light text
                    '--wpr-text-primary'          => '#f5f5f7',
                    '--wpr-text-secondary'        => '#c7c7cc',
                    '--wpr-text-muted'            => '#9a9aa0',
                    '--wpr-text-light'             => '#6e6e76',
                    '--wpr-text-inverse'          => '#0b0b0d',
                    '--wpr-bg-surface'            => '#0b0b0d',
                    '--wpr-bg-subtle'             => '#15151a',
                    '--wpr-bg-muted'              => '#1d1d24',
                    '--wpr-border-color'          => 'rgba(255,255,255,0.08)',
                    '--wpr-border-strong'         => 'rgba(255,255,255,0.16)',
                    // Sharp, deep, luxe
                    '--wpr-radius'                => '4px',
                    '--wpr-radius-sm'             => '2px',
                    '--wpr-shadow'                => '0 8px 32px rgba(200, 10, 10, 0.18)',
                    '--wpr-shadow-lg'             => '0 20px 60px rgba(0, 0, 0, 0.55)',
                    '--wpr-card-padding'          => '22px',
                    '--wpr-btn-padding-x'         => '24px',
                    '--wpr-btn-padding-y'         => '13px',
                    '--wpr-btn-weight'            => '700',
                    '--wpr-btn-letter-spacing'    => '0.08em',
                    '--wpr-font-heading-weight'   => '700',
                    '--wpr-letter-spacing'        => '0.03em',
                    '--wpr-live-color'            => '#ff3b3b',
                    '--wpr-progress-high'         => '#c80a0a',
                ),
            ),
        );
    }

    /**
     * Get the saved styling settings.
     */
    public static function get_settings() {
        return wp_parse_args( get_option( 'wpraffle_styling_settings', array() ), array(
            'preset'                 => 'diamonds',
            'custom_accent'          => '',
            'custom_accent_dark'     => '',
            'custom_text'            => '',
            'custom_bg'              => '',
            'disable_custom_styling' => '0',
        ) );
    }

    /**
     * Output the inline CSS for the selected preset + custom overrides.
     * Hooked on wp_head.
     */
    public static function output_inline_css() {
        $settings = self::get_settings();

        // Skip outputting inline styles if custom styling is disabled (inheriting theme defaults only)
        if ( ! empty( $settings['disable_custom_styling'] ) && $settings['disable_custom_styling'] === '1' ) {
            return;
        }

        $presets  = self::get_presets();
        $preset   = $settings['preset'];
        $vars     = array();

        // Start with preset defaults
        if ( isset( $presets[ $preset ]['vars'] ) ) {
            $vars = $presets[ $preset ]['vars'];
        }

        // Apply custom overrides
        if ( $settings['custom_accent'] ) {
            $vars['--wpr-accent'] = $settings['custom_accent'];
        }
        if ( $settings['custom_accent_dark'] ) {
            $vars['--wpr-accent-dark'] = $settings['custom_accent_dark'];
        }
        if ( $settings['custom_text'] ) {
            $vars['--wpr-text-primary'] = $settings['custom_text'];
        }
        if ( $settings['custom_bg'] ) {
            $vars['--wpr-bg-surface'] = $settings['custom_bg'];
        }

        if ( empty( $vars ) ) {
            return;
        }

        // Build the :root override
        $css_vars = '';
        foreach ( $vars as $var => $value ) {
            $css_vars .= $var . ':' . $value . ';';
        }

        $css = ':root{' . $css_vars . '}';

        if ( wp_style_is( 'raffle-public', 'enqueued' ) || wp_style_is( 'raffle-public', 'registered' ) ) {
            wp_add_inline_style( 'raffle-public', $css );
        } else {
            add_action( 'wp_head', function() use ($css) {
                echo '<style id="wpraffle-styling">' . $css . '</style>' . "\n";
            }, 100 );
        }
    }

    /**
     * Get the full list of CSS variables for developer documentation.
     */
    public static function get_variable_docs() {
        return array(
            'Neutrals' => array(
                '--wpr-text-primary'   => 'Primary text colour (headings, important text)',
                '--wpr-text-secondary' => 'Secondary text colour (descriptions)',
                '--wpr-text-muted'     => 'Muted text colour (metadata, labels)',
                '--wpr-text-light'     => 'Light text colour (timestamps)',
                '--wpr-text-inverse'   => 'Text colour on dark/coloured backgrounds',
                '--wpr-text-dark'      => 'Dark text variant for dark sections',
                '--wpr-bg-surface'     => 'Card/panel background',
                '--wpr-bg-subtle'      => 'Subtle background (empty states)',
                '--wpr-bg-muted'       => 'Muted background (code blocks)',
                '--wpr-border-color'   => 'Standard border colour',
                '--wpr-border-strong'  => 'Stronger border colour (accordion edges)',
            ),
            'Semantic' => array(
                '--wpr-success'        => 'Success green (buttons, badges)',
                '--wpr-success-dark'   => 'Darker green (hover states)',
                '--wpr-danger'         => 'Danger red (sold out, errors)',
                '--wpr-danger-dark'    => 'Darker red (hover)',
                '--wpr-warning'        => 'Warning amber (caution)',
                '--wpr-warning-light'  => 'Lighter amber',
            ),
            'Brand' => array(
                '--wpr-accent'         => 'Primary brand/accent colour',
                '--wpr-accent-dark'    => 'Darker accent (button hover)',
                '--wpr-accent-bg'      => 'Light accent background tint',
                '--wpr-accent-border'  => 'Accent border tint',
                '--wpr-accent-text'    => 'Accent text colour',
                '--wpr-accent-text-dark' => 'Dark accent text',
            ),
            'Status' => array(
                '--wpr-live-color'     => 'Live competition colour (green)',
                '--wpr-live-bg'        => 'Live competition background',
                '--wpr-draw-color'     => 'Draw countdown colour (orange)',
                '--wpr-progress-low'   => 'Progress bar: low sales (red)',
                '--wpr-progress-mid'   => 'Progress bar: medium sales (amber)',
                '--wpr-progress-high'  => 'Progress bar: high sales (green)',
            ),
        );
    }
}