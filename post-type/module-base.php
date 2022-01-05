<?php
if ( !defined( 'ABSPATH' ) ) { exit; } // Exit if accessed directly.

/**
 * Class Disciple_Tools_CRM_Base
 * Load the core post type hooks into the Disciple.Tools system
 */
class Disciple_Tools_CRM_Base extends DT_Module_Base {

    public $post_type = "contacts";
    public $module = "crm";
    public static function post_type(){
        return 'contacts';
    }

    private static $_instance = null;
    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function __construct() {
        parent::__construct();
        if ( !self::check_enabled_and_prerequisites() ){
            return;
        }

        //setup post type
        add_filter( 'dt_set_roles_and_permissions', [ $this, 'dt_set_roles_and_permissions' ], 20, 1 ); //after contacts
        add_filter( 'dt_duplicates_find_types', [ $this, 'dt_duplicates_find_types' ], 20, 1 ); //after contacts


        //setup tiles and fields
        add_filter( 'dt_custom_fields_settings', [ $this, 'dt_custom_fields_settings' ], 150, 2 );
        add_action( 'wp_enqueue_scripts', [ $this, 'scripts' ], 99 );
        add_action( 'dt_record_footer', [ $this, 'dt_record_footer' ], 10, 2 );
        add_action( 'dt_render_field_for_display_template', [ $this, 'dt_render_field_for_display_template' ], 20, 5 );

        // hooks
        add_action( "post_connection_removed", [ $this, "post_connection_removed" ], 10, 4 );
        add_filter( "dt_post_update_fields", [ $this, "dt_post_update_fields" ], 10, 3 );
        add_filter( "dt_post_create_fields", [ $this, "dt_post_create_fields" ], 5, 2 );
        add_action( "dt_post_created", [ $this, "dt_post_created" ], 10, 3 );

        //list
        add_filter( "dt_user_list_filters", [ $this, "dt_user_list_filters" ], 10, 2 );
        add_filter( "dt_filter_access_permissions", [ $this, "dt_filter_access_permissions" ], 20, 2 );
        add_filter( "dt_can_view_permission", [ $this, 'can_view_permission_filter' ], 10, 3 );
        add_filter( "dt_can_update_permission", [ $this, 'can_update_permission_filter' ], 10, 3 );

    }

    /**
     * Documentation
     * @link https://github.com/DiscipleTools/Documentation/blob/master/Theme-Core/roles-permissions.md#rolesd
     */
    public function dt_set_roles_and_permissions( $expected_roles ){

        return $expected_roles;
    }

    /**
     * Documentation
     * @link https://github.com/DiscipleTools/Documentation/blob/master/Theme-Core/fields.md
     */
    public function dt_custom_fields_settings( $fields, $post_type ){
        if ( $post_type === $this->post_type ){
            if ( isset( $fields["type"] ) ){
                $fields["type"]["default"]["crm"] = [
                    "label" => __( 'CRM', 'disciple_tools' ),
                    "color" => "#9b379b",
                    "description" => __( "Collaborative Contact", 'disciple_tools' ),
                    "visibility" => __( "Everyone", 'disciple_tools' ),
                    "order" => 5
                ];
            }
            if ( !isset( $fields["assigned_to"] ) ){
                $fields['assigned_to'] = [
                    'name' => __( 'Assigned To', 'disciple_tools' ),
                    'description' => __( 'Select the main person who is responsible for reporting on this contact.', 'disciple_tools' ),
                    'type' => 'user_select',
                    'default' => '',
                    'tile' => 'status',
                    'icon' => get_template_directory_uri() . "/dt-assets/images/assigned-to.svg?v=2",
                    "show_in_table" => 25,
                    "custom_display" => true
                ];
            }
            $fields["assigned_to"]["only_for_types"][] = "crm";

            if ( !isset( $fields["sources"] ) ){
                $sources_default = [
                    'personal'           => [
                        'label'       => __( 'Personal', 'disciple_tools' ),
                        'key'         => 'personal',
                    ],
                    'web'           => [
                        'label'       => __( 'Web', 'disciple_tools' ),
                        'key'         => 'web',
                    ],
                    'facebook'      => [
                        'label'       => __( 'Facebook', 'disciple_tools' ),
                        'key'         => 'facebook',
                    ],
                    'twitter'       => [
                        'label'       => __( 'Twitter', 'disciple_tools' ),
                        'key'         => 'twitter',
                    ],
                    'transfer' => [
                        'label'       => __( 'Transfer', 'disciple_tools' ),
                        'key'         => 'transfer',
                        'description' => __( 'Contacts transferred from a partnership with another Disciple.Tools site.', 'disciple_tools' ),
                    ]
                ];
                foreach ( dt_get_option( 'dt_site_custom_lists' )['sources'] as $key => $value ) {
                    if ( !isset( $sources_default[$key] ) ) {
                        if ( isset( $value['enabled'] ) && $value["enabled"] === false ) {
                            $value["deleted"] = true;
                        }
                        $sources_default[ $key ] = $value;
                    }
                }

                $fields['sources'] = [
                    'name'        => __( 'Sources', 'disciple_tools' ),
                    'description' => _x( 'The website, event or location this contact came from.', 'Optional Documentation', 'disciple_tools' ),
                    'type'        => 'multi_select',
                    'default'     => $sources_default,
                    'tile'     => 'details',
                    'customizable' => 'all',
                    'display' => "typeahead",
                    'icon' => get_template_directory_uri() . "/dt-assets/images/source.svg?v=2",
                ];
            }
            $fields["sources"]["only_for_types"][] = "crm";
            $fields["sources"]["in_create_form"][] = "crm";

            if ( !isset( $fields["subassigned"] ) ){
                $fields["subassigned"] = [
                    "name" => __( "Sub-assigned to", 'disciple_tools' ),
                    "description" => __( "Contact or User assisting the Assigned To user to follow up with the contact.", 'disciple_tools' ),
                    "type" => "connection",
                    "post_type" => "contacts",
                    "p2p_direction" => "to",
                    "p2p_key" => "contacts_to_subassigned",
                    "tile" => "status",
                    'icon' => get_template_directory_uri() . "/dt-assets/images/subassigned.svg?v=2",
                ];
            }
            $fields["subassigned"]["custom_display"] = true;
            $fields["subassigned"]["show_in_table"] = true;
            $fields["subassigned"]["meta_fields"] = [
                'reason' => [
                    'label' => "Reason"
                ]
            ];

            $fields["faith_status"]["hidden"] = true;
            $fields["contact_email"]["in_create_form"] = true;
            if ( isset( $fields["seeker_path"] ) ){
                $fields["seeker_path"]["show_in_table"] = false;
            }
            if ( isset( $fields["milestones"] ) ){
                $fields["milestones"]["show_in_table"] = false;
            }
            $fields["last_modified"]["show_in_table"] = false;


        }
        return $fields;
    }


    public function dt_render_field_for_display_template( $post, $field_type, $field_key, $required_tag, $display_field_id ){
        $contact_fields = DT_Posts::get_post_field_settings( "contacts" );
        if ( isset( $post["post_type"] ) && $post["post_type"] === "contacts" && $field_key === "subassigned"
            && isset( $contact_fields[$field_key] ) && !empty( $contact_fields[$field_key]["custom_display"] )
            && empty( $contact_fields[$field_key]["hidden"] )
        ){
            if ( !dt_field_enabled_for_record_type( $contact_fields[$field_key], $post ) ){
                return;
            }
            ?>
            <div class="section-subheader">
                    <img src="<?php echo esc_url( $contact_fields[$field_key]["icon"] ) ?>">
                    <?php echo esc_html( $contact_fields[$field_key]["name"] ) ?>
                    <button class="button tiny outlined loader" style="margin-bottom: 2px; padding: 4px 9px; vertical-align: bottom" id="open-subassigned-modal">Add Item</button>
                </div>
            <div id="list-of-subassigned">
                <!-- populated via js-->
            </div>


            <style>
                #list-of-subassigned {
                    display: flex;
                    flex-wrap: wrap;
                    font-size: 0.875rem;
                }
                #list-of-subassigned .connection-item{
                    color:#3f729b;
                    margin-bottom: 2px; margin-right: 2px; padding-left: 4px;
                    border:1px solid #c2e0ff;
                    background: #ecf5fc;
                    display: inline-flex;
                }
                #list-of-subassigned .connection-item >* {
                    align-self:center;
                }
                #list-of-subassigned .connection-item .delete-subassigned {
                    padding: 2px 6px; margin-left: 6px;
                    border-left:1px solid #c2e0ff;
                    cursor: pointer;
                }
                #list-of-subassigned .connection-item .delete-subassigned:hover {
                    color: red;
                }
                #list-of-subassigned .connection-item .connection-meta {
                    margin-left: 2px;
                }
                #list-of-subassigned .none-set {
                    color: grey;
                }
            </style>
            <?php
        }
    }

    public function dt_record_footer( $post_type, $post_id ){
        if ( $post_type !== $this->post_type ){
            return;
        }
        $field_settings = DT_Posts::get_post_field_settings( $post_type );
        $post = DT_Posts::get_post( $this->post_type, $post_id );
        ?>
        <div class="reveal" id="reason-subassinged-modal" data-reveal data-close-on-click="false">

            <h3>Subassign</h3>

            <div>
                <div class="section-subheader">
                    <?php echo esc_html( $field_settings["subassigned"]["name"] )?>
                </div>
                <div class="">
                    <var id="modal_subassigned-result-container" class="result-container modal_subassigned-result-container"></var>
                    <div id="modal_subassigned_t" name="form-modal_subassigned" class="scrollable-typeahead typeahead-margin-when-active">
                        <div class="typeahead__container">
                            <div class="typeahead__field">
                                <span class="typeahead__query">
                                    <input class="js-typeahead-modal_subassigned input-height"
                                           name="modal_subassigned[query]"
                                           placeholder="<?php echo esc_html_x( "Search multipliers and contacts", 'input field placeholder', 'disciple_tools' ) ?>"
                                           autocomplete="off">
                                </span>
                            </div>
                        </div>
                    </div>
                </div>


                <label>
                    Task
                    <input id="modal-reason-subassinged" type="text">
                </label>

            </div>


            <div class="grid-x">
                <button class="button" id="add-subassigned"><?php esc_html_e( 'Add Subassinged', 'disciple_tools' ); ?></button>
                <button class="button clear" data-close type="button" id="close-baptism-modal">
                    <?php echo esc_html__( 'Close', 'disciple_tools' )?>
                </button>
                <button class="close-button" data-close aria-label="Close modal" type="button">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        </div>

        <?php
    }


    //action when a post connection is removed during create or update
    public function post_connection_removed( $post_type, $post_id, $field_key, $value ){
        if ( $post_type === $this->post_type ){
            // execute your code here, if connection removed
            dt_write_log( __METHOD__ );
        }
    }

    //filter at the start of post update
    public function dt_post_update_fields( $fields, $post_type, $post_id ){
        if ( $post_type === $this->post_type ){
            // execute your code here
            dt_write_log( __METHOD__ );
        }
        return $fields;
    }



    // filter at the start of post creation
    public function dt_post_create_fields( $fields, $post_type ){
        if ( $post_type === $this->post_type && !isset( $fields["type"] ) ){
            $fields["type"] = "crm";
        }
        return $fields;
    }

    //action when a post has been created
    public function dt_post_created( $post_type, $post_id, $initial_fields ){
    }


    //build list page filters
    public static function dt_user_list_filters( $filters, $post_type ){
        return $filters;
    }

    // access permission
    public static function dt_filter_access_permissions( $permissions, $post_type ){
        if ( $post_type === self::post_type() ){
            if ( DT_Posts::can_access( $post_type ) ){
                $permissions["type"] = [ "crm" ];
            }
        }
        return $permissions;
    }

    // filter for access to a specific record
    public function can_view_permission_filter( $has_permission, $post_id, $post_type ){
        if ( $post_type === $this->post_type ){
            if ( DT_Posts::can_access( $post_type ) ){
                return true;
            }
        }
        return $has_permission;
    }
    public function can_update_permission_filter( $has_permission, $post_id, $post_type ){
        if ( $post_type === $this->post_type ){
            if ( DT_Posts::can_access( $post_type ) ){
                return true;
            }
        }
        return $has_permission;
    }


    // scripts
    public function scripts(){
        if ( is_singular( $this->post_type ) && get_the_ID() && DT_Posts::can_view( $this->post_type, get_the_ID() ) ){
            wp_enqueue_script( 'dt_crm_scripts', plugin_dir_url( __FILE__ ) . 'crm.js', [
                'jquery',
            ], filemtime( plugin_dir_path( __FILE__ ) . '/crm.js' ), true );
        }
    }

    public function dt_duplicates_find_types( $types ){
        $types[] = "crm";
        return $types;
    }
}


