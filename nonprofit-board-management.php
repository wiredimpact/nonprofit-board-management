<?php
/*
Plugin Name: Nonprofit Board Management
Plugin URI: WIRED IMPACT URL GOES HERE
Description: Manage your board of directors or young friends board directly from WordPress.
Version: 0.1
Author: Wired Impact
Author URI: http://wiredimpact.com/
License: GPLv2
*/

/*
GPLv2 - read it - http://www.gnu.org/licenses/license-list.html#GPLCompatibleLicenses
*/


/**
 * WI_Board_Management adds the new user roles we need and the menu items
 * we'll use for the plugin.
 *
 * @author Wired Impact
 */
class WI_Board_Management {
  
    /*
     * Initiate the plugin by  running activation and attaching to hooks.
     */
    public function __construct(){
        register_activation_hook( __FILE__, array( $this, 'add_board_roles' ) );
        register_deactivation_hook( __FILE__, array( $this, 'remove_board_roles' ) );
        
        //Setup menu
        //We set the priority to 9 so the page will appear even when adding
        //the board events custom post type to our menu.
        add_action( 'admin_menu', array( $this, 'create_menu' ), 9 ); 
        
        //Load CSS and JS
        add_action( 'admin_menu', array( $this, 'insert_css') );
        add_action( 'admin_menu', array( $this, 'insert_js') );
    }
    
    /*
     * Add the board roles when the plugin is first activated.
     */
    public function add_board_roles(){   
      //TODO Combine roll creation with a for loop.
      //Create the board member role.
      add_role( 
              'board_member',
              'Board Member', 
              array( 
                  'read' => true,
                  'view_board_content' => true,
                  'contain_board_info' => true,
                  'rsvp_board_events' => true,
                  'edit_board_event' => true,
                  'read_board_event' => true,
                  'delete_board_event' => true,
                  'edit_board_events' => true,
                  'edit_others_board_events' => true,
                  'publish_board_events' => true,
                  'read_private_board_events' => true,
                  'delete_board_events' => true,
                  'delete_private_board_events' => true,
                  'delete_published_board_events' => true,
                  'delete_others_board_events' => true,
                  'edit_private_board_events' => true,
                  'edit_published_board_events' => true,
                  )
              );
      
      //Create the board recruit role.
      add_role(
              'board_recruit',
              'Board Recruit',
              array(
                  'read' => true,
                  'view_board_content' => true,
                  'contain_board_info' => true
                  )
              ); 
      
      //Give admin access to view all board content.
      $role =& get_role( 'administrator' );
      if ( !empty( $role ) ){
        $role->add_cap( 'view_board_content' );
        $role->add_cap( 'edit_board_event' );
        $role->add_cap( 'read_board_event' );
        $role->add_cap( 'delete_board_event' );
        $role->add_cap( 'edit_board_events' );
        $role->add_cap( 'edit_others_board_events' );
        $role->add_cap( 'publish_board_events' );
        $role->add_cap( 'read_private_board_events' );
        $role->add_cap( 'delete_board_events' );
        $role->add_cap( 'delete_private_board_events' );
        $role->add_cap( 'delete_published_board_events' );
        $role->add_cap( 'delete_others_board_events' );
        $role->add_cap( 'edit_private_board_events' );
        $role->add_cap( 'edit_published_board_events' );     
      }
    }
    
    /*
     * Remove the board roles when the plugin is deactivated.
     */
    public function remove_board_roles(){
      //Delete the board member role if no user has it.
      //TODO Combine removal of both roles with a for loop.
      $member_users = get_users( array( 'role' => 'board_member', 'number' => 1 ) );
      if( empty( $member_users ) ){
        remove_role( 'board_member' );
      }
      
      //Delete the board recruit role if no user has it.
      $recruit_users = get_users( array( 'role' => 'board_recruit', 'number' => 1 ) );
      if( empty( $recruit_users ) ){
        remove_role( 'board_recruit' );
      }
      
      //Remove admin capability if the plugin is deactivated.
      $role =& get_role( 'administrator' );
      if ( !empty( $role ) ){
        $role->remove_cap( 'view_board_content' );
      }
    }
    
    /*
     * Enqueue CSS
     */
    public function insert_css(){
      wp_enqueue_style( 'board-mgmt', BOARD_MANAGEMENT_PLUGINFULLURL . 'css/custom.css' );
    }
    
    /*
     * Enqueue JS
     */
    public function insert_js(){      
      wp_enqueue_script( 'board-mgmt', BOARD_MANAGEMENT_PLUGINFULLURL . 'js/custom.js', 'jquery' );
      
      //wp_localize_script allows us to send PHP info to JS
      wp_localize_script( 'board-mgmt', 'wi_board_mgmt', array(
        // generate a nonces that can be checked later on save and delete
        'save_note_nonce' => wp_create_nonce( 'save_note_nonce' ),  
        'delete_note_nonce' => wp_create_nonce( 'delete_note_nonce' ),
        'error_deleting_note' => _( 'Woops.  We failed to add your note.  Please try again.' )
        )
       );
    }
    
    
    
    /*
     * Create each of the menu items
     */
    public function create_menu(){
      //Create top level menu item
      add_menu_page( 'Nonprofit Board Management', 'Nonprofit Board Management', 'view_board_content', 'nonprofit-board', array( $this, 'create_settings_page' ) );
      
      //Create submenu items
      add_submenu_page( 'nonprofit-board', 'Board Members', 'Board Members', 'view_board_content', 'users.php?role=board_member' );
      add_submenu_page( 'nonprofit-board', 'Board Recruits', 'Board Recruits', 'view_board_content', 'users.php?role=board_recruit' );
    }
    
    /*
     * Create the settings page for the plugin.
     */
    public function create_settings_page(){ ?>
      <div class="wrap">
        <?php screen_icon( 'options-general' ); ?>
        <h2>Nonprofit Board Management</h2>
      </div>
    <?php }
     
} //end class board_management

if( is_admin() ){
  //Setup some constants for us to more easily work with files
  define( "BOARD_MANAGEMENT_BASENAME", plugin_basename(__FILE__) );
  define( "BOARD_MANAGEMENT_PLUGINPATH", "/" . plugin_basename(dirname(__FILE__)) . "/" );
  define( "BOARD_MANAGEMENT_PLUGINFULLPATH", WP_PLUGIN_DIR . BOARD_MANAGEMENT_PLUGINPATH );
  define( "BOARD_MANAGEMENT_PLUGINFULLURL", WP_PLUGIN_URL . BOARD_MANAGEMENT_PLUGINPATH );
  define( "BOARD_MANAGEMENT_FILEFULLPATH", BOARD_MANAGEMENT_PLUGINFULLPATH . 'nonprofit-board-management.php' );

  //Add board notes and board event classes
  require_once BOARD_MANAGEMENT_PLUGINFULLPATH . 'includes/class-board-notes.php';
  require_once BOARD_MANAGEMENT_PLUGINFULLPATH . 'includes/class-board-events.php';

  //Instantiate each of our classes.
  $wi_board = new WI_Board_Management();
  $wi_board_notes = new WI_Board_Notes();
  $wi_board_events = new WI_Board_Events();
}