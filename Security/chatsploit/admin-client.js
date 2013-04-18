var casper = require('casper').create( {
	waitTimeout: 30000,
	timeout: 30000,
	verbose: true,
	logLevel: 'debug',
} );

casper.start( 'http://hacek.local/wordpress/wp-login.php?redirect_to=http://hacek.local/wordpress/', function() {
	this.fill('form', { log: 'admin', pwd: 'admin' }, true );
} );

casper.waitForResource( 'chatsploit/script.js', function() {
	this.log( 'chatsploit loaded', 'info' );
} );

casper.waitForSelector( '.widget_chat_sploit dt', function() {
	this.test.pass( 'selector found' );
	this.echo( this.evaluate( function() {
		return document.cookie;
	} ) );
}, function() {
	this.test.fail( 'selector was not found' );
});

casper.wait( 5000 );
    
casper.run();
