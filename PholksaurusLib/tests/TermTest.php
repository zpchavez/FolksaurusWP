<?php
namespace Folksaurus;

/**
 * Test class for Term.
 */
class TermTest extends \PHPUnit_Framework_TestCase
{
    protected function _getPreferredTermArray()
    {
        return array(
            'id'         => '1',
            'name'       => 'Foo',
            'scope_note' => 'A preferred term.',
            'broader'    => array(array('id' => '2', 'name' => 'Super Foo')),
            'narrower'   => array(array('id' => '3', 'name' => 'Sub Foo')),
            'use'        => array(),
            'used_for'   => array(array('id' => '4', 'name' => 'Faux')),
            'related'    => array(
                                array('id' => '5', 'name' => 'Phoo'),
                                array('id' => '6', 'name' => 'Bar')
                            )
        );
    }

    protected function _getNonPreferredTermArray()
    {
        return array(
            'id'         => '4',
            'name'       => 'Faux',
            'scope_note' => 'A non-preferred term.',
            'broader'    => array(),
            'narrower'   => array(),
            'use'        => array(array('id' => '1', 'name' => 'Foo')),
            'used_for'   => array(),
            'related'    => array()
        );
    }

    protected function _getAmbiguousTermArray()
    {
        return array(
            'id'         => '4',
            'name'       => 'Faux',
            'scope_note' => 'A non-preferred term.',
            'broader'    => array(),
            'narrower'   => array(),
            'use'        => array(
                                array('id' => '1', 'name' => 'Foo'),
                                array('id' => '5', 'name' => 'Phoo')
                         ),
            'used_for'   => array(),
            'related'    => array()
        );
    }

    protected function _getUnsortedTermArray()
    {
        return array(
            'id'         => '7',
            'name'       => 'Unsorted',
            'scope_note' => 'An unsorted term.',
            'broader'    => array(),
            'narrower'   => array(),
            'use'        => array(),
            'used_for'   => array(),
            'related'    => array()
        );
    }

    public function testAccessorMethods()
    {
        $mockTermManager = $this->getMockBuilder('Folksaurus\TermManager')
            ->disableOriginalConstructor()
            ->getMock();

        $termArray = $this->_getPreferredTermArray();
        $term = new Term($termArray, $mockTermManager);

        $this->assertEquals('1', $term->getId());
        $this->assertEquals('Foo', $term->getName());

        $broader = $term->getBroaderTerms();
        $this->assertEquals(1, count($broader));
        $this->assertTrue($broader[0] instanceof TermSummary);
        $this->assertEquals('2', $broader[0]->getId());
        $this->assertEquals('Super Foo', $broader[0]->getName());

        $narrower = $term->getNarrowerTerms();
        $this->assertEquals(1, count($narrower));
        $this->assertTrue($narrower[0] instanceof TermSummary);
        $this->assertEquals('3', $narrower[0]->getId());
        $this->assertEquals('Sub Foo', $narrower[0]->getName());

        $usedFor = $term->getUsedForTerms();
        $this->assertEquals(1, count($usedFor));
        $this->assertTrue($usedFor[0] instanceof TermSummary);
        $this->assertEquals('4', $usedFor[0]->getId());
        $this->assertEquals('Faux', $usedFor[0]->getName());

        $related = $term->getRelatedTerms();
        $this->assertEquals(2, count($related));
        $this->assertTrue($related[0] instanceof TermSummary);
        $this->assertTrue($related[1] instanceof TermSummary);
        $this->assertEquals('5', $related[0]->getId());
        $this->assertEquals('Phoo', $related[0]->getName());
        $this->assertEquals('6', $related[1]->getId());
        $this->assertEquals('Bar', $related[1]->getName());
    }

    public function testGetPreferredOnNonpreferredTermCallsGetTermByFolksaurusIdOnApiObject()
    {
        $mockTermManager = $this->getMockBuilder('Folksaurus\TermManager')
            ->disableOriginalConstructor()
            ->getMock();

        $preferredTerm = new Term($this->_getPreferredTermArray(), $mockTermManager);

        $mockTermManager->expects($this->once())
            ->method('getTermByFolksaurusId')
            ->with($this->equalTo('1'))
            ->will($this->returnValue($preferredTerm));

        $term = new Term($this->_getNonPreferredTermArray(), $mockTermManager);
        $returnValue = $term->getPreferred();
        $this->assertEquals($preferredTerm, $returnValue);
    }

    public function testGetPreferredOnPreferredTermReturnsItself()
    {
        $mockTermManager = $this->getMockBuilder('Folksaurus\TermManager')
            ->disableOriginalConstructor()
            ->getMock();

        $term = new Term($this->_getPreferredTermArray(), $mockTermManager);
        $returnValue = $term->getPreferred();
        $this->assertEquals($term, $returnValue);
    }

    public function testGetPreferredOnUnsortedTermReturnsItself()
    {
        $mockTermManager = $this->getMockBuilder('Folksaurus\TermManager')
            ->disableOriginalConstructor()
            ->getMock();

        $term = new Term($this->_getUnsortedTermArray(), $mockTermManager);
        $returnValue = $term->getPreferred();
        $this->assertEquals($term, $returnValue);
    }

    public function testGetPreferredForAmbiguousTermReturnsArrayOfResultsOfGetByFolkIdCalls()
    {
        // If term is ambiguous, it returns an array of Term objects.
        $mockTermManager = $this->getMockBuilder('Folksaurus\TermManager')
            ->disableOriginalConstructor()
            ->getMock();
        $dummyTermManager = $this->getMockBuilder('Folksaurus\TermManager')
            ->disableOriginalConstructor()
            ->getMock();

        $termPhooArray = array(
            'id'         => '5',
            'name'       => 'Phoo',
            'scope_note' => 'A preferred term.',
            'broader'    => array(),
            'narrower'   => array(),
            'use'        => array(),
            'used_for'   => array(array('id' => '4', 'name' => 'Faux')),
            'related'    => array()
        );
        $termPhoo = new Term($termPhooArray, $dummyTermManager);
        $termFoo  = new Term(
            $this->_getPreferredTermArray(),
            $dummyTermManager
        );

        $mockTermManager->expects($this->at(0))
            ->method('getTermByFolksaurusId')
            ->with($this->equalTo('1'))
            ->will($this->returnValue($termFoo));

        $mockTermManager->expects($this->at(1))
            ->method('getTermByFolksaurusId')
            ->with($this->equalTo('5'))
            ->will($this->returnValue($termPhoo));

        $term = new Term($this->_getAmbiguousTermArray(), $mockTermManager);
        $returnValue = $term->getPreferred();
        $this->assertEquals(2, count($returnValue));
        $this->assertEquals($termFoo, $returnValue[0]);
        $this->assertEquals($termPhoo, $returnValue[1]);

    }

    public function testGetStatusReturnsPreferredIfTermHasRelationshipsOtherThanUse()
    {
        $mockDataInterface = $this->getMock('Folksaurus\DataInterface');
        $termManager = new TermManager($mockDataInterface);

        $preferredTerm = new Term($this->_getPreferredTermArray(), $termManager);

        $this->assertEquals(Term::STATUS_PREFERRED, $preferredTerm->getStatus());
    }

    public function testGetStatusReturnsNonPreferredIfTermHasUseRelationships()
    {
        $mockDataInterface = $this->getMock('Folksaurus\DataInterface');
        $termManager = new TermManager($mockDataInterface);

        $nonPreferredTerm = new Term($this->_getNonPreferredTermArray(), $termManager);

        $this->assertEquals(Term::STATUS_NONPREFERRED, $nonPreferredTerm->getStatus());
    }

    public function testGetStatusReturnsUnsortedIfTermHasNoRelationships()
    {
        $mockDataInterface = $this->getMock('Folksaurus\DataInterface');
        $termManager = new TermManager($mockDataInterface);

        $unsortedTerm = new Term($this->_getUnsortedTermArray(), $termManager);

        $this->assertEquals(Term::STATUS_UNSORTED, $unsortedTerm->getStatus());
    }

    public function testIsAmbiguousReturnsTrueIfTermHasMoreThanOneUseRelationships()
    {
        $mockDataInterface = $this->getMock('Folksaurus\DataInterface');
        $termManager = new TermManager($mockDataInterface);

        $preferredTerm = new Term($this->_getPreferredTermArray(), $termManager);
        $nonPreferredTerm = new Term($this->_getNonPreferredTermArray(), $termManager);
        $unsortedTerm = new Term($this->_getUnsortedTermArray(), $termManager);
        $ambiguousTerm = new Term($this->_getAmbiguousTermArray(), $termManager);

        $this->assertFalse($preferredTerm->isAmbiguous());
        $this->assertFalse($nonPreferredTerm->isAmbiguous());
        $this->assertFalse($unsortedTerm->isAmbiguous());
        $this->assertTrue($ambiguousTerm->isAmbiguous());
    }
}
