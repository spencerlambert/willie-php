<?php
	define('BASE_PATH',dirname(__FILE__)); // Define the base path
	
	require_once BASE_PATH.'/assets/includes/session.php';
	require_once BASE_PATH.'/assets/includes/initialize.php';

	if($_GET['clear'] == 1)
	{
		unset($_SESSION['testing']);
		header("location: output.testing.php");
		exit;
	}

	//$_SESSION['testing']['mytest'] = '';
	
	echo "Testing: <br>";

	if($_SESSION['testing'])
	{
		foreach($_SESSION['testing'] as $key => $value)
		{
			if(is_array($value))
			{
				echo "{$key}: ";
				print_r($value);
				echo "<br>";
			}
			else
				echo "{$key}: {$value}<br>";
		}
	}
	else
		echo "No Testing Session Exists";

	//print_r($_SESSION['testing']);
	
	echo "<br><br><a href='output.testing.php?clear=1'>Clear Testing Session</a>";

?>