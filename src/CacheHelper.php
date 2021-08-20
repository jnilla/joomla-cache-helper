<?php
namespace Jnilla\Joomla;

defined('_JEXEC') or die();

use Joomla\CMS\Factory as JFactory;
use Joomla\CMS\Cache\Cache as JCache;

class CacheHelper{
	/**
	 * Proxy fuction to get a cache item or update its content using a callback to finally get it's content in either case
	 *
	 * @param    string      $id                Cache item id
	 * @param    string      $group             Cache group name
	 * @param    callable    $callback          Callback function that must return the data to store
	 * @param    integer     $lifetime          Time in second before cache item is flagged as stale. If value
	 *                                          is null Joomla global configuration cache time will be used instead.
	 * @param    boolean     $waitIfUpdating    Force current operation to wait if the updating flag is true
	 * @param    integer     $maxWait           Max time in seconds to wait if updating flag is true
	 * @param    boolean     $waitIfUndefined    Force current operation to wait if the updating flag is true
	 *                                           and current cache item is undefined (doesn't exist).
	 *
	 * @return   array       Array with cache data and some flags
	 */
	static function proxy($id, $group, $callback, $lifetime = null, $waitIfUpdating = true, $maxWait = 3, $waitIfUndefined = true){
		// Get cached data
		$data = CacheHelper::get($id, $group, $waitIfUpdating, $maxWait, $waitIfUndefined);

		// Return data if cache item is defined and is not stale
		if(!$data['isUndefined'] && !$data['isStale']) return $data;

		// Check if we can return data while updating
		if(self::getUpdatingFlag($id, $group)){
			// Return data if $waitIfUpdating or $waitIfUndefined are false
			if((!$waitIfUpdating || !$waitIfUndefined)) return $data;
		}

		// Flag cache as updating
		self::setUpdatingFlag($id, $group, true);

		// Execute callback (AKA expensive operation)
		$data = $callback();

		// Set cache data
		CacheHelper::set($id, $group, $data, $lifetime);

		// Return cache item
		return CacheHelper::get($id, $group, $waitIfUpdating, $maxWait, $waitIfUndefined);
	}

	/**
	 * Get the cache item
	 *
	 * @param    string      $id                 Cache item id
	 * @param    string      $group              Cache group name
	 * @param    boolean     $waitIfUpdating     Force current operation to wait if the updating flag is true
	 * @param    integer     $maxWait            Max time in seconds to wait if updating flag is true
	 * @param    boolean     $waitIfUndefined    Force current operation to wait if the updating flag is true
	 *                                           and current cache item is undefined (doesn't exist).
	 *
	 * @return   array       Array with cache data and some flags
	 */
	static function get($id, $group, $waitIfUpdating = true, $maxWait = 3, $waitIfUndefined = true){
		// Define default cache item structure
		$cacheItem = [
			'isUndefined' => true,
			'isStale' => false,
			'isUpdating' => false,
			'data' => ''
		];

		// If updating flag is true apply a delay if required
		// Note: Don't add code before this statement because its values may change after wait time.
		if(($waitIfUpdating || ($waitIfUndefined && self::isUndefined($id, $group))) && self::getUpdatingFlag($id, $group)){
			$startTime = microtime(true); // DEBUG
			// YOUR CODE HERE
			for ($i = 0; $i <= $maxWait*10; $i++){
				usleep(100000);
				if(!self::getUpdatingFlag($id, $group)) break;
			}
			// If max wait time is reached force updating flag to false
			self::setUpdatingFlag($id, $group, false);
		}

		// Get Joomla cache instance to store lifetime variable with a lifetime of 5 years
		$cacheLifetime = new JCache(array('caching' => true, 'defaultgroup' => $group, 'lifetime' => 2628000));

		// Get lifetime
		$lifetime = intval($cacheLifetime->get("CacheHelper-Lifetime-$group-$id"));

		// Get Joomla cache instance to store the dummy data with a custom lifetime
		$cacheDummyData = new JCache(array('caching' => true, 'defaultgroup' => $group, 'lifetime' => $lifetime/60));

		// If cache item is defined and dummy data doen't exist then considered the cache item as stale.
		// Note: Something that doesn't exist can't be considered as stale
		if(!self::isUndefined($id, $group) && !$cacheDummyData->contains("CacheHelper-DummyData-$group-$id")) $cacheItem['isStale'] = true;

		// Save cache data if exist
		if(!self::isUndefined($id, $group)){
			// Get Joomla cache instance to store the data with a lifetime of 5 years
			$cacheData = new JCache(array('caching' => true, 'defaultgroup' => $group, 'lifetime' => 2628000));

			// Save cache data
			$cacheItem['data'] = $cacheData->get($id);
		}

		// Save undefined flag
		$cacheItem['isUndefined'] = self::isUndefined($id, $group);

		// Save updating flag value
		$cacheItem['isUpdating'] = self::getUpdatingFlag($id, $group);

		return $cacheItem;
	}

	/**
	 * Set cache item data
	 *
	 * @param    string     $id          Cache item id
	 * @param    string     $group       Cache group name
	 * @param    string     $data        Data to cache (String only)
	 * @param    integer    $lifetime    Time in second before cache item is flagged as stale. If value
	 *                                   is null Joomla global configuration cache time will be used instead.
	 * @return    void
	 */
	static function set($id, $group, $data, $lifetime = null){
		// Check data argument
		if(!is_string($data)) throw new \InvalidArgumentException('Argument $data must be string type');

		// Get Joomla cache instance to store lifetime variable with a lifetime of 5 years
		$cacheLifetime = new JCache(array('caching' => true, 'defaultgroup' => $group, 'lifetime' => 2628000));

		// Set default life time if needed
		if(!isset($lifetime)) $lifetime = JFactory::getConfig()->get('cachetime')*60; // Seconds

		// Store lifetime
		$cacheLifetime->store($lifetime, "CacheHelper-Lifetime-$group-$id");

		// Get Joomla cache instance to store the dummy data with a custom lifetime
		// This is used to define if cache item is stale or not without destroying previous cached data
		$cacheDummyData = new JCache(array('caching' => true, 'defaultgroup' => $group, 'lifetime' => $lifetime/60));

		// Store the dummy data
		$cacheDummyData->store('dummy', "CacheHelper-DummyData-$group-$id");

		// Get Joomla cache instance to store the data with a lifetime of 5 years
		$cacheData = new JCache(array('caching' => true, 'defaultgroup' => $group, 'lifetime' => 2628000));

		// Store data
		$cacheData->store($data, $id);

		// Set updating flag to false
		self::setUpdatingFlag($id, $group, false);
	}

	/**
	 * Set the cache item updating flag
	 *
	 * @param    string     $id       Cache item id
	 * @param    string     $group    Cache group name
	 * @param    boolean    $flag     Set true to flag current cache item as updating
	 *
	 * @return    void
	 */
	static function setUpdatingFlag($id, $group, $flag){
		// Get Joomla cache instance to store the updating flag variable with a lifetime of 5 years
		$cacheUpdatingFlag = new JCache(array('caching' => true, 'defaultgroup' => $group, 'lifetime' => 2628000));

		// Set flag
		$cacheUpdatingFlag->store($flag, "CacheHelper-UpdatingFlag-$group-$id");
	}

	/**
	 * Get the cache item updating flag
	 *
	 * @param    string     $id       Cache item id
	 * @param    string     $group    Cache group name
	 *
	 * @return    boolean    Updating flag value
	 */
	static function getUpdatingFlag($id, $group){
		// Get Joomla cache instance to store the updating flag variable with a lifetime of 5 years
		$cacheUpdatingFlag = new JCache(array('caching' => true, 'defaultgroup' => $group, 'lifetime' => 2628000));

		// Get/return flag
		return (boolean)$cacheUpdatingFlag->get("CacheHelper-UpdatingFlag-$group-$id");
	}

	/**
	 * Check if a cache item is undefined
	 *
	 * @param    string     $id       Cache item id
	 * @param    string     $group    Cache group name
	 *
	 * @return    boolean
	 */
	static function isUndefined($id, $group){
		// Get Joomla cache instance to store lifetime variable with a lifetime of 5 years
		$cacheLifetime = new JCache(array('caching' => true, 'defaultgroup' => $group, 'lifetime' => 2628000));

		// If lifetime variable is not set then the cache item can be considered as undefined
		return ($cacheLifetime->get("CacheHelper-Lifetime-$group-$id") === false) ? true : false;
	}

	/**
	 * Remove a cache item
	 *
	 * @param    string     $id       Cache item id
	 * @param    string     $group    Cache group name
	 *
	 * @return    boolean
	 */
	static function remove($id, $group){
		// Get Joomla cache instance
		$cache = new JCache(array('caching' => true, 'defaultgroup' => $group, 'lifetime' => 2628000));

		// Remove lifetime
		$cache->remove("CacheHelper-Lifetime-$group-$id", $group);

		// Remove updating flag
		$cache->remove("CacheHelper-UpdatingFlag-$group-$id", $group);

		// Remove dummy data
		$cache->remove("CacheHelper-DummyData-$group-$id", $group);

		// Remove data
		$cache->remove($id, $group);
	}
}




