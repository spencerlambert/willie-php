<?php
session_start();
include('assets/php/db_conec.php');
define('USER_TIME_ZONE',date('P'));
define('FILE_PATH','assets/logs/');
define('FILE_EXT','.html');
define('POST_FILE_LOCATION','assets/php/post.php');
date_default_timezone_set(USER_TIME_ZONE);
//echo time() . " " . USER_TIME_ZONE . " " . $_SESSION['name'];

//$_SESSION['name'] = "kris";
//unset($_SESSION['name']);

if(isset($_GET['logout'])){	
	
	//Simple exit message
	$fp = fopen(FILE_PATH . $_SESSION['name'] . FILE_EXT, 'a');
	fwrite($fp, "<div class='msgln'><i>User ". $_SESSION['name'] ." has left the chat session.</i><br></div>");
	fclose($fp);
	
	session_destroy();
	header("Location: index.php"); //Redirect the user
}

function loginForm(){
	echo '
		<div id="loginform">
		<form action="index.php" method="post">
			<p>Please enter your name to continue:</p>
			<label for="name">Name:</label>
			<input type="text" name="name" id="name" />
			<input type="submit" name="enter" id="enter" value="Enter" />
		</form>
		</div>
	';
}

if(isset($_POST['enter'])){
	if($_POST['name'] != ""){
		$_SESSION['name'] = stripslashes(htmlspecialchars($_POST['name']));
		$ourFileName = FILE_PATH . $_SESSION['name'] . FILE_EXT;
		$ourFileHandle = fopen($ourFileName, 'w') or die("can't open file");
		fclose($ourFileHandle);
		mysql_query("INSERT INTO logs ( username ) VALUES ( '$_SESSION[name]' )", $db) or die(mysql_error());
	}
	else{
		echo '<span class="error">Please type in a name</span>';
	}
}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<title>Chat - Customer Area</title>
<link type="text/css" rel="stylesheet" href="assets/css/style.css" />
</head>

<?php
if(!isset($_SESSION['name'])){
	loginForm();
}
else{
?>
<div id="wrapper">
	<div id="menu">
		<p class="welcome">Welcome, <b><?php echo $_SESSION['name']; ?></b></p>
		<p class="logout"><a id="exit" href="#">Exit Chat</a></p>
		<div style="clear:both"></div>
	</div>	
	<div id="chatbox"><?php
	if(file_exists(FILE_PATH . $_POST['name'] . FILE_EXT) && filesize(FILE_PATH . $_POST['name'] . FILE_EXT) > 0){
		$handle = fopen(FILE_PATH . $_POST['name'] . FILE_EXT, "r");
		$contents = fread($handle, filesize(FILE_PATH . $_POST['name'] . FILE_EXT));
		fclose($handle);
		
		echo $contents;
	}
	?></div>
	
	<form name="message" action="">
		<input name="usermsg" type="text" id="usermsg" size="63" onclick="loadurl('assets/php/mark_read.php')" />
		<input name="submitmsg" type="submit"  id="submitmsg" value="Send" />
	</form>
</div>
<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.3/jquery.min.js"></script>
<script type="text/javascript">
//WHEN THE USE CLICKS THE TEXT AREA MARK THE MESSAGE READ
$(document).ready(function()
{
    $("#usermsg").click(function() {
        $.post("assets/php/mark_read.php", { id: 12 }, function(data){
            
        });
    });
});

//SHOW NEW FUNCTION THAT WILL ALL THE SCRIPT TO SEE IF THE ADMIN HAS REPLIED 
function show_new() {
	$(document).ready(function() {
		$.post("assets/php/status.php", { id: 12 }, function(data) {
            if(data == '1') {
				$("#wrapper").fadeTo(100, 0.1).fadeTo(200, 1.0);
				//alert('data = 1');	
			}
        });
	});
}
setInterval (show_new, 2500);

// ALLOWS THE USER TO SUBMIT A MESSAGE
$(document).ready(function(){
	//IF THE USER SUBMITS THE FORM
	$("#submitmsg").click(function(){	
		var clientmsg = $("#usermsg").val();
		$.post("assets/php/post.php", {text: clientmsg});				
		$("#usermsg").attr("value", "");
		return false;
	});
	
	//LOAD THAT USERS FILE THAT CONATINS THE CHAT LOG
	function loadLog(){		
		var oldscrollHeight = $("#chatbox").attr("scrollHeight") - 20;
		$.ajax({
			url: "<?php echo FILE_PATH . $_SESSION['name'] . FILE_EXT; ?>",
			cache: false,
			success: function(html){		
				$("#chatbox").html(html); //Insert chat log into the #chatbox div				
				var newscrollHeight = $("#chatbox").attr("scrollHeight") - 20;
				if(newscrollHeight > oldscrollHeight){
					$("#chatbox").animate({ scrollTop: newscrollHeight }, 'normal'); //Autoscroll to bottom of div
				}				
		  	},
		});
	}
	setInterval (loadLog, 2500);	//Reload file every 2.5 seconds
	
	//If user wants to end session
	$("#exit").click(function(){
		var exit = confirm("Are you sure you want to end the session?");
		if(exit==true){window.location = 'index.php?logout=true';}		
	});
});
</script>
<?php
}
?>
</body>
</html>