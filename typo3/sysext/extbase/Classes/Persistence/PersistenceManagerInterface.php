<?php
namespace TYPO3\CMS\Extbase\Persistence;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

/**
 * The Extbase Persistence Manager interface
 */
interface PersistenceManagerInterface
{
    /**
     * Commits new objects and changes to objects in the current persistence
     * session into the backend
     *
     * @return void
     * @api
     */
    public function persistAll();

    /**
     * Clears the in-memory state of the persistence.
     *
     * Managed instances become detached, any fetches will
     * return data directly from the persistence "backend".
     *
     * @return void
     */
    public function clearState();

    /**
     * Checks if the given object has ever been persisted.
     *
     * @param object $object The object to check
     * @return bool TRUE if the object is new, FALSE if the object exists in the repository
     * @api
     */
    public function isNewObject($object);

    // @todo realign with Flow PersistenceManager again

    /**
     * Returns the (internal) identifier for the object, if it is known to the
     * backend. Otherwise NULL is returned.
     *
     * Note: this returns an identifier even if the object has not been
     * persisted in case of AOP-managed entities. Use isNewObject() if you need
     * to distinguish those cases.
     *
     * @param object $object
     * @return mixed The identifier for the object if it is known, or NULL
     * @api
     */
    public function getIdentifierByObject($object);

    /**
     * Returns the object with the (internal) identifier, if it is known to the
     * backend. Otherwise NULL is returned.
     *
     * @param mixed $identifier
     * @param string $objectType
     * @param bool $useLazyLoading Set to TRUE if you want to use lazy loading for this object
     * @return object The object for the identifier if it is known, or NULL
     * @api
     */
    public function getObjectByIdentifier($identifier, $objectType = null, $useLazyLoading = false);

    /**
     * Returns the number of records matching the query.
     *
     * @param QueryInterface $query
     * @return int
     * @deprecated since Extbase 6.0, will be removed in Extbase 7.0. It is deprecated only in the interface to be more
     * in sync with Flow in future and will stay in Generic Persistence.
     * @api
     */
    public function getObjectCountByQuery(QueryInterface $query);

    /**
     * Returns the object data matching the $query.
     *
     * @param QueryInterface $query
     * @return array
     * @deprecated since Extbase 6.0, will be removed in Extbase 7.0. It is deprecated only in the interface to be more
     * in sync with Flow in future and will stay in Generic Persistence.
     * @api
     */
    public function getObjectDataByQuery(QueryInterface $query);

    /**
     * Registers a repository
     *
     * @param string $className The class name of the repository to be registered
     * @deprecated since Extbase 6.0, will be removed in Extbase 7.0. It is deprecated only in the interface to be more
     * in sync with Flow in future and will stay in Generic Persistence.
     * @return void
     */
    public function registerRepositoryClassName($className);

    /**
     * Adds an object to the persistence.
     *
     * @param object $object The object to add
     * @return void
     * @api
     */
    public function add($object);

    /**
     * Removes an object to the persistence.
     *
     * @param object $object The object to remove
     * @return void
     * @api
     */
    public function remove($object);

    /**
     * Update an object in the persistence.
     *
     * @param object $object The modified object
     * @return void
     * @throws \TYPO3\CMS\Extbase\Persistence\Exception\UnknownObjectException
     * @api
     */
    public function update($object);

    /**
     * Injects the Extbase settings, called by Extbase.
     *
     * @param array $settings
     * @return void
     * @api
     */
    public function injectSettings(array $settings);

    /**
     * Converts the given object into an array containing the identity of the domain object.
     *
     * @param object $object The object to be converted
     * @return array The identity array in the format array('__identity' => '...')
     * @throws \TYPO3\CMS\Extbase\Persistence\Exception\UnknownObjectException if the given object is not known to the Persistence Manager
     * @api
     */
    public function convertObjectToIdentityArray($object);

    /**
     * Recursively iterates through the given array and turns objects
     * into arrays containing the identity of the domain object.
     *
     * @param array $array The array to be iterated over
     * @return array The modified array without objects
     * @throws \TYPO3\CMS\Extbase\Persistence\Exception\UnknownObjectException if array contains objects that are not known to the Persistence Manager
     * @api
     * @see convertObjectToIdentityArray()
     */
    public function convertObjectsToIdentityArrays(array $array);

    /**
     * Return a query object for the given type.
     *
     * @param string $type
     * @return QueryInterface
     * @api
     */
    public function createQueryForType($type);
}
