<?php
/**
 * Plugin Name: WP Internal Link Automator
 * Plugin URI:  https://github.com/ikoanti/wp-internal-link-automator
 * Description: Automatically links specific keywords in post content to defined URLs. Uses a "First Match Only" logic to prevent SEO over-optimization.
 * Version:     1.1.0
 * Author:      ikoanti
 * Author URI:  https://github.com/ikoanti
 * License:     GNU GENERAL PUBLIC LICENSE
 */


if (!defined('ABSPATH')) exit;

class WP_Internal_Link_Automator {

    public function __construct() {
        // 1. Hook the Backend Menu
        add_action('admin_menu', [$this, 'iko_add_admin_menu']);
        add_action('admin_init', [$this, 'iko_register_settings']);

        // 2. Hook the Frontend Linker (Priority 99)
        add_filter('the_content', [$this, 'iko_process_content'], 99);
    }

    // --- BACKEND LOGIC ---

    public function iko_add_admin_menu() {
        add_menu_page(
            'SEO Linker',           // Page Title
            'SEO Linker',           // Menu Title
            'manage_options',       // Capability
            'seo-linker-settings',  // Menu Slug
            [$this, 'iko_render_admin_page'],
            'dashicons-admin-links' // Icon
        );
    }

    public function iko_register_settings() {
        register_setting('wila_settings_group', 'wila_keywords_list');
    }

    public function iko_render_admin_page() {
        ?>
        <div class="wrap">
            <h1>WP Internal Link Automator <span style="font-size:12px; color:#999;">v1.1</span></h1>
            <form method="post" action="options.php">
                <?php settings_fields('wila_settings_group'); ?>
                <?php do_settings_sections('wila_settings_group'); ?>
                
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Keyword Rules<br><small>(One per line: Keyword|URL)</small></th>
                        <td>
                            <textarea name="wila_keywords_list" rows="10" cols="50" class="large-text" placeholder="Example: WordPress SEO|https://site.com/seo-guide/"><?php echo esc_textarea(get_option('wila_keywords_list')); ?></textarea>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Save SEO Rules'); ?>
            </form>
        </div>
        <?php
    }

    // --- FRONTEND ENGINE ---

    public function iko_process_content($content) {
        if (!is_singular() || !is_main_query() || is_admin()) return $content;

        $raw_rules = get_option('wila_keywords_list');
        if (empty($raw_rules)) return $content;

        // Parse the textarea lines into an array
        $lines = explode("\n", str_replace("\r", "", $raw_rules));
        $rules = [];
        foreach ($lines as $line) {
            $parts = explode('|', $line);
            if (count($parts) === 2) {
                $rules[trim($parts[0])] = trim($parts[1]);
            }
        }

        // Sort by length to protect long-tail keywords
        uksort($rules, function($a, $b) { return strlen($b) - strlen($a); });

        foreach ($rules as $keyword => $url) {
            $quoted = preg_quote($keyword, '/');
            // Regex prevents linking inside <a> tags or HTML attributes
            $pattern = '/(?!(?:[^<]*<[^>]+>)*[^<]*<\/a>)(?![^<]*>)\b' . $quoted . '\b/i';
            $content = preg_replace($pattern, '<a href="' . esc_url($url) . '" class="auto-link">' . esc_html($keyword) . '</a>', $content, 1);
        }

        return $content;
    }
}

new WP_Internal_Link_Automator();
