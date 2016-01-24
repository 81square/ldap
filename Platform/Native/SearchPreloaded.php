<?php

/*
 * This file is part of the 81square/tiesa-ldap package
 *
 * (c) 81 Square <info@81square.nl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Toyota\Component\Ldap\Platform\Native;

use Toyota\Component\Ldap\API\SearchInterface;

/**
 * Implementation of the search interface for php ldap extension that must be
 * preloaded with entries (typically with ldap_get_entries). 
 *
 * @author Maarten van Zanten <info@81square.nl>
 */
class SearchPreloaded implements SearchInterface
{

    protected $entries = null;
    protected $currIndex = -1;
    protected $isEndReached = false;

    /**
     * 
     * @param array $entries Initial set of entries
     */
    public function __construct(array $entries = array())
    {
        $this->entries = null;
        $this->currIndex = -1;
        $this->isEndReached = false;
        
        if (!empty($entries)) {
            $this->addEntries($entries);
        }
    }
    
    /**
     * Adds a set of entries and resets index
     * 
     * @param array $entries Set of entries
     * 
     * @throws \InvalidArgumentException On invalid entries set
     */
    public function addEntries(array $entries)
    {
        if (!isset($entries['count'])) {
            throw new \InvalidArgumentException("Entry set must have count property!");
        }
        
        if (null === $this->entries) {
            $this->entries = $entries;
            // reset
            $this->reset();
            return;
        }
        
        // Existing entries, we must append
        $newCount = $this->entries['count'] + $entries['count'];
        
        // Merge and reset count field
        $this->entries = array_merge($this->entries, $entries);
        $this->entries['count'] = $newCount;
        
        // reset
        $this->reset();
    }
    
    /**
     * Gets total number of entries
     * 
     * @return int Number of entries
     */
    public function count()
    {
        if (!$this->entries || !isset($this->entries['count'])) {
            return 0;
        }
        
        return (int)($this->entries['count']);
    }

    /**
     * Retrieves next available entry from the search result set
     *
     * @return EntryInterface next entry if available, null otherwise
     */
    public function next()
    {
        if (!$this->entries || $this->isEndReached) {
            return null;
        }
        
        // Increase index (if it was -1, it will become 0, our first item)
        $this->currIndex++;
        
        if ($this->currIndex >= $this->count()) {
            // Reached the end
            $this->isEndReached = true;
            return null;
        }
        
        return new EntryPreloaded($this->entries[$this->currIndex]);
    }

    /**
     * Resets entry iterator
     *
     * @return void
     */
    public function reset()
    {
        $this->previous = null;
        $this->isEndReached = false;
        $this->currIndex = -1;
    }

    /**
     * Frees memory for current result set
     *
     * @return void
     */
    public function free()
    {
        $this->entries = null;
    }

}
