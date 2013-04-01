Injection Attacks
=================

Unescaped data tricks the service into betraying itself.

Each data format or protocol needs its own methods of sanitizing data.

Context is king:  Don't sanitize SQL queries with `wp_specialchars()`. Don't sanitize URLs with `$wpdb->escape()`.

SQL Injection
-------------

### Attacker's Goal

Read from or write to your site's database.

### Example

```php
$data = json_decode( $HTTP_RAW_POST_DATA );
$user = $wpdb->get_row( "SELECT * FROM `users` WHERE `name` = '{$data->name}'" );
```

#### Attack
```sh
$ curl -d '{"name":"bob'\''; DROP TABLE `users`;#'\''Comment"}' 'http://example.com'
```

#### Result

> ```sql
> SELECT * FROM `users` WHERE `name` = 'bob'; DROP TABLE `users`;#'Comment'
> ```

#### Solution

```php
$data = json_decode( $HTTP_RAW_POST_DATA );
$user = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `users` WHERE `name` = %s", $data->name ) );
```

* `$wpdb->prepare()`/`$wpdb->insert()`/`$wpdb->update()`
* `like_escape()`


XSS: Cross Site Scripting
-------------------------

### Attacker's Goal

Inject Javascript into your site's HTML.

### Example: Plain Ol' XSS

```html+php
<a href="http://example.com/hello/?ref=<?php echo $_GET['ref']; ?>">Hello</a>
```

#### Attack

http://example.com/?ref=foo%22+onclick%3D%22javascript%3Aalert%28%2FXSS%2F%29

#### Result

> ```html
> <a href="http://example.com/hello/?ref=foo" onclick="javascript:alert(/XSS/)">Hello</a>
> ```

#### Solution

```html+php
<a href="<?php echo esc_url( 'http://example.com/hello/?ref=' . urlencode( $_GET['ref'] ) ); ?>">Hello</a>
```

* `esc_html()`, `esc_attr()`, `esc_url()`
* `esc_js()`, `json_encode()`
* Aside: you can break XML output if it's not run through `ent2ncr()` first.


### Example: It happens in JS too

```js
$( '#error' ).append( "Invalid input: " + $( '#input' ).val() );
```

#### Attack

Enter `<script>` tags in the input

### Example: Don't trust the browser

```js
val html = '<a href="' + document.location + '?fun=1">Click me for fun!</a>';
```

#### Attack

[http://example.com/#"onclick="alert(/XSS/);"data-foo="][fancy-xss]
[fancy-xss]: <http://example.com/#"onclick="alert(/XSS/);"data-foo=">

#### Result

> ```js
> val html = '<a href="http://example.com/#"onclick="alert(/XSS/);"data-foo="?fun=1">Click me for fun!</a>';
> ```

#### Solution

* Building HTML from strings is just like PHP: You have to escape everything.
* Difference between jQuery's `.html()` and `.text()`.
* Use jQuery's `.attr()`.


### Example: CSS

```css+php
background-image: url( <?php echo esc_url_raw( $url ); ?> );
```

#### Attack

`http://foo.com/icon);font-size:expression(alert(/XSS/)`

#### Result

> ```css
> background-image: url( http://foo.com/icon);font-size:expression(alert(/XSS/));
> ```

Seriously IE?

#### Solution

Even if a browser won't execute `expression()`, we don't want a "URL" to be able to inject CSS rules.

Add quotes and slashes.

```css+php
background-image: url( "<?php echo addcslashes( esc_url_raw( $url ), '"' ); ?>" );
```

### Example: Every Flash Script Ever

The way data is transfered between Flash and JS is very hard to secure correctly.

#### Solution

Don't use flash. If you have to, host it on a cookieless domain.


Header Splitting
----------------

### Example

```php
header( "Location: {$_GET['redirect']}" )
```

#### Attack

http://example.com/?redirect=http://example.com/%0DSet-Cookie:+wordpress_logged_in_123=+

#### Result

> ```
> HTTP/1.1 302 Moved Temporarily
> Location: http://example.com/
> Set-Cookie: wordpress_logged_in_123= 
> ...
> ```

#### Solution

* Mitigated only as of PHP 5.3.11/5.4.0
* Use `wp_redirect()`


PHP Injection
-------------

```php
eval()
```

Obvious.

### Example: Regex

```php
// preg_replace( '//e' ) a.k.a. PREG_REPLACE_EVAL
$content = preg_replace( '!<h1>(.*?)</h1>!e', '"<h1>" . strtoupper( "$1" ) . "</h1>"', $content );
```

Arbitrary code injection.

#### Attack

```html
<h1>{${eval($_GET[php_code])}}</h1>
```

#### Result

> ```php
> // More or less
> $something = eval( $_GET['php_code'] );
> $content = "<h1>" . strtoupper( $$something ) . "</h1>";
> ```

#### Solution

* Use `preg_replace_callback()` instead.


OS Injection
------------

`system()`, `exec()`, `passthru()`, `proc_open()`, `shell_exec`, ``` `` ```

* Don't use them :)
* `escapeshellarg()`


XXE: XML eXternal Entity Injection
----------------------------------

XML is awesome.  It lets you define additional `&entities;`.

XML is extra awesome.  It lets you reference external resources in those definitions.

### Example

```html+php
<?php
$xml = simplexml_load_file( $uploaded_file );
?>
<h1><?php printf( "%s Uploaded!", esc_html( $xml->title ) ); ?></h1>
```

#### Attack

```xml
<?xml version="1.0" encoding="UTF-8" ?> 
<!DOCTYPE something [<!ENTITY awesome SYSTEM "file:///home/web/public_html/wp-config.php">]>
<something>
	<title>&awesome;</title>
</something>
```

#### Result

```html
<h1>define('DB_NAME', 'database_name_here');
define('DB_USER', 'username_here');
define('DB_PASSWORD', 'password_here');
define('SECURE_AUTH_KEY',  'RTYi7!;x+mRUi*+/%]1)A^{lPLNO-Wr [D4VWWB}ebFf?:L[Ko89wb-WS+-LwYE}');
define('NONCE_KEY',        'f0h1rhp4V|+%xc?g|c|Q~`Ih%c 1L.,xxY^M}z87;4-(P;B=VwsoaPqc_AG2tQ]-');
define('SECURE_AUTH_SALT', 'f8zFa!>__yxv5$v:]ad~~8;9/|ai++%F`;x]VgX%>q*dk~O~G7q1X|/jQb12OKp;');
define('NONCE_SALT',       'UqtXSfr@PkErk|JX;wg^L*hsi9%17z0#T[2qdAa!V=MwREk2*q6lj6lK4<=axpU7');
... Uploaded!</h1>
```

#### Solution

```html+php
<?php
libxml_disable_entity_loader( true );
$xml = simplexml_load_file( $uploaded_file );
?>
<h1><?php printf( "%s Uploaded!", esc_html( $xml->title ) ); ?></h1>
```

Everything
----------

Mail headers, TCP packets, SOAP requests, LDAP, JSON, Regex, ... anything with input/output.

Context is king. Every context has its own rules for input validation and output sanitation.

### Tips

* Validate input immediately.
* Keep data "raw".
* Sanitize (escape) data as late as possible.

Easier to read is better than clever to write.

```php
$name = esc_html( $_GET['name'] );
...
echo $name; # is this safe? Depends on ...
```

```php
$name = $_GET['name']
...
echo esc_html( $name ); # is this safe? Yes.
```




Attacks of Intent
=================

Trick a user into doing something they don't want to.

CSRF: Cross Site Request Forgery
--------------------------------

### Attacker's Goal

Without you knowing, trick you into performing some action the attacker can't do for themself.

Note: CSRF mitigation is useless if there is an XSS vulnerability.

### Example

```php
if ( '/account/delete' === $_SERVER['REQUEST_URI'] ) {
	delete_account( current_user_id() );
}
```

#### Attack

```html
<h1>Look at this cool picture!</h1>
<img src="http://example.com/account/delete" />
```

#### Result

Your browser makes a request to that "image" URL and happily sends your example.com cookies along with it.

#### Solution

```php
if ( '/account/delete' === $_SERVER['REQUEST_URI'] ) {
	// Ensures the user followed a link that had been protected with wp_nonce_url()
	check_admin_referer();

	delete_account( current_user_id() );
}
```

Request made != Request intended.

Actions that require permissions should be protected with some secret unknown to the attacker and that will not be automatticaly sent by the browser.

* `wp_nonce_url()`, `wp_nonce_field()`, (`wp_create_nonce()`)
* `check_admin_referer()`, `check_ajax_referer()` (both badly named), (`wp_verify_nonce()`)

Clickjacking
------------

A malicious site frames your site in an iframe and tricks visitors to interact with your site when they think they are interacting with the malicous site.

To do this, the malicious site employs some form of UI trickery.
* Overlaying your site transparently over your site.
* Masking all but some piece of (now generic looking) UI under their site.
* Using a custom CSS mouse cursor to make the visitor think they are clicking one place, when really they are clicking somewhere else.

### Solution

* `X-FRAME-OPTIONS` header.
* Framebusting script for older browsers.

```php
header( 'X-FRAME-OPTIONS: SAMEORIGIN' );
```

```html
<html class="inframe">
<head>
<title>Don't frame me, bro!</title>
<style>
.inframe {
        display: none;
}
</style>
<script type="text/javascript">
if ( self === top ) {
        document.documentElement.className = document.documentElement.className.replace( 'inframe', '' );
} else {
        top.location = self.location;
}
</script>
</head>
```

Cross Iframe Communication
--------------------------

Old techniques of communicating between iframes were insecure.
* Updating URL #fragments
* `window.name`
* etc.

Don't use them.

### Solution

`window.postmessage()`

SSRF: Server Side Request Forgery
---------------------------------

### Attacker's Goal

Trick your server into making a request that only it can make.

This is basically CSRF on the backend.

### Example: Server-side script to make sure user-submitted URL is an image

```php
$url = filter_var( $_POST['url'], FILTER_VALIDATE_URL );
if ( $url ) {
	$image = wp_remote_get( $url );
	if ( preg_match( '/image/', wp_remote_retrieve_header( $image, 'content-type' ) ) {
		die( 'YAY! IMAGE!' );
	}
}

die( 'BOO :( AM SAD' );
```

### Attack

Suppose your server hosts or otherwise has access to some internal service.

`url=http://the-ceo:12345@directory.intranet/users/the-president/?_method=DELETE`

Not so <code>wp_<strong>remote</strong>_get()</code> now, are you?

### Solution

Make sure the URL's protocol, host, port, etc. all makes sense.

See core's `pingback_ping_source_uri()` as an example.

Note: The XXE example above incorporates another SSRF example.


Open Redirects
--------------

Resources on a server that will redirect to any other resource.

### Example

http://www.gravatar.com/avatar/00000000000000000000000000000000?d={url}

```php
if ( ! hash_exists( $hash ) ) {
	wp_redirect( $_GET['d'] );
}
```

#### Attack: Phishing

```html
<p>
	You trust <a href="http://www.gravatar.com/avatar/0?d=http://evil.com/">Gravatar</a>, right?
</p>
```

#### Attack: OAuth

If an OAuth client has an open redirect, and the OAuth service is flexible enough with their redirect URLs, you can steal credentials :)

#### Solution

Only redirect to trusted URLs.

* `wp_safe_redirect()`
* Protect redirects with nonces or some other CSRF mitigation.

Gravatar now serves images directly, rather than redirecting to arbitrary URLs.


Everything
----------

* Confidentality: Does it matter who sees your data?
* Authentication: Do you need to know who sent the data you get? Do you need to know who receive the data you send?
* What would happen if someone tried to forge the request/input?
* What would happen if someone sent the requests you expect, but in the wrong order?
* Don't allow requests to be spoofed: verify intent with secrets unavailable to the outside.


Gotchas
=======

`current_user_can() > is_user_logged_in()`
------------------------------------------

Always verify the user has permission to view, create, edit, etc.


Strict Equality Checking
------------------------
* `===`
* `in_array( $needle, $haystack, true )`

```php
10 == '10ab7c4f2e' # true
 0 == 'a75be5c82d' # true
```

This is especially important when verifying passwords, keys, hashes, etc.

http://www.php.net/manual/en/types.comparisons.php


Your MySQL Collation is Probably Case Insensitive
-------------------------------------------------

```sql
SELECT `id`, `secret` FROM `user_secrets` WHERE `secret` = 'abc123';
--> 1, AbC123
```

### Solution

```sql
SELECT `id`, `secret` FROM `user_secrets` WHERE `secret` = BINARY 'abc123';
--> 7, abc123
```


`hash_hmac() > md5()`
---------------------

```php
$hash = md5( $key . $data ); # Boo
$hash = hash_hmac( 'md5', $data, $key ); # Yay!
```

[Hash length extension attack](http://en.wikipedia.org/wiki/Length_extension_attack)


JS Sanitation Is Important
--------------------------

You don't need quotes to do anything in JavaScript.

`/foo/.substr(1, 3)`

In fact, you only need the following :)

`()[]!+`

http://patriciopalladino.com/blog/2012/08/09/non-alphanumeric-javascript.html


Exercises
=========

### Chat widget (XSS)
* Steal another user's credentials.
* Speak as that user.

### Log parser (SQLI)
* Falsify data.

### Blog Privacy Plugin (In the Wild)
* Read sensitive date.
* Write data without permission.

### OPML Importer (XXE)
* Get a list of PHP files on the server.
* Read one.

### PWN
* Use a CSRF
* with an Open Redirect
* to steal credentials of a registered user
* so you can upload an XML file
* that SSRFs ... something
* Needs work :)

Links
=====

* http://phpsecurity.readthedocs.org/
* http://owasp.com/index.php/Main_Page
