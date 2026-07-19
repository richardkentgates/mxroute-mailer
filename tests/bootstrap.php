<?php
/**
 * MXRoute Mailer Test Bootstrap
 *
 * Mocks WordPress functions for unit testing without a full WP installation.
 *
 * @package MXRoute_Mailer
 */

$plugin_dir = dirname(__DIR__);

// Store for mocked options
$GLOBALS['wp_options'] = array();

// Store for mocked transients
$GLOBALS['wp_transients'] = array();

// Store for mocked DB operations
$GLOBALS['wp_db_inserts'] = array();
$GLOBALS['wp_db_queries'] = array();

// Track function calls
$GLOBALS['wp_function_calls'] = array();

// Persistent hook registry (survives setUp resets — tracks singleton registrations)
$GLOBALS['wp_hooks_registered'] = array();

// Mock WordPress functions
if (!function_exists('add_filter')) {
    function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) {
        $GLOBALS['wp_function_calls']['add_filter'][] = compact('hook', 'callback', 'priority');
        $GLOBALS['wp_hooks_registered']['filter'][] = $hook;
    }
}

if (!function_exists('add_action')) {
    function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {
        $GLOBALS['wp_function_calls']['add_action'][] = compact('hook', 'callback', 'priority');
        $GLOBALS['wp_hooks_registered']['action'][] = $hook;
    }
}

if (!function_exists('do_action')) {
    function do_action($hook) {
        $args = array_slice(func_get_args(), 1);
        $GLOBALS['wp_function_calls']['do_action'][] = compact('hook', 'args');
    }
}

if (!function_exists('get_option')) {
    function get_option($option, $default = false) {
        return isset($GLOBALS['wp_options'][$option]) ? $GLOBALS['wp_options'][$option] : $default;
    }
}

if (!function_exists('update_option')) {
    function update_option($option, $value, $autoload = null) {
        $GLOBALS['wp_options'][$option] = $value;
        return true;
    }
}

if (!function_exists('delete_option')) {
    function delete_option($option) {
        unset($GLOBALS['wp_options'][$option]);
        return true;
    }
}

if (!function_exists('register_setting')) {
    function register_setting($option_group, $option_name, $args = array()) {
        $GLOBALS['wp_function_calls']['register_setting'][] = compact('option_group', 'option_name', 'args');
    }
}

if (!function_exists('settings_fields')) {
    function settings_fields($option_group) {
        echo '<input type="hidden" name="option_group" value="' . esc_attr($option_group) . '" />';
        wp_nonce_field($option_group . '-options');
    }
}

if (!function_exists('submit_button')) {
    function submit_button($text = 'Submit', $type = 'primary', $name = 'submit', $wrap = true, $other_attributes = '') {
        echo '<input type="submit" name="' . esc_attr($name) . '" class="button-' . esc_attr($type) . '" value="' . esc_attr($text) . '" ' . $other_attributes . ' />';
    }
}

if (!function_exists('wp_parse_args')) {
    function wp_parse_args($args, $defaults = array()) {
        $args = (array) $args;
        $merged = $defaults;
        foreach ($args as $key => $value) {
            if (is_array($value) && is_array($merged[$key] ?? null)) {
                $merged[$key] = wp_parse_args($value, $merged[$key]);
            } else {
                $merged[$key] = $value;
            }
        }
        return $merged;
    }
}

if (!function_exists('sanitize_email')) {
    function sanitize_email($email) {
        if (!is_string($email)) {
            return '';
        }
        $email = trim(strip_tags($email));
        $email = preg_replace('/[^a-zA-Z0-9._%+-@]/', '', $email);
        return $email;
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) {
        return is_string($str) ? trim(strip_tags($str)) : '';
    }
}

if (!function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field($str) {
        return is_string($str) ? trim(strip_tags($str)) : '';
    }
}

if (!function_exists('wp_kses_post')) {
    function wp_kses_post($content) {
        return is_string($content) ? $content : '';
    }
}

if (!function_exists('wp_unslash')) {
    function wp_unslash($value) {
        if (is_array($value)) {
            return array_map('wp_unslash', $value);
        }
        return is_string($value) ? stripslashes($value) : $value;
    }
}

if (!function_exists('esc_html')) {
    function esc_html($text) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr($text) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data, $options = 0) {
        return json_encode($data, $options);
    }
}

if (!function_exists('wp_send_json_success')) {
    function wp_send_json_success($data = null, $status_code = 200) {
        $response = array('success' => true, 'data' => $data);
        $GLOBALS['wp_function_calls']['wp_send_json_success'][] = $response;
        throw new \MXRouteJSONException($response);
    }
}

if (!function_exists('wp_send_json_error')) {
    function wp_send_json_error($data = null, $status_code = 0) {
        $response = array('success' => false, 'data' => $data);
        $GLOBALS['wp_function_calls']['wp_send_json_error'][] = $response;
        throw new \MXRouteJSONException($response);
    }
}

if (!class_exists('MXRouteJSONException')) {
    class MXRouteJSONException extends \Exception {
        public $response;
        public function __construct($response) {
            $this->response = $response;
            parent::__construct(wp_json_encode($response));
        }
    }
}

if (!class_exists('WP_Error')) {
    class WP_Error {
        public $code;
        public $message;
        public $data;

        public function __construct($code = '', $message = '', $data = '') {
            $this->code = $code;
            $this->message = $message;
            $this->data = $data;
        }

        public function get_error_message() {
            return $this->message;
        }

        public function get_error_code() {
            return $this->code;
        }
    }
}

if (!function_exists('check_ajax_referer')) {
    function check_ajax_referer($action, $nonce = false) {
        $GLOBALS['wp_function_calls']['check_ajax_referer'][] = compact('action', 'nonce');
        return true;
    }
}

if (!function_exists('current_user_can')) {
    function current_user_can($capability) {
        if (isset($GLOBALS['wp_mock_current_user_can'])) {
            return $GLOBALS['wp_mock_current_user_can'];
        }
        return true;
    }
}

if (!function_exists('wp_verify_nonce')) {
    function wp_verify_nonce($nonce, $action) {
        if (isset($GLOBALS['wp_mock_nonce_verification'])) {
            return $GLOBALS['wp_mock_nonce_verification'];
        }
        $expected = 'test-nonce-' . md5($action);
        return $nonce === $expected ? 1 : 0;
    }
}

if (!function_exists('wp_nonce_field')) {
    function wp_nonce_field($action, $nonce_name = '_ajax_nonce', $referer = true, $display = true) {
        echo '<input type="hidden" name="' . esc_attr($nonce_name) . '" value="test-nonce" />';
    }
}

if (!function_exists('set_transient')) {
    function set_transient($name, $value, $expiration = 0) {
        $GLOBALS['wp_transients'][$name] = $value;
        return true;
    }
}

if (!function_exists('get_transient')) {
    function get_transient($name) {
        return isset($GLOBALS['wp_transients'][$name]) ? $GLOBALS['wp_transients'][$name] : false;
    }
}

if (!function_exists('delete_transient')) {
    function delete_transient($name) {
        unset($GLOBALS['wp_transients'][$name]);
        return true;
    }
}

if (!function_exists('set_site_transient')) {
    function set_site_transient($name, $value, $expiration = 0) {
        $GLOBALS['wp_transients'][$name] = $value;
        return true;
    }
}

if (!function_exists('get_site_transient')) {
    function get_site_transient($name) {
        return isset($GLOBALS['wp_transients'][$name]) ? $GLOBALS['wp_transients'][$name] : false;
    }
}

if (!function_exists('wp_salt')) {
    function wp_salt($scheme = 'auth') {
        return 'mock-salt-' . $scheme . '-123456789012345678901234567890';
    }
}

if (!function_exists('hash_equals')) {
    function hash_equals($known_string, $user_string) {
        return $known_string === $user_string;
    }
}

if (!function_exists('esc_url')) {
    function esc_url($url, $protocols = null, $context = 'display') {
        return htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('admin_url')) {
    function admin_url($path = '') {
        return 'http://example.com/wp-admin/' . $path;
    }
}

if (!function_exists('plugin_dir_path')) {
    function plugin_dir_path($file) {
        return dirname($file) . '/';
    }
}

if (!function_exists('plugin_dir_url')) {
    function plugin_dir_url($file) {
        return 'http://example.com/wp-content/plugins/mxroute-mailer/';
    }
}

if (!function_exists('register_activation_hook')) {
    function register_activation_hook($file, $callback) {
        $GLOBALS['wp_function_calls']['register_activation_hook'][] = compact('file', 'callback');
    }
}

if (!function_exists('add_options_page')) {
    function add_options_page($page_title, $menu_title, $capability, $menu_slug, $function = '') {
        $GLOBALS['wp_function_calls']['add_options_page'][] = compact('page_title', 'menu_title', 'capability', 'menu_slug');
        return true;
    }
}

if (!function_exists('add_management_page')) {
    function add_management_page($page_title, $menu_title, $capability, $menu_slug, $function = '') {
        $GLOBALS['wp_function_calls']['add_management_page'][] = compact('page_title', 'menu_title', 'capability', 'menu_slug');
        return true;
    }
}

if (!function_exists('add_submenu_page')) {
    function add_submenu_page($parent_slug, $page_title, $menu_title, $capability, $menu_slug, $function = '') {
        $GLOBALS['wp_function_calls']['add_submenu_page'][] = compact('parent_slug', 'page_title', 'menu_title', 'capability', 'menu_slug');
        return true;
    }
}

if (!function_exists('wp_enqueue_style')) {
    function wp_enqueue_style($handle, $src = '', $deps = array(), $ver = false, $media = 'all') {
        $GLOBALS['wp_function_calls']['wp_enqueue_style'][] = compact('handle', 'src', 'deps', 'ver', 'media');
    }
}

if (!function_exists('wp_enqueue_script')) {
    function wp_enqueue_script($handle, $src = '', $deps = array(), $ver = false, $in_footer = false) {
        $GLOBALS['wp_function_calls']['wp_enqueue_script'][] = compact('handle', 'src', 'deps', 'ver', 'in_footer');
    }
}

if (!function_exists('wp_localize_script')) {
    function wp_localize_script($handle, $object_name, $l10n) {
        $GLOBALS['wp_function_calls']['wp_localize_script'][] = compact('handle', 'object_name', 'l10n');
    }
}

if (!function_exists('checked')) {
    function checked($checked, $current = true, $echo = true) {
        if ($checked === $current) {
            return 'checked="checked" ';
        }
        return '';
    }
}

if (!function_exists('__')) {
    function __($text, $domain = 'default') {
        return $text;
    }
}

if (!function_exists('_e')) {
    function _e($text, $domain = 'default') {
        echo $text;
    }
}

if (!function_exists('esc_html__')) {
    function esc_html__($text, $domain = 'default') {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_html_e')) {
    function esc_html_e($text, $domain = 'default') {
        echo htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_attr_e')) {
    function esc_attr_e($text, $domain = 'default') {
        echo htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('_n')) {
    function _n($single, $plural, $number, $domain = 'default') {
        return (1 === $number) ? $single : $plural;
    }
}

if (!function_exists('current_time')) {
    function current_time($type = 'mysql', $translate = true) {
        if ($type === 'timestamp') {
            return time();
        }
        return date('Y-m-d H:i:s');
    }
}

if (!function_exists('number_format_i18n')) {
    function number_format_i18n($number, $decimals = 0) {
        return number_format($number, $decimals);
    }
}

if (!function_exists('wp_strip_all_tags')) {
    function wp_strip_all_tags($string, $keep_newlines = false) {
        $string = preg_replace('/<[^>]*>/', '', $string);
        if (!$keep_newlines) {
            $string = str_replace(array("\r\n", "\r", "\n"), ' ', $string);
        }
        return trim($string);
    }
}

if (!function_exists('wp_trim_words')) {
    function wp_trim_words($text, $num_words = 55, $more = '...') {
        $words = explode(' ', $text);
        if (count($words) > $num_words) {
            $words = array_slice($words, 0, $num_words);
            return implode(' ', $words) . $more;
        }
        return $text;
    }
}

if (!function_exists('paginate_links')) {
    function paginate_links($args = array()) {
        return '<a href="#">1</a>';
    }
}

if (!function_exists('add_query_arg')) {
    function add_query_arg($key, $value, $url = '') {
        return $url . '?' . $key . '=' . $value;
    }
}

if (!function_exists('selected')) {
    function selected($selected, $current = true, $echo = true) {
        if ($selected === $current) {
            return 'selected="selected" ';
        }
        return '';
    }
}

// Mock wp_send_json_error to not actually send JSON but track the call
// (already defined above)

// Mock wp_die to prevent actual page exit
if (!function_exists('wp_die')) {
    function wp_die($message = '', $title = '', $args = array()) {
        return;
    }
}

if (!function_exists('wp_schedule_event')) {
    function wp_schedule_event($timestamp, $recurrence, $hook, $args = array()) {
        $GLOBALS['wp_function_calls']['wp_schedule_event'][] = compact('timestamp', 'recurrence', 'hook', 'args');
        $GLOBALS['wp_scheduled_events'][$hook] = true;
        return true;
    }
}

if (!function_exists('wp_next_scheduled')) {
    function wp_next_scheduled($hook) {
        if (isset($GLOBALS['wp_scheduled_events'][$hook])) {
            return $GLOBALS['wp_scheduled_events'][$hook];
        }
        $calls = $GLOBALS['wp_function_calls']['wp_schedule_event'] ?? array();
        foreach ( $calls as $call ) {
            if ( $call['hook'] === $hook ) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('home_url')) {
    function home_url($path = '') {
        return 'http://example.com' . $path;
    }
}

if (!function_exists('wp_parse_url')) {
    function wp_parse_url($url, $component = -1) {
        return parse_url($url, $component);
    }
}

if (!function_exists('wp_upload_dir')) {
    function wp_upload_dir($time = '') {
        $upload_dir = sys_get_temp_dir() . '/mxroute_test_uploads';
        if (!is_dir($upload_dir)) {
            @mkdir($upload_dir, 0755, true);
        }
        return array(
            'path'    => $upload_dir,
            'url'     => 'http://example.com/uploads',
            'subdir'  => '',
            'basedir' => $upload_dir,
            'error'   => false,
        );
    }
}

if (!function_exists('wp_basename')) {
    function wp_basename($path, $suffix = '') {
        return basename($path, $suffix);
    }
}

if (!function_exists('trailingslashit')) {
    function trailingslashit($string) {
        return rtrim($string, '/') . '/';
    }
}

if (!function_exists('absint')) {
    function absint($value) {
        return abs(intval($value));
    }
}

if (!function_exists('get_attached_file')) {
    function get_attached_file($attachment_id) {
        if (isset($GLOBALS['wp_attached_files'][$attachment_id])) {
            return $GLOBALS['wp_attached_files'][$attachment_id];
        }
        return false;
    }
}

if (!function_exists('wp_tempnam')) {
    function wp_tempnam($prefix = '') {
        $path = tempnam(sys_get_temp_dir(), $prefix);
        $GLOBALS['wp_function_calls']['wp_tempnam'][] = compact('prefix', 'path');
        return $path;
    }
}

// Define constants
if (!defined('ABSPATH')) {
    define('ABSPATH', '/tmp/wordpress/');
}
if (!defined('OBJECT')) {
    define('OBJECT', 'OBJECT');
}
if (!defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}
if (!defined('ARRAY_N')) {
    define('ARRAY_N', 'ARRAY_N');
}
if (!defined('WP_UNINSTALL_PLUGIN')) {
    // Not defined during normal test runs
}
if (!defined('DAY_IN_SECONDS')) {
    define('DAY_IN_SECONDS', 86400);
}
if (!defined('MB_IN_BYTES')) {
    define('MB_IN_BYTES', 1048576);
}

// Mock dbDelta
if (!function_exists('dbDelta')) {
    function dbDelta($query = '') {
        $GLOBALS['wp_function_calls']['dbDelta'][] = $query;
        return array();
    }
}

// Create mock upgrade.php path
@mkdir('/tmp/wordpress/wp-admin/includes', 0755, true);
if (!file_exists('/tmp/wordpress/wp-admin/includes/upgrade.php')) {
    file_put_contents('/tmp/wordpress/wp-admin/includes/upgrade.php', '<?php // mock');
}

// Mock global $wpdb
class MockWPDB {
    public $prefix = 'wp_';
    public $insert_id = 0;

    public function get_charset_collate() {
        return 'utf8mb4';
    }

    public function prepare($query, $args = array()) {
        if (!is_array($args)) {
            $args = array($args);
        }
        $args = array_values($args);
        $i = 0;
        $query = preg_replace_callback('/%s/', function() use (&$args, &$i) {
            return isset($args[$i]) ? "'" . addslashes($args[$i++]) . "'" : '%s';
        }, $query);
        $query = preg_replace_callback('/%d/', function() use (&$args, &$i) {
            return isset($args[$i]) ? intval($args[$i++]) : '%d';
        }, $query);
        return $query;
    }

    public function get_results($query = null, $output = OBJECT) {
        if (isset($GLOBALS['wp_db_results'])) {
            return $GLOBALS['wp_db_results'];
        }
        return array();
    }

    public function get_col($query = null, $x = 0) {
        if (isset($GLOBALS['wp_db_col'])) {
            return $GLOBALS['wp_db_col'];
        }
        return array();
    }

    public function get_row($query = null, $output = OBJECT, $offset = 0) {
        return null;
    }

    public function get_var($query = null, $x = 0, $y = 0) {
        return 0;
    }

    public function insert($table, $data, $format = null) {
        $GLOBALS['wp_db_inserts'][] = compact('table', 'data');
        $this->insert_id = count( $GLOBALS['wp_db_inserts'] );
        return true;
    }

    public function delete($table, $where, $format = null) {
        $GLOBALS['wp_function_calls']['$wpdb->delete'][] = compact('table', 'where');
        return true;
    }

    public function update($table, $data, $where, $format = null, $where_format = null) {
        $GLOBALS['wp_function_calls']['$wpdb->update'][] = compact('table', 'data', 'where');
        return true;
    }

    public function query($query) {
        $GLOBALS['wp_db_queries'][] = $query;
        return true;
    }

    public function esc_like($text) {
        return addcslashes($text, '%_\\');
    }
}

$GLOBALS['wpdb'] = new MockWPDB();

// Mock wp_create_nonce
if (!function_exists('wp_create_nonce')) {
    function wp_create_nonce($action = -1) {
        return 'test-nonce-' . md5($action);
    }
}

if (!function_exists('wp_remote_post')) {
    function wp_remote_post($url, $args = array()) {
        $GLOBALS['wp_function_calls']['wp_remote_post'][] = compact('url', 'args');
        // Allow tests to override the response via $GLOBALS['mxroute_mock_remote_response']
        if (isset($GLOBALS['mxroute_mock_remote_response'])) {
            return $GLOBALS['mxroute_mock_remote_response'];
        }
        return array(
            'response' => array('code' => 200),
            'body'     => wp_json_encode(array('success' => true, 'message' => 'Email queued for sending.')),
        );
    }
}

if (!function_exists('wp_remote_retrieve_body')) {
    function wp_remote_retrieve_body($response) {
        return $response['body'] ?? '';
    }
}

if (!function_exists('wp_remote_retrieve_response_code')) {
    function wp_remote_retrieve_response_code($response) {
        return $response['response']['code'] ?? 200;
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) {
        return $thing instanceof \WP_Error;
    }
}

// Mock PHPMailer for SMTP tests (WordPress bundles PHPMailer but it's not available in test env)
$mock_phpmailer_dir = sys_get_temp_dir() . '/mxroute_phpmailer_mock';
if ( ! is_dir( $mock_phpmailer_dir ) ) {
	mkdir( $mock_phpmailer_dir, 0755, true );
}
$mock_phpmailer_file = $mock_phpmailer_dir . '/PHPMailer.php';
	file_put_contents(
		$mock_phpmailer_file,
		'<?php' . "\n"
		. 'namespace PHPMailer\\PHPMailer;' . "\n"
		. 'class Exception extends \\Exception {}' . "\n"
		. 'class PHPMailer {' . "\n"
		. '    public $Host = "";' . "\n"
		. '    public $SMTPAuth = false;' . "\n"
		. '    public $Username = "";' . "\n"
		. '    public $Password = "";' . "\n"
		. '    public $SMTPKeepAlive = false;' . "\n"
		. '    public $CharSet = "UTF-8";' . "\n"
		. '    public $Encoding = "base64";' . "\n"
		. '    public $Port = 465;' . "\n"
		. '    public $SMTPSecure = "ssl";' . "\n"
		. '    public $Subject = "";' . "\n"
		. '    public $Body = "";' . "\n"
		. '    public $AltBody = "";' . "\n"
		. '    private $sent = false;' . "\n"
		. '    public function __construct($exceptions = false) {}' . "\n"
		. '    public function isSMTP() {}' . "\n"
		. '    public function setFrom($address, $name = "") {}' . "\n"
		. '    public function addAddress($address, $name = "") {}' . "\n"
		. '    public function addReplyTo($address, $name = "") {}' . "\n"
		. '    public function isHTML($isHtml = true) {}' . "\n"
		. '    public function addAttachment($path, $name = "") {}' . "\n"
		. '    public function clearAddresses() {}' . "\n"
		. '    public function clearAttachments() {}' . "\n"
		. '    public function clearReplyTos() {}' . "\n"
		. '    public function send() {' . "\n"
		. '        $GLOBALS["wp_function_calls"]["phpmailer_send"][] = ["host" => $this->Host, "port" => $this->Port, "secure" => $this->SMTPSecure, "username" => $this->Username, "subject" => $this->Subject, "body" => $this->Body];' . "\n"
		. '        $succeed_port = $GLOBALS["mxroute_phpmailer_succeed_port"] ?? null;' . "\n"
		. '        if ($succeed_port !== null && (int)$succeed_port === (int)$this->Port) { $this->sent = true; return true; }' . "\n"
		. '        throw new Exception("SMTP connection failed");' . "\n"
		. '    }' . "\n"
		. '    public function wasSent() { return $this->sent; }' . "\n"
		. '}' . "\n"
	);
require_once $mock_phpmailer_file;

// Load the plugin files
require_once $plugin_dir . '/includes/class-mxroute-crypto.php';
require_once $plugin_dir . '/mxroute-mailer.php';
require_once $plugin_dir . '/includes/class-mxroute-mailer.php';
require_once $plugin_dir . '/includes/class-mxroute-api.php';
require_once $plugin_dir . '/includes/class-mxroute-settings.php';
require_once $plugin_dir . '/includes/class-mxroute-logger.php';
require_once $plugin_dir . '/includes/class-mxroute-dashboard.php';
require_once $plugin_dir . '/includes/class-mxroute-queue.php';

// Mock wp_remote_get for updater tests
if (!function_exists('wp_remote_get')) {
    function wp_remote_get($url, $args = array()) {
        $GLOBALS['wp_function_calls']['wp_remote_get'][] = compact('url', 'args');
        if (isset($GLOBALS['mxroute_mock_remote_get_response'])) {
            return $GLOBALS['mxroute_mock_remote_get_response'];
        }
        return array(
            'response' => array('code' => 200),
            'body'     => '{}',
        );
    }
}

// Mock get_plugin_data for updater tests
if (!function_exists('get_plugin_data')) {
    function get_plugin_data($file, $markup = true, $translate = true) {
        return array(
            'Name'        => 'MXRoute Mailer',
            'PluginURI'   => '',
            'Description' => 'Intercepts wp_mail to send through MXRoute.',
            'Author'      => 'Richard Kent Gates',
            'Version'     => '1.0.0',
            'TextDomain'  => 'mxroute-mailer',
        );
    }
}

// Mock get_current_screen for help tab tests
if (!function_exists('get_current_screen')) {
    function get_current_screen() {
        return $GLOBALS['mxroute_mock_screen'] ?? null;
    }
}

// Mock plugin_basename for updater tests
if (!function_exists('plugin_basename')) {
    function plugin_basename($file) {
        return ltrim(str_replace(ABSPATH, '', $file), '/');
    }
}
