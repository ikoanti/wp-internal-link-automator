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

class WP_Internal_Link_Automator_V1_1 {

    public function __construct() {
        // Priority 99 ensures it runs after shortcodes and other plugins
        add_filter('the_content', [$this, 'iko_process_content'], 99);
    }

    /**
     * The core logic for version 1.1
     */
    public function iko_process_content($content) {
        // 1. Context Check: Only run on single posts/pages and the main content area
        if (!is_singular() || !is_main_query() || is_admin()) {
            return $content;
        }

        // 2. Fetch your keyword mapping
        // Structure should be: ['keyword' => 'https://yoursite.com/target-page']
        $link_rules = get_option('iko_internal_links_rules', []);

        if (empty($link_rules)) {
            return $content;
        }

        // 3. Sort keywords by length (Longest first) 
        // This is a pro-SEO move: prevents "WordPress" from breaking "WordPress SEO"
        uksort($link_rules, function($a, $b) {
            return strlen($b) - strlen($a);
        });

        foreach ($link_rules as $keyword => $url) {
            $url = esc_url($url);
            $quoted_keyword = preg_quote($keyword, '/');

            /* * REGEX EXPLANATION:
             * (?!(?:[^<]*<[^>]+>)*[^<]*<\/a>) -> Negative lookahead to skip existing <a> tags
             * (?![^<]*>)                       -> Ensures we aren't inside an HTML attribute (like alt or title)
             * \b ... \b                        -> Matches whole words only
             */
            $pattern = '/(?!(?:[^<]*<[^>]+>)*[^<]*<\/a>)(?![^<]*>)\b' . $quoted_keyword . '\b/i';

            // Limit: Replace only the first 2 occurrences to maintain natural SEO balance
            $content = preg_replace($pattern, '<a href="' . $url . '" class="auto-internal-link">' . $keyword . '</a>', $content, 2);
        }

        return $content;
    }
}

// Initialize the version 1.1
new WP_Internal_Link_Automator_V1_1();
