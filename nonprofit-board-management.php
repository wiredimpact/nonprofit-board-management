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
        
        //Add notice to admin who can't serve on board in case they want to.
        add_action( 'admin_notices', array( $this, 'show_admins_notices' ) );

        //Allow admin to click a button and start serving on the board.
        add_action( 'wp_ajax_allow_user_to_serve', array( $this, 'allow_user_to_serve' ) );
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
                  'serve_on_board' => true,
                  
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
        'allow_serve_nonce' => wp_create_nonce( 'allow_serve_nonce' ),
        'error_allow_serve' => __( 'Woops. We weren\'t able to allow you to RSVP.  Please try again.' ),
        )
       );
    }
    
    
    /*
     * Create each of the menu items
     */
    public function create_menu(){
      //Create top level menu item
      add_menu_page( 'Nonprofit Board Management', 'Board Mgmt', 'view_board_content', 'nonprofit-board' );
      
      //Create Board Members page
      add_submenu_page( 'nonprofit-board', 'Board Members', 'Board Members', 'view_board_content', 'nonprofit-board', array( $this, 'display_members_page' ) );
      
      //Add edit and new board event links to our top level menu so the board member role has correct caps.
      add_submenu_page( 'nonprofit-board', 'Board Events', 'Board Events', 'edit_board_events' , 'edit.php?post_type=board_events' ); 
      add_submenu_page( 'nonprofit-board', 'Board Committees', 'Board Committees', 'edit_board_committees' , 'edit.php?post_type=board_committees' ); 
      
      //Add edit and new board commmittee links to our top level menu so the board member role has correct caps.
      add_submenu_page( 'nonprofit-board', 'Add Board Event', 'Add Board Event', 'edit_board_events' , 'post-new.php?post_type=board_events' ); 
      add_submenu_page( 'nonprofit-board', 'Add Board Committee', 'Add Board Committee', 'edit_board_committees' , 'post-new.php?post_type=board_committees' ); 
      
      //Add Resources and Support pages
      add_submenu_page( 'nonprofit-board', 'Board Resources', 'Board Resources', 'view_board_content', 'nonprofit-board/resources', array( $this, 'display_resources_page' ) );
      add_submenu_page( 'nonprofit-board', 'Support', 'Support', 'view_board_content', 'nonprofit-board/support', array( $this, 'display_support_page' ) );
    }
    
    
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
        $board_members = $this->get_users_who_serve();
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
     * Get all the meta data for a board member.
     */
    private function get_board_member_meta( $board_member_id ){
      $board_member_meta = new stdClass();
      $board_member_meta->phone = get_user_meta( $board_member_id, 'phone', true );
      $board_member_meta->current_employer = get_user_meta( $board_member_id, 'current_employer', true );
      $board_member_meta->job_title = get_user_meta( $board_member_id, 'job_title', true );

      
      return $board_member_meta;
    }
   
    
    /*
     * Display the content for our resources page.
     */
    public function display_resources_page(){
      ?>
      <div class="wrap">
        <?php screen_icon( 'options-general' ); ?>
        <h2><?php _e( 'Board Resources' ); ?></h2>
      <?php
    }
    
    
    /*
     * Display the content for our support page.
     */
    public function display_support_page(){
      ?>
      <div class="wrap">
        <?php screen_icon( 'options-general' ); ?>
        <h2><?php _e( 'Support' ); ?></h2>
        <p><?php _e( 'In case you need help here are some videos to help you navigate the board management plugin.' ); ?></p>
        
        <h3><?php _e( 'Getting Started with Nonprofit Board Management' ); ?></h3>
        <iframe width="640" height="360" src="http://www.youtube.com/embed/66TuSJo4dZM" frameborder="0" allowfullscreen></iframe>
        
        <h3><?php _e( 'Allowing Admins to Serve on the Board' ); ?></h3>
        <iframe width="640" height="360" src="http://www.youtube.com/embed/yQ5U8suTUw0" frameborder="0" allowfullscreen></iframe>
        
        <h3><?php _e( 'RSVP to Board Events' ); ?></h3>
        <iframe width="640" height="360" src="http://www.youtube.com/embed/lB95KLmpLR4" frameborder="0" allowfullscreen></iframe>
      <?php     
    }
    
    
    /*
    * Get the users who serve on the board.
    * 
    * @return array Users who can serve on the board.
    */
    public static function get_users_who_serve(){
      $board_members = get_users( array( 'role' => 'board_member' ) );
      $admins = get_users( array( 'role' => 'administrator' ) );

      //Check if admins can rsvp and if not, remove them from the array.
      $admins_count = count( $admins );
      for( $i = 0; $i < $admins_count; $i++ ){
        if( !isset( $admins[$i]->allcaps['serve_on_board'] ) || $admins[$i]->allcaps['serve_on_board'] != true ) {
          unset( $admins[$i] );
        }
      }

      //Combine board members with admins opted to rsvp
      $users_serving = array_merge( $board_members, $admins );

      return $users_serving;
    }
    
    /*
    * Show notice to admins allowing them to start serving on the board if they'd like.
    * 
    * Show notice to admins that allows them to start serving on the board.  Handling
    * of the button click is done through ajax.  With this cap they're able to
    * RSVP to events, join committees and show in the members list.
    * 
    * @see allow_user_to_serve()
    */
   public function show_admins_notices(){
     $screen = get_current_screen();
     
     //If the admin already has the serve capability then don't show the message.
     if( current_user_can( 'serve_on_board' ) ) return;
     
     //If the admin is on the members, events, or committees list then show them message.
     if( $screen->id == 'edit-board_events' || $screen->id == 'toplevel_page_nonprofit-board' || $screen->id == 'edit-board_committees' ){
     ?>
     <div class="updated">
       <p><?php _e( 'You don\'t have the board member role, so you can\'t RSVP to board events, join committees,
         or show up in the board member list.' ); ?>
         <input id="allow-board-serve" type="submit" class="button secondary-button" value="Serve on the Board" />
       </p>
     </div>
     <?php
     }//End if
   }


   /*
    * Via ajax allow the current user to serve on the board 
    * by giving them the capability.
    * 
    * @see show_admin_notices()
    * @return string Echos '1' to show that capability has been added.
    */
   public function allow_user_to_serve(){
     check_ajax_referer( 'allow_serve_nonce', 'security' );

     $current_user = wp_get_current_user();
     $current_user->add_cap( 'serve_on_board' );

     echo '1';

     die();
   }
     
} //WI_Board_Management

if( is_admin() ){
  //Setup some constants for us to more easily work with files
  define( "BOARD_MANAGEMENT_BASENAME", plugin_basename(__FILE__) );
  define( "BOARD_MANAGEMENT_PLUGINPATH", "/" . plugin_basename(dirname(__FILE__)) . "/" );
  define( "BOARD_MANAGEMENT_PLUGINFULLPATH", WP_PLUGIN_DIR . BOARD_MANAGEMENT_PLUGINPATH );
  define( "BOARD_MANAGEMENT_PLUGINFULLURL", WP_PLUGIN_URL . BOARD_MANAGEMENT_PLUGINPATH );
  define( "BOARD_MANAGEMENT_FILEFULLPATH", BOARD_MANAGEMENT_PLUGINFULLPATH . 'nonprofit-board-management.php' );

  //Add board events and committees classes
  require_once BOARD_MANAGEMENT_PLUGINFULLPATH . 'includes/class-board-events.php';
  require_once BOARD_MANAGEMENT_PLUGINFULLPATH . 'includes/class-board-committees.php';

  //Instantiate each of our classes.
  $wi_board_mgmt = new WI_Board_Management();
  $wi_board_events = new WI_Board_Events();
  $wi_board_committees = new WI_Board_Committees();
}