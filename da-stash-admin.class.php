<?php

if ( ! class_exists( 'DA_Stash_Admin' ) ) {

class DA_Stash_Admin {

	const DEBUG = false;

	const WP_OPTIONS_ENTRY = 'da_stash';

	public static function init() {


		add_action( 'admin_enqueue_scripts', array( 'DA_Stash_Admin', 'admin_enqueue_scripts' ) );
		add_action( 'admin_head',            array( 'DA_Stash_Admin', 'admin_head' ) );
		add_action( 'admin_footer',          array( 'DA_Stash_Admin', 'admin_footer' ) );
		add_action( 'admin_notices',         array( 'DA_Stash_Admin', 'admin_notices') );

		// SETTINGS PAGE

		add_options_page(
			__( 'deviantART sta.sh Integration Settings', DA_STASH_I18N ),
			__( 'deviantART sta.sh', DA_STASH_I18N ), 'manage_options',
			'da_stash_settings',
			array('DA_Stash_Admin', 'settings_page' )
		);

		self::settings_page_init();

		// USER PROFILE PAGE

		add_action( 'profile_personal_options', array( 'DA_Stash_Admin', 'profile_personal_options' ) );

		// PLUGIN PAGE
		add_filter( 'plugin_row_meta', array( 'DA_Stash_Admin', 'plugin_row_meta' ), 10, 2 );

	}

	public static function settings_page_init() {
		add_settings_section(
			'da_stash_application_client_settings', // html id tag for section
			__( 'Application Client Settings', DA_STASH_I18N ), // section title text
			array( 'DA_Stash_Admin', 'settings_section_callback_application_client_settings' ), // callback, outputs section support text
			'da_stash_settings' // name of the settings page to display the section - see slug when you add the options page
		);

		// register the storage mechanism and form validation
		register_setting( 'da_stash_options', self::WP_OPTIONS_ENTRY, array( 'DA_Stash_Admin', 'validate_options' ) );


		// set up fields to feed into da_stash
		add_settings_field(
			'da_stash_options_client_id', // HTML field ID
			__( 'Client ID', DA_STASH_I18N ), // field title text
			array( 'DA_Stash_Admin', 'settings_field_callback_options_client_id' ), // callback, outputs html for fields
			'da_stash_settings', // settings page in which to show the section
			'da_stash_application_client_settings'
		);

		add_settings_field(
			'da_stash_options_client_secret', // HTML field ID
			__( 'Client Secret', DA_STASH_I18N ), // field title text
			array( 'DA_Stash_Admin', 'settings_field_callback_options_client_secret' ), // callback, outputs html for fields
			'da_stash_settings', // settings page in which to show the section
			'da_stash_application_client_settings'
		);

	}

	public static function settings_page() {
		?>
		<div>
			<h2><?php _e( 'deviantART Sta.sh', DA_STASH_I18N ); ?></h2>
			<p><?php _e( "Allows your site's users to authenticate their user account with their sta.sh account, enabling your site to access their digital creations.", DA_STASH_I18N ); ?></p>

			<form action="options.php" method="post">
				<?php settings_fields( 'da_stash_options' ); // initialises form with nonce, actions, etc. ?>
				<?php do_settings_sections( 'da_stash_settings' ); // outputs the fields ?>
				<br />
				<input class="button-primary" name="Submit" type="submit" value="<?php esc_attr_e( __( 'Save Changes', DA_STASH_I18N ) ); ?>" />
			</form>
		</div>
		<?php
	}

	public static function settings_section_callback_application_client_settings() {
		?>
		<p>
			<?php echo sprintf( __('Copy the values from your %1$s page and paste them here.', DA_STASH_I18N ),
					sprintf( __( '<a href="%1$s">deviantART Settings > My Applications</a>', DA_STASH_I18N ), 'https://www.deviantART.com/settings/myapps' )
			); ?>
		</p>
		<?php
	}

	public static function settings_field_callback_options_client_id( $args ) {
		$options = get_option( self::WP_OPTIONS_ENTRY, array() );
		echo "<input type='text' id='da_stash_settings_client_id' name='" . self::WP_OPTIONS_ENTRY . "[client_id]' value='" . $options['client_id'] . "' size='5'> ";
	}

	public static function settings_field_callback_options_client_secret( $args ) {
		$options = get_option( self::WP_OPTIONS_ENTRY, array() );
		echo "<input type='text' id='da_stash_settings_client_id' name='" . self::WP_OPTIONS_ENTRY . "[client_secret]' value='" . $options['client_secret'] . "' size='32'> ";
	}

	public static function validate_options( $input ) {
		$options = get_option( self::WP_OPTIONS_ENTRY, array() );

		if ( self::DEBUG ) error_log ('current options: ' . print_r( $options, true ) );

		$options['client_id']       = (int)trim( $input['client_id'] );
		$options['client_secret']   = trim( $input['client_secret'] );

		if ( self::DEBUG ) error_log ('validation begins: ' . print_r( $input, true ) );

		if ( 0 >= $options['client_id'] ) {
			add_settings_error(
				'da_stash_application_client_settings',
				'da_stash_settings_client_id_not_valid',
				__("Client ID must be a positive integer.", DA_STASH_I18N )
			);
			$options['client_id'] = "";
		}

		if ( ! preg_match( '/^[a-f0-9]{32}$/i', $options['client_secret'] ) ) {
			add_settings_error(
				'da_stash_application_client_settings',
				'da_stash_settings_client_secret_not_valid',
				__( "Client Secret must be a 32-digit hexadecimal hash.", DA_STASH_I18N )
			);
			$options['client_secret'] = "";
		}

		if ( self::DEBUG ) error_log ('validation complete: ' . print_r( $options, true ) );

		return $options;
	}

// PROFILE PAGE

	public function profile_personal_options( $profile ) {
		$user_is_authed = DA_Stash::is_user_authed( $profile->ID );

		?>
		<table class="form-table">
			<tr>
				<th style="vertical-align: middle;"><?php _e( 'deviantART Sta.sh', DA_STASH_I18N ); ?></th>
				<td valign="middle">
					<?php if ( $user_is_authed ): ?>
						<?php
							try {
								$user = DA_Stash::whoami();

								?>
								<p><?php _e( 'Connected as', DA_STASH_I18N ); ?>
									<span class='da-stash-usercard'>
										<img src="<?php echo $user->usericonurl; ?>" class="da-stash-usericon" alt="" width="50" height="50">
										<?php echo $user->symbol; ?><a href="http://<?php echo $user->username; ?>.deviantART.com" target="_blank"><?php echo $user->username; ?></a>
									</span>
									<button id="da-stash-disconnect" class="da-stash-disconnect da-stash-button"><?php _e( "Disconnect deviantART Account", DA_STASH_I18N ); ?></button>
								</p>
								<?php

							} catch ( Exception $e ) {
								echo "User is connected, but had trouble contacting deviantART.";
							}
						?>
					<?php else: ?>
						<p>
							<button id="da-stash-connect" class="da-stash-connect da-stash-button"><?php _e( "Connect deviantART Account", DA_STASH_I18N ); ?></button>
						</p>
					<?php endif; ?>
				</td>
			</tr>
		</table>
		<?php
	}

// GENERAL ADMIN

	public function admin_enqueue_scripts( $hook ) {
		wp_register_style( 'da_stash_css', plugins_url( '/da-stash.css', __FILE__ ), false, '1.0.0' );
        wp_enqueue_style( 'da_stash_css' );
    	wp_enqueue_script( 'da_stash_admin', plugins_url( '/da-stash.js', __FILE__ ), array( 'jquery' ) );
	}

	public function admin_head() {
		?>
		<style type='text/css'>
			.da-stash-loading {
				background-image: url(<?php echo get_admin_url( null, '/images/wpspin_light.gif'); ?>);
			}
		</style>
		<?
	}

	public function admin_notices() {
		settings_errors( 'da_stash_application_client_settings' );
	}

	public function admin_footer() {
		?>

		<script type='text/javascript'>
			var DA_Stash = {
				'connect_url': '<?php echo get_da_stash_connect_uri(); ?>',
				'api_url': '<?php echo get_da_stash_api_uri(); ?>',
				'onConnectSuccess': function () {
					window.location.reload();
					return true;
				},
				'onDisconnect': function (data) {
					window.location.reload();
				}
			};
		</script>

		<?php
	}

	function plugin_row_meta( $links, $file ) {
		if ( $file === DA_STASH_PLUGIN_BASE ) {
			$links[] = '<a href="' . admin_url( 'options-general.php?page=da_stash_settings' ) . '">' . __( 'Settings', DA_STASH_I18N ) . '</a>';
		}
		return $links;
	}


} // end DA_Stash_Admin

}