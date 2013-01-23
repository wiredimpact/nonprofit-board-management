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
        
        //Setup top level menu
        add_action( 'admin_menu', array( $this, 'create_menu' ), 10 ); 
        
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
                  
                  //Board event caps
                  'rsvp_board_events' => true,
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
                  
                  //Board committee caps
                  'join_board_committee' => true,
                  'edit_board_committees' => true,
                  'edit_others_board_committees' => true,
                  'publish_board_committees' => true,
                  'read_private_board_committees' => true,
                  'delete_board_committees' => true,
                  'delete_private_board_committees' => true,
                  'delete_published_board_committees' => true,
                  'delete_others_board_committees' => true,
                  'edit_private_board_committees' => true,
                  'edit_published_board_committees' => true
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
        
        //Board event caps
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
        
        //Board committee caps
        $role->add_cap( 'edit_board_committees' );
        $role->add_cap( 'edit_others_board_committees' );
        $role->add_cap( 'publish_board_committees' );
        $role->add_cap( 'read_private_board_committees' );
        $role->add_cap( 'delete_board_committees' );
        $role->add_cap( 'delete_private_board_committees' );
        $role->add_cap( 'delete_published_board_committees' );
        $role->add_cap( 'delete_others_board_committees' );
        $role->add_cap( 'edit_private_board_committees' );
        $role->add_cap( 'edit_published_board_committees' );
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
      add_menu_page( 'Nonprofit Board Management', 'Board Mgmt', 'view_board_content', 'nonprofit-board', array( $this, 'create_settings_page' ) );
      
      //Create Board Members page
      add_submenu_page( 'nonprofit-board', 'Board Members', 'Board Members', 'view_board_content', 'nonprofit-board/members', array( $this, 'display_members_page' ) );
      
      //Add edit and new board event links to our top level menu so the board member role has correct caps.
      add_submenu_page( 'nonprofit-board', 'Board Events', 'Board Events', 'edit_board_events' , 'edit.php?post_type=board_events' ); 
      add_submenu_page( 'nonprofit-board', 'Add Board Event', 'Add Board Event', 'edit_board_events' , 'post-new.php?post_type=board_events' ); 
      
      //Add edit and new board commmittee links to our top level menu so the board member role has correct caps.
      add_submenu_page( 'nonprofit-board', 'Board Committees', 'Board Committees', 'edit_board_committees' , 'edit.php?post_type=board_committees' ); 
      add_submenu_page( 'nonprofit-board', 'Add Board Committee', 'Add Board Committee', 'edit_board_committees' , 'post-new.php?post_type=board_committees' ); 
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
    
    /*
     * Create board members list page
     */
    public function display_members_page(){ ?>
      <div class="wrap">
        <?php screen_icon( 'options-general' ); ?>
        <h2><?php _e( 'Board Members' ); ?></h2>
        <table class="wp-list-table widefat fixed posts" id="board-members-table" cellspacing="0">
          <thead>
            <tr>
              <th scope="col" id="name" class="manage-column column-name">Name</th>
              <th scope="col" id="phone" class="manage-column column-phone">Phone</th>
              <th scope="col" id="email" class="manage-column column-email">Email</th>
              <th scope="col" id="job" class="manage-column column-job">Job</th>
              <th scope="col" id="committees" class="manage-column column-committees">Committees</th>
            </tr>
          </thead>
          <tfoot>
            <tr>
              <th scope="col" class="manage-column column-name">Name</th>
              <th scope="col" class="manage-column column-phone">Phone</th>
              <th scope="col" class="manage-column column-email">Email</th>
              <th scope="col" class="manage-column column-job">Job</th>
              <th scope="col" class="manage-column column-committees">Committees</th>
            </tr>
          </tfoot>
          <tbody>
        
        <?php
        $board_members = $this->get_board_members();
        $alternate = 'alternate';
        foreach( $board_members as $board_member ){
         $board_member_meta = $this->get_board_member_meta( $board_member->ID );
         $job = $board_member_meta->job_title;
         if( $board_member_meta->current_employer != '' and $board_member_meta->job_title != ''){
           $job .= __(' at ');
         }
         $job .= $board_member_meta->current_employer;
         ?>
          <tr class="<?php echo $alternate; ?>">
            <td class="name column-username"><?php echo get_avatar( $board_member->ID, '44' ); echo '<strong>' . $board_member->display_name . '</strong>'; ?></td>
            <td class="phone column-phone"><?php echo $board_member_meta->phone; ?></td>
            <td class="email column-email"><?php echo $board_member->user_email; ?></td>
            <td class="job column-job"><?php echo $job; ?></td>
            <td class="committees column-committees"><?php echo WI_Board_Committees::get_user_committees( $board_member->ID ); ?></td>
          </tr>
        <?php
        $alternate = ( $alternate == 'alternate' ) ? '' : 'alternate';
        }        
        ?>
        
          </tbody>
        </table>
        <p>You can set your photo by creating an account at <a href="http://en.gravatar.com/" target="_blank">Gravatar</a>
           and your name can be adjusted by using the "Display name publicly as" dropdown in 
           <a href="<?php bloginfo('wpurl'); ?>/wp-admin/profile.php">your profile</a>.</p>
      </div>
    <?php }
    
    /*
     * Get all board members and those with cap to serve on board.
     */
    private function get_board_members(){
      $board_members = get_users( array( 'role' => 'board_member' ) );
      
      return $board_members;
    }
    
    /*
     * Get all the meta data for a board member.
     */
    private function get_board_member_meta( $board_member_id ){
      $board_member_meta = new stdClass();
      $board_member_meta->phone = get_user_meta( $board_member_id, 'phone', true );
      $board_member_meta->current_employer = get_user_meta( $board_member_id, 'current_employer', true );
      $board_member_meta->job_title = get_user_meta( $board_member_id, 'job_title', true );

      
      return $board_member_meta;
    }
     
} //end class board_management

if( is_admin() ){
  //Setup some constants for us to more easily work with files
  define( "BOARD_MANAGEMENT_BASENAME", plugin_basename(__FILE__) );
  define( "BOARD_MANAGEMENT_PLUGINPATH", "/" . plugin_basename(dirname(__FILE__)) . "/" );
  define( "BOARD_MANAGEMENT_PLUGINFULLPATH", WP_PLUGIN_DIR . BOARD_MANAGEMENT_PLUGINPATH );
  define( "BOARD_MANAGEMENT_PLUGINFULLURL", WP_PLUGIN_URL . BOARD_MANAGEMENT_PLUGINPATH );
  define( "BOARD_MANAGEMENT_FILEFULLPATH", BOARD_MANAGEMENT_PLUGINFULLPATH . 'nonprofit-board-management.php' );

  //Add board notes and board event classes
  //TODO Move notes to be its own plugin.
  //require_once BOARD_MANAGEMENT_PLUGINFULLPATH . 'includes/class-board-notes.php';
  require_once BOARD_MANAGEMENT_PLUGINFULLPATH . 'includes/class-board-events.php';
  require_once BOARD_MANAGEMENT_PLUGINFULLPATH . 'includes/class-board-committees.php';

  //Instantiate each of our classes.
  $wi_board = new WI_Board_Management();
  //TODO Move notes to be its own plugin.
  //$wi_board_notes = new WI_Board_Notes();
  $wi_board_events = new WI_Board_Events();
  $wi_board_committees = new WI_Board_Committees();
}