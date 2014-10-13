<?php
require_once("core.php");

if(isset($_POST['registeractionx'])||
   isset($_POST['loginaction'])||
   isset($_POST['forgetpasswordaction'])||
   isset($_POST['resetpasswordaction'])||
   isset($_POST['editaccountactionx'])||
   isset($_POST['updatetwitteraccount'])||
   isset($_POST['schedulenewtweet'])||
   isset($_POST['removetwitteraccount'])||
   isset($_POST['refollowtwitteraccount'])||
   isset($_POST['processrefollowopt'])||
   isset($_POST['edittweet'])||
   isset($_POST['deletetweet'])||
   isset($_POST['processdmmessage'])||
   isset($_POST['dmselecttwitteraccount'])||
   isset($_POST['selpurgetwitteraccount'])||
   isset($_POST['processdmfinal'])||
   isset($_POST['updateexistingtweet'])){
	$objCore = new Core();
	$objCore->initSessionInfo();
	$objCore->initFormController();
	$objCore->initMailerService();
	$objCore->dispatchAction();
	unset($objCore);
}
else if(isset($_GET['logoutaction'])){
	$objCore = new Core();
	$objCore->initSessionInfo();
	$objCore->dispatchAction();
	unset($objCore);
}
else if(isset($_GET['mapdata'])){
	$objCore = new Core();
	$objCore->initSessionInfo();
	if($objCore->isAdmin())
		$objCore->dispatchAction();
	else{ 
		unset($objCore);
		header("Location: ../index.php");
	}		
	unset($objCore);
}
else if(isset($_POST['adminopactionx'])){
	$objCore = new Core();
	$objCore->initSessionInfo();
	if($objCore->isAdmin())
		$objCore->dispatchAction();
	else{ 
		unset($objCore);
		header("Location: ../index.php");
	}		
	unset($objCore);
}
else{
	header("Location: ../index.php");
}
?>
