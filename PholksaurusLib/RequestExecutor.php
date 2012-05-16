<?php
namespace Folksaurus;

/**
 * Class for making requests to and receiving responses from Folksaurus.
 */
class RequestExecutor
{
    // Resource URL patterns.
    // In each pattern, the first %s will be replaced with the value of $this->_url.
    const RES_TERM_BY_ID   = '%s/term-by-id/%s/';
    const RES_TERM_BY_NAME = '%s/term-by-name/%s/';

    /**
     * The API key assigned to the app for which requests are made.
     *
     * @var string
     */
    protected $_apiKey;

    /**
     * The base URL to which requests will be made.
     *
     * @var string
     */
    protected $_url;

    /**
     * The latest response code received by this object.
     *
     * @var int
     */
    protected $_latestResponseCode;

    /**
     * A Curl object.
     *
     * @var type Curl
     */
    protected $_curlObj;

    /**
     * Constructor
     *
     * @param string $apiKey
     * @param string $url
     * @param Curl   $curlObj For passing in a mock Curl object.
     */
    public function __construct($apiKey, $url, \Curl $curlObj = null)
    {
        $this->_apiKey = $apiKey;
        $this->_url = trim($url, '/ ');
        if ($curlObj === null) {
            $this->_curlObj = new \Curl();
        } else {
            $this->_curlObj = $curlObj;
        }
    }

    /**
     * Get the latest response code receieved by this object.
     *
     * @return int
     */
    public function getLatestResponseCode()
    {
        return $this->_latestResponseCode;
    }

    /**
     * Set the specified headers plus the authorization header to the handle.
     *
     * @param Curl $handle   A Curl object.
     * @param array $headers
     */
    protected function _addHeaders($headers = array())
    {
        $authorizationHeader = sprintf(
            'Authorization: Basic %s',
            base64_encode(':' . $this->_apiKey)
        );
        $headers[] = $authorizationHeader;
        $this->_curlObj->httpheader = $headers;
    }

    /**
     * Add required headers and execute a GET request on $uri.
     *
     * Return the decoded JSON and set $this->_latestResponseCode to
     * the response code.  Return NULL if the body is empty.
     *
     * @param string $uri
     * @param int $ifModifiedSince  Optional if-modified-since timestamp.
     * @return array      An array of decoded JSON data, or NULL if no response.
     */
    protected function _executeGetRequest($uri, $ifModifiedSince = null)
    {
        $this->_curlObj->url = $uri;
        $this->_curlObj->customrequest = 'GET';
        if ($ifModifiedSince) {
            $additionalHeaders = array(
                'If-Modified-Since: ' . gmdate('D, d M Y H:i:s \G\M\T', $ifModifiedSince)
            );
        } else {
            $additionalHeaders = array();
        }
        $this->_addHeaders($additionalHeaders);
        $response = $this->_curlObj->fetch_json(true);
        $responseCode = $this->_curlObj->info('HTTP_CODE');
        $this->_latestResponseCode = $responseCode;
        return $response;
    }

    /**
     * Get a term info array by its Folksaurus ID.
     *
     * @param type $termId
     * @param type $ifModifiedSince
     * @return array|bool            False if term not found, or if $ifModifiedSince
     *                               is specified and the term has not changed.
     */
    protected function _getTermById($termId, $ifModifiedSince = null)
    {
        $uri = sprintf(self::RES_TERM_BY_ID, $this->_url, $termId);
        $response = $this->_executeGetRequest($uri, $ifModifiedSince);
        return $response ?: false;
    }

    /**
     * Get a term info array by its Folksaurus ID.
     *
     * @param int $termId
     * @return array|bool  False if not found or not modified since.
     */
    public function getTermById($termId)
    {
        return $this->_getTermById($termId);
    }

    /**
     * Get a term info array only if the term has been modified since $ifModifiedSince.
     *
     * @param int $termId
     * @param int $ifModifiedSince  A timestamp.
     * @return array|bool           The term if found and modified since.
     *                              False if not found or not modified since.
     *                              To determine which, call getLastResponseCode.
     *                              304 means the term was found but unchanged.
     */
    public function getTermByIdIfModifiedSince($termId, $ifModifiedSince)
    {
        return $this->_getTermById($termId, $ifModifiedSince);
    }

    /**
     * Get a term info array by the term name, or false if not found.
     *
     * @param string $name
     * @return array
     */
    public function getTermByName($name)
    {
        $uri = sprintf(
            self::RES_TERM_BY_NAME,
            $this->_url,
            rawurlencode($name)
        );
        $response = $this->_executeGetRequest($uri);
        return $response ?: false;
    }

    /**
     * Attempt to get a term by name.  If it does not exist, create it.
     *
     * @param string $name
     * @return array        If found, a term info array.  Otherwise, false.
     */
    public function getOrCreateTerm($name)
    {
        // Since trying to PUT an existing term returns a 409 response with the
        // JSON of the conflicting term, we can save a request by just attempting
        // a PUT and returning the output.
        $uri = sprintf(
            self::RES_TERM_BY_NAME,
            $this->_url,
            rawurlencode($name)
        );
        $this->_curlObj->url = $uri;
        $this->_curlObj->customrequest = 'PUT';
        $this->_addHeaders(array('Content-Length: 0'));
        $this->_curlObj->postfields = '';
        $response = $this->_curlObj->fetch_json(true);
        $this->_latestResponseCode = $this->_curlObj->info('HTTP_CODE');
        return $response;
    }

}