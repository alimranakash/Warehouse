<?php
/**
 * All AJAX related functions
 */
namespace Worzen\Warehouse\App;
use Codexpert\Plugin\Base;

/**
 * if accessed directly, exit.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @package Plugin
 * @subpackage AJAX
 * @author Codexpert <hi@codexpert.io>
 */
class AJAX extends Base {

	public $plugin;

	/**
	 * Constructor function
	 */
	public function __construct( $plugin ) {
		$this->plugin	= $plugin;
		$this->slug		= $this->plugin['TextDomain'];
		$this->name		= $this->plugin['Name'];
		$this->version	= $this->plugin['Version'];
	}

	//Empty Bins
	public function blm_get_empty_bins() {
	    global $wpdb;
	    if (!$wpdb) {
	        wp_die();
	    }
	    // Get bins that are assigned to products but contain no stock
	    $empty_bins = $wpdb->get_results("
	        SELECT p.ID, UPPER(pm.meta_value) AS bin, sku.meta_value AS sku, atum.barcode AS barcode, p.post_title, stock.meta_value AS quantity
	        FROM {$wpdb->prefix}postmeta pm
	        JOIN {$wpdb->prefix}posts p ON pm.post_id = p.ID
	        LEFT JOIN {$wpdb->prefix}postmeta sku ON sku.post_id = p.ID AND sku.meta_key = '_sku'
	        LEFT JOIN {$wpdb->prefix}atum_product_data atum ON atum.product_id = p.ID
	        INNER JOIN {$wpdb->prefix}postmeta stock ON stock.post_id = pm.post_id AND stock.meta_key = '_stock'
	        INNER JOIN {$wpdb->prefix}postmeta manage ON manage.post_id = pm.post_id AND manage.meta_key = '_manage_stock' AND manage.meta_value = 'yes'
	        WHERE pm.meta_key = 'bin_location' AND (stock.meta_value IS NULL OR stock.meta_value <= 0)
	          AND UPPER(pm.meta_value) IN (
	              SELECT UPPER(pm2.meta_value)
	              FROM {$wpdb->prefix}postmeta pm2
	              INNER JOIN {$wpdb->prefix}postmeta stock2 ON stock2.post_id = pm2.post_id AND stock2.meta_key = '_stock'
	              INNER JOIN {$wpdb->prefix}postmeta manage2 ON manage2.post_id = pm2.post_id AND manage2.meta_key = '_manage_stock' AND manage2.meta_value = 'yes'
	              WHERE pm2.meta_key = 'bin_location'
	              GROUP BY UPPER(pm2.meta_value)
	              HAVING SUM(CASE WHEN stock2.meta_value > 0 THEN 1 ELSE 0 END) = 0
	          )
	        ORDER BY bin ASC");

	    // Get bins that are defined but have no products assigned
	    $all_bins = $wpdb->get_col("SELECT UPPER(name) FROM {$wpdb->prefix}bin_locations");
	    $allocated_bins = $wpdb->get_col("
	        SELECT DISTINCT UPPER(meta_value)
	        FROM {$wpdb->prefix}postmeta
	        WHERE meta_key = 'bin_location'");
	    $unused = array_diff($all_bins, $allocated_bins);

	    $options = bin_locations_get_options();
	    echo '<h3>Empty Bin Locations</h3>';
	    if (empty($empty_bins) && empty($unused)) {
	        echo '<p><em>No bins are empty or unused.</em></p>';
	    } else {
	        echo '<datalist id="blm-bin-options">';
			foreach ($options as $opt) {
			    echo '<option value="' . esc_attr($opt) . '"></option>';
			}
			echo '</datalist>';

			// Table wrapper
			echo '<div class="overflow-x-auto bg-white shadow-lg rounded-2xl border border-gray-100">';

			echo '<table class="min-w-full divide-y divide-gray-200 text-sm text-gray-700">';
			echo '<thead class="bg-gray-50 text-gray-600 uppercase text-xs font-semibold">';
			echo '<tr>';
			echo '<th class="px-3 py-3 text-left"><input type="checkbox" class="blm-select-all rounded border-gray-300"></th>';
			echo '<th class="px-3 py-3 text-left">Bin</th>';
			echo '<th class="px-3 py-3 text-left">SKU</th>';
			echo '<th class="px-3 py-3 text-left">Barcode</th>';
			echo '<th class="px-3 py-3 text-left w-2/5">Name</th>';
			echo '<th class="px-3 py-3 text-left">Quantity</th>';
			echo '<th class="px-3 py-3 text-left w-32">Action</th>';
			echo '<th class="px-3 py-3 text-left w-10"></th>';
			echo '</tr>';
			echo '</thead>';
			echo '<tbody class="divide-y divide-gray-100">';

			$grouped = [];
			foreach ($empty_bins as $row) {
			    $grouped[$row->bin][] = $row;
			}

			foreach ($grouped as $bin => $rows) {
			    $count = count($rows);
			    foreach ($rows as $i => $row) {
			        echo '<tr data-product-id="' . esc_attr($row->ID) . '" class="hover:bg-gray-50 transition">';
			        echo '<td class="px-3 py-2"><input type="checkbox" class="blm-select-row rounded border-gray-300" data-product-id="' . esc_attr($row->ID) . '"></td>';
			        echo '<td class="px-3 py-2 blm-bin-editable font-medium text-gray-700" data-product-id="' . esc_attr($row->ID) . '">' . esc_html($row->bin) . '</td>';
			        echo '<td class="px-3 py-2">' . esc_html($row->sku) . '</td>';
			        echo '<td class="px-3 py-2">' . esc_html($row->barcode) . '</td>';
			        echo '<td class="px-3 py-2 w-2/5">' . esc_html($row->post_title) . '</td>';
			        echo '<td class="px-3 py-2 text-center">' . esc_html($row->quantity) . '</td>';

			        // Add product button (only for last row in group)
			        if ($i === $count - 1) {
			            echo '<td class="px-3 py-2 w-32 text-center">
			                    <button type="button" class="blm-add-product bg-blue-600 hover:bg-blue-700 text-white px-3 py-1.5 rounded-md text-xs font-semibold transition" data-bin="' . esc_attr($bin) . '">
			                        + Add Product
			                    </button>
			                  </td>';
			        } else {
			            echo '<td class="px-3 py-2 w-32"></td>';
			        }

			        // Remove button
			        echo '<td class="px-3 py-2 w-10 text-center">
			                <button type="button" class="blm-remove-product bg-red-500 hover:bg-red-600 text-white font-bold rounded-full w-6 h-6 flex items-center justify-center transition" data-product-id="' . esc_attr($row->ID) . '">
			                    ×
			                </button>
			              </td>';
			        echo '</tr>';
			    }
			}

			// Unused bins
			foreach ($unused as $bin) {
			    echo '<tr class="blm-unused-bin hover:bg-gray-50 transition" data-bin="' . esc_attr($bin) . '">';
			    echo '<td class="px-3 py-2"></td>';
			    echo '<td class="px-3 py-2 font-medium text-gray-700">' . esc_html($bin) . '</td>';
			    echo '<td class="px-3 py-2"></td><td class="px-3 py-2"></td>';
			    echo '<td class="px-3 py-2 text-gray-400 italic w-2/5">No Stock Assigned</td>';
			    echo '<td class="px-3 py-2"></td>';
			    echo '<td class="px-3 py-2 w-32 text-center">
			            <button type="button" class="blm-add-product bg-blue-600 hover:bg-blue-700 text-white px-3 py-1.5 rounded-md text-xs font-semibold transition" data-bin="' . esc_attr($bin) . '">
			                + Add Product
			            </button>
			          </td>';
			    echo '<td class="px-3 py-2 w-10"></td>';
			    echo '</tr>';
			}

			echo '</tbody></table>';
			echo '</div>';

	    }
	    wp_die();
	}

	// Products by Location
	public function blm_get_products_by_location() {
	    global $wpdb;
	    if (!$wpdb) {
	        wp_die();
	    }
	    $bins = $wpdb->get_col("SELECT name FROM {$wpdb->prefix}bin_locations ORDER BY name ASC");
	    echo '<h3>Products by Bin Location</h3>';
	    if (empty($bins)) {
	        echo '<p><em>No bin locations found.</em></p>';
	    } else {
	        // Datalist of all bin options for inline editing
	        echo '<datalist id="blm-bin-options">';
			foreach ($bins as $opt) {
			    echo '<option value="' . esc_attr($opt) . '"></option>';
			}
			echo '</datalist>';

			echo '<div class="overflow-x-auto shadow-md rounded-lg mt-6">';
			echo '<table class="min-w-full text-sm text-left text-gray-700 border border-gray-200">';
			echo '<thead class="bg-gray-100 text-gray-800 uppercase text-xs tracking-wider">';
			echo '<tr>
			        <th class="p-3 w-10"><input type="checkbox" class="blm-select-all"></th>
			        <th class="p-3">Bin</th>
			        <th class="p-3">SKU</th>
			        <th class="p-3">Barcode</th>
			        <th class="p-3 w-2/5">Name</th>
			        <th class="p-3 w-32"></th>
			        <th class="p-3 w-10"></th>
			      </tr>';
			echo '</thead><tbody class="divide-y divide-gray-200">';

			foreach ($bins as $bin) {
			    $products = $wpdb->get_results($wpdb->prepare("
			        SELECT p.ID, p.post_title, sku.meta_value AS sku, atum.barcode AS barcode
			        FROM {$wpdb->prefix}postmeta pm
			        JOIN {$wpdb->prefix}posts p ON pm.post_id = p.ID
			        LEFT JOIN {$wpdb->prefix}postmeta sku ON sku.post_id = p.ID AND sku.meta_key = '_sku'
			        LEFT JOIN {$wpdb->prefix}atum_product_data atum ON atum.product_id = p.ID
			        WHERE pm.meta_key = 'bin_location' AND UPPER(pm.meta_value) = %s
			    ", $bin));

			    if ($products) {
			        $count = count($products);
			        foreach ($products as $index => $product) {
			            echo '<tr class="hover:bg-gray-50 transition" data-product-id="' . esc_attr($product->ID) . '">';
			            echo '<td class="p-3"><input type="checkbox" class="blm-select-row" data-product-id="' . esc_attr($product->ID) . '"></td>';
			            echo '<td class="p-3 font-medium text-gray-800 blm-bin-editable" data-product-id="' . esc_attr($product->ID) . '">' . esc_html($bin) . '</td>';
			            echo '<td class="p-3">' . esc_html($product->sku) . '</td>';
			            echo '<td class="p-3">' . esc_html($product->barcode) . '</td>';
			            echo '<td class="p-3 w-2/5">' . esc_html($product->post_title) . '</td>';

			            if ($index === $count - 1) {
			                echo '<td class="p-3 w-32">
			                        <button type="button" class="blm-add-product bg-blue-600 text-white px-3 py-1 rounded-md hover:bg-blue-700 transition text-xs font-medium" data-bin="' . esc_attr($bin) . '">Add Product</button>
			                      </td>';
			            } else {
			                echo '<td class="p-3 w-32"></td>';
			            }

			            echo '<td class="p-3 w-10 text-center">
			                    <button type="button" class="blm-remove-product bg-red-500 text-white rounded-md px-2 py-1 hover:bg-red-600 transition text-xs" data-product-id="' . esc_attr($product->ID) . '">&times;</button>
			                  </td>';
			            echo '</tr>';
			        }
			    } else {
			        echo '<tr class="hover:bg-gray-50 transition blm-no-products" data-bin="' . esc_attr($bin) . '">
			                <td class="p-3 w-10"></td>
			                <td class="p-3 font-medium text-gray-800">' . esc_html($bin) . '</td>
			                <td class="p-3"></td>
			                <td class="p-3"></td>
			                <td class="p-3 w-2/5 italic text-gray-500">No products assigned.</td>
			                <td class="p-3 w-32">
			                    <button type="button" class="blm-add-product bg-green-600 text-white px-3 py-1 rounded-md hover:bg-green-700 transition text-xs font-medium" data-bin="' . esc_attr($bin) . '">Add Product</button>
			                </td>
			                <td class="p-3 w-10"></td>
			              </tr>';
			    }
			}
			echo '</tbody></table>';
			echo '</div>';

	    }
	    wp_die();
	}

	public function blm_update_product_bin() {
	    if (!current_user_can('manage_options')) {
	        wp_send_json_error('Permission denied');
	    }
	    $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
	    $bin       = isset($_POST['bin']) ? strtoupper(sanitize_text_field($_POST['bin'])) : '';
	    if (!$product_id || $bin === '') {
	        wp_send_json_error('Invalid data');
	    }
	    if (!bin_locations_exists($bin)) {
	        wp_send_json_error('Location does not exist');
	    }
	    // Remove any existing bin assignments to avoid stale meta rows
	    delete_post_meta($product_id, 'bin_location');
	    update_post_meta($product_id, 'bin_location', $bin);
	    wp_send_json_success(['bin' => $bin]);
	}

	public function blm_add_product_to_bin() {
	    if (!current_user_can('manage_options')) {
	        wp_send_json_error('Permission denied');
	    }

	    $bin        = isset($_POST['bin']) ? strtoupper(sanitize_text_field($_POST['bin'])) : '';
	    $identifier = isset($_POST['identifier']) ? sanitize_text_field($_POST['identifier']) : '';

	    if ($bin === '' || $identifier === '') {
	        wp_send_json_error('Invalid data');
	    }
	    if (!bin_locations_exists($bin)) {
	        wp_send_json_error('Location does not exist');
	    }

	    global $wpdb;
	    $postmeta_table = $wpdb->postmeta;
	    $product_id     = $wpdb->get_var($wpdb->prepare("SELECT post_id FROM {$postmeta_table} WHERE meta_key = '_sku' AND meta_value = %s LIMIT 1", $identifier));

	    if (!$product_id) {
	        $atum_table  = $wpdb->prefix . 'atum_product_data';
	        $product_id  = $wpdb->get_var($wpdb->prepare("SELECT product_id FROM {$atum_table} WHERE barcode = %s LIMIT 1", $identifier));
	    }

	    if (!$product_id) {
	        wp_send_json_error('Product not found');
	    }

	    // Remove previous assignments to avoid multiple bin_location rows
	    delete_post_meta($product_id, 'bin_location');
	    update_post_meta($product_id, 'bin_location', $bin);

	    $product = get_post($product_id);
	    $sku     = get_post_meta($product_id, '_sku', true);
	    $barcode = $wpdb->get_var($wpdb->prepare("SELECT barcode FROM {$wpdb->prefix}atum_product_data WHERE product_id = %d", $product_id));

	    wp_send_json_success([
	        'ID'      => $product_id,
	        'bin'     => $bin,
	        'sku'     => $sku,
	        'barcode' => $barcode,
	        'name'    => $product ? $product->post_title : '',
	    ]);
	}

	public function blm_remove_product_bin() {
	    if (!current_user_can('manage_options')) {
	        wp_send_json_error('Permission denied');
	    }
	    $ids = isset($_POST['product_ids']) ? (array)$_POST['product_ids'] : [];
	    $ids = array_map('intval', $ids);
	    foreach ($ids as $id) {
	        if ($id) {
	            delete_post_meta($id, 'bin_location');
	        }
	    }
	    wp_send_json_success();
	}

	public function blm_transfer_bin() {
	    if (!current_user_can('manage_options')) {
	        wp_send_json_error('Permission denied');
	    }
	    $source      = isset($_POST['source']) ? strtoupper(sanitize_text_field($_POST['source'])) : '';
	    $destination = isset($_POST['destination']) ? strtoupper(sanitize_text_field($_POST['destination'])) : '';
	    if ($source === '' || $destination === '') {
	        wp_send_json_error('Invalid data');
	    }
	    if (!bin_locations_exists($source) || !bin_locations_exists($destination)) {
	        wp_send_json_error('Bin does not exist');
	    }
	    global $wpdb;
	    // Get all product IDs currently assigned to the source bin
	    $product_ids = $wpdb->get_col($wpdb->prepare(
	        "SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'bin_location' AND UPPER(meta_value) = %s",
	        $source
	    ));

	    foreach ($product_ids as $pid) {
	        // Remove any existing bin assignments to avoid lingering stale rows
	        delete_post_meta($pid, 'bin_location');
	        update_post_meta($pid, 'bin_location', $destination);
	    }

	    wp_send_json_success();
	}

	public function blm_swap_bins() {
	    if (!current_user_can('manage_options')) {
	        wp_send_json_error('Permission denied');
	    }
	    $source      = isset($_POST['source']) ? strtoupper(sanitize_text_field($_POST['source'])) : '';
	    $destination = isset($_POST['destination']) ? strtoupper(sanitize_text_field($_POST['destination'])) : '';
	    if ($source === '' || $destination === '') {
	        wp_send_json_error('Invalid data');
	    }
	    if (!bin_locations_exists($source) || !bin_locations_exists($destination)) {
	        wp_send_json_error('Bin does not exist');
	    }
	    global $wpdb;
	    $postmeta = $wpdb->postmeta;
	    $temp     = uniqid('TMP_BIN_');
	    $wpdb->query($wpdb->prepare(
	        "UPDATE {$postmeta} SET meta_value = %s WHERE meta_key = 'bin_location' AND UPPER(meta_value) = %s",
	        $temp,
	        $source
	    ));
	    $wpdb->query($wpdb->prepare(
	        "UPDATE {$postmeta} SET meta_value = %s WHERE meta_key = 'bin_location' AND UPPER(meta_value) = %s",
	        $source,
	        $destination
	    ));
	    $wpdb->query($wpdb->prepare(
	        "UPDATE {$postmeta} SET meta_value = %s WHERE meta_key = 'bin_location' AND meta_value = %s",
	        $destination,
	        $temp
	    ));
	    wp_send_json_success();
	}

	// Products with no Bin
	public function blm_get_products_no_bin() {
	    global $wpdb;
	    if (!$wpdb) {
	        wp_die();
	    }
	    $no_bin_products = $wpdb->get_results("
	        SELECT p.ID, sku.meta_value AS sku, atum.barcode AS barcode, p.post_title, stock.meta_value AS quantity
	        FROM {$wpdb->prefix}posts p
	        LEFT JOIN {$wpdb->prefix}postmeta bin ON bin.post_id = p.ID AND bin.meta_key = 'bin_location'
	        LEFT JOIN {$wpdb->prefix}postmeta sku ON sku.post_id = p.ID AND sku.meta_key = '_sku'
	        LEFT JOIN {$wpdb->prefix}atum_product_data atum ON atum.product_id = p.ID
	        INNER JOIN {$wpdb->prefix}postmeta stock ON stock.post_id = p.ID AND stock.meta_key = '_stock'
	        INNER JOIN {$wpdb->prefix}postmeta manage ON manage.post_id = p.ID AND manage.meta_key = '_manage_stock' AND manage.meta_value = 'yes'
	        WHERE (bin.post_id IS NULL OR bin.meta_value = '') AND stock.meta_value > 0 AND p.post_type IN ('product','product_variation')");
	    $options = bin_locations_get_options();
	    echo '<h3>Products with No Bin</h3>';
	    if (empty($no_bin_products)) {
	        echo '<p><em>All products have bin locations assigned.</em></p>';
	    } else {
	        echo '<datalist id="blm-bin-options">';
			foreach ($options as $opt) {
			    echo '<option value="' . esc_attr($opt) . '"></option>';
			}
			echo '</datalist>';

			echo '<div class="overflow-x-auto shadow-md rounded-lg mt-6">';
			echo '<table class="min-w-full text-sm text-left text-gray-700 border border-gray-200">';
			echo '<thead class="bg-gray-100 text-gray-800 uppercase text-xs tracking-wider">';
			echo '<tr>
			        <th class="p-3 w-10 text-center">
			            <input type="checkbox" class="blm-select-all">
			        </th>
			        <th class="p-3">Bin</th>
			        <th class="p-3">SKU</th>
			        <th class="p-3">Barcode</th>
			        <th class="p-3 w-2/5">Name</th>
			        <th class="p-3">Quantity</th>
			        <th class="p-3 w-32"></th>
			        <th class="p-3 w-10"></th>
			      </tr>';
			echo '</thead><tbody class="divide-y divide-gray-200 bg-white">';

			foreach ($no_bin_products as $row) {
			    echo '<tr class="hover:bg-gray-50 transition" data-product-id="' . esc_attr($row->ID) . '">';
			    echo '<td class="p-3 text-center">
			            <input type="checkbox" class="blm-select-row" data-product-id="' . esc_attr($row->ID) . '">
			          </td>';
			    echo '<td class="p-3 font-medium text-gray-800 blm-bin-editable" data-product-id="' . esc_attr($row->ID) . '" data-current="">
			            No Bin Assigned
			          </td>';
			    echo '<td class="p-3">' . esc_html($row->sku) . '</td>';
			    echo '<td class="p-3">' . esc_html($row->barcode) . '</td>';
			    echo '<td class="p-3 w-2/5 truncate">' . esc_html($row->post_title) . '</td>';
			    echo '<td class="p-3 text-center">' . esc_html($row->quantity) . '</td>';
			    echo '<td class="p-3 w-32"></td>';
			    echo '<td class="p-3 w-10"></td>';
			    echo '</tr>';
			}

			echo '<tr class="hover:bg-gray-50 transition blm-add-row" data-bin="">
			        <td class="p-3 w-10"></td>
			        <td class="p-3 text-gray-400 italic">Add new</td>
			        <td class="p-3"></td>
			        <td class="p-3"></td>
			        <td class="p-3 w-2/5"></td>
			        <td class="p-3"></td>
			        <td class="p-3 w-32">
			            <button type="button"
			                class="blm-add-product bg-blue-600 text-white px-3 py-1 rounded-md hover:bg-blue-700 transition text-xs font-medium"
			                data-bin="">
			                Add Product
			            </button>
			        </td>
			        <td class="p-3 w-10"></td>
			      </tr>';

			echo '</tbody></table>';
			echo '</div>';

	    }
	    wp_die();
	}

	public function blm_scan_lookup() {
	    check_ajax_referer('blm_scanner', 'nonce');
	    if (!current_user_can('manage_options')) {
	        wp_send_json_error('Permission denied');
	    }
	    global $wpdb;
	    if (!$wpdb) {
	        wp_send_json_error('Database error');
	    }
	    $code = isset($_POST['code']) ? strtoupper(sanitize_text_field($_POST['code'])) : '';
	    if ($code === '') {
	        wp_send_json_error('Invalid code');
	    }

	    if (bin_locations_exists($code)) {
	        $products = $wpdb->get_results($wpdb->prepare("
	            SELECT p.ID, p.post_title AS name, sku.meta_value AS sku,
	                   atum.barcode AS barcode, stock.meta_value AS quantity
	            FROM {$wpdb->prefix}postmeta pm
	            JOIN {$wpdb->prefix}posts p ON pm.post_id = p.ID
	            LEFT JOIN {$wpdb->prefix}postmeta sku ON sku.post_id = p.ID AND sku.meta_key = '_sku'
	            LEFT JOIN {$wpdb->prefix}postmeta stock ON stock.post_id = p.ID AND stock.meta_key = '_stock'
	            LEFT JOIN {$wpdb->prefix}postmeta manage ON manage.post_id = p.ID AND manage.meta_key = '_manage_stock'
	            LEFT JOIN {$wpdb->prefix}atum_product_data atum ON atum.product_id = p.ID
	            WHERE pm.meta_key = 'bin_location' AND UPPER(pm.meta_value) = %s
	        ", $code));
	        wp_send_json_success(['type' => 'bin', 'bin' => $code, 'products' => $products]);
	    }

	    $product_id = $wpdb->get_var($wpdb->prepare(
	        "SELECT product_id FROM {$wpdb->prefix}atum_product_data WHERE barcode = %s LIMIT 1",
	        $code
	    ));
	    if (!$product_id) {
	        $product_id = $wpdb->get_var($wpdb->prepare(
	            "SELECT post_id FROM {$wpdb->prefix}postmeta WHERE meta_key = '_sku' AND meta_value = %s LIMIT 1",
	            $code
	        ));
	    }
	    if ($product_id) {
	        // Retrieve the most recent bin assignment in case multiple meta rows exist
	        $bins = get_post_meta($product_id, 'bin_location');
	        $bin  = $bins ? end($bins) : '';
	        $name = get_the_title($product_id);
	        wp_send_json_success([
	            'type'    => 'product',
	            'product' => ['ID' => $product_id, 'bin' => $bin, 'name' => $name],
	        ]);
	    }
	    wp_send_json_error('Not found');
	}

	public function blm_empty_bin() {
		check_ajax_referer('blm_scanner', 'nonce');
	    if (!current_user_can('manage_options')) {
	        wp_send_json_error('Permission denied');
	    }
	    global $wpdb;
	    if (!$wpdb) {
	        wp_send_json_error('Database error');
	    }
	    $bin = isset($_POST['bin']) ? strtoupper(sanitize_text_field($_POST['bin'])) : '';
	    if ($bin === '') {
	        wp_send_json_error('Invalid bin');
	    }
	    $wpdb->query($wpdb->prepare(
	        "DELETE FROM {$wpdb->postmeta} WHERE meta_key = 'bin_location' AND UPPER(meta_value) = %s",
	        $bin
	    ));
	    wp_send_json_success();
	}
}