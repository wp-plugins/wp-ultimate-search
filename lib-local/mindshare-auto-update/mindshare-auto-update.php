<?php
/**
 * mindshare-auto-update.php
 *
 * Provides a simple way to add automatic updates to premium themes and plugins.
 * Interacts with our remote repository API: http://mindsharelabs.com/update/
 *
 * @version      0.2
 * @created      9/23/12 12:44 AM
 * @author       Mindshare Studios, Inc.
 * @copyright    Copyright (c) 2012
 * @link         http://www.mindsharelabs.com/documentation/
 *
 * @see          http://goo.gl/tUpSc (thanks to Abid Omar for the skeleton)
 *
 */

// make sure the plugin is available
if(!function_exists('is_plugin_active')) {
	include_once(ABSPATH.'wp-admin/includes/plugin.php');
}

if(!class_exists('mindshare_auto_update')) :
	class mindshare_auto_update {
		/**
		 * The plugin current version
		 *
		 * @var string
		 */
		public $current_version;

		/**
		 * The plugin remote update API web service
		 *
		 * @var string
		 */
		public $update_path = 'http://mindsharelabs.com/update/';

		/**
		 * Plugin Slug (plugin_directory/plugin_file.php)
		 *
		 * @var string
		 */
		public $plugin_slug;

		/**
		 * Plugin name (plugin_file)
		 *
		 * @var string
		 */
		public $slug;

		/**
		 * Plugin path (path to plugin file from server root)
		 *
		 * @var string
		 */
		public $plugin_path;

		public $hash = 'cdd96d3cc73d1dbdaffa03cc6cd7339b';

		/**
		 * Initialize a new instance of the WordPress Auto-Update class
		 *
		 * @param        $plugin_slug
		 * @param        $plugin_path
		 * @param string $update_path
		 *
		 * @internal param string $current_version
		 * @internal param string $this->plugin_slug
		 */
		function __construct($plugin_slug, $plugin_path, $update_path = NULL) {
			// Set the class public variables, order is important here
			$this->plugin_slug = $plugin_slug;
			list($t1, $t2) = explode('/', $this->plugin_slug);
			$this->slug = str_replace('.php', '', $t2);
			$this->plugin_path = $plugin_path;
			$this->current_version = $this->get_local_version();
			if(isset($update_path)) {
				$this->update_path = $update_path;
			}

			// define the alternative API for updating checking
			add_filter('pre_set_site_transient_update_plugins', array($this, 'check_update'));

			// Define the alternative response for information checking
			add_filter('plugins_api', array($this, 'check_info'), 10, 3);
			//set_site_transient('update_plugins', NULL); // debugging
			//add_filter('pre_set_site_transient_update_plugins', array($this, 'display_transient_update_plugins')); // debugging
			//$this->check_update(get_site_transient('update_plugins')); // debugging
		}

		function display_transient_update_plugins($transient) {
			if($transient !== FALSE) {
				var_dump($transient);
			} else {
				echo '<h1>not set</h1>';
			}
		}

		/**
		 * Add our self-hosted auto-update plugin to the filter transient
		 *
		 * @param $transient
		 *
		 * @return object $ transient
		 */
		public function check_update($transient) {

			if(empty($transient->checked)) {
				return $transient;
			}

			// Get the remote version / info
			$arg = new stdClass();
			$arg->slug = $this->slug;
			$information = $this->check_info(NULL, 'plugin_information', $arg);

			if($information) {
				// If a newer version is available, add the update
				if(version_compare($this->current_version, $information->new_version, '<')) {
					$obj = new stdClass();
					$obj->slug = $this->slug;
					$obj->new_version = $information->new_version;
					$obj->url = $this->update_path;
					$obj->package = $information->download_link;
					$transient->response[$this->plugin_slug] = $obj;
				}
				return $transient;
			} else {
				return FALSE;
			}
		}

		/**
		 * Add our self-hosted description to the filter
		 *
		 * @param boolean $false
		 * @param array   $action
		 * @param object  $arg
		 *
		 * @return bool|object
		 */
		/*public function check_info($false, $action, $arg)
			{
				if ($arg->slug === $this->slug) {
					$information = $this->get_remote_information();
					return $information;
				}
				return false;
			}*/

		public function check_info($false, $action, $arg) {
			if($arg->slug === $this->slug) {
				$information = $this->get_remote_information();
				return $information;
			}
			/**
			 * Return variable $false instead of explicitly returning boolean FALSE
			 * WordPress passes FALSE here by default
			 *
			 * @see http://goo.gl/tUpSc
			 */
			return $false;
		}

		/**
		 * get_local_version
		 *
		 * Returns the version of a given locally installed plugin
		 *
		 * @param null $plugin_path
		 *
		 * @return string|bool
		 */
		function get_local_version($plugin_path = NULL) {

			if(!isset($plugin_path)) {
				$plugin_path = $this->plugin_path.$this->slug.'.php';
			}
			$plugin_data = get_plugin_data($plugin_path);
			if(!empty($plugin_data['Version'])) {
				return $plugin_data['Version'];
			} else {
				return FALSE;
			}
		}

		/**
		 * Returns the version of a remotely hosted plugin from the API web service
		 *
		 * @return string|bool $remote_version
		 */
		public function get_remote_version() {
			$request = wp_remote_post($this->update_path, array('body' => array('project' => $this->slug, 'action' => 'version')));
			if(!is_wp_error($request) || wp_remote_retrieve_response_code($request) === 200) {
				return $request['body'];
			}
			return FALSE;
		}

		/**
		 * Get information about the remote version
		 *
		 * @return bool|object
		 */
		public function get_remote_information() {

			$request = wp_remote_post($this->update_path, array('body' => array('project' => $this->slug, 'action' => 'info')));

			if(!is_wp_error($request)) {
				if(wp_remote_retrieve_response_code($request) === 200) {
					return unserialize($request['body']);
				} elseif(wp_remote_retrieve_response_code($request) === 503) {
					//echo '<div id="message" class="error"><p>We\'re sorry! The update server timed out (status code: '.wp_remote_retrieve_response_code($request).'). Please try again later.</p></div>';
					return FALSE;
				} else {
					//echo '<div id="message" class="error"><p>We\'re sorry! An unknown error occurred (status code: '.wp_remote_retrieve_response_code($request).'). Please try again later.</p></div>';
					return FALSE;
				}
			} else {
				$error_string = $request->get_error_message();
				//echo '<div id="message" class="error"><p>'.$error_string.'</p></div>';
				return FALSE;
			}
		}

		/**
		 * Return the status of the plugin licensing
		 *
		 * @param string $key
		 * @param string $email
		 *
		 * @return boolean $remote_license
		 */
		public function get_remote_license($key = NULL, $email = NULL) {
			if(empty($key) || empty($email)) {
				return FALSE;
			}

			$body = array('body' => array(
				'project' => $this->slug,
				'action'  => 'license',
				'k'       => $key,
				'u'       => $email
			));
			$response = wp_remote_post($this->update_path, $body);

			if(is_wp_error($response)) {
				//$error_string = $response->get_error_message();
				//echo '<div id="message" class="error"><p>'.$error_string.'</p></div>';
				return FALSE;
			} else {
				if(wp_remote_retrieve_response_code($response) === 200) {
					if(md5(base64_encode(wp_remote_retrieve_body($response))) == $this->hash) {
						return $this->hash;
					} else {
						//echo '<div id="message" class="error"><p>Your license couldn\'t be verified, please double check your entries and try again.</p></div>';
						return FALSE;
					}
				} else {
					if(wp_remote_retrieve_response_code($response) == '503') {
						//echo '<div id="message" class="error"><p>We\'re sorry! The update server timed out (status code: '.wp_remote_retrieve_response_code($response).'). Please try again in 30 seconds.</p></div>';
						return FALSE;
					} else {
						//echo '<div id="message" class="error"><p>We\'re sorry! An unknown error occurred (status code: '.wp_remote_retrieve_response_code($response).'). Please try again in 30 seconds.</p></div>';
						return FALSE;
					}
				}
			}
		}

		/**
		 * maybe_activate_plugin
		 *
		 * Activates a plugin (if it is installed and not already activated.)
		 *
		 * @internal       param $this ->plugin_slug
		 * @internal       param $plugin_file
		 *
		 * @return bool Returns TRUE if plugin activation is successful or
		 *                 plugin is already active, otherwise FALSE
		 */
		public function maybe_activate_plugin() {
			if(!is_plugin_active($this->plugin_slug)) {
				$result = activate_plugin($this->plugin_slug);
				if(!is_wp_error($result)) {
					return TRUE;
				} else {
					// activation failed
					//$error_string = $result->get_error_message();
					//echo '<div id="message" class="error"><p>'.$error_string.'</p></div>';
					return FALSE;
				}
			} else {
				// already active
				return TRUE;
			}
		}

		/**
		 * do_remote_install
		 *
		 * Installs a remote plugin, overwriting if the plugin already exists.
		 *
		 * @todo add ability to overwrite currently active plugin
		 *
		 * @usage:
		 *
		 * <code>
		 * $http_request_url = base64_decode('aHR0cDovL21pbmRzaGFyZWxhYnMuY29tL3Byb3RlY3RlZC93cC11bHRpbWF0ZS1zZWFyY2gtcHJvL3dwLXVsdGltYXRlLXNlYXJjaC1wcm8udHh0');
		 * $http_request_args = array(
		 *         'headers' => array(
		 *         'Authorization' => 'Basic '.base64_encode(base64_decode('d3B1bHRpbWF0ZXNlYXJjaHBybw==').':cdd96d3cc73d1dbdaffa03cc6cd7339b'
		 * )));
		 * do_remote_install('','', $http_request_url, $http_request_args);
		 * </code>
		 *
		 * @param $plugin_slug
		 * @param $plugin_path
		 * @param $http_request_url
		 * @param $http_request_args
		 *
		 * @return bool
		 */
		public function do_remote_install($plugin_slug, $plugin_path, $http_request_url, $http_request_args) {
var_dump($plugin_path.$plugin_slug.'.php');
			var_dump(file_exists($plugin_path.$plugin_slug.'.php'));
			// delete any older versions before upgrade
			if(file_exists($plugin_path.$plugin_slug.'.php')) {
				@unlink($plugin_path.$plugin_slug.'.php');
			}
			if(is_dir($plugin_path)) {
				@rmdir($plugin_path);
			}

			if(!is_dir($plugin_path)) {
				if(mkdir($plugin_path, 0755)) {
					$contents = wp_remote_request($http_request_url, $http_request_args);
					if(@file_put_contents($plugin_path.$plugin_slug.'.php', wp_remote_retrieve_body($contents)) !== FALSE) {
						return $this->maybe_activate_plugin(); // @todo allow plugin/slug to be passed or set this fn to use class vars
					} else {
						//echo '<div id="message" class="error"><p>Plugin upgrade failed. Could not create the main plugin file.</p></div>';
						return FALSE;
					}
				} else {
					// mkdir failed
					//echo '<div id="message" class="error"><p>Plugin upgrade failed. Could not create the plugin directory.</p></div>';
					return FALSE;
				}
			}
			return TRUE;
		}
	}
endif;
