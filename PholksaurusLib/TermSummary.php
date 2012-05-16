<?php
namespace Folksaurus;

/**
 * Term representation that includes only the name and ID.
 */
class TermSummary
{
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
     * A TermManager object, which is used to get info on related terms.
     *
     * @var TermManager
     */
    protected $_termManager;

    /**
     * Constructor.
     *
     * @param array $values  An array with keys "id" and "name".
     * @param TermManager $termManager
     */
    public function __construct(array $values, TermManager $termManager)
    {
        $this->_id   = $values['id'];
        $this->_name = $values['name'];
        $this->_termManager  = $termManager;
    }

    /**
     * Get a complete term info array for this term.
     *
     * @return Term
     */
    public function getCompleteTerm()
    {
        return $this->_termManager->getTermByFolksaurusId($this->_id);
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
     * Get the ID.
     *
     * @return int
     */
    public function getId()
    {
        return $this->_id;
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