<?php
/**
 * Allows users to create board events and RSVP for those events.
 * 
 * The WI_Board_Events class allows those with the board member role to create
 * board events and RSVP for those events through the WordPress admin.
 * Nothing in this class is created for the front-end of WordPress.
 *
 * @package Nonprofit Board Management
 *
 * @version 0.1
 * @author Wired Impact
 */
class WI_Board_Events {
  
  /*
   * The version of our database changes so we know when adjustments are needed.
   * 
   * @var int
   */
  const db_version = 1.0;
  
  /*
   * The full name of our database table.
   * 
   * @var string
   */
  private $table_name = '';
  
  /*
   * Runs all board event filters and hooks and all necessary activation actions.
   */
  public function __construct() {
    //Set our table name for the db from the start.
    $this->table_name = $this->get_table_prefix() . 'board_rsvps';
    
    //Add database table on activation to hold RSVPs.
    //We must use a constant instead of __FILE__ because this file is loaded using require_once.
    register_activation_hook( BOARD_MANAGEMENT_FILEFULLPATH, array( $this, 'create_db_table' ) );
    
    //Load CSS and JS
    add_action( 'admin_menu', array( $this, 'insert_css') );
    add_action( 'admin_menu', array( $this, 'insert_js') );

    //Create our board events custom post type
    add_action( 'init', array( $this, 'create_board_events_type' ) );
    add_action( 'admin_init', array( $this, 'create_board_events_meta_boxes' ) );
    add_action( 'load-post.php', array( $this, 'create_existing_event_meta_boxes' ) );
    add_action( 'save_post', array( $this, 'save_board_events_meta' ), 10, 2 );
    
    //Handle meta capabilities for our board_events custom post type.
    add_filter( 'map_meta_cap', array( $this, 'board_events_map_meta_cap' ), 10, 4 );
    
    //Remove the filter field from the board events list screen
    add_action( 'admin_head', array( $this, 'remove_date_filter' ) );
    
    //Change post updated content.
    add_filter( 'post_updated_messages', array( $this, 'change_updated_messages' ) );
    
    //Adjust the columns and content shown when viewing the board events post type list.
    add_filter( 'manage_edit-board_events_columns', array( $this, 'edit_board_events_columns' ) );
    add_action( 'manage_board_events_posts_custom_column', array( $this, 'show_board_event_columns' ), 10, 2 );
    add_filter( 'manage_edit-board_events_sortable_columns', array( $this, 'make_board_events_sortable' ) );
    add_action( 'load-edit.php', array( $this, 'edit_board_events_load' ) );
    
    //Add our board events dashboard widget
    add_action('wp_dashboard_setup', array( $this, 'add_board_events_dashboard_widget' ) );
    
    //Save RSVPs for the events via ajax
    add_action( 'wp_ajax_rsvp', array( $this, 'rsvp' ) );
  }
  
  
  /*
   * Create the database table that will hold our board event RSVP information.
   * 
   * We create a database table that will hold our board event RSVP information.
   * We check first to make sure the table doesn't exist by seeing if the
   * version exists in the options table.
   */
  public function create_db_table(){
    //Only create table if it doesn't exist.
    if( get_option( 'board_rsvp_db_version' ) == false ){
      global $wpdb;

      $table_name = $this->table_name;

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
   * Return the table prefix for this WordPress install.
   * 
   * @return string Table prefix for this install of WordPress.
   */
  public function get_table_prefix(){
    global $wpdb;

    return $wpdb->prefix;
  }
  
  
  /*
   * Enqueue CSS needed for jQuery UI.
   */
  public function insert_css(){
    wp_enqueue_style( 'jquery-ui-smoothness', BOARD_MANAGEMENT_PLUGINFULLURL . 'css/jquery-ui.css' );
  }

  
  /*
   * Enqueue JS needed for the board events including JS generated through PHP.
   */
  public function insert_js(){
    wp_enqueue_script( 'jquery-ui-slider' );
    wp_enqueue_script( 'jquery-ui-datepicker' );
    wp_enqueue_script( 'jquery-timepicker', BOARD_MANAGEMENT_PLUGINFULLURL . 'js/jquery-ui-timepicker.js', array( 'jquery-ui-slider', 'jquery-ui-datepicker' ) );
    
    //wp_localize_script allows us to send PHP info to JS
    wp_localize_script( 'board-mgmt', 'wi_board_events', array(
      // generate a nonces that can be checked later on save
      'save_rsvp_nonce' => wp_create_nonce( 'save_rsvp_nonce' ),  
      'error_rsvp' => __( 'Woops.  We failed to RSVP for you.  Please try again.' ),
      )
     );
  }
  
  
  /*
   * Create our board_events custom post type.
   */
  public function create_board_events_type(){
    $labels = array(
      'name' => 'Board Events',
      'singular_name' => 'Board Event',
      'add_new' => 'Add Board Event',
      'add_new_item' => 'Add Board Event',
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
      'show_in_menu' => false, //Done through add_submenu_page
      'query_var' => false,
      'capability_type' => 'board_event',
      'capabilities' => array(
          'publish_posts' => 'publish_board_events',
          'edit_posts' => 'edit_board_events',
          'edit_others_posts' => 'edit_others_board_events',
          'delete_posts' => 'delete_board_events',
          'delete_others_posts' => 'delete_others_board_events',
          'read_private_posts' => 'read_private_board_events',
          'edit_post' => 'edit_board_event',
          'delete_post' => 'delete_board_event',
          'read_post' => 'read_board_event'
      ),
      'supports' => array( 'title' )
    ); 
    
    register_post_type( 'board_events', $args );
  }
  
  
  /*
   * Handle meta capabilities for our board_events custom post type.
   * 
   * Handle all the meta capabilities for our board_events custom post type.
   * All board members have the ability to read, edit, and delete all of the 
   * board events since they are given all the capabilities necessary.
   */
  public function board_events_map_meta_cap( $caps, $cap, $user_id, $args ){
    //If editing, deleting, or reading a board event, get the post and post type object.
    if ( 'edit_board_event' == $cap || 'delete_board_event' == $cap || 'read_board_event' == $cap ) {
     $post = get_post( $args[0] );
     $post_type = get_post_type_object( $post->post_type );

     $caps = array();
    }

    //If editing a board_event, assign the required capability.
    if ( 'edit_board_event' == $cap ) {
     if ( $user_id == $post->post_author )
      $caps[] = $post_type->cap->edit_posts;
     else
      $caps[] = $post_type->cap->edit_others_posts;
    }

    //If deleting a board_event, assign the required capability.
    elseif ( 'delete_board_event' == $cap ) {
     if ( $user_id == $post->post_author )
      $caps[] = $post_type->cap->delete_posts;
     else
      $caps[] = $post_type->cap->delete_others_posts;
    }

    //If reading a private board_event, assign the required capability.
    elseif ( 'read_board_event' == $cap ) {
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
   * Create the meta boxes when adding/editing a board event.
   * 
   * Create the meta boxes when adding/editing a board event.  The boxes include
   * one for editing the details of the board event.
   */
  public function create_board_events_meta_boxes(){
    //Details of board event
    add_meta_box( 'board_event_details',
        'Board Event Details',
        array( $this, 'display_board_event_details' ),
        'board_events', 'normal', 'high'
    );
  }
  
  
  /*
   * Show meta boxes that are only needed on existing board events.
   * 
   * Show meta boxes that we only need for existing board events, not new board
   * events.  We add the RSVP meta box here so RSVPs are only shown after members
   * have the ability to RSVP.
   */
  public function create_existing_event_meta_boxes(){
    //Current signup info for board event
    add_meta_box( 'board_event_rsvps',
        'RSVP List',
        array( $this, 'display_board_event_rsvps' ),
        'board_events', 'side', 'default'
    );
  }
  
  
  /*
   * Display the meta fields and values when editing a board event.
   * 
   * @param object $board_event The $post object for the board event.
   */
  public function display_board_event_details( $board_event ){
    //Get all the meta data
    $board_event_meta = $this->retrieve_board_event_meta( $board_event->ID );
    $nonce = wp_create_nonce( 'event_details_nonce' );
    ?>
    <input type="hidden" id="_event_details_nonce" name="_event_details_nonce" value="<?php echo $nonce ?>" />
    <table>
      <tr>
        <td><label for="event-description">Event Description</label></td>
        <td><input type="text" id="event-description" name="event-description" class="large-text" value="<?php echo $board_event_meta['event_description']; ?>" /></td>
      </tr>
      
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
   * Save the meta fields for board events when saving from the edit screen.
   */
  public function save_board_events_meta( $board_event_id, $board_event ){
    
    //Check autosave, post type, user caps, nonce
    if( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
      return false;
    }
    if( $board_event->post_type != 'board_events' ){
      return false;
    }
    if( !current_user_can( 'edit_board_event', $board_event_id ) ){
      return false;
    }
    if ( !isset( $_REQUEST['_event_details_nonce'] ) || !wp_verify_nonce( $_REQUEST['_event_details_nonce'], 'event_details_nonce' ) ){
      return false;
    }
    
    //Save all of our fields
    //Event Description
    if( isset($_REQUEST['event-description'] ) ) {
      update_post_meta( $board_event_id, '_event_description', sanitize_text_field( $_REQUEST['event-description'] ) );
    }
    //Location
    if( isset($_REQUEST['location'] ) ) {
      update_post_meta( $board_event_id, '_location', sanitize_text_field( $_REQUEST['location'] ) );
    }
    //Street
    if(isset($_REQUEST['street'] ) ) {
      update_post_meta( $board_event_id, '_street', sanitize_text_field( $_REQUEST['street'] ) );
    }
    //Area
    if(isset($_REQUEST['area'] ) ) {
      update_post_meta( $board_event_id, '_area', sanitize_text_field( $_REQUEST['area'] ) );
    }
    //Start Date & Time stored as UNIX timestamp
    if( isset($_REQUEST['start-date-time'] ) ) {
      $start_date_time = strtotime( $_REQUEST['start-date-time'] );
      update_post_meta( $board_event_id, '_start_date_time', $start_date_time );
    }
    //End Date & Time stored as UNIX timestamp
    if( isset($_REQUEST['end-date-time'] ) ) {
      $end_date_time = strtotime( $_REQUEST['end-date-time'] );
      update_post_meta( $board_event_id, '_end_date_time', $end_date_time );
    }
  }
  
  
  /*
   * Display attending, not attending, and not responded for each board event.
   * 
   * Display attending, not attending, and not responded for each board event
   * as a meta box on the board_events edit screen.  This includes a count for 
   * each group along with names of the people in each group.
   * 
   * @param object $board_event The $post object for the board event.
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
    echo '<h4>Going (' . count( $attending ) . ')</h4>';
    echo implode( ', ', $attending );
    echo '<h4>Not Going (' . count( $not_attending ) . ')</h4>';
    echo implode( ', ', $not_attending );
    echo '<h4>Not Responsed (' . count( $no_response ) . ')</h4>';
    echo implode( ', ', $no_response );
  }
  
  
  /*
   * Hide the date filter from the board events list page.
   * 
   * There is no way to filter this out or we would have taken that approach.
   */
  public function remove_date_filter(){
    if( isset( $_GET['post_type'] ) && $_GET['post_type'] == 'board_events' ){
      ?>
      <style>
        .tablenav.top .actions + .actions {
          display: none;
        }
      </style>
      <?php
    }
  }
  
  
  /*
   * Change post updated messages on edit screen.
   * 
   * @param array $messages Existing updated messages for posts and pages.
   * @return array New updated messages content with board event messages added.
   */
  public function change_updated_messages( $messages ){    
    $messages['board_events'] = array(
      0 => '', // Unused. Messages start at index 1.
      1 => __( 'Event updated.' ),
      2 => __( 'Custom field updated.' ),
      3 => __( 'Custom field deleted.' ),
      4 => __( 'Event updated.' ),
     /* translators: %s: date and time of the revision */
      5 => isset( $_GET['revision'] ) ? sprintf( __( 'Event restored to revision from %s'), wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
      6 => __( 'Event published.' ),
      7 => __( 'Event saved.' ),
      8 => __( 'Event submitted.' ),
      9 => __( 'You should not be scheduling events this way.  It just won\'t work.'),
     10 => __( 'Event draft updated.' )
    );
    
    return $messages;
  }

  
  /*
   * Retrieve all the board event meta data.
   * 
   * @param int $post_id The ID of the board event we're referencing.
   * @return array Associative array of all meta data for the board event.
   */
  public function retrieve_board_event_meta( $post_id ){
    $board_event_meta_raw = get_post_custom( $post_id );
    $board_event_meta = array();
    
    $board_event_meta['event_description'] = ( isset( $board_event_meta_raw['_event_description'] ) ) ? $board_event_meta_raw['_event_description'][0] : '';
    $board_event_meta['location'] = ( isset( $board_event_meta_raw['_location'] ) ) ? $board_event_meta_raw['_location'][0] : '';
    $board_event_meta['street'] = ( isset( $board_event_meta_raw['_street'] ) ) ? $board_event_meta_raw['_street'][0] : '';
    $board_event_meta['area'] = ( isset( $board_event_meta_raw['_area'] ) ) ? $board_event_meta_raw['_area'][0] : '';
    $board_event_meta['start_date_time'] = ( isset( $board_event_meta_raw['_start_date_time'] ) && $board_event_meta_raw['_start_date_time'][0] != '' ) ? date( 'D, F d, Y h:i a', (int)$board_event_meta_raw['_start_date_time'][0] ) : '';
    $board_event_meta['end_date_time'] = ( isset( $board_event_meta_raw['_end_date_time']  ) && $board_event_meta_raw['_end_date_time'][0] != '' ) ? date( 'D, F d, Y h:i a', (int)$board_event_meta_raw['_end_date_time'][0] ) : '';
    
    return $board_event_meta;
  }
  
  
 /*
  * Add custom columns the board events content type list.
  * 
  * @param array $columns The default columns for board events.
  * @return array Custom columns we want to use on the board events list.
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
   
   //Only show the RSVP column if the user has that capability.
   if( current_user_can( 'serve_on_board' ) ){
     $columns['rsvp'] = __( 'RSVP' );
   }

   return $columns;
 }
 
 
 /*
  * Display content for each custom column for board events.
  * 
  * @param string $column Column to be displayed.
  * @param int $post_id ID of the board event to be displayed.
  */
 public function show_board_event_columns( $column, $post_id ){  
   $board_event_meta = $this->retrieve_board_event_meta( $post_id );
   $board_event = get_post( $post_id );
   
   switch( $column ){
     
     case 'location':
       
       echo $this->get_event_location( $board_event_meta );
       
       break;
     
     case 'date_time':
       
       echo $board_event_meta['start_date_time'];
       if( $board_event_meta['start_date_time'] != '' ) echo ' - ';
       echo $board_event_meta['end_date_time'];
       
       break;
    
     case 'description':
       
       echo wp_trim_words( $board_event_meta['event_description'], 15 );
       
       break;
     
     case 'attending':
       
       //Show how many are going and who it is.
       if( $board_event->post_status == 'publish' ){
         echo $this->get_attending_rsvps( $post_id );
       }
       else{
         _e( 'Event must be published prior to accepting RSVPs.' );
       }

       break;
     
     case 'rsvp':
       
       //Don't allow for an RSVP if board event hasn't been published.
       if( $board_event->post_status != 'publish' ){
         _e( 'Event must be published prior to accepting RSVPs.' );
         
         break;
       }
       
       //Determine whether they're going and if so, add the necessary classes.
       $user_id = get_current_user_id();
       $rsvp_status = $this->rsvp_status( $post_id, $user_id );
       
       //Classes button-primary and active are used for the chosen RSVP choice.
       //Attending RSVP button
       echo '<input id="attending" type="submit" class="button secondary-button ';
       if( $rsvp_status === 1 ){
         echo 'button-primary active';
       }
       echo '" value="I\'m Going" />';
       
       //Not attending RSVP button
       echo '<input id="not-attending" type="submit" class="button secondary-button ';
       if( $rsvp_status === 0 ){
         echo 'button-primary active';
       }
       echo '" value="I\'m Not Going" />';
       
       //Add the spinner for use during ajax loading.
       echo '<span class="waiting spinner" style="display: none;"></span>';
       
       break;
   }
 }

 
 /*
  * Add Date & Time as a sortable field.
  * 
  * @param array $columns List of sortable columns.
  * @return array List of sortable columns with date_time included.
  */
 public function make_board_events_sortable( $columns ){
   $columns['date_time'] = 'date_time';
   
   return $columns;
 }
 
 
 /*
  * Run our sort function for the request filter only during load-edit.php action.
  */
 public function edit_board_events_load() {
   add_filter( 'request', array( $this, 'sort_board_events' ) );
 }

 
 /*
  * On list of board events make date and time sortable by _start_date_time.
  * 
  * @param array $vars All variables needed to handle sorting.
  * @return array Adjusted variables needed to handle sorting.
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
 * Add our board events dashboard widget to the list of widgets.
 */
 public function add_board_events_dashboard_widget(){
   if( current_user_can( 'view_board_content' ) ){
    wp_add_dashboard_widget('board_events_db_widget', 'Upcoming Board Events', array( $this, 'display_board_events_dashboard_widget' ) );
   }
 }


 /*
  * Display a dashboard widget for a few upcoming board events.
  * 
  * @see add_board_events_dashboard_widget()
  */
 public function display_board_events_dashboard_widget(){
   $time_now = current_time( 'timestamp' );
   $args = array(
     'post_type' => 'board_events',
     'posts_per_page' => 3,
     'meta_key' => '_start_date_time',
     'meta_value' => $time_now,
     'meta_compare' => '>=',
     'orderby' => 'meta_value_num',
     'order' => 'ASC'
   );
   $upcoming_events = get_posts( $args );
   
   //If no upcoming events show the user a message.
   if( empty( $upcoming_events ) ){
     _e( 'There are no upcoming events. ' );
     echo '<a href="' . admin_url( 'edit.php?post_type=board_events' ) . '">';
     _e( 'Go ahead and add some.' );
     echo '</a>';
     
     return;
   }
   
   echo '<ul>';
   foreach( $upcoming_events as $event ){
     $board_event_meta = $this->retrieve_board_event_meta( $event->ID );
     ?>
      <li>
        <span class="title"><?php echo $event->post_title; ?></span>
        <span class="start-time"><?php echo $board_event_meta['start_date_time']; ?></span>
        <div class="location"><?php echo $this->get_event_location( $board_event_meta, false ); ?></div>
      </li>
     <?php
   }
   echo '</ul>';
   echo '<p class="note"><a href="' . admin_url( 'edit.php?post_type=board_events' ) . '">View, edit and RSVP to events</a></p>';
 }
 
 
 /*
  * Get the event location with a Google Maps link if possible.
  * 
  * @param array $board_event_meta Array of meta for the board event.
  * @param bool $line_breaks Whether to include line breaks in string.
  * @return string Location of event with Google Maps link if possible.
  */
 private function get_event_location( $board_event_meta, $line_breaks = true ){
  $event_location = ''; 
  
  //Add the Google Maps link if the area or street are not empty
  if( $board_event_meta['area'] != '' || $board_event_meta['street'] != '' ){
    $google_maps_string = str_replace( ' ', '+', $board_event_meta['street'] . ' ' . $board_event_meta['area'] );
    $google_maps_address = 'https://maps.google.com/maps?q=' . $google_maps_string;

    $event_location .= '<a href="' . $google_maps_address . '" title="Map this location on Google Maps" target="_blank">';
  }
  
  //Add location name
  $event_location .= $board_event_meta['location'];
  if( $board_event_meta['location'] != '' && $line_breaks == true ){
    $event_location .= '<br />';
  }
  else if( $board_event_meta['location'] != '' ){
    $event_location .= ', ';
  }
  //Add street
  $event_location .= $board_event_meta['street'];
  if( $board_event_meta['street'] != '' && $line_breaks == true ){
    $event_location .= '<br />';
  }
  else if( $board_event_meta['street'] != '' && $board_event_meta['area'] != '' ) {
    $event_location .= ', ';
  }
  //Add area (city, state zip)
  $event_location .= $board_event_meta['area'];
  
  //Close the Google Maps link if we added one.
  if( $board_event_meta['area'] != '' || $board_event_meta['street'] != '' ){
   $event_location .= '</a>';
  }

  return $event_location;
 } 
 
 
 /*
  * Via ajax save the RSVP for this board member.
  * 
  * Via ajax we save the RSVP for this user to our board_rsvps table. We only
  * update the table if the user is RSVPing for the first time or is changing
  * their RSVP status.  We then provide back the number of people coming to the
  * board event and a list of people that are coming.
  * 
  * @return int|string Int means no change to db.  String is num and list of RSVPs.
  * @see rsvp_status()
  * @see get_attending_rsvps()
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
  if( $rsvp_status === false ){
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
  * Provides the status of the RSVP for this user for this event.
  * 
  * @return bool|int False means hasn't RSVPed.  1 means going, 0 means not going.
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
  
  $rsvp_status = ( $rsvp == NULL ) ? false : (int)$rsvp;
  
  return $rsvp_status;
 }
 
 
 /*
  * Get all the user data for those who can RSVP grouped by their RSVP status.
  * 
  * @param int $post_id ID of the board event of which we want the RSVPs.
  * @return array Array of attending, not attending, and not responded with user objects in each.
  */
 private function board_event_rsvps( $post_id ){
   //Get all users who have cap to RSVP.
   global $wi_board_mgmt;
   $rsvp_users = $wi_board_mgmt->board_members;
   
   //Get all rsvps for this event that have happened.
   $event_rsvps = $this->get_db_rsvps( $post_id );
  
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
  * Get all rsvps from our db table for a given event.
  * 
  * @param int $post_id ID of the board event of which we want the RSVPs.
  * @return array Array of event rsvps in unique objects.
  */
 private function get_db_rsvps( $post_id ){
   global $wpdb;
   $event_rsvps = $wpdb->get_results(
           "
             SELECT user_id, rsvp
             FROM $this->table_name
             WHERE post_id = {$post_id}
           "
           );  
             
   return $event_rsvps;
 }
 
 
 /*
  * Get the number attending and a comma separated list of their names for display.
  * 
  * @param int $post_id ID of the board event.
  * @param bool $include_num Optional Whether we want the number coming too.
  * @return string Number coming in parentheses, along with list of names attending.
  */
 private function get_attending_rsvps( $post_id, $include_num = true ){
    $attending_rsvps = '';
   
    //Include the number of people attending if $include_num == true.
    if( $include_num == true ){
      $num_attending = $this->get_num_attending( $post_id );
      $attending_rsvps .= '(' . $num_attending. ')';
      if( $num_attending > 0 ){
        $attending_rsvps .= ' - ';
      }
    }
   
    //If $include_num is false or $num_attending is not equal to 0 then pull names
    //since we either don't if people are going or we know at least
    //one person is going.
    if( $include_num == false || ( isset( $num_attending ) && $num_attending != 0 ) ){
      $rsvps = $this->board_event_rsvps( $post_id );

      $attending = array();
      foreach( $rsvps['attending'] as $event_rsvp ){
        $attending[] = $event_rsvp->display_name;
      }

      $attending_rsvps .= implode( ', ', $attending );
    }

    return $attending_rsvps;
 }
 
 
 /*
  * Get the number of attending RSVPs to a specific board event.
  * 
  * Get the number of attending RSVPS to a specific event.  To do this
  * we must also check to ensure that those who RSVPed still have that
  * capability.  We use an inner join with the usermeta table to make
  * sure they're a board member or have the serve_on_board capability.
  * 
  * @param int $post_id ID of the board event we want to know about.
  * @return int Number of people attending the board event.
  */
 private function get_num_attending( $post_id ){
  global $wpdb;

  $rsvps_table = $this->table_name;
  $usermeta_table = $this->get_table_prefix() . 'usermeta';
  $num_attending_rsvps = $wpdb->get_var(
          "
            SELECT COUNT( {$rsvps_table}.rsvp )
            FROM {$rsvps_table}
            INNER JOIN {$usermeta_table}
            ON $rsvps_table.user_id = {$usermeta_table}.user_id
            WHERE {$rsvps_table}.post_id = {$post_id}
            AND {$rsvps_table}.rsvp = 1
            AND {$usermeta_table}.meta_key = '{$this->get_table_prefix()}capabilities'
            AND ( {$usermeta_table}.meta_value LIKE '%board_member%' OR {$usermeta_table}.meta_value LIKE '%serve_on_board%')
          "
          );  
            
  return $num_attending_rsvps;
 } 
}//WI_Board_Events