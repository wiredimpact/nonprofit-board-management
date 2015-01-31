<?php
/**
 * WI_Board_Committees allows the board to create committees and add board members to each of those committees.
 * 
 * The WI_Board_Committees class allows board members to add themselves 
 * and others to committees.  Board members can also see the name, description and
 * who is on each committee. Nothing in this class is created for the front-end of WordPress.
 *
 * @package Nonprofit Board Management
 *
 * @version 1.1.2
 * @author Wired Impact
 */
class WI_Board_Committees {
  
  public function __construct() {
    if( is_admin() ){
      //Create our board committees custom post type
      add_action( 'init', array( $this, 'create_board_committees_type' ) );
      add_action( 'admin_init', array( $this, 'create_board_committee_meta_boxes' ) );
      add_action( 'save_post', array( $this, 'save_board_committees_meta' ), 10, 2 );

      //Handle meta capabilities for our board_committees custom post type.
      add_filter( 'map_meta_cap', array( $this, 'board_committees_map_meta_cap' ), 10, 4 );

      //Remove the filter field from the board committees list screen
      add_action( 'admin_head', array( $this, 'remove_date_filter' ) );

      //Remove visibility settings for committees.
      add_action( 'admin_head-post.php', array( $this, 'hide_visibility_options' ) );
      add_action( 'admin_head-post-new.php', array( $this, 'hide_visibility_options' ) );

      //Remove quick edit from the table list of committees
      add_filter( 'post_row_actions', array( $this, 'remove_quick_edit' ), 10, 2 );

      //Change post updated content.
      add_filter( 'post_updated_messages', array( $this, 'change_updated_messages' ) );

      //Adjust the columns and content shown when viewing the board committees post type list.
      add_filter( 'manage_edit-board_committees_columns', array( $this, 'edit_board_committees_columns' ) );
      add_action( 'manage_board_committees_posts_custom_column', array( $this, 'show_board_committee_columns' ), 10, 2 );

      //Add filter for putting phone number on profile.
      add_filter( 'user_contactmethods', array( $this, 'add_phone_contactmethod' ), 10, 2 );

      //Add user fields for job and job title, along with committee info
      add_action( 'show_user_profile', array( $this, 'display_profile_fields' ) );
      add_action( 'edit_user_profile', array( $this, 'display_profile_fields' ) );

      //Save the added user fields
      add_action( 'personal_options_update', array( $this, 'save_profile_fields' ) );
      add_action( 'edit_user_profile_update', array( $this, 'save_profile_fields' ) );

      //Add our board committees dashboard widget
      add_action('wp_dashboard_setup', array( $this, 'add_board_committees_dashboard_widget' ) );
    }
  }

 /*
   * Create our board committees post type.
   */
  public function create_board_committees_type(){
    $labels = array(
      'name' => __( 'Board Committees', 'nonprofit-board-management' ),
      'singular_name' => __( 'Board Committee', 'nonprofit-board-management' ),
      'add_new' => __( '&#43; Add Board Committee', 'nonprofit-board-management' ),
      'add_new_item' => __( 'Add Board Committee', 'nonprofit-board-management' ),
      'edit_item' => __( 'Edit Board Committee', 'nonprofit-board-management' ),
      'new_item' => __( 'New Board Committee', 'nonprofit-board-management' ),
      'all_items' => __( 'All Board Committees', 'nonprofit-board-management' ),
      'view_item' => __( 'View Board Committee', 'nonprofit-board-management' ),
      'search_items' => __( 'Search Board Committees', 'nonprofit-board-management' ),
      'not_found' =>  __( 'No board committees found', 'nonprofit-board-management' ),
      'not_found_in_trash' => __( 'No board committees found in trash', 'nonprofit-board-management' ), 
      'parent_item_colon' => '',
      'menu_name' => __( 'Board Committees', 'nonprofit-board-management' )
    );

    $args = array(
      'labels' => apply_filters( 'winbm_committee_labels', $labels ),
      'public' => false,
      'show_ui' => true,
      'show_in_menu' => false, //Done through add_submenu_page for more flexibility
      'query_var' => false,
      'capability_type' => 'board_committee',
      'capabilities' => array(
          'publish_posts' => 'publish_board_committees',
          'edit_posts' => 'edit_board_committees',
          'edit_others_posts' => 'edit_others_board_committees',
          'delete_posts' => 'delete_board_committees',
          'delete_others_posts' => 'delete_others_board_committees',
          'read_private_posts' => 'read_private_board_committees',
          'edit_post' => 'edit_board_committee',
          'delete_post' => 'delete_board_committee',
          'read_post' => 'read_board_committee'
      ),
      'supports' => array( 'title' )
    ); 
    
    register_post_type( 'board_committees', $args );
  }
  
  
  /*
   * Handle meta capabilities for our board_committees custom post type
   */
  public function board_committees_map_meta_cap( $caps, $cap, $user_id, $args ){
    //If editing, deleting, or reading a board committee, get the post and post type object.
    if ( 'edit_board_committee' == $cap || 'delete_board_committee' == $cap || 'read_board_committee' == $cap ) {
     $post = get_post( $args[0] );
     $post_type = get_post_type_object( $post->post_type );

     $caps = array();
    }

    //If editing a board_committee, assign the required capability.
    if ( 'edit_board_committee' == $cap ) {
     if ( $user_id == $post->post_author )
      $caps[] = $post_type->cap->edit_posts;
     else
      $caps[] = $post_type->cap->edit_others_posts;
    }

    //If deleting a board_committee, assign the required capability.
    elseif ( 'delete_board_committee' == $cap ) {
     if ( $user_id == $post->post_author )
      $caps[] = $post_type->cap->delete_posts;
     else
      $caps[] = $post_type->cap->delete_others_posts;
    }

    //If reading a private board_committee, assign the required capability.
    elseif ( 'read_board_committee' == $cap ) {
     if ( 'private' != $post->post_status )
      $caps[] = 'read';
     elseif ( $user_id == $post->post_author )
      $caps[] = 'read';
     else
      $caps[] = $post_type->cap->read_private_posts;
    }

    //Return the capabilities required by the user.
    return $caps;
  }  
  

  /*
   * Create the meta boxes when adding/editing a board committee.
   * 
   * Create the meta boxes when adding/editing a board committee.  The boxes include
   * one for editing the description of the committee and one for seeing the members of the committee.
   */
  public function create_board_committee_meta_boxes(){
    //Committee description
    add_meta_box( 'board_committee_desc',
        __( 'Committee Description', 'nonprofit-board-management' ),
        array( $this, 'display_board_committee_desc' ),
        'board_committees', 'normal', 'high'
    );
    
    //List of board members on the committee
    add_meta_box( 'board_committee_members',
        __( 'Committee Members', 'nonprofit-board-management' ),
        array( $this, 'display_board_committee_members' ),
        'board_committees', 'normal', 'default'
    );
  }
  
  /*
   * Display the description meta field for the committee.
   * 
   * @param object $board_committee The $post object for the board committee.
   */
  public function display_board_committee_desc( $board_committee ){
    //Get all the meta data
    $board_committee_meta = $this->get_board_committee_meta( $board_committee->ID );
    
    $nonce = wp_create_nonce( 'committee_desc_nonce' );
    ?>
    <input type="hidden" id="committee_desc_nonce" name="committee_desc_nonce" value="<?php echo $nonce ?>" />
    <table class="committee-description-meta">
      <tr>
        <td><textarea id="committee-description" name="committee-description" rows="6" tabindex="10"><?php echo esc_textarea( $board_committee_meta['description'] ); ?></textarea></td>
      </tr>      
    </table>
    <?php
  }
  
  
  /*
   * Display the members of this committee.
   * 
   * @param object $board_committee The $post object for the board committee.
   */
  public function display_board_committee_members( $board_committee ){
    echo apply_filters( 'winbm_committee_members', $this->get_all_user_inputs( $board_committee->ID ), $board_committee );
  }

  
  /*
   * Save the meta fields for board committees when saving from the edit screen.
   * 
   * @param int $board_committee_id Post ID of the board committee we want.
   * @param object $board_committee The $post object of the board committee.
   */
  public function save_board_committees_meta( $board_committee_id, $board_committee ){
    
    //Check autosave, post type, user caps, nonce
    if( wp_is_post_autosave( $board_committee_id ) || wp_is_post_revision( $board_committee_id ) ) {
      return;
    }
    if( $board_committee->post_type != 'board_committees' ){
      return;
    }
    if( !current_user_can( 'edit_board_committee', $board_committee_id ) ){
      return;
    }
    if ( !isset( $_REQUEST['committee_desc_nonce'] ) || !wp_verify_nonce( $_REQUEST['committee_desc_nonce'], 'committee_desc_nonce' ) ){
      return;
    }
    
    //Committee Description
    if( isset( $_REQUEST['committee-description'] ) ) {
      update_post_meta( $board_committee_id, '_committee_description', sanitize_text_field( $_REQUEST['committee-description'] ) );
    }
    
    //Committee Members
    //Get all the board members
    global $wi_board_mgmt;
    $board_members = $wi_board_mgmt->board_members;
    foreach( $board_members as $board_member ){
      //check if board member is on committee
      $user_committees = get_user_meta( $board_member->ID, 'board_committees', true );
      $on_committee_prev = $this->is_user_on_committee( $user_committees, $board_committee_id );
      
      //If the user should be included in this committee.
      if( isset( $_REQUEST['committee-members'] ) && in_array( $board_member->ID, $_REQUEST['committee-members'] ) ){
        //If the committee is not already listed as one of their committees, then we add it.
        if( $on_committee_prev == false){
          $this->add_committee_to_user( $board_member->ID, $board_committee_id );
        }
      }
      //If they should be removed from the comittee.
      else{
       //If they were previously on the committee, then we remove them.
       if( $on_committee_prev == true ){
         $this->remove_committee_from_user( $board_member->ID, $board_committee_id );
       }
      }
    }//End foreach
  }
  
  
  /*
   * Hide the date filter from the board committees list page.
   * 
   * There is no way to filter this out or we would have taken that approach.
   */
  public function remove_date_filter(){
    if( isset( $_GET['post_type'] ) && $_GET['post_type'] == 'board_committees' ){
      ?>
      <style>
        .tablenav.top .actions + .actions,
        .tablenav .view-switch {
          display: none;
        }
      </style>
      <?php
    }
  }
  
  
  /*
   * Hide the visibility options when creating or editing a board committee.
   */
  public function hide_visibility_options(){
    global $post;
    if( $post->post_type == 'board_committees' ){
        ?>
            <style type="text/css">
                #visibility,
                .misc-pub-section.curtime {
                    display:none;
                }
            </style>
        <?php
    }
  }
  
  
  /*
   * Remove quick edit link from each post in the committees list.
   * 
   * @param array $actions List of action links to list for users.
   * @param object $post The post being listed in the table.
   * @return $actions Our actions with the quick edit link removed.
   */
  public function remove_quick_edit( $actions, $post ){
    if( $post->post_type == 'board_committees' ){
      unset( $actions['inline hide-if-no-js']);
    }
    
    return $actions;
  }
  
  
  /*
   * Change post updated messages on edit screen.
   * 
   * @param array $messages Existing updated messages for posts and pages.
   * @return array New updated messages content with board committee messages added.
   */
  public function change_updated_messages( $messages ){    
    $messages['board_committees'] = array(
      0 => '', // Unused. Messages start at index 1.
      1 => sprintf( __( 'Committee updated. <a href="%s">View all of your committees</a>.', 'nonprofit-board-management' ), admin_url( 'edit.php?post_type=board_committees' ) ),
      2 => __( 'Custom field updated.', 'nonprofit-board-management' ),
      3 => __( 'Custom field deleted.', 'nonprofit-board-management' ),
      4 => sprintf( __( 'Committee updated. <a href="%s">View all of your committees</a>.', 'nonprofit-board-management' ), admin_url( 'edit.php?post_type=board_committees' ) ),
     /* translators: %s: date and time of the revision */
      5 => isset( $_GET['revision'] ) ? sprintf( __( 'Committee restored to revision from %s', 'nonprofit-board-management' ), wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
      6 => sprintf( __( 'Committee published. <a href="%s">View all of your committees</a>.', 'nonprofit-board-management' ), admin_url( 'edit.php?post_type=board_committees' ) ),
      7 => __( 'Committee saved.', 'nonprofit-board-management' ),
      8 => __( 'Committee submitted.', 'nonprofit-board-management' ),
      9 => __( 'You should not be scheduling committees.  It just won\'t work.', 'nonprofit-board-management' ),
     10 => __( 'Committee draft updated.', 'nonprofit-board-management' )
    );
    
    return apply_filters( 'winbm_committee_messages', $messages );
  }
  
  
  /*
  * Add custom columns for the board committees content type list.
  * 
  * @param array $columns The default columns for board committees.
  * @return array Custom columns we want to use on the board committees list.
  */
  public function edit_board_committees_columns( $columns ) {
    $columns = array(
      'cb' => '<input type="checkbox" />',
      'title' => __( 'Title', 'nonprofit-board-management' ),
      'description' => __( 'Description', 'nonprofit-board-management' ),
      'committee_members' => __( 'Committee Members', 'nonprofit-board-management' ),
    );

    return apply_filters( 'winbm_committee_columns', $columns );
  }
  
 
  /*
   * Display content for each custom column for board committees.
   * 
   * @param string $column Column to be displayed.
   * @param int $board_committee_id Post ID of the board committee to be displayed.
   */
  public function show_board_committee_columns( $column, $board_committee_id ){  
    $board_committee_meta = $this->get_board_committee_meta( $board_committee_id );

    switch( $column ){

      case 'description':
        
        echo '<span class="waiting spinner" style="display: none;"></span>'; 
        echo wp_trim_words( $board_committee_meta['description'], 15, '&hellip;<br /><a href="#" data-id="' . $board_committee_id . '" class="more-desc">Read full description</a>' );

        break;

      case 'committee_members':

        echo $this->get_committee_member_list( $board_committee_id );

        break;
    }
  } 
 
  
  /*
   * Add and remove certain fields for board members and serving admins.
   * 
   * @param array $user_contactmethods List of current WP contact methods.
   * @param object $user Current user having their profile edited.
   * @return array A new list of contact methods with our methods added.
   */
  public function add_phone_contactmethod( $user_contactmethods, $user ){
    
    //If the user serves on the board we need to add and remove certain fields.
    if( isset( $user->ID ) && user_can( $user->ID, 'serve_on_board' ) ){
      $user_contactmethods['phone'] = __( 'Phone Number', 'nonprofit-board-management' );
      
      //If user serves and isn't an admin then remove all the fields we don't want.
      if( !user_can( $user->ID, 'manage_options' ) ){
        unset( $user_contactmethods['aim'] );
        unset( $user_contactmethods['jabber'] );
        unset( $user_contactmethods['yim'] );
      }
    }

    return $user_contactmethods;
  }


  /*
   * Display fields for employer and job title, along with committee info.
   * 
   * @param object $board_member User object for board member.
   */
  public function display_profile_fields( $board_member ){

    //If the person can't edit ths user don't show these fields.
    if( !current_user_can( 'edit_user', $board_member->ID ) ){
      return;
    }
    //If the user can't join a board committee then don't show these fields.
    if( !user_can( $board_member->ID, 'serve_on_board' ) ){
      return;
    }

    $current_employer = get_user_meta($board_member->ID, 'current_employer', true);
    $job_title = get_user_meta($board_member->ID, 'job_title', true);

    ?>
    <h3><?php _e( 'Additional Info for the Board', 'nonprofit-board-management' ); ?></h3>

    <table class="form-table">
      
      <?php do_action( 'winbm_before_profile_fields', $board_member ); ?>
      
      <tr>
        <th><label for="current-employer"><?php _e( 'Current Employer', 'nonprofit-board-management' ); ?></label></th>
        <td><input type="text" id="current-employer" name="current-employer" class="regular-text" value="<?php echo sanitize_text_field( $current_employer ); ?>" /></td>
      </tr>

      <tr>
        <th><label for="job-title"><?php _e( 'Job Title', 'nonprofit-board-management' ); ?></label></th>
        <td><input type="text" id="job-title" name="job-title" class="regular-text" value="<?php echo sanitize_text_field( $job_title ); ?>" /></td>
      </tr>

      <tr>
        <th><label><?php _e( 'Your Committees', 'nonprofit-board-management' ); ?></label></th>
        <td>
          <?php echo $this->get_all_committee_inputs( $board_member->ID ); ?>
        </td>
      </tr>
      
      <?php
      //Only show checkbox to no longer serve on the board if user is admin
      if( user_can( $board_member, 'manage_options' ) ){ ?>
        <tr>
          <th><?php _e( 'Serving on Board', 'nonprofit-board-management' ); ?></th>
          <td>
            <label><input type="checkbox" name="serve-on-board" checked="checked" /> <?php _e( 'Uncheck this box and click the "Update Profile" button to no longer serve on the board.', 'nonprofit-board-management' ); ?></label>
            <input type="hidden" name="serve-on-board-available" value="1" />
          </td>
        </tr>
      
      <?php }
      
      do_action( 'winbm_after_profile_fields', $board_member ); ?>
      
    </table>

  <?php
  }

  /*
   * Save our new profile fields for employer, job title, and committees.
   * 
   * @param int $board_member_id User ID of the board member.
   */
  public function save_profile_fields( $board_member_id ){

    //If the person can't edit then don't save these fields.
    if( !current_user_can( 'edit_user', $board_member_id ) ){
      return;
    }
    //If the user can't serve then don't save these fields.
    if( !user_can( $board_member_id, 'serve_on_board' ) ){
      return;
    }

    //Current employer
    if ( isset( $_REQUEST['current-employer'] ) ) {
      update_user_meta( $board_member_id, 'current_employer', sanitize_text_field( $_REQUEST['current-employer'] ) );
    }
    //Job title
    if ( isset( $_REQUEST['job-title'] ) ) {
      update_user_meta( $board_member_id, 'job_title', sanitize_text_field( $_REQUEST['job-title'] ) );
    }
    //Board committees
    if( isset( $_REQUEST['board-committees'] ) ){
      update_user_meta( $board_member_id, 'board_committees', array_map( 'absint', $_REQUEST['board-committees'] ) );
    }
    else{
      delete_user_meta( $board_member_id, 'board_committees' );
    }
   //Remove the admin from the board if they unchecked the serve-on-board checkbox
   if( isset( $_REQUEST['serve-on-board-available'] ) && !isset( $_REQUEST['serve-on-board'] ) ){ 
     $board_member = new WP_User( $board_member_id );
     $board_member->remove_cap( 'serve_on_board' );  
   }
   
   do_action( 'winbm_save_profile_fields', $board_member_id );
  }
  
  
  /*
  * Add our committees dashboard widget to the list of widgets.
  */
 public function add_board_committees_dashboard_widget(){
   if( current_user_can( 'view_board_content' ) ){
    wp_add_dashboard_widget('board_committees_db_widget', __( 'Board Committees', 'nonprofit-board-management' ), array( $this, 'display_board_committees_dashboard_widget' ) );
   }
   
   //Make our widget show on the right side of the dashboard by adding to 'side' array.
   global $wp_meta_boxes;
   $widget = $wp_meta_boxes['dashboard']['normal']['core']['board_committees_db_widget'];
   unset($wp_meta_boxes['dashboard']['normal']['core']['board_committees_db_widget']);
   $wp_meta_boxes['dashboard']['side']['core']['board_committees_db_widget'] = $widget;
 }


 /*
  * Display a dashboard widget for all of the board committees.
  * 
  * @see add_board_committees_dashboard_widget()
  */
 public function display_board_committees_dashboard_widget(){
   $board_committees = get_posts( array( 'post_type' => 'board_committees', 'nopaging' => true ) );
   
   //If no committees show the user a message.
   if( empty( $board_committees ) ){
     printf( __( 'There are no board committees. <a href="%s">Go ahead and add some.</a>', 'nonprofit-board-management' ),  admin_url( 'edit.php?post_type=board_committees' ) );
     
     return;
   }
   
   foreach( $board_committees as $board_committee ){
     echo '<h4>' . esc_html( $board_committee->post_title ) . '</h4>';
     echo '<p>' . $this->get_committee_member_list( $board_committee->ID ) . '</p>';
   }
   
   echo '<p class="note"><a href="' . admin_url( 'edit.php?post_type=board_committees' ) . '">' . __( 'View and edit the committees', 'nonprofit-board-management' ) . '</a></p>';
 }
    
  
  /*
   * Get meta data for a board committee.
   * 
   * @param int @board_committee_id Post ID of the board committee we want.
   * @return array The meta data for committees we need.
   */
  private function get_board_committee_meta( $board_committee_id ){
    $board_committee_meta_raw = get_post_custom( $board_committee_id );
    $board_committee_meta['description'] = ( isset( $board_committee_meta_raw['_committee_description'] ) ) ? $board_committee_meta_raw['_committee_description'][0] : '';
    
    return apply_filters( 'winbm_board_committee_meta', $board_committee_meta, $board_committee_id );
  }
  
  
  /*
   * Add the board committee to the list of committees a board member serves on.
   * 
   * @param int $board_member_id User ID of the board member.
   * @param int $board_committee_id Post ID of the committee we want to add to the user.
   */
  private function add_committee_to_user( $board_member_id, $board_committee_id ){
    //Get their previous committees and make array if they weren't on any committees.
    $prev_committees = get_user_meta( $board_member_id, 'board_committees', true );
    if( $prev_committees == '' ){
      $prev_committees = array();
    }
    
    $new_committees = array();
    $new_committees[] = $board_committee_id;
    //Add the new committee array to the old one.
    $new_committees = array_merge( $prev_committees, $new_committees );
    
    update_user_meta( $board_member_id, 'board_committees', $new_committees );
    
    do_action( 'winbm_add_committee_to_user', $board_member_id, $board_committee_id );
  }
  
  
  /*
   * Remove the board committee from the list of committees the board members serves on.
   * 
   * @param int $board_member_id User ID of the board member.
   * @param int $board_committee_id Post ID of the committee we want to add to the user. 
   */
  private function remove_committee_from_user( $board_member_id, $board_committee_id ){
    $prev_committees = get_user_meta( $board_member_id, 'board_committees', true );
    $new_committees = $prev_committees;
    
    //Find the key of this board committee so we can unset it in the array.
    $committee_key = array_search( $board_committee_id, $prev_committees );
    unset( $new_committees[$committee_key] );
    
    //If they are now on no committees, delete meta, otherwise, update with new array.
    if( empty( $new_committees ) ){
      delete_user_meta( $board_member_id, 'board_committees' );
    }
    else {
      update_user_meta( $board_member_id, 'board_committees', $new_committees );
    }
    
    do_action( 'winbm_remove_committee_from_user', $board_member_id, $board_committee_id );
  }
  
  
  /*
   * Get the number of committee members and who is on the committee separated by commas.
   * 
   * @param int $board_committee_id Post ID of board committee.
   * @return string The number and members on committee separated by commas.
   */
  private function get_committee_member_list( $board_committee_id ){
    global $wi_board_mgmt;
    $board_members = $wi_board_mgmt->board_members;
    $num_on_committee = 0;
    $members_on_committee = array();
    
    foreach( $board_members as $board_member ){
      $user_committees = get_user_meta( $board_member->ID, 'board_committees', true );
      if( $this->is_user_on_committee( $user_committees, $board_committee_id ) ){
        $members_on_committee[] = $board_member->display_name;
        $num_on_committee++;
      }
    }
    
    $committee_member_list = '(' . $num_on_committee . ')';
    if( $num_on_committee != 0 ) $committee_member_list .= ' - ';
    $committee_member_list .= implode( ', ', apply_filters( 'winbm_members_on_committee', $members_on_committee, $board_committee_id ) );
    
    return esc_html( $committee_member_list );
  }
 
  
  /*
   * Get a list of the committees the user is on separated by commas.
   * 
   * @param int $board_member_id User ID of board member.
   * @return string comma separated list of committees the board member is on.
   */
  public static function get_user_committees( $board_member_id ){
    $user_committees_ids = get_user_meta( $board_member_id, 'board_committees', true );
    
    //If on no committees get_user_meta returns an empty string.
    if( $user_committees_ids == '' ){
      return '';
    }
    
    //Create an array with the titles of all the committees.
    $committees = array();
    foreach( $user_committees_ids as $user_committee_id ){
      if( get_post_status( $user_committee_id ) == 'publish' ){ //Only add committee if it is published.
        $committees[] = get_the_title( $user_committee_id );
      }
    }
    
    return esc_html( implode( ', ', apply_filters( 'winbm_user_committees', $committees, $board_member_id ) ) );
  }

  
  /*
   * Get checkbox inputs for a board committee edit screen.
   * 
   * Get checkbox inputs for a board committee edit screen.  This includes a 
   * list of board members with those on the board checkmarked.
   * 
   * @param int $board_committee_id Post ID of the board committee.
   * @return string HTML with all user checkbox inputs.
   */
  private function get_all_user_inputs( $board_committee_id ){
    //Get all the board board members
    global $wi_board_mgmt;
    $board_members = $wi_board_mgmt->board_members;
    
    //Provide message if no board members exist
    if( empty( $board_members ) ){
      return '<p>' . __( "There aren't currently any members on your board so no one can join a committee.", 'nonprofit-board-management' ) . '</p>';
    }
    
    //Loop through users and add them
    $committee_inputs = '';
    $tabindex = 20;
    foreach( $board_members as $board_member ){
      $user_committees = get_user_meta( $board_member->ID, 'board_committees', true );
      
      $committee_inputs .= '<label><input type="checkbox" tabindex="' . $tabindex . '" ';
      $committee_inputs .= checked( $this->is_user_on_committee( $user_committees, $board_committee_id ), true, false );
      $committee_inputs .= ' name="committee-members[]" value="';
      $committee_inputs .= intval( $board_member->ID );
      $committee_inputs .= '" /> ';
      $committee_inputs .= esc_html( $board_member->display_name );
      $committee_inputs .= '</label><br />';
      
      $tabindex += 10;
    }
    
    return apply_filters( 'winbm_member_committee_checkboxes', $committee_inputs, $board_committee_id );
  }
  
  
  /*
   * Return inputs for all the committees for the users edit screen.
   * 
   * @param int $board_member_id User ID of the board member.
   * @return string Inputs for all the committees with those they're on as checked.
   */
  private function get_all_committee_inputs( $board_member_id ){
    //Get all the committees
    $board_committees = get_posts( array( 'post_type' => 'board_committees', 'nopaging' => true ) );
    $user_committees = get_user_meta( $board_member_id, 'board_committees', true );

    //If there aren't any committees then we tell the user
    if( empty( $board_committees ) ){
      $committee_inputs = __( 'No committees have been created yet.', 'nonprofit-board-management' );
      
      return $committee_inputs;
    }
    
    //Loop through the committees and make checkboxes
    $committee_inputs = '';
    foreach( $board_committees as $board_committee ){
      if( $this->is_user_on_committee( $user_committees, $board_committee->ID ) == true ){
        $checked = 'checked="checked"';
      }
      else {
        $checked = '';
      }
      
      $committee_inputs .= '<label><input type="checkbox" ';
      $committee_inputs .= $checked;
      $committee_inputs .= ' name="board-committees[]" value="';
      $committee_inputs .= intval( $board_committee->ID );
      $committee_inputs .= '" /> ';
      $committee_inputs .= esc_html( $board_committee->post_title );
      $committee_inputs .= '</label><br />';
    }
    
    return apply_filters( 'winbm_profile_committee_checkboxes', $committee_inputs, $board_member_id );
  }
  
  
  /*
   * Checks if user is on the given board committee
   * 
   * @param array|string $user_committees A list of all the user's committees from get_user_meta or an empty string if none.
   * @param int $board_committee_id Post ID of the board committee to check against.
   * @return bool True if user on committee, false if not.
   */
  public function is_user_on_committee( $user_committees, $board_committee_id ){
    if( !empty( $user_committees) && $user_committees != '' ){
      foreach( $user_committees as $user_committee ){
        if( $user_committee == $board_committee_id ) return true;
      }
    }
    
    return false; //Return false if it doesn't match
  }
  
}//WI_Board_Committees