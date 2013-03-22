<?php
/**
 * WI_Board_Attendance allows the board to track attendace to board events.
 * 
 * The WI_Board_Attendance class allows chosen board members to track attendance to
 * board events.  Once tracked, everyone on the board can see attendance records
 * for each member.
 *
 * @package Nonprofit Board Management
 *
 * @version 0.1
 * @author Wired Impact
 */
class WI_Board_Attendance {
  
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
  
  
  public function __construct() {
    //Set our table name for the db from the start.
    $this->table_name = $this->get_table_prefix() . 'board_attendance';
    
    //Add subpage items below events
    add_action( 'winbm_add_page_after_events', array( $this, 'add_subpages' ) );
    
    //Add database table on activation to hold Attendance tracking.
    //We must use a constant instead of __FILE__ because this file is loaded using require_once.
    register_activation_hook( BOARD_MANAGEMENT_FILEFULLPATH, array( $this, 'create_db_table' ) );
    
    //Add individual attendance page to expand menu hook.
    add_filter( 'winbm_local_scripts', array( $this, 'expand_menu' ), 10, 2 );
    
    //Add and save meta box
    add_action( 'load-post.php', array( $this, 'create_attendance_meta_boxes' ) );
    add_action( 'save_post', array( $this, 'save_board_attendance_meta' ), 10, 2 );
    
    //Add support video onto the support page.
    add_action( 'winbm_at_support_middle', array( $this, 'add_support_content' ) );
  }
  
  
  /*
   * Create the database table that will hold our board attendance information.
   * 
   * We create a database table that will hold our board attendance information.
   * We check first to make sure the table doesn't exist by seeing if the
   * version exists in the options table.
   */
  public function create_db_table(){
    //Only create table if it doesn't exist.
    if( get_option( 'board_attendance_db_version' ) == false ){
      global $wpdb;

      $table_name = $this->table_name;

      $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) NOT NULL,
        post_id bigint(20) NOT NULL,
        attended tinyint(2) NOT NULL,
        time timestamp NOT NULL,
        PRIMARY  KEY  (id),
        UNIQUE KEY (user_id, post_id)
      );";

      require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
      dbDelta($sql);

      //We set a variable in options in case we need to update the database in the future.
      add_option('board_attendance_db_version', 0.1);
    }
  }
  
  
  /*
   * Add two attendance subpages to the sidebar menu just under the "Board Events" menu item.
   */
  public function add_subpages(){
    add_submenu_page( 'nonprofit-board', __( 'Board Event Attendance', 'nonprofit-board-management' ), __( 'Event Attendance', 'nonprofit-board-management' ), 'view_board_content', 'nonprofit-board/attendance', array( $this, 'display_board_attendance_page' ) );
    add_submenu_page( 'options.php', 'Board Member Attendance', 'Board Member Attendance', 'view_board_content', 'nonprofit-board/attendance/member', array( $this, 'display_member_attendance_page' ) );
  }
  
  
  /*
   * Expand the Board management menu for an individual board member's attendance page.
   * 
   * @param array $local_scripts The js that is sent locally to our plugin.
   * @param object $screen Information about the current screen.
   * @return bool True to expand the screen and false not to expand it.
   */
  public function expand_menu( $local_scripts, $screen ){
    if( $screen->id == 'admin_page_nonprofit-board/attendance/member' ){
      $local_scripts['expand_board_menu'] = true;
    }
      
    return $local_scripts;
  }
  
  
  /*
   * Show our meta box for tracking attendance if user has the capability to track.
   */
  public function create_attendance_meta_boxes(){
    if( current_user_can( 'track_event_attendance' ) ){
      add_meta_box( 'board_event_attendance',
          __( 'Track Attendance', 'nonprofit-board-management' ),
          array( $this, 'display_attendance_tracking_meta' ),
          'board_events', 'normal', 'default'
      );
    }
  }
  
  
  /*
   * Display the attendance tracking meta box.
   * 
   * The meta box allows a user who can track attendance to
   * mark who attended and who didn't attend an event.  The meta box
   * only shows after the event's end date and time has passed.
   * 
   * @param object $board_event The $post object for the board event.
   */
  public function display_attendance_tracking_meta( $board_event ){
    //Don't allow them to track attendance if the event isn't over.
    $event_end_time = get_post_meta( $board_event->ID, '_end_date_time', true );
    $current_time = current_time( 'timestamp' );
    if( $event_end_time > $current_time ){
      _e( '<p>You will be able to track attendance once the event has passed the end date and time.</p>', 'nonprofit-board-management' );
      
      return false;
    }
    
    //Get all the serving board members.
    global $wi_board_mgmt;
    $board_members = $wi_board_mgmt->board_members;
    
    //Show message if no board members exist
    //This only happens if a user has the ability to track attendance and then every user is removed from the board
    if( empty( $board_members) ){
      _e( '<p>It looks like you don\'t have any users set up as board members.  Once you have some you can track their attendance.
        To add a board member create a user and set their role to "Board Member".</p>', 'nonprofit-board-management' );

      return false;
    }
    
    $nonce = wp_create_nonce( 'event_attendance_nonce' );
    //Loop through users to display each one with three radio buttons needed.
    ?>
    <input type="hidden" id="_event_attendance_nonce" name="_event_attendance_nonce" value="<?php echo $nonce ?>" />
    <table class="record-attendance">
    <?php 
      foreach( $board_members as $board_member ){
        $attended = apply_filters( 'winbm_user_event_attendance_radios',
                $this->get_user_event_attendance( $board_event->ID, $board_member->ID ),
                $board_member, $board_event
                );
        $board_member->ID = intval( $board_member->ID );
    ?>
      <tr>
        <td><?php echo esc_html( $board_member->display_name ); ?></td>
        <td>
          <input type="radio" id="attended-<?php echo $board_member->ID; ?>" name="attendance-<?php echo $board_member->ID; ?>" <?php checked( $attended, 1 ); ?> value="1" />
          <label for="attended-<?php echo $board_member->ID; ?>"> <?php _e( 'Attended', 'nonprofit-board-management' ); ?></label>
          
          <input type="radio" id="not-<?php echo $board_member->ID; ?>" name="attendance-<?php echo $board_member->ID; ?>" <?php checked( $attended, 0 ); ?> value="0" />
          <label for="not-<?php echo $board_member->ID; ?>"> <?php _e( "Didn't Attend", 'nonprofit-board-management' ); ?></label>
          
          <input type="radio" id="na-<?php echo $board_member->ID; ?>" name="attendance-<?php echo $board_member->ID; ?>" <?php checked( $attended, false ); ?> value="na" />
          <label for="na-<?php echo $board_member->ID; ?>"> <?php _e( 'N/A', 'nonprofit-board-management' ); ?></label>
        </td>
      </tr> 
    <?php
      }//Foreach
    ?>
    </table>
    <?php
  }
  
  
  /*
   * Save the attendance info for a board event from the meta box.
   * 
   * @param int $board_event_id Post ID of the board event.
   * @param object $board_event Post object for the board event.
   */
  public function save_board_attendance_meta( $board_event_id, $board_event ){
    
    //Check autosave, post type, user caps, nonce
    if( wp_is_post_autosave( $board_event_id ) || wp_is_post_revision( $board_event_id ) ) {
      return false;
    }
    if( $board_event->post_type != 'board_events' ){
      return false;
    }
    if( !current_user_can( 'track_event_attendance', $board_event_id ) ){
      return false;
    }
    if ( !isset( $_REQUEST['_event_attendance_nonce'] ) || !wp_verify_nonce( $_REQUEST['_event_attendance_nonce'], 'event_attendance_nonce' ) ){
      return false;
    }
    
    global $wi_board_mgmt;
    $board_members = $wi_board_mgmt->board_members;
    
    foreach( $board_members as $board_member ){
      if( isset( $_REQUEST['attendance-' . $board_member->ID] ) ){

        //Get whether they attended or not and whether they a recorded attendance in the past.
        $attended = intval( $_REQUEST['attendance-' . $board_member->ID] );
        $prev_attended = $this->get_user_event_attendance( $board_event_id, $board_member->ID );
        
        //If attendance was not recorded before, and is either attended or didn't attend, then insert into db
        if( $prev_attended === false && $_REQUEST['attendance-' . $board_member->ID] != 'na' ){
          $this->insert_user_attendance( $board_member->ID, $board_event_id, $attended );
        }
        
        //If attendance was previously recorded, but now must be deleted because they no longer want it recorded.
        else if( $prev_attended !== false && $_REQUEST['attendance-' . $board_member->ID] == 'na' ){
          $this->delete_user_attendance( $board_member->ID, $board_event_id );
        }
        
        //If the attendance was recorded previously, but has been changed.
        else if ( $prev_attended != $attended ){
          $this->update_user_attendance( $board_member->ID, $board_event_id, $attended );
        }
        
      }//If attendance field is set
    }//Foreach board member
  }

  
 /*
  * Display our board attendance page with a summary of attendance for each board member.
  */
  public function display_board_attendance_page(){ ?>
    <div class="wrap">
        <?php screen_icon( 'board-mgmt' ); ?>
        <h2><?php _e( 'Board Event Attendance', 'nonprofit-board-management' ); ?></h2>
        <p>
          <?php _e( 'To see more details about each board member\'s attendance, 
            click the "View Detailed Event Attendance" link that shows up when you hover over their name.', 'nonprofit-board-management' ); ?><br />
          <?php echo $this->get_users_tracking_attendance(); ?>
        </p>
        
        <?php do_action( 'winbm_before_attendance_table' ); ?>
        
        <table class="wp-list-table widefat fixed posts" id="board-attendance-table" cellspacing="0">
          <thead>
            <tr>
              <th scope="col" id="name" class="manage-column column-username"><?php _e( 'Name', 'nonprofit-board-management' ); ?></th>
              <th scope="col" id="attended" class="manage-column column-attended num"><?php _e( 'Attended', 'nonprofit-board-management' ); ?></th>
              <th scope="col" id="not-attended" class="manage-column column-not-attended num"><?php _e( "Didn't Attend", 'nonprofit-board-management' ); ?></th>
              <th scope="col" id="total" class="manage-column column-total num"><?php _e( 'Total Events', 'nonprofit-board-management' ); ?></th>
            </tr>
          </thead>
          <tfoot>
            <tr>
              <th scope="col" class="manage-column column-username"><?php _e( 'Name', 'nonprofit-board-management' ); ?></th>
              <th scope="col" class="manage-column column-attended num"><?php _e( 'Attended', 'nonprofit-board-management' ); ?></th>
              <th scope="col" class="manage-column column-not-attended num"><?php _e( "Didn't Attend", 'nonprofit-board-management' ); ?></th>
              <th scope="col" class="manage-column column-total num"><?php _e( 'Total Events', 'nonprofit-board-management' ); ?></th>
            </tr>
          </tfoot>
          <tbody>
            <?php
            global $wi_board_mgmt;
            $board_members = $wi_board_mgmt->board_members;

            //If no board members were found then give them a message.
            if( empty( $board_members ) ){ ?>
                <tr class="no-items">
                  <td class="colspanchange" colspan="5"><?php _e( 'No board members were found.  You should create some users and set their role to "Board Member".', 'nonprofit-board-management' ); ?></td>
                </tr>
            <?php
            }
            
            $alternate = 'alternate';
            foreach( $board_members as $board_member ){
              $attendance = apply_filters ( 'winbm_user_attendance_totals',
                      $this->get_attendance_totals_percentages( $board_member->ID ),
                      $board_member );
              ?>
               <tr class="<?php echo $alternate; ?>">
                 <td class="name column-username">
                   <?php echo get_avatar( $board_member->ID, '44' ); echo '<strong>' . $board_member->display_name . '</strong>'; ?><br />
                   <div class="row-actions">
                     <span class="view">
                       <a href="<?php echo admin_url( 'admin.php?page=nonprofit-board/attendance/member&id=' . $board_member->ID ); ?>"><?php _e( 'View Detailed Event Attendance', 'nonprofit-board-management' ); ?></a>
                     </span>
                   </div>
                 </td>
                 <td class="attended column-attended num"><?php echo intval( $attendance['attended'] ) . ' (' . $attendance['attended_perc'] . '%)'; ?></td>
                 <td class="not-attended column-attended num"><?php echo intval( $attendance['not'] ) . ' (' . $attendance['not_perc'] . '%)'; ?></td>
                 <td class="total column-total num"><?php echo intval( $attendance['total'] ); ?></td>
               </tr>
             <?php
             $alternate = ( $alternate == 'alternate' ) ? '' : 'alternate';
             }
        
          ?>
          </tbody>
        </table>
        
        <?php do_action( 'winbm_after_attendance_table' ); ?>
        
    </div>
  <?php  
  }
  
  
  /*
   * Display the attendance for a specific member.
   * 
   * This page includes every board event for this member
   * where attendance was tracked.  It shows each event along with
   * whether they attended or didn't attend.  It is sorted with the 
   * most recent events first.  While there are no parameters passed,
   * The board member's user id is sent through a query string.
   */
  public function display_member_attendance_page(){
    //Show an error if the id of the user isn't present or isn't a number.
    if( !isset( $_GET['id'] ) || !is_numeric( $_GET['id'] ) ){ ?>
      <div id="message" class="error">
        <p><?php _e( 'Oops.  You shouldn\'t be on this page right now.', 'nonprofit-board-management' ); ?></p>
      </div>
    <?php 
      return false;
    }
    
    $board_member_id = intval( $_GET['id'] );
    $board_member_array = get_users( array( 'include' => array( $board_member_id ) ) );
    $board_member = $board_member_array[0];
    $attendance = $this->get_attendance_totals_percentages( $board_member_id );
    ?>
    <div class="wrap">
        <?php screen_icon( 'board-mgmt' ); ?>
        <h2><?php _e( 'Board Member Attendance: ', 'nonprofit-board-management' ); echo $board_member->display_name; ?></h2>
        <h3 class="member-attendance">
          <?php
          _e( 'Attended: ', 'nonprofit-board-management' );
          echo $attendance['attended'] . ' (' . $attendance['attended_perc'] . '%) | ';
          _e( 'Didn\'t Attend: ', 'nonprofit-board-management' );
          echo $attendance['not'] . ' (' . $attendance['not_perc'] . '%) | ';
          _e( 'Total Events Tracked: ', 'nonprofit-board-management' );
          echo $attendance['total'];
          ?>
        </h3>
        <p>
          <?php printf( __( 'Back to <a href="%s">Event Attendance Summary</a>.', 'nonprofit-board-management' ), admin_url( 'admin.php?page=nonprofit-board/attendance' ) ); ?>
        </p>
        
        <?php do_action( 'winbm_before_individual_attendance_table', $board_member ); ?>
        
        <table class="wp-list-table widefat fixed posts" id="board-attendance-table" cellspacing="0">
          <thead>
            <tr>
              <th scope="col" id="event" class="manage-column column-event"><?php _e( 'Board Event', 'nonprofit-board-management' ); ?></th>
              <th scope="col" id="attended" class="manage-column column-attended num"><?php _e( 'Attended', 'nonprofit-board-management' ); ?></th>
              <th scope="col" id="not-attended" class="manage-column column-not-attended num"><?php _e( "Didn't Attend", 'nonprofit-board-management' ); ?></th>
            </tr>
          </thead>
          <tfoot>
            <tr>
              <th scope="col" id="event" class="manage-column column-event"><?php _e( 'Board Event', 'nonprofit-board-management' ); ?></th>
              <th scope="col" class="manage-column column-attended num"><?php _e( 'Attended', 'nonprofit-board-management' ); ?></th>
              <th scope="col" class="manage-column column-not-attended num"><?php _e( "Didn't Attend", 'nonprofit-board-management' ); ?></th>
            </tr>
          </tfoot>
          <tbody>
            <?php
            
            $attendance_record = apply_filters( 'winbm_individual_attendance_record', 
                    $this->get_user_attendance_record( $board_member_id ),
                    $board_member_id );
            //If no board members were found then give them a message.
            if( empty( $attendance_record ) ){ ?>
                <tr class="no-items">
                  <td class="colspanchange" colspan="3"><?php _e( 'This board member has not had their attendance recorded for any event.' ); ?></td>
                </tr>
            <?php
            }
            global $wi_board_events;
            $alternate = 'alternate';
            foreach( $attendance_record as $event ){
              $board_event_meta = $wi_board_events->retrieve_board_event_meta( $event->post_id );
              ?>
               <tr class="<?php echo $alternate; ?>">
                 <td>
                   <strong><a href="<?php echo admin_url( 'post.php?post=' . $event->post_id . '&action=edit' ); ?>" title="Edit Event Attendance">
                     <?php echo get_the_title( $event->post_id ); ?>
                     </a></strong><br />
                   <?php echo $wi_board_events->format_event_times( $board_event_meta['start_date_time'], '', true ); ?>
                 </td>
                 <td class="num"><?php if( $event->attended == 1 ) _e( 'X', 'nonprofit-board-management' ); ?></td>
                 <td class="num"><?php if( $event->attended == 0 ) _e( 'X', 'nonprofit-board-management' ); ?></td>
               </tr>
             <?php
             $alternate = ( $alternate == 'alternate' ) ? '' : 'alternate';
             }
        
          ?>
          </tbody>
        </table>
        
        <?php do_action( 'winbm_after_individual_attendance_table', $board_member ); ?>
        
        <p><?php _e( '*Events that have been permanently deleted will not show in the list of events.', 'nonprofit-board-management' ); ?></p>
    </div>
  <?php
  }
  
  
  /*
   * Return the table prefix for this WordPress install.
   * 
   * @return string Table prefix for this install of WordPress.
   */
  private function get_table_prefix(){
    global $wpdb;

    return $wpdb->prefix;
  }

  
  /*
   * Insert user attendance into the database.
   * 
   * @param int $board_member_id User id of the board member.
   * @param int $board_event_id Post id of the board event.
   * @param int $attended 1 for attended, 0 for didn't attend.
   */
  private function insert_user_attendance( $board_member_id, $board_event_id, $attended ){
    global $wpdb;
    $wpdb->insert(
            $this->table_name,
            array( 'user_id' => $board_member_id, 'post_id' => $board_event_id, 'attended' => $attended ),
            array( '%d', '%d', '%d' ) //All of these should be saved as integers
           ); 
    
    do_action( 'winbm_insert_user_attendance', $board_member_id, $board_event_id, $attended );
  }
  
  
  /*
   * Update a user's attendance in the database.
   * 
   * @param int $board_member_id User id of the board member.
   * @param int $board_event_id Post id of the board event.
   * @param int $attended 1 for attended, 0 for didn't attend.
   */
  private function update_user_attendance( $board_member_id, $board_event_id, $attended ){
    global $wpdb;
    $wpdb->update(
            $this->table_name,
            array( 'attended' => $attended ),
            array( 'user_id' => $board_member_id, 'post_id' => $board_event_id ),
            array( '%d' ),
            array( '%d', '%d' )
           );
    
    do_action( 'winbm_update_user_attendance', $board_member_id, $board_event_id, $attended );
  }
  
  
  /*
   * Remove a user's attendance for one event from the database.
   * 
   * @param int $board_member_id User id of the board member.
   * @param int $board_event_id Post id of the board event.
   */
  private function delete_user_attendance( $board_member_id, $board_event_id ){
    global $wpdb;
    $wpdb->query( 
            $wpdb->prepare( 
             "
              DELETE FROM $this->table_name
              WHERE user_id = %d
              AND post_id = %d
             ",
              $board_member_id, $board_event_id
              )
           );
    
    do_action( 'winbm_delete_user_attendance', $board_member_id, $board_event_id );
  }
  
  
  /*
   * Get percentage of two numbers
   * 
   * @param int $top_number Top number in the division formula.
   * @param int $bottom_number Bottom number in the division formula.
   * @return float Percentage rounded to two decimal places (66.67).
   */
  private function get_percentage( $top_number, $bottom_number ){
    $percentage = 0;
    if( $bottom_number != 0 ){
      $percentage = round( $top_number / $bottom_number * 100, 2 );
    }

    return $percentage;
  }
  
  
  /*
   * Get users who can record attendance.
   * 
   * @return string Either a message that no one can track attendance or a list of who can track.
   */
  private function get_users_tracking_attendance(){
    global $wi_board_mgmt;
    $board_members = $wi_board_mgmt->board_members;
    
    $attendance_trackers = array();
    foreach( $board_members as $board_member ){
      if( user_can( $board_member->ID, 'track_event_attendance' ) ){
        $attendance_trackers[] = $board_member->display_name;
      }
    }
    
    if( empty( $attendance_trackers ) ){
      return __( 'No one is currently able to track attendance.  A WordPress admin can give a 
        board member permission to do this on the member\'s profile edit page.', 'nonprofit-board-management' );
    }
    else{
      $trackers_string = sprintf( __( 'The following board members are able to track event attendance through <a href="%s">each past event\'s edit screen</a>: ', 'nonprofit-board-management' ), admin_url( 'edit.php?post_type=board_events&events=past' ) );
      $trackers_string .= implode( ', ', $attendance_trackers );
      
      return $trackers_string;
    }
  }
  
  
  /*
   * Get attendance status for a specific board member for a specific event.
   * 
   * @param int $board_event_id Post ID for the board event.
   * @param int $board_member_id User ID for the board member.
   * @return bool|int False if not recorded, 1 for attended, 0 for not attended.
   */
  private function get_user_event_attendance( $board_event_id, $board_member_id ){
    global $wpdb;

    //Check if this user has had attendance marked for this event.
    //NULL means they haven't yet.
    $attended = $wpdb->get_var( $wpdb->prepare(
              "
               SELECT attended
               FROM " . $wpdb->prefix  . "board_attendance
               WHERE post_id = %d
               AND user_id = %d
              ",
              $board_event_id,
              $board_member_id
            ) );

    $attended_status = ( $attended == NULL ) ? false : (int)$attended;

    return $attended_status;
  }
  
  
  /*
   * Get user numbers for attended, not attended, and total with percentages.
   * 
   * @param int $board_member_id User ID of the board member.
   * @return array Number attended, not attended, totals, and percentages.
   */
  private function get_attendance_totals_percentages( $board_member_id ){
    $attendance = array();
    $attendance['attended'] = $this->get_num_events_attendance( $board_member_id );
    $attendance['not'] = $this->get_num_events_attendance( $board_member_id, 0 );
    $attendance['total'] = $attendance['attended'] + $attendance['not'];

    $attendance['attended_perc'] = $this->get_percentage( $attendance['attended'], $attendance['total'] );
    $attendance['not_perc'] = $this->get_percentage( $attendance['not'], $attendance['total'] );
    
    return $attendance;
  }
  
  
  /*
   * Get number of events attended or not attended for a board member.
   * 
   * @param int $board_member_id User ID of the board member.
   * @param int $attended 1 for number attending, 0 for number not attending.
   * @return int Number attended or not attended.
   */
  private function get_num_events_attendance( $board_member_id, $attended = 1 ){
    global $wpdb, $wi_board_events;

    $attendance_table = $this->table_name;
    $postmeta_table = $wi_board_events->get_table_prefix() . 'postmeta';
    $num_attendance = $wpdb->get_var(
            "
              SELECT COUNT( {$attendance_table}.attended )
              FROM {$attendance_table}
              INNER JOIN {$postmeta_table}
              ON {$attendance_table}.post_id = {$postmeta_table}.post_id
              WHERE user_id = {$board_member_id}
              AND attended = {$attended}
              AND {$postmeta_table}.meta_key = '_start_date_time'
              AND {$postmeta_table}.meta_value < " . current_time( 'timestamp' ) . "
            "
            );  

    return intval( $num_attendance );  
  }
  
  
  /*
   * Get the entire attendance record for a user sorted by start date of event.
   * 
   * With this database call we get the ID of the event and whether
   * or not the user attended.  We also use the _start_date_time meta value
   * to sort from most recent to oldest events.
   * 
   * @param int $board_member_id User ID of the board member.
   * @return array Array of objects for each event that was tracked. Empty array if no events.
   */
  private function get_user_attendance_record( $board_member_id ){
    global $wpdb, $wi_board_events;
    $attendance_table = $this->table_name;
    $postmeta_table = $wi_board_events->get_table_prefix() . 'postmeta';
    $attendance_record = $wpdb->get_results( 
                        "
                        SELECT
                          {$attendance_table}.post_id,
                          {$attendance_table}.attended,
                          {$postmeta_table}.meta_value
                        FROM {$attendance_table}
                        INNER JOIN {$postmeta_table}
                        ON {$attendance_table}.post_id = {$postmeta_table}.post_id
                        WHERE {$attendance_table}.user_id = {$board_member_id}
                        AND {$postmeta_table}.meta_key = '_start_date_time'
                        AND {$postmeta_table}.meta_value < " . current_time( 'timestamp' ) . "
                        ORDER BY {$postmeta_table}.meta_value DESC
                        "
                       );
                        
    return $attendance_record;
  }
  
  
  /*
   * Add a support video on how to track attendance.
   */
  public function add_support_content(){
    ?>
    <h3><a class="support-heading" href="#"><span>+ </span><?php _e( 'How to Track Attendance for an Event', 'nonprofit-board-management' ); ?></a></h3>
    <div class="support-content hide">
      <iframe width="640" height="360" src="https://www.youtube.com/embed/WLz6axkCW1Y" frameborder="0" allowfullscreen></iframe>
    </div>
    <?php
  }
}//WI_Board_Attendance


//Initiate board attendance
add_action('winbm_init', 'wi_board_management_attendance_init' );
function wi_board_management_attendance_init(){
   $wi_board_attendance = new WI_Board_Attendance(); 
}