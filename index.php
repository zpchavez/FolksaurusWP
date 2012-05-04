<?php
/*
Plugin Name: Folksaurus WP
Plugin URI:
Description: Use Folksaurus for tags and categories.
Author: Zachary Chavez
Version: 0.1
Author URI: http://zacharychavez.com
*/

//add_filter('post_updated', 'debug');
//
//function debug() {
//    var_dump(func_get_args());
//}

define('FOLKSAURUS_WP_VERSION', 0.1);

add_action('plugins_loaded', 'folksaurusUpdateDBCheck');
register_activation_hook(__FILE__, 'folksaurusSetupTables');

/**
 * Create tables needed by Folksaurus WP
 */
function folksaurusSetupTables ()
{
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    global $wpdb;

    $tableName = $wpdb->prefix . "folksaurus_term_data";
    $sql = "CREATE TABLE $tableName (
            term_id INT NOT NULL,
            folksaurus_id INT NOT NULL,
            last_retrieved timestamp NOT NULL,
            PRIMARY KEY  term_id (term_id));";
    dbDelta($sql);

    $tableName = $wpdb->prefix . "folksaurus_term_relationships";
    $sql = "CREATE TABLE $tableName (
            term_id INT NOT NULL,
            rel_type enum('USE','UF','BT','NT','RT'),
            related_id INT NOT NULL,
            PRIMARY KEY  (term_id, rel_type, related_id));";
    dbDelta($sql);

    update_option('folksaurus_wp_version', FOLKSAURUS_WP_VERSION);
}

/**
 * Update the database if this is a new version.
 */
function folksaurusUpdateDBCheck()
{
    if (get_site_option('folksaurus_wp_version') != FOLKSAURUS_WP_VERSION) {
        folksaurusSetupTables();
    }
}
