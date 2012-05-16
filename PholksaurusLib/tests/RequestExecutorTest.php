<?php
namespace Folksaurus;

/**
 * Test class for RequestExecutor.
 */
class RequestExecutorTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Get a simple term array for an unsorted term called Foo.
     *
     * @return array
     */
    protected function _getFooTermArray()
    {
        return array(
            'id'         => '1',
            'name'       => 'Foo',
            'scope_note' => 'A term.',
            'broader'    => array(),
            'narrower'   => array(),
            'related'    => array(),
            'used_for'   => array(),
            'use'        => array()
        );
    }

    protected function _getAuthenticationHeader()
    {
        return 'Authorization: Basic ' . base64_encode(':' . API_KEY);
    }

    public function testGetTermByIdMakesExpectedCallsOnCurlObject()
    {
        $mockCurl = $this->getMock('Curl');
        // URL is set.
        $mockCurl->expects($this->at(0))
            ->method('__set')
            ->with(
                $this->equalTo('url'),
                $this->equalTo(
                    sprintf(
                        RequestExecutor::RES_TERM_BY_ID,
                        API_URL,
                        '1'
                    )
                )
            );

        $mockCurl->expects($this->at(1))
            ->method('__set')
            ->with(
                $this->equalTo('customrequest'),
                $this->equalTo('GET')
            );

        // Authorization header set.
        $headers = array($this->_getAuthenticationHeader());
        $mockCurl->expects($this->at(2))
            ->method('__set')
            ->with(
                $this->equalTo('httpheader'),
                $this->equalTo($headers)
            );

        // JSON fetched.
        $mockCurl->expects($this->at(3))
            ->method('fetch_json')
            ->with($this->equalTo(true))
            ->will($this->returnValue($this->_getFooTermArray()));

        // Response code retrieved.
        $mockCurl->expects($this->at(4))
            ->method('info')
            ->with($this->equalTo('HTTP_CODE'))
            ->will($this->returnValue(200));

        $rex = new RequestExecutor(API_KEY, API_URL, $mockCurl);
        $termArray = $rex->getTermById('1');
        $this->assertEquals($this->_getFooTermArray(), $termArray);
    }

    public function testGetTermByIdIfModifiedSinceMakesExpectedCallsOnCurlObject()
    {
        $mockCurl = $this->getMock('Curl');
        // URL is set.
        $mockCurl->expects($this->at(0))
            ->method('__set')
            ->with(
                $this->equalTo('url'),
                $this->equalTo(
                    sprintf(
                        RequestExecutor::RES_TERM_BY_ID,
                        API_URL,
                        '1'
                    )
                )
            );

        $mockCurl->expects($this->at(1))
            ->method('__set')
            ->with(
                $this->equalTo('customrequest'),
                $this->equalTo('GET')
            );

        // Authorization header and if-modified-since header set.
        $headers = array(
            'If-Modified-Since: ' . gmdate('D, d M Y H:i:s \G\M\T', time()),
            $this->_getAuthenticationHeader()
        );
        $mockCurl->expects($this->at(2))
            ->method('__set')
            ->with(
                $this->equalTo('httpheader'),
                $this->equalTo($headers)
            );

        // JSON fetched.
        $mockCurl->expects($this->at(3))
            ->method('fetch_json')
            ->with($this->equalTo(true))
            ->will($this->returnValue(NULL));

        // Response code retrieved.
        $mockCurl->expects($this->at(4))
            ->method('info')
            ->with($this->equalTo('HTTP_CODE'))
            ->will($this->returnValue(304));

        // False returned if response is NULL because term not modified since.

        $rex = new RequestExecutor(API_KEY, API_URL, $mockCurl);
        $returnValue = $rex->getTermByIdIfModifiedSince('1', time());
        $this->assertFalse($returnValue);
        $this->assertEquals(304, $rex->getLatestResponseCode());

        // False also returned if response is NULL because the term was not found at all.

        // Response code retrieved.
        $mockCurl->expects($this->at(4))
            ->method('info')
            ->with($this->equalTo('HTTP_CODE'))
            ->will($this->returnValue(404));

        $returnValue = $rex->getTermByIdIfModifiedSince('1', time());
        $this->assertFalse($returnValue);
        $this->assertEquals(404, $rex->getLatestResponseCode());

        // If term is found and has been modified, the term array is returned.

        $mockCurl->expects($this->at(3))
            ->method('fetch_json')
            ->with($this->equalTo(true))
            ->will($this->returnValue($this->_getFooTermArray()));

        $mockCurl->expects($this->at(4))
            ->method('info')
            ->with($this->equalTo('HTTP_CODE'))
            ->will($this->returnValue(200));

        $rex = new RequestExecutor(API_KEY, API_URL, $mockCurl);
        $termArray = $rex->getTermByIdIfModifiedSince('1', time());
        $this->assertEquals($this->_getFooTermArray(), $termArray);
        $this->assertEquals(200, $rex->getLatestResponseCode());
    }

    public function testGetTermByNameMakesExpectedCallsOnCurlObject()
    {
        $mockCurl = $this->getMock('Curl');
        // URL is set.
        $mockCurl->expects($this->at(0))
            ->method('__set')
            ->with(
                $this->equalTo('url'),
                $this->equalTo(
                    sprintf(
                        RequestExecutor::RES_TERM_BY_NAME,
                        API_URL,
                        'Foo'
                    )
                )
            );

        $mockCurl->expects($this->at(1))
            ->method('__set')
            ->with(
                $this->equalTo('customrequest'),
                $this->equalTo('GET')
            );

        // Authorization header set.
        $headers = array($this->_getAuthenticationHeader());
        $mockCurl->expects($this->at(2))
            ->method('__set')
            ->with(
                $this->equalTo('httpheader'),
                $this->equalTo($headers)
            );

        // JSON fetched.
        $mockCurl->expects($this->at(3))
            ->method('fetch_json')
            ->with($this->equalTo(true))
            ->will($this->returnValue($this->_getFooTermArray()));

        // Response code retrieved.
        $mockCurl->expects($this->at(4))
            ->method('info')
            ->with($this->equalTo('HTTP_CODE'))
            ->will($this->returnValue(200));

        $rex = new RequestExecutor(API_KEY, API_URL, $mockCurl);
        $termArray = $rex->getTermByName('Foo');
        $this->assertEquals($this->_getFooTermArray(), $termArray);
    }

    public function testGetOrCreateTermMakesExpectedCallsOnCurlObject()
    {
        // Acts just like getTermByName if the name returns a result.
        $mockCurl = $this->getMock('Curl');
        // URL is set.
        $mockCurl->expects($this->at(0))
            ->method('__set')
            ->with(
                $this->equalTo('url'),
                $this->equalTo(
                    sprintf(
                        RequestExecutor::RES_TERM_BY_NAME,
                        API_URL,
                        'Foo'
                    )
                )
            );

        $mockCurl->expects($this->at(1))
            ->method('__set')
            ->with(
                $this->equalTo('customrequest'),
                $this->equalTo('PUT')
            );

        // Authorization and Content-Length headers set.
        $headers = array(
            'Content-Length: 0',
            $this->_getAuthenticationHeader()
        );
        $mockCurl->expects($this->at(2))
            ->method('__set')
            ->with(
                $this->equalTo('httpheader'),
                $this->equalTo($headers)
            );

        // Post fields are blank, since all necessary info is in the URL.
        $mockCurl->expects($this->at(3))
            ->method('__set')
            ->with(
                $this->equalTo('postfields'),
                $this->equalTo('')
            );

        // Results fetched and ID returned.
        $mockCurl->expects($this->at(4))
            ->method('fetch_json')
            ->will($this->returnValue($this->_getFooTermArray()));

        // Response code retrieved.
        $mockCurl->expects($this->at(5))
            ->method('info')
            ->with($this->equalTo('HTTP_CODE'))
            ->will($this->returnValue(201));

        $rex = new RequestExecutor(API_KEY, API_URL, $mockCurl);

        $termArray = $rex->getOrCreateTerm('Foo');

        $this->assertEquals($this->_getFooTermArray(), $termArray);
    }

}
