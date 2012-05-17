<?php
require_once '../DataInterface.php';

/**
 * Tests for the plugin's Folksaurus\DataInterface implementation.
 */
class FolksaurusWP_DataInterfaceTest extends WP_UnitTestCase
{
    const FOO_WP_ID = 3;
    const FOO_FOLK_ID = 300;

    public $plugin_slug = 'folksaurus-wp';

    public function setUp()
    {
        parent::setUp();

        global $wpdb;

        // Make sure tables added by folksaurus-wp are empty (wordpress-test does not clear them).
        $wpdb->query(
            sprintf(
                'TRUNCATE %s',
                FOLKSAURUS_TERM_DATA_TABLE
            )
        );
        $wpdb->query(
            sprintf(
                'TRUNCATE %s',
                FOLKSAURUS_TERM_REL_TABLE
            )
        );

        $fooTermArray = $this->_getFolksaurusTermArray();

        // Add existing term "Foo".
        $wpdb->insert(
            FOLKSAURUS_TERM_DATA_TABLE,
            array(
                'term_id'        => self::FOO_WP_ID,
                'folksaurus_id'  => self::FOO_FOLK_ID,
                'scope_note'     => $fooTermArray['scope_note'],
                'last_retrieved' => $fooTermArray['last_retrieved'],
                'deleted'        => 0
            )
        );
        $wpdb->insert(
            $wpdb->terms,
            array(
                'term_id' => self::FOO_WP_ID,
                'name'    => 'Foo',
                'slug'    => 'foo'
            )
        );
    }

    /**
     * Get a term array for the term "Foo".
     *
     * @return array
     */
    protected function _getFolksaurusTermArray()
    {
        $termArray = array(
            'id'             => self::FOO_FOLK_ID,
            'name'           => 'Foo',
            'scope_note'     => 'A term',
            'broader'        => array(),
            'narrower'       => array(),
            'related'        => array(),
            'used_for'       => array(),
            'use'            => array(),
            'app_id'         => self::FOO_WP_ID,
            'last_retrieved' => strtotime('0000-00-00 UTC')
        );
        return $termArray;
    }

    /**
     * Get a Folksaurus\Term object for the term "Foo".
     *
     * @return Folksaurus\Term
     */
    protected function _getFolksaurusTermObject()
    {
        $mockManager = $this->getMockBuilder('Folksaurus\TermManager')
            ->disableOriginalConstructor()
            ->getMock();

        $term = new Folksaurus\Term(
            $this->_getFolksaurusTermArray(),
            $mockManager
        );
    }

    public function testDeleteTermSetsDeletedFlagOfTheTermToTrue()
    {
        global $wpdb;

        $dataInterface = new FolksaurusWP_DataInterface();
        $dataInterface->deleteTerm(self::FOO_WP_ID);

        $deleted = $wpdb->get_var(
            $wpdb->prepare(
                sprintf(
                    'SELECT deleted FROM %s WHERE term_id = %d',
                    FOLKSAURUS_TERM_DATA_TABLE,
                    self::FOO_WP_ID
                )
            )
        );
        $this->assertEquals(1, $deleted);
    }

    public function testGetTermByAppIdReturnsTermArray()
    {
        $dataInterface = new FolksaurusWP_DataInterface();
        $termArray = $dataInterface->getTermByAppId(self::FOO_WP_ID);

        $fooTermArray = $this->_getFolksaurusTermArray();

        $this->assertTrue(is_array($termArray));
        $this->assertEquals($termArray, $fooTermArray);
    }

    public function testGetTermByFolksaurusIdReturnsTermArray()
    {
        $dataInterface = new FolksaurusWP_DataInterface();
        $termArray = $dataInterface->getTermByFolksaurusId(self::FOO_FOLK_ID);

        $fooTermArray = $this->_getFolksaurusTermArray();

        $this->assertTrue(is_array($termArray));
        $this->assertEquals($termArray, $fooTermArray);
    }

    public function testGetTermByNameReturnsTermArray()
    {
        $dataInterface = new FolksaurusWP_DataInterface();
        $termArray = $dataInterface->getTermByName('Foo');

        $fooTermArray = $this->_getFolksaurusTermArray();

        $this->assertTrue(is_array($termArray));
        $this->assertEquals($termArray, $fooTermArray);
    }

    public function testSaveTermAddsNewTermToWpTermTableAndFolksaurusTermTable()
    {
        global $wpdb;

        $mockTermManager = $this->getMockBuilder('Folksaurus\TermManager')
            ->disableOriginalConstructor()
            ->getMock();

        $term = new Folksaurus\Term(
            array(
                'id'             => '400',
                'name'           => 'Bar',
                'scope_note'     => 'Scope note for Bar.',
                'broader'        => array(),
                'narrower'       => array(),
                'related'        => array(),
                'used_for'       => array(),
                'use'            => array(),
                'app_id'         => '',
                'last_retrieved' => 0
            ),
            $mockTermManager
        );

        $dataInterface = new FolksaurusWP_DataInterface();
        $dataInterface->saveTerm($term);

        $wpRow = $wpdb->get_row(
            'SELECT * FROM ' . $wpdb->terms . ' WHERE name = "Bar"',
            ARRAY_A
        );

        $this->assertTrue(is_array($wpRow));
        $this->assertEquals('Bar', $wpRow['name']);
        $this->assertEquals('bar', $wpRow['slug']);

        $folkRow = $wpdb->get_row(
            'SELECT * FROM ' . FOLKSAURUS_TERM_DATA_TABLE .
            ' WHERE term_id = ' . $wpRow['term_id'],
            ARRAY_A
        );

        $this->assertTrue(is_array($folkRow));
        $this->assertEquals('400', $folkRow['folksaurus_id']);
        $this->assertEquals('Scope note for Bar.', $folkRow['scope_note']);
        $this->assertEquals('1', $folkRow['preferred']);
        $this->assertEquals('0', $folkRow['ambiguous']);
        $this->assertEquals('0', $folkRow['deleted']);
        $this->assertEquals(date('Y-m-d H:i:s', 0), $folkRow['last_retrieved']);
    }

    public function testSaveTermAddsNewTermToFolksaurusTermDataTableIfAlreadyExistsInWpTable()
    {
        global $wpdb;

        // Term already exists in WP's term table.
        $wpdb->insert(
            $wpdb->terms,
            array(
                'term_id' => '4',
                'name'    => 'Bar',
                'slug'    => 'slug'
            )
        );

        $mockTermManager = $this->getMockBuilder('Folksaurus\TermManager')
            ->disableOriginalConstructor()
            ->getMock();

        $term = new Folksaurus\Term(
            array(
                'id'             => '400',
                'name'           => 'Bar',
                'scope_note'     => 'Scope note for Bar.',
                'broader'        => array(),
                'narrower'       => array(),
                'related'        => array(),
                'used_for'       => array(),
                'use'            => array(),
                'app_id'         => '4',
                'last_retrieved' => 0
            ),
            $mockTermManager
        );

        $dataInterface = new FolksaurusWP_DataInterface();
        $dataInterface->saveTerm($term);

        $row = $wpdb->get_row(
            'SELECT * FROM ' . FOLKSAURUS_TERM_DATA_TABLE .
            ' WHERE term_id = 4',
            ARRAY_A
        );

        $this->assertTrue(is_array($row));
        $this->assertEquals('400', $row['folksaurus_id']);
        $this->assertEquals('Scope note for Bar.', $row['scope_note']);
        $this->assertEquals('1', $row['preferred']);
        $this->assertEquals('0', $row['ambiguous']);
        $this->assertEquals('0', $row['deleted']);
        $this->assertEquals(date('Y-m-d H:i:s', 0), $row['last_retrieved']);
    }
}