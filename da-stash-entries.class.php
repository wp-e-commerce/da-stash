<?php

if ( ! class_exists( 'DA_Stash_Entries' ) ) {

class DA_Stash_Entries {

	const DEBUG = false;

	public $user_id = 0;

	public $delta_cursor = null;
	public $delta_next_offset = null;
	public $entries = null;

	protected $user_loaded_delta_cursor_data = null;
	protected $user_loaded_entries_data = null;

	public function __construct( $user_id = null ) {

		if ( self::DEBUG ) error_log('----');

		$this->entries = new stdClass();

		$this->user_id = ( $user_id ? $user_id : get_current_user_id() );
		if ( self::DEBUG ) error_log( "__construct: user_id: " . print_r( $this->user_id, true ) );

		$this->load_from_user();
	}

	public function handle_delta( $delta ) {
		if ( self::DEBUG ) error_log( 'handle_delta: ' . print_r( $delta, true ) );

		if ( $delta->reset ) {
			$this->entries = new stdClass();
		}

		// if we've already downloaded this cursor in previous sessions, don't bother, we're up to date.
		if ( $this->delta_cursor === $delta->cursor && ! $this->next_offset ) return true;

		$this->delta_cursor = $delta->cursor;

		$this->process_delta_entries( $delta->entries );

		if ( $delta->has_more ) {
			$this->delta_next_offset = $delta->next_offset;
			return $delta->next_offset;
		} else {
			return true;
		}
	}

	public function process_delta_entries ( $entries ) {
		foreach ( $entries as $entry ) {
			// is it a folder or a stash item
			if ( self::is_folder( $entry ) ) {
				$id = $entry->folderid;
			} else {
				$id = $entry->stashid;
			}
			error_log( "process_delta_entries: " . $id );
			if ( $entry->metadata === null ) {
				error_log( "DELETING ENTRY " . $id . " : " . print_r( $entry, true ) );
				delete( $this->entries->{$id} );
			} else {
				$this->entries->{$id} = $entry;
			}
		}
	}

	public static function is_folder( $entry ) {
		return $entry->folderid && ! $entry->stashid && $entry->metadata->is_folder;
	}

	public static function is_stash( $entry ) {
		return ! $entry->folderid && $entry->stashid && ! $entry->metadata->is_folder;
	}

	// wordpress storage

	public function load_from_user() {
		// talk to wp to get the user's stash
		$this->delta_cursor = get_user_meta( $this->user_id, 'da_stash_delta_cursor', true );
		if ( self::DEBUG ) error_log( "load_from_user: delta_cursor: \n" . print_r( $this->delta_cursor, true ) );
		$this->entries = json_decode( get_user_meta( $this->user_id, 'da_stash_entries', true ) );
		self::debug_json();
		if ( self::DEBUG ) error_log( "load_from_user: entries: \n" . print_r( $this->entries, true ) );
	}

	public function store_to_user() {
		$success_entries = update_user_meta(
			$this->user_id, // user id
			'da_stash_entries', // field
			json_encode( self::clean_json_strings( $this->entries ) ) // new data
		);
		if ( self::DEBUG ) error_log( "store_to_user: entries: \n" . print_r( $this->entries, true ) );

		$success_delta_cursor = update_user_meta(
			$this->user_id, // user id
			'da_stash_delta_cursor', // field
			$this->delta_cursor // new data
		);
		if ( self::DEBUG ) error_log( "store_to_user: delta_cursor: \n" . print_r( $this->delta_cursor, true ) );

		return $success_entries;
	}

// UTILITIES

	// Because quotes in JSON suck before PHP 5.4
	public function clean_json_strings( &$obj ) {
		if ( phpversion() < "5.4" ) {
			foreach ( $obj as $key => &$value ) {
				if ( is_object( $value ) || is_array( $value ) ) {
					clean_json_strings( $value );
				}
				else if ( is_string( $value ) ) {
					$value = str_replace( "\\", "\\\\", $value );
					$value = str_replace( "\n", "\\n", $value );
					$value = str_replace( "\"", "\\\"", $value );
				}
			}
		}
		return $obj;
	}

	public function debug_json () {
		if ( ! self::DEBUG ) return;

		switch ( json_last_error() ) {
			case JSON_ERROR_NONE:
				//error_log( 'debug_json: No errors' );
				return;
			break;
			case JSON_ERROR_DEPTH:
				error_log( 'debug_json: Maximum stack depth exceeded' );
			break;
			case JSON_ERROR_STATE_MISMATCH:
				error_log( 'debug_json: Underflow or the modes mismatch' );
			break;
			case JSON_ERROR_CTRL_CHAR:
				error_log( 'debug_json: Unexpected control character found' );
			break;
			case JSON_ERROR_SYNTAX:
				error_log( 'debug_json: Syntax error, malformed JSON' );
			break;
			case JSON_ERROR_UTF8:
				error_log( 'debug_json: Malformed UTF-8 characters, possibly incorrectly encoded' );
			break;
			default:
				error_log( 'debug_json: Unknown error' );
			break;
		}

		print_r( debug_backtrace() );
	}

} // end DA_Stash_Entries

}
