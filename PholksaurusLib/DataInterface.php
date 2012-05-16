<?php
namespace Folksaurus;

/**
 * An interface for working with the Folksaurus term data in your own database.
 */
interface DataInterface
{
    /**
     * Retrieve a term by the ID assigned to it by your application.
     *
     * @param string $id
     * @return array  A term info array suitable for passing into the Term constructor,
     *                or false if not found.
     */
    public function getTermByAppId($id);

    /**
     * Retrieve a term by its Folksaurus-assigned ID.
     *
     * @param int $id
     * @return array  A term info array suitable for passing into the Term constructor,
     *                or false if not found.
     */
    public function getTermByFolksaurusId($id);

    /**
     * Retrieve a term by its name.
     *
     * @param string $name
     * @return array  A term info array suitable for passing into the Term constructor,
     *                or false if not found.
     */
    public function getTermByName($name);

    /**
     * Save a term to your database.
     *
     * The term may be new, or it may be an update of a term
     * that already exists in your database.  The app_id
     * will be set if the term already exists.
     *
     * See the README for more details on implementing this method.
     *
     * @param Term $term
     */
    public function saveTerm(Term $term);

    /**
     * Flag a term as deleted.
     *
     * @param mixed $appId  The ID assigned to the term by your application.
     */
    public function deleteTerm($appId);

}
