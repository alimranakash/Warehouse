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
	<div class="wrap">
	    <h1 class="text-3xl font-bold mb-6 text-gray-800">📦 Bin Locations Dashboard</h1>
	    
	    <div class="bg-white shadow-md rounded-2xl p-6">
	        <h2 class="text-xl font-semibold mb-4 text-gray-700">Reports</h2>
	        <div class="flex flex-wrap gap-4 mb-6">
	            <button class="load-report bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-lg transition" data-action="blm_get_empty_bins" data-target="empty-bins">
	                Empty Bins
	            </button>
	            <button class="load-report bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-4 rounded-lg transition" data-action="blm_get_products_by_location" data-target="products-by-location">
	                Products by Location
	            </button>
	            <button class="load-report bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2 px-4 rounded-lg transition" data-action="blm_get_products_no_bin" data-target="products-no-bin">
	                Products with No Bin
	            </button>
	        </div>
	        
	        <div id="empty-bins" class="report-section hidden"></div>
	        <div id="products-by-location" class="report-section hidden"></div>
	        <div id="products-no-bin" class="report-section hidden"></div>
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
	    <div class="wrap">
	        <h1 class="text-3xl font-bold mb-6 text-gray-800">🏷️ Manage Bin Locations</h1>

	        <form method="post" class="mb-6 flex gap-3">
	            <?php wp_nonce_field('blm_add_location', 'blm_add_location_nonce'); ?>
	            <input type="text" name="new_location" placeholder="Add new location" required class="border rounded-lg px-4 py-2 w-1/3 focus:ring-2 focus:ring-blue-500">
	            <button class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-5 py-2 rounded-lg transition">Add</button>
	        </form>

	        <form method="post" onsubmit="return confirm('Are you sure you want to delete the selected locations?');" class="bg-white shadow-md rounded-xl p-6">
	            <?php wp_nonce_field('blm_bulk_delete', 'blm_bulk_delete_nonce'); ?>
	            <?php wp_nonce_field('blm_delete_all', 'blm_delete_all_nonce'); ?>
	            <table class="min-w-full border border-gray-200 divide-y divide-gray-200">
	                <thead class="bg-gray-50">
	                    <tr>
	                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"><input type="checkbox" onclick="jQuery('input[name=\'location_ids[]\']').prop('checked', this.checked);"></th>
	                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
	                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
	                    </tr>
	                </thead>
	                <tbody class="bg-white divide-y divide-gray-200">
	                    <?php foreach ($locations as $loc): ?>
	                    <tr>
	                        <td class="px-4 py-2"><input type="checkbox" name="location_ids[]" value="<?php echo $loc->id; ?>"></td>
	                        <td class="px-4 py-2 font-medium text-gray-700"><?php echo esc_html($loc->name); ?></td>
	                        <td class="px-4 py-2 flex gap-2">
	                            <form method="post" class="flex gap-2">
	                                <?php wp_nonce_field('blm_edit_location', 'blm_edit_location_nonce'); ?>
	                                <input type="hidden" name="edit_id" value="<?php echo $loc->id; ?>">
	                                <input type="text" name="edit_location" value="<?php echo esc_attr($loc->name); ?>" required class="border rounded px-2 py-1">
	                                <button class="bg-yellow-500 hover:bg-yellow-600 text-white px-3 py-1 rounded">Update</button>
	                            </form>
	                            <a href="?page=bin-locations-locations&delete=<?php echo $loc->id; ?>&_wpnonce=<?php echo wp_create_nonce('blm_delete_location'); ?>" class="bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded" onclick="return confirm('Delete this location?');">Delete</a>
	                        </td>
	                    </tr>
	                    <?php endforeach; ?>
	                </tbody>
	            </table>

	            <div class="mt-6 flex gap-3">
	                <button class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded" name="bulk_delete">Delete Selected</button>
	                <button class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded" name="delete_all" onclick="return confirm('Delete ALL locations?');">Delete All Locations</button>
	            </div>
	        </form>
	    </div>
	    <?php
	}

}