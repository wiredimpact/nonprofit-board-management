<?php
/**
 * WI_Board_Events allows the board to add and RSVP to upcoming board events.
 *
 * @author Wired Impact
 */
class WI_Board_Events {
  
  public function __construct() {
    //Add database table on activation for RSVP features
    //We must use a constant instead of __FILE__ because this file is loaded using require_once.
    register_activation_hook( BOARD_MANAGEMENT_FILEFULLPATH, array( $this, 'create_db_table' ) );
    
    //Flush rewrite rules 
    register_activation_hook( BOARD_MANAGEMENT_FILEFULLPATH, array( $this, 'flush_slugs' ) ); 
    
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
    
    //Save RSVPs for the events via ajax
    add_action( 'wp_ajax_rsvp', array( $this, 'rsvp' ) );
  }
  
  /*
   * Create the database table that will hold our board event RSVP information.
   */
  public function create_db_table(){
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
  
  
  /*
   * So our new post type's URLs will work out of the box
   * we flush the WordPress rewrite rules on activation.
   */
  public function flush_slugs(){
    $this->create_board_events_type();
    flush_rewrite_rules();
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
  }
  
  
  /*
   * Create our board events post type.
   */
  public function create_board_events_type(){
    //TODO Make sure the front of the URL is not added to the slug.
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

    //TODO Capabilities argument needs to be added here.
    $args = array(
      'labels' => $labels,
      'public' => true,
      'exclude_from_search' => true,
      'publicly_queryable' => true, //TODO See if we can turn this to false for public security purposes.
      'show_ui' => true,
      'show_in_nav_menus' => false,
      'show_in_menu' => 'nonprofit-board', 
      'query_var' => true,
      'rewrite' => array( 'slug' => 'board-event' ),
      'capability_type' => 'post',
      'has_archive' => false, 
      'hierarchical' => false,
      'menu_position' => null,
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
    
    ?>
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
    
    if( $board_event->post_type != 'board_events' ){
      return;
    }
    if( !current_user_can( 'edit_post', $board_event_id ) ){
      return;
    }
    
    //TODO sanitize the data first
    //TODO Save all meta data with underscore first so it doesn't show as a custom field
    //Save all of our fields
    //Location
    if (isset($_REQUEST['location'])) {
      update_post_meta( $board_event_id, 'location', $_REQUEST['location'] );
    }
    //Street
    if (isset($_REQUEST['street'])) {
      update_post_meta( $board_event_id, 'street', $_REQUEST['street'] );
    }
    //Area
    if (isset($_REQUEST['area'])) {
      update_post_meta( $board_event_id, 'area', $_REQUEST['area'] );
    }
    //Start Date & Time stored as UNIX timestamp
    if (isset($_REQUEST['start-date-time'])) {
      $start_date_time = strtotime( $_REQUEST['start-date-time'] );
      update_post_meta( $board_event_id, 'start_date_time', $start_date_time );
    }
    //End Date & Time stored as UNIX timestamp
    if (isset($_REQUEST['end-date-time'])) {
      $end_date_time = strtotime( $_REQUEST['end-date-time'] );
      update_post_meta( $board_event_id, 'end_date_time', $end_date_time );
    }
  }
  
  
  /*
   * Display who has RSVPed for each event including who's coming, 
   * who's not, and who hasn't responded yet.
   */
  public function display_board_event_rsvps(){
    echo '<p>Here we will show each person that is coming, not coming, and hasn\'t responded.</p>';
  }
  
  /*
   * Retrieve all the board event meta data and place it in an array.
   */
  private function retrieve_board_event_meta( $post_id ){
    $board_event_meta_raw = get_post_custom( $post_id );
    $board_event_meta = array();
    
    $board_event_meta['location'] = ( isset( $board_event_meta_raw['location'] ) ) ? $board_event_meta_raw['location'][0] : '';
    $board_event_meta['street'] = ( isset( $board_event_meta_raw['street'] ) ) ? $board_event_meta_raw['street'][0] : '';
    $board_event_meta['area'] = ( isset( $board_event_meta_raw['area'] ) ) ? $board_event_meta_raw['area'][0] : '';
    //TODO Fix dates that break when the field is set to blank.
    $board_event_meta['start_date_time'] = ( isset( $board_event_meta_raw['start_date_time'] ) ) ? date( 'D, F d, Y h:i a', (int)$board_event_meta_raw['start_date_time'][0] ) : '';
    $board_event_meta['end_date_time'] = ( isset( $board_event_meta_raw['end_date_time'] ) ) ? date( 'D, F d, Y h:i a', (int)$board_event_meta_raw['end_date_time'][0] ) : '';
    
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
     'rsvp' => __( 'RSVP' ),
   );

   return $columns;
 }
 
 
 /*
  * Show content for custom columns for board events.
  */
 public function show_board_event_columns( $column, $post_id ){
   global $post;
   $board_event_meta = $this->retrieve_board_event_meta( $post_id );
   
   switch( $column ){
     
     case 'location':
       
       echo $board_event_meta['location'] . '<br />';
       echo $board_event_meta['street'] . '<br />';;
       echo $board_event_meta['area'];
       
       break;
     
     case 'date_time':
       
       echo $board_event_meta['start_date_time'];
       echo ' - ';
       echo $board_event_meta['end_date_time'];
       
       break;
    
     case 'description':
       
       //TODO Make this handle strings of all lengths.  Don't add ... to short strings or strings that end in a period.
       echo substr( $post->post_content, 0, 50 ) . '...';
       
       break;
     
     case 'attending':
       
       echo $this->board_event_rsvps( $post_id );
       
       break;
     
     case 'rsvp':
       
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
       
       break;
   }
 }
 
 
 /*
  * Save the RSVP for this board member
  */
 public function rsvp(){
  //TODO Add nonce field via localize function 
  //check_ajax_referer( 'save_note_nonce', 'security' );
   
  //Put data in variables
  $post_id = intval( $_POST['post_id'] );
  $rsvp = intval( $_POST['rsvp'] );
  $user_id = get_current_user_id();
  
  //Access $wpdb.  We're going to need it.
  global $wpdb;
  $wpdb->show_errors(); //TODO Take this out when done testing.
  
  $rsvp_status = $this->rsvp_status( $post_id, $user_id );
  
  //Insert data into database
  $result = 'we did nothing';
  if( $rsvp_status === FALSE ){
    $result = $wpdb->insert(
            $wpdb->prefix . 'board_rsvps', //TODO Possibly make this a constant so it's easier to manage
            array( 'user_id' => $user_id, 'post_id' => $post_id, 'rsvp' => $rsvp ),
            array( '%d', '%d', '%d' ) //All of these should be saved as integers
           );
  }
  else if( $rsvp_status != $rsvp ) { //Only do the db update if there RSVP status in the db will change
    $result = $wpdb->update(
            $wpdb->prefix . 'board_rsvps', //TODO Possibly make this a constant so it's easier to manage
            array( 'rsvp' => $rsvp ), //Data to be updated
            array( 'user_id' => $user_id, 'post_id' => $post_id ), //Where clause
            array( '%d' ), //Format for data being updated
            array( '%d', '%d' )
           );
  }
   
  echo $result;
  
  die(); //required
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
 
 
 private function board_event_rsvps( $post_id ){
   $board_members = get_users( array( 'role' => 'board_member' ) );
   $board_members_attending = array();
   $board_members_not_attending = array();
   
   //Get user info for all board members
   
   //Get all rsvps for for given event id
   
   //loop through rsvps and for every one move into new arrays for going/not going.
   
   return $board_members;
 }
 
}//WI_Board_Events