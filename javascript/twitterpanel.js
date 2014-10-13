$(document).ready(function() {
	twitterpanel.initEventHandlers();
	$("ul#paging").easyPaginate({step:10,controls:"paging2"});
	$("ul#delpaging").easyPaginate({step:10,controls:"delpaging2"});
	$("ul#dmpaging").easyPaginate({step:10,controls:"dmpaging2"});
	$("ul#repaging").easyPaginate({step:10,controls:"repaging2"});
	$("ul#vwtwtpaging").easyPaginate({step:10,controls:"vwtwtpaging2"});
	$("ul#deletepaging").easyPaginate({step:10,controls:"deletepaging2"});
	$("ul#updatepaging").easyPaginate({step:10,controls:"updatepaging2"});
	$("ul#frmrmpaging").easyPaginate({step:10,controls:"frmrmpaging2"});
	$("ul#frmpgselpaging").easyPaginate({step:10,controls:"frmpgselpaging2"});
});

var twitterpanel = {
	initEventHandlers 	: function(){
		$("#_remove_btt").click(function(e){
			if (undefined === $("input[name='twit_remove_num']:checked").val()) {
				// not selected
				$("#twitter_remove_error").html("<div class='errorimg'>No account selected, please try again!</div>");
			} else {
				$('#remove-twit-acct').hide();
			}
		});
		
		$("#_purge_sel_btt").click(function(e){
			if (undefined === $("input[name='twit_dm_pg_num']:checked").val()) {
				// not selected
				$("#twitter_pg_sel_error").html("<div class='errorimg'>No account selected, please try again!</div>");
			} else {
				twitterpanel.processSubmit("#_purge_sel_btt","#form_twitter_purge_sel","#pgaj","#twitter_pg_sel_error",0);
			}
		});
		
		$("#_edit_btt").click(function(e){
			if (undefined === $("input[name='twit_edit_num']:checked").val()) {
				$("#origin_error").html("<div class='errorimg'>No account selected, please try again!</div>");
			} else {
				twitterpanel.processSubmit("#_edit_btt","#edit_tweet","#edaj","#origin_error","#origin_success_h");
			}
			
		});
		
		$("#_del_confirm_btt").click(function(e){
			$("#del-confirm").hide();
			$("#remove-twit-acct").show();
			twitterpanel.processSubmit("#_remove_btt","#form_twitter_remove","#rmaj","#twitter_remove_error",0);
		});
		
		$("#_delete_btt").click(function(e){
			twitterpanel.processSubmit("#_delete_btt","#delete_tweet","#yabutid","#dstweet_error","#dstweet_success");
		});
		
		$("#_refollow_sel_btt").click(function(e){
			if (undefined === $("input[name='re_follow_new_num']:checked").val()) {
				$("#follow_select_error").html("<div class='errorimg'>No account selected, please try again!</div>");
			} else {
				twitterpanel.processSubmit("#_refollow_sel_btt","#form_follow_select","#upaj","#follow_select_error","#follow_select");
			}
		});
		
		$("#_dm_select_btt").click(function(e){
			if (undefined === $("input[name='twit_dm_new_num']:checked").val()) {
				// not selected
				$("#dm_select_error").html("<div class='errorimg'>No account selected, please try again!</div>");
			} else {
				twitterpanel.processSubmit("#_dm_select_btt","#form_dm_select","#dmflaj","#dm_select_error","#dm_select");
			}
		});
		$("#_update_btt").click(function(e){
			if (undefined === $("input[name='twit_remove_num']:checked").val()) {
				// not selected
				$("#twitter_update_error").html("<div class='errorimg'>No account selected, please try again!</div>");
			} else {
				twitterpanel.processSubmit("#_update_btt","#form_twitter_update","#ukaj","#twitter_update_error","#twitter_update");
			}
		});
		$("#_schedule_btt").click(function(e){
			dt = $('#timetest').val();
			tw = $('#tweet').val();
			ta = $('#twitteracct').val();
			
			if((dt == "") || (dt == undefined)) {
				$("#schedule_tweet_error").html("<div class='errorimg'>Date is blank, please fill in a date!</div>");
			} else if ((ta == "") || (ta == undefined) || (ta == 'choose')) {
				$("#schedule_tweet_error").html("<div class='errorimg'>Select a twitter account before continuing!</div>");
			} else if((tw == "") || (tw == undefined)) {
				$("#schedule_tweet_error").html("<div class='errorimg'>No twitter message entered, please try again!</div>");
			} else {
				ko = new Date(dt);
				ji = ko.getTime();
				
				no = new Date();
				ob = no.getTime();
				
				if(ji < ob) {
					// error in the past
					$("#schedule_tweet_error").html("<div class='errorimg'>You have selected a date in the past, please select a future date!</div>");
					//window.alert('in the past!');
				} else {
					$('#tweet_h').val(ji);
					$('#schedule_tweet').html("");
					twitterpanel.processSubmit("#_schedule_btt","#schedule_new_tweet","#scaj","#schedule_tweet_error","#schedule_tweet");
				}
			}
		});
		$('.inplaceError').each(
				function(i) {
					var $this = $(this)
					$this.focus(function(e){
						$("#"+ $this.attr('id') +"_error").html('');
					});
				}
		);
	},
	processSubmit: function (btn,frm,ldjax,err,suc) {
		$(btn).hide();
		$(err).html('');
		if(suc != 0) {
			$(suc).html('');
		}
		$(ldjax).html('&nbsp;&nbsp;<img class="ajaxload" id="ajaxld" src="../images/ajax-loader.gif" />&nbsp;&nbsp;&nbsp;&nbsp;');
		setTimeout("twitterpanel.formsubmit('" + frm + "')",500);
	},
	formsubmit: function (frm) {
		var url = '../includes/corecontroller.php?ts='+new Date().getTime();
		$.post(url, $(frm).serialize(), twitterpanel.onsubmitcomplete,"json");
	},
	onsubmitcomplete : function(data,textStatus){
		if(textStatus == "success"){
			if(data.result == "1"){
				//sucessful
				$("#" + data.loader).html('');
				$("#" + data.bttn).show();			
				$('#'+data.name).html(data.value);
			}
			else if(data.result == "-2"){
				$("#" + data.loader).html('');
				$("#" + data.bttn).show();		
			}
			else{//errors with form -1
				for(var i=0; i < data.errors.length; i++ ){
					if(data.errors[i].value!="")
						$("#"+data.errors[i].name+'_error').html("<div class='errorimg'>"+data.errors[i].value+"</div>");
				}
				$("#" + data.loader).html('');
				$("#" + data.bttn).show();		
			}		
		}
		else if(textStatus == "error") {
			$("#" + data.bttn).show();		
			$("#" + data.loader).html('');
			alert('There has been an ajax error');
		}
	}
};	

function dm_purge() {
	window.location = "index.php?pm=purge-dm";
}

function autodmacct() {
	window.location = "index.php?pm=dm-new";
}

function retdelacct() {
	window.location = "index.php?pm=remove-twit-acct";
}

function vieweditsch() {
	window.location = "index.php?pm=view-updates";
}

function autorefollow() {
	window.location = "index.php?pm=re-follow";
}

function re_sel() {
	twitterpanel.processSubmit("#_resave_btt","#form_re_edit","#rebody","#re_post_error","#re_success");
}

function dm_sel() {
	rb = $('#dm_body').val();
	if((rb == '' || rb == ' ') && (rh == '1')){
		$("#dm_post_error").html("<div class='errorimg'>Your message is empty, please try again!</div>");
	} else if (undefined === $("input[name='dm_new_num']:checked").val()) {
		$("#dm_post_error").html("<div class='errorimg'>Select an option first!</div>");
	} else {
		twitterpanel.processSubmit("#_dmsave_btt","#form_dm_edit","#dmbody","#dm_post_error","#dm_success");
	}
}

function autodmpgacct() {
	if (undefined === $("input[name='dm_pg_num']:checked").val()) {
		// not selected
		$("#dm_pg_post_error").html("<div class='errorimg'>No account selected, please try again!</div>");
	} else {
		twitterpanel.processSubmit("#_dmpurge_final_btt","#form_dm_purge_confirm","#dbjxh","#dm_pg_post_error","#dm_pg_success","#dm_pg_success");
	}
}

function dmsel_wrap() {
	if (undefined === $("input[name='twit_dm_new_num']:checked").val()) {
		// not selected
		$("#dm_select_error").html("<div class='errorimg'>No account selected, please try again!</div>");
	} else {
		twitterpanel.processSubmit("#_dm_select_btt","#form_dm_select","#dmflaj","#dm_select_error","#dm_select");
	}
}

function edit_tweet() {
	dt = $('#timetest2').val();
	tw = $('#tweet2').val();
	ta = $('#twitteracct2').val();
			
	if((dt == "") || (dt == undefined)) {
		$("#origin_error").html("<div class='errorimg'>Date is blank, please fill in a date!</div>");
	} else if ((ta == "") || (ta == undefined) || (ta == 'choose')) {
		$("#origin_error").html("<div class='errorimg'>Select a twitter account before continuing!</div>");
	} else if((tw == "") || (tw == undefined)) {
		$("#origin_error").html("<div class='errorimg'>No twitter message entered, please try again!</div>");
	} else {
		ko = new Date(dt);
		ji = ko.getTime();
				
		no = new Date();
		ob = no.getTime();
				
		if(ji < ob) {
			// error in the past
			$("#origin_error").html("<div class='errorimg'>You have selected a date in the past, please select a future date!</div>");
			//window.alert('in the past!');
		} else {
			$('#tweet_j').val(ji);
			$('#origin_success_h').html("");
			twitterpanel.processSubmit("#_update_btt2","#update_existing_tweet","#scajh","#origin_error","#origin_success_h");
		}
	}
}