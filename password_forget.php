<?php
$jsextra = '<script type="text/javascript" language="javascript" src="javascript/jquery-1.3.2.js"></script><script type="text/javascript" language="javascript" src="javascript/register/passwprocess.js"></script>';
$title = 'Password Recover';
include("includes/header.php");
?>
        <div id="main" class="login">
            <h1>Password Request</h1>
            <div id="pagecontent" class="forgotpw">
                <p>Type your email. You'll receive information on how to reset your password:</p>
                <form action="" method="" name="form_passwprocess" id="form_passwprocess">
                    <label>email</label>
                    <input class="inplaceError" type="text" id="email" name="email" maxlength="120" value=""/>
                    <input type="hidden" name="forgetpasswordaction" value="1"/>
                    <div style="clear:both;"></div>
					<div id="email_error" class="error">
                        <!--div class="errorimg" style="display:none;">This is an error</div-->
                    </div>
                    <a id="_forgetpassw_btt" class="button">Send</a>                  
                    <img style="display:none;margin-bottom:15px;" class="ajaxload" id="ajaxld" src="images/ajax-loader.gif"/>                  
                </form>                
            </div>
            <br /><div class="linkback"><a href="index.php">Back</a></div>
        </div>
    </body>
</html>
