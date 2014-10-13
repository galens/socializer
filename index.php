<?php
require_once("includes/core.php");
$objCore = new Core();
//session_destroy();
//echo "<pre>";
//print_r($_SESSION);
//exit;

$objCore->initSessionInfo();
$objCore->initFormController();
$jsextra = '<script type="text/javascript" language="javascript" src="javascript/index.js"></script>';
$header  = 'Welcome to our site';
include("includes/header.php");
        if($objCore->getSessionInfo()->isLoggedIn()){
	        echo "<h1>Welcome to autonomous social</h1>";
	        if(!isset($_SESSION['twitter_exists'])) {
				echo "<h3></h2>Our application has yet to gain access to your twitter account.<br />
				<a href='auth.php?authorize=1'>Give our application permission</a></h3>";
			} else {
				if(isset($_SESSION['successfull_add'])) {
					echo "<h3>You have successfully added a twitter user account to this system!</h3><br /><a href='twitter/index.php'>Click here to continue</a>";
					unset($_SESSION['successfull_add']);
				}
			}
        }
        else{
        ?>     
           <h1>Login</h1>	 
            <form name="login" id="login" action="includes/corecontroller.php" method="POST" class="login">
                <label>email</label>
                <input class="inplaceError" style="width:140px;" type="text" id="email" name="email" maxlength="120" value="<?php echo $objCore->getFormController()->value("email"); ?>"/>
                <span></span>
                <label>password</label>
                <input class="inplaceError" style="width:140px;" type="password" id="pass" name="pass" maxlength="20" value="<?php echo $objCore->getFormController()->value("pass"); ?>"/>
                <span></span>
                <div class="login_row">
                    <input type="checkbox" name="remember" <?php if($objCore->getFormController()->value("remember") != ""){ echo "checked"; } ?>/>
                    <label>remember me</label>
                </div>
                <input type="hidden" name="loginaction" value="1"/>
				<a class="button" id="login_button">Login</a>
                <div id="loginerror" class="error">
                <?php echo $objCore->getFormController()->error("email"); ?>
                <?php echo $objCore->getFormController()->error("pass"); ?>
            </div>
                <p>Did you forget your password? Click <a href="password_forget.php">here</a></p>
				<p>Don't have an account yet? <a href="register">Register</a></p>
            </form>
        <?php
        }
        unset($objCore);
        ?>
       </div>
       </div>
    </body>
</html>
