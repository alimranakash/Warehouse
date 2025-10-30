<?php
/**
 * All admin facing functions
 */
namespace Worzen\Warehouse\App;
use Codexpert\Plugin\Base;
use Codexpert\Plugin\Metabox;

/**
 * if accessed directly, exit.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @package Plugin
 * @subpackage Admin
 * @author Codexpert <hi@codexpert.io>
 */
class Admin extends Base {

	public $plugin;

	/**
	 * Constructor function
	 */
	public function __construct( $plugin ) {
		$this->plugin	= $plugin;
		$this->slug		= $this->plugin['TextDomain'];
		$this->name		= $this->plugin['Name'];
		$this->server	= $this->plugin['server'];
		$this->version	= $this->plugin['Version'];
	}

	/**
	 * Internationalization
	 */
	public function i18n() {
		load_plugin_textdomain( 'plugin-client', false, BLM_DIR . '/languages/' );
	}

	/**
	 * Installer. Runs once when the plugin in activated.
	 *
	 * @since 1.0
	 */
	public function install() {

		if( ! get_option( 'plugin-client_version' ) ){
			update_option( 'plugin-client_version', $this->version );
		}
		
		if( ! get_option( 'plugin-client_install_time' ) ){
			update_option( 'plugin-client_install_time', time() );
		}

		global $wpdb;

		$table_name = $wpdb->prefix . 'bin_locations';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(255) NOT NULL,
			PRIMARY KEY (id)
		) $charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
	}

	/**
	 * Enqueue JavaScripts and stylesheets
	 */
	public function enqueue_scripts( $hook ) {
		$min = defined( 'BLM_DEBUG' ) && BLM_DEBUG ? '' : '.min';

		wp_enqueue_style( $this->slug, plugins_url( "/assets/css/admin{$min}.css", BLM ), '', time(), 'all' );

		if ( $hook == 'toplevel_page_bin-locations' || $hook == 'bin-locations_page_bin-locations-locations' ) {
			wp_enqueue_script( 'tailwindcss', 'https://cdn.tailwindcss.com', [ 'jquery' ], time(), false );
			wp_enqueue_script( $this->slug . '-scanner', plugins_url( "/assets/js/scanner{$min}.js", BLM ), [ 'jquery' ], time(), true );
			wp_enqueue_script( $this->slug . '-locations', plugins_url( "/assets/js/locations{$min}.js", BLM ), [ 'jquery' ], time(), true );

			$localized = [
				'ajaxurl'	=> admin_url( 'admin-ajax.php' ),
				'_wpnonce'	=> wp_create_nonce(),
			];
			wp_localize_script( $this->slug . '-locations', 'BLML', apply_filters( "{$this->slug}-localized", $localized ) );

			// Add global ajaxurl for compatibility
			wp_add_inline_script( $this->slug . '-locations', 'var ajaxurl = "' . admin_url( 'admin-ajax.php' ) . '";', 'before' );
	    }

		wp_enqueue_script( $this->slug, plugins_url( "/assets/js/admin{$min}.js", BLM ), [ 'jquery' ], time(), true );
	}

	public function footer_text( $text ) {
		if( get_current_screen()->parent_base != $this->slug ) return $text;

		return sprintf( __( 'Built with %1$s by the folks at <a href="%2$s" target="_blank">Codexpert, Inc</a>.' ), '&hearts;', 'https://codexpert.io' );
	}

	public function modal() {
		echo '
		<div id="plugin-client-modal" style="display: none">
			<img id="plugin-client-modal-loader" src="' . esc_attr( BLM_ASSET . '/img/loader.gif' ) . '" />
		</div>';
	}

	public function admin_menu() {
		add_menu_page(
	        'Bin Locations',
	        'Bin Locations',
	        'manage_options',
	        'bin-locations',
	        [ $this, 'bin_locations_main_page' ],
	        'dashicons-archive',
	        3
	    );

	    add_submenu_page(
	        'bin-locations',
	        'Manage Locations',
	        'Locations',
	        'manage_options',
	        'bin-locations-locations',
	        [ $this, 'bin_locations_locations_page' ]
	    );
	}

	public function bin_locations_main_page() {
	?>
	<div class="wrap bg-gray-50 -ml-5 -mt-2 p-8 min-h-screen">
	    <!-- Header -->
	    <div class="mb-8">
	        <div class="flex items-center gap-3 mb-2">
	            <div class="bg-gradient-to-br from-blue-500 to-blue-600 p-3 rounded-xl shadow-lg">
	                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
	                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
	                </svg>
	            </div>
	            <div>
	                <h1 class="text-4xl font-bold text-gray-900">Warehouse Dashboard</h1>
	                <p class="text-gray-500 text-sm mt-1">Manage your bin locations and inventory</p>
	            </div>
	        </div>
	    </div>

	    <!-- Stats Cards -->
	    <?php
	    global $wpdb;
	    $table = $wpdb->prefix . 'bin_locations';
	    $total_bins = $wpdb->get_var("SELECT COUNT(*) FROM $table");

	    // Count products with bin locations
	    $total_products = $wpdb->get_var("SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key = 'bin_location' AND meta_value != ''");

	    // Get all bin locations
	    $all_bins = $wpdb->get_col("SELECT name FROM $table");

	    // Get bins that have products
	    $bins_with_products = $wpdb->get_col("SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key = 'bin_location' AND meta_value != ''");

	    // Calculate empty bins
	    $empty_bins = count(array_diff($all_bins, $bins_with_products));
	    ?>
	    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
	        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 hover:shadow-md transition-shadow">
	            <div class="flex items-center justify-between">
	                <div>
	                    <p class="text-sm font-medium text-gray-500 uppercase tracking-wide">Total Bins</p>
	                    <p class="text-3xl font-bold text-gray-900 mt-2"><?php echo number_format($total_bins); ?></p>
	                </div>
	                <div class="bg-blue-100 p-4 rounded-xl">
	                    <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
	                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
	                    </svg>
	                </div>
	            </div>
	        </div>

	        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 hover:shadow-md transition-shadow">
	            <div class="flex items-center justify-between">
	                <div>
	                    <p class="text-sm font-medium text-gray-500 uppercase tracking-wide">Empty Bins</p>
	                    <p class="text-3xl font-bold text-orange-600 mt-2"><?php echo number_format($empty_bins); ?></p>
	                </div>
	                <div class="bg-orange-100 p-4 rounded-xl">
	                    <svg class="w-8 h-8 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
	                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
	                    </svg>
	                </div>
	            </div>
	        </div>

	        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 hover:shadow-md transition-shadow">
	            <div class="flex items-center justify-between">
	                <div>
	                    <p class="text-sm font-medium text-gray-500 uppercase tracking-wide">Products</p>
	                    <p class="text-3xl font-bold text-green-600 mt-2"><?php echo number_format($total_products); ?></p>
	                </div>
	                <div class="bg-green-100 p-4 rounded-xl">
	                    <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
	                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/>
	                    </svg>
	                </div>
	            </div>
	        </div>
	    </div>

	    <!-- Reports Section -->
	    <div class="bg-white shadow-sm rounded-2xl border border-gray-100 overflow-hidden">
	        <div class="border-b border-gray-200 bg-gradient-to-r from-gray-50 to-white px-6 py-5">
	            <h2 class="text-xl font-bold text-gray-900 flex items-center gap-2">
	                <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
	                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
	                </svg>
	                Reports & Analytics
	            </h2>
	            <p class="text-gray-500 text-sm mt-1">View detailed reports about your warehouse inventory</p>
	        </div>

	        <div class="p-6">
	            <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-6">
	                <button class="load-report group bg-white border-2 border-blue-200 hover:border-blue-500 hover:bg-blue-50 rounded-xl p-4 transition-all duration-200 text-left" data-action="blm_get_empty_bins" data-target="empty-bins">
	                    <div class="flex items-center gap-3">
	                        <div class="bg-blue-100 group-hover:bg-blue-500 p-2.5 rounded-lg transition-colors">
	                            <svg class="w-5 h-5 text-blue-600 group-hover:text-white transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
	                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
	                            </svg>
	                        </div>
	                        <div class="flex-1">
	                            <div class="font-bold text-gray-900 group-hover:text-blue-700 transition-colors">Empty Bins</div>
	                            <div class="text-xs text-gray-500 mt-0.5">View all empty locations</div>
	                        </div>
	                        <svg class="w-5 h-5 text-gray-400 group-hover:text-blue-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
	                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
	                        </svg>
	                    </div>
	                </button>

	                <button class="load-report group bg-white border-2 border-green-200 hover:border-green-500 hover:bg-green-50 rounded-xl p-4 transition-all duration-200 text-left" data-action="blm_get_products_by_location" data-target="products-by-location">
	                    <div class="flex items-center gap-3">
	                        <div class="bg-green-100 group-hover:bg-green-500 p-2.5 rounded-lg transition-colors">
	                            <svg class="w-5 h-5 text-green-600 group-hover:text-white transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
	                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
	                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
	                            </svg>
	                        </div>
	                        <div class="flex-1">
	                            <div class="font-bold text-gray-900 group-hover:text-green-700 transition-colors">By Location</div>
	                            <div class="text-xs text-gray-500 mt-0.5">Products grouped by bin</div>
	                        </div>
	                        <svg class="w-5 h-5 text-gray-400 group-hover:text-green-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
	                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
	                        </svg>
	                    </div>
	                </button>

	                <button class="load-report group bg-white border-2 border-purple-200 hover:border-purple-500 hover:bg-purple-50 rounded-xl p-4 transition-all duration-200 text-left" data-action="blm_get_products_no_bin" data-target="products-no-bin">
	                    <div class="flex items-center gap-3">
	                        <div class="bg-purple-100 group-hover:bg-purple-500 p-2.5 rounded-lg transition-colors">
	                            <svg class="w-5 h-5 text-purple-600 group-hover:text-white transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
	                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
	                            </svg>
	                        </div>
	                        <div class="flex-1">
	                            <div class="font-bold text-gray-900 group-hover:text-purple-700 transition-colors">No Bin Assigned</div>
	                            <div class="text-xs text-gray-500 mt-0.5">Products without location</div>
	                        </div>
	                        <svg class="w-5 h-5 text-gray-400 group-hover:text-purple-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
	                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
	                        </svg>
	                    </div>
	                </button>
	            </div>

	            <div id="empty-bins" class="report-section"></div>
	            <div id="products-by-location" class="report-section"></div>
	            <div id="products-no-bin" class="report-section"></div>
	        </div>
	    </div>
	</div>
	<?php
	}

	public function bin_locations_locations_page() {

		wp_enqueue_script( 'tailwindcss', 'https://cdn.tailwindcss.com', [ 'jquery' ], time(), false );

	    global $wpdb;
	    if (!$wpdb) return;

	    $table = $wpdb->prefix . 'bin_locations';

	    // Handle Add New Location
	    if (isset($_POST['new_location']) && !empty($_POST['new_location'])) {
	        // Verify nonce
	        if (!isset($_POST['blm_add_location_nonce']) || !wp_verify_nonce($_POST['blm_add_location_nonce'], 'blm_add_location')) {
	            echo '<div class="notice notice-error is-dismissible"><p>Security check failed!</p></div>';
	        } else {
	            $new_location = strtoupper(sanitize_text_field($_POST['new_location']));

	            // Check if location already exists
	            $exists = $wpdb->get_var($wpdb->prepare(
	                "SELECT COUNT(*) FROM $table WHERE UPPER(name) = %s",
	                $new_location
	            ));

	            if ($exists) {
	                echo '<div class="notice notice-error is-dismissible"><p>Location already exists!</p></div>';
	            } else {
	                $wpdb->insert($table, ['name' => $new_location]);
	                echo '<div class="notice notice-success is-dismissible"><p>Location added successfully!</p></div>';
	            }
	        }
	    }

	    // Handle Edit Location
	    if (isset($_POST['edit_id']) && isset($_POST['edit_location'])) {
	        // Verify nonce
	        if (!isset($_POST['blm_edit_location_nonce']) || !wp_verify_nonce($_POST['blm_edit_location_nonce'], 'blm_edit_location')) {
	            echo '<div class="notice notice-error is-dismissible"><p>Security check failed!</p></div>';
	        } else {
	            $edit_id = intval($_POST['edit_id']);
	            $edit_location = strtoupper(sanitize_text_field($_POST['edit_location']));

	            $wpdb->update($table, ['name' => $edit_location], ['id' => $edit_id]);
	            echo '<div class="notice notice-success is-dismissible"><p>Location updated successfully!</p></div>';
	        }
	    }

	    // Handle Delete Single Location
	    if (isset($_GET['delete']) && isset($_GET['_wpnonce'])) {
	        // Verify nonce
	        if (!wp_verify_nonce($_GET['_wpnonce'], 'blm_delete_location')) {
	            echo '<div class="notice notice-error is-dismissible"><p>Security check failed!</p></div>';
	        } else {
	            $delete_id = intval($_GET['delete']);
	            $wpdb->delete($table, ['id' => $delete_id]);
	            echo '<div class="notice notice-success is-dismissible"><p>Location deleted successfully!</p></div>';
	        }
	    }

	    // Handle Bulk Delete
	    if (isset($_POST['bulk_delete']) && isset($_POST['location_ids'])) {
	        // Verify nonce
	        if (!isset($_POST['blm_bulk_delete_nonce']) || !wp_verify_nonce($_POST['blm_bulk_delete_nonce'], 'blm_bulk_delete')) {
	            echo '<div class="notice notice-error is-dismissible"><p>Security check failed!</p></div>';
	        } else {
	            $ids = array_map('intval', $_POST['location_ids']);
	            $placeholders = implode(',', array_fill(0, count($ids), '%d'));
	            $wpdb->query($wpdb->prepare("DELETE FROM $table WHERE id IN ($placeholders)", $ids));
	            echo '<div class="notice notice-success is-dismissible"><p>Selected locations deleted successfully!</p></div>';
	        }
	    }

	    // Handle Delete All
	    if (isset($_POST['delete_all'])) {
	        // Verify nonce
	        if (!isset($_POST['blm_delete_all_nonce']) || !wp_verify_nonce($_POST['blm_delete_all_nonce'], 'blm_delete_all')) {
	            echo '<div class="notice notice-error is-dismissible"><p>Security check failed!</p></div>';
	        } else {
	            $wpdb->query("TRUNCATE TABLE $table");
	            echo '<div class="notice notice-success is-dismissible"><p>All locations deleted successfully!</p></div>';
	        }
	    }

	    $locations = $wpdb->get_results("SELECT * FROM $table ORDER BY name ASC");
	    ?>
	    <div class="wrap bg-gray-50 -ml-5 -mt-2 p-8 min-h-screen">
	        <!-- Header -->
	        <div class="mb-8">
	            <div class="flex items-center gap-3 mb-2">
	                <div class="bg-gradient-to-br from-indigo-500 to-indigo-600 p-3 rounded-xl shadow-lg">
	                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
	                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
	                    </svg>
	                </div>
	                <div>
	                    <h1 class="text-4xl font-bold text-gray-900">Manage Locations</h1>
	                    <p class="text-gray-500 text-sm mt-1">Add, edit, and organize your bin locations</p>
	                </div>
	            </div>
	        </div>

	        <!-- Add New Location Card -->
	        <div class="bg-white shadow-sm rounded-2xl border border-gray-100 p-8 mb-8">
	            <div class="flex items-center gap-3 mb-6">
	                <div class="bg-blue-100 p-2 rounded-lg">
	                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
	                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
	                    </svg>
	                </div>
	                <h2 class="text-xl font-bold text-gray-900">Add New Location</h2>
	            </div>

	            <form method="post" class="flex gap-4">
	                <?php wp_nonce_field('blm_add_location', 'blm_add_location_nonce'); ?>
	                <div class="flex-1">
	                    <input type="text"
	                           name="new_location"
	                           placeholder="Enter location name (e.g., A1, B2, SHELF-01)"
	                           required
	                           class="w-full border-2 border-gray-200 rounded-xl px-5 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all text-lg font-medium uppercase"
	                           autocomplete="off">
	                </div>
	                <button type="submit" class="bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white font-bold px-8 py-3 rounded-xl shadow-lg hover:shadow-xl transition-all duration-300 transform hover:-translate-y-0.5 flex items-center gap-2">
	                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
	                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
	                    </svg>
	                    Add Location
	                </button>
	            </form>
	        </div>

	        <!-- Locations Table -->
	        <div class="bg-white shadow-sm rounded-2xl border border-gray-100 overflow-hidden">
	            <div class="border-b border-gray-200 bg-gradient-to-r from-gray-50 to-white px-8 py-6">
	                <h2 class="text-2xl font-bold text-gray-900 flex items-center gap-3">
	                    <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
	                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
	                    </svg>
	                    All Locations
	                </h2>
	                <p class="text-gray-500 mt-1"><?php echo count($locations); ?> location(s) configured</p>
	            </div>

	            <form method="post" onsubmit="return confirm('Are you sure you want to delete the selected locations?');">
	                <?php wp_nonce_field('blm_bulk_delete', 'blm_bulk_delete_nonce'); ?>
	                <?php wp_nonce_field('blm_delete_all', 'blm_delete_all_nonce'); ?>

	                <div class="overflow-x-auto">
	                    <table class="w-full">
	                        <thead>
	                            <tr class="bg-gradient-to-r from-gray-50 to-gray-100 border-b-2 border-gray-200">
	                                <th class="px-6 py-4 text-left w-12">
	                                    <input type="checkbox" id="select-all" class="w-4 h-4 text-blue-600 rounded border-gray-300 focus:ring-2 focus:ring-blue-500">
	                                </th>
	                                <th class="px-6 py-4 text-left text-sm font-bold text-gray-700 uppercase tracking-wider">Location Name</th>
	                                <th class="px-6 py-4 text-left text-sm font-bold text-gray-700 uppercase tracking-wider">Actions</th>
	                            </tr>
	                        </thead>
	                        <tbody class="divide-y divide-gray-200">
	                            <?php if (empty($locations)): ?>
	                                <tr>
	                                    <td colspan="3" class="px-6 py-12 text-center">
	                                        <div class="flex flex-col items-center justify-center text-gray-400">
	                                            <svg class="w-16 h-16 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
	                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
	                                            </svg>
	                                            <p class="text-lg font-medium">No locations found</p>
	                                            <p class="text-sm mt-1">Add your first bin location above</p>
	                                        </div>
	                                    </td>
	                                </tr>
	                            <?php else: ?>
	                                <?php foreach ($locations as $loc): ?>
	                                <tr class="hover:bg-blue-50 transition-colors group">
	                                    <td class="px-6 py-4">
	                                        <input type="checkbox" name="location_ids[]" value="<?php echo $loc->id; ?>" class="w-4 h-4 text-blue-600 rounded border-gray-300 focus:ring-2 focus:ring-blue-500">
	                                    </td>
	                                    <td class="px-6 py-4">
	                                        <div class="flex items-center gap-3">
	                                            <div class="bg-indigo-100 p-2 rounded-lg">
	                                                <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
	                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
	                                                </svg>
	                                            </div>
	                                            <span class="text-lg font-bold text-gray-900"><?php echo esc_html($loc->name); ?></span>
	                                        </div>
	                                    </td>
	                                    <td class="px-6 py-4">
	                                        <div class="flex items-center gap-2">
	                                            <form method="post" class="flex items-center gap-2">
	                                                <?php wp_nonce_field('blm_edit_location', 'blm_edit_location_nonce'); ?>
	                                                <input type="hidden" name="edit_id" value="<?php echo $loc->id; ?>">
	                                                <input type="text"
	                                                       name="edit_location"
	                                                       value="<?php echo esc_attr($loc->name); ?>"
	                                                       required
	                                                       class="border-2 border-gray-200 rounded-lg px-3 py-2 text-sm font-medium uppercase focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500 transition-all">
	                                                <button type="submit" class="bg-gradient-to-r from-yellow-500 to-yellow-600 hover:from-yellow-600 hover:to-yellow-700 text-white px-4 py-2 rounded-lg font-semibold text-sm shadow-md hover:shadow-lg transition-all duration-300 transform hover:-translate-y-0.5 flex items-center gap-2">
	                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
	                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
	                                                    </svg>
	                                                    Update
	                                                </button>
	                                            </form>
	                                            <a href="?page=bin-locations-locations&delete=<?php echo $loc->id; ?>&_wpnonce=<?php echo wp_create_nonce('blm_delete_location'); ?>"
	                                               class="bg-gradient-to-r from-red-500 to-red-600 hover:from-red-600 hover:to-red-700 text-white px-4 py-2 rounded-lg font-semibold text-sm shadow-md hover:shadow-lg transition-all duration-300 transform hover:-translate-y-0.5 flex items-center gap-2"
	                                               onclick="return confirm('Delete this location?');">
	                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
	                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
	                                                </svg>
	                                                Delete
	                                            </a>
	                                        </div>
	                                    </td>
	                                </tr>
	                                <?php endforeach; ?>
	                            <?php endif; ?>
	                        </tbody>
	                    </table>
	                </div>

	                <?php if (!empty($locations)): ?>
	                <div class="px-8 py-6 bg-gray-50 border-t border-gray-200 flex gap-4">
	                    <button type="submit" name="bulk_delete" class="bg-gradient-to-r from-red-500 to-red-600 hover:from-red-600 hover:to-red-700 text-white px-6 py-3 rounded-xl font-bold shadow-lg hover:shadow-xl transition-all duration-300 transform hover:-translate-y-0.5 flex items-center gap-2">
	                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
	                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
	                        </svg>
	                        Delete Selected
	                    </button>
	                    <button type="submit" name="delete_all" onclick="return confirm('Delete ALL locations? This action cannot be undone!');" class="bg-gradient-to-r from-gray-600 to-gray-700 hover:from-gray-700 hover:to-gray-800 text-white px-6 py-3 rounded-xl font-bold shadow-lg hover:shadow-xl transition-all duration-300 transform hover:-translate-y-0.5 flex items-center gap-2">
	                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
	                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
	                        </svg>
	                        Delete All Locations
	                    </button>
	                </div>
	                <?php endif; ?>
	            </form>
	        </div>
	    </div>

	    <script>
	        jQuery(document).ready(function($) {
	            $('#select-all').on('change', function() {
	                $('input[name="location_ids[]"]').prop('checked', $(this).is(':checked'));
	            });
	        });
	    </script>
	    <?php
	}

}