jQuery(document).ready(function(){
  //Open the Board Management menu on particular pages. 
  if( wi_board_mgmt.expand_board_menu == true ){
    jQuery( 'li.toplevel_page_nonprofit-board' )
          .addClass( 'wp-has-current-submenu wp-menu-open' )
          .removeClass( 'wp-not-current-submenu' )
          .children( '.toplevel_page_nonprofit-board' )
            .addClass( 'wp-has-current-submenu wp-menu-open' )
            .removeClass( 'wp-not-current-submenu' );
  }
  
  
  //JavaScript for Board Events Edit Screen
  var start_date_time = jQuery( '#board_event_details #start-date-time' ),
      end_date_time = jQuery( '#board_event_details #end-date-time' ),
      end_date_time_error = end_date_time.siblings( '.error' );
  
  //Set the end date & time field to match the start date and time if the end is empty.
  //Only do this when focusing out on start time.
  start_date_time.datetimepicker({
    controlType: 'select',
    dateFormat: "D, MM dd, yy",
    timeFormat: "h:mm tt",
    separator: ' @ ',
    stepMinute: 5,
    onClose: function( dateText, inst ) {
      if ( end_date_time.val() != '' ) {
       var test_start_date = start_date_time.datetimepicker( 'getDate' );
       var test_end_date = end_date_time.datetimepicker( 'getDate' );
       if ( test_start_date > test_end_date )
        end_date_time.datetimepicker( 'setDate', test_start_date );
      }
      else {
       end_date_time.val( dateText );
      }
     }
  }); 
  
  end_date_time.datetimepicker({
    controlType: 'select',
    dateFormat: "D, MM dd, yy",
    timeFormat: "h:mm tt",
    separator: ' @ ',
    stepMinute: 5,
    onClose: function( dateText, inst ) {
      if ( start_date_time.val() != '' ) {
       var test_start_date = start_date_time.datetimepicker( 'getDate' );
       var test_end_date = end_date_time.datetimepicker( 'getDate' );
       if ( test_start_date > test_end_date )
        start_date_time.datetimepicker( 'setDate', test_end_date );
      }
      else {
       start_date_time.val( dateText );
      }
     }
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
        post_attending_col.html( wi_board_mgmt.load_spinner_html + response );
        
        //Hide the load spinner
        load_spinner.hide();
      }
    });
    
    return false;
  });
  
  
  //Allow users to read the full description for an event or committee by clicking a more link.
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
  
  
  //Allow users to see all attending for an event by clicking an "x others" link.
  jQuery( '.wp-list-table tr.type-board_events' ).on( "click", ".get-names", function(){
    var $this = jQuery( this ),
        post_id = $this.data( 'id' ),
        table_cell = $this.closest( 'td' ),
        load_spinner = table_cell.find( '.spinner' );
    
    //Show spinner while we handle ajax request.
    load_spinner.show();
    
    var data = {
      action: 'show_all_attendees',
      post_id: post_id,
      security: wi_board_mgmt.see_attendees_nonce
    };
    
    jQuery.post(ajaxurl, data, function( response ) {
        table_cell.html( response );
        load_spinner.hide();
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
  
  
  //Add accordion for Support page
	jQuery('.support-heading').click( function() {

    jQuery('.support-heading').children().html('+ ')
	 	jQuery('.support-content').slideUp('normal');
   
    var heading = jQuery(this),
    content = heading.parent().next();
		if( content.is(':hidden') === true ) {
      
      heading.children().html('- ');
			content.slideDown('normal');
      
		}
		  
	 }); //End click for support
  
  
  //Remove fields from profile edit screen only when editing a board members profile
  //This does not evaluate to true when editing an admin who serves on the board.
  if( wi_board_mgmt.editing_board_member_profile === "1" ){
    jQuery( '#your-profile table:first' ).hide();
    jQuery( '#your-profile h3:first' ).hide();
    jQuery( '#your-profile #url' ).closest( 'tr' ).hide();
    jQuery( '#your-profile h3' ).hide();
  }
  
});