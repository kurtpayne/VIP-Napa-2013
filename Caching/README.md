Notes
-----

If something is slow, you need to fix it.

1. Cache it
2. Long cache expiration time with cache set locking
3. Prime the cache when you know it changes
4. Get rid of it

4 > 3 > 2 > 1 :)

Memcached
=========

WordPress.com uses memcached as a persistent cache.  The cache is exposed through core's `wp_cache_*()` functions.

Cache
-----

```php
function get_things( $args ) {
	$key = md5( serialize( $args ) );
	$things = wp_cache_get( $key, 'things', false, $found );

	if ( $found ) {
		return $things;
	}

	/* ... Do query */

	wp_cache_add( $key, $things, 'things', 300 );

	return $things;
}
```

Cache IDs
---------

Caching objects separately means those cached copies can be used by other queries too.

```php
function get_things( $args ) {
	$ids = get_thing_ids( $args );
	return array_map( 'get_thing', $ids );
}

function get_thing( $id ) {
	$thing = wp_cache_get( $id, 'thing', false, $found );
	if ( $found ) {
		return $thing;
	}

	/* ... Do query */

	wp_cache_add( $id, 'thing' );

	return $thing;
}

function get_thing_ids( $args ) {
	$key = md5( serialize( $args ) );
	$ids = wp_cache_get( $key, 'thing_ids', false, $found );
	if ( $found ) {
		return $ids;
	}

	/* ... Do query */

	wp_cache_add( $key, $ids, 'thing_ids', 300 );

	return $ids;
}
```

Note that `get_thing()` may not be any faster with caching.  PK lookups are fast.  Depends on your infrastructure.


`get_multi()`
-------------

Every call to `wp_cache_get()` is one roundtrip to Memcached.

Consolidate calls with `wp_cache_get_multi()`.

```php
function get_things( $args ) {
	$ids = get_thing_ids( $args );

	// wp_cache_get_multi() is a little annoying.  We probably need a better API.
	$cached_things = array_values( wp_cache_get_multi( array( 'things' => $ids ) ) );

	$things = array();
	foreeach ( $ids as $pos => $id ) {
		$thing = $cached_things[$pos];
		if ( ! $thing ) {
			$thing = get_thing( $id );
		}
		$things[] = $thing;
	}

	return $thing;
}
```


Invalidate Groups
-----------------

Instead of having a short expiry time (300 seconds), it might be better if we could have a long (indefinite) expiry time and invalidate as needed.

```php
function get_thing_ids( $args ) {
	$key = md5( serialize( $args ) );
	$group = get_cache_group( 'thing_ids' );
	$ids = wp_cache_get( $key, $group, false, $found );
	if ( $found ) {
		return $ids;
	}

	/* ... Do query */

	wp_cache_add( $key, $ids, $group );

	return $ids;
}

function get_cache_group( $group ) {
	$incrementor = wp_cache_get( $group, 'group_incrementors', false, $found );
	if ( ! $found ) {
		$incrementor = time();
		wp_cache_set( $group, $incrementor, 'group_incrementors' );
	}

	return "$group_" . $incrementor;
}

function flush_cache_group( $group ) {
	return wp_cache_increment( $group, 'group_incrementors' );
}
```

A word of caution: Eviction.

If your incrementors and data are in different Memcached buckets, your incrementors can get evicted before your data does. That means your incrementor can get *lowered* to an old value, which could cause stale data to be served.

The above snippet initializes the incrementor with `time()`, but the issue remains. There are probably race conditions when everything is stored in the same bucket as well.

WordPress.com uses a (slightly more complicated) version of this pattern for our "Advanced Post Cache".  Mostly successfully :)

Core tried increments and failed http://core.trac.wordpress.org/changeset/23401. Switched to `microtime()` instead.


Locking and Fallback
--------------------

High concurrency and cache misses of expensive objects don't mix.

If something takes 3 seconds to calculate, and you get 100 requests a second, that's 300 processes that are all trying to calculate the same thing.

"Cache Miss Stampede"

Need locking and some sort of smart fallback.

```php
function with_cache_lock_and_fallback( $callback, $key, $group, $expires = 30 ) {
	$value = wp_cache_get( $key, $group, false, $found );
	if ( ! $found ) {
		// Cache is empty.  Update it.
		echo "UNCACHED - Caching...\n";

		// Tell other processes to fallback if possible
		// MC adds succeed only if there is not already a value for that key, but
		// DANGER: not multi-DC aware
		$added = wp_cache_add( $key, '--FALLBACK--', $group, $expires )

		if ( $added ) {
			// This process successfully added

			// Get new value
			$value = call_user_func( $callback );
			wp_cache_replace( $key, $value, $group, $expires ); // Set may be better?

			// Cache old value without expiration
			wp_cache_set( "{$key}_old", $value, 0 );

			return $value;
		} else {
			// This process did not successfully add. Some other process must have gotten there first.
			// Fallback

			$value = '--FALLBACK--';
		}
	}

	if ( '--FALLBACK--' === $value ) {
		// Cache is empty, but some other process is already filling it.
		// Fallback to old value
		echo "FALLBACK\n";
		return wp_cache_get( "{$key}_old", $group );
	}

	// Yay! Cached.
	echo "CACHED\n";
	return $value;
}
```

### WordPress Locks

Even without memcached, WordPress already provides locked processes: WP Cron.

Yes, there are issues, but 3 concurrent processes is better than 300 :)

### Do You Actually Need Locking?

Instead of generating the data on view, try to prime the cache on write.

For example, tag clouds only change when posts are created, updated, deleted.

Cache the tag cloud indefinitely. Regenerate on posts change.


Non-Critical State
------------------

Memcached is also a good choice for storing state, as long as remembering that state isn't critical.

For example, `wpcom_vip_file_get_contents()` uses memcached to remember if the remote host is inaccessible.  If the host is down, no requests will be sent to it (for a given length of time), which means WordPress.com isn't brought down by unresponsive hosts.


APC: Alternative PHP Cache
==========================

APC does two things:

1. Caches the intermediate bytecode of PHP files so that they don't have to be compiled for every request.  Just executed.
2. Provides a persistent, shared-memory key-value store.

Without the bytecode cache, WordPress.com would probably just fall over :) It dramatically improves the number of requests/second we can serve.  300%?

The key-value store is very different than memcached's; it's not distributed.  It's also not replicated across hosts.

So something stored on one web server is not accessible to processes on another web server.  WordPress.com has 800+ web servers, so APC's utility to WordPress.com web developers is limited.

There's one important place, though, where this per-host cache is a feature: HyperDB.

When a DB server is unresponsive, we flag it as such and look to other DB servers.  Responsiveness is affected by network topology, so we need per-host flags.  We want the flags to be persistent across page loads, though.  APC to the rescue.

Note: This is the same caching pattern applied in almost the same scenario as in `wpcom_vip_file_get_contents()`.
