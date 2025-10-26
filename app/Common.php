<?php
/**
 * All common functions to load in both admin and front
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
 * @subpackage Common
 * @author Codexpert <hi@codexpert.io>
 */
class Common extends Base {

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

	public function general_product_data() {
		$options     = bin_locations_get_options();
	    $datalist_id = 'bin_location_list';
	    woocommerce_wp_text_input([
	        'id'                => 'bin_location',
	        'label'             => 'Bin Location',
	        'desc_tip'          => true,
	        'description'       => 'Enter the bin location (must match a valid bin location)',
	        'custom_attributes' => ['list' => $datalist_id],
	    ]);
	    echo '<datalist id="' . esc_attr($datalist_id) . '">';
	    foreach ($options as $opt) {
	        echo '<option value="' . esc_attr($opt) . '"></option>';
	    }
	    echo '</datalist>';
	}

	public function product_meta( $post_id ) {
		if (!isset($_POST['bin_location'])) {
	        return;
	    }
	    $location = strtoupper(sanitize_text_field($_POST['bin_location']));
	    if ($location === '') {
	        delete_post_meta($post_id, 'bin_location');
	        return;
	    }
	    if (bin_locations_exists($location)) {
	        update_post_meta($post_id, 'bin_location', $location);
	    } else {
	        if (class_exists('WC_Admin_Meta_Boxes')) {
	            WC_Admin_Meta_Boxes::add_error('Invalid bin location');
	        }
	    }
	}

	public function variation_fields( $loop, $variation_data, $variation ) {
		$value       = get_post_meta($variation->ID, 'bin_location', true);
	    $options     = bin_locations_get_options();
	    $datalist_id = 'bin_location_list_' . $loop;
	    ?>
	    <div class="form-row form-row-full">
	        <label for="bin_location_<?php echo $loop; ?>">Bin Location</label>
	        <input type="text" class="short" style="width: 100%;" name="bin_location[<?php echo $loop; ?>]" id="bin_location_<?php echo $loop; ?>" value="<?php echo esc_attr($value); ?>" list="<?php echo esc_attr($datalist_id); ?>" />
	        <datalist id="<?php echo esc_attr($datalist_id); ?>">
	            <?php foreach ($options as $opt): ?>
	                <option value="<?php echo esc_attr($opt); ?>"></option>
	            <?php endforeach; ?>
	        </datalist>
	    </div>
	    <?php
	}

	public function save_product_variation( $variation_id, $i ) {
		if (!isset($_POST['bin_location'][$i])) {
	        return;
	    }
	    $location = strtoupper(sanitize_text_field($_POST['bin_location'][$i]));
	    if ($location === '') {
	        delete_post_meta($variation_id, 'bin_location');
	        return;
	    }
	    if (bin_locations_exists($location)) {
	        update_post_meta($variation_id, 'bin_location', $location);
	    } else {
	        if (class_exists('WC_Admin_Meta_Boxes')) {
	            WC_Admin_Meta_Boxes::add_error('Invalid bin location');
	        }
	    }
	}
}