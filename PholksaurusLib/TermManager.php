<?php
namespace Folksaurus;

/**
 * Retrieve terms and keep the database in sync with Folksaurus.
 */
class TermManager
{
    /**
     * The name-value pairs from the configuration file.
     *
     * @var array
     */
    protected $_config;

    /**
     * An implementation of DataInterface.
     *
     * @var DataInterface
     */
    protected $_dataInterface;

    /**
     * @var RequestExecutor
     */
    protected $_rex;

    /**
     * Constructor.
     *
     * @param DataInterface $dataInterface  An implementation of DataInterface.
     * @param string $configFile         The relative path to the config file to use.
     * @param RequestExecutor $rex       If not provided, an instance will be created
     *                                   using api_key and api_url from the config file.
     * @throws Exception if config file could not be found or read or is missing
     *                   required values.
     */
    public function __construct(DataInterface $dataInterface, $configFile = 'config.ini',
                                RequestExecutor $rex = null)
    {
        $config = parse_ini_file($configFile);
        if (!$config) {
            throw new Exception('Failed to read config file: ' . $configFile);
        }
        $requiredKeys = array(
            'api_key',
            'api_url'
        );
        $missingKeys = array_diff(
            $requiredKeys,
            array_keys($config)
        );
        if ($missingKeys) {
            throw new Exception(
                'Values missing for the following keys: ' .
                implode(',', $missingKeys)
            );
        }

        if ($rex === null) {
            $rex = new RequestExecutor(
                $config['api_key'],
                $config['api_url']
            );
        }
        $this->_config        = $config;
        $this->_dataInterface = $dataInterface;
        $this->_rex           = $rex;
    }

    /**
     * Retrieve a term by the ID assigned to it by your application.
     *
     * Update database with most recent term details if expire_time has
     * passed since the last_retrieved date.
     *
     * @param type $id
     * @return Term|bool  False if not found.
     */
    public function getTermByAppId($id)
    {
        $termArray = $this->_dataInterface->getTermByAppId($id);
        if ($termArray) {
            $term = new Term($termArray, $this);
            return $this->_getLatestTerm($term);
        }
        return false;
    }

    /**
     * Retrieve a term by its Folksaurus-assigned ID.
     *
     * Update database if expire_time has passed since the last_retrieved date.
     *
     * @param int $id
     * @return Term|bool  False if unable to retrieve or if the term was deleted.
     */
    public function getTermByFolksaurusId($id)
    {
        $termArray = $this->_dataInterface->getTermByFolksaurusId($id);
        if ($termArray) {
            $term = new Term($termArray, $this);
            return $this->_getLatestTerm($term);
        } else {
            $termArray = $this->_rex->getTermById($id);
            if ($termArray) {
                $termArray['last_retrieved'] = time();
                $term = new Term($termArray, $this);
                $this->_dataInterface->saveTerm($term);
                return $term;
            }
            return false;
        }
    }

    /**
     * Get a term by its name.
     *
     * @param type $name
     * @return Term|bool  False if not found.
     */
    public function getTermByName($name)
    {
        $termArray = $this->_dataInterface->getTermByName($name);
        if ($termArray) {
            $term = new Term($termArray, $this);
            return $this->_getLatestTerm($term);
        }
        $termArray = $this->_rex->getTermByName($name);
        if ($termArray) {
            $termArray['last_retrieved'] = time();
            $term = new Term($termArray, $this);
            $this->_dataInterface->saveTerm($term);
            return $term;
        }
        return false;
    }

    /**
     * Get a term by its name.  If it doesn't exist, create it.
     *
     * @param string $name
     * @return Term|bool  False if unable to get or create term.
     */
    public function getOrCreateTerm($name)
    {
        $termArray = $this->_dataInterface->getTermByName($name);
        if ($termArray) {
            $term = new Term($termArray, $this);
            return $this->_getLatestTerm($term);
        }
        $termArray = $this->_rex->getOrCreateTerm($name);
        if ($termArray) {
            $termArray['last_retrieved'] = time();
            $term = new Term($termArray, $this);
            $this->_dataInterface->saveTerm($term);
            return $term;
        } else if ($this->_rex->getLatestResponseCode() == StatusCodes::GONE) {
            return false;
        } else {
            // If term not retrieved or created for some other reason, create a
            // placeholder term locally.
            $term = new Term(
                array(
                    'name'           => $name,
                    'last_retrieved' => 0
                ),
                $this
            );
            $this->_dataInterface->saveTerm($term);
            return $term;
        }
    }

    /**
     * Get the RequestExecutor object used by this instance.
     *
     * @return RequestExecutor
     */
    public function getRequestExecutor()
    {
        return $this->_rex;
    }

    /**
     * Get the latest data for $term and return an updated Term object.
     *
     * If term is current, return it back.
     *
     * If term is out-of-date, retrieve the latest version, save to your DB, and return it.
     *
     * If Folksaurus ID is not set, search Folksaurus by term name.
     *
     * If term does not exist in Folksaurus, add it as a new term.  This will reset
     * the term info in your database.
     *
     * Original term returned if the request fails for any reason.
     *
     * @param Term $term
     * @return Term
     */
    protected function _getLatestTerm(Term $term)
    {
        $lastRetrievedTime = $term->getLastRetrievedTime();
        $now = time();
        $secondsSinceUpdate = ($now - $lastRetrievedTime);
        if ($secondsSinceUpdate > $this->_config['expire_time']) {
            // If Folksaurus ID is known, search by that.
            if ($term->getId()) {
                $updatedTermArray = $this->_rex->getTermByIdIfModifiedSince(
                    $term->getId(),
                    $lastRetrievedTime
                );
            }

            // If that didn't work, or if Folksaurus ID not set, get or create by name.
            if (!$term->getId() || !$updatedTermArray) {
                $updatedTermArray = $this->_rex->getOrCreateTerm($term->getName());
            }

            $responseCode = $this->_rex->getLatestResponseCode();
            switch ($responseCode) {
                case StatusCodes::CREATED:
                    // Intentional fall-through.
                case StatusCodes::OK:
                    $updatedTermArray['last_retrieved'] = time();
                    $updatedTermArray['app_id'] = $term->getAppId();
                    $updatedTerm = new Term($updatedTermArray, $this);
                    $this->_dataInterface->saveTerm($updatedTerm);
                    return $updatedTerm;
                    break;
                case StatusCodes::NOT_MODIFIED:
                    $term->updateLastRetrievedTime();
                    $this->_dataInterface->saveTerm($term);
                    break;
                case StatusCodes::GONE:
                    $this->_dataInterface->deleteTerm($term->getAppId());
                    return false;
                    break;
                // Other responses are possible, but require no action.
            }
        }
        return $term;
    }
}
