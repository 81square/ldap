<?php

namespace Toyota\Component\Ldap\Platform\Native;

use Toyota\Component\Ldap\API\SearchInterface;
use Toyota\Component\Ldap\Exception\SearchException;

/**
 * Implementation of the search interface that allows searching using an iterator without loading all rows
 *
 * @author Eric D. Olson <eolson@barracuda.com>
 */
class SearchIterator implements SearchInterface
{
	protected $connection      = null;

	protected $isLastPage      = false;

	protected $pageSize        = 1000;

	protected $filter          = null;

	protected $baseDn          = null;

	protected $attributes      = [];

	protected $pageToken       = null;

	protected $currentPage     = 0;

	protected $entries         = [];

	protected $entriesPosition = 0;

	/**
	 * Default constructor
	 *
	 * @param resource $connection the connection to use to get the next page
	 * @param string   $baseDn     the base dn of the search
	 * @param string   $filter     the filter of the search
	 * @param array    $attributes names of attributes to retrieve
	 * @param int      $pageSize   the number of rows in a page
	 */
	public function __construct($connection, $baseDn, $filter, $attributes = null, $pageSize = 1000)
	{
		$this->connection = $connection;
		$this->pageSize = $pageSize;
		$this->baseDn = $baseDn;
		$this->filter = $filter;
		$this->attributes = $attributes;
		$this->isLastPage = false;
	}

	public function __destruct()
	{
		unset($this->connection);
	}

	protected function loadPage()
	{
		if (!ldap_control_paged_result($this->connection, $this->pageSize, true, $this->pageToken)) {
			throw new SearchException("Unable to set paged control pageSize: " . $this->pageSize);
		}

		$search = ldap_search($this->connection, $this->baseDn, $this->filter,
			is_array($this->attributes) ? $this->attributes : []);
		if (!$search) {
			// Something went wrong in search
			throw Connection::createLdapSearchException(ldap_errno($this->connection), $this->baseDn, $this->filter,
				$this->pageSize);
		}

		$this->entries = ldap_get_entries($this->connection, $search);
		$this->entriesPosition = 0;

		if (!$this->entries) {
			throw Connection::createLdapSearchException(ldap_errno($this->connection), $this->baseDn, $this->filter,
				$this->pageSize);
		}

		// check if on first page
		if (empty($this->pageToken)) {
			$this->currentPage = 0;
		} else {
			$this->currentPage++;
		}

		// Ok go to next page
		ldap_control_paged_result_response($this->connection, $search, $this->pageToken);

		if (empty($this->pageToken)) {
			$this->isLastPage = true;
		}
	}

	/**
	 * Retrieves next available entry from the search result set
	 *
	 * @return EntryInterface next entry if available, null otherwise
	 */
	public function next()
	{
		// get more entries if needed
		if (!isset($this->entries[$this->entriesPosition])) {
			// if the last page has loaded and the token is empty, end of the iterator
			if ($this->isLastPage && empty($this->pageToken)) {
				return null;
			}

			$this->loadPage();
		}

		// If now able to get the entry, then return it
		if (isset($this->entries[$this->entriesPosition])) {
			return new EntryPreloaded($this->entries[$this->entriesPosition++]);
		}

		return null;
	}

	/**
	 * Resets entry iterator
	 *
	 * @return void
	 */
	public function reset()
	{
		// if on a successive, reset the cookie and the entries cache
		if ($this->currentPage > 0) {
			$this->pageToken = null;
			$this->entries = [];
		}

		// start over
		$this->entriesPosition = 0;
		$this->isLastPage = false;
	}

	/**
	 * Frees memory for current result set
	 *
	 * @return void
	 */
	public function free()
	{
		unset($this->pageToken);
		unset($this->entries);
	}

}