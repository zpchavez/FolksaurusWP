<?php
namespace Folksaurus;

/**
 * Test class for TermManager.
 */
class TermManagerTest extends \PHPUnit_Framework_TestCase
{
    const FOO_FOLKSAURUS_ID = 1;
    const FOO_APP_ID        = 100;

    /**
     * Get a simple term array for an unsorted term called Foo.
     *
     * @return array
     */
    protected function _getFooTermArray()
    {
        return array(
            'id'             => self::FOO_FOLKSAURUS_ID,
            'app_id'         => self::FOO_APP_ID,
            'name'           => 'Foo',
            'scope_note'     => 'A term.',
            'broader'        => array(),
            'narrower'       => array(),
            'related'        => array(),
            'used_for'       => array(),
            'use'            => array(),
            'last_retrieved' => 0
        );
    }

    public function testGetTermByAppIdForTermNotFoundInDbMakesNoRequestAndReturnsFalse()
    {
        $mockDataInterface  = $this->getMock('Folksaurus\DataInterface');
        $mockRex = $this->getMockBuilder('Folksaurus\RequestExecutor')
            ->disableOriginalConstructor()
            ->getMock();

        $mockDataInterface->expects($this->once())
            ->method('getTermByAppId')
            ->with($this->equalTo(StatusCodes::NOT_FOUND))
            ->will($this->returnValue(false));

        // Won't send a request to Folksaurus, because the ID is unknown.
        $mockRex->expects($this->never())
            ->method('getTermById');
        $mockRex->expects($this->never())
            ->method('getTermByIdIfModifiedSince');

        $termManager = new TermManager($mockDataInterface, CONFIG_PATH, $mockRex);
        $term = $termManager->getTermByAppId(StatusCodes::NOT_FOUND);

        $this->assertFalse($term);
    }

    public function testGetTermByAppIdForNonExpiredTermDoesNotMakeARequestOrUpdateTerm()
    {
        $mockDataInterface  = $this->getMock('Folksaurus\DataInterface');
        $mockRex = $this->getMockBuilder('Folksaurus\RequestExecutor')
            ->disableOriginalConstructor()
            ->getMock();

        $justNow = time();
        $fooTermArray = $this->_getFooTermArray();
        $fooTermArray['last_retrieved'] = $justNow;

        $mockDataInterface->expects($this->once())
            ->method('getTermByAppId')
            ->with($this->equalTo(self::FOO_APP_ID))
            ->will($this->returnValue($fooTermArray));

        // Won't send a request to Folksaurus, because term was retrieved recently.
        $mockRex->expects($this->never())
            ->method('getTermById');
        $mockRex->expects($this->never())
            ->method('getTermByIdIfModifiedSince');

        // Won't save, because no changes were retrieved.
        $mockDataInterface->expects($this->never())
            ->method('saveTerm');

        $termManager = new TermManager($mockDataInterface, CONFIG_PATH, $mockRex);
        $term = $termManager->getTermByAppId(self::FOO_APP_ID);

        $this->assertTrue($term instanceof Term);
        $this->assertEquals('Foo', $term->getName());
        $this->assertEquals(self::FOO_FOLKSAURUS_ID, $term->getId());
        $this->assertEquals(self::FOO_APP_ID, $term->getAppId());
    }

    public function testGetTermByAppIdForModifiedExpiredTermMakesRequestAndUpdatesTerm()
    {
        $mockDataInterface  = $this->getMock('Folksaurus\DataInterface');
        $mockRex = $this->getMockBuilder('Folksaurus\RequestExecutor')
            ->disableOriginalConstructor()
            ->getMock();

        $yesterday = time() - (60 * 60 * 24);
        $fooTermArray = $this->_getFooTermArray();
        $fooTermArray['last_retrieved'] = $yesterday;

        $mockDataInterface->expects($this->once())
            ->method('getTermByAppId')
            ->with($this->equalTo(self::FOO_APP_ID))
            ->will($this->returnValue($fooTermArray));

        $updatedFooTermArray = $fooTermArray;
        $updatedFooTermArray['scope_note'] = 'Updated scope note';

        // Term is expired, so send request for latest info.
        $mockRex->expects($this->once())
            ->method('getTermByIdIfModifiedSince')
            ->with(
                $this->equalTo(self::FOO_FOLKSAURUS_ID),
                $this->equalTo($yesterday)
            )->will($this->returnValue($updatedFooTermArray));

        $mockRex->expects($this->once())
            ->method('getLatestResponseCode')
            ->will($this->returnValue(StatusCodes::OK));

        // Save latest term info.
        $mockDataInterface->expects($this->once())
            ->method('saveTerm')
            ->with($this->isInstanceOf('Folksaurus\Term'));

        $termManager = new TermManager($mockDataInterface, CONFIG_PATH, $mockRex);

        $term = $termManager->getTermByAppId(self::FOO_APP_ID);

        $this->assertTrue($term instanceof Term);
        $this->assertEquals('Foo', $term->getName());
        $this->assertEquals(self::FOO_FOLKSAURUS_ID, $term->getId());
        $this->assertEquals(self::FOO_APP_ID, $term->getAppId());
        $this->assertEquals('Updated scope note', $term->getScopeNote());
        $this->assertTrue($term->getLastRetrievedTime() > $yesterday);
    }

    public function testGettingTermThatWasDeletedRemotelyCallsDeleteTermAndReturnsFalse()
    {
        $mockDataInterface  = $this->getMock('Folksaurus\DataInterface');
        $mockRex = $this->getMockBuilder('Folksaurus\RequestExecutor')
            ->disableOriginalConstructor()
            ->getMock();

        $yesterday = time() - (60 * 60 * 24);
        $fooTermArray = $this->_getFooTermArray();
        $fooTermArray['last_retrieved'] = $yesterday;

        $mockDataInterface->expects($this->once())
            ->method('getTermByAppId')
            ->with($this->equalTo(self::FOO_APP_ID))
            ->will($this->returnValue($fooTermArray));

        $updatedFooTermArray = $fooTermArray;
        $updatedFooTermArray['scope_note'] = 'Updated scope note';

        // Term is expired, so send request for latest info.
        $mockRex->expects($this->once())
            ->method('getTermByIdIfModifiedSince')
            ->with(
                $this->equalTo(self::FOO_FOLKSAURUS_ID),
                $this->equalTo($yesterday)
            )->will($this->returnValue(false));

        $mockRex->expects($this->once())
            ->method('getLatestResponseCode')
            ->will($this->returnValue(StatusCodes::GONE));

        $mockDataInterface->expects($this->once())
            ->method('deleteTerm')
            ->with($this->equalTo($fooTermArray['app_id']));

        $mockDataInterface->expects($this->never())
            ->method('saveTerm');

        $termManager = new TermManager($mockDataInterface, CONFIG_PATH, $mockRex);

        $term = $termManager->getTermByAppId(self::FOO_APP_ID);

        $this->assertFalse($term);
    }

    public function testGetTermByAppIdForUnmodifiedExpiredTermMakesRequestAndUpdatesModTime()
    {
        $mockDataInterface  = $this->getMock('Folksaurus\DataInterface');
        $mockRex = $this->getMockBuilder('Folksaurus\RequestExecutor')
            ->disableOriginalConstructor()
            ->getMock();

        $yesterday = time() - (60 * 60 * 24);
        $fooTermArray = $this->_getFooTermArray();
        $fooTermArray['last_retrieved'] = $yesterday;

        $mockDataInterface->expects($this->once())
            ->method('getTermByAppId')
            ->with($this->equalTo(self::FOO_APP_ID))
            ->will($this->returnValue($fooTermArray));

        // Term is expired, so send request for latest info.
        // False returned since there was no content in the body of the response.
        $mockRex->expects($this->once())
            ->method('getTermByIdIfModifiedSince')
            ->with(
                $this->equalTo(self::FOO_FOLKSAURUS_ID),
                $this->equalTo($yesterday)
            )->will($this->returnValue(false));

        // Response code is 304.
        $mockRex->expects($this->once())
            ->method('getLatestResponseCode')
            ->will($this->returnValue(StatusCodes::NOT_MODIFIED));

        // Term saved in order to update the last modified time.
        $mockDataInterface->expects($this->once())
            ->method('saveTerm');

        $termManager = new TermManager($mockDataInterface, CONFIG_PATH, $mockRex);

        $term = $termManager->getTermByAppId(self::FOO_APP_ID);

        $this->assertTrue($term instanceof Term);
        $this->assertEquals('Foo', $term->getName());
        $this->assertEquals(self::FOO_FOLKSAURUS_ID, $term->getId());
        $this->assertEquals(self::FOO_APP_ID, $term->getAppId());
        $this->assertEquals('A term.', $term->getScopeNote());
        $this->assertTrue($term->getLastRetrievedTime() > $yesterday);
    }

    public function testGetTermByAppIdForExpiredTermWithNoFolksaurusIdGetsByNameAndSavesTerm()
    {
        $mockDataInterface  = $this->getMock('Folksaurus\DataInterface');
        $mockRex = $this->getMockBuilder('Folksaurus\RequestExecutor')
            ->disableOriginalConstructor()
            ->getMock();

        $yesterday = time() - (60 * 60 * 24);
        $fooTermArray = $this->_getFooTermArray();
        $fooTermArray['last_retrieved'] = $yesterday;
        $fooTermArray['id'] = '0';

        $mockDataInterface->expects($this->once())
            ->method('getTermByAppId')
            ->with($this->equalTo(self::FOO_APP_ID))
            ->will($this->returnValue($fooTermArray));

        $updatedFooTermArray               = $fooTermArray;
        $updatedFooTermArray['scope_note'] = 'Updated scope note';
        $updatedFooTermArray['id']         = self::FOO_FOLKSAURUS_ID;

        // Term is expired, so send request for latest info.
        $mockRex->expects($this->once())
            ->method('getOrCreateTerm')
            ->with($this->equalTo('Foo'))
            ->will($this->returnValue($updatedFooTermArray));

        $mockRex->expects($this->once())
            ->method('getLatestResponseCode')
            ->will($this->returnValue(StatusCodes::OK));

        // Save latest term info.
        $mockDataInterface->expects($this->once())
            ->method('saveTerm')
            ->with($this->isInstanceOf('Folksaurus\Term'));

        $termManager = new TermManager($mockDataInterface, CONFIG_PATH, $mockRex);

        $term = $termManager->getTermByAppId(self::FOO_APP_ID);

        $this->assertTrue($term instanceof Term);
        $this->assertEquals('Foo', $term->getName());
        $this->assertEquals(self::FOO_FOLKSAURUS_ID, $term->getId());
        $this->assertEquals(self::FOO_APP_ID, $term->getAppId());
        $this->assertEquals('Updated scope note', $term->getScopeNote());
        $this->assertTrue($term->getLastRetrievedTime() > $yesterday);
    }

    public function testGetTermByFolksaurusIdForTermNotInDbMakesRequestAndSavesNewTermIfFound()
    {
        $mockDataInterface  = $this->getMock('Folksaurus\DataInterface');
        $mockRex = $this->getMockBuilder('Folksaurus\RequestExecutor')
            ->disableOriginalConstructor()
            ->getMock();

        $mockDataInterface->expects($this->once())
            ->method('getTermByFolksaurusId')
            ->with($this->equalTo(self::FOO_FOLKSAURUS_ID))
            ->will($this->returnValue(false));

        // Term not found, so send a request to Folksaurus for it.
        $mockRex->expects($this->once())
            ->method('getTermById')
            ->with($this->equalTo(self::FOO_FOLKSAURUS_ID))
            ->will($this->returnValue($this->_getFooTermArray()));

        // Save the term to the database.
        $mockDataInterface->expects($this->once())
            ->method('saveTerm')
            ->with($this->isInstanceOf('Folksaurus\Term'));

        $termManager = new TermManager($mockDataInterface, CONFIG_PATH, $mockRex);
        $term = $termManager->getTermByFolksaurusId(self::FOO_FOLKSAURUS_ID);

        $this->assertTrue($term instanceof Term);
        $this->assertEquals('Foo', $term->getName());
        $this->assertEquals(self::FOO_FOLKSAURUS_ID, $term->getId());
        $this->assertEquals(self::FOO_APP_ID, $term->getAppId());
        $this->assertEquals('A term.', $term->getScopeNote());
        $this->assertTrue($term->getLastRetrievedTime() > 0);
    }

    public function testGetTermByFolksaurusIdForNonExpiredTermMakesNoRequestAndDoesNotUpdateTerm()
    {
        $mockDataInterface  = $this->getMock('Folksaurus\DataInterface');
        $mockRex = $this->getMockBuilder('Folksaurus\RequestExecutor')
            ->disableOriginalConstructor()
            ->getMock();

        $fooTermArray = $this->_getFooTermArray();
        $fooTermArray['last_retrieved'] = time();

        $mockDataInterface->expects($this->once())
            ->method('getTermByFolksaurusId')
            ->with($this->equalTo(self::FOO_FOLKSAURUS_ID))
            ->will($this->returnValue($fooTermArray));

        // Won't send a request to Folksaurus, because term was retrieved recently.
        $mockRex->expects($this->never())
            ->method('getTermById');

        // Won't save, because no changes were retrieved.
        $mockDataInterface->expects($this->never())
            ->method('saveTerm');

        $termManager = new TermManager($mockDataInterface, CONFIG_PATH, $mockRex);
        $term = $termManager->getTermByFolksaurusId(self::FOO_FOLKSAURUS_ID);

        $this->assertTrue($term instanceof Term);
        $this->assertEquals('Foo', $term->getName());
        $this->assertEquals(self::FOO_FOLKSAURUS_ID, $term->getId());
        $this->assertEquals(self::FOO_APP_ID, $term->getAppId());
    }

    public function testGetTermByFolksaurusIdForExpiredModifiedTermMakesRequestAndUpdatesTerm()
    {
        $mockDataInterface  = $this->getMock('Folksaurus\DataInterface');
        $mockRex = $this->getMockBuilder('Folksaurus\RequestExecutor')
            ->disableOriginalConstructor()
            ->getMock();

        $yesterday = time() - (60 * 60 * 24);
        $fooTermArray = $this->_getFooTermArray();
        $fooTermArray['last_retrieved'] = $yesterday;

        $mockDataInterface->expects($this->once())
            ->method('getTermByFolksaurusId')
            ->with($this->equalTo(self::FOO_FOLKSAURUS_ID))
            ->will($this->returnValue($fooTermArray));

        $updatedFooTermArray = $fooTermArray;
        $updatedFooTermArray['scope_note'] = 'Updated scope note';

        // Term is expired, so send request for latest info.
        $mockRex->expects($this->once())
            ->method('getTermByIdIfModifiedSince')
            ->with($this->equalTo(self::FOO_FOLKSAURUS_ID))
            ->will($this->returnValue($updatedFooTermArray));

        $mockRex->expects($this->once())
            ->method('getLatestResponseCode')
            ->will($this->returnValue(StatusCodes::OK));

        // Save latest term info.
        $mockDataInterface->expects($this->once())
            ->method('saveTerm')
            ->with($this->isInstanceOf('Folksaurus\Term'));

        $termManager = new TermManager($mockDataInterface, CONFIG_PATH, $mockRex);
        $term = $termManager->getTermByFolksaurusId(self::FOO_FOLKSAURUS_ID);

        $this->assertTrue($term instanceof Term);
        $this->assertEquals('Foo', $term->getName());
        $this->assertEquals(self::FOO_FOLKSAURUS_ID, $term->getId());
        $this->assertEquals(self::FOO_APP_ID, $term->getAppId());
        $this->assertEquals('Updated scope note', $term->getScopeNote());
        $this->assertTrue($term->getLastRetrievedTime() > 0);
    }

    public function testGetTermByFolksaurusIdForExpiredUnmodifiedTermMakesRequestButDoesNotUpdate()
    {
        $mockDataInterface  = $this->getMock('Folksaurus\DataInterface');
        $mockRex = $this->getMockBuilder('Folksaurus\RequestExecutor')
            ->disableOriginalConstructor()
            ->getMock();

        $yesterday = time() - (60 * 60 * 24);
        $fooTermArray = $this->_getFooTermArray();
        $fooTermArray['last_retrieved'] = $yesterday;

        $mockDataInterface->expects($this->once())
            ->method('getTermByFolksaurusId')
            ->with($this->equalTo(self::FOO_FOLKSAURUS_ID))
            ->will($this->returnValue($fooTermArray));

        $updatedFooTermArray = $fooTermArray;
        $updatedFooTermArray['scope_note'] = 'Updated scope note';

        // Term is expired, so send request for latest info.
        $mockRex->expects($this->once())
            ->method('getTermByIdIfModifiedSince')
            ->with($this->equalTo(self::FOO_FOLKSAURUS_ID))
            ->will($this->returnValue(false));

        // No term saved since there were no changes.
        $mockDataInterface->expects($this->never())
            ->method('saveTerm');

        $termManager = new TermManager($mockDataInterface, CONFIG_PATH, $mockRex);
        $term = $termManager->getTermByFolksaurusId(self::FOO_FOLKSAURUS_ID);

        $this->assertTrue($term instanceof Term);
        $this->assertEquals('Foo', $term->getName());
        $this->assertEquals(self::FOO_FOLKSAURUS_ID, $term->getId());
        $this->assertEquals(self::FOO_APP_ID, $term->getAppId());
        $this->assertEquals('A term.', $term->getScopeNote());
        $this->assertTrue($term->getLastRetrievedTime() > 0);
    }

    public function testGetOrCreateTermGetsTermFromDbIfItExistsAndIsCurrent()
    {
        $mockDataInterface  = $this->getMock('Folksaurus\DataInterface');
        $mockRex = $this->getMockBuilder('Folksaurus\RequestExecutor')
            ->disableOriginalConstructor()
            ->getMock();

        $fooTermArray = $this->_getFooTermArray();
        $fooTermArray['last_retrieved'] = time();

        $mockDataInterface->expects($this->once())
            ->method('getTermByName')
            ->with($this->equalTo('Foo'))
            ->will($this->returnValue($fooTermArray));

        // Won't send a request to Folksaurus, because term was retrieved recently.
        $mockRex->expects($this->never())
            ->method('getTermByName');
        $mockRex->expects($this->never())
            ->method('getTermByIdIfModifiedSince');
        $mockRex->expects($this->never())
            ->method('getOrCreateTerm');
        $mockRex->expects($this->never())
            ->method('createTerm');

        // Won't save, because no changes were retrieved.
        $mockDataInterface->expects($this->never())
            ->method('saveTerm');

        $termManager = new TermManager($mockDataInterface, CONFIG_PATH, $mockRex);
        $term = $termManager->getOrCreateTerm('Foo');

        $this->assertTrue($term instanceof Term);
        $this->assertEquals('Foo', $term->getName());
        $this->assertEquals(self::FOO_FOLKSAURUS_ID, $term->getId());
        $this->assertEquals(self::FOO_APP_ID, $term->getAppId());
    }

    public function testGetOrCreateTermGetsLatestVersionOfTermIfItExistsInDbButIsPassedExpireTime()
    {
        $mockDataInterface  = $this->getMock('Folksaurus\DataInterface');
        $mockRex = $this->getMockBuilder('Folksaurus\RequestExecutor')
            ->disableOriginalConstructor()
            ->getMock();

        $yesterday = time() - (60 * 60 * 24);
        $fooTermArray = $this->_getFooTermArray();
        $fooTermArray['last_retrieved'] = $yesterday;

        $mockDataInterface->expects($this->once())
            ->method('getTermByName')
            ->with($this->equalTo('Foo'))
            ->will($this->returnValue($fooTermArray));

        $updatedFooTermArray = $fooTermArray;
        $updatedFooTermArray['scope_note'] = 'Updated scope note';

        $mockRex->expects($this->once())
            ->method('getTermByIdIfModifiedSince')
            ->with(
                $this->equalTo(self::FOO_FOLKSAURUS_ID),
                $this->equalTo($yesterday)
            )->will($this->returnValue($updatedFooTermArray));

        $mockRex->expects($this->once())
            ->method('getLatestResponseCode')
            ->will($this->returnValue(StatusCodes::OK));

        $mockDataInterface->expects($this->once())
            ->method('saveTerm')
            ->with($this->isInstanceOf('Folksaurus\Term'));

        $termManager = new TermManager($mockDataInterface, CONFIG_PATH, $mockRex);
        $term = $termManager->getOrCreateTerm('Foo');

        $this->assertTrue($term instanceof Term);
        $this->assertEquals('Foo', $term->getName());
        $this->assertEquals('Updated scope note', $term->getScopeNote());
        $this->assertEquals(self::FOO_FOLKSAURUS_ID, $term->getId());
        $this->assertEquals(self::FOO_APP_ID, $term->getAppId());
    }

    public function testGetOrCreateTermGetsOrCreatesTermInFolksaurusThenAddsItToTheDb()
    {
        $mockDataInterface  = $this->getMock('Folksaurus\DataInterface');
        $mockRex = $this->getMockBuilder('Folksaurus\RequestExecutor')
            ->disableOriginalConstructor()
            ->getMock();

        $mockDataInterface->expects($this->once())
            ->method('getTermByName')
            ->with($this->equalTo('Foo'))
            ->will($this->returnValue(false));

        $mockRex->expects($this->once())
            ->method('getOrCreateTerm')
            ->with('Foo')
            ->will($this->returnValue($this->_getFooTermArray()));

        $mockDataInterface->expects($this->once())
            ->method('saveTerm')
            ->with($this->isInstanceOf('Folksaurus\Term'));

        $termManager = new TermManager($mockDataInterface, CONFIG_PATH, $mockRex);
        $term = $termManager->getOrCreateTerm('Foo');

        $this->assertTrue($term instanceof Term);
        $this->assertEquals('Foo', $term->getName());
        $this->assertEquals(self::FOO_FOLKSAURUS_ID, $term->getId());
        $this->assertEquals(self::FOO_APP_ID, $term->getAppId());
    }

    public function testGetTermByNameGetsTermFromDbIfItExistsAndIsCurrent()
    {
        $mockDataInterface  = $this->getMock('Folksaurus\DataInterface');
        $mockRex = $this->getMockBuilder('Folksaurus\RequestExecutor')
            ->disableOriginalConstructor()
            ->getMock();

        $fooTermArray = $this->_getFooTermArray();
        $fooTermArray['last_retrieved'] = time();

        $mockDataInterface->expects($this->once())
            ->method('getTermByName')
            ->with($this->equalTo('Foo'))
            ->will($this->returnValue($fooTermArray));

        // Won't send a request to Folksaurus, because term was retrieved recently.
        $mockRex->expects($this->never())
            ->method('getTermByName');
        $mockRex->expects($this->never())
            ->method('getTermByIdIfModifiedSince');
        $mockRex->expects($this->never())
            ->method('getOrCreateTerm');
        $mockRex->expects($this->never())
            ->method('createTerm');

        // Won't save, because no changes were retrieved.
        $mockDataInterface->expects($this->never())
            ->method('saveTerm');

        $termManager = new TermManager($mockDataInterface, CONFIG_PATH, $mockRex);
        $term = $termManager->getTermByName('Foo');

        $this->assertTrue($term instanceof Term);
        $this->assertEquals('Foo', $term->getName());
        $this->assertEquals(self::FOO_FOLKSAURUS_ID, $term->getId());
        $this->assertEquals(self::FOO_APP_ID, $term->getAppId());
    }

    public function testGetTermByNameGetsLatestVersionOfTermIfItExistsInDbButIsPassedExpireTime()
    {
        $mockDataInterface  = $this->getMock('Folksaurus\DataInterface');
        $mockRex = $this->getMockBuilder('Folksaurus\RequestExecutor')
            ->disableOriginalConstructor()
            ->getMock();

        $yesterday = time() - (60 * 60 * 24);
        $fooTermArray = $this->_getFooTermArray();
        $fooTermArray['last_retrieved'] = $yesterday;

        $mockDataInterface->expects($this->once())
            ->method('getTermByName')
            ->with($this->equalTo('Foo'))
            ->will($this->returnValue($fooTermArray));

        $updatedFooTermArray = $fooTermArray;
        $updatedFooTermArray['scope_note'] = 'Updated scope note';

        $mockRex->expects($this->once())
            ->method('getTermByIdIfModifiedSince')
            ->with(
                $this->equalTo(self::FOO_FOLKSAURUS_ID),
                $this->equalTo($yesterday)
            )->will($this->returnValue($updatedFooTermArray));

        $mockRex->expects($this->once())
            ->method('getLatestResponseCode')
            ->will($this->returnValue(StatusCodes::OK));

        $mockDataInterface->expects($this->once())
            ->method('saveTerm')
            ->with($this->isInstanceOf('Folksaurus\Term'));

        $termManager = new TermManager($mockDataInterface, CONFIG_PATH, $mockRex);
        $term = $termManager->getTermByName('Foo');

        $this->assertTrue($term instanceof Term);
        $this->assertEquals('Foo', $term->getName());
        $this->assertEquals('Updated scope note', $term->getScopeNote());
        $this->assertEquals(self::FOO_FOLKSAURUS_ID, $term->getId());
        $this->assertEquals(self::FOO_APP_ID, $term->getAppId());
    }

    public function testGetTermByNameCreatesTermInDbIfItExistsInFolksaurusButNotTheDb()
    {
        $mockDataInterface  = $this->getMock('Folksaurus\DataInterface');
        $mockRex = $this->getMockBuilder('Folksaurus\RequestExecutor')
            ->disableOriginalConstructor()
            ->getMock();

        $mockDataInterface->expects($this->once())
            ->method('getTermByName')
            ->with($this->equalTo('Foo'))
            ->will($this->returnValue(false));

        $mockRex->expects($this->once())
            ->method('getTermByName')
            ->with('Foo')
            ->will($this->returnValue($this->_getFooTermArray()));

        $mockDataInterface->expects($this->once())
            ->method('saveTerm')
            ->with($this->isInstanceOf('Folksaurus\Term'));

        $termManager = new TermManager($mockDataInterface, CONFIG_PATH, $mockRex);
        $term = $termManager->getTermByName('Foo');

        $this->assertTrue($term instanceof Term);
        $this->assertEquals('Foo', $term->getName());
        $this->assertEquals(self::FOO_FOLKSAURUS_ID, $term->getId());
        $this->assertEquals(self::FOO_APP_ID, $term->getAppId());
    }

    public function testGetTermByNameReturnsFalseIfNotFoundInDbOrFolksaurus()
    {
        $mockDataInterface  = $this->getMock('Folksaurus\DataInterface');
        $mockRex = $this->getMockBuilder('Folksaurus\RequestExecutor')
            ->disableOriginalConstructor()
            ->getMock();

        $mockDataInterface->expects($this->once())
            ->method('getTermByName')
            ->with($this->equalTo('Foo'))
            ->will($this->returnValue(false));

        $mockRex->expects($this->once())
            ->method('getTermByName')
            ->with('Foo')
            ->will($this->returnValue(false));

        $mockDataInterface->expects($this->never())
            ->method('saveTerm');

        $termManager = new TermManager($mockDataInterface, CONFIG_PATH, $mockRex);
        $term = $termManager->getTermByName('Foo');

        $this->assertFalse($term);
    }

    public function testGetOrCreateTermCreatesTermLocallyIfUnableToCreateItInFolksaurus()
    {
        $mockDataInterface  = $this->getMock('Folksaurus\DataInterface');
        $mockRex = $this->getMockBuilder('Folksaurus\RequestExecutor')
            ->disableOriginalConstructor()
            ->getMock();

        $mockDataInterface->expects($this->once())
            ->method('getTermByName')
            ->with($this->equalTo('Foo'))
            ->will($this->returnValue(false));

        $mockRex->expects($this->once())
            ->method('getOrCreateTerm')
            ->with('Foo')
            ->will($this->returnValue(false));

        $mockDataInterface->expects($this->once())
            ->method('saveTerm')
            ->with($this->isInstanceOf('Folksaurus\Term'));

        $termManager = new TermManager($mockDataInterface, CONFIG_PATH, $mockRex);
        $term = $termManager->getOrCreateTerm('Foo');

        $this->assertTrue($term instanceof Term);
        $this->assertEquals('Foo', $term->getName());
        $this->assertEquals('', $term->getId());
    }

    public function testGetOrCreateOnRemotelyDeletedTermInDbReturnsFalseAndCallsDeleteTerm()
    {
        $mockDataInterface  = $this->getMock('Folksaurus\DataInterface');
        $mockRex = $this->getMockBuilder('Folksaurus\RequestExecutor')
            ->disableOriginalConstructor()
            ->getMock();

        $fooTermArray = $this->_getFooTermArray();

        $mockDataInterface->expects($this->once())
            ->method('getTermByName')
            ->with($this->equalTo('Foo'))
            ->will($this->returnValue($fooTermArray));

        $mockRex->expects($this->once())
            ->method('getTermByIdIfModifiedSince')
            ->with(
                $this->equalTo(self::FOO_FOLKSAURUS_ID),
                $this->equalTo($fooTermArray['last_retrieved'])
            )->will($this->returnValue(false));

        $mockRex->expects($this->once())
            ->method('getLatestResponseCode')
            ->will($this->returnValue(StatusCodes::GONE));

        $mockDataInterface->expects($this->once())
            ->method('deleteTerm')
            ->with($this->equalTo($fooTermArray['app_id']));

        $mockDataInterface->expects($this->never())
            ->method('saveTerm');

        $termManager = new TermManager($mockDataInterface, CONFIG_PATH, $mockRex);
        $term = $termManager->getOrCreateTerm('Foo');

        $this->assertFalse($term);
    }

    public function testGetOrCreateOnRemotelyDeletedTermNotinDbReturnsFalseAndDoesNotSaveTerm()
    {
        $mockDataInterface  = $this->getMock('Folksaurus\DataInterface');
        $mockRex = $this->getMockBuilder('Folksaurus\RequestExecutor')
            ->disableOriginalConstructor()
            ->getMock();

        $fooTermArray = $this->_getFooTermArray();

        $mockDataInterface->expects($this->once())
            ->method('getTermByName')
            ->with($this->equalTo('Foo'))
            ->will($this->returnValue(false));

        $mockRex->expects($this->once())
            ->method('getOrCreateTerm')
            ->with($this->equalTo('Foo'))
            ->will($this->returnValue(false));

        // The GONE code is returned when trying to PUT a term that has been deleted.
        $mockRex->expects($this->once())
            ->method('getLatestResponseCode')
            ->will($this->returnValue(StatusCodes::GONE));

        $mockDataInterface->expects($this->never())
            ->method('saveTerm');

        // Delete not called because there is no local term to delete.
        $mockDataInterface->expects($this->never())
            ->method('deleteTerm');

        $termManager = new TermManager($mockDataInterface, CONFIG_PATH, $mockRex);
        $term = $termManager->getOrCreateTerm('Foo');

        $this->assertFalse($term);
    }

}
