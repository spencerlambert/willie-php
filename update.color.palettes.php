<?php
	/******************************************************************
	*  Copyright 2011 Ktools.net LLC - All Rights Reserved
	*  http://www.ktools.net
	*  Created: 4-25-2011
	*  Modified: 5-26-2011
	*  This file can be used to update all color palettes that are missing
	******************************************************************/
	
	define('BASE_PATH',dirname(__FILE__)); // Define the base path

	require_once BASE_PATH.'/assets/includes/session.php';

	$skipInitialize = array('sessionDestroy'); // Action cases when the includes will be skipped

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

	$colorPalette = new GetMostCommonColors();
	
	try
	{
		$x = 0;
		$mediaResult = mysqli_query($db,"SELECT * FROM {$dbinfo[pre]}media WHERE active = 1"); // Select the active media
		$mediaRows = mysqli_num_rows($mediaResult);
		while($media = mysqli_fetch_assoc($mediaResult))
		{
			$colorPaletteResult = mysqli_query($db,"SELECT * FROM {$dbinfo[pre]}color_palettes WHERE media_id = '{$media[media_id]}' ORDER BY cp_id");
			$colorPaletteRows = mysqli_num_rows($colorPaletteResult);
			if(!$colorPaletteRows and $x < 10)
			{
				$mediaInfo = new mediaTools($media['media_id']);
				$folderInfo = $mediaInfo->getFolderInfoFromDB($media['folder_id']);
				$folderName = $mediaInfo->getFolderName();
				$thumbInfo = $mediaInfo->getThumbInfoFromDB($media['media_id']);
				
				$path = BASE_PATH."/assets/library/{$folderName}/thumbs/{$thumbInfo[thumb_filename]}"; // xxxxxx No encryption detection
				
				//$image = new imagetools($path);
				//$colorPalette = $image->colorPalette(10,5);
				
				if($config['cpResults'] > 0)
					$colors = $colorPalette->Get_Color($path, $config['cpResults'], $config['cpReduceBrightness'], $config['cpReduceGradients'], $config['cpDelta']);
				
				@mysqli_query($db,"DELETE FROM {$dbinfo[pre]}color_palettes WHERE media_id = '{$media[media_id]}'"); // Delete old color palette first - just in case
				
				if(count($colors) > 0)
				{
				
					echo "Updated: {$media[media_id]}<br>";
					
					// Save color palette
					foreach($colors as $hex => $percentage)
					{
						if($percentage > 0)
						{
							$percentage = round($percentage,6);
							$rgb = html2rgb($hex);
							
							echo "{$hex} - rgb({$rgb[red]},{$rgb[green]},{$rgb[blue]}) - {$percentage}<br>";
						
							mysqli_query($db,
							"
								INSERT INTO {$dbinfo[pre]}color_palettes (
								media_id,
								hex,
								red,
								green,
								blue,
								percentage
								) VALUES (
								'{$media[media_id]}',
								'{$hex}',
								'{$rgb[red]}',
								'{$rgb[green]}',
								'{$rgb[blue]}',
								'{$percentage}')
							");
						}
					}
					//print_r($thumbInfo);
					echo "<br><br>";
					
					$x++;
				}	
				
			}
		}
	}
	catch(Exception $e)
	{
		die($e->getMessage());
	}
	
	if($db) mysqli_close($db); // Close any database connections
?>