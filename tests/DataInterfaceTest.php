<?php
namespace FolksaurusWP;

require_once '../DataInterface.php';

/**
 * Tests for the plugin's Folksaurus\DataInterface implementation.
 */
class DataInterfaceTest extends \WP_UnitTestCase
{
    const FOO_FOLK_ID = 300;

    public $plugin_slug = 'folksaurus-wp';

    /**
     * The term_id for the term "Foo", which is set up in the setUp method.
     *
     * @var int
     */
    protected $_fooTermId;

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
        $fooTermIds = wp_insert_term('Foo', 'category');
        $this->_fooTermId = $fooTermIds['term_id'];
        $wpdb->insert(
            FOLKSAURUS_TERM_DATA_TABLE,
            array(
                'term_id'        => $this->_fooTermId,
                'folksaurus_id'  => self::FOO_FOLK_ID,
                'scope_note'     => $fooTermArray['scope_note'],
                'last_retrieved' => $fooTermArray['last_retrieved'],
                'preferred'      => 1,
                'deleted'        => 0
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
            'app_id'         => $this->_fooTermId,
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
        $mockManager = $this->getMockBuilder('\Folksaurus\TermManager')
            ->disableOriginalConstructor()
            ->getMock();

        $term = new \Folksaurus\Term(
            $this->_getFolksaurusTermArray(),
            $mockManager
        );
    }

    public function testDeleteTermSetsDeletedFlagOfTheTermToTrue()
    {
        global $wpdb;

        $dataInterface = new DataInterface();
        $dataInterface->deleteTerm($this->_fooTermId);

        $deleted = $wpdb->get_var(
            $wpdb->prepare(
                sprintf(
                    'SELECT deleted FROM %s WHERE term_id = %d',
                    FOLKSAURUS_TERM_DATA_TABLE,
                    $this->_fooTermId
                )
            )
        );
        $this->assertEquals(1, $deleted);
    }

    public function testGetTermByAppIdReturnsTermArray()
    {
        $dataInterface = new DataInterface();
        $termArray = $dataInterface->getTermByAppId($this->_fooTermId);

        $fooTermArray = $this->_getFolksaurusTermArray();

        $this->assertTrue(is_array($termArray));
        $this->assertEquals($termArray, $fooTermArray);
    }

    public function testGetTermByFolksaurusIdReturnsTermArray()
    {
        $dataInterface = new DataInterface();
        $termArray = $dataInterface->getTermByFolksaurusId(self::FOO_FOLK_ID);

        $fooTermArray = $this->_getFolksaurusTermArray();

        $this->assertTrue(is_array($termArray));
        $this->assertEquals($termArray, $fooTermArray);
    }

    public function testGetTermByNameReturnsTermArray()
    {
        $dataInterface = new DataInterface();
        $termArray = $dataInterface->getTermByName('Foo');

        $fooTermArray = $this->_getFolksaurusTermArray();

        $this->assertTrue(is_array($termArray));
        $this->assertEquals($termArray, $fooTermArray);
    }

    public function testTermNotSavedIfItDoesNotExistInTheTermTable()
    {
        global $wpdb;

        $mockTermManager = $this->getMockBuilder('\Folksaurus\TermManager')
            ->disableOriginalConstructor()
            ->getMock();

        $term = new \Folksaurus\Term(
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

        $dataInterface = new DataInterface();
        $dataInterface->saveTerm($term);

        $wpRow = $wpdb->get_row(
            'SELECT * FROM ' . $wpdb->terms . ' WHERE name = "Bar"',
            ARRAY_A
        );

        $this->assertNull($wpRow);

        $folkRow = $wpdb->get_row(
            'SELECT * FROM ' . FOLKSAURUS_TERM_DATA_TABLE .
            ' WHERE term_id = ' . $wpRow['term_id'],
            ARRAY_A
        );

        $this->assertNull($folkRow);
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
                'slug'    => 'bar'
            )
        );

        $mockTermManager = $this->getMockBuilder('\Folksaurus\TermManager')
            ->disableOriginalConstructor()
            ->getMock();

        $term = new \Folksaurus\Term(
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

        $dataInterface = new DataInterface();
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

    public function testExistingTermNameAndScopeNoteUpdatedWhenSaved()
    {
        global $wpdb;

        // Term already exists in WP's term table
        $wpdb->insert(
            $wpdb->terms,
            array(
                'term_id' => '4',
                'name'    => 'Bar',
                'slug'    => 'bar'
            )
        );
        // and Folksaurus term table.
        $wpdb->insert(
            FOLKSAURUS_TERM_DATA_TABLE,
            array(
                'term_id'       => '4',
                'folksaurus_id' => '400',
                'scope_note'    => 'Scope note for Baz.',
                'preferred'     => '1',
                'ambiguous'     => '0',
            )
        );

        $mockTermManager = $this->getMockBuilder('\Folksaurus\TermManager')
            ->disableOriginalConstructor()
            ->getMock();

        $term = new \Folksaurus\Term(
            array(
                'id'             => '400',
                'name'           => 'Baz',
                'scope_note'     => 'Scope note for Baz.',
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

        $dataInterface = new DataInterface();
        $dataInterface->saveTerm($term);

        $wpRow = $wpdb->get_row(
            'SELECT * FROM ' . $wpdb->terms . ' WHERE term_id = 4',
            ARRAY_A
        );

        $this->assertTrue(is_array($wpRow));
        $this->assertEquals('Baz', $wpRow['name']);
        $this->assertEquals('baz', $wpRow['slug']);

        $folkRow = $wpdb->get_row(
            'SELECT * FROM ' . FOLKSAURUS_TERM_DATA_TABLE .
            ' WHERE term_id = 4',
            ARRAY_A
        );

        $this->assertTrue(is_array($folkRow));
        $this->assertEquals('400', $folkRow['folksaurus_id']);
        $this->assertEquals('Scope note for Baz.', $folkRow['scope_note']);
        $this->assertEquals('1', $folkRow['preferred']);
        $this->assertEquals('0', $folkRow['ambiguous']);
        $this->assertEquals('0', $folkRow['deleted']);
        $this->assertEquals(date('Y-m-d H:i:s', 0), $folkRow['last_retrieved']);
    }

    public function testRelationshipsAddedWhenSaved()
    {
        global $wpdb;

        $barTermIds = wp_insert_term('Bar', 'category');
        $barTermId = $barTermIds['term_id'];
        wp_insert_term('SuperBar', 'category');
        wp_insert_term('SubBar', 'category');
        wp_insert_term('RelBar', 'category');
        wp_insert_term('UsedForBar', 'category');

        $mockTermManager = $this->getMockBuilder('\Folksaurus\TermManager')
            ->disableOriginalConstructor()
            ->getMock();

        $term = new \Folksaurus\Term(
            array(
                'id'             => '400',
                'name'           => 'Bar',
                'scope_note'     => 'Scope note for Bar.',
                'broader'        => array(
                    array(
                        'id'   => '500',
                        'name' => 'SuperBar'
                    )
                ),
                'narrower'       => array(
                    array(
                        'id'   => '600',
                        'name' => 'SubBar'
                    )
                ),
                'related'        => array(
                    array(
                        'id'   => '700',
                        'name' => 'RelBar'
                    )
                ),
                'used_for'       => array(
                    array(
                        'id'   => '800',
                        'name' => 'UsedForBar'
                    )
                ),
                'use'            => array(),
                'app_id'         => $barTermId,
                'last_retrieved' => 0
            ),
            $mockTermManager
        );

        $dataInterface = new DataInterface();
        $dataInterface->saveTerm($term);

        $superBarTermId = $wpdb->get_var(
            'SELECT term_id FROM ' . $wpdb->terms . ' WHERE name = "SuperBar"'
        );

        $subBarTermId = $wpdb->get_var(
            'SELECT term_id FROM ' . $wpdb->terms . ' WHERE name = "SubBar"'
        );

        $relBarTermId = $wpdb->get_var(
            'SELECT term_id FROM ' . $wpdb->terms . ' WHERE name = "RelBar"'
        );

        $usedForBarTermId = $wpdb->get_var(
            'SELECT term_id FROM ' . $wpdb->terms . ' WHERE name = "UsedForBar"'
        );

        $results = $wpdb->get_results('SELECT * FROM ' . FOLKSAURUS_TERM_REL_TABLE, ARRAY_A);

        $this->assertContains(
            array(
                'term_id'    => $superBarTermId,
                'rel_type'   => 'NT',
                'related_id' => $barTermId
            ),
            $results
        );

        $this->assertContains(
            array(
                'term_id'    => $barTermId,
                'rel_type'   => 'NT',
                'related_id' => $subBarTermId
            ),
            $results
        );

        $this->assertContains(
            array(
                'term_id'    => $barTermId,
                'rel_type'   => 'RT',
                'related_id' => $relBarTermId
            ),
            $results
        );

        $this->assertContains(
            array(
                'term_id'    => $barTermId,
                'rel_type'   => 'UF',
                'related_id' => $usedForBarTermId
            ),
            $results
        );
    }

    public function testRelationshipsRemovedWhenSaved()
    {
        global $wpdb;

        // Set up two existing relationships.  One will be removed.
        $phooTermIds = wp_insert_term('Phoo', 'category');
        $barTermIds  = wp_insert_term('Bar', 'category');
        $wpdb->insert(
            FOLKSAURUS_TERM_DATA_TABLE,
            array(
                'term_id'       => $phooTermIds['term_id'],
                'folksaurus_id' => '400',
                'preferred'     => '1'
            )
        );
        $wpdb->insert(
            FOLKSAURUS_TERM_DATA_TABLE,
            array(
                'term_id'       => $barTermIds['term_id'],
                'folksaurus_id' => '500',
                'preferred'     => '1'
            )
        );

        // Both are related terms.
        $wpdb->insert(
            FOLKSAURUS_TERM_REL_TABLE,
            array(
                'term_id'    => $this->_fooTermId,
                'rel_type'   => 'RT',
                'related_id' => $phooTermIds['term_id']
            )
        );
        $wpdb->insert(
            FOLKSAURUS_TERM_REL_TABLE,
            array(
                'term_id'    => $this->_fooTermId,
                'rel_type'   => 'RT',
                'related_id' => $barTermIds['term_id']
            )
        );

        $mockTermManager = $this->getMockBuilder('\Folksaurus\TermManager')
            ->disableOriginalConstructor()
            ->getMock();

        $term = new \Folksaurus\Term(
            array(
                'id'             => self::FOO_FOLK_ID,
                'name'           => 'Foo',
                'scope_note'     => '',
                'broader'        => array(),
                'narrower'       => array(),
                'related'        => array(
                    array(
                        'id'   => '400',
                        'name' => 'Phoo'
                    )
                ),
                'used_for'       => array(),
                'use'            => array(),
                'app_id'         => $this->_fooTermId,
                'last_retrieved' => 0
            ),
            $mockTermManager
        );

        $dataInterface = new DataInterface();
        $dataInterface->saveTerm($term);

        $results = $wpdb->get_results('SELECT * FROM ' . FOLKSAURUS_TERM_REL_TABLE, ARRAY_A);

        // Still related to Phoo but not Bar.
        $this->assertEquals(
            array(
                array(
                    'term_id'    => $this->_fooTermId,
                    'rel_type'   => 'RT',
                    'related_id' => $phooTermIds['term_id']
                )
            ),
            $results
        );
    }

    public function testAmbiguousFlagSetToTrueIfTermBecomesAmbiguous()
    {
        global $wpdb;

        $mockTermManager = $this->getMockBuilder('\Folksaurus\TermManager')
            ->disableOriginalConstructor()
            ->getMock();

        $term = new \Folksaurus\Term(
            array(
                'id'             => self::FOO_FOLK_ID,
                'name'           => 'Foo',
                'scope_note'     => '',
                'broader'        => array(),
                'narrower'       => array(),
                'related'        => array(),
                'used_for'       => array(),
                'use'            => array(
                    array(
                        'id'   => '400',
                        'name' => 'RealFoo'
                    ),
                    array(
                        'id'   => '500',
                        'name' => 'RealFooToo'
                    )
                ),
                'app_id'         => $this->_fooTermId,
                'last_retrieved' => 0
            ),
            $mockTermManager
        );

        // Assert begins non-ambiguous.
        $ambiguous = $wpdb->get_var(
            'SELECT ambiguous FROM ' . FOLKSAURUS_TERM_DATA_TABLE
            . ' WHERE term_id = ' . $this->_fooTermId
        );
        $this->assertEquals(0, $ambiguous);

        $dataInterface = new DataInterface();
        $dataInterface->saveTerm($term);

        $ambiguous = $wpdb->get_var(
            'SELECT ambiguous FROM ' . FOLKSAURUS_TERM_DATA_TABLE
            . ' WHERE term_id = ' . $this->_fooTermId
        );
        $this->assertEquals(1, $ambiguous);
    }

    public function testAmbiguousFlagSetToFalseIfTermIsNotAmbiguous()
    {
        global $wpdb;

        $wpdb->update(
            FOLKSAURUS_TERM_DATA_TABLE,
            array(
                'ambiguous' => '1'
            ),
            array(
                'term_id' => $this->_fooTermId
            )
        );

        $mockTermManager = $this->getMockBuilder('\Folksaurus\TermManager')
            ->disableOriginalConstructor()
            ->getMock();

        $term = new \Folksaurus\Term(
            array(
                'id'             => self::FOO_FOLK_ID,
                'name'           => 'Foo',
                'scope_note'     => '',
                'broader'        => array(),
                'narrower'       => array(),
                'related'        => array(),
                'used_for'       => array(),
                'use'            => array(),
                'app_id'         => $this->_fooTermId,
                'last_retrieved' => 0
            ),
            $mockTermManager
        );

        // Assert begins ambiguous.
        $ambiguous = $wpdb->get_var(
            'SELECT ambiguous FROM ' . FOLKSAURUS_TERM_DATA_TABLE
            . ' WHERE term_id = ' . $this->_fooTermId
        );
        $this->assertEquals(1, $ambiguous);

        $dataInterface = new DataInterface();
        $dataInterface->saveTerm($term);

        $ambiguous = $wpdb->get_var(
            'SELECT ambiguous FROM ' . FOLKSAURUS_TERM_DATA_TABLE
            . ' WHERE term_id = ' . $this->_fooTermId
        );
        $this->assertEquals(0, $ambiguous);
    }

    public function testPreferredFlagSetToFalseIfTermNoLongerPreferred()
    {
        global $wpdb;

        $mockTermManager = $this->getMockBuilder('\Folksaurus\TermManager')
            ->disableOriginalConstructor()
            ->getMock();

        $term = new \Folksaurus\Term(
            array(
                'id'             => self::FOO_FOLK_ID,
                'name'           => 'Foo',
                'scope_note'     => '',
                'broader'        => array(),
                'narrower'       => array(),
                'related'        => array(),
                'used_for'       => array(),
                'use'            => array(
                    array(
                        'id'   => '400',
                        'name' => 'RealFoo'
                    )
                ),
                'app_id'         => $this->_fooTermId,
                'last_retrieved' => 0
            ),
            $mockTermManager
        );

        // Assert begins as preferred.
        $preferred = $wpdb->get_var(
            'SELECT preferred FROM ' . FOLKSAURUS_TERM_DATA_TABLE
            . ' WHERE term_id = ' . $this->_fooTermId
        );
        $this->assertEquals(1, $preferred);

        $dataInterface = new DataInterface();
        $dataInterface->saveTerm($term);

        $preferred = $wpdb->get_var(
            'SELECT preferred FROM ' . FOLKSAURUS_TERM_DATA_TABLE
            . ' WHERE term_id = ' . $this->_fooTermId
        );
        $this->assertEquals(0, $preferred);
    }

    public function testPreferredFlagSetToTrueIfTermBecomesPreferred()
    {
        global $wpdb;

        $wpdb->update(
            FOLKSAURUS_TERM_DATA_TABLE,
            array(
                'preferred' => '0'
            ),
            array(
                'term_id' => $this->_fooTermId
            )
        );

        $mockTermManager = $this->getMockBuilder('\Folksaurus\TermManager')
            ->disableOriginalConstructor()
            ->getMock();

        $term = new \Folksaurus\Term(
            array(
                'id'             => self::FOO_FOLK_ID,
                'name'           => 'Foo',
                'scope_note'     => '',
                'broader'        => array(),
                'narrower'       => array(),
                'related'        => array(),
                'used_for'       => array(
                    array(
                        'id'   => '400',
                        'name' => 'Faux'
                    )
                ),
                'use'            => array(),
                'app_id'         => $this->_fooTermId,
                'last_retrieved' => 0
            ),
            $mockTermManager
        );

        // Assert begins as non-preferred.
        $preferred = $wpdb->get_var(
            'SELECT preferred FROM ' . FOLKSAURUS_TERM_DATA_TABLE
            . ' WHERE term_id = ' . $this->_fooTermId
        );
        $this->assertEquals(0, $preferred);

        $dataInterface = new DataInterface();
        $dataInterface->saveTerm($term);

        $preferred = $wpdb->get_var(
            'SELECT preferred FROM ' . FOLKSAURUS_TERM_DATA_TABLE
            . ' WHERE term_id = ' . $this->_fooTermId
        );
        $this->assertEquals(1, $preferred);
    }

    public function testWpObjectRelationshipsUpdatedWhenPreferredTermChanges()
    {
        global $wpdb;

        // Initial data has category "Uncategorized" related to object_id 1.
        // Add folksaurus_term_data row for "Uncategorized".
        $wpdb->insert(
            FOLKSAURUS_TERM_DATA_TABLE,
            array(
                'term_id'       => '1',
                'folksaurus_id' => '100',
                'preferred'     => '1',
                'ambiguous'     => '0',
                'deleted'       => '0'
            )
        );
        // Add "Not Categorized" term, which will be the new preferred term.
        $termIds = wp_insert_term('Not Categorized', 'category');
        $notCategorizedTermId = $termIds['term_id'];
        $notCategorizedTaxonomyId = $termIds['term_taxonomy_id'];
        $wpdb->insert(
            FOLKSAURUS_TERM_DATA_TABLE,
            array(
                'term_id'       => $notCategorizedTermId,
                'folksaurus_id' => '400',
                'preferred'     => '1',
                'ambiguous'     => '0',
                'deleted'       => '0'
            )
        );

        // Save new version of term which is now non-preferred.

        $mockTermManager = $this->getMockBuilder('\Folksaurus\TermManager')
            ->disableOriginalConstructor()
            ->getMock();

        $mockTermManager2 = clone $mockTermManager;

        $notCategorizedTerm = new \Folksaurus\Term(
            array(
                'id'             => '400',
                'name'           => 'Not Categorized',
                'scope_note'     => '',
                'broader'        => array(),
                'narrower'       => array(),
                'related'        => array(),
                'used_for'       => array(
                    array(
                        'id'   => '100',
                        'name' => 'Uncategorized'
                    )
                ),
                'use'            => array(),
                'app_id'         => $notCategorizedTermId,
                'last_retrieved' => 0
            ),
            $mockTermManager2
        );

        $mockTermManager->expects($this->once())
            ->method('getTermByFolksaurusId')
            ->with($this->equalTo('400'))
            ->will($this->returnValue($notCategorizedTerm));

        $term = new \Folksaurus\Term(
            array(
                'id'             => '100',
                'name'           => 'Uncategorized',
                'scope_note'     => '',
                'broader'        => array(),
                'narrower'       => array(),
                'related'        => array(),
                'used_for'       => array(),
                'use'            => array(
                    array(
                        'id'   => '400',
                        'name' => 'Not Categorized'
                    )
                ),
                'app_id'         => '1',
                'last_retrieved' => 0
            ),
            $mockTermManager
        );

        $dataInterface = new DataInterface();
        $dataInterface->saveTerm($term);

        $results = $wpdb->get_results(
            'SELECT * FROM ' . $wpdb->term_relationships .
            ' WHERE object_id = 1 AND term_taxonomy_id = ' . $notCategorizedTaxonomyId,
            ARRAY_A
        );
        $this->assertEquals(
            array(
                array(
                    'object_id'        => '1',
                    'term_taxonomy_id' => $notCategorizedTaxonomyId,
                    'term_order'       => '0'
                ),
            ),
            $results
        );
    }

    public function testParentIdValuesSetInWpTaxonomyTableForNarrowerAndBroaderTerms()
    {
        global $wpdb;

        $barIds = wp_insert_term('Bar', 'post_tag');
        $subBarIds = wp_insert_term('SubBar', 'post_tag');

        $barTermId = $barIds['term_id'];
        $subBarTermId = $subBarIds['term_id'];

        $wpdb->insert(
            FOLKSAURUS_TERM_DATA_TABLE,
            array(
                'term_id'        => $barTermId,
                'folksaurus_id'  => '400',
                'scope_note'     => '',
                'last_retrieved' => 0,
                'preferred'      => 1,
                'deleted'        => 0
            )
        );
        $wpdb->insert(
            FOLKSAURUS_TERM_DATA_TABLE,
            array(
                'term_id'        => $subBarTermId,
                'folksaurus_id'  => '500',
                'scope_note'     => '',
                'last_retrieved' => 0,
                'preferred'      => 1,
                'deleted'        => 0
            )
        );

        $mockTermManager = $this->getMockBuilder('\Folksaurus\TermManager')
            ->disableOriginalConstructor()
            ->getMock();

        $term = new \Folksaurus\Term(
            array(
                'id'             => '400',
                'name'           => 'Bar',
                'scope_note'     => '',
                'broader'        => array(),
                'narrower'       => array(
                    array(
                        'id'   => '500',
                        'name' => 'SubBar'
                    )
                ),
                'related'        => array(),
                'used_for'       => array(),
                'use'            => array(),
                'app_id'         => $barTermId,
                'last_retrieved' => 0
            ),
            $mockTermManager
        );

        // Assert parent ID not set before saving.

        $subBarParentId = $wpdb->get_var(
            'SELECT parent FROM ' . $wpdb->term_taxonomy .
            ' WHERE term_id = ' . $subBarTermId
        );
        $this->assertEquals(0, $subBarParentId);

        $dataInterface = new DataInterface();
        $dataInterface->saveTerm($term);

        $terms = $wpdb->get_results('SELECT * FROM ' . $wpdb->terms);

        $subBarParentId = $wpdb->get_var(
            'SELECT parent FROM ' . $wpdb->term_taxonomy .
            ' WHERE term_id = ' . $subBarTermId
        );
        $this->assertEquals($barTermId, $subBarParentId);
    }

}