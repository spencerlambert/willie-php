<?php
  define('BASE_PATH',dirname(__FILE__)); // Define the base path
  define('PAGE_ID','contact'); // Page ID
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
  require_once BASE_PATH.'/mailchimp/Mailchimp.php';

  //define('META_TITLE',''); // Override page title, description, keywords and page encoding here
  //define('META_DESCRIPTION','');
  //define('META_KEYWORDS','');
  //define('PAGE_ENCODING','');
  
 // define('META_TITLE',$lang['contactUs'].' &ndash; '.$config['settings']['site_title']); // Assign proper meta titles
  
  require_once BASE_PATH.'/assets/includes/header.inc.php';
  require_once BASE_PATH.'/assets/includes/errors.php';
  function isValidEmail($email){ 
      return filter_var($email, FILTER_VALIDATE_EMAIL);
  }

  //print_r($_POST);
  $email = $_POST['mailchimp_email'];
  //echo $email;
  
  $pattern = '/^(?!(?:(?:\\x22?\\x5C[\\x00-\\x7E]\\x22?)|(?:\\x22?[^\\x5C\\x22]\\x22?)){255,})(?!(?:(?:\\x22?\\x5C[\\x00-\\x7E]\\x22?)|(?:\\x22?[^\\x5C\\x22]\\x22?)){65,}@)(?:(?:[\\x21\\x23-\\x27\\x2A\\x2B\\x2D\\x2F-\\x39\\x3D\\x3F\\x5E-\\x7E]+)|(?:\\x22(?:[\\x01-\\x08\\x0B\\x0C\\x0E-\\x1F\\x21\\x23-\\x5B\\x5D-\\x7F]|(?:\\x5C[\\x00-\\x7F]))*\\x22))(?:\\.(?:(?:[\\x21\\x23-\\x27\\x2A\\x2B\\x2D\\x2F-\\x39\\x3D\\x3F\\x5E-\\x7E]+)|(?:\\x22(?:[\\x01-\\x08\\x0B\\x0C\\x0E-\\x1F\\x21\\x23-\\x5B\\x5D-\\x7F]|(?:\\x5C[\\x00-\\x7F]))*\\x22)))*@(?:(?:(?!.*[^.]{64,})(?:(?:(?:xn--)?[a-z0-9]+(?:-+[a-z0-9]+)*\\.){1,126}){1,}(?:(?:[a-z][a-z0-9]*)|(?:(?:xn--)[a-z0-9]+))(?:-+[a-z0-9]+)*)|(?:\\[(?:(?:IPv6:(?:(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){7})|(?:(?!(?:.*[a-f0-9][:\\]]){7,})(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,5})?::(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,5})?)))|(?:(?:IPv6:(?:(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){5}:)|(?:(?!(?:.*[a-f0-9]:){5,})(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,3})?::(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,3}:)?)))?(?:(?:25[0-5])|(?:2[0-4][0-9])|(?:1[0-9]{2})|(?:[1-9]?[0-9]))(?:\\.(?:(?:25[0-5])|(?:2[0-4][0-9])|(?:1[0-9]{2})|(?:[1-9]?[0-9]))){3}))\\]))$/iD';

  if (preg_match($pattern, $email) === 1)
  {
    $Mailchimp = new Mailchimp( $mailchimp_api_key );
    $Mailchimp_Lists = new Mailchimp_Lists( $Mailchimp );
    $Mailchimp_Lists->subscribe( $mailchimp_list_id, array( 'email' => $email ) );
    if ($Mailchimp->errorCode)
    {
      echo "subscription_error";
      exit;
    }
    else
    {
      echo "subscribed";
      exit;
    }
  }
  else
  {
    echo "invalid_email";
    exit;
  }

exit;
?>