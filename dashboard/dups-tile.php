<?php

/**
* Your custom tile class
 */
class Your_Custom_Tile extends DT_Dashboard_Tile
{

    /**
     * Register any assets the tile needs or do anything else needed on registration.
     * @return mixed
     */
    public function setup() {
//        wp_enqueue_script( $this->handle, 'path-t0-your-tiles-script.js', [], null, true);
    }

    /**
     * Render the tile
     */
    public function render() {
        global $wpdb;
        $bad_emails = $wpdb->get_results("
            SELECT p.post_title, p.ID, pm.meta_value, pm.post_id
            FROM $wpdb->postmeta pm
            INNER JOIN $wpdb->posts p ON p.ID = pm.post_id
            WHERE p.post_type = 'contacts' 
            AND pm.meta_key like 'contact_email%'
            AND pm.meta_key NOT like 'contact_email%_details'
            AND ( pm.meta_value LIKE '% %' OR pm.meta_value LIKE '%;%' )
            
        ", ARRAY_A );

        $dups = $wpdb->get_results("
            SELECT p.post_title, p.ID, pm.meta_value, pm.post_id, GROUP_CONCAT(pm.meta_value) as emails, GROUP_CONCAT(pm.post_id) as ids
            FROM $wpdb->postmeta pm
            INNER JOIN $wpdb->posts p ON p.ID = pm.post_id
            LEFT JOIN $wpdb->postmeta pm2 ON ( pm2.post_id = pm.post_id AND pm2.meta_key = 'overall_status' )
            WHERE p.post_type = 'contacts' 
            AND pm.meta_key like 'contact_email%'
            AND pm2.meta_value != 'closed'
            AND pm.meta_key not like 'contact_email%_details'
            AND pm.meta_value != ''
            GROUP BY pm.meta_value
            HAVING COUNT(pm.meta_value) > 1
        ", ARRAY_A);

        ?>
        <div class='tile-header'>
           Email Management
        </div>
        <div class="tile-body">
            <strong>Delete the duplicate contact.</strong>
            <?php foreach ( array_slice( $dups, 0, 8 ) as $dup ) :
                $ids = explode( ',', $dup['ids'] );
                ?>
                <div class="tile-row">
                    <?php echo esc_html( $dup['meta_value'] ) ?> -
                    <?php foreach ( $ids as $index => $id ) : ?>
                        <a href="<?php echo esc_url( get_permalink( $id ) ) ?>">
                            #<?php echo esc_html( $id ) ?>
                        </a>
                        <?php if ( $index < count( $ids ) - 1 ){
                            echo ', ';
                        }
                    endforeach; ?>
                </div>
            <?php endforeach;
            if ( count( $dups ) > 8 ) : ?>
                <strong><?php echo esc_html( count( $dups ) - 8 ) ?> more found.</strong>
            <?php endif; ?>
            <br>
            <strong>Bad email format</strong>
            <?php foreach ( array_slice( $bad_emails, 0, 5 ) as $dup ) :

                ?>
                <div class="tile-row">
                    <?php echo esc_html( $dup['meta_value'] ) ?> -
                        <a href="<?php echo esc_url( get_permalink( $dup['ID'] ) ) ?>">
                            #<?php echo esc_html( $dup['ID'] ) ?>
                        </a>
                </div>
            <?php endforeach;
            if ( count( $bad_emails ) > 5 ) : ?>
                <strong><?php echo esc_html( count( $bad_emails ) - 5 ) ?> more found.</strong>
            <?php endif; ?>
        </div>
        <?php

    }
}

/**
* Next, register our class. This can be done in the after_setup_theme hook.
*/
DT_Dashboard_Plugin_Tiles::instance()->register(
    new Your_Custom_Tile(
        'Your_Custom_Tile',                     //handle
        __( 'Custom Tile Label', 'your-plugin' ), //label
        [
            'priority' => 3,
            'span' => 1
         ]
));