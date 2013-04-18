Function.prototype.bind = Function.prototype.bind || function (thisp) {
	var fn = this;
	return function () {
		return fn.apply( thisp, arguments );
	};
};

(function( $ ) {
	var ChatSploitProperties = {
		$el: false,
		$form: false,
		$chat: false,
		$list: false,

		since: 0,
		interval: false,
		ajaxURL: false,

		start: start,
		stop: stop,
		send: send,
		poll: poll,
		receive: receive,
		clear: clear
	};

	function ChatSploit( $el ) {
		this.start( $el );
	}

	ChatSploit.chats = {};
	ChatSploit.startAll = startAll;
	ChatSploit.stopAll = stopAll;

	function startAll() {
		console.log( "START ALL" );
		$( '.widget_chat_sploit' ).each( function() {
			new ChatSploit( this );
		} );
	}

	function stopAll() {
		console.log( "STOP ALL" );
		$.each( this.chats, function( id, chat ) {
			chat.stop();
		} );

		$.each( this.chats, function( id ) {
			delete( ChatSploit.chats[id] );
		} );
	}


	function start( $el ) {
		console.log( "START" );
		this.$el = $( $el );
		this.$form = this.$el.find( 'form' );
		this.$chat = this.$el.find( '.chat' );
		this.$list = this.$chat.find( 'dl' );

		this.ajaxURL = this.$form.attr( 'action' );
		this.$form.submit( this.send.bind( this ) );

		this.interval = setInterval( this.poll.bind( this ), 3000 );
		this.since = (new Date).toISOString();

		ChatSploit.chats[this.$el.attr( 'id' )] = this;
	}

	function stop() {
		console.log( "STOP" );
		clearInterval( this.interval );
	}

	function send( event ) {
		var chat = $( '<chat />' );

		console.log( "SEND" );
		event.preventDefault();

		chat.text( this.$form.find( 'textarea' ).val() );

		$.post( this.ajaxURL, chat.get(0).outerHTML ).done( this.clear.bind( this ) );
	}

	function clear() {
		console.log( "CLEAR" );
		this.$form.trigger( 'reset' );
	}

	function poll() {
		console.log( "POLL" );
		$.get( this.ajaxURL, {
			since: this.since
		} ).done( this.receive.bind( this ) );
	}

	function receive( data, status, xhr ) {
		console.log( "RECEIVE" );
		var $holder = $( '<div />' );
		var scroll = false;

		this.since = data.since;

		$.each( data.chats, function( key, chat ) {
			$( [
				$( '<dt>' + chat.author + '</dt>' ).get(0),
				$( '<dd>' + chat.text + '</dt>' ).get(0)
			] ).appendTo( $holder );
		} );

		if ( $holder.children().size() ) {
			scroll = true;
		}

		this.$list.append( $holder.children() );

		if ( scroll ) {
			this.$chat.scrollTop( this.$list.outerHeight() );
		}
	}



	$.extend( ChatSploit.prototype, ChatSploitProperties );

	window.ChatSploit = ChatSploit;


	$( function() {
		window.ChatSploit.startAll();
	} );
})(jQuery);
