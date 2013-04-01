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


XSS
---

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

### Example

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

```html?php
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
<h1>define(&#039;DB_NAME&#039;, &#039;database_name_here&#039;);
define(&#039;DB_USER&#039;, &#039;username_here&#039;);
define(&#039;DB_PASSWORD&#039;, &#039;password_here&#039;);
define(&#039;SECURE_AUTH_KEY&#039;,  &#039;RTYi7!;x+mRUi*+/%]1)A^{lPLNO-Wr [D4VWWB}ebFf?:L[Ko89wb-WS+-LwYE}&#039;);
define(&#039;SECURE_AUTH_SALT&#039;, &#039;f8zFa!&gt;__yxv5$v:]ad~~8;9/|ai++%F`;x]VgX%&gt;q*dk~O~G7q1X|/jQb12OKp;&#039;);
define(&#039;NONCE_KEY&#039;,        &#039;f0h1rhp4V|+%xc?g|c|Q~`Ih%c 1L.,xxY^M}z87;4-(P;B=VwsoaPqc_AG2tQ]-&#039;);
define(&#039;NONCE_SALT&#039;,       &#039;UqtXSfr@PkErk|JX;wg^L*hsi9%17z0#T[2qdAa!V=MwREk2*q6lj6lK4&lt;=axpU7&#039;);
... Uploaded!</h1>
```

#### Solution

```html?php
libxml_disable_entity_loader( true );
$xml = simplexml_load_file( $uploaded_file );
?>
<h1><?php printf( "%s Uploaded!", esc_html( $xml->title ) ); ?></h1>


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
....
echo $name; # is this safe? Depends on ...
```

```
$name = $_GET['name']
....
echo esc_html( $name ); # is this safe? Yes.
```




Attacks of Intent
=================

Trick a user into doing something they don't want to.

CSRF
----

`<img src="http://example.com/delete-my-stuff-now/" />`


Clickjacking
------------

Transparent overlays. Cursor repositioning.

Cross Iframe Communication
--------------------------

SSRF
----

Internal IPs, different protocols, username:password

Open Redirects
--------------

Consider OAuth...

Everything
----------
 * Confidentality: Does it matter who sees your data?
 * Authentication: Do you need to know who sent the data you get? Do you need to know who receive the data you send?
 * What would happen if someone tried to forge the request/input?
 * What would happen if someone sent the requests you expect, but in the wrong order?
 * Don't allow requests to be spoofed: verify intent with secrets unavailable to the outside.


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
* that SSRFs


* Use an Open Redirect
* Use an XSS
* To CSRF
* an 

Todo
====
* Strict equality checking?

Links
=====

http://phpsecurity.readthedocs.org/
