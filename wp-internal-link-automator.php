<?php
/**
 * Plugin Name: WP Internal Link Automator
 * Plugin URI:  https://github.com/ikoanti/wp-internal-link-automator
 * Description: Automatically links specific keywords in post content to defined URLs. Uses a "First Match Only" logic to prevent SEO over-optimization.
 * Version:     1.4.0
 * Author:      ikoanti
 * Author URI:  https://github.com/ikoanti
 * License:     GNU GENERAL PUBLIC LICENSE
 */

if (!defined('ABSPATH'))
    exit;

class WP_Internal_Link_Automator
{

    public function __construct()
    {
        add_action('admin_menu', [$this, 'iko_add_admin_menu']);
        add_action('admin_init', [$this, 'iko_register_settings']);
        
        // Add plugin action links
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), [$this, 'iko_add_plugin_action_links']);

        // Priority 999: Run even later to beat SEO plugins
        add_filter('the_content', [$this, 'iko_process_content'], 999);
    }

    public function iko_add_admin_menu()
    {
        add_menu_page('SEO Linker', 'SEO Linker', 'manage_options', 'seo-linker-settings', [$this, 'iko_render_admin_page'], 'dashicons-admin-links');
    }

    public function iko_add_plugin_action_links($links)
    {
        $settings_link = '<a href="admin.php?page=seo-linker-settings">' . __('Settings', 'wp-internal-link-automator') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    public function iko_register_settings()
    {
        register_setting('wila_settings_group', 'wila_keywords_list', [
            'sanitize_callback' => [$this, 'iko_sanitize_keywords_list']
        ]);
        
        register_setting('wila_settings_group', 'wila_open_new_tab');
        register_setting('wila_settings_group', 'wila_apply_post_types', [
            'sanitize_callback' => [$this, 'iko_sanitize_post_types']
        ]);
    }

    public function iko_sanitize_post_types($input)
    {
        if (empty($input) || !is_array($input)) {
            return [];
        }
        return array_map('sanitize_text_field', $input);
    }

    public function iko_sanitize_keywords_list($input)
    {
        if (empty($input)) {
            update_option('wila_parsed_rules', []);
            return '';
        }

        $lines = explode("\n", str_replace("\r", "", $input));
        $valid_lines = [];
        $invalid_lines = [];
        $parsed_rules = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line))
                continue;

            $parts = explode('|', $line);
            if (count($parts) === 2 && !empty(trim($parts[0])) && !empty(trim($parts[1]))) {
                $keyword = trim($parts[0]);
                $url = trim($parts[1]);
                $valid_lines[] = $keyword . '|' . $url;
                $parsed_rules[$keyword] = $url;
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

        // Pre-sort by length descending to prevent partial word overlap bugs on frontend
        uksort($parsed_rules, function ($a, $b) {
            return mb_strlen($b) - mb_strlen($a);
        });

        update_option('wila_parsed_rules', $parsed_rules);

        return implode("\n", $valid_lines);
    }

    public function iko_render_admin_page()
    {
        // Safe defaults fallback
        $post_types = function_exists('get_post_types') ? get_post_types(['public' => true], 'objects') : [];
        $selected_post_types = get_option('wila_apply_post_types', ['post', 'page']);
        ?>
        <div class="wrap">
            <h1>SEO Linker</h1>
            <?php settings_errors('wila_settings_group'); ?>
            <form method="post" action="options.php">
                <?php settings_fields('wila_settings_group'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="wila_keywords_list">Keyword Linking Rules</label></th>
                        <td>
                            <textarea name="wila_keywords_list" id="wila_keywords_list" rows="10" cols="50" class="large-text code" placeholder="Keyword|https://example.com/url"><?php echo esc_textarea(get_option('wila_keywords_list')); ?></textarea>
                            <p class="description"><strong>Format:</strong> <code>Keyword|URL</code> (One per line). Ensure there are no spaces around the <code>|</code> symbol.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Link Target</th>
                        <td>
                            <label>
                                <input type="checkbox" name="wila_open_new_tab" value="1" <?php checked(get_option('wila_open_new_tab'), '1'); ?> />
                                Open links in a new tab (<code>target="_blank"</code>)
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Apply to Post Types</th>
                        <td>
                            <fieldset>
                                <?php if (!empty($post_types)) : ?>
                                    <?php foreach ($post_types as $pt) : ?>
                                        <label style="display:block; margin-bottom:4px;">
                                            <input type="checkbox" name="wila_apply_post_types[]" value="<?php echo esc_attr($pt->name); ?>" <?php checked(in_array($pt->name, (array)$selected_post_types)); ?> />
                                            <?php echo esc_html($pt->labels->name); ?>
                                        </label>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p>No public post types found.</p>
                                <?php endif; ?>
                            </fieldset>
                            <p class="description">Select which post types the automator logic should run on.</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    public function iko_process_content($content)
    {
        // 1. Only run in the main query to avoid altering sidebars and widgets
        if (!in_the_loop() || !is_main_query())
            return $content;

        $target_post_types = get_option('wila_apply_post_types', ['post', 'page']);
        if (!is_singular($target_post_types) || is_admin())
            return $content;

        // Try getting parsed rules, fallback to building them securely
        $rules = get_option('wila_parsed_rules');
        if (!is_array($rules) || empty($rules)) {
            $raw_rules = get_option('wila_keywords_list');
            if (empty($raw_rules)) {
                return $content;
            }
            $this->iko_sanitize_keywords_list($raw_rules);
            $rules = get_option('wila_parsed_rules');
        }

        if (empty($rules))
            return $content;

        $open_new_tab = get_option('wila_open_new_tab') === '1';
        $target_html = $open_new_tab ? ' target="_blank" rel="noopener"' : '';

        foreach ($rules as $keyword => $url) {
            if (empty($keyword) || empty($url))
                continue;

            $chunks = wp_html_split($content);
            $new_content = '';
            $inside_ignore_tag = 0;
            $replaced = false;

            foreach ($chunks as $chunk) {
                // If chunk is a tag, observe depth of a, script, style, textarea, h1-h6
                if (strpos($chunk, '<') === 0) {
                    if (preg_match('#^<(a|script|style|textarea|h1|h2|h3|h4|h5|h6)(?:\s|>)#i', $chunk)) {
                        $inside_ignore_tag++;
                    }
                    elseif (preg_match('#^</(a|script|style|textarea|h1|h2|h3|h4|h5|h6)\s*>#i', $chunk)) {
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

                    $chunk = preg_replace_callback($pattern, function ($matches) use ($url, $target_html) {
                        return '<a href="' . esc_url($url) . '" class="auto-link"' . $target_html . '>' . esc_html($matches[0]) . '</a>';
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
new WP_Internal_Link_Automator();
