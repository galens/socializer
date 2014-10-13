<?php

// minute events
// when triggered, checks for any scheduled events and sends to twitter

require_once("includes/dbcontroller.php");
require_once("includes/tmhOAuth.php");
require_once("includes/tmhUtilities.php");

$cur_time  = time();
$dbcontrol = new DBController();
$dbreturn  = $dbcontrol->returnCurrentEvents($cur_time);

# event table
# 0 - public tweet
# 1 - direct message

if($dbreturn != -1) {
	foreach($dbreturn as $row) {
		$tmhOAuth = new tmhOAuth(array(
		  'consumer_key'    => CONSUMER_KEY,
		  'consumer_secret' => CONSUMER_SECRET,
		  'user_token'      => $row['oauth_token'],
		  'user_secret'     => $row['oauth_token_secret'],
		));
		
		if($row['content_type'] == 0) {
			$code = $tmhOAuth->request('POST', $tmhOAuth->url('1/statuses/update'), array(
			  'status' => $row['event_body']
			));
			
			// check the rate limit
			check_rate_limit($tmhOAuth->response);

			if ($code == 200) {
				$dbcontrol->removeScheduledEvent($row['twitter_id']);
				//tmhUtilities::pr(json_decode($tmhOAuth->response['response']));
			}
			else {
				$myFile = "includes/log/tweet.error.log";
				$fh = fopen($myFile, 'a') or die("can't open file");
				fwrite($fh, $tmhOAuth->response['response']);
				fclose($fh);
			}
		} elseif($row['content_type'] == 1) {
			$code = $tmhOAuth->request('POST', $tmhOAuth->url('1/direct_messages/new'), array(
			  'text' => $row['event_body'], 'wrap_links' => 'true', 'screen_name' => $row['dm_screenname']
			));
			
			// check the rate limit
			check_rate_limit($tmhOAuth->response);

			if ($code == 200) {
				$dbcontrol->removeScheduledEvent($row['twitter_id']);
				//tmhUtilities::pr(json_decode($tmhOAuth->response['response']));
			}
			else {
				$myFile = "includes/log/dm.error.log";
				$fh = fopen($myFile, 'a') or die("can't open file");
				fwrite($fh, $tmhOAuth->response['response']);
				fclose($fh);
			}
		}
	}
}

function check_rate_limit($response) {
  $headers = $response['headers'];
  if ($headers['x_ratelimit_remaining'] == 0) :
	$reset = $headers['x_ratelimit_reset'];
	$sleep = time() - $reset;
	#echo 'rate limited. reset time is ' . $reset . PHP_EOL;
	#echo 'sleeping for ' . $sleep . ' seconds';
	sleep($sleep);
  endif;
}

?>