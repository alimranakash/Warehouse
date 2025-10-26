<?php
if( ! function_exists( 'get_plugin_data' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
}

/**
 * Gets the site's base URL
 * 
 * @uses get_bloginfo()
 * 
 * @return string $url the site URL
 */
if( ! function_exists( 'pc_site_url' ) ) :
function pc_site_url() {
	$url = get_bloginfo( 'url' );

	return $url;
}
endif;

if( ! function_exists( 'bin_locations_get_options' ) ) :
function bin_locations_get_options() {
    global $wpdb;
    if (!$wpdb) {
        return [];
    }
    $table = $wpdb->prefix . 'bin_locations';
    return (array) $wpdb->get_col("SELECT name FROM $table ORDER BY name ASC");
}
endif;

if( ! function_exists( 'bin_locations_exists' ) ) :
function bin_locations_exists($location) {
    global $wpdb;
    if (!$wpdb || $location === '') {
        return false;
    }
    $table = $wpdb->prefix . 'bin_locations';
    return (bool) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE UPPER(name) = %s", strtoupper($location)));
}
endif;