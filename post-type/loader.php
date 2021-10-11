<?php
if ( !defined( 'ABSPATH' ) ) { exit; } // Exit if accessed directly.

/**
 * Test that DT_Module_Base has loaded
 */
if ( ! class_exists( 'DT_Module_Base' ) ) {
    dt_write_log( 'Disciple.Tools System not loaded. Cannot load custom post type.' );
    return;
}

/**
 * Add any modules required or added for the post type
 */
add_filter( 'dt_post_type_modules', function( $modules ){

    $modules["crm"] = [
        "name" => "CRM",
        "enabled" => true,
        "locked" => false,
        "prerequisites" => [ "contacts_base" ],
        "post_type" => "contacts",
        "description" => "CRM Contact Type"
    ];

    return $modules;
}, 20, 1 );

require_once 'module-base.php';
Disciple_Tools_CRM_Base::instance();
