<?php
namespace Jnilla\Joomla;

defined('_JEXEC') or die();

use Joomla\CMS\Factory as JFactory;
use Joomla\CMS\Cache\Cache as JCache;

class CacheHelper{
	/**
	 * Proxy fuction to get a cache item or update its content using a callback to finally get it's content in either case
	 *
	 * @param    string      $id          Cache item id
	 * @param    string      $group       Cache group name
	 * @param    callable    $callback    Callback function that must return the data to store
	 * @param    integer     $lifetime    Time in second before cache item is flagged as stale. If value
	 *                                    is null Joomla global configuration cache time will be used instead.
	 * @param    integer     $wait        Time in seconds to force current operation to wait if the cache item is updating
	 *
	 * @return   array       Array with cache data and some flags
	 */
	static function proxy($id, $group, $callback, $lifetime = null, $wait = 5){
		// Get cached data
		$data = CacheHelper::get($id, $group, $wait);

		// Return data if isValid is true
		if($data['isValid']) return $data;

		// Return data if isTimeout is true
		if($data['isTimeout']) return $data;

		// Check if we can return data while updating
		if(($wait === 0) && self::getUpdatingFlag($id, $group)) return $data;

		// Flag cache as updating
		self::setUpdatingFlag($id, $group, true);

		// Execute callback (AKA expensive operation)
		$data = $callback();

		// Set cache data
		CacheHelper::set($id, $group, $data, $lifetime);

		// Return cache item
		return CacheHelper::get($id, $group, $wait);
	}

	/**
	 * Get the cache item
	 *
	 * @param    string      $id       Cache item id
	 * @param    string      $group    Cache group name
	 * @param    integer     $wait     Time in seconds to force current operation to wait if the cache item is updating
	 *
	 * @return   array       Array with cache data and some flags
	 */
	static function get($id, $group, $wait = 5){
		// Define default cache item structure
		$cacheItem = [
			'isValid' => false,
			'isUpdating' => false,
			'isTimeout' => false,
			'data' => ''
		];
		$isTimeout = null;
		$isStale = true;

		// Apply a delay if required
		// Note: Don't add code before this statement because its values may change after wait time.
		if(($wait > 0) && self::getUpdatingFlag($id, $group)){
			for ($i = 0; $i <= $wait*10; $i++){
				usleep(100000);
				if(!self::getUpdatingFlag($id, $group)){
					$isTimeout = false;
					break;
				}
			}
			if(!isset($isTimeout)) $isTimeout = true;
			// If max wait time is reached force updating flag to false
			self::setUpdatingFlag($id, $group, false);
		}

		// Save timeout flag
		$cacheItem['isTimeout'] = isset($isTimeout) ? $isTimeout : false;

		// Return data if isTimeout is true
		if($cacheItem['isTimeout']) return $cacheItem;

		// Get Joomla cache instance to store lifetime variable with a lifetime of 5 years
		$cacheLifetime = new JCache(array('caching' => true, 'defaultgroup' => $group, 'lifetime' => 2628000));

		// Get lifetime
		$lifetime = intval($cacheLifetime->get("CacheHelper-Lifetime-$group-$id"));

		// Get Joomla cache instance to store the data with a custom lifetime
		$cacheData = new JCache(array('caching' => true, 'defaultgroup' => $group, 'lifetime' => $lifetime/60));

		// Save cache data
		if($cacheData->contains($id)){
			$cacheItem['data'] = $cacheData->get($id);
			$isStale = false;
		}

		// Save updating flag
		$cacheItem['isUpdating'] = self::getUpdatingFlag($id, $group);

		// Save valid flag
		$cacheItem['isValid'] = !$isStale && !$cacheItem['isUpdating'] && !$cacheItem['isTimeout'];

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

		// Get Joomla cache instance to store the data with a custom lifetime
		$cacheData = new JCache(array('caching' => true, 'defaultgroup' => $group, 'lifetime' => $lifetime/60));

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

		// Return flag
		return (boolean)$cacheUpdatingFlag->get("CacheHelper-UpdatingFlag-$group-$id");
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

		// Remove data
		$cache->remove($id, $group);
	}
}




