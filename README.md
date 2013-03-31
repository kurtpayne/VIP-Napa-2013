Injection
---------

Unescaped data tricks the service into betraying itself.

Each data format or protocol needs its own methods of sanitizing data.

Context is king:  Don't sanitize SQL queries with `wp_specialchars()`. Don't sanitize URLs with `$wpdb->escape()`.


### SQL Injection

Attacker's goal: read from or write to your site's database.

```php
"SELECT * FROM `users` WHERE `name` = '{$_GET['name']}'"
```

http://example.com/?name=foo%27%3B+DROP+TABLE+%60users%60%3B%23%27Comment

> ```sql
> SELECT * FROM `users` WHERE `name` = 'foo'; DROP TABLE `users`;#'
> ```

* `$wpdb->prepare()`/`$wpdb->insert()`/`$wpdb->update()`
* `like_escape()`


### XSS

Attacker's goal: Inject Javascript into your site's HTML.

#### Plain Ol' XSS

```html+php
<a href="http://example.com/hello/?ref=<?php echo $_GET['ref']; ?>">Hello</a>
```

http://example.com/?ref=foo%22+onclick%3D%22javascript%3Aalert%28%2FXSS%2F%29

> ```html
> <a href="http://example.com/hello/?ref=foo" onclick="javascript:alert(/XSS/)">Hello</a>
> ```

* `esc_html()`, `esc_attr()`, `esc_url()`
* `esc_js()`, `json_encode()`
* Aside: you can break XML output if it's not run through `ent2ncr()` first.

#### It happens in JS too

```js
$( '#error' ).append( "Invalid input: " + $( '#input' ).val() );
```

```js
val html = '<a href="' + document.location + '?fun=1">Click me for fun!</a>';
```

[http://example.com/#"onclick="alert(/XSS/);"data-foo="][fancy-xss]
[fancy-xss]: <http://example.com/#"onclick="alert(/XSS/);"data-foo=">

> ```js
> val html = '<a href="http://example.com/#"onclick="alert(/XSS/);"data-foo="?fun=1">Click me for fun!</a>';
> ```

```js
window.location = query_string['url'];
```

http://example.com/?url=javascript%3Aalert%28%2FXXS%2F%29

> ```js
> window.location = 'javascript:alert(/XSS/)';
> ```


* Building HTML from strings is just like PHP: You have to escape everything
* Difference between jQuery's `.html()` and `.text()`
* Use jQuery's `.attr()`


#### And CSS

```css+php
background-image: url( <?php echo esc_url_raw( $url ); ?> );
```

`http://foo.com/icon);font-size:expression(alert(/XSS/)`

> ```css
> background-image: url( http://foo.com/icon);font-size:expression(alert(/XSS/));
> ```


Seriously IE?

Even if a browser won't execute `expression()`, we don't want a "URL" to be able to inject CSS rules.

```css+php
background-image: url( "<?php echo addcslashes( esc_url_raw( $url ), '"' ); ?>" );
```

#### Every flash script ever.

The way data is transfered between Flash and JS is very hard to secure correctly.


### Header Splitting

```php
header( "Location: {$_GET['redirect']}" )
```

> `http://example.com/?redirect=http://example.com/%0DSet-Cookie:+wordpress_logged_in_123=+`

> ```
> HTTP/1.1 302 Moved Temporarily
> Location: http://example.com/
> Set-Cookie: wordpress_logged_in_123= 
> ...
> ```

* Mitigated only as of PHP 5.3.11/5.4.0
* Use `wp_redirect()`

### PHP Injection

* `eval()`
* `preg_replace( '//e' )` a.k.a. `PREG_REPLACE_EVAL`

### OS Injection

`system()`, `exec()`, `passthru()`, `proc_open()`, `shell_exec`, `` `` ``

### XXE



### Everything




Security "Guide" - Don't learn what to do - learn how to think


 * Think about what/how data moves from one place to another, and over what channels it moves: I/O
 * Each input needs its own validation based on expected data type
 * Each output needs its own sanitation based on context (output channel)
 * http://codex.wordpress.org/Data_Validation




Attacks of Intent: Trick a user into doing something they don't want to.
 * CSRF: <img src="http://example.com/delete-my-stuff-now/" />
 * Clickjacking: transparent overlays,
 * Communication between iframes
 * SSRF: internal IPs, different protocols, username:password, 
 * Open redirects - consider OAuth...

How to approach the problem
 * Confidentality: Does it matter who sees your data?
 * Authentication: Do you need to know who sent the data you get? Do you need to know who receive the data you send?
 * What would happen if someone tried to forge the request/input?
 * Sent the requests you expect, but in the wrong order?
 * Don't allow requests to be spoofed: verify intent with secrets unavailable to the outside


Easier to read is better than clever to write
$name = esc_html( $_GET['name'] );
....
echo $name

$name = $_GET['name']
....
echo esc_html( $name )

 



Blog Privacy plugins
XXE SSRF
Strict equality checking
