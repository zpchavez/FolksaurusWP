<?php
namespace Folksaurus;

/**
 * Test class for TermSummary.
 */
class TermSummaryTest extends \PHPUnit_Framework_TestCase
{
    public function testGetCompleteTermReturnsResultsOfGetTermByFolksaurusIdCallOnTermManagerObject()
    {
        $mockTermManager = $this->getMockBuilder('Folksaurus\TermManager')
            ->disableOriginalConstructor()
            ->getMock();

        $termArray = array(
            'id'         => '1',
            'name'       => 'Foo',
            'scope_note' => '',
            'broader'    => array(),
            'narrower'   => array(),
            'used_for'   => array(),
            'use'        => array(),
            'related'    => array()
        );


        $mockTermManager->expects($this->once())
            ->method('getTermByFolksaurusId')
            ->with($this->equalTo('1'))
            ->will($this->returnValue($termArray));

        $termSummary = new TermSummary(array('id' => '1', 'name' => 'Foo'), $mockTermManager);
        $returnValue = $termSummary->getCompleteTerm();
        $this->assertEquals($termArray, $returnValue);
    }
}
