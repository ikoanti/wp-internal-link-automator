<?php
/**
 * Plugin Name: WP Internal Link Automator
 * Plugin URI:  https://github.com/ikoanti/wp-internal-link-automator
 * Description: Automatically links specific keywords in post content to defined URLs. Uses a "First Match Only" logic to prevent SEO over-optimization.
 * Version:     1.0.0
 * Author:      ikoanti
 * Author URI:  https://github.com/ikoanti
 * License:     GNU GENERAL PUBLIC LICENSE
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class WP_Internal_Link_Automator {

    public function __construct() {
        // Add admin menu
        add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
        // Register settings
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        // The main SEO logic hook
        add_filter( 'the_content', array( $this, 'auto_link_keywords' ) );
    }
// CHANGED: Priority set to 99 to run AFTER themes/builders
        add_filter( 'the_content', array( $this, 'auto_link_keywords' ), 99 );
    }
    public function add_plugin_page() {
        add_options_page(
            'Internal Link Automator',
            'Link Automator',
            'manage_options',
            'wp-internal-link-automator',
            array( $this, 'create_admin_page' )
        );
    }

    public function register_settings() {
        register_setting( 'wila_settings_group', 'wila_keywords_list', 'sanitize_textarea_field' );
    }

    public function create_admin_page() {
        ?>
        <div class="wrap">
            <h1>Internal Link Automator Configuration</h1>
            <form method="post" action="options.php">
                <?php settings_fields( 'wila_settings_group' ); ?>
                <?php do_settings_sections( 'wila_settings_group' ); ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Keywords & URLs</th>
                        <td>
                            <p>Enter one pair per line using the format: <code>Keyword|URL</code></p>
                            <textarea name="wila_keywords_list" rows="10" cols="50" class="large-text code"><?php echo esc_attr( get_option('wila_keywords_list') ); ?></textarea>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    /**
     * The Core SEO Logic
     * Replaces keywords with links, but limits frequency to avoid spam flags.
     */
    public function auto_link_keywords( $content ) {
        // Only run on single posts/pages to save resources
        if ( ! is_singular() || is_admin() ) {
            return $content;
        }

        $raw_list = get_option( 'wila_keywords_list' );
        if ( empty( $raw_list ) ) {
            return $content;
        }

        $lines = explode( "\n", $raw_list );

        foreach ( $lines as $line ) {
            $parts = explode( '|', $line );
            if ( count( $parts ) !== 2 ) continue;

            $keyword = trim( $parts[0] );
            $url     = trim( $parts[1] );

            if ( empty( $keyword ) || empty( $url ) ) continue;

            // 1. Word Boundary Check (\b): Ensures we match "SEO" but not "Roseose"
            // 2. HTML Tag Avoidance: Simple lookaround to avoid linking inside existing tags (Basic implementation)
            // 3. Limit=1: Crucial for SEO. We only link the FIRST instance to avoid keyword stuffing.
            
            $pattern = '/\b(' . preg_quote( $keyword, '/' ) . ')\b(?![^<]*<\/a>)/i';
            $replacement = '<a href="' . esc_url( $url ) . '" title="' . esc_attr( $keyword ) . '">' . '$1' . '</a>';
            
            // limiting to 1 replacement per keyword
            $content = preg_replace( $pattern, $replacement, $content, 1 ); 
        }

        return $content;
    }
}

new WP_Internal_Link_Automator();
