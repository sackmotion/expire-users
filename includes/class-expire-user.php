<?php

class Expire_User {
	
	// User
	var $user_id = null;
	var $user = null;
	
	// Settings
	var $expire_timestamp = null;
	var $on_expire_default_to_role = false;
	var $on_expire_user_reset_password = false;
	var $on_expire_user_email = false;
	var $on_expire_user_email_admin = false;
	var $on_expire_user_remove_expiry = false;
	
	function Expire_User( $user_id = null ) {
		if ( $user_id ) {
			$this->user_id = absint( $user_id );
			$this->expire_timestamp = get_user_meta( $this->user_id, '_expire_user_date', true );
			$expire_user_settings = get_user_meta( $this->user_id, '_expire_user_settings', true );
			if ( isset( $expire_user_settings['default_to_role'] ) ) {
				$this->on_expire_default_to_role = $expire_user_settings['default_to_role'];
			}
			if ( isset( $expire_user_settings['reset_password'] ) ) {
				$this->on_expire_user_reset_password = $expire_user_settings['reset_password'];
			}
			if ( isset( $expire_user_settings['email'] ) ) {
				$this->on_expire_user_email = $expire_user_settings['email'];
			}
			if ( isset( $expire_user_settings['email_admin'] ) ) {
				$this->on_expire_user_email_admin = $expire_user_settings['email_admin'];
			}
			if ( isset( $expire_user_settings['remove_expiry'] ) ) {
				$this->on_expire_user_remove_expiry = $expire_user_settings['remove_expiry'];
			}
		}
	}
	
	/**
	 * Set Expire Time In Future
	 */
	function set_expire_time_in_future( $amt, $unit = 'days' ) {
		switch ( $unit ) {
			case 'days':
				$this->expire_timestamp = current_time( 'timestamp' ) + ( 60 * 60 * 24 * $amt );
				break;
			case 'weeks':
				$this->expire_timestamp = current_time( 'timestamp' ) + ( 60 * 60 * 24 * 7 * $amt );
				break;
			case 'months':
				$date = getdate();
				$this->expire_timestamp = mktime( $date['hours'], $date['minutes'], $date['seconds'], $date['mon'] + $amt, $date['mday'], $date['year'] );
				break;
			case 'years':
				$date = getdate();
				$this->expire_timestamp = mktime( $date['hours'], $date['minutes'], $date['seconds'], $date['mon'], $date['mday'], $date['year'] + $amt );
				break;
		}
	}
	
	/**
	 * Set Expire Date
	 */
	function set_expire_date( $yyyy, $mm, $dd, $hrs = 0, $min = 0 ) {
		$this->expire_timestamp = mktime( $hrs, $min, 0, $mm, $dd, $yyyy );
	}
	
	/**
	 * Set Expire Timestamp
	 *
	 * @todo Validate?
	 */
	function set_expire_timestamp( $timestamp ) {
		$this->expire_timestamp = $timestamp;
	}
	
	/**
	 * Remove Expire Date
	 */
	function remove_expire_date() {
		$this->expire_timestamp = null;
	}

	/**
	 * Get Expire Date Display
	 *
	 * @todo In up to 14 days, otherwise date
	 */
	function get_expire_date_display( $args = null ) {
		$args = wp_parse_args( $args, array(
			'date_format'    => _x(  get_option( 'date_format' ) . ' @ ' . get_option( 'time_format' ), 'display date format', 'expire-users' ),
			'expires_format' => __( 'Expires: <strong>%s</strong>', 'expire-users' ),
			'expired_format' => __( 'Expired: <strong>%s</strong>', 'expire-users' ),
			'never_expire'   => __( 'Expire: <strong>never</strong>', 'expire-users' ),
		) );
		$date = '';
		if ( $this->expire_timestamp ) {
			if ( $this->expire_timestamp > current_time( 'timestamp' ) ) {
				$format = $args['expires_format'];
			} else {
				$format = $args['expired_format'];
			}
			$date = date( $args['date_format'], $this->expire_timestamp );
		} else {
			$format = $args['never_expire'];
		}
		return sprintf( $format, $date );
	}

	/**
	 * Set Default To Role
	 *
	 * @todo Check if valid role...
	 */
	function set_default_to_role( $role ) {
		$this->on_expire_default_to_role = $role;
	}
	
	/**
	 * Maybe Expire
	 * If expire date is set and in past...
	 */
	function maybe_expire() {
		if ( $this->expire_timestamp && current_time( 'timestamp' ) > $this->expire_timestamp ) {
			$this->expire();
		}
	}
	
	/**
	 * Expire
	 */
	function expire() {
		update_user_meta( $this->user_id, '_expire_user_expired', 'Y' );
		do_action( 'expire_users_expired', $this );
	}
	
	/**
	 * Get User
	 */
	function get_user() {
		//$this->user = ...
		//$this->expire_timestamp = null;
		//$this->on_expire_default_to_role = false;
		//$this->on_expire_user_reset_password = false;
		//$this->on_expire_user_email = false;
	}
	
	/**
	 * Save User
	 */
	function save_user() {
		$expire_user_date = '';
		if ( $this->expire_timestamp ) {
			$expire_user_date = $this->expire_timestamp;
		}
		$expire_user_settings = array(
			'default_to_role' => $this->on_expire_default_to_role,
			'reset_password'  => $this->true_or_false( $this->on_expire_user_reset_password ),
			'email'           => $this->true_or_false( $this->on_expire_user_email ),
			'email_admin'     => $this->true_or_false( $this->on_expire_user_email_admin ),
			'remove_expiry'   => $this->true_or_false( $this->on_expire_user_remove_expiry )
		);
		$expire_user_expired = is_numeric( $expire_user_date ) ? 'N' : 'Y';
		
		// Update User
		update_user_meta( $this->user_id, '_expire_user_date', $expire_user_date );
		update_user_meta( $this->user_id, '_expire_user_settings', $expire_user_settings );
		if ( is_numeric( $expire_user_date ) ) {
			if ( $expire_user_date < current_time( 'timestamp' ) ) {
				update_user_meta( $this->user_id, '_expire_user_expired', 'Y' );
			} else {
				update_user_meta( $this->user_id, '_expire_user_expired', 'N' );
			}
		} else {
			delete_user_meta( $this->user_id, '_expire_user_expired' );
		}
	}
	
	/**
	 * Set Expire Data
	 * Processes array of data and saves as class properties ready to be saved.
	 *
	 * @param array $data Post data.
	 */
	function set_expire_data( $data = null ) {
		if ( isset( $data['expire_user_date_type'] ) ) {
			switch ( $data['expire_user_date_type'] ) {
				// In a specified amount of time
				case 'in':
					$this->set_expire_time_in_future(
						absint( $data['expire_user_date_in_num'] ),
						$data['expire_user_date_in_block']
					);
					break;
				// On a specific date
				case 'on':
					if ( isset( $data['expire_user_date_on_timestamp'] ) ) {
						$this->set_expire_timestamp( absint( $data['expire_user_date_on_timestamp'] ) );
					} else {
						$this->set_expire_date(
							absint( $data['expire_user_date_on_yyyy'] ),
							absint( $data['expire_user_date_on_mm'] ),
							absint( $data['expire_user_date_on_dd'] ),
							absint( $data['expire_user_date_on_hrs'] ),
							absint( $data['expire_user_date_on_min'] )
						);
					}
					break;
				// Never
				default:
					$this->expire_timestamp = null;
					break;
			}
		}
		$this->set_default_to_role( $data['expire_user_role'] );
		$this->on_expire_user_reset_password = isset( $data['expire_user_reset_password'] ) && $data['expire_user_reset_password'] == 'Y';
		$this->on_expire_user_email          = isset( $data['expire_user_email'] ) && $data['expire_user_email'] == 'Y';
		$this->on_expire_user_email_admin    = isset( $data['expire_user_email_admin'] ) && $data['expire_user_email_admin'] == 'Y';
		$this->on_expire_user_remove_expiry  = isset( $data['expire_user_remove_expiry'] ) && $data['expire_user_remove_expiry'] == 'Y';
	}
	
	/**
	 * True / False
	 * Used for saving true/false settings.
	 *
	 * @param $value True or false value. True values are 'Y', 1, or true
	 * @return (string) 'Y' or 'N'
	 */
	function true_or_false( $value ) {
		if ( $value == 'Y' || $value == 1 || $value === true ) {
			return true;
		}
		return false;
	}
	
}
