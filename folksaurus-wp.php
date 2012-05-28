<?php
/*
Plugin Name: Folksaurus WP
Plugin URI:
Description: Use Folksaurus for tags and categories.  Requires PHP 5.3.0 or higher.
Author: Zachary Chavez
Version: 0.1
Author URI: http://zacharychavez.com
License:
*/

global $wpdb;

require 'PholksaurusLib/init.php';
require 'DataInterface.php';

define('FOLKSAURUS_WP_VERSION', 0.1);
define('FOLKSAURUS_TERM_DATA_TABLE', $wpdb->prefix . 'folksaurus_term_data');
define('FOLKSAURUS_TERM_REL_TABLE', $wpdb->prefix . 'folksaurus_term_relationships');

add_action('init', 'folksaurusInit');
add_action('plugins_loaded', 'folksaurusUpdateDBCheck');
add_filter('get_the_terms', 'folksaurusGetTerms');
register_activation_hook(__FILE__, 'folksaurusSetupTables');

add_filter('the_tags', 'folksaurusAddClassesToTermHtml');
add_filter('the_category', 'folksaurusAddClassesToTermHtml');

/**
 * Configure stylesheet.
 */
function folksaurusInit()
{
    wp_enqueue_style(
        'folksaurus-wp-css',
        plugins_url(NULL, __FILE__) . '/folksaurus-wp.css',
        array(),
        FOLKSAURUS_WP_VERSION
    );
}

/**
 * Get the term_id from the anchor tag for a category or tag.
 *
 * @param string $html
 * @return int
 */
function folksaurusGetTermIdFromAnchorTag($html)
{
    global $wpdb;

    if (preg_match('/tag=(.+?)"/', $html, $matches)) {
        $slug = $matches[1];
        $termId = $wpdb->get_var(
            $wpdb->prepare(
                'SELECT term_id FROM ' . $wpdb->terms .
                ' WHERE slug = %s',
                $slug
            )
        );
        return $termId;
    }

    if (preg_match('/cat=(.+?)"/', $html, $matches)) {
        $termId = $matches[1];
        return $termId;
    }

    return false;
}

/**
 * Add a class attribute to the anchor tags of terms if the term is
 * deleted, ambiguous, or nonpreferred.
 *
 * @param string $html
 * @return string
 */
function folksaurusAddClassesToTermHtml($html)
{
    global $wpdb;

    if (!preg_match_all('/<a href=.+?rel=".+?">/', $html, $matches)) {
        return $html;
    }

    $openingAnchorTags = $matches[0];
    foreach ($openingAnchorTags as $openingAnchorTag) {
        $termId = folksaurusGetTermIdFromAnchorTag($openingAnchorTag);
        if (!$termId) {
            continue;
        }

        $termData = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT * FROM ' . FOLKSAURUS_TERM_DATA_TABLE .
                ' WHERE term_id = %d',
                $termId
            ),
            ARRAY_A
        );
        if (!$termData) {
            continue;
        }

        if ($termData['deleted'] == 1) {
            $class = 'deleted';
        } else if ($termData['ambiguous'] == 1) {
            $class = 'ambiguous';
        } else if ($termData['preferred'] == 0) {
            $class = 'nonpreferred';
        }

        $filteredAnchorTag = str_replace(
            'href=',
            sprintf('class="%s" href=', $class),
            $openingAnchorTag
        );
        $html = str_replace($openingAnchorTag, $filteredAnchorTag, $html);
    }

    return $html;
}

function folksaurusAddClassesToCategoryHtml($param)
{
    var_dump($param);
}


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
        preferred BOOLEAN DEFAULT 0,
        ambiguous BOOLEAN DEFAULT 0,
        deleted BOOLEAN DEFAULT 0,
        last_retrieved datetime NOT NULL,
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

/**
 * Get term data from Folksaurus.
 *
 * @param array $terms
 */
function folksaurusGetTerms($terms)
{
    if (!$terms) {
        return;
    }
    $dataInterface = new FolksaurusWP\DataInterface();
    try {
        $termManager = new PholksaurusLib\TermManager($dataInterface);
        foreach ($terms as $term) {
            $termManager->getTermByAppId($term->term_id);
        }
    } catch (\PholksaurusLib\Exception $e) {
        // Ignore errors.
    }
    return $terms;
}