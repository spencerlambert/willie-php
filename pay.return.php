<?php
	/******************************************************************
	*  Copyright 2011 Ktools.net LLC - All Rights Reserved
	*  http://www.ktools.net
	*  Created: 2-13-2012
	*  Modified: 2-13-2012
	******************************************************************/

	$orderID = $_GET['orderID']; // Convert to local

	/*
	* Redirect the customer to the correct location
	*/
	$explodedOrderID = explode('-',$orderID); // Explode the value to check for bill
	if($explodedOrderID[0] == 'bill') // Payment for a bill
	{
		$billID = str_replace('bill-','',$orderID); // Get order ID without bill- in it		
		header("location: bill.details.php?billID={$billID}"); // Redirect to bill details page
		exit;
	}
	else
	{
		header("location: order.details.php?orderID={$orderID}"); // Redirect to order details page
		exit;
	}	
?>