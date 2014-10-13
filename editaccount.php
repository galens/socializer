<?php
require_once("includes/core.php");

$objCore = new Core();
$objCore->initSessionInfo();
$objCore->initFormController();

$jsextra = '<script type="text/javascript" language="javascript" src="javascript/autosuggest/bsn.AutoSuggest_2.1.3.js" charset="utf-8"></script><script type="text/javascript" language="javascript" src="javascript/editaccount.js"></script>';
$title = 'Edit Account';
include("includes/header.php");
if($objCore->getSessionInfo()->isLoggedIn()){
  	$userdata = $objCore->getUserAccountDetails();
  	$arrTimezone = $objCore->setTimezoneSession();
?>
		<div id="reg">
        <h1>Account</h1>
        <div align='center'><div id="editaccountmessage" class="message_success"></div></div>
        <form name="form_edit" id="form_edit" action="" method="" class="editaccount">
        	<fieldset>
	            <legend>Personal Details</legend>
	            <label>First and Last name</label>
	            <input type="text" class="inplaceError" id="flname" name="flname" maxlength="100" value="<?php echo $userdata['flname'];?>"/>
	            <div class="error" id="flname_error"></div>
	            <label>Country</label>
	            <input class="inplaceError" style="width: 140px" type="text" id="country" name="country" value="<?php echo $userdata['country_name'];?>"/>
				<input type="hidden" name="country_code" id="country_code" value="-1"/>
	            <div style="height:25px;" class="error" id="country_error"></div>
	            <select id="timezone" name="timezone" class="inplaceError">
				<?php
				echo "<option value=\"choose\">Select a timezone</option>";
				foreach($arrTimezone as $tz => $value) {
					if($userdata['timezone'] == $tz) {
						echo "<option value=\"".$tz."\" selected=\"selected\">".$tz." ".$value."</option>";
					} else {
						echo "<option value=\"".$tz."\">".$tz." ".$value."</option>";
					}
				}
				?>
				</select>
				<div class="error" id="timezone_error"></div>
			</fieldset>
			<fieldset>
	            <legend>Account Details</legend>
	            <label>E-Mail</label>
	            <input type="text" class="inplaceError" id="email" name="email" maxlength="120" value="<?php echo $userdata['email'];?>"/>
	            <div class="error" id="email_error"></div>
	            
	            <label>Current Password</label>
	            <input type="password" class="inplaceError" id="currpass" name="currpass" maxlength="20" value=""/>
	            <div class="error" id="currpass_error"></div>
	            <label>New Password</label>
	            <input type="password" class="inplaceError" id="pass" name="pass" maxlength="20" value=""/>
	            <div class="error" id="pass_error"></div>
	            <label>Verify New Password</label>
	            <input type="password" class="inplaceError" id="confpass" name="confpass" maxlength="20" value=""/>
	            <div class="error" id="confpass_error"></div><br />
				<a id="_editor_btt" class="button" href="#">Save</a>
				<a href="index.php" class="backlink">Back</a>
				<img class="ajaxload" style="display:none;" id="ajaxld" src="images/ajax-loader.gif" />
            </fieldset>
            <input type="hidden" name="editaccountactionx" value="1"/>
		    
           <p>You can edit your account details here. Altered values will be saved.</p>
        </form>
        </div>
        
        <script type="text/javascript">
			var options_country = {
				script:"php/suggestion.php?json=true&limit=10&field=country&",
				varname:"input",
				json:true,
				shownoresults:false,
				maxresults:10,
				callback: function (obj) { $('#country_code').val(obj.id); }
			};
			var as_json_country = new bsn.AutoSuggest('country', options_country);
		</script>
		</div>
		</body>
</html>
<?php	
}
else{
  	header("Location: index.php");
}
unset($objCore);
?>