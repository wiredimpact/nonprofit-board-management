jQuery(document).ready(function(){
  //JavaScript for Board Events Edit Screen
  var start_date_time = jQuery( '#board_event_details #start-date-time' ),
      end_date_time = jQuery( '#board_event_details #end-date-time' );
  
  //Set the end date & time field to match the start date and time if the end is empty.
  //Only do this when focusing out on start time.
  //TODO Use restrict start and end date at http://trentrichardson.com/examples/timepicker/ to ensure the end date isn't before start date
  start_date_time.datetimepicker({
    dateFormat: "D, MM d, yy",
    timeFormat: "hh:mm tt",
    stepMinute: 5,
    onClose: function(dateText, inst){
      if( end_date_time.val() === '' ){
        end_date_time.val( start_date_time.val() );
      }
    }
  }); 
  
  end_date_time.datetimepicker({
    dateFormat: "D, MM d, yy",
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
  
  //Allow admins to rsvp by giving them the correct capability.
  jQuery( 'input#allow-rsvp' ).click(function(){
    var data = {
      action: 'allow_rsvp',
      security: wi_board_events.allow_rsvp_nonce
     };

    jQuery.post(ajaxurl, data, function( response ) {
      if( response !== '1' ){ //If there's an error
        alert( wi_board_events.error_allow_rsvp ); 
      }
      else{
       //Reload the current page so they can start RSVPing.
       location.reload(true); 
      }
    });
    
    return false;
  });
});

