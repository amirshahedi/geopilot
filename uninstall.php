<?php
/**
 * Fires only on actual plugin deletion (not deactivation).
 * Removes the option we set; leaves generated export files in place
 * since they're just static text files the store owner may still want.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'wgt_last_generated' );
