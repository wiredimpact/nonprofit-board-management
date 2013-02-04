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
    
    //Add database table on activation to hold Attendance tracking.
    //We must use a constant instead of __FILE__ because this file is loaded using require_once.
    register_activation_hook( BOARD_MANAGEMENT_FILEFULLPATH, array( $this, 'create_db_table' ) );
    
    //Add and save meta box
    add_action( 'load-post.php', array( $this, 'create_existing_attendance_meta_boxes' ) );
    add_action( 'save_post', array( $this, 'save_board_attendance_meta' ), 10, 2 );
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
   * Show our meta box for tracking attendance.
   */
  public function create_existing_attendance_meta_boxes(){
    if( current_user_can( 'track_event_attendance' ) ){
      add_meta_box( 'board_event_attendance',
          'Track Attendance',
          array( $this, 'display_event_attendance_tracking' ),
          'board_events', 'normal', 'default'
      );
    }
  }
  
  
  /*
   * Display the attendance tracking we need.
   * 
   * @param object $board_event The $post object for the board event.
   */
  public function display_event_attendance_tracking( $board_event ){
    //Don't allow them to track attendance if the event isn't over.
    $event_end_time = get_post_meta( $board_event->ID, '_end_date_time', true );
    $current_time = current_time( 'timestamp' );
    if( $event_end_time > $current_time ){
      _e( 'You will be able to track attendance once the event has passed the end date and time.' );
      
      return false;
    }
    
    //Get all the serving board members.
    global $wi_board_mgmt;
    $board_members = $wi_board_mgmt->board_members;
    
    //Get all the meta data
    $nonce = wp_create_nonce( 'event_attendance_nonce' );
    
    //Loop through users to display each one with three radio buttons needed.
    ?>
    <input type="hidden" id="_event_attendance_nonce" name="_event_attendance_nonce" value="<?php echo $nonce ?>" />
    <table class="record-attendance">
    <?php 
      foreach( $board_members as $board_member ){
        $attended = $this->get_user_event_attendance( $board_event->ID, $board_member->ID );
    ?>
      <tr>
        <td><?php echo $board_member->display_name; ?></td>
        <td>
          <input type="radio" id="attended-<?php echo $board_member->ID; ?>" name="attendance-<?php echo $board_member->ID; ?>" <?php checked( $attended, 1 ); ?> value="1" />
          <label for="attended-<?php echo $board_member->ID; ?>"> <?php _e( 'Attended' ); ?></label>
          
          <input type="radio" id="not-<?php echo $board_member->ID; ?>" name="attendance-<?php echo $board_member->ID; ?>" <?php checked( $attended, 0 ); ?> value="0" />
          <label for="not-<?php echo $board_member->ID; ?>"> <?php _e( 'Didn\'t Attend' ); ?></label>
          
          <input type="radio" id="na-<?php echo $board_member->ID; ?>" name="attendance-<?php echo $board_member->ID; ?>" <?php checked( $attended, false ); ?> value="na" />
          <label for="na-<?php echo $board_member->ID; ?>"> <?php _e( 'N/A' ); ?></label>
        </td>
      </tr> 
    <?php
      }
    ?>
    </table>
    <?php
  }
  
  
   /*
   * Save the meta fields for board events when saving from the edit screen.
   */
  public function save_board_attendance_meta( $board_event_id, $board_event ){
    
    //Check autosave, post type, user caps, nonce
    if( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
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
   * Display our board attendance page.
   */
  public function display_board_attendance_page(){ ?>
    <div class="wrap">
        <?php screen_icon( 'options-general' ); ?>
        <h2><?php _e( 'Board Event Attedance' ); ?></h2>
        <table class="wp-list-table widefat fixed posts" id="board-attendance-table" cellspacing="0">
          <thead>
            <tr>
              <th scope="col" id="name" class="manage-column column-username">Name</th>
              <th scope="col" id="attended" class="manage-column column-attended">Attended</th>
              <th scope="col" id="not-attended" class="manage-column column-not-attended">Didn't Attend</th>
              <th scope="col" id="total" class="manage-column column-total">Total Events</th>
            </tr>
          </thead>
          <tfoot>
            <tr>
              <th scope="col" class="manage-column column-username">Name</th>
              <th scope="col" class="manage-column column-attended">Attended</th>
              <th scope="col" class="manage-column column-not-attended">Didn't Attend</th>
              <th scope="col" class="manage-column column-total">Total Events</th>
            </tr>
          </tfoot>
          <tbody>
            <?php
            global $wi_board_mgmt;
            $board_members = $wi_board_mgmt->board_members;

            //If no board members were found then give them a message.
            if( empty( $board_members ) ){ ?>
                <tr class="no-items">
                  <td class="colspanchange" colspan="5"><?php _e( 'No board members were found.  You should create some users and set their role to "Board Member".' ); ?></td>
                </tr>
            <?php
            }
            
            $alternate = 'alternate';
            foreach( $board_members as $board_member ){
              $attendance = array();
              $attendance['attended'] = $this->get_num_events_attendance( $board_member->ID );
              $attendance['not'] = $this->get_num_events_attendance( $board_member->ID, 0 );
              $attendance['total'] = $attendance['attended'] + $attendance['not'];
              
              $attendance['attended_perc'] = $this->get_percentage( $attendance['attended'], $attendance['total'] );
              $attendance['not_perc'] = $this->get_percentage( $attendance['not'], $attendance['total'] );
              ?>
               <tr class="<?php echo $alternate; ?>">
                 <td class="name column-username">
                   <?php echo get_avatar( $board_member->ID, '44' ); echo '<strong>' . $board_member->display_name . '</strong>'; ?><br />
                   <div class="row-actions">
                     <span class="view">
                       <a href="<?php echo admin_url( 'admin.php?page=nonprofit-board/attendance/member&id=' . $board_member->ID ); ?>">View Detailed Event Attendance</a>
                     </span>
                   </div>
                 </td>
                 <td class="attended column-attended"><?php echo $attendance['attended'] . ' (' . $attendance['attended_perc'] . '%)'; ?></td>
                 <td class="not-attended column-attended"><?php echo $attendance['not'] . ' (' . $attendance['not_perc'] . '%)'; ?></td>
                 <td class="total column-total"><?php echo $attendance['total'] ?></td>
               </tr>
             <?php
             $alternate = ( $alternate == 'alternate' ) ? '' : 'alternate';
             }
        
          ?>
          </tbody>
        </table>
        <p><?php echo $this->get_users_tracking_attendance(); ?></p>
    </div>
  <?php  
  }
  
  
  /*
   * Display the attendance for a specific member.
   */
  public function display_member_attendance_page(){
    if( !isset( $_GET['id'] ) ){ ?>
      <div id="message" class="error">
        <p><?php _e( 'Oops.  You shouldn\'t be on this page right now.' ); ?></p>
      </div>
    <?php 
      return false;
    }
    
    $board_member_id = intval( $_GET['id'] );
    $board_member = get_users( array( 'include' => array( $board_member_id ) ) );
    $board_member = $board_member[0];
    ?>
    <div class="wrap">
        <?php screen_icon( 'options-general' ); ?>
        <h2><?php _e( 'Board Member Attedance: ' ); echo $board_member->display_name; ?></h2>
        <table class="wp-list-table widefat fixed posts" id="board-attendance-table" cellspacing="0">
          <thead>
            <tr>
              <th scope="col" id="event" class="manage-column column-event">Board Event</th>
              <th scope="col" id="attended" class="manage-column column-attended">Attended</th>
              <th scope="col" id="not-attended" class="manage-column column-not-attended">Didn't Attend</th>
            </tr>
          </thead>
          <tfoot>
            <tr>
              <th scope="col" id="event" class="manage-column column-event">Board Event</th>
              <th scope="col" class="manage-column column-attended">Attended</th>
              <th scope="col" class="manage-column column-not-attended">Didn't Attend</th>
            </tr>
          </tfoot>
          <tbody>
            <?php
            
            $attendance_record = $this->get_user_attendance_record( $board_member_id );
            //If no board members were found then give them a message.
            if( empty( $attendance_record ) ){ ?>
                <tr class="no-items">
                  <td class="colspanchange" colspan="5"><?php _e( 'This board member has not had their attendance recorded at any event.' ); ?></td>
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
                   <strong><?php echo get_the_title( $event->post_id ); ?></strong><br />
                   <?php echo $board_event_meta['start_date_time']; ?>
                 </td>
                 <td><?php if( $event->attended == 1 ) _e( 'X' ); ?></td>
                 <td><?php if( $event->attended == 0 ) _e( 'X' ); ?></td>
               </tr>
             <?php
             $alternate = ( $alternate == 'alternate' ) ? '' : 'alternate';
             }
        
          ?>
          </tbody>
        </table>
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
   */
  private function insert_user_attendance( $board_member_id, $board_event_id, $attended ){
    global $wpdb;
    $wpdb->insert(
            $this->table_name,
            array( 'user_id' => $board_member_id, 'post_id' => $board_event_id, 'attended' => $attended ),
            array( '%d', '%d', '%d' ) //All of these should be saved as integers
           );  
  }
  
  
  /*
   * Update a user's attendance in the database.
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
  }
  
  
  /*
   * Remove a user's attendance for one event from the database.
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
  }
  
  
  /*
   * Get percentage of two numbers
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
      return _( 'No one is currently able to track attendance.  A WordPress admin can give a 
        board member permission to do this on the member\'s profile edit page.' );
    }
    else{
      $trackers_string = _( 'The following board members are able to track event attendance: ' );
      $trackers_string .= implode( ', ', $attendance_trackers );
      
      return $trackers_string;
    }
  }
  
  /*
   * Get attendance status for a specific board member.
   * 
   * @return bool|int False if not recorded, 1 for attended, 0 for not attended.
   */
  private function get_user_event_attendance( $board_event_id, $board_member_id ){
    global $wpdb;

    //Check if this user has already had attendance marked for this event.
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
   * Get number of events attended or not attended for a board member.
   * 
   * @param $attendance int 1 for number attending, 0 for not attending.
   */
  private function get_num_events_attendance( $board_member_id, $attended = 1 ){
    global $wpdb;

    $attendance_table = $this->table_name;
    $num_attendance = $wpdb->get_var(
            "
              SELECT COUNT( attended )
              FROM {$attendance_table}
              WHERE user_id = {$board_member_id}
              AND attended = {$attended}
            "
            );  

    return $num_attendance;  
  }
  
  /*
   * Get the entire attendance record for a user sorted by start date of event.
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
                        ORDER BY {$postmeta_table}.meta_value DESC
                        "
                       );
                        
    return $attendance_record;
  }  
}//WI_Board_Attendance