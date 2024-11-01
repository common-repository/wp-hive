<?php
/*
Plugin Name: WP HIVE
Plugin URI: http://wordpress.org/plugins/wp-hive/
Description: All-In-One WordPress Marketing Automation Suite
Author: WP HIVE
Author URI: http://wp-hive.com/
Version: 1.0.3
*/

if ( !defined( 'WPHIVE_FILE' ) && !isset( $wphive ) ) {

    define( 'WPHIVE_FILE', __FILE__ );
    define( 'WPHIVE_DIR',  dirname( WPHIVE_FILE ) );

    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
    require_once( ABSPATH . 'wp-admin/includes/plugin.php' );

    require_once( WPHIVE_DIR . '/libs/class-wphive.php' );
    require_once( WPHIVE_DIR . '/libs/class-wphive-downloads.php' );
    require_once( WPHIVE_DIR . '/libs/class-wphive-lead.php' );
    require_once( WPHIVE_DIR . '/libs/class-wphive-list-table.php' );
    require_once( WPHIVE_DIR . '/libs/class-wphive-mimetype.php' );
    require_once( WPHIVE_DIR . '/libs/class-wphive-session.php' );

    $wphive = new WPHive();

    // activation hook
    register_activation_hook( __FILE__, array( $wphive, 'activate' ) );

}
