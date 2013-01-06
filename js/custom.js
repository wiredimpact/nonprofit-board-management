jQuery(document).ready(function(){  
  
  //Delete note through Ajax
  jQuery( '#notes-list' ).on( 'click', '.note-delete a', function(){    
    var $this = jQuery(this),
      parent_row = $this.closest( 'tr' ),
      user_id = parent_row.find( 'div.note' ).attr( 'data-user-id' ),
      note = parent_row.find( 'div.note' ).text(),
      note_timestamp = parent_row.find( '.note-date' ).attr( 'data-timestamp' ),
      creator_id = parent_row.attr( 'data-creator-id' );
    
    var data = {
      action: 'delete_note',
      user_id: user_id,
      meta_key: 'note',
      note: note,
      note_timestamp: note_timestamp,
      creator_id: creator_id,
      security: wi_board_mgmt.delete_note_nonce
     };

     jQuery.post(ajaxurl, data, function( response ) {
      if( 'deleted' === response ){
        //Remove row from notes-list table
        parent_row.addClass( 'deleting' );
        parent_row.fadeOut( 1000 );  //1 second fade out.
      }
      else {
        alert( response );
      }
     });
     
     return false;
   });
   
   //Add note through Ajax
   jQuery( '#add-note' ).click(function(){    
    var $this = jQuery( this ),
      note_textarea = $this.siblings( '#note' ),
      user_id = note_textarea.attr( 'data-user-id' ),
      note = note_textarea.val();
    
    //If the note textarea is empty then don't save the note.
    if( note == '' ){
      alert( 'You need to add some text for the note first.' );
      return false;
    }
    
    var data = {
      action: 'add_note',
      user_id: user_id,
      note: note,
      security: wi_board_mgmt.save_note_nonce
     };

     jQuery.post(ajaxurl, data, function( response ) {
      if( 'error' === response ){
        alert( wi_board_mgmt.error_deleting_note ); 
      }
      else {
        //Clear text from the textarea
        note_textarea.val('');
        
        //Add new note to the notes list table
        var notes_list = jQuery( '#notes-list' ),
            new_note = jQuery( response );
        
        new_note.hide()
                .prependTo( notes_list )
                .addClass( 'adding' )
                .fadeIn( 1000 , function(){
                  jQuery(this).removeClass( 'adding' );
                });    
      }
     });
     
     return false;
   });
});