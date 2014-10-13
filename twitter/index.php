<?php
require_once("../includes/core.php");
$objCore = new Core();
$objCore->initSessionInfo();
$objCore->initFormController();

/*
		$myFile = "../log/h.log";
		$fh = fopen($myFile, 'a') or die("can't open file");
		fwrite($fh, "h2");
		fclose($fh);
*/

// todo implement a "purge dm inbox" feature
// retrieve all direct messages, delete

//unset($_SESSION['oauth']);
//session_destroy();
$dirdeep = '../';
$jsextra  = '<script type="text/javascript" language="javascript" src="../javascript/modal.js"></script><script type="text/javascript" language="javascript" src="../javascript/twitterpanel.js"></script><script type="text/javascript" language="javascript" src="../javascript/date.js"></script><script type="text/javascript" language="javascript" src="../javascript/jquery.datePicker.js"></script><script type="text/javascript" language="javascript" src="../javascript/jquery-ui-1.8.14.custom.min.js"></script><script type="text/javascript" language="javascript" src="../javascript/jquery-ui-timepicker-addon.js"></script><script type="text/javascript" language="javascript" src="../javascript/easypaginate.js"></script>';
$cssextra = '<link rel="stylesheet" href="../css/datepicker.css" type="text/css" media="screen" charset="utf-8" /><link rel="stylesheet" href="../css/jquery-ui-1.8.14.custom.css" type="text/css" media="screen" charset="utf-8" />';
$title  = 'Twitter Control Panel';
include("../includes/header.php");

if($objCore->getSessionInfo()->isLoggedIn()) {
	$userdata = $objCore->getUserAccountDetails();
	$twitaccts = $_SESSION['total_twitter_accounts'];
	if($twitaccts < 2) { $acct = 'account'; } else { $acct = 'accounts'; }
	
	// check for get requests to load a certain modal on pageload
	if(isset($_GET['pm'])) {
		if($_GET['pm'] == 'dm-new') {
			echo '<script type="text/javascript">
				    $(document).ready(function () {
						$("a#dmmod").click();
				  });</script>';
		} elseif($_GET['pm'] == 'view-updates') {
			echo '<script type="text/javascript">
				    $(document).ready(function () {
						$("a#vetweet").click();
				  });</script>';
		} elseif($_GET['pm'] == 'del-tweets') {
			echo '<script type="text/javascript">
				    $(document).ready(function () {
						$("a#deltweeto").click();
				  });</script>';
		} elseif($_GET['pm'] == 're-follow') {
			echo '<script type="text/javascript">
				    $(document).ready(function () {
						$("a#refo").click();
				  });</script>';
		} elseif($_GET['pm'] == 'remove-twit-acct') {
			echo '<script type="text/javascript">
				    $(document).ready(function () {
						$("a#yadelid").click();
				  });</script>';
		} elseif($_GET['pm'] == 'purge-dm') {
			echo '<script type="text/javascript">
				    $(document).ready(function () {
						$("a#urge2purge").click();
				  });</script>';
		}
	}

	// calculate the standard height for the modal
	if($twitaccts >= 10) {
		$artwetfinal = 10;
	} else { 
		$artwetfinal = $twitaccts;
	}
	$height = ($artwetfinal * 30) + 250;
	
	echo "<h1>Twitter Control Panel</h1>
		  <p>This control panel handles twitter account functions.<br />
		  You currently have <b>{$_SESSION['total_twitter_accounts']}</b> twitter $acct loaded.</p>";
	echo "<p>Account Creation:
		  <ul>
			<li><a href='../auth.php?authorize=1'>Add a new Twitter Account</a></li>
		  </ul>
		  </p><p>Account Automation:
		  <ul>
			<li><a href='#sch-update' name='modal'>Schedule an Update/Message</a></li>";
	if($_SESSION['premium'] == 0) { // 0 == regular, 1 == premium
		echo "<li><a href='payfull.php'>Direct Message New Followers</a></li>
		  	  <li><a href='payfull.php'>Re-Follow Options</a></li>";
	} else {
		echo "<li><a href='#dm-new' id='dmmod' name='modal'>Direct Message New Followers</a></li>
		  	  <li><a href='#re-follow' id='refo' name='modal'>Re-Follow Options</a></li>";
	}
	
	echo "</ul></p>
		  <p>Account Maintenance:
		  <ul>
			<li><a href='#view-updates' id='vetweet' name='modal'>View/Edit Scheduled Tweets</a></li>
			<li><a href='#view-stat' name='modal'>View Twitter Accounts</a></li>
			<li><a href='#upd-twit' name='modal'>Update Twitter Account</a></li>
			<li><a href='#purge-dm' id='urge2purge' name='modal'>Purge Direct Message Inbox</a></li>
			<li><a href='#del-tweets' id='deltweeto' name='modal'>Delete a Scheduled Tweet</a></li>
			<li><a href='#remove-twit-acct' id='yadelid' name='modal'>Delete a Twitter Account</a></li>
		  </ul></p>";
	echo '<script type="text/javascript" language="javascript">
		    $(function() {
				$(\'#timetest\').datetimepicker({
				dateFormat: $.datepicker.RFC_2822,
				minDate: new Date(),
				timeFormat: \'hh:mm z\',
				timezone: \''.$_SESSION['timezone_offset'].'\',
				showTimezone: false,
				showOn: \'both\',
				buttonImage: \'../images/calendar.png\', 
				buttonImageOnly: true
				});
			});
			$(document).ready(function() {
				d = new Date();
				var year = d.getFullYear();
				var month = d.getMonth() + 1;
				var day = d.getDay();
				var ydate = d.getDate();
				var hours = d.getHours();
				var minutes = d.getMinutes();
				if (minutes < 10) {
					switch(minutes) {
						case 0: minutes = "00"; break;
						case 1: minutes = "01"; break;
						case 2: minutes = "02"; break;
						case 3: minutes = "03"; break;
						case 4: minutes = "04"; break;
						case 5: minutes = "05"; break;
						case 6: minutes = "06"; break;
						case 7: minutes = "07"; break;
						case 8: minutes = "08"; break;
						case 9: minutes = "09"; break;
					}
				}
				
				switch(month) {
					case 1: month = "Jan"; break;
					case 2: month = "Feb"; break;
					case 3: month = "Mar"; break;
					case 4: month = "Apr"; break;
					case 5: month = "May"; break;
					case 6: month = "Jun"; break;
					case 7: month = "Jul"; break;
					case 8: month = "Aug"; break;
					case 9: month = "Sep"; break;
					case 10: month = "Oct"; break;
					case 11: month = "Nov"; break;
					case 12: month = "Dec"; break;
				}
				
				switch(day) {
					case 0: day = "Sun"; break;
					case 1: day = "Mon"; break;
					case 2: day = "Tue"; break;
					case 3: day = "Wed"; break;
					case 4: day = "Thu"; break;
					case 5: day = "Fri"; break;
					case 6: day = "Sat"; break;
					case 7: day = "Sun"; break;
				}
				
				fulldate = day + ", " + ydate + " " + month + " " + year + " " + hours + ":" + minutes + " '.$_SESSION['timezone_offset'].'"
				$(\'.timetest\').val(fulldate);
			});
		</script><style>#boxes #sch-update {
			  height:300px;
			  width:500px; 
			  padding:10px;
			  background-color:#ffffff;
			}
			div.errorimg {
				width:320px;
		    }</style>';
	echo '<div id="boxes"><div id="sch-update" class="window">
		  <h3>Schedule a new Twitter Message</h3>
		  <form name="schedule_new_tweet" id="schedule_new_tweet" action="../includes/corecontroller.php" method="POST">
		  <textarea rows="3" cols="61" id="tweet" name="tweet"></textarea>
		  <br /><br />
		  <table>
			<tr><td><b>Timezone:</b></td><td>'.$userdata['timezone'].' &nbsp;<a href="../editaccount.php">Change</a>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<a href="#" onclick="vieweditsch();">View Tweets</a></td></tr>
			<tr><td><b>Account:</b></td><td><select id="twitteracct" name="twitteracct" class="inplaceError"><option value="choose">Select an Account</option>';
			for($i=0;$i<$twitaccts;$i++) {
			$h = $i+1;
			$twname = 'twitter_name_'.$h;
			$twsnm  = 'twitter_screenname_'.$h;
			//$twfolo = 'followers_count_'.$h;
			//$twfoll = 'friends_count_'.$h;
				echo '<option value="'.$_SESSION[$twsnm].'">'.$_SESSION[$twsnm].'</option>';
			}
	echo '</select></td></tr>
			<tr><td><b>Date & Time:</b></td><td><input name="timetest" id="timetest" class="timetest" size="26" /></td></tr>
		  </table>
		  <input type="hidden" name="schedulenewtweet" value="1" />
		  <input type="hidden" id="tweet_h" name="tweet_h" value="" />
		  <input type="hidden" id="tz" name="tz" value="'.$userdata['timezone'].'" />
		  <br />
		  <a id="_schedule_btt" class="button" href="#">Schedule</a><span id="scaj"></span>&nbsp;<a href="#" class="close">Close</a></form>&nbsp;&nbsp;<span id="schedule_tweet" class="message_success"></span><span id="schedule_tweet_error" class="error"></span>
		  </div></div>';
	echo '<style>#boxes #view-updates {
			  width:500px; 
			  padding:10px;
			  background-color:#ffffff;
			}</style>';
	echo '<div id="boxes"><div id="view-updates" class="window">
		  <h3>View/Edit Scheduled Tweets</h3><span id="origin">';
	echo $objCore->returnScheduledTweets();
	echo '</span>&nbsp;&nbsp;&nbsp;<a href="#" class="close c_fix"/>Close</a>
		  <span id="origin_error" class="error"></span><span id="origin_success_h"></span></div></div>';
	echo '<style>#boxes #view-stat {
			  height:'.$height.'px;
			  width:500px; 
			  padding:10px;
			  background-color:#ffffff;
			}</style>';
	echo '<div id="boxes"><div id="view-stat" class="window">
		  <h3>View Twitter Accounts</h3><div id="edit_title">
		  Twitter Screen Name&nbsp; - Twitter Name&nbsp; - Followers &nbsp; Following</div><ul id="vwtwtpaging">';
			for($i=0;$i<$twitaccts;$i++) {
			$h = $i+1;
			$twname = 'twitter_name_'.$h;
			$twsnm  = 'twitter_screenname_'.$h;
			$twfolo = 'followers_count_'.$h;
			$twfoll = 'friends_count_'.$h;
				echo '<li>'.$_SESSION[$twsnm].'&nbsp; - '.$_SESSION[$twname].'&nbsp; (<b>'.$_SESSION[$twfolo].'</b>)&nbsp; (<b>'.$_SESSION[$twfoll].'</b>)</li>';
			}
	echo  '</ul><br />&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<a href="#"class="close">Close</a></div></div>';
	if($_SESSION['premium'] == 1) { // 0 == regular, 1 == premium
		echo '<style>#boxes #dm-new {
				  height:'.$height.'px;
				  width:500px; 
				  padding:10px;
				  background-color:#ffffff;
				}</style><span id="ad-sc-inject"></span>';
		echo '<div id="boxes"><div id="dm-new" class="window"><h3>Auto Direct Message New Followers</h3><span id="dm_rep">
		     <form name="form_dm_select" id="form_dm_select" action="../includes/corecontroller.php" method="post"><div id="edit_title">Screen Name&nbsp; - &nbsp;DM Reply Status&nbsp;<span class="right">Select:</span></div><ul id="dmpaging">';
			  for($i=0;$i<$twitaccts;$i++) {
				$h = $i+1;
				//$twname = 'twitter_name_'.$h;
				$twsnm  = 'twitter_screenname_'.$h;
				$twfolo = 'followers_count_'.$h;
				$twdmnw = 'dm_new_follow_'.$h;
					echo '<li>'.$_SESSION[$twsnm].'(<b>'.$_SESSION[$twfolo].'</b>)&nbsp; - &nbsp;';
					if($_SESSION[$twdmnw] == 0){echo "Off";}else{echo "On";}
					echo '&nbsp; <input type="radio" name="twit_dm_new_num" class="right" id="twit_dm_new_num" value="'.$h.'" /></li>';
			  }
		echo '</ul><br /><a id="_dm_select_btt" class="button" href="#">Select</a><span id="dmflaj"></span>&nbsp;<a href="#"class="close">Close</a>&nbsp;&nbsp;<span id="dm_select" class="message_success"></span><span id="dm_select_error" class="error"></span><input type="hidden" name="dmselecttwitteraccount" value="1" /></form></span></div></div>';
		echo '<style>#boxes #re-follow {
				  height:'.$height.'px;
				  width:500px; 
				  padding:10px;
				  background-color:#ffffff;
				}</style>';
		echo '<div id="boxes"><div id="re-follow" class="window"><h3>Follow those who follow you</h3>
		     <span id="re_flw"><form name="form_follow_select" id="form_follow_select" action="../includes/corecontroller.php" method="post"><div id="edit_title">Screen Name &nbsp;- &nbsp;Re-follow Status&nbsp;<span class="right">Select:</span></div><ul id="repaging">';
			  for($i=0;$i<$twitaccts;$i++) {
				$h = $i+1;
				//$twname = 'twitter_name_'.$h;
				$twsnm  = 'twitter_screenname_'.$h; 
				$twfolo = 'followers_count_'.$h;
				$twrefl = 're_follow_'.$h;
					echo '<li>'.$_SESSION[$twsnm].'(<b>'.$_SESSION[$twfolo].'</b>)&nbsp; - ';
					if($_SESSION[$twrefl] == 0){echo " Off";}else{echo " On";}
					echo '<input type="radio" name="re_follow_new_num" id="re_follow_new_num" class="right" value="'.$h.'" /></li>';
			  }
		echo '</ul><a id="_refollow_sel_btt" class="button" href="#">Select</a><span id="upaj"></span>&nbsp;<a href="#"class="close">Close</a>&nbsp;&nbsp;<span id="follow_select" class="message_success"></span><span id="follow_select_error" class="error"></span><input type="hidden" name="refollowtwitteraccount" value="1" /></form></span></div></div>';
	}
	echo '<style>#boxes #upd-twit {
			  height:'.$height.'px;
			  width:500px;
			  padding:10px;
			  background-color:#ffffff;
			}</style>';
	echo '<div id="boxes"><div id="upd-twit" class="window">
		  <h3>Select which account you would like to update:</h3>
		  If you would like to update your friends/follower count, or have edited your twitter account and want the changes reflected here.<br /><br />
		  <form name="form_twitter_update" id="form_twitter_update" action="../includes/corecontroller.php" method="post"><ul id="updatepaging">';
			for($i=0;$i<$twitaccts;$i++) {
			$h = $i+1;
			$twname = 'twitter_name_'.$h;
			$twsnm  = 'twitter_screenname_'.$h;
			$twfolo = 'followers_count_'.$h;
				echo '<li>'.$_SESSION[$twsnm].'&nbsp; - '.$_SESSION[$twname].' (<b>'.$_SESSION[$twfolo].'</b>)<input type="radio" name="twit_update_num" id="twit_update_num" class="right" value="'.$h.'" /></li>';
			}
	echo  '</ul><a id="_update_btt" class="button" href="#">Update</a><span id="ukaj"></span>&nbsp;<a href="#"class="close">Close</a>&nbsp;&nbsp;<span id="twitter_update" class="message_success"></span><span id="twitter_update_error" class="error"></span><input type="hidden" name="updatetwitteraccount" value="1" /></form></div></div>';
	echo '<style>#boxes #remove-twit-acct {
			  height:'.$height.'px;
			  width:500px; 
			  padding:10px;
			  background-color:#ffffff;
			}</style>';
	echo '<div id="boxes"><div id="remove-twit-acct" class="window">
		  <h3>Delete a twitter Account</h3>
		  Please select which account you would like to remove from this site.<br />
		  Any automated events for this account will also be eliminated.<br /><br />
		  <span id="del_rep">
		  <form name="form_twitter_remove" id="form_twitter_remove" action="../includes/corecontroller.php" method="post"><ul id="deletepaging">';
			for($i=0;$i<$twitaccts;$i++) {
			$h = $i+1;
			$twname = 'twitter_name_'.$h;
			$twsnm  = 'twitter_screenname_'.$h;
			$twfolo = 'followers_count_'.$h;
				echo '<li>'.$_SESSION[$twsnm].'&nbsp; - '.$_SESSION[$twname].' (<b>'.$_SESSION[$twfolo].'</b>) <input type="radio" name="twit_remove_num" id="twit_remove_num" class="right" value="'.$h.'" /></li>';
			}
	echo  '</ul><a id="_remove_btt" class="button" name="modal" href="#del-confirm">Delete</a><span id="rmaj"></span>&nbsp;<a href="#"class="close">Close</a>&nbsp;&nbsp;<span class="error" id="twitter_remove_error"></span><input type="hidden" name="removetwitteraccount" value="1" /></form></span></div></div>';
	echo '<style>#boxes #purge-dm {
			  height:'.$height.'px;
			  width:500px; 
			  padding:10px;
			  background-color:#ffffff;
			}</style>';
	echo '<div id="boxes"><div id="purge-dm" class="window">
		  <h3>Purge Twitter Direct Messages</h3>
		  <span id="purge_rep"><form name="form_twitter_purge_sel" id="form_twitter_purge_sel" action="../includes/corecontroller.php" method="post"><div id="edit_title">
		  Twitter Screen Name&nbsp; - Twitter Name&nbsp; - Followers - Inbox Purge</div><ul id="frmpgselpaging">';
			for($i=0;$i<$twitaccts;$i++) {
			$h = $i+1;
			$twname = 'twitter_name_'.$h;
			$twsnm  = 'twitter_screenname_'.$h;
			$twfolo = 'followers_count_'.$h;
			$twpurg = 'purge_dm_'.$h;
				echo '<li>'.$_SESSION[$twsnm].'&nbsp; - '.$_SESSION[$twname].'&nbsp; (<b>'.$_SESSION[$twfolo].'</b>)&nbsp;';
				if($_SESSION[$twpurg] == 0){echo "Off";}else{echo "On";}
				echo '&nbsp; <input type="radio" name="twit_dm_pg_num" class="right" id="twit_dm_pg_num" value="'.$h.'" /></li>';
			}
	echo  '</ul><a id="_purge_sel_btt" class="button" href="#">Select</a><span id="pgaj"></span>&nbsp;<a href="#"class="close">Close</a>&nbsp;&nbsp;<span class="error" id="twitter_pg_sel_error"></span><input type="hidden" name="selpurgetwitteraccount" value="1" /></form></span></div></div>';
	echo '<style>#boxes #del-confirm {
			  height:240px;
			  width:500px; 
			  padding:10px;
			  background-color:#ffffff;
			}</style>';
	echo '<div id="boxes"><div id="del-confirm" class="window"><h1>Confirm Delete</h1>Are you sure you want to delete this twitter account?  <br />Any scheduled events or data will be <b>permanently</b> removed along with the account.<br />This action cannot be undone.<br /><br /><a id="_del_confirm_btt" class="button" href="#">Delete</a>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<a href="#" class="close" onclick="retdelacct()">Return to Accounts</a></div></div>';
	echo '<style>#boxes #del-tweets {
			  width:500px; 
			  padding:10px;
			  background-color:#ffffff;
			}</style>';
	echo '<div id="boxes"><div id="del-tweets" class="window">
		  <h3>Delete Scheduled Tweets</h3><span id="dstweet">';
	echo $objCore->returnDeleteScheduledTweets();
	echo '&nbsp;&nbsp;&nbsp;<a href="#" class="close c_fix"/>Close</a>
		  <span id="dstweet_error" class="error"></span><span id="dstweet_success">';
		  if(isset($_SESSION['twit_del_msg'])) { echo $_SESSION['twit_del_msg']; unset($_SESSION['twit_del_msg']); }
	echo '</span></div></span>';
	echo '<div id="mask"></div></div>';
	echo "<pre>";
	print_r($_SESSION);
	echo "</pre>";
}
else {
	header("Location: ../index.php");
}
unset($objCore);
