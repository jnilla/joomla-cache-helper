<?php
namespace Jnilla\Joomla;

defined('_JEXEC') or die();

use Joomla\CMS\Factory as JFactory;
use Joomla\CMS\Cache\Cache as JCache;

class CacheHelper{
	/*
	 * Gets data stored in the Joomla cache
	 *
	 * @param    string    $id       Cache item id
	 * @param    string    $group    Cache group name
	 *
	 * return    array               Return Array:
	 *                                   boolean    status    False if cache item expired or doesn't exist
	 *                                   mixed      data      Cached data
	 */
	static function get($id, $group, $timeout = 2){
		// Get Joomla cache instance with 5 years lifetime
		$cache1 = new JCache(array('caching' => true, 'defaultgroup' => $group, 'lifetime' => 2628000));
		
		// Apply a delay if cache is updating
		if($cache1->get("CacheHelper-Updating-$group-$id")){
			for ($i = 0; $i <= $timeout*10; $i++) {
				usleep(100000);
				// Check if cache updating finished
				if(!$cache1->get("CacheHelper-Updating-$group-$id")) break;
			}
			// Set updating flag to false
			$cache1->store(false, "CacheHelper-Updating-$group-$id");
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

	/*
	 * Store/update data in the Joomla cache
	 *
	 * @param    string    $id          Cache item id
	 * @param    string    $group       Cache group name
	 * @param    mixed     $data        Data to cache
	 * @param    string    $lifeTime    Cache life time in seconds
	 *
	 * return    void
	 */
	static function set($id, $group, $data, $lifeTime=null, $updating = false){
		// Get Joomla cache instance with 5 years lifetime
		$cache1 = new JCache(array('caching' => true, 'defaultgroup' => $group, 'lifetime' => 2628000));
		
		// Check if updating flag needs to be set
		if($updating){
			// Set updating flag to true
			$cache1->store(true, "CacheHelper-Updating-$group-$id");
			return;
		}
		
		// Set default life time if needed
		if(!isset($lifeTime)) $lifeTime = JFactory::getConfig()->get('cachetime')*60; // Seconds
		
		// Store life time
		$cache1->store($lifeTime, "CacheHelper-Life-Time-$group-$id");
		
		// Get Joomla cache instance with custom lifetime
		$cache2 = new JCache(array('caching' => true, 'defaultgroup' => $group, 'lifetime' => $lifetime/60));
		
		// Store data
		$cache2->store($data, $id);
		
		// Set updating flag to false
		$cache1->store(false, "CacheHelper-Updating-$group-$id");
	}

}




