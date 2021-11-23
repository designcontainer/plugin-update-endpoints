<?php
/**
 * Plugin Name:       Plugin Update Endpoints
 * Plugin URI:        https://github.com/designcontainer/plugin-update-endpoints
 * Description:       A plugin for exposing the update urls for plugins. Used in combination with the plugin updater workflow on GitHub
 * Version:           1.1.1
 * Author:            Design Container
 * Author URI:        https://designcontainer.no
 * Text Domain:       plugin-update-endpoints
 */

class Plugin_Update_Endpoints {
	public function __construct() {
		$this->version = '1.1.1';
		$this->plugin_name = 'plugin-update-endpoints';
		$this->api_route = $this->plugin_name.'/v1';

		$this->loader();
	}

	/**
	 * Load plugin functions.
	 *
	 * @return void
	 */
	private function loader() {
		add_filter( 'rest_api_init', array( $this, 'update_url_endpoint' ) );
		add_action( 'template_redirect', array( $this, 'frontend_content' ) ); 
		add_filter( 'auto_update_plugin', '__return_false' );
	}

	/**
	 * The main endpoint for the plugin.
	 * Pass a plugin parameter and an auth parameter to retrive the plugin zip file.
	 *
	 * @return void
	 */
	public function update_url_endpoint() {
		register_rest_route($this->api_route, '/download', array(
			'methods' => 'GET',	
			'callback' => array( $this, 'update_url_endpoint_response' ),
			'args' => array(
				'auth' => array(
					'validate_callback' => function($param, $request, $key) {
						return is_string( $param );
					}
				),
				'plugin' => array(
					'validate_callback' => function($param, $request, $key) {
						return is_string( $param );
					}
				),
			),
		) );
	}

	/**
	 * Updates a given plugin from endpoint, 
	 * and redirects the client to the plugin update url.
	 * If an update does not exist, a 204 response will be returned.
	 *
	 * @param object $r HTTP Request
	 * @return void
	 */
	public function update_url_endpoint_response($r) {
		if ( ! isset ( $r['auth'] ) )                             return new WP_Error(401, 'Missing Authentication code.');
		if ( ! defined( 'DC_PUE_AUTH_CODE' ) )                    return new WP_Error(401, 'Authentication code is not defined on the server.');
		if ( $r['auth'] !== DC_PUE_AUTH_CODE )                    return new WP_Error(401, 'Wrong authentication code.');
		if ( ! isset ( $r['plugin'] ) )                           return new WP_Error(401, 'Missing plugin parameter with plugin slug.');
		if ( NULL === $this->get_plugin_by_slug( $r['plugin'] ) ) return new WP_Error(404, 'Plugin does not exist on the WordPress install.');

		$plugin_php_file = $this->get_plugin_by_slug($r['plugin']);


		ob_start();
		$this->update_plugin($plugin_php_file);
		$out = ob_get_clean();
		
		$url = strip_tags($out);
		if ( true === empty($url) ) {
			$plugin_abs = WP_PLUGIN_DIR . '/' . plugin_dir_path($plugin_php_file);
			$url = $this->zip_plugin($plugin_abs);
		}
		wp_redirect($url, 301);
		exit;
	}


	/**
	 * Zip a given directory and return the zip file.
	 */
	public function zip_plugin($dir) {
		// Get real path for our folder
		$rootPath = realpath($dir);

		// Setup Dist folder and delete previous ones.
		$dist_path = wp_upload_dir()['basedir'] . '/' . $this->plugin_name;
		$dist_url = wp_upload_dir()['baseurl'] . '/' . $this->plugin_name;
		$file_name = sprintf('plugin-%s-%s.zip', time(), uniqid());
		$dist_file = $dist_path . '/' . $file_name;
		if (!file_exists($dist_path)) {
			mkdir($dist_path, 0777, true);
		}

		// Delete old zip files
		$all_zips = scandir($dist_path);
		foreach ($all_zips as $zip) {
			$time_file = explode('-', $zip)[1];
			if ($time_file < time() - 3600) { // Delete files older than 1 hour
				unlink($dist_path . '/' . $zip);
			}
		}

		// Initialize archive object
		$zip = new ZipArchive();
		$zip->open($dist_file, ZipArchive::CREATE | ZipArchive::OVERWRITE);

		// Create recursive directory iterator
		/** @var SplFileInfo[] $files */
		$files = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($rootPath),
			RecursiveIteratorIterator::LEAVES_ONLY
		);
		foreach ($files as $file) {
			// Skip directories (they would be added automatically)
			if ( ! $file->isDir() ) {
				// Get real and relative path for current file
				$filePath = $file->getRealPath();
				$relativePath = substr($filePath, strlen($rootPath) + 1);

				// Add current file to archive
				$zip->addFile($filePath, $relativePath);
			}
		}

		// Zip archive will be created only after closing object
		$zip->close();

		return $dist_url . '/' . $file_name;
	}

	/**
	 * Check for plugin updates.
	 * Handles plugins both inside and outside SVN.
	 *
	 * @return void
	 */
	private function check_for_plugin_updates() {
		// SVN plugins
		wp_update_plugins();
		// Other plugins
		global $wpdb;
		$sql = "UPDATE wp_options SET option_value='' WHERE option_name='_site_transient_update_plugins';";
		$wpdb->get_results($sql);
	}

	/**
	 * Update a plugin by plugin file.
	 *
	 * @param string $file The plugin file with the main file included. 
	 *                     Example: contact-form-7/wp-contact-form-7.php
	 * @return void
	 */
	private function update_plugin($file) {
		// Activate plugin before upgrade
		activate_plugin($file);
		$this->check_for_plugin_updates();
		if (!class_exists('Plugin_Upgrader')) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
			require_once dirname( __FILE__ ) . '/inc/class-url-upgrader-skin.php';
		}
		// Upgrade plugin and return url
		$skin = new WP_Upgrader_Url();
		$upgrader = new Plugin_Upgrader($skin);
		$upgrader->upgrade($file);
		// Deactivate plugin after upgrade
		deactivate_plugins($file);
	}

	/**
	 * Gets a plugin file by slug.
	 * Example: contact-form-7 -> contact-form-7/wp-contact-form-7.php
	 *
	 * @param string $plugin_slug
	 * @return string
	 */
	private function get_plugin_by_slug($plugin_slug) {
		$all_plugins = get_plugins();
		foreach ( $all_plugins as $plugin => $plugin_info ) {
			if ( strpos( $plugin, $plugin_slug ) !== false ) {
				return $plugin;
			}
		}
		return null;
	}

	/**
	 * Simple frontend content.
	 * Takes over the WP Theme.
	 *
	 * @return void
	 */
	public function frontend_content() {
		if ( is_admin() ) return;
		echo 'This site is being used for handling plugin downloads.';
		die();
	}
}

$plugin_update_endpoints = new Plugin_Update_Endpoints();
