<?php

/*
 * This file is part of the Toyota Legacy PHP framework package.
 *
 * (c) Toyota Industrial Equipment <cyril.cottet@toyota-industries.eu>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Toyota\Component\Ldap\Platform\Native;

use Toyota\Component\Ldap\Exception\ConnectionException;
use Toyota\Component\Ldap\Exception\OptionException;
use Toyota\Component\Ldap\Exception\BindException;
use Toyota\Component\Ldap\Exception\PersistenceException;
use Toyota\Component\Ldap\Exception\NoResultException;
use Toyota\Component\Ldap\Exception\SizeLimitException;
use Toyota\Component\Ldap\Exception\MalformedFilterException;
use Toyota\Component\Ldap\Exception\SearchException;
use Toyota\Component\Ldap\API\SearchInterface;
use Toyota\Component\Ldap\API\ConnectionInterface;

/**
 * Connection implementing interface for php ldap native extension
 *
 * @author Cyril Cottet <cyril.cottet@toyota-industries.eu>
 */
class Connection implements ConnectionInterface
{

    protected $connection = null;

    /**
     * Default constructor for native connection
     *
     * @param resource $id Link resource identifier for Ldap Connection
     *
     * @return Connection
     */
    public function __construct($id)
    {
        $this->connection = $id;
    }

    /**
     * Set an option
     *
     * @param int   $option Ldap option name
     * @param mixed $value  Value to set on Ldap option
     *
     * @return void
     *
     * @throws OptionException if option cannot be set
     */
    public function setOption($option, $value)
    {
        if (!(@ldap_set_option($this->connection, $option, $value))) {
            $code = @ldap_errno($this->connection);
                throw new OptionException(
                sprintf(
                        'Could not change option %s value: Ldap Error Code=%s - %s', $option, $code, ldap_err2str($code)
                ), $code
            );
        }
    }

    /**
     * Gets current value set for an option
     *
     * @param int $option Ldap option name
     *
     * @return mixed value set for the option
     *
     * @throws OptionException if option cannot be retrieved
     */
    public function getOption($option)
    {
        $value = null;
        if (!(@ldap_get_option($this->connection, $option, $value))) {
            $code = @ldap_errno($this->connection);
                throw new OptionException(
                sprintf(
                        'Could not retrieve option %s value: Ldap Error Code=%s - %s', $option, $code, ldap_err2str($code)
                ), $code
            );
        }
        return $value;
    }

    /**
     * Binds to the LDAP directory with specified RDN and password
     *
     * @param string   $rdn        Rdn to use for binding (Default: null)
     * @param string   $password   Plain or hashed password for binding (Default: null)
     *
     * @return void
     *
     * @throws BindException if binding fails
     */
    public function bind($rdn = null, $password = null)
    {
        $isAnonymous = false;
        if ((null === $rdn) || (null === $password)) {
            if ((null !== $rdn) || (null !== $password)) {
                throw new BindException(
                'For an anonymous binding, both rdn & passwords have to be null'
                );
            }
            $isAnonymous = true;
        }

        if (!(@ldap_bind($this->connection, $rdn, $password))) {
            $code = @ldap_errno($this->connection);
            $err = sprintf(
                    'Could not bind %s user: Ldap Error Code=%s - %s', 
                    $isAnonymous ? 'anonymous' : 'privileged', 
                    $code, 
                    ldap_err2str($code)
                );

            throw new BindException($err, $code);
        }
    }

    /**
     * Closes the connection
     *
     * @return void
     *
     * @throws ConnectionException if connection could not be closed
     */
    public function close()
    {
        if (!(@ldap_unbind($this->connection))) {
            $code = @ldap_errno($this->connection);
            throw new ConnectionException(
                sprintf(
                        'Could not close the connection: Ldap Error Code=%s - %s', $code, ldap_err2str($code)
                ), $code
            );
        }

        $this->connection = null;
    }

    /**
     * Adds a Ldap entry
     *
     * @param string $dn   Distinguished name to register entry for
     * @param array  $data Ldap attributes to save along with the entry
     *
     * @return void
     *
     * @throws PersistenceException if entry could not be added
     */
    public function addEntry($dn, $data)
    {
        $data = $this->normalizeData($data);

        if (!(@ldap_add($this->connection, $dn, $data))) {
            $code = @ldap_errno($this->connection);
            throw new PersistenceException(
                sprintf(
                        'Could not add entry %s: Ldap Error Code=%s - %s', $dn, $code, ldap_err2str($code)
                ), $code
            );
        }
    }

    /**
     * Deletes an existing Ldap entry
     *
     * @param string $dn Distinguished name of the entry to delete
     *
     * @return void
     *
     * @throws PersistenceException if entry could not be deleted
     */
    public function deleteEntry($dn)
    {
        if (!(@ldap_delete($this->connection, $dn))) {
            $code = @ldap_errno($this->connection);
            throw new PersistenceException(
                sprintf(
                        'Could not delete entry %s: Ldap Error Code=%s - %s', $dn, $code, ldap_err2str($code)
                ), $code
            );
        }
    }

    /**
     * Adds some value(s) to some entry attribute(s)
     *
     * The data format for attributes is as follows:
     *     array(
     *         'attribute_1' => array(
     *             'value_1',
     *             'value_2'
     *          ),
     *         'attribute_2' => array(
     *             'value_1',
     *             'value_2'
     *          ),
     *          ...
     *     );
     *
     * @param string $dn   Distinguished name of the entry to modify
     * @param array  $data Values to be added for each attribute
     *
     * @return void
     *
     * @throws PersistenceException if entry could not be updated
     */
    public function addAttributeValues($dn, $data)
    {
        $data = $this->normalizeData($data);

        if (!(@ldap_mod_add($this->connection, $dn, $data))) {
            $code = @ldap_errno($this->connection);
            throw new PersistenceException(
                sprintf(
                        'Could not add attribute values for entry %s: Ldap Error Code=%s - %s', $dn, $code, ldap_err2str($code)
                ), $code
            );
        }
    }

    /**
     * Replaces value(s) for some entry attribute(s)
     *
     * The data format for attributes is as follows:
     *     array(
     *         'attribute_1' => array(
     *             'value_1',
     *             'value_2'
     *          ),
     *         'attribute_2' => array(
     *             'value_1',
     *             'value_2'
     *          ),
     *          ...
     *     );
     *
     * @param string $dn   Distinguished name of the entry to modify
     * @param array  $data Values to be set for each attribute
     *
     * @return void
     *
     * @throws PersistenceException if entry could not be updated
     */
    public function replaceAttributeValues($dn, $data)
    {
        $data = $this->normalizeData($data);

        if (!(@ldap_mod_replace($this->connection, $dn, $data))) {
            $code = @ldap_errno($this->connection);
            throw new PersistenceException(
                sprintf(
                        'Could not replace attribute values for entry %s: Ldap Error Code=%s - %s', $dn, $code, ldap_err2str($code)
                ), $code
            );
        }
    }

    /**
     * Delete value(s) for some entry attribute(s)
     *
     * The data format for attributes is as follows:
     *     array(
     *         'attribute_1' => array(
     *             'value_1',
     *             'value_2'
     *          ),
     *         'attribute_2' => array(
     *             'value_1',
     *             'value_2'
     *          ),
     *          ...
     *     );
     *
     * @param string $dn   Distinguished name of the entry to modify
     * @param array  $data Values to be removed for each attribute
     *
     * @return void
     *
     * @throws PersistenceException if entry could not be updated
     */
    public function deleteAttributeValues($dn, $data)
    {
        $data = $this->normalizeData($data);

        if (!(@ldap_mod_del($this->connection, $dn, $data))) {
            $code = @ldap_errno($this->connection);
            throw new PersistenceException(
                sprintf(
                        'Could not delete attribute values for entry %s: Ldap Error Code=%s - %s', $dn, $code, ldap_err2str($code)
                ), $code
            );
        }
    }

    /**
     * Searches for entries in the directory
     *
     * @param int     $scope      Search scope (ALL, ONE or BASE)
     * @param string  $baseDn     Base distinguished name to look below
     * @param string  $filter     Filter for the search
     * @param array   $attributes Names of attributes to retrieve (Default: All)
     * @param int     $pageSize   Page size (Default: 0, no paging)
     * 
     * @return SearchInterface Search result set
     *
     * @throws NoResultException if no result can be retrieved
     * @throws SizeLimitException if size limit got exceeded
     * @throws MalformedFilterException if filter is wrongly formatted
     * @throws SearchException if search failed otherwise
     */
    public function search($scope, $baseDn, $filter, $attributes = null, $pageSize = 0)
    {
        // For now we only support paging on search
        if ($pageSize > 0 && $scope === SearchInterface::SCOPE_ALL) {
            // Do paged search
            return $this->searchLdapPaged($pageSize, $baseDn, $filter, $attributes);
        }

        // Ok regular 'search' with no paging
        switch ($scope) {
            case SearchInterface::SCOPE_BASE:
                $function = 'ldap_read';
                break;
            case SearchInterface::SCOPE_ONE:
                $function = 'ldap_list';
                $pageSize = 0;
                break;
            case SearchInterface::SCOPE_ALL:
                $function = 'ldap_search';
                break;
            default:
                throw new SearchException(sprintf('Scope %s not supported', $scope));
        }

        $params = array($this->connection, $baseDn, $filter);
        if (is_array($attributes)) {
            $params[] = $attributes;
        }

        $search = @call_user_func_array($function, $params);
        if (false === $search) {
            // Something went wrong
            throw $this->createLdapSearchException(@ldap_errno($this->connection), $baseDn, $filter);
        }

        return new Search($this->connection, $search);
    }

    /**
     * Performs multiple ldap_search calls to retrieve multiple pages of entries
     * 
     * @param int     $pageSize   Page size
     * @param string  $baseDn     Base distinguished name to look below
     * @param string  $filter     Filter for the search
     * @param array   $attributes Names of attributes to retrieve (Default: All)
     * 
     * @return SearchInterface
     */
    protected function searchLdapPaged($pageSize, $baseDn, $filter, $attributes = null)
    {
        $cookie = '';
        $allResults = new SearchPreloaded();

        do {
            if (!ldap_control_paged_result($this->connection, $pageSize, true, $cookie)) {
                throw new SearchException("Unable to set paged control pageSize: ".$pageSize);
            }

            $search = ldap_search($this->connection, $baseDn, $filter, is_array($attributes) ? $attributes : array());
            if (!$search) {
                // Something went wrong in search
                throw $this->createLdapSearchException(ldap_errno($this->connection), $baseDn, $filter, $pageSize);
            }

            $entries = ldap_get_entries($this->connection, $search);
            if(!$entries) {
                // No entries?
                throw $this->createLdapSearchException(ldap_errno($this->connection), $baseDn, $filter, $pageSize);
            }
            
            // Ok add all entries
            $allResults->addEntries($entries);

            // Ok go to next page
            ldap_control_paged_result_response($this->connection, $search, $cookie);
        } while ($cookie !== null && $cookie != '');

        return $allResults;
    }

    /**
     * Create an exception for ldap error code
     * 
     * @param int     $code     LDAP error code
     * @param string  $baseDn   Base distinguished name to look below
     * @param string  $filter   Filter for the search
     * @param int     $pageSize PageSize
     * @return \Toyota\Component\Ldap\Exception\SizeLimitException|\Toyota\Component\Ldap\Exception\NoResultException|\Toyota\Component\Ldap\Exception\MalformedFilterException|\Toyota\Component\Ldap\Exception\SearchException
     */
    protected function createLdapSearchException($code, $baseDn, $filter, $pageSize = 0)
    {
        switch ($code) {

            case 32:
                return new NoResultException('No result retrieved for the given search', $code);
            case 4:
                return new SizeLimitException(
                        'Size limit reached while performing the expected search', $code
                );
            case 87:
                return new MalformedFilterException(
                        sprintf('Search for filter %s fails for a malformed filter', $filter), $code
                );
            default:
                return new SearchException(
                        sprintf(
                                'Search on %s with filter %s failed. PageSize: %s, Ldap Error Code:%s - %s', 
                                $baseDn, 
                                $filter, 
                                $pageSize,
                                $code, 
                                ldap_err2str($code)
                        ), $code
                );
        }
    }

    /**
     * Normalizes data for Ldap storage
     *
     * @param array $data Ldap data to store
     *
     * @return array Normalized data
     */
    protected function normalizeData($data)
    {
        foreach ($data as $attribute => $info) {
            if (is_array($info) && count($info) == 1) {
                $data[$attribute] = $info[0];
                continue;
            }
        }
        return $data;
    }

}
