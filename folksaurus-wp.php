<?php
/*
Plugin Name: Folksaurus WP
Plugin URI: https://github.com/zpchavez/FolksaurusWP
Description: Use Folksaurus for tags and categories.  Requires PHP 5.3.0 or higher.
Author: Zachary Chavez
Version: 0.1
Author URI: http://zacharychavez.com
License: BSD
*/

global $wpdb;

require_once 'FolksaurusWP.php';

define('FOLKSAURUS_WP_VERSION', 0.1);
define('FOLKSAURUS_TERM_DATA_TABLE', $wpdb->prefix . 'folksaurus_term_data');
define('FOLKSAURUS_TERM_REL_TABLE', $wpdb->prefix . 'folksaurus_term_relationships');

$folksaurusWP = FolksaurusWP::getInstance();

if ($folksaurusWP->requirementsMet()) {
    require 'PholksaurusLib/init.php';
    require 'DataInterface.php';

    register_activation_hook(__FILE__, array($folksaurusWP, 'setupTables'));

    add_action('init', array($folksaurusWP, 'addStyleSheets'));
    add_action('plugins_loaded', array($folksaurusWP, 'updateDbCheck'));

    add_filter('get_the_terms', array($folksaurusWP, 'getTerms'));
    add_filter('the_tags', array($folksaurusWP, 'addClassesToTermHtml'));
    add_filter('the_category', array($folksaurusWP, 'addClassesToTermHtml'));
} else {
    add_action('admin_notices', array($folksaurusWP, 'printErrors'));
}