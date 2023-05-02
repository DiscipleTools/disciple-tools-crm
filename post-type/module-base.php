<?php
if ( !defined( 'ABSPATH' ) ) { exit; } // Exit if accessed directly.

/**
 * Class Disciple_Tools_CRM_Base
 * Load the core post type hooks into the Disciple.Tools system
 */
class Disciple_Tools_CRM_Base extends DT_Module_Base {

    public $post_type = 'contacts';
    public $module = 'crm';
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
        add_action( 'post_connection_added', [ $this, 'post_connection_added' ], 10, 4 );
        add_action( 'post_connection_removed', [ $this, 'post_connection_removed' ], 10, 4 );
        add_filter( 'dt_post_update_fields', [ $this, 'dt_post_update_fields' ], 10, 3 );
        add_filter( 'dt_post_create_fields', [ $this, 'dt_post_create_fields' ], 5, 2 );
        add_action( 'dt_post_created', [ $this, 'dt_post_created' ], 10, 3 );

        //list
        add_filter( 'dt_user_list_filters', [ $this, 'dt_user_list_filters' ], 10, 2 );
        add_filter( 'dt_filter_access_permissions', [ $this, 'dt_filter_access_permissions' ], 20, 2 );
        add_filter( 'dt_can_view_permission', [ $this, 'can_view_permission_filter' ], 10, 3 );
        add_filter( 'dt_can_update_permission', [ $this, 'can_update_permission_filter' ], 10, 3 );
        add_filter( 'dt_can_delete_permission', [ $this, 'dt_can_delete_permission' ], 20, 3 );

        add_filter( 'dt_get_viewable_compact_search_query', [ $this, 'dt_get_viewable_compact_search_query' ], 10, 4 );

    }

    /**
     * Documentation
     * @link https://github.com/DiscipleTools/Documentation/blob/master/Theme-Core/roles-permissions.md#rolesd
     */
    public function dt_set_roles_and_permissions( $expected_roles ){
        $multiplier_permissions = Disciple_Tools_Roles::default_multiplier_caps(); // get the base multiplier permissions
        $expected_roles['all_crm'] = [
            'label' => __( 'CRM Admin', 'disciple_tools' ),
            'description' => 'All CRM and Access contacts',
            'permissions' => wp_parse_args( [
                'dt_all_access_contacts' => true,
                'dt_all_crm_contacts' => true,
                'view_project_metrics' => true,
                'assign_any_contacts' => true, //assign contacts to others
            ], $multiplier_permissions ),
            'order' => 20
        ];

        $expected_roles['administrator']['permissions']['delete_contacts'] = true;
        $expected_roles['administrator']['permissions']['dt_all_crm_contacts'] = true;
        $expected_roles['administrator']['permissions']['dt_all_access_contacts'] = true;
        $expected_roles['dt_admin']['permissions']['dt_all_access_contacts'] = true;
        $expected_roles['dt_admin']['permissions']['dt_all_crm_contacts'] = true;


        return $expected_roles;
    }

    /**
     * Documentation
     * @link https://github.com/DiscipleTools/Documentation/blob/master/Theme-Core/fields.md
     */
    public function dt_custom_fields_settings( $fields, $post_type ){
        if ( $post_type === $this->post_type ){
            if ( isset( $fields['type'] ) ){
                $fields['type']['default']['crm'] = [
                    'label' => __( 'CRM', 'disciple_tools' ),
                    'color' => '#9b379b',
                    'description' => __( 'Collaborative Contact', 'disciple_tools' ),
                    'visibility' => __( 'Everyone', 'disciple_tools' ),
                    'order' => 5,
                    'default' => true
                ];
                if ( isset( $fields['type']['default']['access'] ) ){
                    $fields['type']['default']['access']['default'] = false;
                }
            }
            if ( !isset( $fields['assigned_to'] ) ){
                $fields['assigned_to'] = [
                    'name' => __( 'Assigned To', 'disciple_tools' ),
                    'description' => __( 'Select the main person who is responsible for reporting on this contact.', 'disciple_tools' ),
                    'type' => 'user_select',
                    'default' => '',
                    'tile' => 'status',
                    'icon' => get_template_directory_uri() . '/dt-assets/images/assigned-to.svg?v=2',
                    'show_in_table' => 25,

                ];
            }
//            $fields["assigned_to"]["only_for_types"][] = "crm";

            $fields['overall_status'] = [
                'name'        => __( 'Contact Status', 'disciple_tools' ),
                'type'        => 'key_select',
                'default_color' => '#eeeeee',
                'default'     => array_merge( [
                    'new'   => [
                        'label' => __( 'New Contact', 'disciple_tools' ),
                        'description' => _x( 'The contact is new in the system.', 'Contact Status field description', 'disciple_tools' ),
                        'color' => '#F43636',
                    ],
                    'unassignable' => [
                        'label' => __( 'Not Ready', 'disciple_tools' ),
                        'description' => _x( 'There is not enough information to move forward with the contact at this time.', 'Contact Status field description', 'disciple_tools' ),
                        'color' => '#FF9800',
                    ],
                    'unassigned'   => [
                        'label' => __( 'Dispatch Needed', 'disciple_tools' ),
                        'description' => _x( 'This contact needs to be assigned to a multiplier.', 'Contact Status field description', 'disciple_tools' ),
                        'color' => '#F43636',
                    ],
                    'assigned'     => [
                        'label' => __( 'Waiting to be accepted', 'disciple_tools' ),
                        'description' => _x( 'The contact has been assigned to someone, but has not yet been accepted by that person.', 'Contact Status field description', 'disciple_tools' ),
                        'color' => '#FF9800',
                    ],
                    'active'       => [], //already declared. Here to indicate order
                    'paused'       => [
                        'label' => __( 'Paused', 'disciple_tools' ),
                        'description' => _x( 'This contact is currently on hold (i.e. on vacation or not responding).', 'Contact Status field description', 'disciple_tools' ),
                        'color' => '#FF9800',
                    ],
                    'closed' => [] //already declared. Here to indicate order
                ], $fields['overall_status']['default'] ),
                'tile'     => 'status',
                'customizable' => true,
                'icon' => get_template_directory_uri() . '/dt-assets/images/status.svg?v=2',
                'show_in_table' => 10,
                'select_cannot_be_empty' => false
            ];


            if ( !isset( $fields['sources'] ) ){
                $sources_default = [
                    'personal'           => [
                        'label'       => __( 'Personal', 'disciple_tools' ),
                        'key'         => 'personal',
                    ],
                    'transfer' => [
                        'label'       => __( 'Transfer', 'disciple_tools' ),
                        'key'         => 'transfer',
                        'description' => __( 'Contacts transferred from a partnership with another Disciple.Tools site.', 'disciple_tools' ),
                    ]
                ];
                foreach ( dt_get_option( 'dt_site_custom_lists' )['sources'] as $key => $value ) {
                    if ( !isset( $sources_default[$key] ) ) {
                        if ( isset( $value['enabled'] ) && $value['enabled'] === false ) {
                            $value['deleted'] = true;
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
                    'display' => 'typeahead',
                    'icon' => get_template_directory_uri() . '/dt-assets/images/source.svg?v=2',
                ];
            }
            $fields['sources']['only_for_types'][] = 'crm';
            $fields['sources']['in_create_form'][] = 'crm';

            if ( !isset( $fields['campaigns'] ) ){
                $fields['campaigns'] = [
                    'name' => __( 'Campaigns', 'disciple_tools' ),
                    'description' => _x( 'Marketing campaigns or access activities that this contact interacted with.', 'Optional Documentation', 'disciple_tools' ),
                    'tile' => 'details',
                    'type'        => 'tags',
                    'default'     => [],
                    'icon' => get_template_directory_uri() . '/dt-assets/images/megaphone.svg?v=2',
                ];
            }


            if ( !isset( $fields['subassigned'] ) ){
                $fields['subassigned'] = [
                    'name' => __( 'Sub-assigned to', 'disciple_tools' ),
                    'description' => __( 'Contact or User assisting the Assigned To user to follow up with the contact.', 'disciple_tools' ),
                    'type' => 'connection',
                    'post_type' => 'contacts',
                    'p2p_direction' => 'to',
                    'p2p_key' => 'contacts_to_subassigned',
                    'tile' => 'status',
                    'icon' => get_template_directory_uri() . '/dt-assets/images/subassigned.svg?v=2',
                ];
            }
            $fields['subassigned']['custom_display'] = true;
            $fields['subassigned']['show_in_table'] = true;
            $fields['subassigned']['meta_fields'] = [
                'reason' => [
                    'label' => 'Reason'
                ]
            ];

            if ( isset( $fields['faith_status'] ) ){
                $fields['faith_status']['hidden'] = true;
            }
            $fields['contact_email']['in_create_form'] = true;
            if ( isset( $fields['seeker_path'] ) ){
                $fields['seeker_path']['show_in_table'] = false;
            }
            if ( isset( $fields['milestones'] ) ){
                $fields['milestones']['show_in_table'] = false;
            }
            $fields['last_modified']['show_in_table'] = false;


        }
        return $fields;
    }


    public function dt_render_field_for_display_template( $post, $field_type, $field_key, $required_tag, $display_field_id ){
        $contact_fields = DT_Posts::get_post_field_settings( 'contacts' );
        if ( isset( $post['post_type'] ) && $post['post_type'] === 'contacts' && $field_key === 'subassigned'
            && isset( $contact_fields[$field_key] ) && !empty( $contact_fields[$field_key]['custom_display'] )
            && empty( $contact_fields[$field_key]['hidden'] )
        ){
            if ( !dt_field_enabled_for_record_type( $contact_fields[$field_key], $post ) ){
                return;
            }
            ?>
            <div class="section-subheader">
                    <img src="<?php echo esc_url( $contact_fields[$field_key]['icon'] ) ?>">
                    <?php echo esc_html( $contact_fields[$field_key]['name'] ) ?>
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
                    <?php echo esc_html( $field_settings['subassigned']['name'] )?>
                </div>
                <div class="">
                    <var id="modal_subassigned-result-container" class="result-container modal_subassigned-result-container"></var>
                    <div id="modal_subassigned_t" name="form-modal_subassigned" class="scrollable-typeahead typeahead-margin-when-active">
                        <div class="typeahead__container">
                            <div class="typeahead__field">
                                <span class="typeahead__query">
                                    <input class="js-typeahead-modal_subassigned input-height"
                                           name="modal_subassigned[query]"
                                           placeholder="<?php echo esc_html_x( 'Search multipliers and contacts', 'input field placeholder', 'disciple_tools' ) ?>"
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
    public function post_connection_added( $post_type, $post_id, $post_key, $value ){
        if ( $post_type === 'contacts' ){
            if ( $post_key === 'subassigned' ){
                $user_id = get_post_meta( $value, 'corresponds_to_user', true );
                if ( $user_id ){
                    DT_Posts::add_shared( $post_type, $post_id, $user_id, null, false, false, false );
                    Disciple_Tools_Notifications::insert_notification_for_subassigned( $user_id, $post_id );
                }
            }
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
        if ( $post_type === $this->post_type && !isset( $fields['type'] ) ){
            $fields['type'] = 'crm';
        }
        return $fields;
    }

    //action when a post has been created
    public function dt_post_created( $post_type, $post_id, $initial_fields ){
    }



    //list page filters function
    private static function count_records_by_source(){
        global $wpdb;
        $results = $wpdb->get_results($wpdb->prepare( "
            SELECT source.meta_value as source, count(source.post_id) as count
            FROM $wpdb->postmeta source
            INNER JOIN $wpdb->posts a ON( a.ID = source.post_id AND a.post_type = %s and a.post_status = 'publish' )
            WHERE source.meta_key = 'sources'
            GROUP BY source.meta_value
        ", self::post_type() ), ARRAY_A );

        return $results;
    }

    //build list page filters
    public static function dt_user_list_filters( $filters, $post_type ){
        if ( $post_type !== 'contacts' ){
            return $filters;
        }
        $filters = DT_Contacts_Access::dt_user_list_filters( $filters, $post_type );


        $fields = DT_Posts::get_post_field_settings( $post_type );
        if ( true ){
            $records_by_source_counts = self::count_records_by_source();
            $source_counts = [];
            $total_all = 0;
            foreach ( $records_by_source_counts as $count ){
                $total_all += $count['count'];
                dt_increment( $source_counts[$count['source']], $count['count'] );
            }

            // add by source Tab
            $filters['tabs'][] = [
                'key' => 'by_source',
                'label' => __( 'All By Source', 'disciple-tools-plugin-starter-template' ),
                'count' => $total_all,
                'order' => 30
            ];
            // add assigned to me filters
            $filters['filters'][] = [
                'ID' => 'all',
                'tab' => 'by_source',
                'name' => __( 'All', 'disciple-tools-plugin-starter-template' ),
                'query' => [
                    'sort' => '-post_date'
                ],
                'count' => $total_all
            ];

            foreach ( $fields['sources']['default'] as $source_key => $source_value ) {
                if ( isset( $source_counts[$source_key] ) ){
                    $filters['filters'][] = [
                        'ID' => 'all_' . $source_key,
                        'tab' => 'by_source',
                        'name' => $source_value['label'],
                        'query' => [
                            'sources' => [ $source_key ],
                            'sort' => '-post_date'
                        ],
                        'count' => $source_counts[$source_key]
                    ];
                }
            }
        }



        return $filters;
    }

    // access permission
    public static function dt_filter_access_permissions( $permissions, $post_type ){
        if ( $post_type === self::post_type() ){
            if ( current_user_can( 'dt_all_crm_contacts' ) ){
                $permissions['type'] = [ 'crm', 'access', 'user', 'access_placeholder' ];
            }
        }
        return $permissions;
    }

    // filter for access to a specific record
    public function can_view_permission_filter( $has_permission, $post_id, $post_type ){
        if ( $post_type === $this->post_type ){
            $contact_type = get_post_meta( $post_id, 'type', true );
            if ( current_user_can( 'dt_all_crm_contacts' ) ){
                if ( in_array( $contact_type, [ 'crm', 'access', 'access_placeholder' ], true ) ){
                    return true;
                }
            }
        }
        return $has_permission;
    }
    public function can_update_permission_filter( $has_permission, $post_id, $post_type ){
        if ( $post_type === $this->post_type ){
            $contact_type = get_post_meta( $post_id, 'type', true );
            if ( current_user_can( 'dt_all_crm_contacts' ) ){
                if ( in_array( $contact_type, [ 'crm', 'access', 'access_placeholder' ], true ) ){
                    return true;
                }
            }
        }
        return $has_permission;
    }

    public function dt_can_delete_permission( $can_delete, $post_id, $post_type ){
        //allow administrators to delete more contacts
        if ( $post_type === $this->post_type && current_user_can( 'delete_' . $post_type ) ){
            $contact_type = get_post_meta( $post_id, 'type', true );
            if ( in_array( $contact_type, [ 'crm', 'access', 'access_placeholder' ], true ) ){
                return true;
            }
        }
        return $can_delete;
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
        $types[] = 'crm';
        return $types;
    }

    public function dt_get_viewable_compact_search_query( $query, $post_type, $search_string, $args ){
        if ( $post_type === 'contacts' ){
            $query[] = [
                'overall_status' => [ '-closed' ],
            ];
        }
        return $query;
    }

}


