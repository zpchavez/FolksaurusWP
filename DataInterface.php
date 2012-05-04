<?php

/**
 * Implementation of Folksaurus\DataInterface
 */
class FolksaurusWPDataInterface implements Folksaurus\DataInterface
{

    public function deleteTerm($appId)
    {
        global $wpdb;

        $wpdb->update(
            FOLKSAURUS_TERM_DATA_TABLE,
            array('deleted' => 1),
            array('term_id' => $appId)
        );
    }

    public function getTermByAppId($appId)
    {
        return $this->_createTermArray($appId);
    }

    public function getTermByFolksaurusId($folksaurusId)
    {
        global $wpdb;

        $appId = $wpdb->get_var(
            $wpdb->prepare(
                'SELECT term_id FROM %s WHERE folksaurus_id = %d',
                FOLKSAURUS_TERM_DATA_TABLE,
                $folksaurusId
            )
        );
        if ($appId) {
            return $this->_createTermArray($appId);
        }
        return false;
    }

    public function getTermByName($name)
    {
        global $wpdb;

        $appId = $wpdb->get_var(
            $wpdb->prepare(
                'SELECT term_id FROM %s WHERE name = %s',
                $wpdb->terms,
                $name
            )
        );
        if ($appId) {
            return $this->_createTermArray($appId);
        }
        return false;
    }

    public function saveTerm(Folksaurus\Term $term)
    {
        
    }

    /**
     * Create the term array which must be returned by the getTerm methods.
     *
     * @param int $appId
     * @return array|bool   False if term not found.
     */
    protected function _createTermArray($appId)
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT
                    wp_terms.term_id,
                    name,
                    folkaurus_id,
                    scope_note,
                    deleted,
                    last_retrieved
                 FROM %s AS wp_terms
                 LEFT JOIN %s AS folk_terms
                 ON wp_terms.term_id = folk_terms.term_id
                 WHERE wp_terms.term_id = %d
                 LIMIT 1',
                 $wpdb->terms,
                 FOLKSAURUS_TERM_DATA_TABLE,
                 $appId
            ),
            ARRAY_A
        );

        if (!$row) {
            return false;
        }

        return array(
            'id'             => $row['folksaurus_id'],
            'name'           => $row['name'],
            'scope_note'     => $row['scope_note'],
            'broader'        => $this->_getBroaderTerms($row['term_id']),
            'narrower'       => $this->_getNarrowerTerms($row['term_id']),
            'related'        => $this->_getRelatedTerms($row['term_id']),
            'used_for'       => $this->_getUsedForTerms($row['term_id']),
            'use'            => $this->_getUseTerms($row['term_id']),
            'app_id'         => $row['term_id'],
            'last_retrieved' => strtotime($row['last_retrieved'] . ' UTC')
        );
    }

    /**
     * Get an array of arrays with IDs 'id' and 'name' containing
     * folksaurus_ids and names of the terms specified by $appIds.
     *
     * @param array $appIds
     */
    protected function _getTermSummaries(array $appIds)
    {
        global $wpdb;

        $summaries = array();
        foreach ($appIds as $appId) {
            $row = $wpdb->get_row(
                $wpdb->prepare(
                    'SELECT
                        term_id AS id,
                        name
                     FROM %s
                     WHERE term_id = %d',
                     $wpdb->terms,
                     $appId
                ),
                ARRAY_A
            );
            if ($row) {
                $summaries[] = $row;
            }
        }
        return $summaries;
    }

    /**
     * Get array of folksaurus_ids and names of related terms.
     *
     * @param type $appId
     * @return array  An array of arrays with keys 'id' and 'name'.
     */
    protected function _getRelatedTerms($appId)
    {
        global $wpdb;

        $appIds = $wpdb->get_col(
            $wpdb->prepare(
                'SELECT related_id AS term_id
                FROM %s
                WHERE term_id = %d
                AND type = "RT"
                UNION
                SELECT term_id
                FROM %s
                WHERE related_id = %d
                AND type = "RT"',
                FOLKSAURUS_TERM_REL_TABLE,
                $appId,
                FOLKSAURUS_TERM_REL_TABLE,
                $appId
            )
        );

        return $this->_getTermSummaries($appIds);
    }

    /**
     * Get array of folksaurus_ids and names of narrower terms.
     *
     * @param type $appId
     * @return array  An array of arrays with keys 'id' and 'name'.
     */
    protected function _getNarrowerTerms($appId)
    {
        global $wpdb;

        $appIds = $wpdb->get_col(
            $wpdb->prepare(
                'SELECT related_id AS term_id
                FROM %s
                WHERE term_id = %d
                AND type = "NT"',
                FOLKSAURUS_TERM_REL_TABLE,
                $appId
            )
        );

        return $this->_getTermSummaries($appIds);
    }

    /**
     * Get array of folksaurus_ids and names of broader terms.
     *
     * @param type $appId
     * @return array  An array of arrays with keys 'id' and 'name'.
     */
    protected function _getBroaderTerms($appId)
    {
        global $wpdb;

        $appIds = $wpdb->get_col(
            $wpdb->prepare(
                'SELECT term_id
                FROM %s
                WHERE related_id = %d
                AND type = "NT"',
                FOLKSAURUS_TERM_REL_TABLE,
                $appId
            )
        );

        return $this->_getTermSummaries($appIds);
    }

    /**
     * Get array of folksaurus_ids and names of "used for" terms.
     *
     * @param type $appId
     * @return array  An array of arrays with keys 'id' and 'name'.
     */
    protected function _getUsedForTerms($appId)
    {
        global $wpdb;

        $appIds = $wpdb->get_col(
            $wpdb->prepare(
                'SELECT related_id AS term_id
                FROM %s
                WHERE term_id = %d
                AND type = "UF"',
                FOLKSAURUS_TERM_REL_TABLE,
                $appId
            )
        );

        return $this->_getTermSummaries($appIds);
    }

    /**
     * Get array of folksaurus_ids and names of use terms.
     *
     * @param type $appId
     * @return array  An array of arrays with keys 'id' and 'name'.
     */
    protected function _getUseTerms($appId)
    {
        global $wpdb;

        $appIds = $wpdb->get_col(
            $wpdb->prepare(
                'SELECT term_id
                FROM %s
                WHERE related_id = %d
                AND type = "YF"',
                FOLKSAURUS_TERM_REL_TABLE,
                $appId
            )
        );

        return $this->_getTermSummaries($appIds);
    }

}
