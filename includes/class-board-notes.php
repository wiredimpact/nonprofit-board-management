<?php
/**
 * WI_Board_Notes allows us to add and delete notes for each board member or
 * board recruit.
 *
 * @author Wired Impact
 */
class WI_Board_Notes {

   /*
    * Initiate the board notes by adding actions for fields and for adding and deleting notes.
    */
   public function __construct() {
    //Add user notes field to each users edit screen
    add_action( 'show_user_profile', array( $this, 'user_notes' ) );
    add_action( 'edit_user_profile', array( $this, 'user_notes' ) );
     
    //Delete user note using AJAX
    add_action( 'wp_ajax_delete_note', array( $this, 'delete_user_note' ) );
    add_action( 'wp_ajax_add_note', array( $this, 'save_user_note' ) );
   }
   
   
    /*
     * Display all the user notes fields and info
     */
    public function user_notes( $user ){ 
      $user_id = $user->ID;
      
      if( user_can( $user_id, 'contain_board_info' ) && current_user_can( 'view_board_content' ) ){ //Only show notes on that can hold board information 
      
      ?>
      <h3>Board Members</h3>

      <table class="form-table">

        <tr>
         <th><label for="note">Add Note</label></th>

         <td>
           <textarea name="note" id="note" rows="5" cols="30" data-user-id="<?php echo $user_id; ?>" /></textarea><input id="add-note" type="submit" class="button secondary-button" value="Add Note" /><br />
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
    private function show_user_notes( $user_id ){
      $notes = get_user_meta( $user_id, 'note' );
      $notes_ordered = array_reverse ( $notes );
      
      foreach( $notes_ordered as $note ){
        $this->create_note_row( $user_id, $note );
      }
    }
    
    
    /*
     * Display a single note in a table row.
     */
    private function create_note_row( $user_id, $note ){ 
      $creator_data = get_userdata( $note['creator_id'] );
      $creator_name = $creator_data->display_name;
      ?>
      <tr data-creator-id="<?php echo $note['creator_id']; ?>"><td>
        <div class="note" data-user-id="<?php echo $user_id; ?>"><?php echo esc_attr( $note['note'] ); ?></div>
        <div class="note-date submitted-on" data-timestamp="<?php echo $note['time']; ?>">Added on <?php echo date('F d, Y \a\t g:ia', $note['time']); ?> by <?php echo $creator_name ?></div>
        <div class="note-delete"><span class="trash"><a href="#">Delete Note</a></span></div>
      </td></tr>
    <?php }
    
    
    /*
     * Save the user note via Ajax.
     */
    public function save_user_note(){
      //Use nonce passed through wp_localize_script for added security.
      check_ajax_referer( 'save_note_nonce', 'security' );
      
      $user_id = intval( $_POST['user_id'] );
      $current_user = wp_get_current_user();
      
      if ( !current_user_can( 'edit_user', $user_id ) ){
        return false;
      }
      
      $safe_note = esc_textarea( $_POST['note'] );
      $creator_id = $current_user->ID;
      $timestamp = current_time( 'timestamp' );
      
      $note_data = array(
          'note' => $safe_note,
          'creator_id' => $creator_id,
          'time' => $timestamp
          );
      
      if( add_user_meta( $user_id, 'note', $note_data ) ){
        //Send back the complete table row to be added to the table.
        $this->create_note_row( $user_id, $note_data );
      }
      else {
        echo 'error';
      }
      
      die(); //Required to avoid errors
    }
    
    
    /*
     * Delete user note using Ajax
     */
    public function delete_user_note(){      
      //Use nonce passed through wp_localize_script for added security.
      check_ajax_referer( 'delete_note_nonce', 'security' );
      
      $user_id = intval( $_POST['user_id'] );
      $meta_key = esc_html( $_POST['meta_key'] );
      $meta_value = array(
          'note' => esc_textarea( $_POST['note'] ),
          'creator_id' => intval( $_POST['creator_id'] ),
          'time' => floatval( $_POST['note_timestamp'] )
          );
      
      if( delete_user_meta( $user_id, $meta_key, $meta_value ) ){
       _e( 'deleted' ); 
      }
      else {
       _e( 'We failed to delete that note.  Please try again.' );
      }
      
      die(); //Required to avoid errors
    }
  
}