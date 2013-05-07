Notes
-----

If something is slow, you need to fix it.

1. Cache it
2. Long cache expiration time with cache set locking
3. Prime the cache when you know it changes
4. Get rid of it

4 > 3 > 2 > 1 :)

WordPress
=========

WordPress has lots of places to cache data.
* Options
* Transients
* Post Meta
* Custom Post Type
* Comment Meta
* Custom Comment Type

These are all good places to store data you need cached in a truly-persistent and replicated way.

### Options

All options are loaded on all pageloads, so if you don't need the data often, don't store it in options.

Note: For performance reasons, WordPress.com limits a site's options to be less than 1MB total (aggregate over all options).

### Transients

On sites with no persistent caching backend, transients are implemented internally by WordPress via the options table.
* The same caution with options applies to transients, though expiring transients are (probably) not autoloaded.
* Expiring transients to not get culled automatically as they expire.  They only get removed when they are accessed after expiration.
* Plugins/themes should clean up after themselves on deactivate just like with options.

On sites with a persistent caching backend, transients are implemented with that persistent cache.

On WordPress.com, that means memcached, which is only quasi-persistent.

### Post/Comment Meta

The first time any one meta item is accessed, all meta items for that post/comment are queried and cached.

As with options, be judicious about storing data in meta.

### Custom Post/Comment Types

Make sure you get your permissions right :)


Memcached
=========

WordPress.com uses memcached as a quasi-persistent cache.  The cache is exposed through core's `wp_cache_*()` functions.

It's only quasi-persistent because Memcached evicts old, infrequently accessed items to free up memory for new items.

CRUD API
--------

### Create

```php
// Overwrites any pre-existing data in that key/group.
wp_cache_set( $key, $value, $group, $expiry = null );

// Only succeeds if nothing exists already in that key/group.
wp_cache_add( $key, $value, $group, $expiry = null );
```

### Read

```php
// $force bypasses the local, in-memory cache and goes straight to Memcached
// &$found out-variable to disambiguate a stored (and found) FALSE value.
wp_cache_get( $key, $group, $force = false, &$found = null );

// $array = [ $group => [ $key1, $key2, ... ], ... ];
// Return value is annoying
wp_cache_multi( $array );
```

### Update

```php
// Only succeeds if something exists already in that key/group.
wp_cache_replace( $key, $value, $group, $expiry = null );

wp_cache_incr( $key, $number, $group );

wp_cache_decr( $key, $number, $group );
```

### Delete

```php
wp_cache_delete( $key, $group );
```

### Not Implemented by WordPress

* append/prpend for working with Memcached Lists
* CAS: Check and Set (Compare and Swap)

WordPress.com Idiosyncracy
--------------------------

WordPress.com maintains a separate Memcached pool in each of its three datacenters.

By design, the pools are not required to be in sync.

To avoid cache-based data poisoning, though, most write operations are replicated to each pool.

### Replicated Operations
* `wp_cache_set()`,
* `wp_cache_replace()`,
* `wp_cache_delete()`,
* `wp_cache_incr()`, and
* `wp_cache_decr()`.

### Unreplicated Operations
* `wp_cache_add()`

This difference further complicates choosing between set and add.

My suggestion: use add unless you have a reason to use set.

Example: Caching Queries of Items in a Set
------------------------------------------

We have a set of things we want to query:
* Get most recent 10 things,
* Get things that start with "cheese",
* etc.

Typical use case: cache on demand.

### Cache Query

Just cache the full response.  Fine for small objects that don't often get queried in different ways.

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

### Cache by ID

Caching each thing object separately means those cached copies can be used by other queries too.

```php
function get_things( $args ) {
	$ids = get_thing_ids( $args );
	return array_map( 'get_thing', $ids );
}

// Cache each thing object separately
function get_thing( $id ) {
	$thing = wp_cache_get( $id, 'thing', false, $found );
	if ( $found ) {
		return $thing;
	}

	/* ... Do query */

	wp_cache_add( $id, 'thing' );

	return $thing;
}

// Cache query as list of IDs instead of list of thing objects
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

Note that `get_thing()` may not be any faster with caching.  PK lookups are fast.  Depends on your infrastructure.  Consider non-persistent cache group.

### `get_multi()`

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

### Group Invalidation

Instead of having a short expiry time (300 seconds), it might be better if we could have a long (indefinite) expiry time and invalidate as needed.

Sadly, Memcaced can't invalidate groups.  (Memcached doesn't really have groups, anyway.)

So we have to implement something ourselves.

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

Core tried increments and failed http://core.trac.wordpress.org/changeset/23401. Switched to `microtime()` instead.


### Real World: Advanced Post Cache

WordPress.com uses a (slightly more complicated) version of this Group Invalidation via Incrementors pattern for queries on blogs' posts tables.  Mostly successfully :)

In addition to the results of the regular query, though, it also has to cache the corresponding `FOUND_ROWS()` query.

It uses `wp_cache_add()` to write to both caches (posts and found rows), but if it thinks the two have gotten out of sync, it switches to `wp_cache_set()`.

Greatly speeds up post queries (core does some of this now...)

Mostly bug free :)

Invalidation is tricky: we do it too often.


Example: Non-Critical State
---------------------------

Memcached is also a good choice for storing state, as long as remembering that state isn't critical.

For example, `wpcom_vip_file_get_contents()` uses memcached to remember if the remote host is inaccessible.  If the host is down, no requests will be sent to it (for a given length of time), which means WordPress.com isn't brought down by unresponsive hosts.


APC: Alternative PHP Cache
==========================

APC does two things:

1. Caches the intermediate bytecode of PHP files so that they don't have to be compiled for every request.  Just executed.
2. Provides a persistent, shared-memory key-value store.

Without the bytecode cache, WordPress.com would probably just fall over :) It dramatically improves the number of requests/second we can serve.  300%?

The key-value store is very different than Memcached's; it's local.

So something stored on one web server is not accessible to processes on another web server.  WordPress.com has 800+ web servers, so APC's utility to WordPress.com web developers is limited.

There's one important place, though, where this per-host cache is a feature: HyperDB.

When a DB server is unresponsive, we flag it as such and look to other DB servers.  Responsiveness is affected by network topology, so we need per-host flags.  We want the flags to be persistent across page loads, though.  APC to the rescue.

Note: This is the same caching pattern applied in almost the same scenario as in `wpcom_vip_file_get_contents()`.


Output Cache
============

Batcache
--------

PHP based page output cache implemented with Memcached.

https://github.com/skeltoac/batcache

WordPress.com uses it for almost all non-admin page views.

When a page gets viewed several times in rapid succession the page is cached and subsequent views are served from the cache.

Bypassed for logged in users, previous commenters, etc.

nginx
-----

Great because it bypasses all of PHP, which makes Barry very happy.

WordPress.com uses it for feeds, a few other things.

And so can you!

### Example: Metro UK's sitemaps

Sitemaps are updated by WP Cron and stored in nginx.  State is held in WordPress options.

A good example since:
* There's not too many files
* The files all have a predetermined URL structure


Locking and Fallback
====================

No matter what caching backend you use (WordPress, Memcached, nginx, ...), high concurrency and cache misses of expensive objects don't mix.

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

			// Cache value with the specified expiration
			// Replace would be nice, but multi-DC makes it tricky
			wp_cache_set( $key, $value, $group, $expires );

			// Cache value as backup without expiration
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

Remember: 4 > 3 > 2 > 1 :)


Todo:
Embed examples
