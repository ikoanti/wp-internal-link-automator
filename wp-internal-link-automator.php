<?php
/**
 * Plugin Name: WP Internal Link Automator
 * Plugin URI:  https://github.com/ikoanti/wp-internal-link-automator
 * Description: Automatically links specific keywords in post content to defined URLs. Uses a "First Match Only" logic to prevent SEO over-optimization.
 * Version:     1.2.0
 * Author:      ikoanti
 * Author URI:  https://github.com/ikoanti
 * License:     GNU GENERAL PUBLIC LICENSE
 */


if (!defined('ABSPATH')) exit;

class WP_Internal_Link_Automator_V1_2 {

    public function __construct() {
        add_action('admin_menu', [$this, 'iko_add_admin_menu']);
        add_action('admin_init', [$this, 'iko_register_settings']);
        
        // Priority 999: Run even later to beat SEO plugins
        add_filter('the_content', [$this, 'iko_process_content'], 999);
    }

    public function iko_add_admin_menu() {
        add_menu_page('SEO Linker', 'SEO Linker', 'manage_options', 'seo-linker-settings', [$this, 'iko_render_admin_page'], 'dashicons-admin-links');
    }

    public function iko_register_settings() {
        register_setting('wila_settings_group', 'wila_keywords_list');
    }

    public function iko_render_admin_page() {
        ?>
        <div class="wrap">
            <h1>SEO Linker v1.2</h1>
            <form method="post" action="options.php">
                <?php settings_fields('wila_settings_group'); ?>
                <textarea name="wila_keywords_list" rows="10" cols="50" class="large-text" placeholder="Keyword|URL"><?php echo esc_textarea(get_option('wila_keywords_list')); ?></textarea>
                <?php submit_button(); ?>
            </form>
            <p><strong>Pro-Tip:</strong> Ensure there are no spaces around the <code>|</code> symbol.</p>
        </div>
        <?php
    }

    public function iko_process_content($content) {
        // 1. Remove the "is_main_query" check temporarily to see if your theme is causing the block
        if (!is_singular() || is_admin()) return $content;

        $raw_rules = get_option('wila_keywords_list');
        if (empty($raw_rules)) return $content;

        $lines = explode("\n", str_replace("\r", "", $raw_rules));
        $rules = [];
        foreach ($lines as $line) {
            $parts = explode('|', $line);
            if (count($parts) === 2) {
                $rules[trim($parts[0])] = trim($parts[1]);
            }
        }

        uksort($rules, function($a, $b) { return mb_strlen($b) - mb_strlen($a); });

        foreach ($rules as $keyword => $url) {
            $quoted = preg_quote($keyword, '/');
            
            /* * THE FIXES:
             * 1. Added 'u' flag at the end for UTF-8 support (Cyrillic/Georgian).
             * 2. Replaced \b with (?<!\p{L}) and (?!\p{L}) for universal word boundaries.
             */
            $pattern = '/(?!(?:[^<]*<[^>]+>)*[^<]*<\/a>)(?![^<]*>)(?<!\p{L})' . $quoted . '(?!\p{L})/iu';
            
            $content = preg_replace($pattern, '<a href="' . esc_url($url) . '" class="auto-link">' . esc_html($keyword) . '</a>', $content, 1);
        }

        return $content;
    }
}
new WP_Internal_Link_Automator_V1_2();
