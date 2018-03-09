<?php
namespace GW\ContentLock;

if (!class_exists('\GW\ContentLock\Plugin')):

define('GW_CONTENTLOCK_PLUGIN_NAME', '\GW\ContentLock\Plugin');

class Plugin {
    static $config;

    static public function instance($base = null) {
        static $instance;
        if (null === $instance) {
            $instance = new Plugin();
            $instance->configure($base);
            $instance->listen();
        }
        return $instance;
    }

    public function configure($base) {
        global $wpdb;

        if (null === $base) {
            $base = __FILE__;
        }

        $config = Config::instance(GW_CONTENTLOCK_PLUGIN_NAME);
        $config->add('domain', 'gw');
        $config->add('min_version', '4.1');

        $config->add('basename', \plugin_basename(\plugin_dir_path($base) . 'gw-contentlock-shortcode.php'));
        $config->add('plugin_file', $base);
        $config->add('plugin_uri', \plugin_dir_url($base));
        $config->add('plugin_path', \plugin_dir_path($base));

        self::$config = $config;
    }

    public function listen() {
        \add_action('init', array(GW_CONTENTLOCK_PLUGIN_NAME, 'init'));
        \add_action('save_post', array(GW_CONTENTLOCK_PLUGIN_NAME, 'extractShortcodeAtts'), 10, 3);
        \add_action('wp_enqueue_scripts', array(GW_CONTENTLOCK_PLUGIN_NAME, 'enqueueScripts'));
        \add_action('wp_enqueue_scripts', array(GW_CONTENTLOCK_PLUGIN_NAME, 'enqueueStyle'));

        // set up ajax handler
        \add_action('wp_ajax_contentlock_update', array(GW_CONTENTLOCK_PLUGIN_NAME, 'ajaxUpdateContent'));

        // set up render hooks
        \add_action('contentlock-save_post_shortcodes', array(GW_CONTENTLOCK_PLUGIN_NAME, 'saveContentLocksInShortcodes'), 10, 3);
        \add_action('contentlock-render_header_locked', array(GW_CONTENTLOCK_PLUGIN_NAME, 'displayLockedHeader'), 10, 2);
        \add_action('contentlock-render_header_unlocked', array(GW_CONTENTLOCK_PLUGIN_NAME, 'displayUnlockedHeader'), 10, 2);
        \add_action('contentlock-render_content', array(GW_CONTENTLOCK_PLUGIN_NAME, 'displayUnlockedContent'), 10, 1);

        // use ONLY the default 'the_content' filters
        \add_filter('contentlock_main_content', 'wptexturize');
        \add_filter('contentlock_main_content', 'convert_smilies');
        \add_filter('contentlock_main_content', 'convert_chars');
        \add_filter('contentlock_main_content', 'wpautop');
        \add_filter('contentlock_main_content', 'shortcode_unautop');
        \add_filter('contentlock_main_content', 'prepend_attachment');
    }

    static public function init() {
        \add_shortcode('timelock', array(GW_CONTENTLOCK_PLUGIN_NAME, 'registerTimelockShortcode'));
    }

    static public function ajaxUpdateContent() {
        global $wpdb;

        $res = array(
            'status' => 200,
            'data' => null
        );

        $post = isset($_POST['post']) ? intval($_POST['post']) : null;
        $id = isset($_POST['id']) ? trim($_POST['id']) : null;

        if (null === $post || null === $id) {
            $res['status'] = 502;
        } else {
            $locks = \get_post_meta($post, '_timelocks_in_content', true);
            if (!isset($locks[$id])) {
                $res['status'] = 404;
            } else {
                $timelock = $locks[$id];
                $now = time();

                if ($timelock['release'] <= $now) {
                    ob_start();
                    self::getTemplate('content-unlocked.php', array(
                        'id' => $timelock['id'],
                        'since' => $timelock['release'],
                        'content' => $timelock['content']
                    ));

                    $buffer = ob_get_contents();
                    ob_end_clean();
                    $res['data'] = $buffer;
                } else {
                    $res['data'] = array(
                        'release' => $timelock['release'],
                        'now' => $now
                    );
                    $res['status'] = 204;
                }
            }
        }

        echo json_encode($res);
        \wp_die();
    }

    static public function displayLockedHeader($time) {
        $template = <<<EOT
Unlocking in
<span class="countdown">
    <span class="hours">%02d</span><span class="separator">:</span><span class="minutes">%02d</span><span class="separator">:</span><span class="seconds">%02d</span>
</span>
EOT;

        $hours = floor($time / 3600);
        $minutes = floor(($time % 3600) / 60);
        $seconds = ($time % 3600) % 60;

        $header = sprintf($template, $hours, $minutes, $seconds);

        // give custom display a chance to override
        echo \apply_filters('contentlock_locked_header', $header, $time, $hours, $minutes, $seconds);
    }

    static public function displayUnlockedHeader($since) {
        $header = sprintf('Unlocked since %s', date('M j, Y @ h:ia', $since));
        echo \apply_filters('contentlock_unlocked_header', $header, $since);
    }

    static public function displayUnlockedContent($content) {
        echo \apply_filters('contentlock_main_content', $content);
    }

    static public function registerTimelockShortcode($atts = [], $content = null, $tag = '') {
        $timelocks = \get_post_meta(\get_the_ID(), '_timelocks_in_content', true);

        $atts = \shortcode_atts(array(
            'id' => null,
            'release' => null,
            'mask' => null
        ), $atts, 'timelock');

        // Let's work some magic
        // Using the ID, let's pull the pre-parsed meta data for this timelock to render
        if (!isset($timelocks[$atts['id']])) {
            // if ID is not in meta, we cannot render
            return;
        }

        $timelock = $timelocks[$atts['id']];
        $now = time();

        ob_start();
        printf('<p>Release: %d</p>', $timelock['release']);
        printf('<p>Now: %d</p>', $now);
        if ($timelock['release'] >= $now) {
            // still locked, so render the mask
            self::getTemplate('content-locked.php', array(
                'post' => \get_the_ID(),
                'id' => $timelock['id'],
                'release' => $timelock['release'] - $now, // seconds remaining
                'mask' => $timelock['mask']
            ));
        } else {
            self::getTemplate('content-unlocked.php', array(
                'post' => \get_the_ID(),
                'id' => $timelock['id'],
                'since' => $timelock['release'],
                'content' => $timelock['content']
            ));
        }

        $buffer = ob_get_contents();
        ob_end_clean();
        return $buffer;
    }

    // Pull out only the "timelock" shortcodes, validate, and save to post meta
    static public function saveContentLocksInShortcodes($shortcode_data, $post_id, $post) {
        $content_locks = array();

        if (array_key_exists('timelock', $shortcode_data)) {
            $locks = array_map(array(GW_CONTENTLOCK_PLUGIN_NAME, 'sanitizeTimelockAttributes'), $shortcode_data['timelock']);
            // de-dupe locks
            foreach ($locks as $lock) {
                if (!is_null($lock['id']) && !isset($content_locks[$lock['id']])) {
                    $content_locks[$lock['id']] = $lock;
                }
            }
        }

        if (count($content_locks) > 0) {
            \update_post_meta($post_id, '_timelocks_in_content', $content_locks);
        } else {
            \delete_post_meta($post_id, '_timelocks_in_content');
        }
    }

    // Check for validly formatted timelock attributes
    static public function sanitizeTimelockAttributes($timelock) {
        $id = isset($timelock['id']) ? \sanitize_text_field($timelock['id']) : null;
        $release = isset($timelock['release']) ? strtotime($timelock['release']) : null;
        $mask = isset($timelock['mask']) ? \sanitize_text_field($timelock['mask']) : null;

        if (is_null($id) || is_null($release) || is_null($mask)) {
            return null;
        }

        return array(
            'id' => $id,
            'release' => $release,
            'mask' => $mask,
            'content' => $timelock['content']
        );
    }

    // parse shortcodes and do the action for any other hooks
    static public function extractShortcodeAtts($post_id, $post, $update) {
        if ('auto-draft' === $post->post_status || 'revision' === $post->post_type) {
            return;
        }

        $shortcode_regex = \get_shortcode_regex();
        $shortcode_data  = self::parseShortcodes($shortcode_regex, $post->post_content);

        \do_action('contentlock-save_post_shortcodes', $shortcode_data, $post_id, $post);
    }

    static public function parseShortcodes($regex, $content, $existing = array(), $parent_tags = array()) {
        if (is_array($content)) {
            $content = implode(' ', $content);
        }

        $count = preg_match_all("/$regex/", $content, $matches);

        if ($count) {
            // reindex
            $parent_tags = array_values($parent_tags);

            foreach ($matches[3] as $index => $attributes) {
                if (empty($existing[$matches[2][$index]])) {
                    $existing[$matches[2][$index]] = array();
                }

                $shortcode_data = \shortcode_parse_atts($attributes);

                if (!empty($parent_tags[$index])) {
                    $parent_tag = array( 'parent_shortcode' => $parent_tags[$index] );
                    $shortcode_data = array_merge($shortcode_data, $parent_tag);
                }

                if (!empty($matches[5][$index])) {
                    $content = array( 'content' => $matches[5][$index] );
                    $shortcode_data = array_merge($shortcode_data, $content);
                }

                $existing[$matches[2][$index]][] = $shortcode_data;
            }

            foreach ($matches[5] as $index => $parent_content) {
                $child_count = preg_match_all("/$regex/", $parent_content, $child_matches);
                if (!$child_count) {
                    unset($matches[2][$index]);
                    unset($matches[5][$index]);
                }
            }

            return self::parseShortcodes($regex, $matches[5], $existing, $matches[2]);
        } else {
            return $existing;
        }
    }

    static public function locateTemplate($template_name, $template_path = '', $default_path = '') {
        // set variable to search in contentlock-plugin-templates folder of theme
        if (!$template_path) {
            $template_path = 'contentlock-plugin-templates/';
        }

        // set default plugin templates path
        if (!$default_path) {
            $default_path = self::$config->plugin_path . 'templates/'; // path to the templates folder
        }

        // search for template file in theme folder
        $template = \locate_template(array(
            $template_path . $template_name,
            $template_name
        ));

        // get plugins template file
        if (!$template) {
            $template = $default_path . $template_name;
        }

        return \apply_filters('contentlock_locate_template', $template, $template_name, $template_path, $default_path);
    }

    static public function getTemplate($template_name, $args = array(), $template_path = '', $default_path = '') {
        if (is_array($args) && isset($args)) {
            extract($args);
        }

        $template_file = self::locateTemplate($template_name, $template_path, $default_path);

        if (!file_exists($template_file)) {
            \_doing_it_wrong(__FUNCTION__, sprintf('<code>%s</code> does not exist.', $template_file), self::$config->version);
            return;
        }

        include $template_file;
    }

    static public function enqueueScripts() {
        if (!is_admin()) {
            \wp_register_script('contentlock-js', self::$config->plugin_uri . '/js/contentlock.js', array('jquery'), null, true);
            \wp_enqueue_script('contentlock-js');
            \wp_localize_script('contentlock-js', 'ajax_object', array('ajax_url' => admin_url('admin-ajax.php'), 'action' => 'contentlock_update'));
        }
    }

    static public function enqueueStyle() {
        if (!is_admin()) {
            \wp_register_style('contentlock-css', self::$config->plugin_uri . '/css/contentlock.css', array(), null, 'screen');
            \wp_enqueue_style('contentlock-css');

            \wp_register_style('fontawesome', 'https://use.fontawesome.com/releases/v5.0.6/css/all.css', array(), null, 'screen');
            \wp_enqueue_style('fontawesome');
        }
    }

    // safer GUID generation
    static public function generateGuid() {
        if (function_exists('com_create_guid') === true) {
            return trim(com_create_guid(), '{}');
        }

        $data = openssl_random_pseudo_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    static public function logIt($message) {
        if (WP_DEBUG === true) {
            if (is_array($message) || is_object($message)) {
                error_log(print_r($message, true));
            } else {
                error_log($message);
            }
        }
    }
}

endif;
