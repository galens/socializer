<?php
require_once("sessioninfo.php");
require_once("dbcontroller.php");
require_once("formcontroller.php");
require_once("mailer.php");
require_once("recaptchalib.php");
require_once("tmhOAuth.php");
require_once("tmhUtilities.php");

class Core{
	
	private $url;          				/* The page url current being viewed */
	private $referrer;     				/* Last recorded site page viewed */
	
	private $sessioninfo;				/* The session object */		
	private $dbcontroller;				/* The database object */
	private $formcontroller;			/* The form object - holds form submited values and errors*/
	private $mailer;					/* The mail object - sends mails ... */
	
	public function __construct() {
		session_start();
	}
	
	public function __destruct() {
		unset($this->sessioninfo);
		unset($this->dbcontroller);
		unset($this->formcontroller);
		unset($this->mailer);
	}
	
	/*
	 * starts the sessioninfo object and the dbcontroller and checks if user is logged in
	* */
	public function initSessionInfo() {
		
		$this->sessioninfo 	= new SessionInfo();
		$this->dbcontroller = new DBController();
		
		$cl = $this->checkLogin(); 
		$this->sessioninfo->setLoggedIn($cl);
		if(!$cl){
			$this->sessioninfo->setUserName(GUEST_NAME);
			$this->setSessionVariable('username',GUEST_NAME);
		}

		if(isset($_SESSION['url'])){
			$this->referrer = $_SESSION['url'];
		}else{
			$this->referrer = "/";
		}
		$this->url = $_SERVER['PHP_SELF'];
		$this->setSessionVariable('url',$_SERVER['PHP_SELF']);
	}
	
	/*
	 * starts the formcontroller object
	 * */
	public function initFormController() {
		$this->formcontroller = new FormController();
	}
	
	/*
	 * starts the mailer object
	 * */
	public function initMailerService() {
		$this->mailer 	= new Mailer();
	}	
	
	/* 
	 * Based on what comes in the POST, it triggers the right process function.
	 * */
	public function dispatchAction() {
		
		if(isset($_POST['loginaction'])){
			$this->processLogin();
		}
		else if(isset($_POST['processdmfinal'])) {
			$this->processDMPurgeFinal();
		}
		else if(isset($_POST['selpurgetwitteraccount'])) {
			$this->processSelectPurgeDM();
		}
		else if(isset($_POST['removetwitteraccount'])) {
			$this->removeTwitterAccount();
		}
		else if(isset($_POST['processdmmessage'])) {
			$this->processDMForm();
		}
		else if(isset($_POST['dmselecttwitteraccount'])) {
			$this->processDMSelect();
		}
		else if(isset($_POST['updateexistingtweet'])) {
			$this->processUpdateTweet();
		}
		else if(isset($_POST['deletetweet'])) {
			$this->processDeleteTweet();
		}
		else if(isset($_POST['edittweet'])) {
			$this->processEditTweet();
		}
		else if(isset($_POST['processrefollowopt'])) {
			$this->processRefollowPut();
		}
		else if(isset($_POST['refollowtwitteraccount'])) {
			$this->processRefollowSel();
		}
		else if(isset($_POST['schedulenewtweet'])) {
			$this->processNewTweet();
		}
		else if(isset($_POST['updatetwitteraccount'])) {
			if(isset($_POST['twit_update_num'])) {
				if(is_numeric($_POST['twit_update_num'])) { $num = $_POST['twit_update_num']; }
			} else {
				$num = 1;
			}
			$this->processUpdateTwitter($num);
		}
		else if(isset($_POST['registeractionx'])){
			$retuserkey = $this->processRegisterx();
		}
		else if(isset($_POST['forgetpasswordaction'])){
			$this->processForgotPassword();
		}
		else if(isset($_POST['resetpasswordaction'])){
			$this->processResetPassword();
		}
		else if(isset($_GET['logoutaction'])){
			$this->processLogout();
		}
		else if(isset($_POST['editaccountactionx'])){
			$retuserkey = $this->processEditAccountx();
		}
		else if(isset($_GET['mapdata'])){
			$this->processMapRequest();
		}
		else if(isset($_POST['adminopactionx'])){
			$this->processAdminOperation();
		}
		/* Should not get here, which means user is viewing this page by mistake and therefore is redirected */
		else{
			header("Location: ../index.php");
		}
	}

	/*
	 * checks if user is logged in (including if the user set the remember me feature)
	 * */
	public function checkLogin() {
		/* if remember me feature activated (cookies set) */
		if(isset($_COOKIE['cookname']) && isset($_COOKIE['cookid'])){
			$this->sessioninfo->setUserName($_COOKIE['cookname']); 
			$this->setSessionVariable('username',$_COOKIE['cookname']);
			
			$this->sessioninfo->setUserId($_COOKIE['cookid']);
			$this->setSessionVariable('userid',$_COOKIE['cookid']);
		}

		if(isset($_SESSION['username']) && isset($_SESSION['userid']) && $_SESSION['username'] != GUEST_NAME){
			/* Confirm that username and userid are valid */
			if($this->dbcontroller->confirmUserID($_SESSION['username'], $_SESSION['userid']) != 1){
				/* Variables are incorrect, user not logged in */
				$this->unsetSessionVariable('username');
				$this->unsetSessionVariable('userid');
				return false;
			}

			/* User is logged in, set class variables */
			$this->sessioninfo->setUserInfo($this->dbcontroller->dbgetUserInfo($_SESSION['username']));
			$this->sessioninfo->setUserName($this->sessioninfo->getUserInfo('pk_user'));
			$this->sessioninfo->setUserId($this->sessioninfo->getUserInfo('usr_userid'));
			$this->sessioninfo->setUserKey($this->sessioninfo->getUserInfo('pk_user'));
			
			/* Set twitter data if already in db */
			$this->loadTwitterObject();
			
			/* set timezone session, default, and offset */
			$this->setSessionVariable('timezone',$this->sessioninfo->getUserInfo('timezone'));
			$this->setTimezoneSession();
			date_default_timezone_set($this->sessioninfo->getUserInfo('timezone'));
			
			/* is user a premium (paid) or free user */
			$this->setSessionVariable('premium',$this->sessioninfo->getUserInfo('premium'));
			return true;
		}
		else{ 				/* User not logged in */
			return false;
		}	
	}
	
	/*
	 * processes the login action
	 * */
	public function processLogin() {
		/* Login attempt */	  
		$retval = $this->__login($_POST['email'], $_POST['pass'],isset($_POST['remember']));      
		/* Login successful */
		if($retval){
			header("Location: ".$this->referrer);
		}
		/* Login failed */
		else{
			$this->setSessionVariable('value_array',$_POST);
			$this->setSessionVariable('error_array',$this->formcontroller->getErrorArray());
			header("Location: ".$this->referrer);
		}
	}
	
	/*
	 * process when user clicks the forgot password link and 
	 * types his email for receiving a mail with instructions
	 * to reset the password 
	 */
	public function processForgotPassword() {
		$email = mb_strtolower($_POST['email']);
		
		//validations:
		//1 - if email typed
		//2 - if email is valid
		//3 - if email exists in db
		
		$field = "email";  //Use field name for email
	  
		if(!$email || mb_strlen($email = trim($email)) == 0){
			$this->formcontroller->setError($field, "Email not entered");
		}
		else{
			/* Check if valid email address */
			$regex = "^[_+a-z0-9-]+(\.[_+a-z0-9-]+)*"
                 ."@[a-z0-9-]+(\.[a-z0-9-]{1,})*"
                 ."\.([a-z]{2,}){1}$";
			if(!mb_eregi($regex,$email)){
				$this->formcontroller->setError($field, "Invalid Email");
			}
			else if(mb_strlen(trim($email)) > 120){
				$this->formcontroller->setError($field, "Email too big");
			}
			/* Check if email is already in use */
			else if(!$this->dbcontroller->dbemailTaken($email)){
				$this->formcontroller->setError($field, "Email does not exists in the system");
			}
		}
		
		if($this->formcontroller->formCountErrors() > 0){
			$json = array(
				"result" => -1, 
				"errors" => array(
								array("name" => "email","value" => $this->formcontroller->error_value("email"))
								)
				);
							
			$encoded = json_encode($json);
			echo $encoded;
			unset($encoded);
		}
		else{
			$this->initMailerService();
			//generates an hash and inserts in table users for the user with email: $email 
			$time = time();
			$hash = sha1($email.supersecret_hash_padding.supersecret_hash_padding_2.$time.YAS);
			$retval = $this->dbcontroller->updateUserFieldEmail($email,"usr_resetpassword_hash",$hash);
			//then sends an email with that hash for that email!			
			$message_subject="reset password";
			$encoded_email = urlencode($email);
			$message_body="click here ".RESETPASSWORDLINK."?c=$hash&email=$encoded_email"; 
			$headers="From: ".EMAIL_FROM_NAME." <".EMAIL_FROM_ADDR.">";
			$this->mailer->sendMail($email,$message_subject,$message_body,$headers);
			$json = array("result" => 1); 
			$encoded = json_encode($json);
			echo $encoded;
			unset($encoded);
		}
	}
	
	/*
	 * user clicked on a link sent to his email account for reseting the password.
	 * this link contains an hash and his email. checks if the hash
	 * is associated to this email in the user table 
	 * */
	public function confirmResetPasswordData($email,$hash) {
		$email = urldecode($email);
		$retval = $this->dbcontroller->dbconfirmResetPasswordHash($email,$hash);
		return $retval;
	}
	
	/* 
	 * user types new password for the reset process
	 * */ 
	public function processResetPassword() {
		//make validations:
		$pass1 = mb_strtolower($_POST['password']);
		$pass2 = mb_strtolower($_POST['password2']);
		
		$field = "password";
		if(!$pass1){
			$this->formcontroller->setError($field, "Password not entered");
		}
		elseif(mb_strlen(trim($pass1)) < 8){
			$this->formcontroller->setError($field, "8 characters necessary");
		}
		elseif(mb_strlen(trim($pass1)) > 20){
			$this->formcontroller->setError($field, "Password too big");
		}
		elseif (mb_eregi("^((root)|(bin)|(daemon)|(adm)|(lp)|(sync)|(shutdown)|
(halt)|(mail)|(news)|(uucp)|(operator)|(games)|(mysql)|
(httpd)|(nobody)|(dummy)|(www)|(cvs)|(shell)|(ftp)|(irc)|
(debian)|(ns)|(download))$", $pass1))
            $this->formcontroller->setError($field, "Password not allowed");
		elseif(!$pass2){
			$this->formcontroller->setError($field, "Second Password not entered");
		}
		elseif($pass2!=$pass1){
				$this->formcontroller->setError($field, "Passwords don't match");
		}
		
		if($this->formcontroller->formCountErrors() > 0){
			$json = array(
				"result" => -1, 
				"errors" => array(
								array("name" => "password","value" => $this->formcontroller->error_value("password"))
								)
				);
							
			$encoded = json_encode($json);
			echo $encoded;
			unset($encoded);
		}
		else{
			//change password:
			$email = $_SESSION['emailreset'];
			$this->dbcontroller->updateUserFieldEmail($email,'password',sha1($_POST['password']).YAS);
			$this->unsetSessionVariable('emailreset');
			$json = array("result" => 1); 
			$encoded = json_encode($json);
			echo $encoded;
			unset($encoded);
		}	
	}
	
	/*
	 * user loggs in 
	 * */
	public function __login($email, $pass, $subremember) {
		$field = "email";
		if(!$email || strlen($email = trim($email)) == 0){
			$this->formcontroller->setError($field, "Email not entered");
			return false;
		}
		else{		
			/* Check if valid email address */
			$regex = "^[_+a-z0-9-]+(\.[_+a-z0-9-]+)*"
				   ."@[a-z0-9-]+(\.[a-z0-9-]{1,})*"
				   ."\.([a-z]{2,}){1}$";
			if(!mb_eregi($regex,$email)){
				$this->formcontroller->setError($field, "Invalid Email");
				return false;
			}
			else if(mb_strlen(trim($email)) > 120){
				$this->formcontroller->setError($field, "Email too big");
				return false;
			}
		}
		
		/* Password error checking */
		$field = "pass";
		if(!$pass){
			$this->formcontroller->setError($field, "Password not entered");
			return false;
		}
		elseif(mb_strlen(trim($pass)) > 20){
			$this->formcontroller->setError($field, "Password too big");
			return false;
		}
		elseif (mb_eregi("^((root)|(bin)|(daemon)|(adm)|(lp)|(sync)|(shutdown)|
		(halt)|(mail)|(news)|(uucp)|(operator)|(games)|(mysql)|(httpd)|(nobody)|
		(dummy)|(www)|(cvs)|(shell)|(ftp)|(irc)|(debian)|(ns)|(download))$", $pass)){
			$this->formcontroller->setError($field, "Password not allowed");
			return false;
		}
	  
	  
		/* Return if form errors exist */
		if($this->formcontroller->formCountErrors() > 0){
			return false;
		}

		/* Checks that username is in database and password is correct */
		//$email = stripslashes($email);
		
		$result = $this->dbcontroller->confirmUserPass($email, sha1($pass.YAS));

		/* Check error codes */
		if($result == -1){
			$field = "email";
			$this->formcontroller->setError($field, "Email not found");
		}
		else if($result == -2){
			$field = "pass";
			$this->formcontroller->setError($field, "Invalid password");
			return false;
		}
		else{
			$result2 = $this->dbcontroller->is_confirmed($email);
			if($result2 == -1){
				$field = "email";
				$this->formcontroller->setError($field, "Not yet confirmed via email");
				return false;
			}
			$result3 = $this->dbcontroller->is_blocked($email);
			if($result3 == 1){
				$field = "email";
				$this->formcontroller->setError($field, "Your account is blocked");
				return false;
			}
		}
		/* Return if form errors exist */
		if($this->formcontroller->formCountErrors() > 0) {
			return false;
		}
		/* Username and password correct, register session variables */
		$this->sessioninfo->setUserInfo($this->dbcontroller->dbgetUserInfoEmail($email));
		
		$this->sessioninfo->setUserName($this->sessioninfo->getUserInfo('pk_user'));
		$this->setSessionVariable('username',$this->sessioninfo->getUserInfo('pk_user'));
	    $this->sessioninfo->setUserKey($this->sessioninfo->getUserInfo('pk_user'));
		
		$rid = generateRandID();
		$this->setSessionVariable('userid',$rid);
		$this->sessioninfo->setUserId($rid);
		
		/* Insert userid into database and update active users table */
		$this->dbcontroller->updateUserField($this->sessioninfo->getUserKey(), "usr_userid", $this->sessioninfo->getUserId());
		/* Increment number of logins */
		$this->dbcontroller->incrementLogins($this->sessioninfo->getUserKey());
		
		// load twitter session objects
		$this->loadTwitterObject();
		
		// load session and default timezone
		$this->setSessionVariable('timezone',$this->sessioninfo->getUserInfo('timezone'));		
		$this->setTimezoneSession();
		date_default_timezone_set($this->sessioninfo->getUserInfo('timezone'));
		
		/* is user a premium (paid) or free user */
		$this->setSessionVariable('premium',$this->sessioninfo->getUserInfo('premium'));
		
		/**
		* This is the cool part: the user has requested that we remember that
        * he's logged in, so we set two cookies. One to hold his username,
        * and one to hold his random value userid. It expires by the time
        * specified in constants.php. Now, next time he comes to our site, we will
        * log him in automatically, but only if he didn't log out before he left.
        */
		if($subremember){
			setcookie("cookname", $this->sessioninfo->getUserKey(), time()+COOKIE_EXPIRE, COOKIE_PATH);
			setcookie("cookid",   $this->sessioninfo->getUserId(),   time()+COOKIE_EXPIRE, COOKIE_PATH);
		}
		
		/* Login completed successfully */
		return true;
	}	
	
	/*
	 * processes the logout action
	 * */
	public function processLogout() {
		$retval = $this->__logout();
		header("Location: ../index.php");
	}
	
	/*
	 * user loggs out
	 * */
	public function __logout() {
		/* Delete cookies - the time must be in the past, so just negate what you added when creating the cookie */
		if(isset($_COOKIE['cookname']) && isset($_COOKIE['cookid'])){
			setcookie("cookname", "", time()-COOKIE_EXPIRE, COOKIE_PATH);
			setcookie("cookid",   "", time()-COOKIE_EXPIRE, COOKIE_PATH);
		}
				
		$this->unsetSessionVariable('username');
		$this->unsetSessionVariable('userid');
	  
		$this->sessioninfo->setLoggedIn(false);
		$this->dbcontroller->disconnect();
		$this->sessioninfo->setUserName(GUEST_NAME);
		
		// destroy all twitter session data
		$iter = $_SESSION['total_twitter_accounts'];
		for($i=0; $i < $iter; $i++) {
			$iterp = $i + 1;
			$this->unsetTwitterSession($iterp);
		}
		$this->unsetSessionVariable('twitter_exists');
		$this->unsetSessionVariable('timezone_offset');
		$this->unsetSessionVariable('timezone');
		$this->unsetSessionVariable('total_twitter_accounts');
	}
	
	
	/*
	 * processes the register action - this action starts
	 * on an ajax call
	 * */
	public function processRegisterx() {
		/* Convert username to all lowercase (by option) */
		if(ALL_LOWERCASE){
			$_POST['email'] = mb_strtolower($_POST['email']);
		}
	  
		/* Registration attempt */
		$retuserkey = $this->__register( $_POST['email'],$_POST['pass'],$_POST['flname'],$_POST['country'],$_POST['country_code'],$_POST['confemail'],$_POST['confpass'],$_POST['timezone']); 
		/* Registration Successful */
		if($retuserkey > 0){
			$json = array("result" => 1); 
			$encoded = json_encode($json);
			echo $encoded;
			unset($encoded);
		}
		/* Error found with form */
		else if($retuserkey == -1){
			
			$json = array(
				"result" => -1, 
				"errors" => array(
								array("name" => "email","value" => $this->formcontroller->error_value("email")),
								array("name" => "pass","value" => $this->formcontroller->error_value("pass")),
								array("name" => "flname","value" => $this->formcontroller->error_value("flname")),
								array("name" => "recaptcha_response_field","value" => $this->formcontroller->error_value("recaptcha_response_field")),
								array("name" => "country","value" => $this->formcontroller->error_value("country")),
								array("name" => "confemail","value" => $this->formcontroller->error_value("confemail")),
								array("name" => "confpass","value" => $this->formcontroller->error_value("confpass")),
								array("name" => "timezone","value" => $this->formcontroller->error_value("timezone"))
								)
				);
							
			$encoded = json_encode($json);
			echo $encoded;
			unset($encoded);		
		}
		/* Registration attempt failed */
		else if($retuserkey == -2){
			$json = array("result" => -2);
			$encoded = json_encode($json);
			echo $encoded;
			unset($encoded);	
		}
		if($retuserkey > 0) return $retuserkey;return -1;
	}
	
	/*
	* registers a user.
	* returns error code or the user key if success
	*/
	public function __register($email, $pass, $flname, $country, $country_code, $confemail, $confpass, $timezone) {
				
		/******Email error checking ******/
		$field = "email";  //Use field name for email
	  
		if(!$email || mb_strlen($email = trim($email)) == 0){
			$this->formcontroller->setError($field, "Email not entered");
		}
		else{
			/* Check if valid email address */
			$regex = "^[_+a-z0-9-]+(\.[_+a-z0-9-]+)*"
                 ."@[a-z0-9-]+(\.[a-z0-9-]{1,})*"
                 ."\.([a-z]{2,}){1}$";
			if(!mb_eregi($regex,$email)){
				$this->formcontroller->setError($field, "Invalid Email");
			}
			else if(mb_strlen(trim($email)) > 120){
				$this->formcontroller->setError($field, "Email too big");
			}
			/* Check if email is already in use */
			else if($this->dbcontroller->dbemailTaken($email)){
				$this->formcontroller->setError($field, "Email already in use");
			}

		}	  

		/********* Email Confirm error checking*******/
		if(REPEAT_EMAIL){
			$field = "confemail";  //Use field name for confemail
			if(!$confemail){
				$this->formcontroller->setError($field, "Email not entered");
			}
			if($confemail!=$email){
				$this->formcontroller->setError($field, "Emails don't match");
			}  
		}
		/**** Password error checking*****/
		$field = "pass";  //Use field name for password
		if(!$pass){
			$this->formcontroller->setError($field, "Password not entered");
		}
		else{
			if(mb_strlen(trim($pass)) < 8){
				$this->formcontroller->setError($field, "8 characters necessary");
			}
			else if(mb_strlen(trim($pass)) > 20){
				$this->formcontroller->setError($field, "Password too big");
			}
			else if (mb_eregi("^((root)|(bin)|(daemon)|(adm)|(lp)|(sync)|(shutdown)|
(halt)|(mail)|(news)|(uucp)|(operator)|(games)|(mysql)|
(httpd)|(nobody)|(dummy)|(www)|(cvs)|(shell)|(ftp)|(irc)|
(debian)|(ns)|(download))$", $pass))
            $this->formcontroller->setError($field, "Password not allowed");
		}
		
		/********* Password Confirm error checking*******/
		if(REPEAT_PASSWORD){
			$field = "confpass";  //Use field name for confpassword
			if(!$confpass){
				$this->formcontroller->setError($field, "Password not entered");
			}
			if($confpass!=$pass){
				$this->formcontroller->setError($field, "Passwords don't match");
			}  
		}
		/***************************** First and Last Name error checking *************************/
		$field = "flname";  
		
		if(!$flname || mb_strlen($flname = trim($flname)) == 0){
			$this->formcontroller->setError($field, "Name not entered");
		}
		else if(!mb_eregi("^[[:alpha:] ]*$", $flname))
			$this->formcontroller->setError($field, "Name invalid");	
		else if (mb_eregi("^((root)|(bin)|(daemon)|(adm)|(lp)|(sync)|(shutdown)|
(halt)|(mail)|(news)|(uucp)|(operator)|(games)|(mysql)|
(httpd)|(nobody)|(dummy)|(www)|(cvs)|(shell)|(ftp)|(irc)|
(debian)|(ns)|(download))$", $flname))
			$this->formcontroller->setError($field, "Name not allowed");
		else if(mb_strlen(trim($flname)) > 100){
				$this->formcontroller->setError($field, "Name too big");
		}
		
		$flname = html_entity_decode($flname,ENT_NOQUOTES, 'UTF-8') ;
		
		/******************************** Captcha error checking *************************/
		$field = "recaptcha_response_field"; 		
		$privatekey = PRIVATEKEY;
		$resp = recaptcha_check_answer ($privatekey,
		                                $_SERVER["REMOTE_ADDR"],
		                                $_POST["recaptcha_challenge_field"],
		                                $_POST["recaptcha_response_field"]);
		
		if (!$resp->is_valid) {
		  $this->formcontroller->setError($field, "please type again");	
		  //die ("The reCAPTCHA wasn't entered correctly. Go back and try it again." . //   "(reCAPTCHA said: " . $resp->error . ")");
		}
		
		/******************************** Country error checking *************************/	
		$field = "country"; 
		if(!$country || mb_strlen($country = trim($country)) == 0){
			$this->formcontroller->setError($field, "Country not entered");
		}
		else{
			//if the code of the country that is posted is different than -1 (the user selected a country from the list):
			if($country_code == -1){ 
				//the user selected a country but it was not one of the list:
				//check if the country typed by the user exists in table of countries(lower case both)
				//if so inserts that as the country of the user otherwise gives back an error saying that the user has to select 
				//a country from the list
				$db_country_code = $this->dbcontroller->dbexistsCountry($country);
				if($db_country_code == null){
					$this->formcontroller->setError($field, "Please choose a country from the list");
				}
				else $country_code = $db_country_code;
				
			}
		}	
		
		/******************************** timezone error checking *************************/	
		$field = "timezone";
		if(mb_eregi("/^\w+$/", $timezone)) {
			$this->formcontroller->setError($field, "timezone is invalid");
		} elseif($timezone == 'choose' || $timezone == 'disabled') {
			$this->formcontroller->setError($field, "please select a timezone!");
		}
			
		/* Errors exist, have user correct them */
		if($this->formcontroller->formCountErrors() > 0){
			return -1;  //Errors with form
		}
		/* No errors, add the new account to the db*/
		else{
			
			$hash = sha1($email.supersecret_hash_padding);
			
	        if(($retuserkey = $this->dbcontroller->dbregister($email, sha1($pass.YAS), $flname, $hash, $country_code, $timezone)) > 0){	
				//if user was successfuly registered, lets send an email where he can activate his account!
				$this->mailer->sendForReg($email,$hash,$flname);
				return $retuserkey;  //New user added succesfully
			}
			else{
				return -2;  //Registration attempt failed
			}
	  }
	}

	/*
	 * processes the editaccount action - this action starts
	 * on an ajax call
	 * */
	public function processEditAccountx() {
		/* Convert username to all lowercase (by option) */
		if(ALL_LOWERCASE){
			$_POST['email'] = mb_strtolower($_POST['email']);
		}
	  
		/* Edit Account attempt */
		$ret = $this->__editaccount( $_POST['email'],$_POST['flname'],$_POST['country'],$_POST['country_code'],$_POST['currpass'],$_POST['pass'],$_POST['confpass'],$_POST['timezone']);      
		/* Edit Account Successful */
		if($ret > 0){
			$json = array("result" => 1); 
			$encoded = json_encode($json);
			echo $encoded;
			unset($encoded);
		}
		/* Error found with form */
		else if($ret == -1){
			
			$json = array(
				"result" => -1, 
				"errors" => array(
								array("name" => "email","value" => $this->formcontroller->error_value("email")),
								array("name" => "currpass","value" => $this->formcontroller->error_value("currpass")),
								array("name" => "flname","value" => $this->formcontroller->error_value("flname")),
								array("name" => "country","value" => $this->formcontroller->error_value("country")),
								array("name" => "pass","value" => $this->formcontroller->error_value("pass")),
								array("name" => "confpass","value" => $this->formcontroller->error_value("confpass")),
								array("name" => "timezone","value" => $this->formcontroller->error_value("timezone"))
								)
				);
							
			$encoded = json_encode($json);
			echo $encoded;
			unset($encoded);		
		}
		/* Edit Account attempt failed */
		else if($ret == -2){
			$json = array("result" => -2);
			$encoded = json_encode($json);
			echo $encoded;
			unset($encoded);	
		}
		if($ret > 0) return $ret;return -1;
	}
	
	
/*
	* registers a user.
	* returns error code or 1 if success
	*/
	public function __editaccount($email, $flname, $country, $country_code, $currpass, $pass, $confpass, $timezone){
				
		/******Email error checking ******/
		$field = "email";  //Use field name for email
	  
		if(!$email || mb_strlen($email = trim($email)) == 0){
			$this->formcontroller->setError($field, "Email not entered");
		}
		else{
			/* Check if valid email address */
			$regex = "^[_+a-z0-9-]+(\.[_+a-z0-9-]+)*"
                 ."@[a-z0-9-]+(\.[a-z0-9-]{1,})*"
                 ."\.([a-z]{2,}){1}$";
			if(!mb_eregi($regex,$email)){
				$this->formcontroller->setError($field, "Invalid Email");
			}
			else if(mb_strlen(trim($email)) > 120){
				$this->formcontroller->setError($field, "Email too big");
			}
			else if($this->dbcontroller->matchUserField($email,'email',$this->sessioninfo->getUserKey())){
				
			}
			/* Check if email is already in use */
			else if($this->dbcontroller->dbemailTaken($email)){
				$this->formcontroller->setError($field, "Email already in use");
			}

		}	  
		
		/*
		 *if new password and confirm password are empty dont process the password changes 
		 * */
		if(($pass)||($confpass)){
		/**** Current Password error checking*****/
		$field = "currpass";
		if(!$currpass){
			$this->formcontroller->setError($field, "Password not entered");
		}
		else{
			if(mb_strlen(trim($currpass)) < 8){
				$this->formcontroller->setError($field, "The Password is wrong.You are missing some characters");
			}
			else if(mb_strlen(trim($currpass)) > 20){
				$this->formcontroller->setError($field, "The Password is wrong.You are typing to many characters");
			}
			else if (mb_eregi("^((root)|(bin)|(daemon)|(adm)|(lp)|(sync)|(shutdown)|
(halt)|(mail)|(news)|(uucp)|(operator)|(games)|(mysql)|
(httpd)|(nobody)|(dummy)|(www)|(cvs)|(shell)|(ftp)|(irc)|
(debian)|(ns)|(download))$", $currpass))
            	$this->formcontroller->setError($field, "The Password is wrong");
            else if(!$this->dbcontroller->matchUserField(sha1($currpass.YAS),'password',$this->sessioninfo->getUserKey())){
				$this->formcontroller->setError($field, "The Password is wrong");
			}
		}
				
		/**** Password error checking*****/
		$field = "pass";  //Use field name for password
		if(!$pass){
			$this->formcontroller->setError($field, "Password not entered");
		}
		else{
			if(mb_strlen(trim($pass)) < 8){
				$this->formcontroller->setError($field, "8 characters necessary");
			}
			else if(mb_strlen(trim($pass)) > 20){
				$this->formcontroller->setError($field, "Password too big");
			}
			else if (mb_eregi("^((root)|(bin)|(daemon)|(adm)|(lp)|(sync)|(shutdown)|
(halt)|(mail)|(news)|(uucp)|(operator)|(games)|(mysql)|
(httpd)|(nobody)|(dummy)|(www)|(cvs)|(shell)|(ftp)|(irc)|
(debian)|(ns)|(download))$", $pass))
            $this->formcontroller->setError($field, "Password not allowed");
		}
		
		/********* Password Confirm error checking*******/
		if(REPEAT_PASSWORD){
			$field = "confpass";  //Use field name for confpassword
			if(!$confpass){
				$this->formcontroller->setError($field, "Password not entered");
			}
			if($confpass!=$pass){
				$this->formcontroller->setError($field, "Passwords don't match");
			}  
		}
		}
		
		/***************************** First and Last Name error checking *************************/
		$field = "flname";  
		
		if(!$flname || mb_strlen($flname = trim($flname)) == 0){
			$this->formcontroller->setError($field, "Name not entered");
		}
		else if(!mb_eregi("^[[:alpha:] ]*$", $flname))
			$this->formcontroller->setError($field, "Name invalid");	
		else if (mb_eregi("^((root)|(bin)|(daemon)|(adm)|(lp)|(sync)|(shutdown)|
(halt)|(mail)|(news)|(uucp)|(operator)|(games)|(mysql)|
(httpd)|(nobody)|(dummy)|(www)|(cvs)|(shell)|(ftp)|(irc)|
(debian)|(ns)|(download))$", $flname))
			$this->formcontroller->setError($field, "Name not allowed");
		else if(mb_strlen(trim($flname)) > 100){
				$this->formcontroller->setError($field, "Name too big");
		}
		
		$flname = html_entity_decode($flname,ENT_NOQUOTES, 'UTF-8') ;
		
		
		/******************************** Country error checking *************************/	
		$field = "country"; 
		if(!$country || mb_strlen($country = trim($country)) == 0){
			$this->formcontroller->setError($field, "Country not entered");
		}
		else{
			//if the code of the country that is posted is different than -1 (the user selected a country from the list):
			if($country_code == -1){ 
				//the user selected a country but it was not one of the list:
				//check if the country typed by the user exists in table of countries(lower case both)
				//if so inserts that as the country of the user otherwise gives back an error saying that the user has to select 
				//a country from the list
				$db_country_code = $this->dbcontroller->dbexistsCountry($country);
				if($db_country_code == null){
					$this->formcontroller->setError($field, "Please choose a country from the list");
				}
				else $country_code = $db_country_code;
				
			}
		}
		
		/******************************** timezone error checking *************************/	
		$field = "timezone";
		if(mb_eregi("/^\w+$/", $timezone)) {
			$this->formcontroller->setError($field, "timezone is invalid");
		} elseif($timezone == 'choose' || $timezone == 'disabled') {
			$this->formcontroller->setError($field, "please select a timezone!");
		}	
			
		/* Errors exist, have user correct them */
		if($this->formcontroller->formCountErrors() > 0){
			return -1;  //Errors with form
		}
		/* No errors, change the data in the db*/
		else{
			$sha1pass ="";
			if($pass)
				$sha1pass=sha1($pass.YAS);
			if(($ret = $this->dbcontroller->dbeditaccount($email, $flname, $country_code, $sha1pass, $this->sessioninfo->getUserKey(), $timezone)) > 0){
				$this->unsetSessionVariable('timezone_offset');
				$this->unsetSessionVariable('timezone');
				$this->sessioninfo->setUserInfo($this->dbcontroller->dbgetUserInfoEmail($email));
				$this->setSessionVariable('timezone',$this->sessioninfo->getUserInfo('timezone'));
				$this->setTimezoneSession();
				date_default_timezone_set($this->sessioninfo->getUserInfo('timezone'));
				/* is user a premium (paid) or free user */
				$this->setSessionVariable('premium',$this->sessioninfo->getUserInfo('premium'));
				
				return $ret;  //User data changed succesfully
			}
			else{
				return -2;  //Edit Account failed
			}
	  }
	}
	
	/*
	 * processes the map request for admin - this action starts
	 * on an ajax call - gets number of users per country
	 * */
	public function processMapRequest() {
		$ret = $this->dbcontroller->getUsersPerCountry($this->sessioninfo->getUserKey());      
		echo "{\"results\": [";
		$arr = array();
		for ($i=0;$i<count($ret);$i++)
		{
			$arr[] = "{\"country_name\": \"".$ret[$i]['country_name']."\", \"value\": \"".$ret[$i]['value']."\"}";
		}
		echo implode(", ", $arr);
		echo "]}";
	}

	public function processAdminOperation() {
		$userkey = $_POST['uk'];
		$currentvalue = $_POST['currval'];
		$operation = $_POST['op'];
		if($operation=='block'){
			if($currentvalue == '0')
				$retval = $this->dbcontroller->updateUserField($userkey,'usr_is_blocked',1);
			else
				$retval = $this->dbcontroller->updateUserField($userkey,'usr_is_blocked',0);	
		}
		elseif($operation=='admin'){
			if($currentvalue == '0')
				$retval = $this->dbcontroller->updateUserField($userkey,'usr_is_admin',1);
			else
				$retval = $this->dbcontroller->updateUserField($userkey,'usr_is_admin',0);	
		}
		elseif($operation=='delete'){
			$retval = $this->dbcontroller->deleteUser($userkey);	
		}
		if($retval < 0)	
			$json = array("result" => -1);
		else
			$json = array("result" => 1);	
		$encoded = json_encode($json);
		echo $encoded;
		unset($encoded);		
	}
	
	/*
	 * gets all users data except the current user - for admin
	 * */
	public function getUsersData() {
		return $this->dbcontroller->getUsersData($this->sessioninfo->getUserKey());      
	}
	
	/*
	 * returns true if user is admin
	 * */	
	public function isAdmin() {
		$details = $this->dbcontroller->dbgetUserAccountDetails($this->sessioninfo->getUserKey());
		return ($details['usr_is_admin'] > 0);
	}
	
	/*
	 * returns user account details
	 * */	
	public function getUserAccountDetails() {
		return $this->dbcontroller->dbgetUserAccountDetails($this->sessioninfo->getUserKey());
	}
	
	
	/*
	 * returns sessioninfo obj
	 * */	
	public function getSessionInfo() {
		return $this->sessioninfo;
	}

	/*
	 * returns formcontroller obj
	 * */	
	public function getFormController() {
		return $this->formcontroller;
	}
	
	
	/*
	 * set a session variable 
	 * */
	public function setSessionVariable($name,$value) {
		$_SESSION[$name] = $value;
	}
	
	
	/*
	 * unset a session variable 
	 * */
	public function unsetSessionVariable($name) {
		unset($_SESSION[$name]);
	}
	
	/**
	 * set twitter session variables
	 */
	public function setTwitterSession($twitterObject, $num) {
		if(!isset($_SESSION['twitter_exists'])) { $this->setSessionVariable('twitter_exists',"true"); }
		$this->setSessionVariable('twitter_id_'.$num,$twitterObject['twitter_id']);
		$this->setSessionVariable('twitter_name_'.$num,$twitterObject['twitter_name']);
		$this->setSessionVariable('twitter_screenname_'.$num,$twitterObject['twitter_screenname']);
		$this->setSessionVariable('friends_count_'.$num,$twitterObject['friends_count']);
		$this->setSessionVariable('followers_count_'.$num,$twitterObject['followers_count']);
		$this->setSessionVariable('profile_image_url_'.$num,$twitterObject['profile_image_url']);
		$this->setSessionVariable('oauth_token_'.$num,$twitterObject['oauth_token']);
		$this->setSessionVariable('oauth_token_secret_'.$num,$twitterObject['oauth_token_secret']);
		//$this->setSessionVariable('utc_diff_'.$num,$twitterObject['utc_diff']);
		$this->setSessionVariable('purge_dm_'.$num,$twitterObject['purge_dm']);
		$this->setSessionVariable('dm_new_follow_'.$num,$twitterObject['dm_new_follow']);
		$this->setSessionVariable('re_follow_'.$num,$twitterObject['re_follow']);
	}
	
	/**
	 * unset twitter session variables
	 */
	public function unsetTwitterSession($num) {
		//if(isset($_SESSION['twitter_exists'])) { $this->unsetSessionVariable('twitter_exists'); }
		$this->unsetSessionVariable('twitter_id_'.$num);
		$this->unsetSessionVariable('twitter_name_'.$num);
		$this->unsetSessionVariable('twitter_screenname_'.$num);
		$this->unsetSessionVariable('friends_count_'.$num);
		$this->unsetSessionVariable('followers_count_'.$num);
		$this->unsetSessionVariable('profile_image_url_'.$num);
		$this->unsetSessionVariable('oauth_token_'.$num);
		$this->unsetSessionVariable('oauth_token_secret_'.$num);
		//$this->unsetSessionVariable('utc_diff_'.$num);
		$this->unsetSessionVariable('purge_dm_'.$num);
		$this->unsetSessionVariable('dm_new_follow_'.$num);
		$this->unsetSessionVariable('re_follow_'.$num);
	}
	
	/**
	 * save twitter data
	 */
	public function saveTwitterData($ukey, $nm, $sn, $fc, $fc2, $pimg, $oauth, $secret, $utc) {
		$twitresponse = $this->dbcontroller->setTwitterData($ukey, $nm, $sn, $fc, $fc2, $pimg, $oauth, $secret, $utc);
		if($twitresponse) {
			return $twitresponse;
		} else {
			return false;
		}
	}
	
	/**
	 * load twitter object
	 */
	public function loadTwitterObject() {
		$twitterobject = $this->dbcontroller->getTwitterData($this->sessioninfo->getUserKey());
		if($twitterobject) {
			$x=0;
			foreach($twitterobject as $to) {
				$x++;
				$this->setTwitterSession($to,$x);
			}
			$this->setSessionVariable('total_twitter_accounts',$x);
		} else {
			return false;
		}
	}
	
	/**
	 * update twitter data
	 */
	public function updateTwitterData($twid, $ukey, $nm, $sn, $fc, $fc2, $pimg, $utc) {
		$twitresponse = $this->dbcontroller->updateTwitterAcct($twid, $ukey, $nm, $sn, $fc, $fc2, $pimg, $utc);
		if($twitresponse) {
			return true;
		} else {
			return false;
		}
	}
	
	/**
	 * process update twitter account 
	 */
	public function processUpdateTwitter() {
		if(isset($_POST['twit_update_num'])) {
			$num = $_POST['twit_update_num'];
			$twid = 'twitter_id_'.$num;
			$oauth_token = 'oauth_token_'.$num;
			$oauth_token_secret = 'oauth_token_secret_'.$num;
			$tmhOAuth = new tmhOAuth(array(
			  'consumer_key'    => CONSUMER_KEY,
			  'consumer_secret' => CONSUMER_SECRET,
			  'user_token'      => $_SESSION[$oauth_token],
			  'user_secret'     => $_SESSION[$oauth_token_secret],
			));

			$code = $tmhOAuth->request('GET', $tmhOAuth->url('1/account/verify_credentials'));
			if ($code == 200) {
				$resp = json_decode($tmhOAuth->response['response']);
				if($this->updateTwitterData($twid, $this->getSessionInfo()->getUserKey(), $resp->name, $resp->screen_name, $resp->friends_count, $resp->followers_count, $resp->profile_image_url, $resp->utc_offset)) {
					$this->loadTwitterObject();
					$json = array("result" => 1,
							"name" => "twitter_update",
							"value" => "&nbsp;&nbsp;&nbsp;<b>" . $resp->screen_name . "</b>: successfully updated!" ); 
				} else {
					$this->formcontroller->setError('twitter_update', "Error saving to database!");
					$json = array(
						"result" => -1, 
						"errors" => array(
						array("name" => "twitter_update","value" => $this->formcontroller->error_value("twitter_update"))
										)
						);
				}
			} else {
				$this->formcontroller->setError('twitter_update', "Twitter Error: ".tmhUtilities::pr(htmlentities($tmhOAuth->response['response'])));
				$json = array(
					"result" => -1, 
					"errors" => array(
					array("name" => "twitter_update","value" => $this->formcontroller->error_value("twitter_update"))
									)
					);
			}
		} else {
			$this->formcontroller->setError('twitter_update', "Please select an account to update!");
			$json = array(
				"result" => -1, 
				"errors" => array(
				array("name" => "twitter_update","value" => $this->formcontroller->error_value("twitter_update"))
								)
				);
			
		}
		$this->array_push_associative($json, array("bttn" => "_update_btt"), array("loader" => "ukaj"));
		$encoded = json_encode($json);
		echo $encoded;
		unset($encoded);
	}
	
	/**
	 * process update twitter account quiet
	 */
	public function processUpdateTwitterQuiet($tmhOAuth,$tid,$uid) {
		if(is_numeric($tid)) {
			$code = $tmhOAuth->request('GET', $tmhOAuth->url('1/account/verify_credentials'));
			if ($code == 200) {
				$resp = json_decode($tmhOAuth->response['response']);
				if($this->updateTwitterData($tid, $uid, $resp->name, $resp->screen_name, $resp->friends_count, $resp->followers_count, $resp->profile_image_url, $resp->utc_offset)) {
					return true;
				} else {
					return false;
				}
			} else {
				return false;
			}
		}
	}
	
	/**
	 * remove twitter account
	 */
	public function removeTwitterAccount() {
		if(isset($_POST['twit_remove_num']) && (is_numeric($_POST['twit_remove_num']))) {
			$twid = 'twitter_id_'.$_POST['twit_remove_num'];
			$twsn = 'twitter_screenname_'.$_POST['twit_remove_num'];
			$status = $this->dbcontroller->removeTwitterAcct($_SESSION[$twid], $_SESSION[$twsn], $this->sessioninfo->getUserKey());
			if ($status == -1) {
				$this->formcontroller->setError('twitter_remove', "Problem removing twitter account!");
				$json = array(
					"result" => -1, 
					"errors" => array(
					array("name" => "twitter_remove","value" => $this->formcontroller->error_value("twitter_remove"))
									)
					);
			} else {
				// success
				$this->unsetTwitterSession($_POST['twit_remove_num']);
				if($this->loadTwitterObject()) { // reload twitter object
					$out = '<form name="form_twitter_remove" id="form_twitter_remove" action="../includes/corecontroller.php" method="post"><ul id="frmrmpaging">';
					for($i=0;$i<$_SESSION['total_twitter_accounts'];$i++) {
					$h = $i+1;
					$twname = 'twitter_name_'.$h;
					$twsnm  = 'twitter_screenname_'.$h;
					$twfolo = 'followers_count_'.$h;
						$out .= '<li>'.$_SESSION[$twsnm].' &nbsp; - '.$_SESSION[$twname].' (<b>'.$_SESSION[$twfolo].'</b>) <input type="radio" name="twit_remove_num" id="twit_remove_num" class="right" value="'.$h.'" /></li>';
					}
					$out .= '</ul><a id="_remove_btt" class="button" href="#">Remove</a><span id="rmaj"></span>&nbsp;<a href="#"class="close">Close</a>&nbsp;&nbsp;<span class="message_success" id="rm_suc"><b>'.htmlspecialchars($status).'</b> has been successfully removed</span><span class="error" id="twitter_remove_error"></span><input type="hidden" name="removetwitteraccount" value="1" /></form>';
					$json = array(  "result" => 1,
									"name" => "del_rep",
									"value" => $out);
				} else {
					// no more twitter accounts, send to index
					$this->unsetSessionVariable('twitter_exists');
					$json = array(  "result" => 1,
									"name" => "del_rep",
									"value" => 'no more twitter accounts<script type="text/javascript">window.location = "http://'.$_SERVER['SERVER_NAME'].'/social"</script>');
				}
			}
		} else {
				$this->formcontroller->setError('twitter_remove', "Please select an account to remove!");
				$json = array(
					"result" => -1, 
					"errors" => array(
					array("name" => "twitter_remove","value" => $this->formcontroller->error_value("twitter_remove"))
									)
					);
		}
		$this->array_push_associative($json, array("bttn" => "_remove_btt"), array("loader" => "rmaj"));
		$encoded = json_encode($json);
		echo $encoded;
		unset($encoded);	
	}
	
	/**
	 * preload scheduled tweets
	 */
	public function returnScheduledTweets() {
		$arrTweets = $this->dbcontroller->returnScheduledEvents($_SESSION['username'],0);
		if($arrTweets == -1) {
			return "You have no tweets scheduled!";
		} else {
			// success
			$arcnt = count($arrTweets);
			if ( $arcnt >= 10) {
				$artwetfinal = 10;
			} else { 
				$artwetfinal = $arcnt;
			}
			$height = ($artwetfinal * 30) + 250;
			$tmp = '<style>#boxes #view-updates {
				  height:'.$height.'px;
				  width:500px; 
				  padding:10px;
				  background-color:#ffffff;
				}</style><form name="edit_tweet" id="edit_tweet" action="../includes/corecontroller.php" method="post">
			<div id="edit_title"><b>Screen name</b> | <b><a href="../editaccount.php">Time ('.$_SESSION['timezone_offset'].')</a></b> | <b>Message</b></div><ul id="paging">';
			$separator = '<b>...</b>';
			$separatorlength = strlen($separator);
			$maxlength = 30 - $separatorlength;
			$start = $maxlength / 2 ;
			
			foreach($arrTweets as $arrTweet) {
				$bdylen = strlen($arrTweet['event_body']);
				$scnlen = strlen($arrTweet['twitter_screenname']);
				$trunc  =  $bdylen - $maxlength;
				if($bdylen > 35) {
					$bdy = substr_replace($arrTweet['event_body'], $separator, $start, $trunc);
				} else {
					$bdy = $arrTweet['event_body'];
				}
				
				if($scnlen > 9) {
					$twitname = substr_replace($arrTweet['twitter_screenname'], '...', 10, $scnlen);
				} else {
					$twitname = $arrTweet['twitter_screenname'];
				}
				$newdate = date("Y-m-d h:i A",$arrTweet['scheduled_twit_date']);
				$tmp .= '<li>' .$twitname. ' | ' .$newdate. ' | ' .$bdy. ' <input type="radio" name="twit_edit_num" id="twit_edit_num" value="'.$arrTweet['scheduled_twit_id'].'" /> </li>';
			}
			$tmp .= '</ul><a id="_edit_btt" class="button" href="#">Select</a><span id="edaj"></span><input type="hidden" name="edittweet" value="1" /></form></div>';
			return $tmp;
		}
	}
	
	/**
	 * preload scheduled tweets for deleting
	 */
	public function returnDeleteScheduledTweets() {
		$arrTweets = $this->dbcontroller->returnScheduledEvents($_SESSION['username'],0);
		if($arrTweets == -1) {
			return "You have no tweets scheduled!";
		} else {
			// success
			$arcnt = count($arrTweets);
			if ( $arcnt >= 10) {
				$artwetfinal = 10;
			} else { 
				$artwetfinal = $arcnt;
			}
			$height = ($artwetfinal * 30) + 250;
			$tmp = '<style>#boxes #del-tweets {
				  height:'.$height.'px;
				  width:500px; 
				  padding:10px;
				  background-color:#ffffff;
				}</style><form name="delete_tweet" id="delete_tweet" action="../includes/corecontroller.php" method="post">
			<div id="edit_title"><b>Screen name</b> | <b><a href="../editaccount.php">Time ('.$_SESSION['timezone_offset'].')</a></b> | <b>Message</b></div><ul id="delpaging">';
			$separator = '<b>...</b>';
			$separatorlength = strlen($separator);
			$maxlength = 30 - $separatorlength;
			$start = $maxlength / 2 ;
			
			foreach($arrTweets as $arrTweet) {
				$bdylen = strlen($arrTweet['event_body']);
				$scnlen = strlen($arrTweet['twitter_screenname']);
				$trunc  =  $bdylen - $maxlength;
				if($bdylen > 35) {
					$bdy = substr_replace($arrTweet['event_body'], $separator, $start, $trunc);
				} else {
					$bdy = $arrTweet['event_body'];
				}
				
				if($scnlen > 9) {
					$twitname = substr_replace($arrTweet['twitter_screenname'], '<b>...</b>', 10, $scnlen);
				} else {
					$twitname = $arrTweet['twitter_screenname'];
				}
				$newdate = date("Y-m-d h:i A",$arrTweet['scheduled_twit_date']);
				$tmp .= '<li>' .$twitname. ' | ' .$newdate. ' | ' .$bdy. ' <input type="checkbox" name="twit_delete_num[]" id="twit_delete_num" value="'.$arrTweet['scheduled_twit_id'].'" /></li>';
			}
			$tmp .= '</ul><a id="_delete_btt" class="button" href="#">Delete</a><span id="yabutid"></span><input type="hidden" name="deletetweet" value="1" /></form>';
			return $tmp;
		}
	}
	
	/**
	 * process a new tweet
	 */
	public function processNewTweet() {
		if(isset($_POST['tweet_h']) && ($_POST['tweet_h'] != '')) {
			if(isset($_POST['tz']) && ($_POST['tz'] != '')) {
				if(isset($_POST['tweet']) && ($_POST['tweet'] != '')) {
					// remove the last three digits making javascript epoch compatible with php
					$fixed_time = substr($_POST['tweet_h'],0,strlen($_POST['tweet_h'])-3);
					date_default_timezone_set($_POST['tz']);
					if($_POST['tweet_h'] < time()) {
						// error GOING BACK IN TIE AYE AYE IEIE AYEYE IME
						$this->formcontroller->setError('schedule_tweet', "You have scheduled a twitter message in the past, please try again!");
						$json = array(
							"result" => -1, 
							"errors" => array(
							array("name" => "schedule_tweet","value" => $this->formcontroller->error_value("schedule_tweet"))
											)
							);
					} else if(isset($_POST['twitteracct']) && ($_POST['twitteracct'] == 'choose') ) {
						$this->formcontroller->setError('schedule_tweet', "Select a twitter account and try again!");
						$json = array(
							"result" => -1, 
							"errors" => array(
							array("name" => "schedule_tweet","value" => $this->formcontroller->error_value("schedule_tweet"))
											)
							);
					} else if(strlen($_POST['tweet']) > 144) {
						// error tweet too long
						$this->formcontroller->setError('schedule_tweet', "Your message is longer than twitters allowed length, please try again!");
						$json = array(
							"result" => -1, 
							"errors" => array(
							array("name" => "schedule_tweet","value" => $this->formcontroller->error_value("schedule_tweet"))
											)
							);
					} else {
						// successful input, schedule
						$retval = $this->dbcontroller->scheduleTwitEvent($_POST['twitteracct'], $fixed_time, $_POST['tweet'], 0, $this->sessioninfo->getUserKey());
						if($retval != -1) {
							$json = array("result" => 1,
								"name" => "schedule_tweet",
								"value" => "Your message on account: <b>" . $_POST['twitteracct'] . "</b> has been successfully scheduled!"); 
						} else{
							$this->formcontroller->setError('schedule_tweet', "There has been an error saving, your tweet has <b>not</b> been scheduled.");
							$json = array(
								"result" => -1, 
								"errors" => array(
								array("name" => "schedule_tweet","value" => $this->formcontroller->error_value("schedule_tweet"))
												)
								); 
						}
					}
				} else {
					// error no value
					$this->formcontroller->setError('schedule_tweet', "You have not entered a twitter message, please try again!");
					$json = array(
						"result" => -1, 
						"errors" => array(
						array("name" => "schedule_tweet","value" => $this->formcontroller->error_value("schedule_tweet"))
										)
						);
				}
			} else {
				// error no value
				$this->formcontroller->setError('schedule_tweet', "You have not entered a timezone, please try again!");
				$json = array(
					"result" => -1, 
					"errors" => array(
					array("name" => "schedule_tweet","value" => $this->formcontroller->error_value("schedule_tweet"))
									)
					);
			}
		} else {
			// error no value
			$this->formcontroller->setError('schedule_tweet', "You have not entered any date, please try again!");
			$json = array(
				"result" => -1, 
				"errors" => array(
				array("name" => "schedule_tweet","value" => $this->formcontroller->error_value("schedule_tweet"))
								)
				);
			
		}
		$this->array_push_associative($json, array("bttn" => "_schedule_btt"), array("loader" => "scaj"));
		$encoded = json_encode($json);
		echo $encoded;
		unset($encoded);
	}
	
	/**
	 * process edit tweet final
	 */
	public function processUpdateTweet() {
		if(isset($_POST['tweet_j']) && ($_POST['tweet_j'] != '')) {
			if(isset($_POST['tz_up']) && ($_POST['tz_up'] != '')) {
				if(isset($_POST['tweet2']) && ($_POST['tweet2'] != '')) {
					date_default_timezone_set($_POST['tz_up']);
					// remove the last three digits making javascript epoch compatible with php
					$fixed_time = substr($_POST['tweet_j'],0,strlen($_POST['tweet_j'])-3);
					if($_POST['tweet_j'] < time()) {
						// error GOING BACK IN TIE AYE AYE IEIE AYEYE IME
						$this->formcontroller->setError('origin_error', "You have scheduled a twitter message in the past, please try again!");
						$json = array(
							"result" => -1, 
							"errors" => array(
							array("name" => "origin_error","value" => $this->formcontroller->error_value("origin_error"))
											)
							);
					} else if(strlen($_POST['tweet2']) > 144) {
						// error tweet too long
						$this->formcontroller->setError('origin_error', "Your message is longer than twitters allowed length, please try again!");
						$json = array(
							"result" => -1, 
							"errors" => array(
							array("name" => "origin_error","value" => $this->formcontroller->error_value("origin_error"))
											)
							);
					} else if(!is_numeric($_POST['twit_edit_num_2'])) {
						// error user messing about
						$this->formcontroller->setError('origin_error', "There has been an error, please try again.");
						$json = array(
							"result" => -1, 
							"errors" => array(
							array("name" => "origin_error","value" => $this->formcontroller->error_value("origin_error"))
											)
							);
					} else {
						// successful input, schedule
						$retval = $this->dbcontroller->updateTwitterEvent($_POST['twit_edit_num_2'], $fixed_time, $_POST['tweet2'], $this->sessioninfo->getUserKey());
						if($retval != -1) {
							$json = array("result" => 1,
								"name" => "origin_success_h",
								"value" => "Your message on account: <b>" . $_POST['twitteraccth'] . "</b> has been successfully updated!"); 
						} else{
							$this->formcontroller->setError('origin_error', "There has been an error saving, your tweet has <b>not</b> been scheduled.");
							$json = array(
								"result" => -1, 
								"errors" => array(
								array("name" => "origin_error","value" => $this->formcontroller->error_value("origin_error"))
												)
								); 
						}
					}
				} else {
					// error no value
					$this->formcontroller->setError('schedule_tweet', "You have not entered a twitter message, please try again!");
					$json = array(
						"result" => -1, 
						"errors" => array(
						array("name" => "schedule_tweet","value" => $this->formcontroller->error_value("schedule_tweet"))
										)
						);
				}
			} else {
				// error no value
				$this->formcontroller->setError('schedule_tweet', "You have not entered a timezone, please try again!");
				$json = array(
					"result" => -1, 
					"errors" => array(
					array("name" => "schedule_tweet","value" => $this->formcontroller->error_value("schedule_tweet"))
									)
					);
			}
		} else {
			// error no value
			$this->formcontroller->setError('schedule_tweet', "You have not entered any date, please try again!");
			$json = array(
				"result" => -1, 
				"errors" => array(
				array("name" => "schedule_tweet","value" => $this->formcontroller->error_value("schedule_tweet"))
								)
				);
			
		}
		$this->array_push_associative($json, array("bttn" => "_update_btt2"), array("loader" => "scajh"));
		$encoded = json_encode($json);
		echo $encoded;
		unset($encoded);
	}
	
	/**
	 * edit tweet
	 */
	public function processEditTweet() {
		// finish imputting date
		if(isset($_POST['twit_edit_num']) && (is_numeric($_POST['twit_edit_num']))) {
			$schEvent = $this->dbcontroller->returnScheduledEvent($_POST['twit_edit_num'],$this->sessioninfo->getUserKey());
			if($schEvent) {
				$twitaccts = $_SESSION['total_twitter_accounts'];
				// add three digits to convert to the javascript form of utc
				$editout = '<script type="text/javascript" language="javascript">
					$(function() {
						$(\'#timetest2\').datetimepicker({
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
						d = new Date('.$schEvent[0]['scheduled_twit_date'].'000);
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
						$(\'.timetest2\').val(fulldate);
					});
				</script><style>#boxes #view-updates {height:300px;width:500px;padding:10px;background-color:#ffffff;}</style><form name="update_existing_tweet" id="update_existing_tweet" action="../includes/corecontroller.php" method="post">
				  <textarea rows="3" cols="61" id="tweet2" name="tweet2">'.$schEvent[0]['event_body'].'</textarea><br /><br />
				  <table>
					<tr><td><b>Timezone:</b></td><td>'.$_SESSION['timezone'].' &nbsp;<a href="../editaccount.php">Change</a>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<a href="#" onclick="vieweditsch();">Return to Tweets</a></td></tr>
					<tr><td><b>Account:</b></td><td><input id="twitteracct2" name="twitteracct2" value="'.$schEvent[0]['twitter_screenname'].'" disabled="disabled" /></td></tr><tr><td><b>Date & Time:</b></td><td><input name="timetest2" id="timetest2" class="timetest2" size="26" /></td></tr>
				  </table>
				  <input type="hidden" name="updateexistingtweet" value="1" />
				  <input type="hidden" name="twitteraccth" value="'.$schEvent[0]['twitter_screenname'].'" />
				  <input type="hidden" name="twit_edit_num_2" value="'.$_POST['twit_edit_num'].'" />
				  <input type="hidden" id="tweet_j" name="tweet_j" value="" />
				  <input type="hidden" id="tz_up" name="tz_up" value="'.$_SESSION['timezone'].'" />
				  <br />
				  <a id="_update_btt2" class="button" href="#" onclick="edit_tweet();">Edit</a><span id="scajh"></span></form>';
				  
				  $json = array(  "result" => 1,
									"name" => "origin",
									"value" => "$editout"); 
		  } else {
			  $this->formcontroller->setError('origin', "Could not retrieve message!");
			  $json = array(
					"result" => -1, 
					"errors" => array(
					array("name" => "origin","value" => $this->formcontroller->error_value("origin"))
									)
					);
		  }
	 } else {
			$this->formcontroller->setError('origin', "No message selected!");
			$json = array(
				"result" => -1, 
				"errors" => array(
				array("name" => "origin","value" => $this->formcontroller->error_value("origin"))
								)
				);
	 }
		$this->array_push_associative($json, array("bttn" => "_edit_btt"), array("loader" => "edaj")); 
		$encoded = json_encode($json);
		echo $encoded;
		unset($encoded);
	}
	
	/**
	 * process the auto direct message account select
	 */
	public function processDMSelect() {
		if(isset($_POST['twit_dm_new_num']) && (is_numeric($_POST['twit_dm_new_num']))) {
			$twid   = 'twitter_id_'.$_POST['twit_dm_new_num'];
			$twsn   = 'twitter_screenname_'.$_POST['twit_dm_new_num'];
			$twdmnw = 'dm_new_follow_'.$_POST['twit_dm_new_num'];
			$dmbody = $this->dbcontroller->returnDirectMessage($_SESSION[$twid],$this->sessioninfo->getUserKey());
			$out = '<style>#boxes #dm-new {height:270px;width:500px;padding:10px;background-color:#ffffff;}</style><form name="form_dm_edit" id="form_dm_edit" action="../includes/corecontroller.php" method="post">Only new followers from today will be messaged.<br /><b>'.$_SESSION[$twsn].'</b> auto dm status: <br /><br />';
			$out .= 'On&nbsp <input type="radio" name="dm_new_num" id="dm_new_num" value="1" ';
			if($_SESSION[$twdmnw] == 1){$out .= 'checked="checked"';}
			$out .= '/>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
			Off<input type="radio" name="dm_new_num" id="dm_new_num" value="0" ';
			if($_SESSION[$twdmnw] == 0){$out .= 'checked="checked"';}
			$out .= '/><br /><br />DM Body<br /><textarea rows="3" cols="61" id="dm_body" name="dm_body">';
			if($dmbody != 0) { $out.= $dmbody['dm_body']; } 
			$out .='</textarea><br /><a id="_dmsave_btt" class="button" href="#"  onclick="dm_sel();">Save</a><span id="dmbody"></span>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<a href="#" onclick="autodmacct();">Return to Accounts</a><br /><span class="message_success" id="dm_success"></span><span class="error" id="dm_post_error"></span><input type="hidden" name="processdmmessage" value="1" /><input type="hidden" name="tid" value="'.$_POST['twit_dm_new_num'].'" /></form>';
			$json = array(  "result" => 1,
							"name" => "dm_rep",
							"value" => $out);
			$this->array_push_associative($json, array("bttn" => "_dm_select_btt"), array("loader" => "dmflaj"));
			$encoded = json_encode($json);
			echo $encoded;
			unset($encoded);
		}
	}
	
	/**
	 * process the user auto direct message select form
	 */
	public function processDMForm() {
		if(isset($_POST['tid']) && (is_numeric($_POST['tid']))) {
			if((empty($_POST['dm_body']) || ($_POST['dm_body'] == ' ')) && ($_POST['dm_new_num'] == '1')) {
				// error empty
				$this->formcontroller->setError('dm_post', "Your message is empty, please try again!");
				$json = array(
					"result" => -1, 
					"errors" => array(
					array("name" => "dm_post","value" => $this->formcontroller->error_value("dm_post"))
									)
					);
			} else if(strlen($_POST['dm_body']) > 144) {
				// error tweet too long
				$this->formcontroller->setError('dm_post', "Your message is longer than twitters allowed length, please try again!");
				$json = array(
					"result" => -1, 
					"errors" => array(
					array("name" => "dm_post","value" => $this->formcontroller->error_value("dm_post"))
									)
					);
			} else if($_POST['dm_new_num'] == '0') {
				// turn off
				$tidvar = 'twitter_id_'.$_POST['tid'];
				if($this->dbcontroller->updateTwitterDM($_SESSION[$tidvar],$this->sessioninfo->getUserKey(),0) == 1) {
					// success
					$this->loadTwitterObject(); // reload the users session objects					
					$json = array(  "result" => 1,
								    "name" => "dm_success",
									"value" => "&nbsp;&nbsp;&nbsp;&nbsp;Auto DM has been turned off!");
				} else {
					$this->formcontroller->setError('dm_post', "We apologize but there has been an error. Please try again later.");
					$json = array(
						"result" => -1, 
						"errors" => array(
						array("name" => "dm_post","value" => $this->formcontroller->error_value("dm_post"))
										)
						);
				}
			} else if($_POST['dm_new_num'] == '1') {
				// turn on
				$tidvar   = 'twitter_id_'.$_POST['tid'];
				$oatoken  = 'oauth_token_'.$_POST['tid'];
				$oasecret = 'oauth_token_secret_'.$_POST['tid'];
				// check if there is a direct message saved already
				if($this->dbcontroller->checkSavedDM($_SESSION[$tidvar],$this->sessioninfo->getUserKey()) == 0) {
					// no saved message, insert
					if($this->dbcontroller->insertNewDM($_SESSION[$tidvar],$this->sessioninfo->getUserKey(),$_POST['dm_body']) != -1) {
						if($this->dbcontroller->updateTwitterDM($_SESSION[$tidvar],$this->sessioninfo->getUserKey(),1) == 1) {
							$this->saveNewFollowers($_SESSION[$tidvar],$this->sessioninfo->getUserKey(),$_SESSION[$oatoken],$_SESSION[$oasecret]);
							// success
							$this->loadTwitterObject(); // reload the users session objects
							$json = array(  "result" => 1,
											"name" => "dm_success",
											"value" => "&nbsp;&nbsp;&nbsp;&nbsp;Auto DM has been turned on, and your message saved!");
						} else {
							$this->formcontroller->setError('dm_post', "We apologize but there has been an error. Please try again later.");
							$json = array(
								"result" => -1, 
								"errors" => array(
								array("name" => "dm_post","value" => $this->formcontroller->error_value("dm_post"))
												)
								);
						}
					}
				} else {
					// saved message, update
					if($this->dbcontroller->updateExistingDM($_SESSION[$tidvar],$this->sessioninfo->getUserKey(),$_POST['dm_body']) == 1) {
						// turn on
						if($this->dbcontroller->updateTwitterDM($_SESSION[$tidvar],$this->sessioninfo->getUserKey(),1) == 1) {
							$this->saveNewFollowers($_SESSION[$tidvar],$this->sessioninfo->getUserKey(),$_SESSION[$oatoken],$_SESSION[$oasecret]);
							// success
							$this->loadTwitterObject(); // reload the users session objects
							$json = array(  "result" => 1,
											"name" => "dm_success",
											"value" => "&nbsp;&nbsp;&nbsp;&nbsp;Auto DM has been turned on, and your message updated!");
						} else {
							$this->formcontroller->setError('dm_post_error', "We apologize but there has been an error. Please try again later.");
							$json = array(
								"result" => -1, 
								"errors" => array(
								array("name" => "dm_post","value" => $this->formcontroller->error_value("dm_post"))
												)
								);
						}
					}
				}				
			}
			$this->array_push_associative($json, array("bttn" => "_dmsave_btt"), array("loader" => "dmbody")); 
			$encoded = json_encode($json);
			echo $encoded;
			unset($encoded);			
		}
	}
	
	/**
	 * process the delete tweet form
	 */
	public function processDeleteTweet() {
		if(isset($_POST['twit_delete_num']) && (is_array($_POST['twit_delete_num']))) {
			foreach($_POST['twit_delete_num'] as $twitDelNum => $twitnum) {
				$status = $this->dbcontroller->removeScheduledEvent($twitnum, $this->sessioninfo->getUserKey());
				if ($status == -1) {
					$this->formcontroller->setError('dstweet', "Problem removing tweet message!");
					$json = array(
						"result" => -1, 
						"errors" => array(
						array("name" => "dstweet","value" => $this->formcontroller->error_value("dstweet"))
										)
						);
					break;
				} else {
					$success = true; // success
				}
			}
			
			if(isset($success)) {
				$_SESSION['twit_del_msg'] = '&nbsp Tweet(s) has been removed!';
				$out = "<h2>Loading New Tweets</h2><img src='../images/big-ajax-loader.gif' /><script type='text/javascript'>window.location = \"../twitter/index.php?pm=del-tweets\";</script>";
				$json = array(  "result" => 1,
								"name" => "dstweet",
								"value" => $out);
			}
		} else {
				$this->formcontroller->setError('dstweet', "Please select a message to remove!");
				$json = array(
					"result" => -1, 
					"errors" => array(
					array("name" => "dstweet","value" => $this->formcontroller->error_value("dstweet"))
									)
					);
		}
		$this->array_push_associative($json, array("bttn" => "_delete_btt"), array("loader" => "yabutid"));
		$encoded = json_encode($json);
		echo $encoded;
		unset($encoded);
	}
	
	/**
	 * return the refollow form and options for a specific account
	 */
	public function processRefollowSel() {
		if(isset($_POST['re_follow_new_num']) && (is_numeric($_POST['re_follow_new_num']))) {
			$twid   = 'twitter_id_'.$_POST['re_follow_new_num'];
			$twsn   = 'twitter_screenname_'.$_POST['re_follow_new_num'];
			$twrenw = 're_follow_'.$_POST['re_follow_new_num'];
			$out = '<style>#boxes #re-follow {height:190px;width:500px;padding:10px;background-color:#ffffff;}</style><form name="form_re_edit" id="form_re_edit" action="../includes/corecontroller.php" method="post">Only new followers from today will be followed.<br /><b>'.$_SESSION[$twsn].'</b> re follow status:<br /><br />';
			$out .= 'On&nbsp <input type="radio" name="re_new_num" id="re_new_num" value="1" ';
			if($_SESSION[$twrenw] == 1){$out .= 'checked="checked"';}
			$out .= '/>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
			Off<input type="radio" name="re_new_num" id="re_new_num" value="0" ';
			if($_SESSION[$twrenw] == 0){$out .= 'checked="checked"';}
			$out .= '/><br /><a id="_resave_btt" class="button" href="#"  onclick="re_sel();">Save</a><span id="rebody"></span>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<a href="#" onclick="autorefollow();">Return to Accounts</a><span class="message_success" id="re_success"></span><span class="error" id="re_post_error"></span><input type="hidden" name="processrefollowopt" value="1" /><input type="hidden" name="tid" value="'.$_POST['re_follow_new_num'].'" /></form>';
			$json = array(  "result" => 1,
							"name" => "re_flw",
							"value" => $out);
			$this->array_push_associative($json, array("bttn" => "_refollow_sel_btt"), array("loader" => "upaj"));
			$encoded = json_encode($json);
			echo $encoded;
			unset($encoded);
		}
	}
	
	/**
	 * process the refollow form
	 */
	public function processRefollowPut() {
		if(isset($_POST['tid']) && (is_numeric($_POST['tid']))) {
			if($_POST['re_new_num'] == '0') {
				// turn off
				$tidvar = 'twitter_id_'.$_POST['tid'];
				if($this->dbcontroller->updateTwitterRe($_SESSION[$tidvar],$this->sessioninfo->getUserKey(),0) == 1) {
					// success
					$this->loadTwitterObject(); // reload the users session objects					
					$json = array(  "result" => 1,
								    "name" => "re_success",
									"value" => "&nbsp;&nbsp;&nbsp;&nbsp;Re-Follow has been turned off!");
				} else {
					$this->formcontroller->setError('re_post', "We apologize but there has been an error. Please try again later.");
					$json = array(
						"result" => -1, 
						"errors" => array(
						array("name" => "re_post","value" => $this->formcontroller->error_value("re_post"))
										)
						);
				}
			} else if($_POST['re_new_num'] == '1') {
				// turn on
				$tidvar   = 'twitter_id_'.$_POST['tid'];
				$oatoken  = 'oauth_token_'.$_POST['tid'];
				$oasecret = 'oauth_token_secret_'.$_POST['tid'];
				if($this->dbcontroller->updateTwitterRe($_SESSION[$tidvar],$this->sessioninfo->getUserKey(),1) == 1) {
					$this->saveNewFollowers($_SESSION[$tidvar],$this->sessioninfo->getUserKey(),$_SESSION[$oatoken],$_SESSION[$oasecret]);
					// success
					$this->loadTwitterObject(); // reload the users session objects					
					$json = array(  "result" => 1,
								    "name" => "re_success",
									"value" => "&nbsp;&nbsp;&nbsp;&nbsp;Re-Follow has been turned on!");
				} else {
					$this->formcontroller->setError('re_post', "We apologize but there has been an error. Please try again later.");
					$json = array(
						"result" => -1, 
						"errors" => array(
						array("name" => "re_post","value" => $this->formcontroller->error_value("re_post"))
										)
						);
				}
			}
			$this->array_push_associative($json, array("bttn" => "_resave_btt"), array("loader" => "rebody"));
			$encoded = json_encode($json);
			echo $encoded;
			unset($encoded);
		}
	}
	
	/**
	 * process the select purge dm form
	 */
	
	public function processSelectPurgeDM() {
		 if(isset($_POST['twit_dm_pg_num']) && (is_numeric($_POST['twit_dm_pg_num']))) {
			$twid   = 'twitter_id_'.$_POST['twit_dm_pg_num'];
			$twsn   = 'twitter_screenname_'.$_POST['twit_dm_pg_num'];
			$twpurg = 'purge_dm_'.$_POST['twit_dm_pg_num'];
			$out = '<style>#boxes #purge-dm {height:210px;width:500px;padding:10px;background-color:#ffffff;}</style><form name="form_dm_purge_confirm" id="form_dm_purge_confirm" action="../includes/corecontroller.php" method="post">This is not an immediate action, please be patient.<br />Once all messages are purged, this will be turned back off<br /><br /><b>'.$_SESSION[$twsn].'</b> purge dm inbox status: <br /><br />';
			$out .= 'On&nbsp <input type="radio" name="dm_pg_num" id="dm_pg_num" value="1" ';
			if($_SESSION[$twpurg] == 1){$out .= 'checked="checked"';}
			$out .= '/>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
			Off<input type="radio" name="dm_pg_num" id="dm_pg_num" value="0" ';
			if($_SESSION[$twpurg] == 0){$out .= 'checked="checked"';}
			$out .= '/><br /><a id="_dmpurge_final_btt" class="button" href="#"  onclick="autodmpgacct();">Save</a><span id="dbjxh"></span>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<a href="#" onclick="dm_purge();">Return to Accounts</a><br /><span class="message_success" id="dm_pg_success"></span><span class="error" id="dm_pg_post_error"></span><input type="hidden" name="processdmfinal" value="1" /><input type="hidden" name="tid" value="'.$_POST['twit_dm_pg_num'].'" /></form>';
			$json = array(  "result" => 1,
							"name"   => "purge_rep",
							"value"  => $out);
			$this->array_push_associative($json, array("bttn" => "_dmpurge_final_btt"), array("loader" => "dbjxh"));
			$encoded = json_encode($json);
			echo $encoded;
			unset($encoded);
		}
	}
	
	/**
	 * process the on or off switch for direct message purge
	 */
	public function processDMPurgeFinal() {
		if(isset($_POST['tid']) && (is_numeric($_POST['tid']))) {
			$tidvar = 'twitter_id_'.$_POST['tid'];
			if($_POST['dm_pg_num'] == '0') {
				// turn off
				if($this->dbcontroller->updateTwitterPG($_SESSION[$tidvar],$this->sessioninfo->getUserKey(),0) == 1) {
					// success
					$this->loadTwitterObject(); // reload the users session objects					
					$json = array(  "result" => 1,
								    "name" => "dm_pg_success",
									"value" => "&nbsp;&nbsp;&nbsp;&nbsp;DM purge has been turned off!");
				} else {
					$this->formcontroller->setError('dm_pg_post', "We apologize but there has been an error. Please try again later.");
					$json = array(
						"result" => -1, 
						"errors" => array(
						array("name" => "dm_pg_post","value" => $this->formcontroller->error_value("dm_pg_post"))
										)
						);
				}
			} elseif($_POST['dm_pg_num'] == '1') {
				// turn on
				if($this->dbcontroller->updateTwitterPG($_SESSION[$tidvar],$this->sessioninfo->getUserKey(),1) == 1) {
					// success
					$this->loadTwitterObject(); // reload the users session objects					
					$json = array(  "result" => 1,
								    "name" => "dm_pg_success",
									"value" => "&nbsp;&nbsp;&nbsp;&nbsp;DM purge has been turned on!");
				} else {
					$this->formcontroller->setError('dm_pg_post', "We apologize but there has been an error. Please try again later.");
					$json = array(
						"result" => -1, 
						"errors" => array(
						array("name" => "dm_pg_post","value" => $this->formcontroller->error_value("dm_pg_post"))
										)
						);
				}
			}
			$this->array_push_associative($json, array("bttn" => "_dmpurge_final_btt"), array("loader" => "dbjxh"));
			$encoded = json_encode($json);
			echo $encoded;
			unset($encoded);
		}
	}
	
	/**
	 * return an array of twitter friends
	 */
	public function getTwitterFriends($oauthtoken,$oauthsecret) {
			$tmhOAuth = new tmhOAuth(array(
			  'consumer_key'    => CONSUMER_KEY,
			  'consumer_secret' => CONSUMER_SECRET,
			  'user_token'      => $oauthtoken,
			  'user_secret'     => $oauthsecret,
			));

			$cursor = '-1';
			$ids = array();

			while (true) :
			  if ($cursor == '0')
				break;

			  $tmhOAuth->request('GET', $tmhOAuth->url('1/friends/ids'), array(
				'cursor' => $cursor
			  ));

			  // check the rate limit
			  $this->check_rate_limit($tmhOAuth->response);

			  if ($tmhOAuth->response['code'] == 200) {
				$data = json_decode($tmhOAuth->response['response'], true);
				$ids += $data['ids'];
				$cursor = $data['next_cursor_str'];
			  } else {
				break;
			  }
			endwhile;
			unset($tmhOAuth);
			if(empty($ids)) { return false; } else { return $ids; }
	}
	
	/**
	 * return an array of twitter followers
	 */
	public function getTwitterFollowers($oauthtoken,$oauthsecret) {
			$tmhOAuth = new tmhOAuth(array(
			  'consumer_key'    => CONSUMER_KEY,
			  'consumer_secret' => CONSUMER_SECRET,
			  'user_token'      => $oauthtoken,
			  'user_secret'     => $oauthsecret,
			));

			$cursor = '-1';
			$ids = array();

			while (true) :
			  if ($cursor == '0')
				break;

			  $tmhOAuth->request('GET', $tmhOAuth->url('1/followers/ids'), array(
				'cursor' => $cursor
			  ));

			  // check the rate limit
			  $this->check_rate_limit($tmhOAuth->response);

			  if ($tmhOAuth->response['code'] == 200) {
				$data = json_decode($tmhOAuth->response['response'], true);
				$ids += $data['ids'];
				$cursor = $data['next_cursor_str'];
			  } else {
				break;
			  }
			endwhile;
			unset($tmhOAuth);
			if(empty($ids)) { return false; } else { return $ids; }
	}
	
	/**
	 * return all dms
	 */
	public function returnAllDMS($tmhOAuth) {
		$page   = 1;
		$arrDms = array();

		while(true):
		  $tmhOAuth->request('GET', $tmhOAuth->url('1/direct_messages'), array(
			'count' => '200', 'page' => $page
		  ));
		  
		  // check the rate limit
		  $this->check_rate_limit($tmhOAuth->response);

		  if ($tmhOAuth->response['code'] == 200) {
			$arrDm = json_decode($tmhOAuth->response['response'], true);
			if(empty($arrDm)) {
				break; // user has no dms, break loop
			} elseif(count($arrDm) == 200) {
				// needs to iterate again
				foreach($arrDm as $dm) { 
					$arrDms[] = $dm['id_str']; // append each element individually so we dont have a big giant mess
				}
				$page += 1; // append page var and carry on
			} else {
				// less than 200 dm's returned, last iteration needed
				foreach($arrDm as $dm) { 
					$arrDms[] = $dm['id_str']; // append each element individually so we dont have a big giant mess
				}
				break; // last iteration needed, break loop
			}
		  } else {
				$myFile = "log/dm.fetch.error.log";
				$fh = fopen($myFile, 'a') or die("can't open file");
				fwrite($fh, $tmhOAuth->response['response']);
				fclose($fh);
				break;
		  }
		endwhile;
		
		return $arrDms;
	}
	
	/**
	 * delete all dms
	 */
	public function deleteAllDM($tmhOAuth,$dms) {
		foreach($dms as $dm) {
			if(!deleteDM($tmhOAuth,$dm)) {
				// log
			}
		}
	}
	
	/**
	 * delete a single dm
	 */
	public function deleteDM($tmhOAuth,$dm) {
		$tmhOAuth->request('POST', $tmhOAuth->url('1/direct_messages/destroy'), array(
			'id' => $dm
		));
		
		$this->check_rate_limit($tmhOAuth->response);
		
		if ($tmhOAuth->response['code'] == 200) {
			return true;
		} else {
			return false;
		}
	}
	
	/**
	 * follow a user
	 */
	public function followTwitterUser($tmhOAuth,$uid) {
		$tmhOAuth->request('POST', $tmhOAuth->url('1/friendships/create'), array(
			'user_id' => $uid
		 ));
		 
		$this->check_rate_limit($tmhOAuth->response);

		if ($tmhOAuth->response['code'] == 200) {
			return true;
		} else {
			return false;
		}
	}
	
	/**
	 * direct message a twitter user
	 */
	public function dmTwitterUser($tmhOAuth,$uid,$dmbody) {
		$tmhOAuth->request('POST', $tmhOAuth->url('1/direct_messages/new'), array(
			'user_id' => $uid, 'text' => $dmbody
		  ));
		  
		$this->check_rate_limit($tmhOAuth->response);

		if ($tmhOAuth->response['code'] == 200) {
			return true;
		} else {
			return false;
		}
	}
	
	/*
	check to see if followers row exists at all
	if it doesnt grab and save to db
	if it does check the date
	if its been less than 24 hours since last pull do nothing
	if its been 24 or more hours, pull and save
	*/
	public function saveNewFollowers($twid,$pk_user,$oauthtoken,$oauthsecret) {
		$twitFollowers = $this->dbcontroller->returnTwitterFollowers($twid,$pk_user);
		
		if($twitFollowers) {
			// followers row exists, check date
			if($twitFollowers['lastupdate'] > strtotime("-1 day")) {
				// been less than 24 hours since last check
				// do nothing
			} else {
				// been 24 hours or more since last check, update
				$ids = serialize($this->getTwitterFollowers($oauthtoken,$oauthsecret)); // retrieve
				$this->dbcontroller->updateFollowers($twid,$pk_user,$ids,time()); // update
			}
		} else {
			// no row for followers related to this twitter account exists, insert
			$ids = serialize($this->getTwitterFollowers($oauthtoken,$oauthsecret)); // retrieve
			if($ids) {
				$this->dbcontroller->insertNewFollowers($twid,$pk_user,$ids,time()); // insert
			}
		}
	}
	
	/*
	check to see if friends row exists at all
	if it doesnt grab and save to db
	if it does check the date
	if its been less than 24 hours since last pull do nothing
	if its been 24 or more hours, pull and save
	*/
	public function saveNewFriends($twid,$pk_user,$oauthtoken,$oauthsecret) {
		$twitFriends = $this->dbcontroller->returnTwitterFriends($twid,$pk_user);
		
		if($twitFriends) {
			// friends row exists, check date
			if($twitFriends['lastupdate'] > strtotime("-1 day")) {
				// been less than 24 hours since last check
				// do nothing
			} else {
				// been 24 hours or more since last check, update
				$ids = serialize($this->getTwitterFriends($oauthtoken,$oauthsecret)); // retrieve
				$this->dbcontroller->updateFriends($twid,$pk_user,$ids,time()); // update
			}
		} else {
			// no row for friends related to this twitter account exists, insert
			$ids = serialize($this->getTwitterFriends($oauthtoken,$oauthsecret)); // retrieve
			if($ids) {
				$this->dbcontroller->insertNewFriends($twid,$pk_user,$ids,time()); // insert
			}
		}
	}
	
	public function check_rate_limit($response) {
	  $headers = $response['headers'];
	  if ($headers['x_ratelimit_remaining'] == 0) :
		$reset = $headers['x_ratelimit_reset'];
		$sleep = time() - $reset;
		#echo 'rate limited. reset time is ' . $reset . PHP_EOL;
		#echo 'sleeping for ' . $sleep . ' seconds';
		print_r($response);
		exit;
		sleep($sleep);
	  endif;
	}
	
	// Append associative array elements
	public function array_push_associative(&$arr) {
		$ret = 0;
		$args = func_get_args();
	    foreach ($args as $arg) {
		    if (is_array($arg)) {
			    foreach ($arg as $key => $value) {
				    $arr[$key] = $value;
				    $ret++;
			    }
		    }else{
			    $arr[$arg] = "";
		    }
	    }
	    return $ret;
	}
	
	public function returnPremiumFollowers() {
		return $this->dbcontroller->returnPremiums();
	}
	
	public function returnUserstoPurge() {
		return $this->dbcontroller->returnUserstoPurges();
	}
	
	public function returnTwitterFols($twid,$pk_user) {
		return $this->dbcontroller->returnTwitterFollowers($twid,$pk_user);
	}
	
	public function returnDirectMessageBody($twid,$pk_user) {
		return $this->dbcontroller->returnDirectMessage($twid,$pk_user);
	}
	
	public function updateFollower($twid,$pk_user,$ids,$time) {
		return $this->dbcontroller->updateFollowers($twid,$pk_user,$ids,$time);
	}
	
	public function updateTwitterPurge($twid,$pk_user,$pg) {
		return $this->dbcontroller->updateTwitterPG($twid,$pk_user,$pg);
	}
	
	/**
	 * sets up the time zone offset session
	 */
	public function setTimezoneSession() {
		$arrTimezone = array();
		$timezone_identifiers = DateTimeZone::listIdentifiers();
		for ($i=0; $i < count($timezone_identifiers); $i++) {
			date_default_timezone_set($timezone_identifiers[$i]);
			$date = date('P');
			$arrTimezone[$timezone_identifiers[$i]] = str_replace(":","",$date);
		}
		if(isset($_SESSION['timezone'])) {
			$_SESSION['timezone_offset'] = $arrTimezone[$_SESSION['timezone']];
		}
		return $arrTimezone;
	}
};

?>