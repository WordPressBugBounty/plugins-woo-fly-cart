<?php
defined( 'ABSPATH' ) || exit;

register_activation_hook( defined( 'WOOFC_LITE' ) ? WOOFC_LITE : WOOFC_FILE, 'woofc_activate' );
register_deactivation_hook( defined( 'WOOFC_LITE' ) ? WOOFC_LITE : WOOFC_FILE, 'woofc_deactivate' );
add_action( 'admin_init', 'woofc_check_version' );

function woofc_check_version() {
	if ( ! empty( get_option( 'woofc_version' ) ) && ( get_option( 'woofc_version' ) < WOOFC_VERSION ) ) {
		wpc_log( 'woofc', 'upgraded' );
		update_option( 'woofc_version', WOOFC_VERSION, false );
	}
}

function woofc_activate() {
	wpc_log( 'woofc', 'installed' );
	update_option( 'woofc_version', WOOFC_VERSION, false );
}

function woofc_deactivate() {
	wpc_log( 'woofc', 'deactivated' );
}

if ( ! function_exists( 'wpc_log' ) ) {
	function wpc_log( $prefix, $action ) {
		$logs = get_option( 'wpc_logs', [] );
		$user = wp_get_current_user();

		if ( ! isset( $logs[ $prefix ] ) ) {
			$logs[ $prefix ] = [];
		}

		$logs[ $prefix ][] = [
			'time'   => current_time( 'mysql' ),
			'user'   => $user->display_name . ' (ID: ' . $user->ID . ')',
			'action' => $action
		];

		update_option( 'wpc_logs', $logs, false );
	}
}