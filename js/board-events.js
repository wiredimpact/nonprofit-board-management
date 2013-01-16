//JavaScript for Board Events Edit Screen
jQuery(document).ready(function(){
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
});

