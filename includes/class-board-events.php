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
   * Display the meta fields and values for volunteer apps when in the admin
   */
  public function display_board_event_details( $board_event ){
    //Get all the meta data
    $board_event_meta = get_post_custom( $board_event->ID );
    $location = ( isset( $board_event_meta['location'] ) ) ? $board_event_meta['location'][0] : '';
    $street = ( isset( $board_event_meta['street'] ) ) ? $board_event_meta['street'][0] : '';
    $area = ( isset( $board_event_meta['area'] ) ) ? $board_event_meta['area'][0] : '';
    $date = ( isset( $board_event_meta['date'] ) ) ? $board_event_meta['date'][0] : '';
    $start_date_time = ( isset( $board_event_meta['start_date_time'] ) ) ? $board_event_meta['start_date_time'][0] : '';
    $end_date_time = ( isset( $board_event_meta['end_date_time'] ) ) ? $board_event_meta['end_date_time'][0] : '';
    
    ?>
    <table>
      <tr>
        <td><label for="location">Location Name</label></td>
        <td><input type="text" id="location" name="location" class="regular-text" value="<?php echo $location ?>" /></td>
      </tr>
      
      <tr>
        <td><label for="street">Street Address</label></td>
        <td><input type="text" id="street" name="street" class="regular-text" value="<?php echo $street ?>" /></td>
      </tr>
      
      <tr>
        <td><label for="area">City, State Zip</label></td>
        <td><input type="text" id="area" name="area" class="regular-text" value="<?php echo $area ?>" /></td>
      </tr>
      
      <tr>
        <td><label for="start-date-time">Start Date & Time</label></td>
        <td><input type="text" id="start-date-time" name="start-date-time" class="regular-text" value="<?php echo $start_date_time ?>" /></td>
      </tr>
      
      <tr>
        <td><label for="end-date-time">End Date & Time</label></td>
        <td><input type="text" id="end-date-time" name="end-date-time" class="regular-text" value="<?php echo $end_date_time ?>" /></td>
      </tr>
      
    </table>
    <?php
  }
  
  /*
   * Save the meta data fields for volunteer opps
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
  
  public function display_board_event_rsvps(){
    echo '<p>Here we will show each person that is coming, not coming, and hasn\'t responded.</p>';
  }
}