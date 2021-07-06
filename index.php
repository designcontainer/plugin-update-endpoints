<?php
/**
 * Plugin Name:       Plugin Update Endpoints
 * Plugin URI:        https://github.com/designcontainer/plugin-update-endpoints
 * Description:       A plugin for exposing the update urls for plugins. Used in combination with the plugin updater workflow on GitHub
 * Version:           1.0.0
 * Author:            Design Container
 * Author URI:        https://designcontainer.no
 * Text Domain:       plugin-update-endpoints
 */

class PluginUpdateEndpoints {
	public function __construct() {
		$this->version = '1.0.0';
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
			return new WP_REST_Response(null, 204);
		}
		wp_redirect($url, 301);
		exit;
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
		$this->check_for_plugin_updates();
		if (!class_exists('Plugin_Upgrader')) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
			require_once dirname( __FILE__ ) . '/inc/class-url-upgrader-skin.php';
		}
		// Upgrade plugin and return url
		$skin = new WP_Upgrader_Url();
		$upgrader = new Plugin_Upgrader($skin);
		$upgrader->upgrade($file);
		// Activate plugin after upgrade
		activate_plugin($file);
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

$plugin_update_endpoints = new PluginUpdateEndpoints();
