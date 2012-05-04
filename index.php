<?php
/*
Plugin Name: Folksaurus WP
Plugin URI:
Description: Use Folksaurus for tags and categories.  Requires PHP 5.3.0 or higher.
Author: Zachary Chavez
Version: 0.1
Author URI: http://zacharychavez.com
*/

define('FOLKSAURUS_WP_VERSION', 0.1);
define('FOLKSAURUS_TERM_DATA_TABLE', $wpdb->prefix . 'folksaurus_term_data');
define('FOLKSAURUS_TERM_REL_TABLE', $wpdb->prefix . 'folksaurus_term_relationships');

add_action('plugins_loaded', 'folksaurusUpdateDBCheck');
register_activation_hook(__FILE__, 'folksaurusSetupTables');

/**
 * Set up tables needed by Folksaurus WP
 */
function folksaurusSetupTables()
{
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    global $wpdb;

    $sql = sprintf(
        "CREATE TABLE %s (
        term_id INT NOT NULL,
        folksaurus_id INT NOT NULL,
        scope_note TEXT NOT NULL,
        deleted BOOLEAN DEFAULT 0,
        last_retrieved timestamp NOT NULL,
        PRIMARY KEY  term_id (term_id));",
        FOLKSAURUS_TERM_DATA_TABLE
    );
    dbDelta($sql);

    $tableName = $wpdb->prefix . "folksaurus_term_relationships";
    $sql = sprintf(
        "CREATE TABLE %s (
        term_id INT NOT NULL,
        rel_type enum('UF','NT','RT'),
        related_id INT NOT NULL,
        PRIMARY KEY  (term_id, rel_type, related_id));",
        FOLKSAURUS_TERM_REL_TABLE
    );
    dbDelta($sql);

    update_option('folksaurus_wp_version', FOLKSAURUS_WP_VERSION);
}

/**
 * Update the database if this is a new version of Folksaurus WP.
 */
function folksaurusUpdateDBCheck()
{
    if (get_site_option('folksaurus_wp_version') != FOLKSAURUS_WP_VERSION) {
        folksaurusSetupTables();
    }
}
