<?php defined('ABSPATH') or die();

if (defined('ABSPATH') && !class_exists('PKS_WP_Admin_Login')) {

	class PKS_WP_Admin_Login
	{

		private $wp_login_php;

		private function pks_wp_plugindir()
		{
			return plugin_basename(__FILE__);
		}

		private function path()
		{
			return trailingslashit(__DIR__);
		}

		private function use_trailing_slashes()
		{
			return '/' === substr(get_option('permalink_structure'), -1, 1);
		}

		private function user_trailingslashit($string)
		{
			return $this->use_trailing_slashes() ? trailingslashit($string) : untrailingslashit($string);
		}

		private function wp_template_loader()
		{
			global $pagenow;

			$pagenow = 'index.php';

			if (!defined('WP_USE_THEMES')) {
				define('WP_USE_THEMES', true);
			}

			wp();

			if ($_SERVER['REQUEST_URI'] === $this->user_trailingslashit(str_repeat('-/', 10))) {
				$_SERVER['REQUEST_URI'] = $this->user_trailingslashit('/wp-login-php/');
			}

			require_once ABSPATH . WPINC . '/template-loader.php';

			die;
		}


		public function __construct()
		{
			global $wp_version;

			if (version_compare($wp_version, '5.0', '<')) {
				add_action('admin_notices', array($this, 'admin_notices_incompatible'));
				add_action('network_admin_notices', array($this, 'admin_notices_incompatible'));
				return;
			}
			register_activation_hook($this->pks_wp_plugindir(), array($this, 'activate'));
			register_uninstall_hook($this->pks_wp_plugindir(), array('PKS_WP_Admin_Login', 'uninstall'));
			// add_action( 'admin_init', array( $this, 'admin_init' ) );

			// ---------------------------------------//-----------------------------

			add_action('admin_menu', array($this, 'add_admin_menu'));

			// ---------------------------------------//-----------------------------
			// add_action( 'admin_notices', array( $this, 'admin_notices' ) );
			// add_action( 'network_admin_notices', array( $this, 'admin_notices' ) );
			if (is_multisite() && !function_exists('is_plugin_active_for_network')) {
				require_once ABSPATH . '/wp-admin/includes/plugin.php';
			}
			add_filter('plugin_action_links_' . $this->pks_wp_plugindir(), array($this, 'plugin_action_links'));
			if (is_multisite() && is_plugin_active_for_network($this->pks_wp_plugindir())) {
				add_filter('network_admin_plugin_action_links_' . $this->pks_wp_plugindir(), array($this, 'plugin_action_links'));
				add_action('wpmu_options', array($this, 'wpmu_options'));
				add_action('update_wpmu_options', array($this, 'update_wpmu_options'));
			}
			add_action('plugins_loaded', array($this, 'plugins_loaded'), 1);
			add_action('wp_loaded', array($this, 'wp_loaded'));
			add_filter('site_url', array($this, 'site_url'), 10, 4);
			add_filter('network_site_url', array($this, 'network_site_url'), 10, 3);
			add_filter('wp_redirect', array($this, 'wp_redirect'), 10, 2);
			add_filter('site_option_welcome_email', array($this, 'welcome_email'));
			remove_action('template_redirect', 'wp_redirect_admin_locations', 1000);
		}


		// ===============================//====================================
		public function add_admin_menu()
		{
			// Add a top-level menu item
			add_menu_page(
				'PKS WP Hide Login',
				'PKS WP Hide Login',
				'manage_options',
				'pks-wp-hide-login-settings',
				array($this, 'settings_page'),
				'dashicons-shield',
				30
			);
		}

		public function settings_page()
		{
			// Check if the form is submitted
			if (isset($_POST['submit'])) {
				// Handle form submission, update options, etc.
				$loginSlug   = sanitize_title_with_dashes($_POST['pks_wp_hl_page']);
				$redirectURL = sanitize_text_field($_POST['pks_wp_redirect_field']);

				// Save the login slug and redirect URL to options or database
				update_option('pks_wp_hl_page', $loginSlug);
				update_option('pks_wp_redirect_field', $redirectURL);

				echo '<div class="updated" id="success-message"><p>Settings saved successfully!</p></div>';

?>
				<script>
					jQuery(document).ready(function($) {
						setTimeout(function() {
							$("#success-message").fadeOut("slow");
						}, 500);
					});
				</script>
			<?php
			}

			// Fetch the current values from options or database
			$currentLoginSlug   = get_option('pks_wp_hl_page', 'login');
			$currentRedirectURL = get_option('pks_wp_redirect_field', '');

			// Display the HTML content for the settings page
			?>
			<div class="wrap">
				<h1>PKS WP Hide Login Settings</h1>
				<form method="post" action="">
					<label for="pks-wp-hl-page">Login URL Slug:</label>
					<input type="text" name="pks_wp_hl_page" value="<?php echo esc_attr($currentLoginSlug); ?>" />

					<label for="pks_wp_redirect_field">Redirect URL:</label>
					<input type="text" name="pks_wp_redirect_field" value="<?php echo esc_attr($currentRedirectURL); ?>" />

					<?php
					// Output security fields
					wp_nonce_field('pks_wp_hide_login_nonce', 'pks_wp_hide_login_nonce');
					?>

					<input type="submit" name="submit" class="button-primary" value="Save Changes" />
				</form>
			</div>
<?php
		}
		// ===============================//====================================
		private function pks_wp_new_login_slug()
		{
			if (
				($slug = get_option('pks_wp_hl_page')) || (
					is_multisite() &&
					is_plugin_active_for_network($this->pks_wp_plugindir()) &&
					($slug = get_site_option('pks_wp_hl_page', 'login'))
				) ||
				($slug = 'login')
			) {
				return $slug;
			}
		}

		public function pks_wp_new_login_url($scheme = null)
		{
			if (get_option('permalink_structure')) {
				return $this->user_trailingslashit(home_url('/', $scheme) . $this->pks_wp_new_login_slug());
			} else {
				return home_url('/', $scheme) . '?' . $this->pks_wp_new_login_slug();
			}
		}

		public function admin_notices_incompatible()
		{
			echo '<div class="error"><p>' . sprintf(__('Please upgrade to the latest version of WordPress to activate %s.', 'pks-wp-admin-login'), '<strong>' . __('PKS wp-admin login', 'pks-wp-admin-login') . '</strong>') . '</p></div>';
		}

		public function activate()
		{
			add_option('pks_hl_redirect', '1');
			delete_option('pks_hl_admin');
		}

		public static function uninstall()
		{
			global $wpdb;

			if (is_multisite()) {
				$blogs = $wpdb->get_col("SELECT blog_id FROM {$wpdb->blogs}");

				if ($blogs) {
					foreach ($blogs as $blog) {
						switch_to_blog($blog);
						delete_option('pks_wp_hl_page');
					}

					restore_current_blog();
				}

				delete_site_option('pks_wp_hl_page');
			} else {
				delete_option('pks_wp_hl_page');
			}
		}


		public function wpmu_options()
		{
			$out = '';

			$out         .= '<h3>' . __('PKS wp-admin login', 'pks-wp-admin-login') . '</h3>';
			$out         .= '<p>' . __('This option allows you to set a networkwide default, which can be overridden by individual sites. Simply go to to the site’s permalink settings to change the url.', 'pks-wp-admin-login') . '</p>';
			$out         .= '<table class="form-table">';
			$out     .= '<tr valign="top">';
			$out .= '<th scope="row">' . __('Networkwide default', 'pks-wp-admin-login') . '</th>';
			$out .= '<td><input id="pks-wp-hl-page-input" type="text" name="pks_wp_hl_page" value="' . esc_attr(get_site_option('pks_wp_hl_page', 'login')) . '"></td>';
			$out     .= '</tr>';
			$out         .= '</table>';

			echo $out;
		}

		public function update_wpmu_options()
		{
			if (
				($pks_wp_hl_page = sanitize_title_with_dashes($_POST['pks_wp_hl_page'])) &&
				strpos($pks_wp_hl_page, 'wp-login') === false &&
				!in_array($pks_wp_hl_page, $this->forbidden_slugs())
			) {
				update_site_option('pks_wp_hl_page', $pks_wp_hl_page);
			}
		}

		public function admin_init()
		{
			global $pagenow;

			add_settings_section(
				'pks-wp-admin-login-section',
				__('PKS wp-admin login', 'pks-wp-admin-login'),
				array($this, 'pks_hl_section_desc'),
				'permalink'
			);

			add_settings_field(
				'pks-wp-hl-page',
				'<label for="pks-wp-hl-page">' . __('Login URL', 'pks-wp-admin-login') . '</label>',
				array($this, 'pks_wp_hl_page_input'),
				'permalink',
				'pks-wp-admin-login-section'
			);

			// Add redirect field
			add_settings_field(
				'pks_wp_redirect_field',
				__('Redirect URL', 'pks-wp-admin-login'),
				array($this, 'pks_hl_redirect_func'),
				'permalink',
				'pks-wp-admin-login-section'
			);

			register_setting('permalink', 'pks_wp_hl_page_input');
			register_setting('permalink', 'pks_wp_redirect_field');

			if (current_user_can('manage_options') && isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'update-permalink')) {
				if (isset($_POST['permalink_structure']) && isset($_POST['pks_wp_redirect_field'])) {
					$short_domain = sanitize_title_with_dashes(wp_unslash($_POST['pks_wp_redirect_field']));
					update_option('pks_wp_redirect_field', $short_domain);
				}

				if (isset($_POST['permalink_structure']) && isset($_POST['pks_wp_hl_page'])) {
					if (
						($pks_wp_hl_page = sanitize_title_with_dashes($_POST['pks_wp_hl_page'])) &&
						strpos($pks_wp_hl_page, 'wp-login') === false &&
						!in_array($pks_wp_hl_page, $this->forbidden_slugs())
					) {
						if (is_multisite() && $pks_wp_hl_page === get_site_option('pks_wp_hl_page', 'login')) {
							delete_option('pks_wp_hl_page');
						} else {
							update_option('pks_wp_hl_page', $pks_wp_hl_page);
						}
					}
				}

				if (get_option('pks_hl_redirect')) {
					delete_option('pks_hl_redirect');

					if (is_multisite() && is_super_admin() && is_plugin_active_for_network($this->pks_wp_plugindir())) {
						$redirect = network_admin_url('settings.php#pks-wp-hl-page-input');
					} else {
						$redirect = admin_url('options-permalink.php#pks-wp-hl-page-input');
					}

					wp_safe_redirect($redirect);

					die;
				}
			}
		}

		public function pks_hl_section_desc()
		{
			$out = '';

			if (is_multisite() && is_super_admin() && is_plugin_active_for_network($this->pks_wp_plugindir())) {
				$out .= '<p>' . sprintf(__('To set a networkwide default, go to %s.', 'pks-wp-admin-login'), '<a href="' . esc_attr(network_admin_url('settings.php#pks-wp-hl-page-input')) . '">' . __('Network Settings', 'pks-wp-admin-login') . '</a>') . '</p>';
			}

			echo $out;
		}


		public function pks_hl_redirect_func()
		{
			$value = get_option('pks_wp_redirect_field');
			echo '<code>' . trailingslashit(home_url()) . '</code> <input type="text" value="' . esc_attr($value) . '" name="pks_wp_redirect_field" id="pks_wp_redirect_field" class="regular-text" /> <code>/</code>';
			echo '<p class="description"><strong>' . __('If you leave the above field empty the plugin will add a redirect to the website homepage.', 'pks-wp-admin-login') . '</strong></p>';
		}

		public function pks_wp_hl_page_input()
		{
			if (get_option('permalink_structure')) {
				echo '<code>' . trailingslashit(home_url()) . '</code> <input id="pks-wp-hl-page-input" type="text" name="pks_wp_hl_page" value="' . $this->pks_wp_new_login_slug() . '">' . ($this->use_trailing_slashes() ? ' <code>/</code>' : '');
			} else {
				echo '<code>' . trailingslashit(home_url()) . '?</code> <input id="pks-wp-hl-page-input" type="text" name="pks_wp_hl_page" value="' . $this->pks_wp_new_login_slug() . '">';
			}
		}

		public function admin_notices()
		{
			global $pagenow;

			if (!is_network_admin() && $pagenow === 'options-permalink.php' && isset($_GET['settings-updated'])) {
				echo '<div class="updated"><p>' . sprintf(__('Your login page is now here: %s. Bookmark this page!', 'pks-wp-admin-login'), '<strong><a href="' . $this->pks_wp_new_login_url() . '">' . $this->pks_wp_new_login_url() . '</a></strong>') . '</p></div>';
			}
		}

		public function plugin_action_links($links)
		{
			if (is_network_admin() && is_plugin_active_for_network($this->pks_wp_plugindir())) {
				array_unshift($links, '<a href="' . network_admin_url('settings.php#pks-wp-hl-page-input') . '">' . __('Settings', 'pks-wp-admin-login') . '</a>');
			} elseif (!is_network_admin()) {
				array_unshift($links, '<a href="' . admin_url('options-permalink.php#pks-wp-hl-page-input') . '">' . __('Settings', 'pks-wp-admin-login') . '</a>');
			}

			return $links;
		}


		public function plugins_loaded()
		{

			global $pagenow;

			if (
				!is_multisite()
				&& (strpos(rawurldecode($_SERVER['REQUEST_URI']), 'wp-signup') !== false
					|| strpos(rawurldecode($_SERVER['REQUEST_URI']), 'wp-activate') !== false)
			) {

				wp_die(__('This feature is not enabled.', 'pks-wp-admin-login'));
			}

			$request = parse_url(rawurldecode($_SERVER['REQUEST_URI']));

			if ((strpos(rawurldecode($_SERVER['REQUEST_URI']), 'wp-login.php') !== false
					|| (isset($request['path']) && untrailingslashit($request['path']) === site_url('wp-login', 'relative')))
				&& !is_admin()
			) {

				$this->wp_login_php = true;

				$_SERVER['REQUEST_URI'] = $this->user_trailingslashit('/' . str_repeat('-/', 10));

				$pagenow = 'index.php';
			} elseif ((isset($request['path']) && untrailingslashit($request['path']) === home_url($this->pks_wp_new_login_slug(), 'relative'))
				|| (!get_option('permalink_structure')
					&& isset($_GET[$this->pks_wp_new_login_slug()])
					&& empty($_GET[$this->pks_wp_new_login_slug()]))
			) {

				$pagenow = 'wp-login.php';
			} elseif ((strpos(rawurldecode($_SERVER['REQUEST_URI']), 'wp-register.php') !== false
					|| (isset($request['path']) && untrailingslashit($request['path']) === site_url('wp-register', 'relative')))
				&& !is_admin()
			) {

				$this->wp_login_php = true;

				$_SERVER['REQUEST_URI'] = $this->user_trailingslashit('/' . str_repeat('-/', 10));

				$pagenow = 'index.php';
			}
		}


		public function wp_loaded()
		{
			global $pagenow;

			if (is_admin() && !is_user_logged_in() && !defined('DOING_AJAX')) {

				if (get_option('pks_wp_redirect_field') == 'false') {
					wp_safe_redirect('/');
				} else {
					wp_safe_redirect('/' . get_option('pks_wp_redirect_field'));
				}
				die();
			}

			$request = parse_url(rawurldecode($_SERVER['REQUEST_URI']));

			if (
				$pagenow === 'wp-login.php' &&
				$request['path'] !== $this->user_trailingslashit($request['path']) &&
				get_option('permalink_structure')
			) {
				wp_safe_redirect($this->user_trailingslashit($this->pks_wp_new_login_url()) . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : ''));
				die;
			} elseif ($this->wp_login_php) {
				if (
					($referer = wp_get_referer()) &&
					strpos($referer, 'wp-activate.php') !== false &&
					($referer = parse_url($referer)) &&
					!empty($referer['query'])
				) {
					parse_str($referer['query'], $referer);

					if (
						!empty($referer['key']) &&
						($result = wpmu_activate_signup($referer['key'])) &&
						is_wp_error($result) && (
							$result->get_error_code() === 'already_active' ||
							$result->get_error_code() === 'blog_taken'
						)
					) {
						wp_safe_redirect($this->pks_wp_new_login_url() . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : ''));
						die;
					}
				}

				$this->wp_template_loader();
			} elseif ($pagenow === 'wp-login.php') {
				global $error, $interim_login, $action, $user_login;

				@require_once ABSPATH . 'wp-login.php';

				die;
			}
		}

		public function site_url($url, $path, $scheme, $blog_id)
		{
			return $this->filter_wp_login_php($url, $scheme);
		}

		public function network_site_url($url, $path, $scheme)
		{
			return $this->filter_wp_login_php($url, $scheme);
		}

		public function wp_redirect($location, $status)
		{
			return $this->filter_wp_login_php($location);
		}

		public function filter_wp_login_php($url, $scheme = null)
		{
			if (strpos($url, 'wp-login.php') !== false) {
				if (is_ssl()) {
					$scheme = 'https';
				}

				$args = explode('?', $url);

				if (isset($args[1])) {
					parse_str($args[1], $args);
					$url = add_query_arg($args, $this->pks_wp_new_login_url($scheme));
				} else {
					$url = $this->pks_wp_new_login_url($scheme);
				}
			}

			return $url;
		}

		public function welcome_email($value)
		{
			return $value = str_replace('wp-login.php', trailingslashit(get_site_option('pks_wp_hl_page', 'login')), $value);
		}

		public function forbidden_slugs()
		{
			$wp = new WP();
			return array_merge($wp->public_query_vars, $wp->private_query_vars);
		}
	}


	new PKS_WP_Admin_Login();
}
