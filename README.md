# joomla-cache-helper

Joomla cache facade for easier usage.

This helper is build on top of the native Joomla cache support. We implemented a simpler API and added few extra features to make the helper practical. 

## Installation

Install using Composer:

```
$ composer require jnilla/joomla-cache-helper
```

Load the library using the Composer autoloader:

```
require('vendor/autoload.php');
```

## Basic Usage

Declaration:

```
use Jnilla\Joomla\CacheHelper as CacheHelper;
```

Store data to cache:

```
CacheHelper::set('idHere', 'groupNameHere', 'Some string data here');
```

Get data from cache:

```
$cache = CacheHelper::get('idHere', 'groupNameHere');

// $cache var dump
array (size=4)
  'isUndefined' => boolean false
  'isStale' => boolean false
  'isUpdating' => boolean false
  'data' => string 'Some string data here' (length=0)
```

The proxy function:

```
$cache = CacheHelper::proxy(
	'idHere', // Cache Id
	'groupNameHere', // Cache Group
	function(){return 'Some string data here';}, // Callback that returns the data to cache
	6, // Cache Lifetime
	true, // Wait if updating
	15, // Max wait time
	true // Wait if undefined
);

// $cache var dump
array (size=4)
  'isUndefined' => boolean false
  'isStale' => boolean false
  'isUpdating' => boolean false
  'data' => string 'Some string data here' (length=0)
```

The most practical way to work with this library is using the `proxy` function.

This functions works as 2 in 1 intermediary that get and updates the cache item automatically if needed. 

How it works:

* If the cache item is not stale the function returns the cache item.
* If the cache item is stale the function executes de callback, store the result in the cache and returns the updated cache item.

Example with remote data:

```
$cache = CacheHelper::callback(
	'idHere', // Cache Id
	'groupNameHere', // Cache Group
	function(){return file_get_contents('https://jsonplaceholder.typicode.com/todos');}, // Callback that returns the data to cache
	6, // Cache Lifetime
	true, // Wait if updating
	15, // Max wait time
	true // Wait if undefined
);

// $cache var dump
array (size=4)
  'isUndefined' => boolean false
  'isStale' => boolean false
  'isUpdating' => boolean false
  'data' => string '{some JSON code from that source}' (length=0)
```

The lifetime is the time before a cache item is considered stale and needs to be updated.

For this example the remote data is requested only 10 times per minute because of the lifetime value of 6 seconds. This is usefull if we are working with remote APIs that are bound to rate limits.

## Mechanism

There is a blocking and non-blocking mechanism that is useful for certain cases of use.

The methods `get` and `proxy` share the following arguments:

* @param boolean **$waitIfUpdating:** Force current operation to wait if the updating flag is true.
* @param integer **$maxWait:** Max time in seconds to wait if updating flag is true.
* @param boolean **$waitIfUndefined:** Force current operation to wait if the updating flag is true and current cache item is undefined (doesn't exist).

**Case 1 - Blocking Mechanism:**

5 users request remote data using the `proxy` function at the same time for the first time (meaning there is no previous cache item).

If `$waitIfUpdating` is `true` the first user to use the `proxy` function triggers the `isUpdading` flag and everyone gets to wait for the remote fetch operation to finish. After this, everyone gets the same data at the same time.

**Case 2 - Blocking Mechanism:**

5 users request remote data using the `proxy` function at the same time for the first time (meaning there is no previous cache item).

If `$waitIfUpdating` is `false` and `$waitIfUndefined` is `true` the first user to use the `proxy` function triggers the `isUpdading`  flag and everyone gets to wait for the remote fetch operation to finish. After this, everyone gets the same data at the same time.

This case is useful if you don't want to return an empty cache item the firt time the remote data is requested.

**Case 3 - Non-Blocking Mechanism:**

5 users request remote data using the `proxy` function at the same time for the first time (meaning there is no previous cache item).

If `$waitIfUpdating` is `false` and `$waitIfUndefined` is `false` the first user to use the `proxy` function triggers the `isUpdading` flag and only this person gets to wait for the remote fetch operation to finish. Everyone else gets an empty cache item the same time.

This case is useful if you don't want to lock almost everyone on a waiting period the firt time the remote data is requested and it doens't matter if the cache item is empty for some users.

This behavior helps to aliviate server RAM usage because requests/CPU Threads live shorter.

**Case 4 - Non-Blocking Mechanism:**

5 users request remote data using the `proxy` function at the same time but there is a previous cache item that is stale.

If `$waitIfUpdating` is `false` the first user to use the `proxy` function triggers the `isUpdading` flag and only this person gets to wait for the remote fetch operation to finish. Everyone else get thre previous stale cache item at the same time.

This case is useful if you don't want to lock almost everyone on a waiting period the firt time the remote data is requested and it doens't matter some users get the previos stale cache item.

This behavior helps to aliviate server RAM usage because requests/CPU Threads live shorter but bandwith may be wasted is stale data is useless.

**Case 5 - Update operation takes too long:**

5 users request remote data using the `proxy` function at the same but the remote operation takes too long.

If `$waitIfUpdating` or `$waitIfUndefined` are `true` the first user to use the `proxy` function triggers the `isUpdading` flag and everyone gets to wait. If `$maxWait` is reached the `isUpdading` flag is set to `true` and everyone gets a stale cache item if any or an empty one.

This prevents too long or infinite wait times for eveyone.

## License

This project is under the MIT License.
