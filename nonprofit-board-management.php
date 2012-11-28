<?php
/*
Plugin Name: Nonprofit Board Management
Plugin URI: WIRED IMPACT URL GOES HERE
Description: DESCRIPTION GOES HERE
Version: 0.1
Author: Wired Impact
Author URI: http://wiredimpact.com/
License: GPLv2
*/

/*
GPLv2 - read it - http://www.gnu.org/licenses/license-list.html#GPLCompatibleLicenses
*/

class wi_board_management {
  
    /*
     * Initiate the plugin by  running activation and attaching to hooks.
     */
    public function __construct() {
        register_activation_hook( __FILE__, array( $this, 'add_board_roles' ) );
        register_deactivation_hook( __FILE__, array( $this, 'remove_board_roles' ) );
        
        //Setup menu
        add_action( 'admin_menu', array( $this, 'create_menu' ) ); 
        
        //Add user notes field
        add_action( 'show_user_profile', array( $this, 'user_notes' ) );
        add_action( 'edit_user_profile', array( $this, 'user_notes' ) );
        //Save user note
        add_action( 'personal_options_update', array( $this, 'save_user_note' ) );
        add_action( 'edit_user_profile_update', array( $this, 'save_user_note' ) );
        //Delete user note using AJAX
        add_action( 'wp_ajax_delete_note', array( $this, 'delete_user_note' ) );
        add_action( 'wp_ajax_add_note', array( $this, 'save_user_note_ajax' ) );
        
        //Load CSS and JS
        add_action( 'admin_menu', array( $this, 'insert_css') );
        add_action( 'admin_menu', array( $this, 'insert_js') );
    }
    
    /*
     * Add the board roles when the plugin is first acticated.
     */
    public function add_board_roles(){
      // Create the board member role.
      add_role( 'board_member', 'Board Member', array( 'read' ) );
      
      // Create the board recruit role.
      add_role( 'board_recruit', 'Board Recruit', array( 'read' ) );
    }
    
    /*
     * Remove the board roles when the plugin is deactivated.
     */
    public function remove_board_roles(){
      // Delete the board member role if no user has it.
      $member_users = get_users( array( 'role' => 'board_member', 'number' => 1 ) );
      if( empty( $member_users ) ){
        remove_role( 'board_member' );
      }
      
      //Delete the board recruit role if no user has it.
      $recruit_users = get_users( array( 'role' => 'board_recruit', 'number' => 1 ) );
      if( empty( $recruit_users ) ){
        remove_role( 'board_recruit' );
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
    }
    
    
    
    /*
     * Create each of the menu items
     */
    public function create_menu(){
      //Create top level menu item
      add_menu_page( 'Nonprofit Board Management', 'Nonprofit Board Management', 'manage_options', 'nonprofit-board', array( $this, 'create_settings_page' ) );
      
      //Create submenu items
      add_submenu_page( 'nonprofit-board', 'Board Members', 'Board Members', 'manage_options', 'users.php?role=board_member' );
      add_submenu_page( 'nonprofit-board', 'Board Recruits', 'Board Recruits', 'manage_options', 'users.php?role=board_recruit' );
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
     * Display all the user notes fields and info
     */
    public function user_notes( $user ){ 
      $user_id = $user->ID;
      $role = $user->roles[0];
      if( 'board_recruit' == $role || 'board_member' == $role ){ //Only show notes on recruits and board members 
      
      ?>
      <h3>Board Member Notes</h3>

      <table class="form-table">

        <tr>
         <th><label for="note">Add Note</label></th>

         <td>
           <textarea name="note" id="note" rows="5" cols="30" data-user-id="<?php echo $user_id; ?>" /></textarea><input id="add-note" type="submit" class="button-primary" value="Add Note" /><br />
          <span class="description">Enter a new note for this board member or recruit.</span>
         </td>
        </tr>
      </table>
      
      <table class="form-table">
        
          <tr><th>Existing Notes</th>
          <td><table class="widefat" id="notes-list"><?php $this->show_user_notes( $user_id ); ?></table></td>
          </tr>

       </table>
       <?php }
    }
    
    /*
     * Show the individual notes for this user.
     */
    public function show_user_notes( $user_id ){
      $notes = get_user_meta( $user_id, 'note' );
      $notes_ordered = array_reverse ( $notes );
      
      foreach( $notes_ordered as $note ){
        $this->create_note_row( $user_id, $note );
      }
    }
    
    private function create_note_row( $user_id, $note ){ ?>
      <tr><td>
        <div class="note" data-user-id="<?php echo $user_id; ?>"><?php echo esc_attr( $note[0] ); ?></div>
        <div class="note-date submitted-on" data-timestamp="<?php echo $note[1]; ?>">Added on <?php echo date('F d, Y, g:ia', $note[1]); ?></div>
        <div class="note-delete"><span class="trash"><a href="#">Delete Note</a></span></div>
      </td></tr>
    <?php }
    
    /*
     * Save the new note for this user
     */
    //TODO MAKE SURE THE USER ID WE'RE CHECKING FOR PERMISSIONS IS THE USER CURRENTLY WORKING ON THE SITE, NOT THE ONE BEING EDITED
    public function save_user_note( $user_id ){
      if ( !current_user_can( 'edit_user', $user_id ) ){
        return false;
      }
      
      $safe_note = esc_textarea( $_POST['note'] );
      $note_data = array( $safe_note, current_time( 'timestamp' ) );
      
      add_user_meta( $user_id, 'note', $note_data );
    }
    
    /*
     * Save the user note via Ajax.
     */
    //TODO MAKE SURE THE USER ID WE'RE CHECKING FOR PERMISSIONS IS THE USER CURRENTLY WORKING ON THE SITE, NOT THE ONE BEING EDITED
    public function save_user_note_ajax(){
      $user_id = intval( $_POST['user_id'] );
      
      if ( !current_user_can( 'edit_user', $user_id ) ){
        return false;
      }
      
      $safe_note = esc_textarea( $_POST['note'] );
      $note_data = array( $safe_note, current_time( 'timestamp' ) );
      
      if( add_user_meta( $user_id, 'note', $note_data ) ){
        //Send back the complete table row to be added to the table.
        $this->create_note_row( $user_id, $note_data );
      }
      else {
        echo FALSE;
      }
      
      die(); //Required to avoid errors
    }
    
    /*
     * Delete user note using Ajax
     */
    public function delete_user_note(){      
      //TODO Add check_ajax_referer for security purposes.
      $user_id = intval( $_POST['user_id'] );
      $meta_key = esc_html( $_POST['meta_key'] );
      $meta_value = array( esc_html( $_POST['note'] ), intval( $_POST['note_timestamp'] ) );
      
      if( delete_user_meta( $user_id, $meta_key, $meta_value ) ){
       echo 'deleted'; 
      }
      else {
       echo 'We failed to delete that note.  Please try again.';
      }
      
      die(); //Required to avoid errors
    }
     
} //end class board_management

//Setup some constants for us to more easily work with files
define("BOARD_MANAGEMENT_BASENAME", plugin_basename(__FILE__) );
define("BOARD_MANAGEMENT_PLUGINPATH", "/" . plugin_basename(dirname(__FILE__)) . "/");
define("BOARD_MANAGEMENT_PLUGINFULLPATH", WP_PLUGIN_DIR . BOARD_MANAGEMENT_PLUGINPATH);
define("BOARD_MANAGEMENT_PLUGINFULLURL", WP_PLUGIN_URL . BOARD_MANAGEMENT_PLUGINPATH);

//Instantiate our board class
$wi_board = new wi_board_management();