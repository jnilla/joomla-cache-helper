# joomla-cache-helper

A Joomla cache helper for easier usage.

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
  'isValid' => boolean true
  'isUpdating' => boolean false
  'isTimeout' => boolean false
  'data' => string 'Some string data here' (length=0)
```

The proxy method:

```
$cache = CacheHelper::proxy(
	'idHere', // Cache Id
	'groupNameHere', // Cache Group
	function(){return 'Some string data here';}, // Callback that returns the data to cache
	6, // Cache Lifetime
	15 // Wait if updating
);

// $cache var dump
array (size=4)
  'isValid' => boolean true
  'isUpdating' => boolean false
  'isTimeout' => boolean false
  'data' => string 'Some string data here' (length=0)
```

If the flag `isValid` is `true` you can use the cache item data safely.

The most practical way to work with this library is using the `proxy` method.

This methods works as 2 in 1 intermediary that get and updates the cache item automatically if needed. 

How it works:

* If the cache item is not stale the method returns the cache item.
* If the cache item is stale the method executes de callback, store the result in the cache and returns the updated cache item.

Example with remote data:

```
$cache = CacheHelper::callback(
	'idHere', // Cache Id
	'groupNameHere', // Cache Group
	function(){return file_get_contents('https://jsonplaceholder.typicode.com/todos');}, // Callback that returns the data to cache
	6, // Cache Lifetime
	15 // Wait if updating
);

// $cache var dump
array (size=4)
  'isValid' => boolean true
  'isUpdating' => boolean false
  'isTimeout' => boolean false
  'data' => string '{some JSON code from that source}' (length=0)
```

The lifetime is the time before a cache item is considered stale and needs to be updated.

It's useful to know that we can use the `$lifetime` argument as a request rate limit mechanism using a formula like this 60 seconds  / number_of_request = lifetime. Example: For a rate limit of 10 requests per minute use: 60 seconds / 10 requests = 10 seconds.

## Wait Mechanism

There is a wait mechanism that is useful for certain cases of use.

The methods `get` and `proxy` share the following argument:

* @param integer **$wait:** Time in seconds to force current operation to wait if the cache item is updating.

**Case 1:**

5 users request remote data using the `proxy` method at the same time for the first time and the `$wait` is not `0`.

The first user to use the `proxy` method triggers the `isUpdading` flag and everyone gets to wait for the remote fetch operation to finish. After this, everyone gets the same data at the same time.

**Case 2:**

5 users request remote data using the `proxy` method at the same time for the first time and the `$wait` is `0`.

The first user to use the `proxy` method triggers the `isUpdading` flag and only this user gets to wait for the remote fetch operation to finish. Everyone else gets an empty cache item at the same time.

This case is useful if you don't want to lock everyone on a waiting period the firt time the remote data is requested.

This behavior helps to aliviate server RAM usage because requests/CPU Threads live shorter.

**Case 3:**

5 users request remote data using the `proxy` method at the same time for the first time and the `$wait` is not `0`, but the remote operation takes too long.

The first user to use the `proxy` method triggers the `isUpdading` flag and everyone gets to wait. If `$wait` time is reached the `isUpdading` flag is set to `false`, the `isTimeout` flag is set to `true` and everyone gets an empty cache item at the same time.

This prevents too long or infinite wait times for eveyone.

## License

This project is under the MIT License.

