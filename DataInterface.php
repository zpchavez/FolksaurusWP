<?php
namespace FolksaurusWP;

/**
 * Implementation of PholksaurusLib\DataInterface
 */
class DataInterface implements \PholksaurusLib\DataInterface
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
                'SELECT term_id FROM ' . FOLKSAURUS_TERM_DATA_TABLE .
                ' WHERE folksaurus_id = %d',
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
                'SELECT term_id FROM ' . $wpdb->terms . ' WHERE name = %s',
                $name
            )
        );
        if ($appId) {
            return $this->_createTermArray($appId);
        }
        return false;
    }

    public function saveTerm(\PholksaurusLib\Term $term)
    {
        global $wpdb;

        $wpdb->hide_errors();

        if ($term->getAppId()) {
            $appId = $term->getAppId();
            $wasPreferred = $wpdb->get_var(
                $wpdb->prepare(
                    'SELECT preferred FROM ' . FOLKSAURUS_TERM_DATA_TABLE .
                    ' WHERE term_id = %d',
                    $appId
                )
            );
        } else {
            $wasPreferred = false;
        }

        $this->_updateTermInWpTerms($term);

        $this->_updateOrInsertTermInFolksaurusTermData($term);

        $this->_saveRelationships($term);

        $this->_updateTermToObjectRelationshipsIfNecessary($term, $wasPreferred);

    }

    /**
     * Update a term in wp_terms.
     *
     * @todo delete
     *
     * @param PholksaurusLib\Term $term
     */
    protected function _updateTermInWpTerms(\PholksaurusLib\Term $term)
    {
        global $wpdb;

        // Deal with possible slug collisions.
        $baseSlug = sanitize_title($term->getName());
        $slug = $baseSlug;
        $slugIndex = 0;

        // Update or insert term in wp_terms
        $appId = $term->getAppId();
        if ($appId) {
            $updated = false;
            while ($updated === false && $slugIndex < 10) {
                $updated = $wpdb->update(
                    $wpdb->terms,
                    array(
                        'name' => $term->getName(),
                        'slug' => $slug
                    ),
                    array('term_id' => $appId)
                );
                $slug = $baseSlug . $slugIndex;
                $slugIndex += 1;
            }
            if (!$updated) {
                return false;
            }
        }
    }

    /**
     * Update or insert term data in the folksaurus term data table.
     *
     * @param PholksaurusLib\Term $term
     * @return bool
     */
    protected function _updateOrInsertTermInFolksaurusTermData(\PholksaurusLib\Term $term)
    {
        global $wpdb;

        // Update or insert item in the folksaurus term data table.
        $insertedOrUpdated = $wpdb->query(
            $wpdb->prepare(
                'REPLACE INTO ' . FOLKSAURUS_TERM_DATA_TABLE . ' (
                    term_id,
                    folksaurus_id,
                    scope_note,
                    last_retrieved,
                    preferred,
                    ambiguous,
                    deleted
                ) VALUES (%d, %d, %s, %s, %d, %d, %d)',
                $term->getAppId(),
                $term->getId(),
                $term->getScopeNote(),
                $term->getLastRetrievedDatetime(),
                $term->getStatus() != \PholksaurusLib\Term::STATUS_NONPREFERRED,
                $term->isAmbiguous(),
                false
            )
        );
        return $insertedOrUpdated;
    }

    /**
     * Insert a placeholder term if the term does not already exist.
     *
     * @param \PholksaurusLib\TermSummary $termSummary
     * @param string $taxonomy  The taxonomy to which the placeholder term will be assigned.
     * @return int|bool  The app_id, or false if unable to create the term.
     */
    protected function _insertTermPlaceholderIfNotExists(\PholksaurusLib\TermSummary $termSummary,
                                                         $taxonomy)
    {
        global $wpdb;

        $appId = $wpdb->get_var(
            $wpdb->prepare(
                'SELECT term_id FROM ' . FOLKSAURUS_TERM_DATA_TABLE .
                ' where folksaurus_id = %d',
                $termSummary->getId()
            )
        );
        if (!$appId) {
            $appId = $wpdb->get_var(
                $wpdb->prepare(
                    'SELECT term_id FROM ' . $wpdb->terms .
                    ' WHERE name = %s',
                    $termSummary->getName()
                )
            );
        }
        if (!$appId) {
            $termIds = wp_insert_term($termSummary->getName(), $taxonomy);
            if ($termIds) {
                $appId = $termIds['term_id'];
            }
        }
        if ($appId) {
            $wpdb->insert(
                FOLKSAURUS_TERM_DATA_TABLE,
                array(
                    'term_id'        => $appId,
                    'folksaurus_id'  => $termSummary->getId(),
                    'last_retrieved' => 0,
                )
            );
        }
        return $appId;
    }

    /**
     * Save the term relationships for $term in the folksaurus term relationship table.
     *
     * Create placeholder terms for any terms not in wp_terms.
     *
     * @param \PholksaurusLib\Term $term
     */
    protected function _saveRelationships(\PholksaurusLib\Term $term)
    {
        global $wpdb;

        // First clear existing relationships.
        $wpdb->query(
            $wpdb->prepare(
                'DELETE FROM ' . FOLKSAURUS_TERM_REL_TABLE .
                ' WHERE term_id = %d OR related_id = %d',
                $term->getAppId(),
                $term->getAppId()
            )
        );

        // Need to know which taxonomy to assign new terms.
        // Should be the same as the term being saved.
        $taxonomy = $wpdb->get_var(
            $wpdb->prepare(
                'SELECT taxonomy FROM ' . $wpdb->term_taxonomy . ' WHERE term_id = %d',
                $term->getAppId()
            )
        );
        if (!$taxonomy) {
            return;
        }

        foreach ($term->getBroaderTerms() as $broader) {
            $appId = $this->_insertTermPlaceholderIfNotExists($broader, $taxonomy);
            if ($appId) {
                $wpdb->insert(
                    FOLKSAURUS_TERM_REL_TABLE,
                    array(
                        'term_id'    => $appId,
                        'rel_type'   => 'NT',
                        'related_id' => $term->getAppId()
                    )
                );
                // Set parent IDs.
                $wpdb->update(
                    $wpdb->term_taxonomy,
                    array('parent' => $appId),
                    array('term_id' => $term->getAppId())
                );
            }
        }

        foreach ($term->getNarrowerTerms() as $narrower) {
            $appId = $this->_insertTermPlaceholderIfNotExists($narrower, $taxonomy);
            if ($appId) {
                $wpdb->insert(
                    FOLKSAURUS_TERM_REL_TABLE,
                    array(
                        'term_id'    => $term->getAppId(),
                        'rel_type'   => 'NT',
                        'related_id' => $appId
                    )
                );
                // Set parent IDs.
                $wpdb->update(
                    $wpdb->term_taxonomy,
                    array('parent' => $term->getAppId()),
                    array('term_id' => $appId)
                );
            }
        }

        foreach ($term->getUsedForTerms() as $usedFor) {
            $appId = $this->_insertTermPlaceholderIfNotExists($usedFor, $taxonomy);
            if ($appId) {
                $wpdb->insert(
                    FOLKSAURUS_TERM_REL_TABLE,
                    array(
                        'term_id'    => $term->getAppId(),
                        'rel_type'   => 'UF',
                        'related_id' => $appId
                    )
                );
            }
        }

        foreach ($term->getUseTerms() as $use) {
            $appId = $this->_insertTermPlaceholderIfNotExists($use, $taxonomy);
            if ($appId) {
                $wpdb->insert(
                    FOLKSAURUS_TERM_REL_TABLE,
                    array(
                        'term_id'    => $appId,
                        'rel_type'   => 'UF',
                        'related_id' => $term->getAppId()
                    )
                );
            }
        }

        foreach ($term->getRelatedTerms() as $related) {
            $appId = $this->_insertTermPlaceholderIfNotExists($related, $taxonomy);
            if ($appId) {
                // Just to be consistent, set the lower ID as term_id.
                $wpdb->insert(
                    FOLKSAURUS_TERM_REL_TABLE,
                    array(
                        'term_id'    => min($appId, $term->getAppId()),
                        'rel_type'   => 'RT',
                        'related_id' => max($appId, $term->getAppId())
                    )
                );
            }
        }

    }

    /**
     * If term has become non-preferred, update objects related to the term to
     * instead relate to the preferred term.
     *
     * @param \PholksaurusLib\Term $term
     * @param bool $wasPreferred  Whether the term was preferred before the changes.
     */
    protected function _updateTermToObjectRelationshipsIfNecessary(\PholksaurusLib\Term $term,
                                                                   $wasPreferred)
    {
        global $wpdb;

        $isNonPreferred = $term->getStatus() == \PholksaurusLib\Term::STATUS_NONPREFERRED;

        if ($wasPreferred && !$term->isAmbiguous() && $isNonPreferred) {
            $oldTermTaxonomyId = $wpdb->get_var(
                $wpdb->prepare(
                    'SELECT term_taxonomy_id FROM ' . $wpdb->term_taxonomy . ' WHERE term_id = %d',
                    $term->getAppId()
                )
            );
            $preferredTerm = $term->getPreferred();
            if (!$preferredTerm) {
                return;
            }
            $newTermTaxonomyId = $wpdb->get_var(
                $wpdb->prepare(
                    'SELECT term_taxonomy_id FROM ' . $wpdb->term_taxonomy . ' WHERE term_id = %d',
                    $preferredTerm->getAppId()
                )
            );
            if ($oldTermTaxonomyId && $newTermTaxonomyId) {
                $wpdb->update(
                    $wpdb->term_relationships,
                    array('term_taxonomy_id' => $newTermTaxonomyId),
                    array('term_taxonomy_id' => $oldTermTaxonomyId)
                );
            }
        }
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
                    folksaurus_id,
                    scope_note,
                    deleted,
                    IFNULL(last_retrieved, 0) AS last_retrieved
                 FROM ' . $wpdb->terms . ' AS wp_terms
                 LEFT JOIN ' . FOLKSAURUS_TERM_DATA_TABLE . ' AS folk_terms
                 ON wp_terms.term_id = folk_terms.term_id
                 WHERE wp_terms.term_id = %d
                 LIMIT 1',
                 $appId
            ),
            ARRAY_A
        );

        if (!$row) {
            return false;
        }

        $termArray = array(
            'id'             => $row['folksaurus_id'],
            'name'           => html_entity_decode($row['name']),
            'scope_note'     => $row['scope_note'],
            'broader'        => $this->_getBroaderTerms($row['term_id']),
            'narrower'       => $this->_getNarrowerTerms($row['term_id']),
            'related'        => $this->_getRelatedTerms($row['term_id']),
            'used_for'       => $this->_getUsedForTerms($row['term_id']),
            'use'            => $this->_getUseTerms($row['term_id']),
            'app_id'         => $row['term_id'],
            'last_retrieved' => strtotime($row['last_retrieved'] . ' UTC')
        );
        return $termArray;
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
     * @param int $appId
     * @return array  An array of arrays with keys 'id' and 'name'.
     */
    protected function _getRelatedTerms($appId)
    {
        global $wpdb;

        $appIds = $wpdb->get_col(
            $wpdb->prepare(
                'SELECT related_id AS term_id
                FROM ' . FOLKSAURUS_TERM_REL_TABLE . '
                WHERE term_id = %d
                AND rel_type = "RT"
                UNION
                SELECT term_id
                FROM ' . FOLKSAURUS_TERM_REL_TABLE . '
                WHERE related_id = %d
                AND rel_type = "RT"',
                $appId,
                $appId
            )
        );

        return $this->_getTermSummaries($appIds);
    }

    /**
     * Get array of folksaurus_ids and names of narrower terms.
     *
     * @param int $appId
     * @return array  An array of arrays with keys 'id' and 'name'.
     */
    protected function _getNarrowerTerms($appId)
    {
        global $wpdb;

        $appIds = $wpdb->get_col(
            $wpdb->prepare(
                'SELECT related_id AS term_id
                FROM ' . FOLKSAURUS_TERM_REL_TABLE . '
                WHERE term_id = %d
                AND rel_type = "NT"',
                $appId
            )
        );

        return $this->_getTermSummaries($appIds);
    }

    /**
     * Get array of folksaurus_ids and names of broader terms.
     *
     * @param int $appId
     * @return array  An array of arrays with keys 'id' and 'name'.
     */
    protected function _getBroaderTerms($appId)
    {
        global $wpdb;

        $appIds = $wpdb->get_col(
            $wpdb->prepare(
                'SELECT term_id
                FROM ' . FOLKSAURUS_TERM_REL_TABLE . '
                WHERE related_id = %d
                AND rel_type = "NT"',
                $appId
            )
        );

        return $this->_getTermSummaries($appIds);
    }

    /**
     * Get array of folksaurus_ids and names of "used for" terms.
     *
     * @param int $appId
     * @return array  An array of arrays with keys 'id' and 'name'.
     */
    protected function _getUsedForTerms($appId)
    {
        global $wpdb;

        $appIds = $wpdb->get_col(
            $wpdb->prepare(
                'SELECT related_id AS term_id
                FROM ' . FOLKSAURUS_TERM_REL_TABLE . '
                WHERE term_id = %d
                AND rel_type = "UF"',
                $appId
            )
        );

        return $this->_getTermSummaries($appIds);
    }

    /**
     * Get array of folksaurus_ids and names of use terms.
     *
     * @param int $appId
     * @return array  An array of arrays with keys 'id' and 'name'.
     */
    protected function _getUseTerms($appId)
    {
        global $wpdb;

        $appIds = $wpdb->get_col(
            $wpdb->prepare(
                'SELECT term_id
                FROM ' . FOLKSAURUS_TERM_REL_TABLE . '
                WHERE related_id = %d
                AND rel_type = "YF"',
                $appId
            )
        );

        return $this->_getTermSummaries($appIds);
    }

}
