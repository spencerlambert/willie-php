<?php
	/******************************************************************
	*  Copyright 2011 Ktools.net LLC - All Rights Reserved
	*  http://www.ktools.net
	*  Created: 4-21-2011
	*  Modified: 4-21-2011
	******************************************************************/
	
	define('BASE_PATH',dirname(__FILE__)); // Define the base path
	define('PAGE_ID','invoice'); // Page ID
	define('ACCESS','public'); // Page access type - public|private
	define('INIT_SMARTY',true); // Use Smarty
	
	require_once BASE_PATH.'/assets/includes/session.php';
	require_once BASE_PATH.'/assets/includes/initialize.php';
	require_once BASE_PATH.'/assets/includes/commands.php';
	require_once BASE_PATH.'/assets/includes/init.member.php';
	require_once BASE_PATH.'/assets/includes/security.inc.php';
	require_once BASE_PATH.'/assets/includes/language.inc.php';
	require_once BASE_PATH.'/assets/includes/cart.inc.php';
	require_once BASE_PATH.'/assets/includes/affiliate.inc.php';

	define('META_TITLE',''); // Override page title, description, keywords and page encoding here
	define('META_DESCRIPTION','');
	define('META_KEYWORDS','');
	define('PAGE_ENCODING','');
	
	require_once BASE_PATH.'/assets/includes/header.inc.php';
	require_once BASE_PATH.'/assets/includes/errors.php';
	require_once BASE_PATH.'/assets/classes/invoicetools.php';

	try
	{		
		$billTotal = new number_formatting; // Used to make sure the bills are showing in the admins currency
		$billTotal->set_custom_cur_defaults($config['settings']['defaultcur']);
		$parms['noDefault'] = true;
		
		if(!$billID and !$orderID) die('No bill/order ID was passed'); // Just to be safe make sure a bill/order ID was passed
		
		$invoiceObj = new invoiceTools; // Create new invoice object
		$invoiceObj->options = false; // Do not select options here
		
		$adminCurrency = getCurrencyInfo($config['settings']['defaultcur']);
		
		if($billID) // Invoice from billID
		{
			$invoiceObj->setUBillID($billID);			
			if($billInfo = $invoiceObj->getBillDetails())
			{	
				$invoice = $invoiceObj->getInvoiceDetailsViaBillDBID($billInfo['bill_id']);
				
				if($billInfo['bill_type'] == 2) // Bill made from bill me later orders
				{
					$ordersResult = mysqli_query($db,
						"
						SELECT * FROM {$dbinfo[pre]}orders 
						LEFT JOIN {$dbinfo[pre]}invoices 
						ON {$dbinfo[pre]}orders.order_id = {$dbinfo[pre]}invoices.order_id
						WHERE {$dbinfo[pre]}orders.bill_id = {$billInfo[bill_id]}
						"
					); // Select invoice items
					$invoiceItemsCount = mysqli_num_rows($ordersResult);
					while($order = mysqli_fetch_array($ordersResult))
					{
						$invoiceItems[$order['order_id']] = $order;
						$invoiceItems[$order['order_id']]['name'] = "{$lang[order]} {$order[order_number]}";
						$invoiceItems[$order['order_id']]['quantity'] = 1;
						
						if($invoice['payment_status'] != 2) // Other than unpaid - make sure the correct amount is shown - the amount should be shown in the admins currency
						{
							$invoiceItems[$order['order_id']]['price_total'] = $billTotal->currency_display($order['total'],1);
						}
						else
						{
							$priceTotal = getCorrectedPrice($order['total'],$parms); // Unpaid invoice can show in the members currency
							$invoiceItems[$order['order_id']]['price_total'] = $priceTotal['display'];
						}
					}
				}
				else // Regular bill
				{
					$invoiceItemsCount = $invoiceObj->queryInvoiceItems(); // Number of invoice items total				
					$invoiceItems = $invoiceObj->getInvoiceItemsRaw();
				}
			}
		}
		
		if($orderID) // Invoice from orderID
		{
			$invoiceObj->setOrderID($orderID); // Set the order ID			
			if($orderInfo = $invoiceObj->getOrderDetails())
			{
				$invoice = $invoiceObj->getInvoiceDetailsViaOrderDBID($orderInfo['order_id']);
				$invoiceObj->includeGalleries = true; // Include galleries in the result				
				$invoiceItemsCount = $invoiceObj->queryInvoiceItems(); // Number of invoice items total
				
				$invoiceItems = $invoiceObj->getAllInvoiceItems();
				/*
				foreach($invoiceItems as $invoiceItemKey => $invoiceItem)
				{
					$invoiceItems[$invoiceItemKey]['itemDetails']['galleries'] = 'test';
				}
				*/
			}
			else
				die("An order with this order id does not exist in our system.");
		}
			
		//print_k($invoiceItems); exit;
		
		//if($invoiceItemsCount > 0)
		//{	
			if($billID)
			{
				if($invoice['payment_status'] != 2) // Other than unpaid - make sure the correct amount is shown - the amount should be shown in the admins currency
				{
					$invoice['subtotal'] = $billTotal->currency_display($invoice['subtotal'],1);
					$invoice['total'] = $billTotal->currency_display($invoice['total'],1);
					
					$tax_total = $invoice['taxa_cost'] + $invoice['taxb_cost'] + $invoice['taxc_cost'];
					$invoice['tax_total'] = $billTotal->currency_display($tax_total,1);
					
					$invoice['taxa_cost'] = $billTotal->currency_display($invoice['taxa_cost'],1);
					$invoice['taxb_cost'] = $billTotal->currency_display($invoice['taxb_cost'],1);
					$invoice['taxc_cost'] = $billTotal->currency_display($invoice['taxc_cost'],1);
					
					$invoice['shipping_cost'] = $billTotal->currency_display($invoice['shipping_cost'],1);
					
					switch($invoice['payment_status'])
					{
						case 0: // PROCCESSING
						case 1: // PAID/APPROVED
							$invoice['payment'] = $invoice['total'];
							$invoice['balance'] = $billTotal->currency_display(0,1);
						break;
						case 4: // FAILED
						case 5: // REFUNDED
						case 6: // CANCELLED
							$invoice['payment'] = $billTotal->currency_display(0,1);
							$invoice['balance'] = $billTotal->currency_display(0,1);
						break;
					}
				}
				else
				{	
					$subTotalTemp = getCorrectedPrice($invoice['subtotal'],$parms); // Unpaid invoice can show in the members currency
					$invoice['subtotal'] = $subTotalTemp['display'];
					
					$totalTemp = getCorrectedPrice($invoice['total'],$parms); // Unpaid invoice can show in the members currency
					$invoice['total'] = $totalTemp['display'];
					
					$tax_total = $invoice['taxa_cost'] + $invoice['taxb_cost'] + $invoice['taxc_cost'];
					$taxTotalTemp = getCorrectedPrice($tax_total,$parms); // Unpaid invoice can show in the members currency
					$invoice['tax_total'] = $taxTotalTemp['display'];
					
					$taxaTemp = getCorrectedPrice($invoice['taxa_cost'],$parms); // Unpaid invoice can show in the members currency
					$invoice['taxa_cost'] = $taxaTemp['display'];
					
					$taxbTemp = getCorrectedPrice($invoice['taxb_cost'],$parms); // Unpaid invoice can show in the members currency
					$invoice['taxb_cost'] = $taxbTemp['display'];
					
					$taxcTemp = getCorrectedPrice($invoice['taxc_cost'],$parms); // Unpaid invoice can show in the members currency
					$invoice['taxc_cost'] = $taxcTemp['display'];
					
					$paymentTemp = getCorrectedPrice(0,$parms); // Unpaid invoice can show in the members currency
					$invoice['payment'] = $paymentTemp['display'];
					$invoice['balance'] = $invoice['total'];
					
					$shippingTemp = getCorrectedPrice($invoice['shipping_cost'],$parms); // Unpaid invoice can show in the members currency
					$invoice['shipping_cost'] = $shippingTemp['display'];
				}
								
				if($billInfo['bill_type'] == 1) // Items
				{
					foreach($invoiceItems as $invoiceItemKey => $invoiceItem)
					{						
						if($invoice['payment_status'] != 2) // Other than unpaid - make sure the correct amount is shown - the amount should be shown in the admins currency
						{
							$invoiceItems[$invoiceItemKey]['price_total'] = $billTotal->currency_display($invoiceItems[$invoiceItemKey]['price_total'],1);
							$invoiceItems[$invoiceItemKey]['cost_value'] = $billTotal->currency_display($invoiceItems[$invoiceItemKey]['price_total'],1);
						}
						else
						{
							$priceTotal = getCorrectedPrice($invoiceItems[$invoiceItemKey]['price_total'],$parms); // Unpaid invoice can show in the members currency
							$invoiceItems[$invoiceItemKey]['price_total'] = $priceTotal['display'];
							$invoiceItems[$invoiceItemKey]['cost_value'] = $priceTotal['display'];
						}
						
						$invoiceItems[$invoiceItemKey]['name'] = $invoiceItems[$invoiceItemKey]['description']; // Set the quantity to 1
						$invoiceItems[$invoiceItemKey]['quantity'] = 1; // Set the quantity to 1
					}
				}
				
				if($invoice['bill_type'] == 2) // Orders
				{	
					/*
					$itemsResult = mysqli_query($db,
						"
						SELECT * FROM {$dbinfo[pre]}orders 
						LEFT JOIN {$dbinfo[pre]}invoices 
						ON {$dbinfo[pre]}orders.order_id = {$dbinfo[pre]}invoices.order_id
						WHERE {$dbinfo[pre]}orders.bill_id = {$invoice[bill_id]}
						"
					); // Select invoice items
					while($item = mysqli_fetch_array($itemsResult))
					{
						$invoice['items'][$item['order_id']] = $item;
						
						if($invoice['payment_status'] != 2) // Other than unpaid - make sure the correct amount is shown - the amount should be shown in the admins currency
						{
							$invoice['items'][$item['order_id']]['price_total']['display'] = $billTotal->currency_display($item['subtotal'],1);
						}
						else
						{
							$invoice['items'][$item['order_id']]['price_total'] = getCorrectedPrice($item['total'],$parms); // Unpaid invoice can show in the members currency
						}
						
						$orderDate = $customDate->showdate($item['order_date'],0);
						
						$invoice['items'][$item['order_id']]['description'] = 'Order Number[t]: <strong>'.$item['order_number']."</strong> ({$orderDate})";
						
						$invoice['items'][$item['order_id']]['quantity'] = 1;
					}
					*/
				}
			}
			
			if($orderID)
			{
				foreach($invoiceItems as $invoiceItemKey => $invoiceItem)
				{
					$invoiceItems[$invoiceItemKey]['price_total'] = $billTotal->currency_display($invoiceItems[$invoiceItemKey]['price_total'],1);
					$invoiceItems[$invoiceItemKey]['cost_value'] = ($invoiceItem['paytype'] == 'cur') ? $billTotal->currency_display($invoiceItem['price_total'],1) : $invoiceItem['credits_total'];
					
					$invoiceItems[$invoiceItemKey]['name'] = $invoiceItems[$invoiceItemKey]['itemDetails']['name']; // Shortcut to name of item
					
					if($invoiceItems[$invoiceItemKey]['itemDetails']['media'])
						$invoiceItems[$invoiceItemKey]['thumbnail'] = "<img src='image.php?mediaID=".$invoiceItems[$invoiceItemKey]['itemDetails']['media']['encryptedID']."&type=thumb&folderID=".$invoiceItems[$invoiceItemKey]['itemDetails']['media']['encryptedFID']."&size=45' />";
					else if($invoiceItems[$invoiceItemKey]['itemDetails']['photo'])
						$invoiceItems[$invoiceItemKey]['thumbnail'] = "<img src='productshot.php?itemID=".$invoiceItems[$invoiceItemKey]['item_id']."&itemType=".$invoiceItems[$invoiceItemKey]['itemTypeShort']."&photoID=".$invoiceItems[$invoiceItemKey]['itemDetails']['photo']['id']."&size=45' />";
					
					//http://localhost:1978/productshot.php?itemID=1&itemType=credit&photoID=4&size=60
					
					/*
					{if $cartItem.itemDetails.media}
						<a href="media.details.php?mediaID={$cartItem.itemDetails.media.useMediaID}"><img src="image.php?mediaID={$cartItem.itemDetails.media.encryptedID}=&type=rollover&folderID={$cartItem.itemDetails.media.encryptedFID}==&size=60" class="thumb" /></a>
					{elseif $cartItem.itemDetails.photo}
						<img src="{productShot itemID=$cartItem.item_id itemType=$cartItem.itemTypeShort photoID=$cartItem.itemDetails.photo.id size=60}" class="thumb" />
					{else}
						<img src="{$imgPath}/spacer.png" style="height: 1px; width: 79px" /><!-- Spacer -->
					{/if}
					*/
					//print_r($invoiceItem);
					if($invoiceItem['rm_selections'])
					{	
						foreach(explode(',',$invoiceItem['rm_selections']) as $value)
						{
							if($value)
								$rm = explode(':',$value);
							
							if($rm[0])
							{
								$rmGroupResult = mysqli_query($db,"SELECT og_name FROM {$dbinfo[pre]}rm_option_grp WHERE og_id = '{$rm[0]}'");
								if($rmGroupRows = mysqli_num_rows($rmGroupResult))
								{
									$rmGroup = mysqli_fetch_assoc($rmGroupResult);
									
									$rmOptionResult = mysqli_query($db,"SELECT op_name FROM {$dbinfo[pre]}rm_options WHERE op_id = '{$rm[1]}'");
									$rmOption = mysqli_fetch_assoc($rmOptionResult);
									
									$invoiceItems[$invoiceItemKey]['rm'][] = array('grpName' => $rmGroup['og_name'],'opName' => $rmOption['op_name']);
									//echo "<li style='margin: 4px 0'><strong>{$rmGroup[og_name]}</strong>: {$rmOption[op_name]}</li>";	
								}
							}							
							unset($rm);
						}
					}
					
					//print_r($invoiceItems[$invoiceItemKey]['rm']); 
					//echo '<br /><br />';
				}
				
				$invoice['subtotal'] = $billTotal->currency_display($invoice['subtotal'],1);
				$invoice['total'] = $billTotal->currency_display($invoice['total'],1);
				$invoice['discounts_total'] = $billTotal->currency_display($invoice['discounts_total']*-1,1);
				
				$tax_total = $invoice['taxa_cost'] + $invoice['taxb_cost'] + $invoice['taxc_cost'];
				$invoice['tax_total'] = $billTotal->currency_display($tax_total,1);
				
				// Tax
				$invoice['taxa_cost'] = $billTotal->currency_display($invoice['taxa_cost'],1);
				$invoice['taxb_cost'] = $billTotal->currency_display($invoice['taxb_cost'],1);
				$invoice['taxc_cost'] = $billTotal->currency_display($invoice['taxc_cost'],1);
				
				// Credits
				$invoice['credits_subtotal'] = $invoice['credits_total']+$invoice['discounts_credits_total'];
				$invoice['credits_discounts_total'] = $invoice['discounts_credits_total']*-1;
				$invoice['credits_total'] = $invoice['credits_total'];
				
				$invoice['shipping_cost'] = $billTotal->currency_display($invoice['shipping_cost'],1);
				
				switch($invoice['payment_status'])
				{
					case 0: // PROCCESSING
					case 1: // PAID/APPROVED
						$invoice['payment'] = $invoice['total'];
						$invoice['balance'] = $billTotal->currency_display(0,1);
					break;
					case 3: // BILL LATER
						$invoice['payment'] = $billTotal->currency_display(0,1);
						$invoice['balance'] = $invoice['total'];
					break;
					case 4: // FAILED
					case 5: // REFUNDED
					case 6: // CANCELLED
						$invoice['payment'] = $billTotal->currency_display(0,1);
						$invoice['balance'] = $billTotal->currency_display(0,1);
					break;
				}
			}
		
			$invoice['invoice_date_display'] = $customDate->showdate($invoice['invoice_date'],0); // Get corrected dates
			$invoice['due_date_display'] = $customDate->showdate($invoice['due_date'],0);
			
			
			//echo $invoiceItems[1204]['asset_id'];
			//print_k($invoiceItems); exit;
			
			$smarty->assign('adminCurrency',$adminCurrency); // Admins currency info 
			$smarty->assign('order',$orderInfo); // Assign order details
			$smarty->assign('invoiceItems',$invoiceItems); // Assign invoice details
			$smarty->assign('invoice',$invoice); // Assign invoice details
			$smarty->assign('invoiceItemsCount',$invoiceItemsCount); // Number of invoice items
			
			$content = getDatabaseContent('invoiceTemplate'); // Get db content
			$content['body'] = $smarty->fetch('eval:'.$content['body']); // Evaluate the content from the db
			$smarty->assign('content',$content); // Output content to smarty
			
			$smarty->display('invoice.tpl');
		//}
		//else
		//	die('Order is empty - no invoice Items');
	}
	catch(Exception $e)
	{
		echo $e->getMessage();
	}
	
	include BASE_PATH.'/assets/includes/debug.php';
	if($db) mysqli_close($db); // Close any database connections
?>