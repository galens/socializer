<?php
/*
** Class for connecting and manage the mysql database
*/
require_once("constants.php");
require_once("utils.php");

class DBController{
	
	private $link;
	
	public function __construct(){
		mb_internal_encoding("UTF-8");
		mb_regex_encoding("UTF-8");
		$this->link = mysqli_connect(DB_SERVER, DB_USER, DB_PASS, DB_NAME);
		if (mysqli_connect_errno()) {
			echo mysqli_connect_errno();
			echo '<br />unrecoverable db error, exiting';
		    exit();
		}
	}
	
	public function __destruct() {
		$this->disconnect();
	}
	
	/*
	 * checks if user with email "$username" and password "$password" exists
	 * */
	public function confirmUserPass($username, $password){
		$username = mysqli_real_escape_string($this->link,$username);
		/* Verify that user is in database */
		$q = "SELECT password FROM users WHERE email = '$username'";
		$results = mysqli_query($this->link,$q);
		if(!$results || (mysqli_num_rows($results) < 1)){
			mysqli_free_result($results);
			return -1; //Indicates username failure
		}
		$dbarray = mysqli_fetch_array($results,MYSQLI_ASSOC);
		$dbarray['password'] = stripslashes($dbarray['password']);
		$password = stripslashes($password);
		mysqli_free_result($results);
		
		if($password == $dbarray['password']){
			return 1; //Success, Username and password confirmed
		}
		else{
			return -2; //Indicates password failure
		}
	}

   
	/**
    * confirmUserID - Checks whether or not the given
    * username is in the database, if so it checks if the
    * given userid is the same userid in the database
    * for that user. If the user doesn't exist or if the
    * userids don't match up, it returns an error code
    */
	public function confirmUserID($username, $userid){
		$username = mysqli_real_escape_string($this->link,$username);

		/* Verify that user is in database */
		$q = "SELECT usr_userid FROM users WHERE pk_user = '$username'";
		
		$results = mysqli_query($this->link,$q);
	  	  
		if(!$results || (mysqli_num_rows($results) < 1)){
			mysqli_free_result($results);
			return -1; //Indicates username failure
		}

		/* Retrieve userid from result, strip slashes */
		$dbarray = mysqli_fetch_array($results,MYSQLI_ASSOC);
		$dbarray['usr_userid'] = stripslashes($dbarray['usr_userid']);
		$userid = stripslashes($userid);
		mysqli_free_result($results);	
		/* Validate that userid is correct */

		if($userid == $dbarray['usr_userid']){
			return 1; //Success! Username and userid confirmed
		}
		else{
			return -2; //Indicates userid invalid
		}
   }
   
	/*
	* dbemailTaken - Returns true if the email has
	* been taken by another user, false otherwise.
	*/
	public function dbemailTaken($email){
		$email = mysqli_real_escape_string($this->link,$email);
		$q = "SELECT email FROM users WHERE email = '$email'";
		$results = mysqli_query($this->link,$q);
		$numr = mysqli_num_rows($results);
		mysqli_free_result($results);	
		return ($numr > 0);
	}
	
	/*
	** schedule a new twitter event
	*/
	public function scheduleTwitEvent($tw_acct, $tw_sch, $tw_body, $event, $pk_user) {		
		# event table
		# 0 - public tweet
		# 1 - direct message
		
		$tw_acct = mysqli_real_escape_string($this->link,$tw_acct);
		$tw_sch  = mysqli_real_escape_string($this->link,$tw_sch);
		$tw_body = mysqli_real_escape_string($this->link,$tw_body);
		$event   = mysqli_real_escape_string($this->link,$event);
		$pk_user = mysqli_real_escape_string($this->link,$pk_user);
		
		// translate twitter account into id
		$q = "SELECT twitter_id FROM twitter WHERE twitter_screenname = '$tw_acct' AND pk_user = '$pk_user'";
		$results = mysqli_query($this->link,$q);
		if(!$results || (mysqli_num_rows($results) < 1)){
			mysqli_free_result($results);
			return -1; //Indicates username failure
		}
		
		$dbarray = mysqli_fetch_array($results,MYSQLI_ASSOC);
		$dbarray['twitter_id'] = stripslashes($dbarray['twitter_id']);
		mysqli_free_result($results);
		
		// check to see if the message already exists
		$q = "SELECT scheduled_twit_id FROM scheduled_twitter_events WHERE twitter_id = '".$dbarray['twitter_id']."' AND pk_user = '$pk_user' AND scheduled_twit_date = '$tw_sch'";
		$results = mysqli_query($this->link,$q);
		if((mysqli_num_rows($results) >= 1)){
			mysqli_free_result($results);
			return -1; //Indicates username failure
		}
		
		//############### INSERTION ###############	
		mysqli_autocommit($this->link,FALSE);
		mysqli_query($this->link,"SET NAMES 'utf8'");
		$q = "insert into scheduled_twitter_events(scheduled_twit_id,pk_user,twitter_id,event_body,scheduled_twit_date,content_type) values('NULL','$pk_user','".$dbarray['twitter_id']."','$tw_body','$tw_sch','$event')";		
	    
		mysqli_query($this->link,$q);
		if(mysqli_errno($this->link)){
			mysqli_rollback($this->link);
			return -1;
		}
		else{
			mysqli_commit($this->link);
			$result = mysqli_query($this->link,'SELECT LAST_INSERT_ID() as lid');
			$obj = $result->fetch_object();
			$lastinsertedid = $obj->lid;
			$result->close();
			unset($obj);
			return $lastinsertedid;
		}
	}
	
	/*
	 * returns an array of scheduled user data
	 *
	 */
	public function returnScheduledEvents($userkey,$type) {
		# event table
		# 0 - public tweet
		# 1 - direct message
				
		$userkey = mysqli_real_escape_string($this->link,$userkey);
		$type    = mysqli_real_escape_string($this->link,$type);
		
		$q = "select scheduled_twitter_events.event_body, scheduled_twitter_events.scheduled_twit_date, twitter.twitter_screenname, scheduled_twitter_events.scheduled_twit_id from scheduled_twitter_events,twitter where twitter.twitter_id = scheduled_twitter_events.twitter_id AND scheduled_twitter_events.content_type ='$type' AND scheduled_twitter_events.pk_user ='$userkey'";
		
		$results = mysqli_query($this->link,$q);
		/* Error occurred, return given name by default */
		if(!$results || (mysqli_num_rows($results) < 1)){
			mysqli_free_result($results);
			return -1;
		}
		/* Return result array */
		$aResults = array();
		while ($row = $results->fetch_assoc()) {
			$aResults[] = array("scheduled_twit_id"=>$row['scheduled_twit_id'], "twitter_screenname"=>$row['twitter_screenname'], "scheduled_twit_date"=>$row['scheduled_twit_date'], "event_body"=>$row['event_body']);
		}
		mysqli_free_result($results);
		return $aResults;
	}
	
	
	/*
	 * returns array of current events
	 *
	 */

	public function returnCurrentEvents($cur_time) {
		# event table
		# 0 - public tweet
		# 1 - direct message
		
		$cur_time = mysqli_real_escape_string($this->link,$cur_time);
		
		$q = "select scheduled_twitter_events.*,twitter.oauth_token_secret,twitter.oauth_token from scheduled_twitter_events,twitter where scheduled_twitter_events.twitter_id = twitter.twitter_id AND scheduled_twit_date < '$cur_time'";
		
		$results = mysqli_query($this->link,$q);
		/* Error occurred, return given name by default */
		if(!$results || (mysqli_num_rows($results) < 1)){
			mysqli_free_result($results);
			return -1;
		}
		/* Return result array */
		$aResults = array();
		while ($row = $results->fetch_assoc()) {
			$aResults[] = array("scheduled_twit_id"=>$row['scheduled_twit_id'], "pk_user"=>$row['pk_user'],"oauth_token_secret"=>$row['oauth_token_secret'],"oauth_token"=>$row['oauth_token'],"dm_screenname"=>$row['dm_screenname'],"twitter_id"=>$row['twitter_id'],"content_type"=>$row['content_type'], "scheduled_twit_date"=>$row['scheduled_twit_date'], "event_body"=>$row['event_body']);
		}
		mysqli_free_result($results);
		return $aResults;
	}
	
	/**
	 * returns array of premium twitter users with re follow or dm new follower on
	 **/

	public function returnPremiums() {				
		$q = "select tw.*, u.pk_user, u.premium from twitter tw, users u where u.premium = 1 AND u.pk_user = tw.pk_user AND tw.dm_new_follow = 1 OR tw.re_follow = 1";
		
		$results = mysqli_query($this->link,$q);
		/* Error occurred, return given name by default */
		if(!$results || (mysqli_num_rows($results) < 1)){
			mysqli_free_result($results);
			return -1;
		}
		/* Return result array */
		$aResults = array();
		while ($row = $results->fetch_assoc()) {
			$aResults[] = array("twitter_id"=>$row['twitter_id'], "pk_user"=>$row['pk_user'],"dm_new_follow"=>$row['dm_new_follow'],"re_follow"=>$row['re_follow'],"oauth_token_secret"=>$row['oauth_token_secret'],"oauth_token"=>$row['oauth_token']);
		}
		mysqli_free_result($results);
		return $aResults;
	}
	
	/** 
	 * returns array of users who want their dm inbox purged
	 **/

	public function returnUserstoPurges() {				
		$q = "select tw.*, u.pk_user from twitter tw, users u where u.pk_user = tw.pk_user AND tw.purge_dm = 1";
		
		$results = mysqli_query($this->link,$q);
		/* Error occurred, return given name by default */
		if(!$results || (mysqli_num_rows($results) < 1)){
			mysqli_free_result($results);
			return -1;
		}
		/* Return result array */
		$aResults = array();
		while ($row = $results->fetch_assoc()) {
			$aResults[] = array("twitter_id"=>$row['twitter_id'], "pk_user"=>$row['pk_user'],"oauth_token_secret"=>$row['oauth_token_secret'],"oauth_token"=>$row['oauth_token']);
		}
		mysqli_free_result($results);
		return $aResults;
	}
   
   
	/*
	** registers a user in the system, and returns user key if successfull
	*/
	public function dbregister($email, $pass, $flname, $hash, $country_code, $timezone){
		
		$email 			= mysqli_real_escape_string($this->link,$email);
		$pass 			= mysqli_real_escape_string($this->link,$pass);
		$flname 		= mysqli_real_escape_string($this->link,$flname);
		$country_code	= mysqli_real_escape_string($this->link,$country_code);
		$timezone       = mysqli_real_escape_string($this->link,$timezone);
		
		$ip = getRealIpAddr();
		
		//############### INSERTION ###############	
		mysqli_autocommit($this->link,FALSE);
		mysqli_query($this->link,"SET NAMES 'utf8'");
		$q = "insert into users(pk_user,email,flname,password,usr_confirm_hash,country_code,usr_ip,timezone) values('NULL','$email','$flname','$pass','$hash','$country_code','$ip','$timezone')";
	    
		mysqli_query($this->link,$q);
		if(mysqli_errno($this->link)){
			mysqli_rollback($this->link);
			return -1;
		}
		else{
			mysqli_commit($this->link);
			$result = mysqli_query($this->link,'SELECT LAST_INSERT_ID() as lid');
			$obj = $result->fetch_object();
			$lastinsertedid = $obj->lid;
			$result->close();
			unset($obj);
			return $lastinsertedid;
		}
		return -1;
	}  
   

    
	/*
	 * checks if user with email "$email" did already the confirmation of the account
	 * */
    public function is_confirmed($username){
		$q = "SELECT usr_is_confirmed FROM users WHERE email = '$username'";	  	
		$results = mysqli_query($this->link,$q);
		$dbarray = mysqli_fetch_array($results,MYSQLI_ASSOC);
		$is_confirmed = $dbarray['usr_is_confirmed'];
		mysqli_free_result($results);
		if($is_confirmed == 1){
			return 1; //Success! 
		}
		else{
			return -1; //Indicates failure
		}
	} 

	/*
	* checks if user with email "$email" is blocked
	* */
    public function is_blocked($username){
		$q = "SELECT usr_is_blocked FROM users WHERE email = '$username'";	  	
		$results = mysqli_query($this->link,$q);
		$dbarray = mysqli_fetch_array($results,MYSQLI_ASSOC);
		$usr_is_blocked = $dbarray['usr_is_blocked'];
		mysqli_free_result($results);
		if($usr_is_blocked == 1){
			return 1; //blocked
		}
		else{
			return -1; //Indicates failure
		}
	} 
	
    /*
     * checks if the resethash is associated with the email in the users table
     */
	public function dbconfirmResetPasswordHash($email,$hash){
		$email = mysqli_real_escape_string($this->link,$email);
		$q = "SELECT pk_user FROM users WHERE email = '$email' and usr_resetpassword_hash = '$hash'";	
		$results = mysqli_query($this->link,$q);
		
		$numr = mysqli_num_rows($results);
		mysqli_free_result($results);	
		if($numr > 0) 
			return 1; 
		else
			return -1;
	}

	/**
    * updateUserField - Updates a field, specified by the field
    * parameter, in the user's row of the database, given the pk_user
    */
	public function updateUserField($userkey, $field, $value){
		$q = "UPDATE users SET ".$field." = '$value' WHERE pk_user = '$userkey'";	
		mysqli_query($this->link,$q);
		if(mysqli_errno($this->link)){
			return -1;
		}
		return 1;
	}
	
	/**
    * deleteUser - Deletes a User
    */
	public function deleteUser($userkey){
		$q = "DELETE from users WHERE pk_user = '$userkey'";	
		mysqli_query($this->link,$q);
		if(mysqli_errno($this->link)){
			return -1;
		}
		return 1;
	}
	
	/**
    * updateUserFieldEmail - Updates a field, in the user's row of the database, given the email
    */
	public function updateUserFieldEmail($email, $field, $value){
		$email = mysqli_real_escape_string($this->link,$email);
		$q = "UPDATE users SET ".$field." = '$value' WHERE email = '$email'";	
		return mysqli_query($this->link,$q);
	}
	
	/**
    * dbgetUserInfo - Returns the result array from a mysql
    * query asking for some data regarding
    * the given username(email). If query fails, NULL is returned.
    */
	public function dbgetUserInfoEmail($email){
		$email = mysqli_real_escape_string($this->link,$email);
		$q = "SELECT pk_user,email,usr_userid,premium,timezone FROM users WHERE email = '$email'";		
		$results = mysqli_query($this->link,$q);
		/* Error occurred, return given name by default */
		if(!$results || (mysqli_num_rows($results) < 1)){
			mysqli_free_result($results);
			return NULL;
		}
		/* Return result array */
		$dbarray = mysqli_fetch_array($results,MYSQLI_ASSOC);
		mysqli_free_result($results);
		return $dbarray;
	}
	
	/**
    * dbgetUserInfo - Returns the result array from a mysql
    * query asking for some data regarding
    * the given username(pk_user). If query fails, NULL is returned.
    */
	public function dbgetUserInfo($username){
		$username = mysqli_real_escape_string($this->link,$username);
		$q = "SELECT pk_user,email,usr_userid,premium,timezone FROM users WHERE pk_user = '$username'";		
		$results = mysqli_query($this->link,$q);
		/* Error occurred, return given name by default */
		if(!$results || (mysqli_num_rows($results) < 1)){
			mysqli_free_result($results);
			return NULL;
		}
		/* Return result array */
		$dbarray = mysqli_fetch_array($results,MYSQLI_ASSOC);
		mysqli_free_result($results);
		return $dbarray;
	}

	/**
    * dbgetUserAccountDetails - Returns the result array from a mysql
    * query asking for some data regarding
    * the given username(email). If query fails, NULL is returned.
    */
	public function dbgetUserAccountDetails($userkey){
		$q = "SELECT U.*,C.country_name FROM users U,Country C WHERE U.pk_user = '$userkey' AND C.country_code = U.country_code";
			
		$results = mysqli_query($this->link,$q);
		/* Error occurred, return given name by default */
		if(!$results || (mysqli_num_rows($results) < 1)){
			mysqli_free_result($results);
			return NULL;
		}
		/* Return result array */
		$dbarray = mysqli_fetch_array($results,MYSQLI_ASSOC);
		mysqli_free_result($results);
		return $dbarray;
	}	
	
	public function user_confirm($urlemail,$urlhash) {
		$new_hash = sha1($urlemail.supersecret_hash_padding);
		if ($new_hash && ($new_hash == $urlhash)) {
			$q = "SELECT email FROM users WHERE usr_confirm_hash = '$new_hash'";
			$results = mysqli_query($this->link,$q);
			
			if (!$results || (mysqli_num_rows($results) < 1)) {
				$feedback = 'ERROR -- Hash not found';
				mysqli_free_result($results);
				return $feedback;
			} 
			else {
			// Confirm the email and set account to active
			$email = $urlemail;
			$hash = $urlhash;

			$query = "UPDATE users SET usr_is_confirmed='1' WHERE usr_confirm_hash='$hash'";
			mysqli_query($this->link,$query);
			return 1;
			}
		} 
		else {
			$feedback = 'ERROR -- Values do not match';
			return $feedback;
		}
	}

	/*
	* checks if value matches a field in the table users
	*/
	public function matchUserField($value,$field,$userkey){
		$value 			= mysqli_real_escape_string($this->link,$value);
		$q = "SELECT pk_user FROM users WHERE ".$field." = '$value' and pk_user = '$userkey'";
		
		$results = mysqli_query($this->link,$q);
		$numr = mysqli_num_rows($results);
		mysqli_free_result($results);	
		return ($numr > 0);
	} 
	
	/*
	** changes the user account details, and returns 1 successfull
	*/
	public function dbeditaccount($email, $flname, $country_code, $pass, $userkey, $timezone){
		
		$email 			= mysqli_real_escape_string($this->link,$email);
		$pass 			= mysqli_real_escape_string($this->link,$pass);
		$flname 		= mysqli_real_escape_string($this->link,$flname);
		$country_code	= mysqli_real_escape_string($this->link,$country_code);
		$timezone		= mysqli_real_escape_string($this->link,$timezone);
		
		//############### UPDATE ###############	
		mysqli_autocommit($this->link,FALSE);
		mysqli_query($this->link,"SET NAMES 'utf8'");
		$q="";
		if($pass)
			$q = "UPDATE users SET email='$email',flname='$flname',password='$pass',country_code='$country_code',timezone='$timezone' where pk_user = '$userkey'";
	    else
	    	$q = "UPDATE users SET email='$email',flname='$flname',country_code='$country_code',timezone='$timezone' where pk_user = '$userkey'";
		
	    mysqli_query($this->link,$q);
		if(mysqli_errno($this->link)){
			mysqli_rollback($this->link);
			return -1;
		}
		else{
			mysqli_commit($this->link);
			return 1;
		}
		return -1;
	}  
	
	/*
	* checks if a country typed by the user exists in the table country. Returns the id of the country, or null
	*/
	public function dbexistsCountry($country_name){
		$country_name_lower = mb_strtolower(html_entity_decode($country_name,ENT_NOQUOTES, 'UTF-8'));
		$q = "SELECT country_code FROM Country WHERE LOWER(country_name) = '$country_name_lower'";	
		$results = mysqli_query($this->link,$q);		
		if(!$results || (mysqli_num_rows($results) < 1)){
			mysqli_free_result($results);
			return null; //Indicates country check failure
		}
		$dbarray = mysqli_fetch_array($results,MYSQLI_ASSOC);
		$dbarray['country_code'] = stripslashes($dbarray['country_code']);
		mysqli_free_result($results);	
		return $dbarray['country_code'];
	}  
	
	/**
	*	Increments the number of logins of a user
	**/
	public function incrementLogins($userkey){
		$q = "SELECT usr_nmb_logins FROM users WHERE pk_user = '$userkey'";
		$results = mysqli_query($this->link,$q);
		if(!$results || (mysqli_num_rows($results) < 1)){
			mysqli_free_result($results);
			return -1;
		}
		else{
			$dbarray = mysqli_fetch_array($results,MYSQLI_ASSOC);
			$nmb_logins = $dbarray['usr_nmb_logins'];
			$nmb_logins_inc = $nmb_logins + 1 ;
			mysqli_free_result($results);
			
			mysqli_autocommit($this->link,FALSE);
			$qu = "update users set usr_nmb_logins = '$nmb_logins_inc' WHERE pk_user = '$userkey'";
			mysqli_query($this->link,$qu);
			
			if(mysqli_errno($this->link)){
				mysqli_rollback($this->link);
				return -2;//Indicates error updating row
			}
			else{
				mysqli_commit($this->link);
				return 1;
			}
		}
		return -3;
	}
	
	/*
	 * returns the array with the users per country info
	 * note: it just includes the users that have their accounts confirmed!
	 * It does not includes the user viewing this (admin)
	 * */
	public function getUsersPerCountry($userkey){
		$q = "SELECT COUNT(*) AS value,users.country_code,country_name FROM users INNER JOIN Country ON Country.country_code = users.country_code WHERE usr_is_confirmed=1 and pk_user <> '$userkey' GROUP BY users.country_code";		
		$results = mysqli_query($this->link,$q);
		/* Error occurred, return given name by default */
		if(!$results || (mysqli_num_rows($results) < 1)){
			mysqli_free_result($results);
			return NULL;
		}
		/* Return result array */
		
		$aResults = array();
		while ($row = $results->fetch_assoc()) {
			$aResults[] = array( "country_name"=>$row['country_name'] ,"value"=>$row['value']);
		}
		mysqli_free_result($results);
		return $aResults;
	}

	/*
	 * returns the array with the users data
	 * */
	public function getUsersData($userkey){
		mysqli_query($this->link,"SET NAMES 'utf8'");	
		$q = "SELECT pk_user,country_name,email,flname,usr_ip,usr_nmb_logins,usr_signup_date,usr_is_blocked,usr_is_admin FROM users INNER JOIN Country ON Country.country_code=users.country_code WHERE usr_is_confirmed=1 and pk_user <> '$userkey'";		
		
		$results = mysqli_query($this->link,$q);
		/* Error occurred, return given name by default */
		if(!$results || (mysqli_num_rows($results) < 1)){
			mysqli_free_result($results);
			return NULL;
		}
		/* Return result array */
		$aResults = array();
		while ($row = $results->fetch_assoc()) {
			$aResults[] = array( "pk_user"=>$row['pk_user'] ,"country_name"=>$row['country_name'] ,"email"=>$row['email'],"flname"=>$row['flname'],"usr_ip"=>$row['usr_ip'],"usr_nmb_logins"=>$row['usr_nmb_logins'],"usr_signup_date"=>$row['usr_signup_date'],"usr_is_blocked"=>$row['usr_is_blocked'],"usr_is_admin"=>$row['usr_is_admin']);
		}
		mysqli_free_result($results);
		return $aResults;
	}
	
	/**
	 * returns a single scheduled event
	 */
	public function returnScheduledEvent($twid,$userkey) {
		# event table
		# 0 - public tweet
		# 1 - direct message
				
		$userkey = mysqli_real_escape_string($this->link,$userkey);
		$twid    = mysqli_real_escape_string($this->link,$twid);
		
		$q = "select sc.*, tw.twitter_screenname from scheduled_twitter_events sc, twitter tw where sc.pk_user = '$userkey' AND sc.scheduled_twit_id = '$twid' AND sc.twitter_id = tw.twitter_id";
		
		$results = mysqli_query($this->link,$q);
		/* Error occurred, return given name by default */
		if(!$results || (mysqli_num_rows($results) < 1)) {
			mysqli_free_result($results);
			return -1;
		}
		
		/* Return result array */
		$aResults = array();
		while ($row = $results->fetch_assoc()) {
			$aResults[] = array("twitter_screenname"=>$row['twitter_screenname'], "event_body"=>$row['event_body'], "scheduled_twit_date"=>$row['scheduled_twit_date']);
		}
		mysqli_free_result($results);
		return $aResults;
	}
	
	/**
	 * returns the twitter user data
	 */
	public function getTwitterData($userkey) {
		$q = "SELECT * FROM twitter WHERE pk_user = '$userkey'";
		$results = mysqli_query($this->link,$q);
		/* Error occurred, return given name by default */
		if(!$results || (mysqli_num_rows($results) < 1)){
			mysqli_free_result($results);
			return NULL;
		}
		/* Return result array */
		$aResults = array();
		while ($row = $results->fetch_assoc()) {
			$aResults[] = array("twitter_name"=>$row['twitter_name'], "twitter_id"=>$row['twitter_id'], "twitter_screenname"=>$row['twitter_screenname'], "friends_count"=>$row['friends_count'], "followers_count"=>$row['followers_count'], "profile_image_url"=>$row['profile_image_url'], "oauth_token"=>$row['oauth_token'], "oauth_token_secret"=>$row['oauth_token_secret'], "utc_diff"=>$row['utc_diff'], "purge_dm"=>$row['purge_dm'],"dm_new_follow"=>$row['dm_new_follow'], "re_follow"=>$row['re_follow']);
		}
		mysqli_free_result($results);
		return $aResults;
	}
	
	/**
	 * save twitter user data
	 */
	public function setTwitterData($pk_user, $twitter_name, $twitter_screenname, $friends_count, $followers_count, $profile_image_url, $oauth_token, $oauth_token_secret, $utc_diff) {
		$twitter_name 		= mysqli_real_escape_string($this->link,$twitter_name);
		$twitter_screenname = mysqli_real_escape_string($this->link,$twitter_screenname);
		$friends_count 		= mysqli_real_escape_string($this->link,$friends_count);
		$followers_count	= mysqli_real_escape_string($this->link,$followers_count);
		$profile_image_url 	= mysqli_real_escape_string($this->link,$profile_image_url);
		$oauth_token 		= mysqli_real_escape_string($this->link,$oauth_token);
		$oauth_token_secret = mysqli_real_escape_string($this->link,$oauth_token_secret);
		$utc_diff			= mysqli_real_escape_string($this->link,$utc_diff);
		//############### INSERTION ###############	
		mysqli_autocommit($this->link,FALSE);
		mysqli_query($this->link,"SET NAMES 'utf8'");
		$q = "insert into twitter(pk_user,twitter_name,twitter_screenname,friends_count,followers_count,profile_image_url,oauth_token,oauth_token_secret,utc_diff) values('$pk_user','$twitter_name','$twitter_screenname','$friends_count','$followers_count','$profile_image_url','$oauth_token','$oauth_token_secret','$utc_diff')";
	    
		mysqli_query($this->link,$q);
		if(mysqli_errno($this->link)){
			mysqli_rollback($this->link);
			return -1;
		}
		else{
			mysqli_commit($this->link);
			$result = mysqli_query($this->link,'SELECT LAST_INSERT_ID() as lid');
			$obj = $result->fetch_object();
			$lastinsertedid = $obj->lid;
			$result->close();
			unset($obj);
			return $lastinsertedid;
		}
		return -1;
	}
	
	/**
	 * returnTwitterUserNames - returns an array of all accounts we have stored for a user
	 */
	public function returnTwitterUserNames($pk_user) {
		$pk_user = mysqli_real_escape_string($this->link,$pk_user);
		$q = "SELECT twitter_name FROM twitter WHERE pk_user = '$pk_user'";
		$results = mysqli_query($this->link,$q);
		/* Error occurred, return given name by default */
		if(!$results || (mysqli_num_rows($results) < 1)){
			mysqli_free_result($results);
			return NULL;
		}
		/* Return result array */
		$aResults = array();
		while ($row = $results->fetch_assoc()) {
			$aResults[] = array("twitter_name"=>$row['twitter_name']);
		}
		mysqli_free_result($results);
		return $aResults;
	}
	
    /**
	 * returnDirectMessage - returns a direct message body
	 */
	public function returnDirectMessage($twid,$pk_user) {
		$twid = mysqli_real_escape_string($this->link,$twid);
		$pk_user = mysqli_real_escape_string($this->link,$pk_user);
		
		$q = "SELECT dm_body FROM direct_message WHERE twitter_id = '$twid' AND pk_user = '$pk_user'";
		$results = mysqli_query($this->link,$q);
		/* Error occurred, return given name by default */
		if(!$results || (mysqli_num_rows($results) < 1)){
			mysqli_free_result($results);
			return 0;
		}
		/* Return result array */
		$dbarray = mysqli_fetch_array($results,MYSQLI_ASSOC);
		mysqli_free_result($results);
		return $dbarray;
	}
	
	/**
	 * update twitter account
	 */
	public function updateTwitterAcct($twid, $pk_user, $twitterName, $twitterScreenName, $friendsCount, $followersCount, $profileImage, $utcDiff){		
		$twid 				= mysqli_real_escape_string($this->link,$twid);
		$twitterName 		= mysqli_real_escape_string($this->link,$twitterName);
		$twitterScreenName  = mysqli_real_escape_string($this->link,$twitterScreenName);
		$friendsCount 		= mysqli_real_escape_string($this->link,$friendsCount);
		$followersCount		= mysqli_real_escape_string($this->link,$followersCount);
		$profileImage 		= mysqli_real_escape_string($this->link,$profileImage);
		$utcDiff			= mysqli_real_escape_string($this->link,$utcDiff);
		
		//############### UPDATE ###############	
		mysqli_autocommit($this->link,FALSE);
		mysqli_query($this->link,"SET NAMES 'utf8'");
	    $q = "UPDATE twitter SET twitter_name='$twitterName',twitter_screenname='$twitterScreenName',friends_count='$friendsCount',followers_count='$followersCount',profile_image_url='$profileImage',utc_diff='$utcDiff' where pk_user='$pk_user' and twitter_id='$twid'";		
	    mysqli_query($this->link,$q);
		if(mysqli_errno($this->link)){
			mysqli_rollback($this->link);
			return -1;
		}
		else{
			mysqli_commit($this->link);
			return 1;
		}
		return -1;
	}
	
	/**
	 * update twitter event
	 */
	public function updateTwitterEvent($tw_id, $tw_sch, $tw_body, $pk_user) {		
		# event table
		# 0 - public tweet
		# 1 - direct message
		
		$tw_id   = mysqli_real_escape_string($this->link,$tw_id);
		$tw_sch  = mysqli_real_escape_string($this->link,$tw_sch);
		$tw_body = mysqli_real_escape_string($this->link,$tw_body);
		$pk_user = mysqli_real_escape_string($this->link,$pk_user);
		
		//############### UPDATE ###############	
		mysqli_autocommit($this->link,FALSE);
		mysqli_query($this->link,"SET NAMES 'utf8'");
	    $q = "UPDATE scheduled_twitter_events SET event_body='$tw_body',scheduled_twit_date='$tw_sch' where scheduled_twit_id='$tw_id' AND pk_user='$pk_user'";		
	    mysqli_query($this->link,$q);
		if(mysqli_errno($this->link)){
			mysqli_rollback($this->link);
			return -1;
		}
		else{
			mysqli_commit($this->link);
			return 1;
		}
		return -1;
	}
	
	/**
	 * updates a twitter auto dm status
	 */
	
	public function updateTwitterDM($twid,$pk_user,$status) {
		$twid    = mysqli_real_escape_string($this->link,$twid);
		$status  = mysqli_real_escape_string($this->link,$status);
		$pk_user = mysqli_real_escape_string($this->link,$pk_user);
		
		//############### UPDATE ###############	
		mysqli_autocommit($this->link,FALSE);
		mysqli_query($this->link,"SET NAMES 'utf8'");
	    $q = "UPDATE twitter SET dm_new_follow='$status' where twitter_id='$twid' AND pk_user='$pk_user'";		
	    mysqli_query($this->link,$q);
		if(mysqli_errno($this->link)){
			mysqli_rollback($this->link);
			return -1;
		}
		else{
			mysqli_commit($this->link);
			return 1;
		}
		return -1;
	}
	
	/**
	 * updates a twitter auto re follow status
	 */
	
	public function updateTwitterRe($twid,$pk_user,$status) {
		$twid    = mysqli_real_escape_string($this->link,$twid);
		$status  = mysqli_real_escape_string($this->link,$status);
		$pk_user = mysqli_real_escape_string($this->link,$pk_user);
		
		//############### UPDATE ###############	
		mysqli_autocommit($this->link,FALSE);
		mysqli_query($this->link,"SET NAMES 'utf8'");
	    $q = "UPDATE twitter SET re_follow='$status' where twitter_id='$twid' AND pk_user='$pk_user'";		
	    mysqli_query($this->link,$q);
		if(mysqli_errno($this->link)){
			mysqli_rollback($this->link);
			return -1;
		}
		else{
			mysqli_commit($this->link);
			return 1;
		}
		return -1;
	}
	
	/**
	 * updates a twitter purge dm status
	 */
	
	public function updateTwitterPG($twid,$pk_user,$status) {
		$twid    = mysqli_real_escape_string($this->link,$twid);
		$status  = mysqli_real_escape_string($this->link,$status);
		$pk_user = mysqli_real_escape_string($this->link,$pk_user);
		
		//############### UPDATE ###############	
		mysqli_autocommit($this->link,FALSE);
		mysqli_query($this->link,"SET NAMES 'utf8'");
	    $q = "UPDATE twitter SET purge_dm='$status' where twitter_id='$twid' AND pk_user='$pk_user'";		
	    mysqli_query($this->link,$q);
		if(mysqli_errno($this->link)){
			mysqli_rollback($this->link);
			return -1;
		}
		else{
			mysqli_commit($this->link);
			return 1;
		}
		return -1;
	}
	
	/**
	 * remove scheduled event
	 */
	public function removeScheduledEvent($twid,$pk_user) {		
		$twid    = mysqli_real_escape_string($this->link,$twid);
		$pk_user = mysqli_real_escape_string($this->link,$pk_user);
		$q = "DELETE from scheduled_twitter_events where scheduled_twit_id = '$twid' AND pk_user = '$pk_user'";
		mysqli_query($this->link,$q);
		if(mysqli_errno($this->link)){
			return -1;
		}
		return 1;		
	}
	
	/**
	 * remove twitter account
	 */
	public function removeTwitterAcct($twid,$twsn,$pk_user) {
		$twid    = mysqli_real_escape_string($this->link,$twid);
		$twsn    = mysqli_real_escape_string($this->link,$twsn);
		$pk_user = mysqli_real_escape_string($this->link,$pk_user);
		
		$q = "DELETE from scheduled_twitter_events where twitter_id = '$twid' AND pk_user = '$pk_user'";
		mysqli_query($this->link,$q);
		if(mysqli_errno($this->link)){
			return -1;
		}
		
		//tod delete direct messages stored
		$q = "DELETE from twitter where twitter_id = '$twid' AND pk_user = '$pk_user'";
		mysqli_query($this->link,$q);
		if(mysqli_errno($this->link)){
			return -1;
		}
		return $twsn;		
	}
	
	/**
	 * check for a saved dm
	 */
	public function checkSavedDM($twid,$pk_user) {
		$twid    = mysqli_real_escape_string($this->link,$twid);
		$pk_user    = mysqli_real_escape_string($this->link,$pk_user);
		
		$q = "SELECT dm_id FROM direct_message WHERE twitter_id = '$twid' AND pk_user = '$pk_user'";
		$results = mysqli_query($this->link,$q);
		if(!$results || (mysqli_num_rows($results) < 1)){
			mysqli_free_result($results);
			return 0;
		} else { 
			mysqli_free_result($results);
			return 1;
		}
	}
	
	/**
	 * update an existing saved dm
	 */
	public function updateExistingDM($twid,$pk_user,$body) {
		$twid    = mysqli_real_escape_string($this->link,$twid);
		$body    = mysqli_real_escape_string($this->link,$body);
		$pk_user = mysqli_real_escape_string($this->link,$pk_user);
		
		//############### UPDATE ###############	
		mysqli_autocommit($this->link,FALSE);
		mysqli_query($this->link,"SET NAMES 'utf8'");
	    $q = "UPDATE direct_message SET dm_body='$body' where twitter_id='$twid' AND pk_user='$pk_user'";		
	    mysqli_query($this->link,$q);
		if(mysqli_errno($this->link)){
			mysqli_rollback($this->link);
			return -1;
		}
		else{
			mysqli_commit($this->link);
			return 1;
		}
		return -1;
	}
	
	/**
	 * insert a new dm
	 */
	public function insertNewDM($twid,$pk_user,$body) {
		$twid    = mysqli_real_escape_string($this->link,$twid);
		$body    = mysqli_real_escape_string($this->link,$body);
		$pk_user = mysqli_real_escape_string($this->link,$pk_user);
		
		//############### INSERTION ###############	
		mysqli_autocommit($this->link,FALSE);
		mysqli_query($this->link,"SET NAMES 'utf8'");
		$q = "insert into direct_message(dm_id,twitter_id,pk_user,dm_body) values('NULL','$twid','$pk_user','$body')";
	    
		mysqli_query($this->link,$q);
		if(mysqli_errno($this->link)){
			mysqli_rollback($this->link);
			return -1;
		}
		else{
			mysqli_commit($this->link);
			$result = mysqli_query($this->link,'SELECT LAST_INSERT_ID() as lid');
			$obj = $result->fetch_object();
			$lastinsertedid = $obj->lid;
			$result->close();
			unset($obj);
			return $lastinsertedid;
		}
		return -1;
	}
	
	/**
	 * update the friends table
	 */
	public function updateFriends($twid,$pk_user,$friends,$time) {
		$twid    = mysqli_real_escape_string($this->link,$twid);
		$body    = mysqli_real_escape_string($this->link,$body);
		$friends = mysqli_real_escape_string($this->link,$friends);
		$time    = mysqli_real_escape_string($this->link,$time);
		
		//############### UPDATE ###############	
		mysqli_autocommit($this->link,FALSE);
		mysqli_query($this->link,"SET NAMES 'utf8'");
	    $q = "UPDATE twitter_friends SET friends_data='$friends', lastupdate='$time' where twitter_id='$twid' AND pk_user='$pk_user'";		
	    mysqli_query($this->link,$q);
		if(mysqli_errno($this->link)){
			mysqli_rollback($this->link);
			return -1;
		}
		else{
			mysqli_commit($this->link);
			return 1;
		}
		return -1;
	}
	
	/**
	 * update the followers table
	 */
	public function updateFollowers($twid,$pk_user,$followers,$time) {
		$twid      = mysqli_real_escape_string($this->link,$twid);
		$pk_user   = mysqli_real_escape_string($this->link,$pk_user);
		$followers = mysqli_real_escape_string($this->link,$followers);
		$time      = mysqli_real_escape_string($this->link,$time);
		
		//############### UPDATE ###############	
		mysqli_autocommit($this->link,FALSE);
		mysqli_query($this->link,"SET NAMES 'utf8'");
	    $q = "UPDATE twitter_followers SET followers_data='$followers', lastupdate='$time' where twitter_id='$twid' AND pk_user='$pk_user'";		
	    mysqli_query($this->link,$q);
		if(mysqli_errno($this->link)){
			mysqli_rollback($this->link);
			return -1;
		}
		else{
			mysqli_commit($this->link);
			return 1;
		}
		return -1;
	}
	
	/**
	 * insert a new friends row
	 */
	public function insertNewFriends($twid,$pk_user,$arrFriends,$time) {
		$twid       = mysqli_real_escape_string($this->link,$twid);
		$pk_user    = mysqli_real_escape_string($this->link,$pk_user);
		$arrFriends = mysqli_real_escape_string($this->link,$arrFriends);
		$time       = mysqli_real_escape_string($this->link,$time);
		
		//############### INSERTION ###############	
		mysqli_autocommit($this->link,FALSE);
		mysqli_query($this->link,"SET NAMES 'utf8'");
		$q = "insert into twitter_friends(twitter_friends_id,twitter_id,pk_user,friends_data,lastupdate) values('NULL','$twid','$pk_user','$arrFriends','$time')";
	    
		mysqli_query($this->link,$q);
		if(mysqli_errno($this->link)){
			mysqli_rollback($this->link);
			return -1;
		}
		else{
			mysqli_commit($this->link);
			$result = mysqli_query($this->link,'SELECT LAST_INSERT_ID() as lid');
			$obj = $result->fetch_object();
			$lastinsertedid = $obj->lid;
			$result->close();
			unset($obj);
			return $lastinsertedid;
		}
		return -1;
	}
	
	/**
	 * insert a new followers row
	 */
	public function insertNewFollowers($twid,$pk_user,$arrFollowers,$time) {
		$twid         = mysqli_real_escape_string($this->link,$twid);
		$pk_user      = mysqli_real_escape_string($this->link,$pk_user);
		$arrFollowers = mysqli_real_escape_string($this->link,$arrFollowers);
		$time         = mysqli_real_escape_string($this->link,$time);
		
		//############### INSERTION ###############	
		mysqli_autocommit($this->link,FALSE);
		mysqli_query($this->link,"SET NAMES 'utf8'");
		$q = "insert into twitter_followers(twitter_followers_id,twitter_id,pk_user,followers_data,lastupdate) values('NULL','$twid','$pk_user','$arrFollowers','$time')";
	    
		mysqli_query($this->link,$q);
		if(mysqli_errno($this->link)){
			mysqli_rollback($this->link);
			return -1;
		}
		else{
			mysqli_commit($this->link);
			$result = mysqli_query($this->link,'SELECT LAST_INSERT_ID() as lid');
			$obj = $result->fetch_object();
			$lastinsertedid = $obj->lid;
			$result->close();
			unset($obj);
			return $lastinsertedid;
		}
		return -1;
	}
	
	/**
	 * return friends of a twitter account
	 */
	public function returnTwitterFriends($twid,$pk_user) {
		$twid    = mysqli_real_escape_string($this->link,$twid);
		$pk_user = mysqli_real_escape_string($this->link,$pk_user);
		
		$q = "SELECT friends_data,lastupdate FROM twitter_friends WHERE pk_user = '$pk_user' AND twitter_id = '$twid'";
		$results = mysqli_query($this->link,$q);
		/* Error occurred, return given name by default */
		if(!$results || (mysqli_num_rows($results) < 1)){
			mysqli_free_result($results);
			return NULL;
		}
		
		/* Return result array */
		$dbarray = mysqli_fetch_array($results,MYSQLI_ASSOC);
		mysqli_free_result($results);
		return $dbarray;
	}
	
	/**
	 * return followers of a twitter account
	 */
	public function returnTwitterFollowers($twid,$pk_user) {
		$twid    = mysqli_real_escape_string($this->link,$twid);
		$pk_user = mysqli_real_escape_string($this->link,$pk_user);
		
		$q = "SELECT followers_data,lastupdate FROM twitter_followers WHERE pk_user = '$pk_user' AND twitter_id = '$twid'";
		$results = mysqli_query($this->link,$q);
		/* Error occurred, return given name by default */
		if(!$results || (mysqli_num_rows($results) < 1)){
			mysqli_free_result($results);
			return NULL;
		}
		
		/* Return result array */
		$dbarray = mysqli_fetch_array($results,MYSQLI_ASSOC);
		mysqli_free_result($results);
		return $dbarray;
	}
	
	// select tw.*, u.* from twitter tw, users u where u.premium = 1 AND u.pk_user = tw.pk_user AND tw.dm_new_follow = 1 OR tw.re_follow = 1;
	
	/**
    * query - Performs the given query on the database and
    * returns the result, which may be false, true or a
    * resource identifier.
    */
	public function query($query){
		return mysqli_query($this->link,$query);
	}
    
	public function disconnect(){
		mysqli_close($this->link);
	}

}; 
?>