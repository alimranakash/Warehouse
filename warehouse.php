<?php
/**
 * Plugin Name: Warehouse
 * Description: Manage warehouse bins, locations, and scanning system.
 * Plugin URI:  https://worzen.com/products/
 * Author:      Al Imran Akash
 * Author URI:  https://profiles.wordpress.org/al-imran-akash/
 * Version: 0.9
 * Text Domain: warehouse
 * Domain Path: /languages
 */

namespace Worzen\Warehouse;
use Codexpert\Plugin\Notice;

/**
 * if accessed directly, exit.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main class for the plugin
 * @package Plugin
 * @author Codexpert <hi@codexpert.io>
 */
final class Plugin {
	
	/**
	 * Plugin instance
	 * 
	 * @access private
	 * 
	 * @var Plugin
	 */
	private static $_instance;

	/**
	 * The constructor method
	 * 
	 * @access private
	 * 
	 * @since 0.9
	 */
	private function __construct() {
		/**
		 * Includes required files
		 */
		$this->include();

		/**
		 * Defines contants
		 */
		$this->define();

		/**
		 * Runs actual hooks
		 */
		$this->hook();
	}

	/**
	 * Includes files
	 * 
	 * @access private
	 * 
	 * @uses composer
	 * @uses psr-4
	 */
	private function include() {
		require_once( dirname( __FILE__ ) . '/vendor/autoload.php' );
	}

	/**
	 * Define variables and constants
	 * 
	 * @access private
	 * 
	 * @uses get_plugin_data
	 * @uses plugin_basename
	 */
	private function define() {

		/**
		 * Define some constants
		 * 
		 * @since 0.9
		 */
		define( 'BLM', __FILE__ );
		define( 'BLM_DIR', dirname( BLM ) );
		define( 'BLM_ASSET', plugins_url( 'assets', BLM ) );
		define( 'BLM_DEBUG', apply_filters( 'plugin-client_debug', true ) );

		/**
		 * The plugin data
		 * 
		 * @since 0.9
		 * @var $plugin
		 */
		$this->plugin					= get_plugin_data( BLM );
		$this->plugin['basename']		= plugin_basename( BLM );
		$this->plugin['file']			= BLM;
		$this->plugin['server']			= apply_filters( 'plugin-client_server', 'https://codexpert.io/dashboard' );
		$this->plugin['min_php']		= '5.6';
		$this->plugin['min_wp']			= '4.0';
		$this->plugin['icon']			= BLM_ASSET . '/img/icon.png';
		$this->plugin['depends']		= [ 'woocommerce/woocommerce.php' => 'WooCommerce' ];
		
	}

	/**
	 * Hooks
	 * 
	 * @access private
	 * 
	 * Executes main plugin features
	 *
	 * To add an action, use $instance->action()
	 * To apply a filter, use $instance->filter()
	 * To register a shortcode, use $instance->register()
	 * To add a hook for logged in users, use $instance->priv()
	 * To add a hook for non-logged in users, use $instance->nopriv()
	 * 
	 * @return void
	 */
	private function hook() {

		if( is_admin() ) :

			/**
			 * Admin facing hooks
			 */
			$admin = new App\Admin( $this->plugin );
			$admin->activate( 'install' );
			$admin->action( 'admin_footer', 'modal' );
			$admin->action( 'plugins_loaded', 'i18n' );
			$admin->action( 'admin_enqueue_scripts', 'enqueue_scripts' );
			// $admin->action( 'admin_footer_text', 'footer_text' );
			$admin->action( 'admin_menu', 'admin_menu' );

			/**
			 * Settings related hooks
			 */
			$settings = new App\Settings( $this->plugin );
			$settings->action( 'plugins_loaded', 'init_menu' );

			/**
			 * Renders different notices
			 * 
			 * @package Codexpert\Plugin
			 * 
			 * @author Codexpert <hi@codexpert.io>
			 */
			$notice = new Notice( $this->plugin );

		else : // ! is_admin() ?

			/**
			 * Front facing hooks
			 */
			$front = new App\Front( $this->plugin );
			$front->action( 'wp_head', 'head' );
			$front->action( 'wp_footer', 'modal' );
			$front->action( 'wp_enqueue_scripts', 'enqueue_scripts' );

			/**
			 * Shortcode related hooks
			 */
			$shortcode = new App\Shortcode( $this->plugin );
			$shortcode->register( 'blm_bin_scanner', 'blm_bin_scanner' );

		endif;

		/**
		 * Cron facing hooks
		 */
		$cron = new App\Cron( $this->plugin );
		$cron->activate( 'install' );
		$cron->deactivate( 'uninstall' );

		/**
		 * Common hooks
		 *
		 * Executes on both the admin area and front area
		 */
		$common = new App\Common( $this->plugin );
		$common->action( 'woocommerce_product_options_general_product_data', 'general_product_data' );
		$common->action( 'woocommerce_process_product_meta', 'product_meta' );
		$common->action( 'woocommerce_variation_options_pricing', 'variation_fields', 5, 3 );
		$common->action( 'woocommerce_save_product_variation', 'save_product_variation', 10, 2 );

		/**
		 * AJAX related hooks
		 */
		$ajax = new App\AJAX( $this->plugin );
		$ajax->priv( 'blm_get_empty_bins', 'blm_get_empty_bins' );
		$ajax->priv( 'blm_get_products_by_location', 'blm_get_products_by_location' );
		$ajax->priv( 'blm_update_product_bin', 'blm_update_product_bin' );
		$ajax->priv( 'blm_add_product_to_bin', 'blm_add_product_to_bin' );
		$ajax->priv( 'blm_remove_product_bin', 'blm_remove_product_bin' );
		$ajax->priv( 'blm_transfer_bin', 'blm_transfer_bin' );
		$ajax->priv( 'blm_swap_bins', 'blm_swap_bins' );
		$ajax->priv( 'blm_get_products_no_bin', 'blm_get_products_no_bin' );
		$ajax->all( 'blm_scan_lookup', 'blm_scan_lookup' );
		$ajax->priv( 'blm_empty_bin', 'blm_empty_bin' );
	}

	/**
	 * Cloning is forbidden.
	 * 
	 * @access public
	 */
	public function __clone() { }

	/**
	 * Unserializing instances of this class is forbidden.
	 * 
	 * @access public
	 */
	public function __wakeup() { }

	/**
	 * Instantiate the plugin
	 * 
	 * @access public
	 * 
	 * @return $_instance
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}
}

Plugin::instance();