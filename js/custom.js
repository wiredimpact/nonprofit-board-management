jQuery(document).ready(function(){
  //JavaScript for Board Events Edit Screen
  var start_date_time = jQuery( '#board_event_details #start-date-time' ),
      end_date_time = jQuery( '#board_event_details #end-date-time' );
  
  //Set the end date & time field to match the start date and time if the end is empty.
  //Only do this when focusing out on start time.
  //TODO Use restrict start and end date at http://trentrichardson.com/examples/timepicker/ to ensure the end date isn't before start date
  start_date_time.datetimepicker({
    controlType: 'select',
    dateFormat: "D, MM dd, yy",
    timeFormat: "hh:mm tt",
    stepMinute: 5,
    onClose: function(dateText, inst){
      if( end_date_time.val() === '' ){
        end_date_time.val( start_date_time.val() );
      }
    }
  }); 
  
  end_date_time.datetimepicker({
    controlType: 'select',
    dateFormat: "D, MM dd, yy",
    timeFormat: "hh:mm tt",
    stepMinute: 5
  });
  
  
  //JS for RSVPing to an Event
  jQuery( '#the-list #attending, #the-list #not-attending' ).click(function(){
    var $this = jQuery(this),
    button_id = $this.attr('id'),
    rsvp = 1,
    post_row = $this.closest('tr'),
    post_attending_col = post_row.find( 'td.attending' ),
    load_spinner = $this.siblings( '.spinner' ),
    post_id = post_row.attr('id');
    post_id = parseInt( post_id.replace('post-', '') );
    
    //If they've already RSPVed for this then don't continue.
    if( $this.hasClass( 'active' ) ){
      return false;
    }
    
    //Make rsvp = 0 if they're not coming.
    if( button_id === 'not-attending' ){
      rsvp = 0;
    }
    
    //Show spinner while we handle ajax request.
    load_spinner.show();
    
    //Send RSVP via ajax
    var data = {
      action: 'rsvp',
      rsvp: rsvp,
      post_id: post_id,
      security: wi_board_events.save_rsvp_nonce
     };

    jQuery.post(ajaxurl, data, function( response ) {
      if ( response !== '0' ) { //If we made a db change
        //Add class of button primary and of rsvped and remove those for the siblings.
        $this.addClass('button-primary active');
        $this.siblings().removeClass('button-primary active'); 
        
        //Put the new list of who's coming in the attending column.
        post_attending_col.html( response );
        
        //Hide the load spinner
        load_spinner.hide();
      }
    });
    
    return false;
  });
  
  
  //Allow users to read the full description for an event by clicking a more link.
  jQuery( '.wp-list-table tr.type-board_events .more-desc, .wp-list-table tr.type-board_committees .more-desc' ).click(function(){
    var $this = jQuery( this ),
        post_id = $this.data( 'id' ),
        table_cell = $this.closest( 'td' ),
        load_spinner = table_cell.find( '.spinner' );
    
    //Show spinner while we handle ajax request.
    load_spinner.show();
    
    var data = {
      action: 'get_full_description',
      post_id: post_id,
      security: wi_board_mgmt.get_description_nonce
    };
    
    jQuery.post(ajaxurl, data, function( response ) {
      if( response !== '-1' ){ 
        table_cell.html( response );
        load_spinner.hide();
      }
      else{ //If there's an error
       alert( wi_board_mgmt.error_get_description ); 
      }
    });
    
    return false;
  });

  
  //Allow admins to serve on the board by giving them the correct capability.
  jQuery( 'input#allow-board-serve' ).click(function(){
    var data = {
      action: 'allow_user_to_serve',
      security: wi_board_mgmt.allow_serve_nonce
     };

    jQuery.post(ajaxurl, data, function( response ) {
      if( response !== '1' ){ //If there's an error
        alert( wi_board_mgmt.error_allow_serve ); 
      }
      else{
       //Reload the current page so they can start serving.
       location.reload( true ); 
      }
    });
    
    return false;
  });
});

