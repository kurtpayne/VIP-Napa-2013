<?php

/**
 * No idea if this is a good pattern or not :)
 */

require 'wp-load.php';

// Everything's static because I'm lazy.
// Singletons may make more sense.

abstract class Object_Getter {
	/**
	 * @param int $id
	 *
	 * @retern object
	 */
	static function get( $id ) {
		return static::_get( $id );
	}

	/**
	 * @param array $args
	 *
	 * @return array of objects
	 */
	static function query( $args ) {
		return static::_query( $id );
	}
}

/**
 * Naively caches output of ::get() and ::query()
 */
trait Cache {
	static function get( $id ) {
		return self::with_cache( array( 'parent', __FUNCTION__ ), $id, $id, __METHOD__ );
	}

	static function query( $args ) {
		return self::with_cache( array( 'parent', __FUNCTION__ ), $args, md5( serialize( $args ) ), __METHOD__, 300 );
	}

	protected static function with_cache( $callback, $args, $key, $group, $expiry = 0 ) {
		$return = wp_cache_get( $key, $group );
		if ( $return ) {
			return $return;
		}

		$return = call_user_func( $callback, $args );

		wp_cache_add( $key, $return, $group, $expiry );

		return $return;
	}
}

/**
 * Caches only the object IDs of the outpu of ::query()
 */
trait Cache_IDs {
	use Cache;

	static function query( $args ) {
	/*
		Hm...
		This will either have to
		 * impose some structure on the objects (an ->id parameter, for example),
		 * impose some structure on the arguments accepted by ::query(),
		 * add complexity by offering configuration (an ::$id_field parameter, for example),
		 * or necessitate a new ::get_ids( $args ) API.
	*/
	}


}

class Posts extends Object_Getter {
	static function _get( $id ) {
		return get_post( $id );
	}

	static function _query( $args ) {
		return get_posts( $args );
	}
}

class Cached_Posts extends Posts {
	use Cache;
}

/*
$posts = Posts::query( $args );
$posts = Cached_Posts::query( $args );

If these were Singletons, we could do things like

function Posts() {
	return Posts::Singleton()
}

$post = Posts()[123]; // implements ArrayAccess
*/
