<?php

/*
 * Plugin Name: Chat Sploit!
 * Description: Chat Widget vulnerable to XXS.  Don't fix it.  Figure out how to exploit it. Goal: Log in as author, and make admin say something.
 * Author: malwaffe
 */

class Chat_Sploit {
	static $instance;

	private $post_id = 0;

	static function init() {
		if ( ! self::$instance ) {
			self::$instance = new Chat_Sploit;
		}

		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', array( $this, 'setup_post' ) );

		add_action( 'widgets_init', array( $this, 'register_widget' ) );
		add_action( 'wp_print_styles', array( $this, 'enqueue_styles' ) );

		add_action( 'wp_ajax_chatsploit', array( $this, 'ajax' ) );
		add_action( 'wp_ajax_nopriv_chatsploit', array( $this, 'nopriv_ajax' ) );
	}

	/*
	 * Chat messages are stored as comments under a global post.
	 */
	function setup_post() {
		register_post_type( 'chatsploit', array(
			'public' => false,
			'supports' => array(
				'author', 'editor',
			),
			'rewrite' => false,
			'query_var' => false,
			'can_export' => false,
		) );

		$this->post_id = get_option( 'chat_sploit_post', 0 );

		if ( $this->post_id ) {
			return;
		}

		$this->post_id = (int) wp_insert_post( array(
			'post_content' => 'Chat Sploit',
			'post_type' => 'chatsploit',
			'post_status' => 'publish',
		) );

		update_option( 'chat_sploit_post', $this->post_id );
	}

	function register_widget() {
		register_widget( 'Chat_Sploit_Widget' );
	}

	function enqueue_styles() {
		if ( is_active_widget( false, false, 'chat_sploit' ) ) {
			wp_enqueue_style( 'chatsploit', plugins_url( 'style.css', __FILE__ ), array(), mt_rand() );
		}
	}

	/*
	 * Handles authenticated POST requests (chat submits) and authenticated GET requests (chat polls)
	 */
	function ajax() {
		if ( 'POST' === strtoupper( $_SERVER['REQUEST_METHOD'] ) ) {
			return $this->submit();
		}

		return $this->read();
	}

	/*
	 * Handles unauthenticated GET requests (chat polls)
	 */
	function nopriv_ajax() {
		if ( 'POST' === strtoupper( $_SERVER['REQUEST_METHOD'] ) ) {
			// Unauthenticated users can't chat...
			die( '-1' );
		}

		// ... but they can see the chat: "I could be that cool if I registered for an account!" :)
		return $this->read();
	}

	/*
	 * Process a chat submission. Store new chat as a comment.
	 */
	function submit() {
		$user = wp_get_current_user();

		$comment_id = (int) wp_insert_comment( array(
			'comment_post_ID' => (int) $this->post_id,
			'comment_author' => $user->user_login,
			'comment_author_IP' => $_SERVER['REMOTE_ADDR'],
			'comment_date' => current_time( 'mysql' ),
			'comment_date_gmt' => current_time( 'mysql', true ),
			'comment_content' => $_POST['text'],
			'comment_approved' => 1,
			'comment_type' => 'chatsploit',
			'user_id' => $user->ID,
		) );

		die( "$comment_id" );
	}

	/*
	 * Get recent chats.
	 */
	function read() {
		global $wpdb;

		header( 'Content-Type: application/json' );

		$since = (int) $_GET['since'];
		$now = time();
		if ( $now - 600 < $since ) {
			$since = gmdate( 'Y-m-d H:i:s', $since );
		} else {
			$since = gmdate( 'Y-m-d H:i:s', $now );
		}

		$chats = $wpdb->get_results( $wpdb->prepare(
			"SELECT `comment_author` AS author, `comment_content` AS text, `comment_date_gmt` AS time FROM `$wpdb->comments` " .
			"WHERE `comment_post_ID` = %d AND `comment_type` = 'chatsploit' AND `comment_date_gmt` > %s ORDER BY `comment_date_gmt` ASC",

			$this->post_id, $since
		) );

		$last = end( $chats );
		if ( $last ) {
			$since = $last->time;
		}

		$since = date_create( $since, timezone_open( 'UTC' ) );
		$since = $since->getTimestamp();

		die( json_encode( compact( 'since', 'chats' ) ) );
	}
}

class Chat_Sploit_Widget extends WP_Widget {
	function __construct() {
		parent::__construct(
			'chat_sploit',
			'Chat Sploit!'
		);
	}

	function widget( $args, $instance ) {
		wp_enqueue_script( 'chatsploit', plugins_url( 'script.js', __FILE__ ), array( 'jquery' ), mt_rand(), true );

		$title = apply_filters( 'widget_title', $instance['title'] );
		echo $args['before_widget'];
		if ( strlen( $title ) ) {
			echo $args['before_title'] . $title . $args['after_title'];
		}
?>
		<div class="chat"><dl></dl></div>
		<form method="POST" action="<?php echo esc_url( admin_url( 'admin-ajax.php?action=chatsploit' ) ); ?>">
<?php		if ( is_user_logged_in() ) : ?>
			<textarea name="text"></textarea>
			<p class="submit">
				<input type="submit" value="Say it" />
			</p>
<?php		endif; ?>
		</form>
<?php

		echo $args['after_widget'];
	}

	public function update( $new_instance, $old_instance ) {
		return array(
			'title' => wp_kses( $new_instance['title'], 'data' )
		);
	}

	public function form( $instance ) {
		if ( isset( $instance[ 'title' ] ) ) {
			$title = $instance[ 'title' ];
		} else {
			$title = 'Chat Sploit!';
		}
		?>
		<p>
		<label for="<?php echo $this->get_field_id( 'title' ); ?>">Title:</label>
		<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" />
		</p>
		<?php
	}
}

add_action( 'plugins_loaded', array( 'Chat_Sploit', 'init' ) );
