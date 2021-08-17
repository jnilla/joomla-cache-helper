<?php
namespace Jnilla\Joomla;

defined('_JEXEC') or die();

use Joomla\CMS\Factory as JFactory;
use Joomla\CMS\Cache\Cache as JCache;

class CacheHelper{
	/**
	 * Cache by callback
	 *
	 * @param    string      $id          Cache item id
	 * @param    string      $group       Cache group name
	 * @param    callable    $callback    Callback reference
	 * @param    integer     $lifeTime    (Optional) Time in seconds before cache expires.
	 *                                    If not set Joomla cache time will be used as default.
	 *                                    Range goes from 1 second to 5 years (in seconds).
	 * @param    integer     $timeout     (Optional) Max time in seconds to wait for cache to finish updating
	 *
	 * @return    array      Return Array:
	 *                           boolean    status    False if cache item expired or doesn't exist
	 *                           mixed      data      Cache data
	 */
	static function callback($id, $group, $callback, $lifeTime = null, $timeout = null){
		// Get cache
		$data = CacheHelper::get($id, $group, $timeout);
		
		// Return data if cache is valid
		if($data['status']) return $data;
		
		// Flag cache as updating
		self::updating($id, $group, true);
		
		// Execute expensive operation
		$data = $callback();
		
		// Cache data
		CacheHelper::set($id, $group, $data, $lifeTime);
		
		// Return data
		return CacheHelper::get($id, $group, $timeout);;
	}
	
	/**
	 * Gets data stored in the Joomla cache
	 *
	 * @param    string     $id         Cache item id
	 * @param    string     $group      Cache group name
	 * @param    integer    $timeout    (Optional) Max time in seconds to wait for cache to finish updating.
	 *                                  Range goes from 0 seconds to 5 years (in seconds).
	 *
	 * @return    array      Return Array:
	 *                          boolean    status    False if cache item expired or doesn't exist
	 *                          mixed      data      Cache data
	 */
	static function get($id, $group, $timeout = 2){
		// Get Joomla cache instance with 5 years lifetime
		$cache1 = new JCache(array('caching' => true, 'defaultgroup' => $group, 'lifetime' => 2628000));
		
		// Apply a delay if cache is updating
		if(self::updating($id, $group)){
			for ($i = 0; $i <= $timeout*10; $i++) {
				usleep(100000);
				// Check if cache updating finished
				if(!self::updating($id, $group)) break;
			}
			// Set updating flag to false
			self::updating($id, $group, false);
		}
		
		// Get life time value
		$lifeTime = intval($cache1->get("CacheHelper-Life-Time-$group-$id"));
		if($lifeTime < 1) return array('status' => false);
		
		// Get Joomla cache instance with custom lifetime
		$cache2 = new JCache(array('caching' => true, 'defaultgroup' => $group, 'lifetime' => $lifeTime/60));
		
		// Check if cache exist
		if($cache2->contains($id)){
			$data = $cache2->get($id);
			return array('status' => true, 'data' => $data);
		}
		
		// Return default
		return array('status' => false);
	}

	/**
	 * Store/update data in the Joomla cache
	 *
	 * @param    string     $id          Cache item id
	 * @param    string     $group       Cache group name
	 * @param    mixed      $data        Data to cache
	 * @param    integer    $lifeTime    (Optional) Time in seconds before cache expires.
	 *                                   If not set Joomla cache time will be used as default.
	 *                                   Range goes from 1 second to 5 years (in seconds).
	 *
	 * @return    void
	 */
	static function set($id, $group, $data, $lifeTime = null){
		// Get Joomla cache instance with 5 years lifetime
		$cache1 = new JCache(array('caching' => true, 'defaultgroup' => $group, 'lifetime' => 2628000));
		
		// Set default life time if needed
		if(!isset($lifeTime)) $lifeTime = JFactory::getConfig()->get('cachetime')*60; // Seconds
		
		// Store life time
		$cache1->store($lifeTime, "CacheHelper-Life-Time-$group-$id");
		
		// Get Joomla cache instance with custom lifetime
		$cache2 = new JCache(array('caching' => true, 'defaultgroup' => $group, 'lifetime' => $lifeTime/60));
		
		// Store data
		$cache2->store($data, $id);
		
		// Set updating flag to false
		self::updating($id, $group, false);
	}
	
	/**
	 * Flag cache as updating
	 *
	 * @param    string     $id       Cache item id
	 * @param    string     $group    Cache group name
	 * @param    boolean    $flag     (Option) If true cache will be flagged as updating.
	 *                                If not set current value will be returned.
	 *
	 * @return    boolean
	 */
	static function updating($id, $group, $flag = null){
		// Get Joomla cache instance with 5 years lifetime
		$cache1 = new JCache(array('caching' => true, 'defaultgroup' => $group, 'lifetime' => 2628000));
		
		// Set flag
		if(isset($flag)) $cache1->store($flag, "CacheHelper-Updating-Flag-$group-$id");
		
		// Get/return flag
		return $cache1->get("CacheHelper-Updating-Flag-$group-$id");
	}
	
}




