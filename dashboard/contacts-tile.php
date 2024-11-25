<?php

/**
* Your custom tile class
 */
class Contacts_Tile extends DT_Dashboard_Tile
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
        $new_contacts = DT_Posts::list_posts( 'contacts', [
            'sort' => '-post_date',
            'limit' => 20
        ], true );

        $recent_contacts = DT_Posts::list_posts( 'contacts', [
            'sort' => '-post_date',
            'limit' => 20,
            'dt_recent' => true
        ], true );

        ?>
        <style>
            .dash-tile {
                display: block !important;
            }
            .tile-row {
                white-space: nowrap;
                overflow: hidden;
            }
        </style>
        <div class='tile-header'>
           Contacts
        </div>
        <div class="tile-body">
            <strong>New Contacts</strong>
            <?php foreach ( array_slice( $new_contacts['posts'], 0, 8 ) as $c ) :

                ?>
                <div class="tile-row">
                    <a href="<?php echo esc_url( $c['permalink'] ) ?>">
                        <?php echo esc_html( $c['name'] ) ?> - <?php echo esc_html( $c['post_date']['formatted'] ) ?>
                    </a>
                </div>
            <?php endforeach;?>

            <br>
            <strong>Recently Viewed</strong>
            <?php foreach ( array_slice( $recent_contacts['posts'], 0, 8 ) as $c ) :

                ?>
                <div class="tile-row">
                    <a href="<?php echo esc_url( $c['permalink'] ) ?>">
                        <?php echo esc_html( $c['name'] ) ?> - <?php echo esc_html( $c['post_date']['formatted'] ) ?>
                    </a>
                </div>
            <?php endforeach;?>
        </div>
        <?php

    }
}

/**
* Next, register our class. This can be done in the after_setup_theme hook.
*/
DT_Dashboard_Plugin_Tiles::instance()->register(
    new Contacts_Tile(
        'contacts_tile',                     //handle
        __( 'Contacts Tile', 'your-plugin' ), //label
        [
            'priority' => 3,
            'span' => 1
         ]
));