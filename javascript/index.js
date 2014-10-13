$(document).ready(function() {
	Index.initEventHandlers();
});

var Index = {
	init 				: function(){
	},	
	initEventHandlers 	: function(){
		$("#login_button").click(function(e){
			Index.processSubmit();
		});
		
		$('.inplaceError').each(
				function(i) {
					$(this).focus(function(e){
						$("#loginerror").html("");
					});
				}
		);
		
		$(document).keyup(function(event) { 
			if (event.keyCode == 13) {
				Index.processSubmit();
			} 
		});
	},
	processSubmit		: function(event){
		email = $('#email').val();
		pass  = $('#pass').val();
		
		if ((email == "") || (email == undefined)) {
			$("#loginerror").html("<div class='errorimg'>Email is blank!</div>");
		} else if ((pass == "") || (pass == undefined)) {
			$("#loginerror").html("<div class='errorimg'>Password is blank!</div>");
		} else {
			$('#login').submit();
		}
	}
};	