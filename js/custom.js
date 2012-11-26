jQuery(document).ready(function(){  
  jQuery( '.note-delete a' ).click(function(){    
    var $this = jQuery(this),
      parent_row = $this.closest( 'tr' ),
      user_id = parent_row.find( 'div.note' ).attr( 'data-user-id' ),
      note = parent_row.find( 'div.note' ).text(),
      note_timestamp = parent_row.find( '.note-date' ).attr( 'data-timestamp' );
    
    var data = {
      action: 'delete_note',
      user_id: user_id,
      meta_key: 'note',
      note: note,
      note_timestamp: note_timestamp
     };

     jQuery.post(ajaxurl, data, function( response ) {
      if( 'deleted' == response ){
        parent_row.addClass('deleting');
        parent_row.fadeOut( 1000 );  //1 second fade out.
      }
      else {
        alert( response );
      }
     });
     
     return false;
   });
});