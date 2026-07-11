<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}
// The base plugin intentionally keeps app data by default.
// Remove tables/options manually only when a client project is permanently decommissioned.
