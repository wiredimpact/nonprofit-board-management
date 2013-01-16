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
  jQuery( '#the-list #attending, #the-list #not-attending' ).not('.rsvped').click(function(){
    var $this = jQuery(this),
    button_id = $this.attr('id'),
    rsvp = 1,
    post_id = $this.closest('tr').attr('id');
    post_id = parseInt( post_id.replace('post-', '') );
    
    //Make rsvp = 0 if they're not coming.
    if( button_id === 'not-attending' ){
      rsvp = 0;
    }
    
    //Send RSVP via ajax
    var data = {
      action: 'rsvp',
      rsvp: rsvp,
      post_id: post_id,
      security: wi_board_mgmt.save_note_nonce
     };

    jQuery.post(ajaxurl, data, function( response ) {
      if( 'error' === response ){
        alert( wi_board_mgmt.error_deleting_note ); 
      }
      else {
        //Add class of button primary and of rsvped and remove those for the siblings.
        $this.addClass('button-primary active');
        $this.siblings().removeClass('button-primary active'); 
      }
    });
    
    return false;
  });
});

