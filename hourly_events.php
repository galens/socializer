<?php

// todo add logging

// hourly events
// when triggered, returns all users who want to purge their inbox and does exactly that

require_once("includes/core.php");

$objCore  = new Core();
$objCore->initSessionInfo();
$dbreturn = $objCore->returnUserstoPurge();

//echo "<pre>";
//print_r($dbreturn);
//exit;

if($dbreturn != -1) {
	foreach($dbreturn as $row) {
		$tmhOAuth = new tmhOAuth(array(
		  'consumer_key'    => CONSUMER_KEY,
		  'consumer_secret' => CONSUMER_SECRET,
		  'user_token'      => $row['oauth_token'],
		  'user_secret'     => $row['oauth_token_secret'],
		));
		
		$arrdms = $objCore->returnAllDMS($tmhOAuth);
		
		if(!empty($arrdms)) {
			$objCore->deleteAllDM($tmhOAuth,$arrdms);
		}

		// assuming it finished without error, turn off purge dm
		$objCore->updateTwitterPurge($row['twitter_id'],$row['pk_user'],0);
		unset($tmhOAuth);
	}
}

?>