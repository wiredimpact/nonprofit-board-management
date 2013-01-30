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
 * WI_Board_Management is used to set up the board management plugin by adding the needed roles
 * and caps, along with providing most of the necessary css and js.
 *
 * @package Nonprofit Board Management
 *
 * @version 0.1
 * @author Wired Impact
 */
class WI_Board_Management {
  
   /*
    * All of the board members' user objects.
    * 
    * @var array
    */
    public $board_members;
    
  
    public function __construct(){
        //Put all the user objects for every board member in a variable.
        $this->board_members = $this->get_users_who_serve();
      
        register_activation_hook( __FILE__, array( $this, 'add_board_roles' ) );
        register_deactivation_hook( __FILE__, array( $this, 'remove_board_roles' ) );
        
        //Setup top level menu
        add_action( 'admin_menu', array( $this, 'create_menu' ), 10 ); 
        
        //Load CSS and JS
        add_action( 'admin_menu', array( $this, 'insert_css') );
        add_action( 'admin_menu', array( $this, 'insert_js') );
        
        //Add our board members dashboard widget
        add_action('wp_dashboard_setup', array( $this, 'add_board_members_dashboard_widget' ) );
        
        //Add notice to admin who can't serve on board in case they want to.
        add_action( 'admin_notices', array( $this, 'show_admins_notices' ) );

        //Allow admin to click a button and start serving on the board.
        add_action( 'wp_ajax_allow_user_to_serve', array( $this, 'allow_user_to_serve' ) );
    }
    
    
    /*
     * Add the board roles when the plugin is first activated.
     */
    public function add_board_roles(){   
      add_role( 
              'board_member',
              'Board Member', 
              array( 
                  'read' => true,
                  'view_board_content' => true,
                  'edit_board_content' => true,
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
      
      //Give admin access to view and edit all board content.
      //Initially they can't serve on the board, but can add that cap
      //through the UI.
      $role =& get_role( 'administrator' );
      if ( !empty( $role ) ){
        $role->add_cap( 'view_board_content' );
        $role->add_cap( 'edit_board_content' );
        
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
     * Remove the board member role when the plugin is deactivated.
     * 
     * We remove the board member role when the plugin is deactivated,
     * but we do not remove the caps from the admins since we still want
     * them to have those caps if the board is activated again.
     */
    public function remove_board_roles(){
      $member_users = get_users( array( 'role' => 'board_member', 'number' => 1 ) );
      if( empty( $member_users ) ){
        remove_role( 'board_member' );
      }
    }
    
    
    /*
     * Enqueue the necessary CSS.
     */
    public function insert_css(){
      wp_enqueue_style( 'board-mgmt', BOARD_MANAGEMENT_PLUGINFULLURL . 'css/custom.css' );
    }

    
    /*
     * Enqueue the necessary JS.
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
     * Create each of the menu items we need for board management.
     */
    public function create_menu(){
      //Create top level menu item
      add_menu_page( 'Nonprofit Board Management', 'Board Mgmt', 'view_board_content', 'nonprofit-board' );
      
      //Create Board Members page
      add_submenu_page( 'nonprofit-board', 'Board Members', 'Board Members', 'view_board_content', 'nonprofit-board', array( $this, 'display_members_page' ) );
      
      //Add edit and new board event pages to our top level menu so the board member role has correct caps.
      add_submenu_page( 'nonprofit-board', 'Board Events', 'Board Events', 'edit_board_events' , 'edit.php?post_type=board_events' ); 
      add_submenu_page( 'nonprofit-board', 'Board Committees', 'Board Committees', 'edit_board_committees' , 'edit.php?post_type=board_committees' ); 
      
      //Add edit and new board commmittee pages to our top level menu so the board member role has correct caps.
      add_submenu_page( 'nonprofit-board', 'Add Board Event', 'Add Board Event', 'edit_board_events' , 'post-new.php?post_type=board_events' ); 
      add_submenu_page( 'nonprofit-board', 'Add Board Committee', 'Add Board Committee', 'edit_board_committees' , 'post-new.php?post_type=board_committees' ); 
      
      //Add Resources and Support pages
      add_submenu_page( 'nonprofit-board', 'Board Resources', 'Board Resources', 'view_board_content', 'nonprofit-board/resources', array( $this, 'display_resources_page' ) );
      add_submenu_page( null, 'Edit Your Board Resources', 'Edit Your Board Resources', 'edit_board_content', 'nonprofit-board/resources/edit', array( $this, 'edit_resources_page' ) );
      add_submenu_page( 'nonprofit-board', 'Support', 'Support', 'view_board_content', 'nonprofit-board/support', array( $this, 'display_support_page' ) );
    }
    
    
    /*
     * Display the list of board members with their contact info and current committees.
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
        $board_members = $this->board_members;
        
        //If no board members were found then give them a message.
        if( empty( $board_members ) ){ ?>
            <tr class="no-items">
              <td class="colspanchange" colspan="5"><?php _e( 'No board members were found.  You should create some users and set their role to "Board Member".' ); ?></td>
            </tr>
        <?php
        }
        
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
    <?php }//display_members_page()

    
    /*
     * Get all the meta data for a board member.
     * 
     * @param int $board_member_id User ID of the board member.
     * @return object Meta data for the provided board member.
     */
    private function get_board_member_meta( $board_member_id ){
      $board_member_meta = new stdClass();
      $board_member_meta->phone = get_user_meta( $board_member_id, 'phone', true );
      $board_member_meta->current_employer = get_user_meta( $board_member_id, 'current_employer', true );
      $board_member_meta->job_title = get_user_meta( $board_member_id, 'job_title', true );
      
      return $board_member_meta;
    }
   
    
    /*
     * Display the content for the board resources page.
     */
    public function display_resources_page(){
      ?>
      <div class="wrap board-resources">
        <?php screen_icon( 'options-general' ); ?>
        <h2><?php _e( 'Board Resources' ); ?></h2>
        <p><?php _e( 'We\'ve provided two resource sections.  One for you to include your own resources
          and one where we\'ve included resources we think are helpful.' ); ?></p>
        <h3>
          <?php _e( 'Your Board Resources' ); ?>
          <a class="button secondary-button" href="<?php bloginfo( 'wpurl' ); ?>/wp-admin/admin.php?page=nonprofit-board/resources/edit">
            <?php _e( 'Edit your board resources' ); ?>
          </a>
        </h3>
        <div class="custom-board-resources">
          <?php echo stripslashes( get_option( 'board_resources_content', 'You haven\'t added any resources yet.  Use the edit button above to add some.' ) ); ?>
        </div>
        
        <h3><?php _e( 'Some Other Helpful Resources' ); ?></h3>
      </div><!-- /wrap -->
      <?php
    }
    
    
    /*
     * Screen for editing the organization's board resources content.
     */
    public function edit_resources_page(){
      if( isset( $_POST['board_resources'] ) ){
        $result = $this->save_board_resources();
        
        //If updated we show an updated message to the user.
        if( $result == true ){
          ?>
          <div class="updated">
            <p>
              <?php _e( 'Your board resources have been updated.' ); ?>
              <a href="<?php bloginfo( 'wpurl' ); ?>/wp-admin/admin.php?page=nonprofit-board/resources">
                <?php _e( 'View your board resources.' ); ?>
              </a>
            </p>
          </div>
          <?php
        }
      }
      
      ?>
      <div class="wrap edit-board-resources">
        <?php screen_icon( 'options-general' ); ?>
        <h2><?php _e( 'Edit Your Board Resources' ); ?></h2>
        <p><?php _e( 'Edit the content in your board resources section.' ); ?></p>
        <form method="post" action="">
        <div id="poststuff">
          <div class="postbox">
            <h3 class="hndle">
              <span><?php _e( 'Save Your Resources' ); ?></span>
            </h3>
            <div class="inside">
              <input type="submit" class="button button-primary button-large" value="Update" />
              <a class="button secondary-button button-large" href="<?php bloginfo( 'wpurl' ); ?>/wp-admin/admin.php?page=nonprofit-board/resources">
                <?php _e( 'Back to Resources' ); ?>
              </a>
            </div>
          </div><!-- /postbox -->
        </div><!-- /poststuff -->
        <div id="edit-resources-editor">
          <?php wp_editor( stripslashes( get_option( 'board_resources_content' ) ), 'board_resources', array( 'teeny' => true ) ); ?>
        </div><!-- /edit-resources-editor -->
        <?php $board_resources_nonce = wp_create_nonce( 'board_resources_nonce' ); ?>
        <input type="hidden" id="board_resources_nonce" name="board_resources_nonce" value="<?php echo $board_resources_nonce; ?>" />
        </form>
      </div><!-- /wrap -->
      <?php
    }
    
    
    /*
     * Save the organization's board resources.
     * 
     * @return bool True if the resources were updated, false otherwise.
     */
    private function save_board_resources(){
      if( !current_user_can( 'edit_board_content' ) ){
        return false;
      }
      if ( !isset( $_POST['board_resources_nonce'] ) || !wp_verify_nonce( $_POST['board_resources_nonce'], 'board_resources_nonce' ) ){
        return false;
      }
      
      //Sanitize the board resources content, then save or delete it.
      $clean_content = wp_kses_post( $_POST['board_resources'] );
      if( $clean_content != '' ){
        $result = update_option( 'board_resources_content', $clean_content );
      }
      else{
        $result = delete_option( 'board_resources_content' );
      }
      
      return $result;
    }
    
    
    /*
     * Display the content for our support page.
     * TODO Replace these videos with our support videos.
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
     * Add our members dashboard widget to the list of widgets.
     */
    public function add_board_members_dashboard_widget(){
      if( current_user_can( 'view_board_content' ) ){
        wp_add_dashboard_widget('board_members_db_widget', 'Board Members', array( $this, 'display_board_members_dashboard_widget' ) );
      }
    }
    
    
    /*
     * Display a dashboard widget for all of the board members.
     * 
     * @see add_board_members_dashboard_widget()
     */
    public function display_board_members_dashboard_widget(){
      $board_members = $this->board_members;
      
      //If we don't have any board members then the user needs a message.
      if( empty( $board_members ) ){
        _e( 'You don\'t have any board members.  You should create some users and set their role to "Board Member".' );
        
        return;
      }
      
      ?>
        <table class="widefat">
          <thead>
            <th scope="col" class="column-name">Name</th>
            <th scope="col" class="column-phone">Phone</th>
            <th scope="col" lass="column-email">Email</th>
          </thead>
          <tbody>
      <?php
      $alternate = 'alternate';
      
      foreach( $board_members as $board_member ){
        $board_member_meta = $this->get_board_member_meta( $board_member->ID );
        ?>
        <tr class="<?php echo $alternate; ?>">
          <td><?php echo $board_member->display_name; ?></td>
          <td><?php echo $board_member_meta->phone; ?></td>
          <td><?php echo $board_member->user_email; ?></td>
        </tr>
        <?php
      $alternate = ( $alternate == 'alternate' ) ? '' : 'alternate';  
      }
      
      ?>
      </tbody></table>
      <p class="note"><a href="<?php bloginfo( 'wpurl' ); ?>/wp-admin/admin.php?page=nonprofit-board">View more board member details</a></p>
      <?php
    }

    
    /*
     * Get the users who serve on the board.
     * 
     * The users who serve on the board includes all users
     * with the board member role and any admins who added the
     * serve_on_board capability.
     * 
     * @return array User objects for users who can serve on the board.
     */
    private function get_users_who_serve(){
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
    * RSVP to events, join committees and show in the board members list.
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
    * Allow the current user to serve on the board.
    * 
    * Via ajax allow the current user to serve on the board 
    * by giving them the capability.  Only admins have
    * the ability to use this method since the button used to activate
    * this method is only shown to that role.
    * 
    * @see show_admin_notices()
    * @return string Echos '1' to show that the method has run.
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