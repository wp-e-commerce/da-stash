<?php

if ( ! class_exists( 'DA_Stash' ) ) {

class DA_Stash {

	const DEBUG                          = false;
	const DEBUG_HTTP                     = false;

	public static $client_id             = null;
	public static $client_secret         = null;

	public static $old_user_token        = array();
	public static $access_token          = '';
	public static $refresh_token         = '';

	public static $entries               = null;

	const AUTHORIZATION_ENDPOINT         = 'https://www.deviantART.com/oauth2/draft15/authorize';
	const TOKEN_ENDPOINT                 = 'https://www.deviantART.com/oauth2/draft15/token';

	const PLACEBO_ENDPOINT               = 'https://www.deviantART.com/api/draft15/placebo';
	const WHOAMI_ENDPOINT                = 'https://www.deviantART.com/api/draft15/user/whoami';
	const STASH_DELTA_ENDPOINT           = 'https://www.deviantART.com/api/draft15/stash/delta';

	/* yet to implement */
	const STASH_SUBMIT_ENDPOINT          = 'https://www.deviantART.com/api/draft15/stash/submit';
	const STASH_MOVE_ENDPOINT            = 'https://www.deviantART.com/api/draft15/stash/move';
	const STASH_FOLDER_ENDPOINT          = 'https://www.deviantART.com/api/draft15/stash/folder';
	const STASH_SPACE_ENDPOINT           = 'https://www.deviantART.com/api/draft15/stash/space';
	const STASH_METADATA_ENDPOINT        = 'https://www.deviantART.com/api/draft15/stash/metadata';
	const STASH_MEDIA_ENDPOINT           = 'https://www.deviantART.com/api/draft15/stash/media';
	const DAMNTOKEN_ENDPOINT             = 'https://www.deviantART.com/api/draft15/user/damntoken';

	const EXCEPTION_UNKNOWN              = 'DA_STASH_EXCEPTION_UNKNOWN';
	const EXCEPTION_NOT_LOGGED_IN        = 'DA_STASH_EXCEPTION_NOT_LOGGED_IN';
	const EXCEPTION_NOT_CONFIGURED       = 'DA_STASH_EXCEPTION_NOT_CONFIGURED';
	const EXCEPTION_NO_DA_AUTH           = 'DA_STASH_EXCEPTION_NO_DA_AUTH';
	const EXCEPTION_REFRESH_TOKEN_FAILED = 'DA_STASH_EXCEPTION_REFRESH_TOKEN_FAILED';
	const EXCEPTION_HTTP_REQUEST_ERROR   = 'DA_STASH_EXCEPTION_HTTP_REQUEST_ERROR';
	const EXCEPTION_INVALID_GRANT_TOKEN  = 'DA_STASH_EXCEPTION_INVALID_GRANT_TOKEN';
	const EXCEPTION_INVALID_TOKEN        = 'DA_STASH_EXCEPTION_INVALID_TOKEN';

	public static function controller () {
		// CONTROLLER

		if ( self::DEBUG || self::DEBUG_HTTP ) error_log( '---- ' . $_GET['da-stash'] . ' -> ' . $_GET['action'] );

		if ( self::DEBUG_HTTP ) {
			add_action( 'http_api_debug', array( 'DA_Stash', 'http_api_debug'), 10, 5 );
		}

		$result = null;
		try {
			switch ( $_GET['action'] ) {
				case "authorize-user":
					self::authorize_user();
					break;
				case "auth-token":
					self::auth_token( $_GET['code'] );
					self::output_auth_success_page();
					break;
				case "placebo":
					$result = self::placebo();
					if ( self::DEBUG ) error_log( 'placebo result: ' . print_r( $result, true ) );
					break;
				case "delta":
					$result = self::stash_delta();
					break;
				case "whoami":
					$result = self::whoami();
					break;
				case "test-success":
					self::output_auth_success_page();
					break;
				case "disconnect":
					if ( 1 == $_POST['disconnect'] ) {
						$result = self::disconnect_user( get_current_user_id() );
					} else {
						self::json_http_response_unrecognized_action();
					}
					break;
				default:
					self::json_http_response_unrecognized_action();
			}
		} catch ( Exception $e ) {
			switch ( $e->getMessage() ) {
				case self::EXCEPTION_NOT_CONFIGURED :
					$error = self::json_error_obj( 'not_configured_correctly', __( 'Bad Request - DA Stash: Client ID or Client Secret not set up. Visit the deviantART Sta.sh settings page in the admin panel.', DA_STASH_I18N ) );
					self::json_http_response( $error, 400 );
					break;
				case self::EXCEPTION_NOT_LOGGED_IN :
					$error = self::json_error_obj( 'user_not_logged_in', __( 'Unauthorised - DA Stash: User must be logged in.', DA_STASH_I18N) );
					self::json_http_response( $error, 401 );
					break;
				case self::EXCEPTION_NO_DA_AUTH :
					$error = self::json_error_obj( 'user_has_no_da_auth', __( 'Unauthorised - DA Stash: User has not been authenticated with deviantART.', DA_STASH_I18N) );
					self::json_http_response( $error, 401 );
					break;
				case self::EXCEPTION_HTTP_REQUEST_ERROR :
					$error = self::json_error_obj( 'http_request_error', __( 'We had a problem talking to deviantART. This is most likely a service timeout.', DA_STASH_I18N) );
					self::json_http_response( $error, 500 );
					break;
				case self::EXCEPTION_REFRESH_TOKEN_FAILED :
					$error = self::json_error_obj( 'refresh_token_failed', __( "We tried to refresh the user's auth token and it failed. The user might have deauthorised their account against your site.", DA_STASH_I18N) );
					self::json_http_response( $error, 401 );
					break;
				case self::EXCEPTION_INVALID_TOKEN :
					$error = self::json_error_obj( 'token_failed', __( "We tried to talk to deviantART but they rejected the user's authorisation token. The user probably deauthorised their account against your site.", DA_STASH_I18N) );
					self::json_http_response( $error, 401 );
					break;
				default:
					if ( self::DEBUG ) error_log( 'rethrowing ' . $e->GetMessage() . ' ; it was not caught by DA_Stash::controller()' );
					throw $e;
					break;
			}
		}

		self::json_http_response( $result, 200 );
	}

	public static function get_redirect_uri( $action_name = '') {
		$action_fragment = '';

		if ( ! empty( $action_name ) ) {
			$action_fragment = '&action=' . $action_name;
		}

		return site_url('?da-stash=1' . $action_fragment);
	}

// AUTHORISATION

	public static function authorize_user() {
		// redirect user to API

		self::load_client_identity();

		$parameters = array(
			'response_type' => 'code',
			'client_id'     => self::$client_id,
			'redirect_uri'  => self::get_redirect_uri('auth-token') // used here to redirect
		);

		$auth_url = self::AUTHORIZATION_ENDPOINT . '?' . http_build_query( $parameters, null, '&' );

		if ( self::DEBUG ) error_log('redirecting user to dA...');

		header('Location: ' . $auth_url);
		exit();
	}

	public static function auth_token( $code ) {
		// get the auth token using the provided code

		self::load_client_identity();

		$body = array( 'code' => $code );

		$result = self::wp_oauth_token_request( 'authorization_code', $body );

		if ( $result->status === "success" ) {
			self::store_user_auth_token( get_current_user_id(), $result );
			return true;
		} else {
			if ($result->body->error === 'invalid_grant') {
				throw new Exception( self::EXCEPTION_INVALID_GRANT_TOKEN );
			}
		}

		return false;
	}

	public static function refresh_auth_token( $refresh_token ) {
		// get the auth token using the refresh token

		self::load_client_identity();

		$result = self::wp_oauth_token_request( 'refresh_token', array( 'refresh_token' => $refresh_token ) );

		if ( $result->status === "success" ) {
			self::store_user_auth_token( get_current_user_id(), $result );
		} else {
			throw new Exception( self::EXCEPTION_REFRESH_TOKEN_FAILED );
		}
	}

	public static function output_auth_success_page() {
		?>
		<html>
			<head>
				<title><?php _e( 'deviantART Connection Successful', DA_STASH_I18N ); ?></title>
				<style type='text/css'>
				body { font: 12px Arial, Helvetica, sans-serif; }
				.dev { font-size: 10px; color: #ccc;}
				</style>
			</head>
			<body>
				<div id="message" style="display:none;">
					<p>
						<?php echo sprintf( __( 'Your deviantART account is now connected to %1$s.', DA_STASH_I18N ), get_bloginfo('name') ); ?>
						<?php _e( 'You may now close this window.', DA_STASH_I18N ); ?>
					</p>
				</div>
				<script type='text/javascript'>
					if (window.opener && window.opener.DA_Stash && window.opener.DA_Stash.onConnectSuccess) {
						if ( window.opener.DA_Stash.onConnectSuccess() ) {
							window.close();
						}
					} else if (<?php echo (current_user_can('manage_options') ? 'true' : 'false'); ?>) {
						var d = document.getElementById('message');
						d.innerHTML = d.innerHTML + "<div class='dev'><p><strong><?php _e( 'Note to WordPress Developer:', DA_STASH_I18N ); ?></strong> <?php _e( 'DA_Stash.onConnectSuccess JavaScript function is not yet implemented on the window that opened this window.', DA_STASH_I18N ); ?><br/><?php _e( 'Implement this function in the global scope to recieve that this event has occured. If the function returns true, this window will self-close.', DA_STASH_I18N ); ?></p></div>";
					}
					setTimeout(function () {
						document.getElementById('message').style.display = '';
					}, 1500);
				</script>
			</body>
		</html>
		<?php
		exit();
	}

// API CALLS

	public static function placebo() {
		$body = array();

		$args = self::get_template_user_oauth_args();

		$result = self::wp_oauth_request( self::PLACEBO_ENDPOINT, $body, $args );

		return $result;
	}

	public static function whoami() {
		$body = array();

		$args = self::get_template_user_oauth_args();

		$result = self::wp_oauth_request( self::WHOAMI_ENDPOINT, $body, $args );

		return $result;
	}

	public static function stash_delta() {

		self::$entries = new DA_Stash_Entries();

		$next_offset = "";

		do {
			$args = self::get_template_user_oauth_args();

			$parameters = array(
				'cursor'      => self::$entries->delta_cursor,
			);
			if ($next_offset !== "") {
				$parameters['next_offset'] = $next_offset;
			}

			$url = self::STASH_DELTA_ENDPOINT . '?' . http_build_query($parameters, null, '&');
			$result = self::wp_oauth_request( $url, null, $args );
			$next_offset = self::$entries->handle_delta( $result );

		} while ( $next_offset !== true );

		self::$entries->store_to_user();
		return self::$entries->entries;
	}

// OAUTH

	public static function get_template_user_oauth_args() {
		self::load_user_oauth_token();

		$args = array(
			'headers' => array(
				'Authorization' => 'Oauth ' . self::$access_token
			)
		);

		return $args;
	}

	public static function wp_oauth_token_request( $grant_type, $vars ) {
		$body = array(
			'client_id'     => self::$client_id,
			'client_secret' => self::$client_secret,
			'grant_type'    => $grant_type,
			'redirect_uri'  => self::get_redirect_uri( 'auth-token' ) // used here to match code with authorisation
		);

		$body = array_merge( $body, $vars );

		$result = self::wp_oauth_request( self::TOKEN_ENDPOINT, $body);

		return $result;
	}

	public static function wp_oauth_request( $endpoint, $body = array(), $args = array() ) {
		$default_args = array(
			'method'    => 'POST',
			'body'      => $body
		);

		$args = array_merge( $default_args, $args );

		$response = wp_remote_request( $endpoint, $args );

		if ( is_wp_error( $response ) ) {
			if ( $response->errors['http_request_failed'] ) {
				throw new Exception( self::EXCEPTION_HTTP_REQUEST_ERROR );
			}
			if ( self::DEBUG ) error_log( "DA_Stash: wp_oauth_request: wp_remote_post returned WP_Error: \n" . print_r( $response, true ) );
			throw new Exception( self::EXCEPTION_UNKNOWN );
		}

		if ( $response['response']['code'] !== 200 ) {
			$body = json_decode( $response['body'] );
			if ( $body->status === 'error' ) {
				if ( $body->error === "invalid_token" ) {
					throw new Exception( self::EXCEPTION_INVALID_TOKEN );
				}
			}
			if ( self::DEBUG ) error_log( "DA_Stash: wp_oauth_request: wp_remote_post returned unknown HTTP status code: \n" . print_r( $response, true ) );
			throw new Exception( self::EXCEPTION_UNKNOWN );
		}
		return json_decode( wp_remote_retrieve_body( $response ) );
	}

	public static function disconnect_user( $user_id = null ) {
		$user_id = self::user_id_or_current_user( $user_id );

		self::remove_user_auth_token( $user_id );

		return self::json_success_obj();
	}

// UTILITIES

	public static function json_http_response_unrecognized_action() {
		$error = self::json_error_obj( 'unrecognized_action', __( 'Bad Request - DA Stash: Unrecognized action', DA_STASH_I18N ) );
		if ( self::DEBUG ) error_log( print_r( $error, true ) );
		self::json_http_response( $error, 400 );
	}

	public static function json_success_obj() {
		$obj = new stdClass();

		$obj->success = $success;
		return $obj;
	}


	public static function json_error_obj($error, $error_description) {
		$obj = new stdClass();

		$obj->error             = $error;
		$obj->error_description = $error_description;

		return $obj;
	}

	public static function json_http_response( $obj, $status_code = 200, $message = "") {
		if ($message == "" && $obj->error_description) {
			$message = $obj->error_description;
		}
		$protocol = ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' );
		header( $protocol . ' ' . $status_code . ' ' . $message );
		header( 'Content-type: application/json' );
		exit( json_encode( $obj ) );
	}

// WORDPRESS SPECIFICS

	public static function load_client_identity() {
		$options = get_option( 'da_stash', array('') );
		self::$client_id        = $options['client_id'];
		self::$client_secret    = $options['client_secret'];
		if ( self::$client_id <= 0 || strlen( self::$client_secret ) != 32 ) {
			throw new Exception( self::EXCEPTION_NOT_CONFIGURED );
		}
	}

	public static function load_user_oauth_token( $user_id = null ) {

		$user_id = self::user_id_or_current_user( $user_id );

		self::check_user_authed( $user_id );

		$result = get_user_meta( $user_id, 'da_stash_auth', true );
		if ( self::DEBUG ) error_log( 'DEBUG: loaded oauth: ' . print_r( $result, true ) );

		self::$old_user_token   = $result;
		self::$access_token     = $result['access_token'];
		self::$refresh_token    = $result['refresh_token'];

		if ( time() >= (int)$result['expires'] ) {
			// access token has expired, lets renew it now
			if ( self::DEBUG ) error_log( 'DEBUG: access_token is stale, refreshing...' );
			self::refresh_auth_token( self::$refresh_token );
		}
	}

	public static function store_user_auth_token( $user_id, $result ) {

		$new_user_token = self::convert_auth_result_to_array( $result );

		if ( self::DEBUG ) error_log( 'DEBUG: storing new_user_token: ' . print_r( $new_user_token, true ) );

		$success = update_user_meta( $user_id, 'da_stash_auth', $new_user_token, self::$old_user_token );

		if ( $success ) {
			self::$old_user_token   = $new_user_token;
			self::$access_token     = $result->access_token;
			self::$refresh_token    = $result->refresh_token;
		}

		return $success;
	}

	public static function remove_user_auth_token( $user_id ) {

		delete_user_meta( $user_id, 'da_stash_auth' );
		delete_user_meta( $user_id, 'da_stash_entries' );
		delete_user_meta( $user_id, 'da_stash_delta_cursor' );

		return true;
	}

	public static function check_user_logged_in() {
		if ( get_current_user_id() === 0 ) {
			throw new Exception( self::EXCEPTION_NOT_LOGGED_IN );
		}
		return true;
	}

	public static function check_user_authed( $user_id = null ) {
		if ( ! $user_id ) $user_id = get_current_user_id();

		$result = get_user_meta( $user_id, 'da_stash_auth', true );

		if ( ! is_array( $result ) ) {
			throw new Exception( self::EXCEPTION_NO_DA_AUTH );
		}
		return true;
	}

	public static function is_user_authed( $user_id = null ) {
		try {
			DA_Stash::check_user_authed( $user_id );
		} catch ( Exception $e ) {
			return false;
		}
		return true;
	}

	public static function user_id_or_current_user( $user_id = null ) {
		if ( ! $user_id ) {
			self::check_user_logged_in();
			$user_id = get_current_user_id();
		}
		return $user_id;
	}

	public static function convert_auth_result_to_array( $result ) {
		$token = array();

		$token['access_token']  = $result->access_token;
		$token['refresh_token'] = $result->refresh_token;
		$token['expires']       = time() + $result->expires_in;

		return $token;
	}

	public static function http_api_debug( $response, $type, $class, $args, $url ) {
		error_log( 'Request URL: ' . var_export( $url, true ) );
		error_log( 'Request Args: ' . var_export( $args, true ) );
		error_log( 'Request Response : ' . var_export( $response, true ) );
	}


} // end DA_Stash


}