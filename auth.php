<?php

/**
 * Demonstration of the various OAuth flows. You would typically do this
 * when an unknown user is first using your application. Instead of storing
 * the token and secret in the session you would probably store them in a
 * secure database with their logon details for your website.
 *
 * When the user next visits the site, or you wish to act on their behalf,
 * you would use those tokens and skip this entire process.
 *
 * The Sign in with Twitter flow directs users to the oauth/authenticate
 * endpoint which does not support the direct message permission. To obtain
 * direct message permissions you must use the "Authorize Application" flows.
 *
 * Instructions:
 * 1) If you don't have one already, create a Twitter application on
 *      http://dev.twitter.com/apps
 * 2) From the application details page copy the consumer key and consumer
 *      secret into the place in this code marked with (YOUR_CONSUMER_KEY
 *      and YOUR_CONSUMER_SECRET)
 * 3) Visit this page using your web browser.
 *
 * @author themattharris
 */

require_once("includes/core.php");
$objCore = new Core();

$objCore->initSessionInfo();
$objCore->initFormController();
$tmhOAuth = new tmhOAuth(array(
  'consumer_key'    => CONSUMER_KEY,
  'consumer_secret' => CONSUMER_SECRET,
));

$header = 'Allow Access to Twitter';
//include("includes/header.php");
if($objCore->getSessionInfo()->isLoggedIn()){

	$here = tmhUtilities::php_self();

	function outputError($tmhOAuth) {
	  echo 'Error: ' . $tmhOAuth->response['response'] . PHP_EOL;
	  tmhUtilities::pr($tmhOAuth);
	}
	
	// app denied
	if ( isset($_REQUEST['denied'])) {
		unset($_SESSION['oauth']);
		header("Location: index.php");
	}		

	// reset request?
	if ( isset($_REQUEST['wipe'])) {
	  session_destroy();
	  header("Location: {$here}");

	// already got some credentials stored?
	} elseif ( isset($_SESSION['access_token']) ) {
	  $tmhOAuth->config['user_token']  = $_SESSION['access_token']['oauth_token'];
	  $tmhOAuth->config['user_secret'] = $_SESSION['access_token']['oauth_token_secret'];

	  $code = $tmhOAuth->request('GET', $tmhOAuth->url('1/account/verify_credentials'));
	  if ($code == 200) {
		$resp = json_decode($tmhOAuth->response['response']);
		if($objCore->saveTwitterData($objCore->getSessionInfo()->getUserKey(), $resp->name, $resp->screen_name, $resp->friends_count, $resp->followers_count, $resp->profile_image_url, $_SESSION['access_token']['oauth_token'], $_SESSION['access_token']['oauth_token_secret'], $resp->utc_offset)) {
			$objCore->loadTwitterObject();
			unset($_SESSION['access_token']);
			$_SESSION['successfull_add'] = true;
			header("Location: index.php");
		} else {
			echo "<h1>There has been an error</h1>";
			echo "Not able to save twitter information to database!";
		}
	  } else {
		outputError($tmhOAuth);
	  }
	// we're being called back by Twitter
	} elseif (isset($_REQUEST['oauth_verifier'])) {
	  $tmhOAuth->config['user_token']  = $_SESSION['oauth']['oauth_token'];
	  $tmhOAuth->config['user_secret'] = $_SESSION['oauth']['oauth_token_secret'];

	  $code = $tmhOAuth->request('POST', $tmhOAuth->url('oauth/access_token', ''), array(
		'oauth_verifier' => $_REQUEST['oauth_verifier']
	  ));

	  if ($code == 200) {
		$_SESSION['access_token'] = $tmhOAuth->extract_params($tmhOAuth->response['response']);
		unset($_SESSION['oauth']);
		header("Location: {$here}");
	  } else {
		outputError($tmhOAuth);
	  }
	// start the OAuth dance
	} elseif ( isset($_REQUEST['authenticate']) || isset($_REQUEST['authorize']) ) {
	  $callback = isset($_REQUEST['oob']) ? 'oob' : $here;

	  $params = array(
		'oauth_callback'     => $callback
	  );

	  if (isset($_REQUEST['force_write'])) :
		$params['x_auth_access_type'] = 'write';
	  elseif (isset($_REQUEST['force_read'])) :
		$params['x_auth_access_type'] = 'read';
	  endif;

	  $code = $tmhOAuth->request('POST', $tmhOAuth->url('oauth/request_token', ''), $params);

	  if ($code == 200) {
		$_SESSION['oauth'] = $tmhOAuth->extract_params($tmhOAuth->response['response']);
		$method = isset($_REQUEST['authenticate']) ? 'authenticate' : 'authorize';
		$force  = isset($_REQUEST['force']) ? '&force_login=1' : '';
		$authurl = $tmhOAuth->url("oauth/{$method}", '') .  "?oauth_token={$_SESSION['oauth']['oauth_token']}{$force}";
		header("Location: {$authurl}");
		echo '<h1>Twitter Authorization</h1><br /><p><a href="'. $authurl . '">To complete the twiter authorization, click here</a></p>';
	  } else {
		outputError($tmhOAuth);
	  }
	}
}else{
  	header("Location: index.php");
}
unset($objCore);

?>
