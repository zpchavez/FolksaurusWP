<?php

/**
 * Class containing callback methods for hooks.
 */
class FolksaurusWP
{
    const REQUIRED_PHP_VER = '5.3.0';

    static protected $_instance;

    /**
     *
     *
     * @var type
     */
    protected $_errors = array();

    private function __construct()
    {
    }

    /**
     * Get an instance of FolksaurusWP.
     *
     * @return FolksaurusWP
     */
    public static function getInstance()
    {
        if (self::$_instance === null) {
            $instance = new self();
            self::$_instance = $instance;
        }
        return self::$_instance;
    }

    /**
     * Determine if the requirements are met for using the plugin.
     *
     * If not, error messages will be saved which can be printed with
     * the printErrors method.
     *
     * @return boolean
     */
    public function requirementsMet()
    {
        $requirementsMet = true;

        if (version_compare(PHP_VERSION, self::REQUIRED_PHP_VER, '<')) {
            $requirementsMet = false;
            $this->_errors[] = sprintf(
                'FolksaurusWP plugin requires PHP version %s or greater.  You are using %s.',
                self::REQUIRED_PHP_VER,
                PHP_VERSION
            );
        }

        $requiredLib = 'PholksaurusLib/init.php';
        $paths = explode(PATH_SEPARATOR, get_include_path());
        $found = false;
        foreach ($paths as $path) {
            $fullPath = $path . DIRECTORY_SEPARATOR . $requiredLib;
            if (is_file($fullPath)) {
                $found = true;
                break;
            }
        }
        if (!$found) {
            $requirementsMet = false;
            $this->_errors[] = sprintf(
                'PholksaurusLib library not found in include path.'
            );
        }

        return $requirementsMet;
    }

    /**
     * Set up tables needed by Folksaurus WP
     */
    public function setupTables()
    {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

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
    public function updateDbCheck()
    {
        if (get_site_option('folksaurus_wp_version') != FOLKSAURUS_WP_VERSION) {
            folksaurusSetupTables();
        }
    }

    /**
     * Print error messages for any errors that have been encountered.
     */
    public function printErrors()
    {
        include 'errors.phtml';
    }

    /**
    * Configure stylesheets.
    */
    public function addStyleSheets()
    {
        wp_enqueue_style(
            'folksaurus-wp-css',
            plugins_url(NULL, __FILE__) . '/folksaurus-wp.css',
            array(),
            FOLKSAURUS_WP_VERSION
        );
    }


    /**
     * Get term data from Folksaurus.
     *
     * @param array $terms
     */
    public function getTerms($terms)
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

    /**
     * Add a class attribute to the anchor tags of terms if the term is
     * deleted, ambiguous, or nonpreferred, and if the user is logged in
     * as an admin.
     *
     * @param string $html
     * @return string
     */
    public function addClassesToTermHtml($html)
    {
        global $wpdb;

        if (!current_user_can('administrator') && !current_user_can('editor')) {
            return $html;
        }

        if (!preg_match_all('/<a href=.+?rel=".+?">/', $html, $matches)) {
            return $html;
        }

        $openingAnchorTags = $matches[0];
        foreach ($openingAnchorTags as $openingAnchorTag) {
            $termId = $this->_getTermIdFromAnchorTag($openingAnchorTag);
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

    /**
     * Get the term_id from the anchor tag for a category or tag.
     *
     * @param string $html
     * @return int
     */
    protected function _getTermIdFromAnchorTag($html)
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

}
