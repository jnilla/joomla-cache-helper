# joomla-cache-helper

Use Joomla cache support fast and easy

## Installation

Install using Composer:

```
$ php composer.phar require jnilla/joomla-cache-helper
```

Load the library using the Composer autoloader:

```
require('vendor/autoload.php');
```

## Usage

This helper is build on top of the native Joomla cache support. We implemented a simpler API and added few extra features to make the helper practical.

### Declaration

```
use Jnilla\Joomla\CacheHelper as CacheHelper;
```

### Method: get()

Gets cache data.

**Parameters:**

* **$group:** The cache data group.
* **$id:** The cache data id.
* **$timeout:** (Optional) Max time in seconds to wait for cache to finish updating.

**Return:**

Array with these elements:

* **status**:  ```true``` if cache is valid.
* **data**: Cached data.

**Cases of use:**

* If cache is empty ```status``` will be ```false``` and ```data``` will be ```null```.
* If cache is expired ```status``` will be ```false``` and ```data``` will be the old data.
* If cache is updating and timeout is reached ```status``` will be ```false``` and ```data``` will be the old data

**Examples:**

Assuming cache is valid.

```
$response = CacheHelper::get('groupNameHere', 'idHere');

// $response['status'] --> true
// $response['data'] --> "Some data..."
```

Assuming a previous operation sets the flag ```$updating = true```.

```
$response = CacheHelper::get('groupNameHere', 'idHere', 5);
```

The ```get()``` method will return the new cache data as soon as the cache finish updating or it will wait up to 5 seconds and then return the old cache data if any.

### Method: set()

Stores data to cache.

**Parameters:**

* **$group:** The cache data group.
* **$id:** The cache data id.
* **$data:** The data to store.
* **$lifeTime:** (Optional) Time in seconds before cache expires. If not set Joomla cache time will be used as default. Min value 1 second, max value 5 years.
* **$updating:** (Optional) If ```true``` cache will be flagged as updating and current data will remain untouch.

**Return:**

Void.

**Examples:**

Assuming ```$response``` is the data to store to cache.

```
CacheHelper::set('groupNameHere', 'idHere', $response, 120);
```

The content of  ```$response``` is stored to cache and will be valid for 120 seconds.

For this example we will demonstrate how to avoid simultaneous expensive operations.

```
function getMessages(){
	// If cache is updating wait up to 5 seconds
	$response = CacheHelper::get('groupNameHere', 'idHere', $response, 5);
	
	// If cache is valid return the data
	if($response['status']) return $response['data'];
	
	// Flag cache as updating
	CacheHelper::set('groupNameHere', 'idHere', null, null, true);
	
	// Execute expensive operation. Takes around 200ms
	$response = $externalService->getMessages();
	
	// Cache response
	CacheHelper::set('groupNameHere', 'idHere', $response, 10);
	
	return $response;
}
```

The external service have a request rate limit of 10 calls per minute. The data returned by our function ```getMessages()``` is used to populate a list items in a website. This website have several hundred users and is requested more than 50 times per second.

Is clear that cache needs to be implemented to provide performance and prevent simultaneous requests to the external service.

This is how the website will react to the users interaction:

Website is requested for the first time:

* Get data from cache.
* Cache is invalid.
* Cache gets flagged as updating.
* Expensive operation is executed and takes 200ms to finish.
* Operation result data is stored to cache with a life time of 10secs.
* Return data.
* The list is populated.

During the update operation (200ms) the website was requested 10 more times:

* Get data from cache.
* Cache is flagged as updating.
* Wait for cache to finish updating.
* Cache finish updating.
* Return data.
* The list is populated for each request.

5secs later the website was requested 250 times:

* Get data from cache.
* Cache is valid.
* Return data.
* The list is populated for each request.

Cache expires after 10 seconds and the process repeats

The cache life time of 10secs ensures the external service is requested no more than 10 times per minute. The timeout of 5 seconds covers most delays from the external service.

## License

This project is under the MIT License.

