<?php

require_once( 'dups-tile.php' );
require_once( 'contacts-tile.php' );

add_filter( 'dt_dashboard_tiles', function ( $tiles ) {
    if ( isset( $tiles['DT_Dashboard_Plugin_Active_Contact'] ) ) {
        unset( $tiles['DT_Dashboard_Plugin_Active_Contact'] );
    }
    if ( isset( $tiles['DT_Dashboard_Plugin_Contact_Workload'] ) ) {
        unset( $tiles['DT_Dashboard_Plugin_Contact_Workload'] );
    }
    if ( isset( $tiles['DT_Dashboard_Plugin_Faith_Milestone_Totals'] ) ) {
        unset( $tiles['DT_Dashboard_Plugin_Faith_Milestone_Totals'] );
    }
    if ( isset( $tiles['DT_Dashboard_Plugin_Seeker_Path_Progress'] ) ) {
        unset( $tiles['DT_Dashboard_Plugin_Seeker_Path_Progress'] );
    }
    if ( isset( $tiles['DT_Dashboard_Plugin_Personal_Benchmarks'] ) ) {
        unset( $tiles['DT_Dashboard_Plugin_Personal_Benchmarks'] );
    }
    return $tiles;
} );