<?php

// todo add logging

// daily events
// when triggered, return premium users who have either refollow or dm auto respond turned on
// iterate through the list grabbing friends and followers, refollow and auto respond

require_once("includes/core.php");

$objCore  = new Core();
$objCore->initSessionInfo();
$dbreturn = $objCore->returnPremiumFollowers();

//echo "<pre>";
//print_r($dbreturn);
//exit;

/*
grab friends from twitter
grab friends from db

if refollow is on
	if empty, no difference
		update timestamp
	else
		follow those ids returned from follow array_diff
		update timestamp
		
if auto dm msg is on
	if empty, no difference
		update timestamp
	else
*/

if($dbreturn != -1) {
	foreach($dbreturn as $row) {
		$tmhOAuth = new tmhOAuth(array(
		  'consumer_key'    => CONSUMER_KEY,
		  'consumer_secret' => CONSUMER_SECRET,
		  'user_token'      => $row['oauth_token'],
		  'user_secret'     => $row['oauth_token_secret'],
		));
		
		$twitFollowers = $objCore->returnTwitterFols($row['twitter_id'],$row['pk_user']);
		$remoteIds     = $objCore->getTwitterFollowers($row['oauth_token'],$row['oauth_token_secret']);
		
		if($twitFollowers) {
			$localIds = unserialize($twitFollowers['followers_data']);
			if(empty($localIds)) {
				$localIds = array();
			}
		} else {
			$localIds = array();
		}
		
		if($remoteIds) {
			// a list of followers was returned
			$newids = array_diff($remoteIds,$localIds);
				
			if($newids) {
				// there is a difference in arrays, process these new ids				
				foreach ($newids as $id) {
					if($row['re_follow'] == 1) { // refollow is turned on
						$objCore->followTwitterUser($tmhOAuth,$id);
					}
					
					if($row['dm_new_follow'] == 1) { // dm new follower is turned on
						$dmbody = $objCore->returnDirectMessageBody($row['twitter_id'],$row['pk_user']);
						if($dmbody != 0 ) {
							$objCore->dmTwitterUser($tmhOAuth,$id,$dmbody['dm_body']);
						}
					}
				}
			}
		}
		
		$objCore->updateFollower($row['twitter_id'],$row['pk_user'],serialize($remoteIds),time()); // update
		$objCore->processUpdateTwitterQuiet($tmhOAuth,$row['twitter_id'],$row['pk_user']);

		unset($tmhOAuth);
	}
}

?>