<?php
namespace Folksaurus;

/**
 * Representation of a term.
 */
class Term
{
    // Values returned by getStatus().
    const STATUS_PREFERRED    = 'preferred';
    const STATUS_NONPREFERRED = 'non-preferred';
    const STATUS_UNSORTED     = 'unsorted';

    /**
     * The term ID.
     *
     * @var int
     */
    protected $_id;

    /**
     * The term name.
     *
     * @var string
     */
    protected $_name;

    /**
     * @var string
     */
    protected $_scopeNote;

    /**
     * Broader terms for this term.
     *
     * @var array  An array of TermSummary objects.
     */
    protected $_broaderTerms = array();

    /**
     * Narrower terms for this term.
     *
     * @var array  An array of TermSummary objects.
     */
    protected $_narrowerTerms = array();

    /**
     * Related terms for this term.
     *
     * @var array  An array of TermSummary objects.
     */
    protected $_relatedTerms = array();

    /**
     * Non-preferred terms used for this term.
     *
     * @var array  An array of TermSummary objects.
     */
    protected $_usedForTerms = array();

    /**
     * Preferred terms used for this term.
     *
     * @var array  An array of TermSummary objects.
     */
    protected $_useTerms = array();

    /**
     * A timestamp of when the details for the term were last retrieved.
     *
     * @var string
     */
    protected $_lastRetrieved = 0;

    /**
     * The ID assigned to this term by your application.
     *
     * @var string
     */
    protected $_appId = '';

    /**
     * A TermManager object, which is used to get info on related terms.
     *
     * @var TermManager
     */
    protected $_termManager;

    /**
     * Constructor
     *
     * $values must contain the following keys:
     *
     * <pre>
     * id (the Folksaurus-assigned ID)
     * name
     * scope_note
     * broader
     * narrower
     * related
     * used_for
     * use
     * last_retrieved (a UNIX timestamp)
     *
     * and optionally (if you know it): app_id
     *
     * Values for the keys between 'broader' and 'use' are arrays
     * where each element is an array with the following keys:
     *
     * id (the Folksaurus-assigned ID)
     * name
     *
     * and optionally: app_id
     * </pre>
     *
     * @var array $values
     * @param TermManager $termManager
     * @throws Exception  if required keys missing from $values.
     */
    public function __construct(array $values, TermManager $termManager)
    {
        if (!isset($values['name']) || !$values['name']) {
            throw new Exception('Term must have a value set for name');
        }

        $defaultValues = array(
            'id'             => '',
            'app_id'         => '',
            'scope_note'     => '',
            'last_retrieved' => 0,
            'broader'        => array(),
            'narrower'       => array(),
            'related'        => array(),
            'used_for'       => array(),
            'use'            => array(),
        );

        $termValues = array_merge($defaultValues, $values);

        $this->_termManager   = $termManager;
        $this->_id            = $termValues['id'];
        $this->_name          = $termValues['name'];
        $this->_scopeNote     = $termValues['scope_note'];
        $this->_appId         = $termValues['app_id'];
        $this->_lastRetrieved = $termValues['last_retrieved'];

        foreach ($termValues['broader'] as $broader) {
            $this->_broaderTerms[] = new TermSummary(
                $broader,
                $termManager
            );
        }
        foreach ($termValues['narrower'] as $narrower) {
            $this->_narrowerTerms[] = new TermSummary(
                $narrower,
                $termManager
            );
        }
        foreach ($termValues['related'] as $related) {
            $this->_relatedTerms[] = new TermSummary(
                $related,
                $termManager
            );
        }
        foreach ($termValues['used_for'] as $usedFor) {
            $this->_usedForTerms[] = new TermSummary(
                $usedFor,
                $termManager
            );
        }
        foreach ($termValues['use'] as $use) {
            $this->_useTerms[] = new TermSummary(
                $use,
                $termManager
            );
        }
    }

    /**
     * Get the broader terms.
     *
     * @return array  An array of TermSummary objects.
     */
    public function getBroaderTerms()
    {
        return $this->_broaderTerms;
    }

    /**
     * Get the narrower terms.
     *
     * @return array  An array of TermSummary objects.
     */
    public function getNarrowerTerms()
    {
        return $this->_narrowerTerms;
    }

    /**
     * Get the related terms.
     *
     * @return array  An array of TermSummary objects.
     */
    public function getRelatedTerms()
    {
        return $this->_relatedTerms;
    }

    /**
     * Get the used for terms.
     *
     * @return array  An array of TermSummary objects.
     */
    public function getUsedForTerms()
    {
        return $this->_usedForTerms;
    }

    /**
     * Get the use terms.
     *
     * @return array  An array of TermSummary objects.
     */
    public function getUseTerms()
    {
        return $this->_useTerms;
    }

    /**
     * Return status as a string.
     *
     * @return string  One of this class's STATUS constants.
     */
    public function getStatus()
    {
        if ($this->getUseTerms()) {
            return self::STATUS_NONPREFERRED;
        }
        $allTerms = array_merge(
            $this->getBroaderTerms(),
            $this->getNarrowerTerms(),
            $this->getRelatedTerms(),
            $this->getUsedForTerms()
        );
        return $allTerms ? self::STATUS_PREFERRED : self::STATUS_UNSORTED;
    }

    /**
     * Return true if the term is a non-preferred term which may
     * describe any of multiple concepts.
     *
     * @return bool
     */
    public function isAmbiguous()
    {
        return count($this->getUseTerms()) > 1;
    }

    /**
     * Get the preferred term or terms for this term.
     *
     * If this is the preferred term, or if it is unsorted, return it.
     *
     * If this term is ambiguous, return an array of TermSummary objects.
     *
     * @return Term|array
     */
    public function getPreferred()
    {
        if ($this->getStatus() != self::STATUS_NONPREFERRED) {
            return $this;
        }
        $useTerms = $this->getUseTerms();
        if (count($useTerms) == 1) {
            return $this->_termManager->getTermByFolksaurusId($useTerms[0]->getId());
        }
        $preferredTerms = array();
        foreach ($useTerms as $useTerm) {
            $preferredTerms[] = $this->_termManager->getTermByFolksaurusId(
                $useTerm->getId()
            );
        }
        return $preferredTerms;
    }

    /**
     * Get the name.
     *
     * @return string
     */
    public function getName()
    {
        return $this->_name;
    }

    /**
     * Get the Folksaurus-assigned ID.
     *
     * @return int
     */
    public function getId()
    {
        return $this->_id;
    }

    /**
     * Get the scope note.
     *
     * @return string
     */
    public function getScopeNote()
    {
        return $this->_scopeNote;
    }

    /**
     * Get the ID assigned by your application.
     *
     * @return string
     */
    public function getAppId()
    {
        return $this->_appId;
    }

    /**
     * Get the timestamp from when the term details were last retrieved from Folksaurus.
     *
     * @return int
     */
    public function getLastRetrievedTime()
    {
        return $this->_lastRetrieved;
    }

    /**
     * Get the last retrieved timestamp as a datetime string.
     *
     * The datetime will use the current default timezone.
     *
     * @return string
     */
    public function getLastRetrievedDatetime()
    {
        return date('Y-m-d H:i:s', $this->_lastRetrieved);
    }

    /**
     * Set the ID assigned by your application.
     *
     * @param mixed $id
     */
    public function setAppId($id)
    {
        $this->_appId = $id;
    }

    /**
     * Update last retrieved time to the current time.
     */
    public function updateLastRetrievedTime()
    {
        $this->_lastRetrieved = time();
    }

    /**
     * Get the name.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->_name;
    }

}