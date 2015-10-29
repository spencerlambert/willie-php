<?php
	/******************************************************************
	*  Copyright 2011 Ktools.net LLC - All Rights Reserved
	*  http://www.ktools.net
	*  Created: 4-25-2011
	*  Modified: 5-26-2011
	******************************************************************/
	
	define('BASE_PATH',dirname(__FILE__)); // Define the base path

	require_once BASE_PATH.'/assets/includes/session.php';
	require_once BASE_PATH.'/assets/includes/initialize.php';
	require_once BASE_PATH.'/assets/includes/commands.php';
	require_once BASE_PATH.'/assets/includes/init.member.php';
	require_once BASE_PATH.'/assets/includes/security.inc.php';
	require_once BASE_PATH.'/assets/includes/language.inc.php';
	require_once BASE_PATH.'/assets/includes/cart.inc.php';
	require_once BASE_PATH.'/assets/includes/affiliate.inc.php';
	require_once BASE_PATH.'/assets/includes/header.inc.php';
	require_once BASE_PATH.'/assets/includes/errors.php';
	require_once BASE_PATH.'/assets/classes/imagetools.php';
	require_once BASE_PATH.'/assets/classes/mediatools.php';
	require_once BASE_PATH.'/assets/classes/colors.php';
	
	try
	{	
		$colorPalette = new GetMostCommonColors();
		
		if(!$_GET['id'])
			die("No media ID was passed");
			
		if(!is_numeric($_GET['id']))
			die("Need an unencrypted media ID");
		
		$mediaResult = mysqli_query($db,"SELECT * FROM {$dbinfo[pre]}media WHERE media_id = '{$_GET[id]}'"); // Select the media info
		$mediaRows = mysqli_num_rows($mediaResult);
		
		if($mediaRows)
		{
			$media = mysqli_fetch_assoc($mediaResult);
			
			$mediaInfo = new mediaTools($media['media_id']);
			$folderInfo = $mediaInfo->getFolderInfoFromDB($media['folder_id']);
			$folderName = $mediaInfo->getFolderName();
			$thumbInfo = $mediaInfo->getThumbInfoFromDB($media['media_id']);
			
			$path = BASE_PATH."/assets/library/{$folderName}/thumbs/{$thumbInfo[thumb_filename]}"; // xxxxxx No encryption detection
			
			$colors= $colorPalette->Get_Color($path, $config['cpResults'], $config['cpReduceBrightness'], $config['cpReduceGradients'], $config['cpDelta']);
			
			$encMediaID = k_encrypt($media['media_id']);
			$encFolderID = k_encrypt($media['folder_id']);
		}
		else
			die("Media ID not found");
			
	}
	catch(Exception $e)
	{
		echo $e->getMessage();
	}
	

/*
function colorPalette($imageFile, $numColors, $granularity = 5)  
{  
   $granularity = max(1, abs((int)$granularity));  
   $colors = array();  
   $size = @getimagesize($imageFile);  
   if($size === false)  
   {  
      user_error("Unable to get image size data");  
      return false;  
   }  
   //$img = @imagecreatefromjpeg($imageFile);    
   $img = @imagecreatefromstring(file_get_contents($imageFile));
 
   if(!$img)  
   {  
      user_error("Unable to open image file");  
      return false;  
   }
   
   $countedPixels=round(($size[0]*$size[1])/$granularity);
   
   for($x = 0; $x < $size[0]; $x += $granularity)  
   {  
      for($y = 0; $y < $size[1]; $y += $granularity)  
      {  
         $thisColor = imagecolorat($img, $x, $y);  
         $rgb = imagecolorsforindex($img, $thisColor);  
         $red = round(round(($rgb['red'] / 0x33)) * 0x33);  
         $green = round(round(($rgb['green'] / 0x33)) * 0x33);  
         $blue = round(round(($rgb['blue'] / 0x33)) * 0x33);  
         $thisRGB = sprintf('%02X%02X%02X', $red, $green, $blue);  
		 
		 $colorsRGB[$thisRGB]['red'] = $red; // Added this to get the RGB colors from the array as well as the hex colors
		 $colorsRGB[$thisRGB]['green'] = $green;
		 $colorsRGB[$thisRGB]['blue'] = $blue;
		 $colorsRGB[$thisRGB]['thisColorCount']++;
		 
		 if(array_key_exists($thisRGB, $colors))
            $colors[$thisRGB]++;
         else  
            $colors[$thisRGB] = 1;
      }  
   }  
   arsort($colors);
  
  $colorArray = array_slice(array_keys($colors), 0, $numColors); 
   
   foreach($colorArray as $key => $color) // Added this to allow me to grab RGB also
   {
	  $multiColorArray[$key]['hex'] = $color;
	  $multiColorArray[$key]['rgb']['red'] = $colorsRGB[$color]['red'];
	  $multiColorArray[$key]['rgb']['green'] = $colorsRGB[$color]['green'];
	  $multiColorArray[$key]['rgb']['blue'] = $colorsRGB[$color]['blue'];
	  $multiColorArray[$key]['countedPixels'] = $countedPixels;
	  $multiColorArray[$key]['thisColorCount'] = $colorsRGB[$color]['thisColorCount'];
	  $multiColorArray[$key]['colorPercentage'] = round($colorsRGB[$color]['thisColorCount']/$countedPixels,6);
   }
   
   return $multiColorArray;  
}  
// sample usage:  
$palette = colorPalette('C:\UniServer\www\assets\library\2012-02-03\thumbs\thumb_DSC00741.jpg', 10, 10);  
echo "<table>\n";  
foreach($palette as $color)  
{  
   echo "<tr><td style='background-color:#{$color[hex]};width:2em;'>&nbsp;</td><td>#{$color[hex]} {$color[rgb][red]}-{$color[rgb][green]}-{$color[rgb][blue]} ||| Color Times: {$color[thisColorCount]} ||| Total Colors: {$color[countedPixels]} ||| Percentage: {$color[colorPercentage]}</td></tr>\n";  
}  
echo "</table>\n"; 

//print_r($palette);
*/
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" xml:lang="en">
<head>
	<title>Image Color Extraction</title>
	<style type="text/css">
		* {margin: 0; padding: 0}
		body {text-align: center;}
		div#wrap {margin: 10px auto; text-align: left; position: relative; width: 500px;}
		img {width: 200px;}
		table {border: solid #000 1px; border-collapse: collapse;}
		td {border: solid #000 1px; padding: 2px 5px; white-space: nowrap;}
		br {width: 100%; height: 1px; clear: both; }
	</style>
</head>
<body>
<div id="wrap">
<table>
<tr><td>Color</td><td>Color Code</td><td>Percentage</td><td rowspan="<?php echo (($num_results > 0)?($num_results+1):22500);?>"><img src="<?php echo "image.php?mediaID={$encMediaID}&type=thumb&folderID={$encFolderID}"; ?>" alt="test image" /></td></tr>
<?php

foreach ( $colors as $hex => $count )
{
	if ( $count > 0 )
	{
		$rgbColor = html2rgb($hex);
		
		echo "<tr><td style=\"background-color:#".$hex.";\"></td><td>".$hex."<br>rgb($rgbColor[red],$rgbColor[green],$rgbColor[blue])</td><td>$count</td></tr>";
		
		unset($rgbColor);
	}
}
?>
</table>

<br />
</div>
</body>
</html>
