<?php
/**
 * All Shortcode related functions
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
 * @subpackage Shortcode
 * @author Codexpert <hi@codexpert.io>
 */
class Shortcode extends Base {

    public $plugin;

    /**
     * Constructor function
     */
    public function __construct( $plugin ) {
        $this->plugin   = $plugin;
        $this->slug     = $this->plugin['TextDomain'];
        $this->name     = $this->plugin['Name'];
        $this->version  = $this->plugin['Version'];
    }

    public function blm_bin_scanner() {
        if (!is_user_logged_in()) {
            return '<p>You must be logged in to use the scanner.</p>';
        }

        wp_enqueue_script( 'tailwindcss', 'https://cdn.tailwindcss.com', [ 'jquery' ], time(), true );
        wp_enqueue_script('html5-qrcode', 'https://unpkg.com/html5-qrcode@2.3.10', [], null, true);
        wp_enqueue_script(
            'blm-scanner',
            plugins_url('assets/js/scanner.js', BLM),
            ['jquery', 'html5-qrcode'],
            null,
            true
        );
        wp_localize_script('blm-scanner', 'blmScanner', [
            'ajax'  => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('blm_scanner'),
        ]);
        ob_start();
        ?>
        <style>
            #blm-actions button {
                width: 100%;
                display: block;
                margin-top: 5px;
                border-radius: 3px;
            }
            .blm-scan-instructions {
                text-align: center;
            }
        </style>
        <div id="blm-scan-wrap">
            <p class="blm-scan-instructions">Scan barcode or bin number</p>
            <div id="blm-scanner" style="width:100%;max-width:400px;margin:0 auto;">
                <input type="text" id="blm-manual" style="width:100%;font-size:2em;" autofocus placeholder="Scan">
            </div>
            <div id="blm-result"></div>
            <div id="blm-actions" style="margin-top:10px;"></div>
        </div>
        <?php
        return ob_get_clean();
    }
}