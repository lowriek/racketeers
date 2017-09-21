   jQuery(function() {
		jQuery( "#accordion" ).accordion({ collapsible: true });
	});

jQuery(document).ready(function() {

	/* sets up all the datepickers on the page */
    jQuery('.datepicker').datepicker({
        dateFormat : 'yy-mm-dd'
    });
      
    jQuery('.eventForm').submit(function (event){
        var errormessage = "";
    	var title = jQuery("input[name='eventTitle']", this).val();
    	var date  = jQuery("input[name='eventDate']", this).val();
						
	    if (title == "enter event title" || title == "") {
			errormessage = "Please enter the event title.  ";
		}
		if (date == "select date" || date == ""){
			errormessage += "Please select a date.";
		}    		

     	if (errormessage != "") {	
     		event.preventDefault();
     		jQuery('#eventError').css("color", "red"); 
     		jQuery('#eventError').text(errormessage);

     	} else {
     		jQuery('#eventError').text("");
     		
     	}
		
    });  
    
});



