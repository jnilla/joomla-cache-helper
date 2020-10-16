# joomla-cache-helper

Use Joomla cache support fast and easy.

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
CacheHelper::set('idHere', 'groupNameHere', $data);
```

Get data from cache:

```
$response = CacheHelper::get('idHere', 'groupNameHere');

// $response['status'] --> true
// $response['data'] --> "Some data..."
```

Cache using callbacks:

```
$data = CacheHelper::callback(
	'idHere',
	'groupNameHere',
	function(){return $externalService->getMessages();}
	120
);
```
 
## Example

The most practical way to work with this library is using the ```callback()``` method.

For this example we will demonstrate how to avoid simultaneous calls to an expensive operation.

```
$data = CacheHelper::callback(
	'idHere',
	'groupNameHere',
	function(){return $externalService->getMessages();}
	10,
	5
);

printMessages($data['data']);
```

The ```$externalService``` API object have a request rate limit of 6 calls per minute. The data returned is used to print a list of messages in a website. 

This website have several hundred users and is requested more than 50 times per second. It's clear that cache needs to be implemented to provide performance and prevent simultaneous requests to the external service.

This is how the website will react to the users interaction:

Website is requested for the first time:

* Get data from cache.
* Cache is invalid.
* Cache gets flagged as updating.
* Expensive operation is executed and takes 200ms to finish.
* Operation result data is stored to cache with a life time of 10 seconds.
* Return data.
* Print list of messages.

During the update operation (200ms) the website was requested 10 more times:

* Get data from cache.
* Cache is flagged as updating.
* Wait for cache to finish updating.
* Cache finished updating.
* Return data.
* Print list of messages (for each request).

5 seconds later the website was requested 250 times:

* Get data from cache.
* Cache is valid.
* Return data.
* Print list of messages (for each request).

Cache expires after 10 seconds and the process repeats.

The cache life time of 10 seconds ensures the external service is requested no more than 6 times per minute. The timeout of 5 seconds covers most delays that may happen while requesting the external service.

## License

This project is under the MIT License.
