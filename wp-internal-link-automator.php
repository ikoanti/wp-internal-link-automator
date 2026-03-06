<?php
/**
 * Plugin Name: WP Internal Link Automator
 * Plugin URI:  https://github.com/ikoanti/wp-internal-link-automator
 * Description: Automatically links specific keywords in post content to defined URLs. Uses a "First Match Only" logic to prevent SEO over-optimization.
 * Version:     1.3.0
 * Author:      ikoanti
 * Author URI:  https://github.com/ikoanti
 * License:     GNU GENERAL PUBLIC LICENSE
 */


if (!defined('ABSPATH'))
    exit;

class WP_Internal_Link_Automator_V1_3
{

    public function __construct()
    {
        add_action('admin_menu', [$this, 'iko_add_admin_menu']);
        add_action('admin_init', [$this, 'iko_register_settings']);

        // Priority 999: Run even later to beat SEO plugins
        add_filter('the_content', [$this, 'iko_process_content'], 999);
    }

    public function iko_add_admin_menu()
    {
        add_menu_page('SEO Linker', 'SEO Linker', 'manage_options', 'seo-linker-settings', [$this, 'iko_render_admin_page'], 'dashicons-admin-links');
    }

    public function iko_register_settings()
    {
        register_setting('wila_settings_group', 'wila_keywords_list', [
            'sanitize_callback' => [$this, 'iko_sanitize_keywords_list']
        ]);
    }

    public function iko_sanitize_keywords_list($input)
    {
        if (empty($input))
            return '';

        $lines = explode("\n", str_replace("\r", "", $input));
        $valid_lines = [];
        $invalid_lines = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line))
                continue;

            $parts = explode('|', $line);
            if (count($parts) === 2 && !empty(trim($parts[0])) && !empty(trim($parts[1]))) {
                $valid_lines[] = trim($parts[0]) . '|' . trim($parts[1]);
            }
            else {
                $invalid_lines[] = $line;
            }
        }

        if (!empty($invalid_lines)) {
            add_settings_error(
                'wila_settings_group',
                'wila_invalid_rules',
                'Some rules were invalid and have been removed: ' . esc_html(implode(', ', $invalid_lines)),
                'error'
            );
        }

        return implode("\n", $valid_lines);
    }

    public function iko_render_admin_page()
    {
?>
                                                                                                                                                                                                                                                                                                                                                                                                                <div class="wrap">
                                                                                                                                                                                                                                                                                                                                                                                                                            <h1>SEO Linker v1.3.0</h1>
                                                                                                                                                                                                                                                                                                                                                                                                                                        <?php settings_errors('wila_settings_group'); ?>
                                                                                                                                                                                                                                                                                                                                                                                                                                                    <form method="post" action="options.php">
                                                                                                                                                                                                                                                                                                                                                                                                                                                                    <?php settings_fields('wila_settings_group'); ?>
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                    <textarea name="wila_keywords_list" rows="10" cols="50" class="large-text" placeholder="Keyword|URL"><?php echo esc_textarea(get_option('wila_keywords_list')); ?></textarea>
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                    <?php submit_button(); ?>
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                </form>
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                            <p><strong>Pro-Tip:</strong> Ensure there are no spaces around the <code>|</code> symbol.</p>
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                    </div>
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                            <?php
    }

    public function iko_process_content($content)
    {
        // 1. Remove the "is_main_query" check temporarily to see if your theme is causing the block
        if (!is_singular() || is_admin())
            return $content;

        $raw_rules = get_option('wila_keywords_list');
        if (empty($raw_rules))
            return $content;

        $lines = explode("\n", str_replace("\r", "", $raw_rules));
        $rules = [];
        foreach ($lines as $line) {
            $parts = explode('|', $line);
            if (count($parts) === 2) {
                $rules[trim($parts[0])] = trim($parts[1]);
            }
        }

        uksort($rules, function ($a, $b) {
            return mb_strlen($b) - mb_strlen($a); });

        foreach ($rules as $keyword => $url) {
            if (empty($keyword) || empty($url))
                continue;

            $chunks = wp_html_split($content);
            $new_content = '';
            $inside_ignore_tag = 0;
            $replaced = false;

            foreach ($chunks as $chunk) {
                // If chunk is a tag, observe depth of a, script, style, textarea
                if (strpos($chunk, '<') === 0) {
                    if (preg_match('#^<(a|script|style|textarea)(?:\s|>)#i', $chunk)) {
                        $inside_ignore_tag++;
                    }
                    elseif (preg_match('#^</(a|script|style|textarea)\s*>#i', $chunk)) {
                        $inside_ignore_tag = max(0, $inside_ignore_tag - 1);
                    }
                    $new_content .= $chunk;
                    continue;
                }

                // If chunk is text and we are not inside an ignored tag
                if ($inside_ignore_tag === 0 && !$replaced && trim($chunk) !== '') {
                    $quoted = preg_quote($keyword, '/');
                    $pattern = '/(?<!\p{L})' . $quoted . '(?!\p{L})/iu';
                    $count = 0;

                    /* * THE FIXES:
                     * 1. Added 'u' flag at the end for UTF-8 support (Cyrillic/Georgian).
                     * 2. Replaced \b with (?<!\p{L}) and (?!\p{L}) for universal word boundaries.
                     * 3. Uses wp_html_split to avoid modifying HTML attributes or already linked text.
                     * 4. Uses preg_replace_callback to preserve original text capitalization.
                     */
                    $chunk = preg_replace_callback($pattern, function ($matches) use ($url) {
                        return '<a href="' . esc_url($url) . '" class="auto-link">' . esc_html($matches[0]) . '</a>';
                    }, $chunk, 1, $count);

                    if ($count > 0) {
                        $replaced = true;
                    }
                }

                $new_content .= $chunk;
            }

            $content = $new_content;
        }

        return $content;
    }
}
new WP_Internal_Link_Automator_V1_3();
