<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1); 
//error_reporting(E_ALL); // turn error reporting on
error_reporting(0); // turn error reporting off

define("DB_SERVER", "localhost");
define("DB_USER", "XXXXXXX");
define("DB_PASS", "XXXXXXX");
define("DB_NAME", "XXXXXXX");

/**
 * Cookie Constants - these are the parameters
 * to the setcookie function call, change them
 * if necessary to fit your website. If you need
 * help, visit www.php.net for more info.
 * <http://www.php.net/manual/en/function.setcookie.php>
 */
define("GUEST_NAME", "Guest");
define("COOKIE_EXPIRE", 60*60*24*100);  //100 days by default
define("COOKIE_PATH", "/");  //Avaible in whole domain
/**
 * Email Constants - these specify what goes in
 * the from field in the emails that the script
 * sends to users, and whether to send a
 * welcome email to newly registered users.
 */
define("EMAIL_FROM_NAME", "admin");
define("EMAIL_FROM_ADDR", "null.net");
/**
 * This constant forces all users to have
 * lowercase usernames, capital letters are
 * converted automatically.
 */
define("ALL_LOWERCASE", false);

/**
 *For hashing purposes  
 **/
define("supersecret_hash_padding","XXXXXXXXXXXXXXX");
define("supersecret_hash_padding_2","XXXXXXXXXXXXXXX");
define("YAS","XXXXXXXXXXXXXXX");

/**
 *If you want that the user has to repeat the E-Mail and/or the Password
 *in the registration form , set the following to true or false  
 **/
define("REPEAT_EMAIL",true);
define("REPEAT_PASSWORD",true);


/*
 * the link on your server to the file resetpassword.php and confirm.php
 * these are gonna be used in the mail body 
 * */
define("RESETPASSWORDLINK","http://null.net/test/resetpassword.php");
define("CONFIRMACCOUNTLINK","http://null.net/test/includes/confirm.php");

/*
 * recaptcha keys:
 * */
define("PUBLICKEY","XXXXXXXXXXXXXXX");
define("PRIVATEKEY","XXXXXXXXXXXXXXX");

/**
 * twitter api keys
 */
define("CONSUMER_KEY","XXXXXXXXXXXXXXX");
define("CONSUMER_SECRET","XXXXXXXXXXXXXXX"); 
?>
