<?php
	/* this is the header file so that we dont have to keep copy pasting such things around */
	if(!isset($dirdeep)) { $dirdeep = ''; }
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Strict//EN">
<html>
    <head>
        <title><?php if(isset($title)) { echo $title; } ?></title>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">      
        <link rel="stylesheet" type="text/css" href="<?php echo $dirdeep; ?>css/style.css" />
		<?php if(isset($cssextra)) { echo $cssextra; } ?>
        <script type="text/javascript" language="javascript" src="<?php echo $dirdeep; ?>javascript/jquery-1.6.2.min.js"></script>
        <?php if(isset($jsextra)) { echo $jsextra; } ?>
    </head>
    <body>
		<div id="fixed">
		<?php
			echo "<a href=\"".$dirdeep."index.php\">Home</a><br />";
			if($objCore->isAdmin())
	        	echo "<a href=\"".$dirdeep."admin.php\">Admin Panel</a> &nbsp;&nbsp;<br />";
	        if(isset($_SESSION['twitter_exists'])) {
				echo "<a href='".$dirdeep."twitter/'>Twitter Control Panel</a><br />";
			}
			if($objCore->getSessionInfo()->isLoggedIn()){
				echo "<a href=\"".$dirdeep."editaccount.php\">Edit Account</a> &nbsp;&nbsp;<br />";
				echo "<a href=\"".$dirdeep."includes/corecontroller.php?logoutaction=1\">Logout</a>";
			} else {
				echo "<a href=\"".$dirdeep."index.php\">Login</a><br />";
				echo "<a href=\"".$dirdeep."register\">Register</a>";
			}
		?>
		</div>
		<div id="scroller">
		<div id="content">
