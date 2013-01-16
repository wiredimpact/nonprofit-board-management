<?php
/**
 * WI_Board_Events allows the board to add and RSVP to upcoming board events.
 *
 * @author Wired Impact
 */
class WI_Board_Events {
  
  public function __construct() {
    //Flush rewrite rules 
    register_activation_hook( __FILE__, array( $this, 'flush_slugs' ) ); 
    
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
        <td><input type="text" id="start-date-time" name="start-date-time" class="regular-text" value="<?php echo $board_event_meta['start_date_time']; ?>" /></td>
      </tr>
      
      <tr>
        <td><label for="end-date-time">End Date & Time</label></td>
        <td><input type="text" id="end-date-time" name="end-date-time" class="regular-text" value="<?php echo $board_event_meta['end_date_time']; ?>" /></td>
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
    
    //Save all of our fields
    //Location
    if (isset($_REQUEST['location'])) {
      update_post_meta($board_event_id, 'location', $_REQUEST['location']);
    }
    //Street
    if (isset($_REQUEST['street'])) {
      update_post_meta($board_event_id, 'street', $_REQUEST['street']);
    }
    //Area
    if (isset($_REQUEST['area'])) {
      update_post_meta($board_event_id, 'area', $_REQUEST['area']);
    }
    //Start Date & Time
    if (isset($_REQUEST['start-date-time'])) {
      update_post_meta($board_event_id, 'start_date_time', $_REQUEST['start-date-time']);
    }
    //End Date & Time
    if (isset($_REQUEST['end-date-time'])) {
      update_post_meta($board_event_id, 'end_date_time', $_REQUEST['end-date-time']);
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
    $board_event_meta['start_date_time'] = ( isset( $board_event_meta_raw['start_date_time'] ) ) ? $board_event_meta_raw['start_date_time'][0] : '';
    $board_event_meta['end_date_time'] = ( isset( $board_event_meta_raw['end_date_time'] ) ) ? $board_event_meta_raw['end_date_time'][0] : '';
    
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
       
       echo '';
       
       break;
     
     case 'rsvp':
       
       echo '';
       
       break;
   }
 }
}//WI_Board_Events