<?php
namespace Jnilla\Joomla;

defined('_JEXEC') or die();

use Joomla\CMS\Factory as JFactory;
use Joomla\CMS\Cache\Cache as JCache;

class CacheHelper{
	/*
	 * Gets data stored in the Joomla cache
	 *
	 * @param    string    $group    Cache group name
	 * @param    string    $id       Cache item id
	 *
	 * return    array               Return Array:
	 *                                   boolean    status    False if cache item expired or doesn't exist
	 *                                   mixed      data      Cached data
	 */
	static function getCache($group, $id){
		// Get timer value
		$timer = new JCache(array('caching' => true, 'defaultgroup' => 'JnillaTimer', 'lifetime' => 2*365*24*60));
		$timer = $timer->get($group.$id);
		if($timer === false) return array('status' => false);

		// Get cached data
		$cache = new JCache(array('caching' => true, 'defaultgroup' => $group, 'lifetime' => $timer/60));
		if($cache->contains($id)){
			$data = $cache->get($id);
			return array('status' => true, 'data' => $data);
		}

		return array('status' => false);
	}

	/*
	 * Store/update data in the Joomla cache
	 *
	 * @param    string    $group       Cache group name
	 * @param    string    $id          Cache item id
	 * @param    mixed     $data        Data to cache
	 * @param    string    $lifeTime    Cache life time in seconds
	 *
	 * return    void
	 */
	static function setCache($group, $id, $data, $lifeTime=null){
		// Set default life time if needed
		if(!isset($lifeTime)){
			$lifeTime = JFactory::getConfig()->get('cachetime')*60; // Seconds
		}
		// Store timer value
		$timer = new JCache(array('caching' => true, 'defaultgroup' => 'JnillaTimer', 'lifetime' => 2*365*24*60));
		$timer = $timer->store($lifeTime, $group.$id);
		if($timer === false) return false;

		// The cache object uses minutes for lifetime. This hack allows to use seconds instead
		$lifetime = $lifetime/60;

		$cache = new JCache(array('caching' => true, 'defaultgroup' => $group, 'lifetime' => $lifetime));
		$cache->store($data, $id);
	}

}

