<?php
/**
 * WI_Board_Events allows the board to add and RSVP to upcoming board events.
 *
 * @author Wired Impact
 */
class WI_Board_Events {
  
  //The version of our database.
  const db_version = 1.0;
  
  //The full name of our database table.
  private $table_name = '';
  
  public function __construct() {
    //Set our table name for our db from the start.
    $this->table_name = $this->get_table_prefix() . 'board_rsvps';
    
    //Add database table on activation for RSVP features
    //We must use a constant instead of __FILE__ because this file is loaded using require_once.
    register_activation_hook( BOARD_MANAGEMENT_FILEFULLPATH, array( $this, 'create_db_table' ) );
    
    //Load CSS and JS
    add_action( 'admin_menu', array( $this, 'insert_css') );
    add_action( 'admin_menu', array( $this, 'insert_js') );

    //Create our board events custom post type
    add_action( 'init', array( $this, 'create_board_events_type' ) );
    add_action( 'admin_init', array( $this, 'create_board_events_meta_boxes' ) );
    add_action( 'save_post', array( $this, 'save_board_events_meta' ), 10, 2 );
    
    //Adjust the columns and content shown when viewing the board events post type list.
    add_filter( 'manage_edit-board_events_columns', array( $this, 'edit_board_events_columns' ) );
    add_action( 'manage_board_events_posts_custom_column', array( $this, 'show_board_event_columns' ), 10, 2 );
    add_filter( 'manage_edit-board_events_sortable_columns', array( $this, 'make_board_events_sortable' ) );
    add_action( 'load-edit.php', array( $this, 'edit_board_events_load' ) );
    
    //Add notice to admin who can't RSVP
    add_action( 'admin_notices', array( $this, 'show_admins_notices' ) );
    
    //Save RSVPs for the events via ajax
    add_action( 'wp_ajax_rsvp', array( $this, 'rsvp' ) );
    
    //Allow user to RSVP for events
    add_action( 'wp_ajax_allow_rsvp', array( $this, 'allow_rsvp' ) );
  }
  
  /*
   * Create the database table that will hold our board event RSVP information.
   */
  public function create_db_table(){
    
    //Only create table if it doesn't exist.
    if( get_option( 'board_rsvp_db_version' ) == FALSE ){
      global $wpdb;

      $table_name = $wpdb->prefix . "board_rsvps";

      $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) NOT NULL,
        post_id bigint(20) NOT NULL,
        rsvp tinyint(2) NOT NULL,
        time timestamp NOT NULL,
        PRIMARY  KEY  (id),
        UNIQUE KEY (user_id, post_id)
      );";

      require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
      dbDelta($sql);

      //We set a variable in options in case we need to update the database in the future.
      add_option('board_rsvp_db_version', 0.1);
    }
  }
  
  
  /*
   * Enqueue CSS
   */
  public function insert_css(){
    wp_enqueue_style( 'board-events', BOARD_MANAGEMENT_PLUGINFULLURL . 'css/board-events.css' );
    wp_enqueue_style( 'jquery-ui-smoothness', 'http://code.jquery.com/ui/1.9.2/themes/base/jquery-ui.css' );
  }

  
  /*
   * Enqueue JS
   */
  public function insert_js(){
    wp_enqueue_script( 'jquery-ui-slider' );
    wp_enqueue_script( 'jquery-ui-datepicker' );
    wp_enqueue_script( 'jquery-timepicker', BOARD_MANAGEMENT_PLUGINFULLURL . 'js/jquery-ui-timepicker.js', array( 'jquery-ui-slider', 'jquery-ui-datepicker' ) );

    wp_enqueue_script( 'board-events', BOARD_MANAGEMENT_PLUGINFULLURL . 'js/board-events.js', 'jquery' );
    
    
    $current_user = wp_get_current_user();
    //wp_localize_script allows us to send PHP info to JS
    wp_localize_script( 'board-events', 'wi_board_events', array(
      // generate a nonces that can be checked later on save
      'save_rsvp_nonce' => wp_create_nonce( 'save_rsvp_nonce' ),  
      'allow_rsvp_nonce' => wp_create_nonce( 'allow_rsvp_nonce' ),
      'error_rsvp' => __( 'Woops.  We failed to RSVP for you.  Please try again.' ),
      'error_allow_rsvp' => __( 'Woops. We weren\'t able to allow you to RSVP.  Please try again.' ),
      'current_user_display_name' => __( $current_user->display_name ) //Must match text used to display who's coming
      )
     );
  }
  
  
  /*
   * Create our board events post type.
   */
  public function create_board_events_type(){
    $labels = array(
      'name' => 'Board Events',
      'singular_name' => 'Board Event',
      'add_new' => 'Add New Board Event',
      'add_new_item' => 'Add New Board Event',
      'edit_item' => 'Edit Board Event',
      'new_item' => 'New Board Event',
      'all_items' => 'All Board Events',
      'view_item' => 'View Board Event',
      'search_items' => 'Search Board Events',
      'not_found' =>  'No board events found',
      'not_found_in_trash' => 'No board events found in trash', 
      'parent_item_colon' => '',
      'menu_name' => 'Board Events'
    );

    $args = array(
      'labels' => $labels,
      'public' => false,
      'show_ui' => true,
      'show_in_menu' => 'nonprofit-board', 
      'query_var' => false,
      'capability_type' => 'board_event',
      'supports' => array( 'title', 'editor' )
    ); 
    
    register_post_type( 'board_events', $args );
  }
  
  /*
   * Create the meta box when adding/editing a board event
   */
  public function create_board_events_meta_boxes(){
    //Details of board event
    add_meta_box( 'board_event_details',
        'Board Event Details',
        array( $this, 'display_board_event_details' ),
        'board_events', 'normal', 'high'
    );
    
    //Current signup info for board event
    add_meta_box( 'board_event_rsvps',
        'RSVP List',
        array( $this, 'display_board_event_rsvps' ),
        'board_events', 'side', 'default'
    );
  }
  
  /*
   * Display the meta fields and values for boad events when in the admin
   */
  public function display_board_event_details( $board_event ){
    //Get all the meta data
    $board_event_meta = $this->retrieve_board_event_meta( $board_event->ID );
    $nonce = wp_create_nonce( 'event_details_nonce' );
    ?>
    <input type="hidden" id="_event_details_nonce" name="_event_details_nonce" value="<?php echo $nonce ?>" />
    <table>
      <tr>
        <td><label for="location">Location Name</label></td>
        <td><input type="text" id="location" name="location" class="regular-text" value="<?php echo $board_event_meta['location']; ?>" /></td>
      </tr>
      
      <tr>
        <td><label for="street">Street Address</label></td>
        <td><input type="text" id="street" name="street" class="regular-text" value="<?php echo $board_event_meta['street']; ?>" /></td>
      </tr>
      
      <tr>
        <td><label for="area">City, State Zip</label></td>
        <td><input type="text" id="area" name="area" class="regular-text" value="<?php echo $board_event_meta['area']; ?>" /></td>
      </tr>
      
      <tr>
        <td><label for="start-date-time">Start Date & Time</label></td>
        <td><input type="text" id="start-date-time" name="start-date-time" class="regular-text" value="<?php echo $board_event_meta['start_date_time'] ?>" /></td>
      </tr>
      
      <tr>
        <td><label for="end-date-time">End Date & Time</label></td>
        <td><input type="text" id="end-date-time" name="end-date-time" class="regular-text" value="<?php echo $board_event_meta['end_date_time'] ?>" /></td>
      </tr>
      
    </table>
    <?php
  }
  
  /*
   * Save the meta data fields for board events
   */
  public function save_board_events_meta( $board_event_id, $board_event ){
    
    //Check autosave, post type, user caps, nonce
    if( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
      return;
    }
    if( $board_event->post_type != 'board_events' ){
      return;
    }
    if( !current_user_can( 'edit_post', $board_event_id ) ){
      return;
    }
    if ( !isset( $_REQUEST['_event_details_nonce'] ) || !wp_verify_nonce( $_REQUEST['_event_details_nonce'], 'event_details_nonce' ) ){
      return;
    }
    
    //Save all of our fields
    //Location
    if (isset($_REQUEST['location'])) {
      update_post_meta( $board_event_id, '_location', sanitize_text_field( $_REQUEST['location'] ) );
    }
    //Street
    if (isset($_REQUEST['street'])) {
      update_post_meta( $board_event_id, '_street', sanitize_text_field( $_REQUEST['street'] ) );
    }
    //Area
    if (isset($_REQUEST['area'])) {
      update_post_meta( $board_event_id, '_area', sanitize_text_field( $_REQUEST['area'] ) );
    }
    //Start Date & Time stored as UNIX timestamp
    if (isset($_REQUEST['start-date-time'])) {
      $start_date_time = strtotime( $_REQUEST['start-date-time'] );
      update_post_meta( $board_event_id, '_start_date_time', $start_date_time );
    }
    //End Date & Time stored as UNIX timestamp
    if (isset($_REQUEST['end-date-time'])) {
      $end_date_time = strtotime( $_REQUEST['end-date-time'] );
      update_post_meta( $board_event_id, '_end_date_time', $end_date_time );
    }
  }
  
  
  /*
   * Display who has RSVPed for each event including who's coming, 
   * who's not, and who hasn't responded yet.
   */
  public function display_board_event_rsvps( $board_event ){
        
    $rsvps = $this->board_event_rsvps( $board_event->ID );
    
    //Attending Array
    $attending = array();
    foreach( $rsvps['attending'] as $event_rsvp ){
      $attending[] = $event_rsvp->display_name;
    }
    
    //Not Attending Array
    $not_attending = array();
    foreach( $rsvps['not_attending'] as $event_rsvp ){
      $not_attending[] = $event_rsvp->display_name;
    }
    
    //No Response Array
    $no_response = array();
    foreach( $rsvps['no_response'] as $event_rsvp ){
      $no_response[] = $event_rsvp->display_name;
    }
    
    //Display all the board members
    echo '<h4>Attending</h4>';
    echo implode( ', ', $attending );
    echo '<h4>Not Attending</h4>';
    echo implode( ', ', $not_attending );
    echo '<h4>No Response</h4>';
    echo implode( ', ', $no_response );
  }
  
  /*
   * Retrieve all the board event meta data and place it in an array.
   */
  private function retrieve_board_event_meta( $post_id ){
    $board_event_meta_raw = get_post_custom( $post_id );
    $board_event_meta = array();
    
    $board_event_meta['location'] = ( isset( $board_event_meta_raw['_location'] ) ) ? $board_event_meta_raw['_location'][0] : '';
    $board_event_meta['street'] = ( isset( $board_event_meta_raw['_street'] ) ) ? $board_event_meta_raw['_street'][0] : '';
    $board_event_meta['area'] = ( isset( $board_event_meta_raw['_area'] ) ) ? $board_event_meta_raw['_area'][0] : '';
    $board_event_meta['start_date_time'] = ( isset( $board_event_meta_raw['_start_date_time'] ) && $board_event_meta_raw['_start_date_time'][0] != '' ) ? date( 'D, F d, Y h:i a', (int)$board_event_meta_raw['_start_date_time'][0] ) : '';
    $board_event_meta['end_date_time'] = ( isset( $board_event_meta_raw['_end_date_time']  ) && $board_event_meta_raw['_end_date_time'][0] != '' ) ? date( 'D, F d, Y h:i a', (int)$board_event_meta_raw['_end_date_time'][0] ) : '';
    
    return $board_event_meta;
  }
  
  
 /*
  * Add custom columns the board events content type.
  */
 public function edit_board_events_columns( $columns ) {

   $columns = array(
     'cb' => '<input type="checkbox" />',
     'title' => __( 'Title' ),
     'location' => __( 'Location' ),
     'date_time' => __( 'Date & Time' ),
     'description' => __( 'Description' ),
     'attending' => __( 'Who\'s Coming?' ),
   );
   
   if( current_user_can( 'rsvp_board_events' ) ){
     $columns['rsvp'] = __( 'RSVP' );
   }

   return $columns;
 }
 
 
 /*
  * Show content for custom columns for board events.
  */
 public function show_board_event_columns( $column, $post_id ){  
   $board_event_meta = $this->retrieve_board_event_meta( $post_id );
   
   switch( $column ){
     
     case 'location':
       
       //Create a Google maps URL so we can add a link to get a map.
       if( $board_event_meta['street'] != '' || $board_event_meta['area'] != '' ){
         
        $google_maps_string = str_replace( ' ', '+', $board_event_meta['street'] . ' ' . $board_event_meta['area'] );
        $google_maps_address = 'https://maps.google.com/maps?q=' . $google_maps_string;

        echo '<a href="' . $google_maps_address . '" target="_blank">';
        if( $board_event_meta['location'] != '' ) echo $board_event_meta['location'] . '<br />';
        if( $board_event_meta['street'] != '' ) echo $board_event_meta['street'] . '<br />';;
        if( $board_event_meta['area'] != '' ) echo $board_event_meta['area'];
        echo '</a>';
        
       }
       
       break;
     
     case 'date_time':
       
       echo $board_event_meta['start_date_time'];
       if( $board_event_meta['start_date_time'] != '' ) echo ' - ';
       echo $board_event_meta['end_date_time'];
       
       break;
    
     case 'description':
       
       echo wp_trim_words( get_the_content(), 15 );
       
       break;
     
     case 'attending':
       
       echo $this->get_attending_rsvps( $post_id );
       
       break;
     
     case 'rsvp':
       
       if( !current_user_can( 'rsvp_board_events' ) ){
         echo 'You are not a board member so you can\'t RSVP for events.';
         
         break;
       }
       
       //Determine whether they're going and if add classes if they have RSVPed previously.
       $user_id = get_current_user_id();
       $rsvp_status = $this->rsvp_status( $post_id, $user_id );
       
       //class button-primary should be used for selected option
       //class secondary-button should be used for those that aren't selected
       echo '<input id="attending" type="submit" class="button secondary-button ';
       if( $rsvp_status === 1 ){
         echo 'button-primary active';
       }
       echo '" value="I\'m Going" />';
       echo '<input id="not-attending" type="submit" class="button secondary-button ';
       if( $rsvp_status === 0 ){
         echo 'button-primary active';
       }
       echo '" value="I\'m Not Going" />';
       echo '<span class="waiting spinner" style="display: none;"></span>';
       
       break;
   }
 }
 
 /*
  * Add Date & Time as a sortable field.
  */
 public function make_board_events_sortable( $columns ){
   $columns['date_time'] = 'date_time';
   
   return $columns;
 }
 
 
 /*
  * Run our sort function for the request filter.
  */
 public function edit_board_events_load() {
	add_filter( 'request', array( $this, 'sort_board_events' ) );
 }

 
 /*
  * On list of board events make date and time sortable by start_date_time.
  */
 public function sort_board_events( $vars ) {

  if ( isset( $vars['post_type'] ) && 'board_events' == $vars['post_type'] ) {

    if ( isset( $vars['orderby'] ) && 'date_time' == $vars['orderby'] ) {
     
      $vars = array_merge(
        $vars,
        array(
          'meta_key' => '_start_date_time',
          'orderby' => 'meta_value_num'
        )
      );
    }
  }
  
  return $vars;
}

/*
 * Show notice to admins allowing them to start RSVPing.
 */
public function show_admins_notices(){
  $screen = get_current_screen();
  if( $screen->id != 'edit-board_events' || current_user_can( 'rsvp_board_events' ) ) return;
  ?>
  <div class="updated">
    <p>You don't have the board member role, so you can't RSVP to board events.
      <input id="allow-rsvp" type="submit" class="button secondary-button" value="Allow Me to RSVP" />
    </p>
  </div>
  <?php
}

  /*
   * Allow current user to RSVP by giving them the capability.
   * This method is called via ajax.
   */
  public function allow_rsvp(){
    check_ajax_referer( 'allow_rsvp_nonce', 'security' );

    $current_user = wp_get_current_user();
    $current_user->add_cap( 'rsvp_board_events' );

    echo '1';

    die();
  }

  /*
   * Return the table prefix for this WordPress install.
   */
  private function get_table_prefix(){
    global $wpdb;

    return $wpdb->prefix;
  }
  
  
 /*
  * Save the RSVP for this board member.
  * This method is called via ajax.
  */
 public function rsvp(){
  //Use nonce passed through wp_localize_script for added security.
  check_ajax_referer( 'save_rsvp_nonce', 'security' );
   
  //Put data in variables
  $post_id = intval( $_POST['post_id'] );
  $rsvp = intval( $_POST['rsvp'] );
  $user_id = get_current_user_id();
  
  //Access $wpdb.  We're going to need it.
  global $wpdb;
  
  $rsvp_status = $this->rsvp_status( $post_id, $user_id );
  
  //Insert data into database
  $result = 0;
  if( $rsvp_status === FALSE ){
    $wpdb->insert(
            $this->table_name,
            array( 'user_id' => $user_id, 'post_id' => $post_id, 'rsvp' => $rsvp ),
            array( '%d', '%d', '%d' ) //All of these should be saved as integers
           );
    
    $result = $this->get_attending_rsvps( $post_id );
  }
  else if( $rsvp_status != $rsvp ) { //Only do the db update if there RSVP status in the db will change
    $wpdb->update(
            $this->table_name,
            array( 'rsvp' => $rsvp ), //Data to be updated
            array( 'user_id' => $user_id, 'post_id' => $post_id ), //Where clause
            array( '%d' ), //Format for data being updated
            array( '%d', '%d' )
           );
    
    $result = $this->get_attending_rsvps( $post_id );
  }
  
  //0 means that nothing changed, a returned string is the list of RSVPs
  echo $result;
  
  die();
 }
 
 
 /*
  * Provides the status or the RSVP for this user for this event.
  * Possible returns include FALSE, 0, 1
  */
 private function rsvp_status( $post_id, $user_id ){
  global $wpdb;

  //Check if this user has already RSVPed for this event.
  //NULL means they haven't yet.
  $rsvp = $wpdb->get_var( $wpdb->prepare(
            "
             SELECT rsvp
             FROM " . $wpdb->prefix  . "board_rsvps
             WHERE post_id = %d
             AND user_id = %d
            ",
            $post_id,
            $user_id
          ) );
  
  $rsvp_status = ( $rsvp == NULL ) ? FALSE : (int)$rsvp;
  
  return $rsvp_status;
 }
 
 /*
  * Provide an array for users that are attending, not attending and haven't 
  * responded to an event.
  */
 private function board_event_rsvps( $post_id ){
   $rsvp_users = $this->get_users_who_rsvp();
   
   //Get all rsvps for for given event id
   global $wpdb;
   $event_rsvps = $wpdb->get_results(
           "
             SELECT user_id, rsvp
             FROM $this->table_name
             WHERE post_id = {$post_id}
           "
           );          
  
  //Loop through and add RSVP info to each board member.
  //Loop through all the board members first.
  $rsvp_users_count = count( $rsvp_users );
  for( $i = 0; $i < $rsvp_users_count; $i++){
    //With each board member loop through all RSVPs
    //If one matches then add it as a property of that user object
    foreach( $event_rsvps as $event_rsvp ){
      if( $event_rsvp->user_id == $rsvp_users[$i]->ID ){
        $rsvp_users[$i]->rsvp = $event_rsvp->rsvp;
      }
    }
  }
  
  //Build an array with all those attending, not attending and haven't responded.
  $rsvps = array( 'attending' => array(), 'not_attending' => array(), 'no_response' => array() );
  for( $i = 0; $i < $rsvp_users_count; $i++ ){
    if( !isset( $rsvp_users[$i]->rsvp ) ){
      $rsvps['no_response'][] = $rsvp_users[$i];
    }
    else if ( $rsvp_users[$i]->rsvp == 1 ){
      $rsvps['attending'][] = $rsvp_users[$i];
    }
    else {
      $rsvps['not_attending'][] = $rsvp_users[$i];
    }
  }
   
   return $rsvps;
 }
 
 /*
  * Get a comma separated list of those attending the event.
  */
 private function get_attending_rsvps( $post_id ){
    $rsvps = $this->board_event_rsvps( $post_id );
       
    $attending = array();
    foreach( $rsvps['attending'] as $event_rsvp ){
      $attending[] = $event_rsvp->display_name;
    }

    return implode( ', ', $attending );
 }
 
 
 /*
  * Get an array with all the users who can potentially RSVP to an event.
  */
 private function get_users_who_rsvp(){
   $board_members = get_users( array( 'role' => 'board_member' ) );
   $admins = get_users( array( 'role' => 'administrator' ) );
   
   //Check if admins can rsvp and if not, remove them from the array.
   $admins_count = count( $admins );
   for( $i = 0; $i < $admins_count; $i++ ){
     if( !isset( $admins[$i]->allcaps['rsvp_board_events'] ) || $admins[$i]->allcaps['rsvp_board_events'] != true ) {
       unset( $admins[$i] );
     }
   }
   
   //Combine board members with admins that can rsvp
   $rsvp_users = array_merge( $board_members, $admins );
   
   return $rsvp_users;
 }
 
}//WI_Board_Events