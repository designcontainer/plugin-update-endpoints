<?php

//includes necessary for Plugin_Upgrader and Plugin_Installer_Skin
include_once( ABSPATH . 'wp-admin/includes/file.php' );
include_once( ABSPATH . 'wp-admin/includes/misc.php' );
include_once( ABSPATH . 'wp-admin/includes/class-wp-upgrader.php' );


class WP_Upgrader_Url extends \WP_Upgrader_Skin {
    public function feedback($string, ...$args)  {
        if ( isset( $args[0] ) ) {
            echo $args[0];
        }
        return false;
    }
}