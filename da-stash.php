<?php
/*
Plugin Name: deviantART Sta.sh
Description: Pairs users accounts with deviantART accounts and gives you access to their sta.sh
Author: Brett Taylor
Version: 0.1
*/

define ( 'DA_STASH_I18N', 'da_stash' );

define ( 'DA_STASH_PLUGIN_BASE', plugin_basename( __FILE__ ) );
define ( 'DA_STASH_PLUGIN_DIR', dirname( DA_STASH_PLUGIN_BASE ) );

load_plugin_textdomain( DA_STASH_I18N, false, DA_STASH_PLUGIN_DIR . '/languages/' );

require_once( 'da-stash.class.php' );
require_once( 'da-stash-template-tags.inc.php' );
require_once( 'da-stash-entries.class.php' );

if ( isset( $_GET['da-stash'] ) ) {
	add_action( 'init', array( 'DA_Stash', 'controller' ) );
}

require_once( 'da-stash-admin.class.php' );
add_action( 'admin_menu', array( 'DA_Stash_Admin', 'init' ) );
