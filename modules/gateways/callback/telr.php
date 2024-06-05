<?php 

use WHMCS\Database\Capsule;

# Required File Includes
include("../../../init.php");
include("../../../includes/functions.php");
include("../../../includes/gatewayfunctions.php");
include("../../../includes/invoicefunctions.php");

$gatewayParams  = getGatewayVariables('telr');
if (!$gatewayParams["type"]) die("Module Not Activated");

if(isset($_GET['invoiceid']) && $_GET['invoiceid'] > 0){
	$invoiceId 				= $_GET['invoiceid'];
	$accountId 				= $gatewayParams["accountID"];
	$secretKey 				= $gatewayParams["secretKey"];
	$whcmsRedirectInvoice 	= $gatewayParams['systemurl'].'viewinvoice.php?id='.$invoiceId; 
	# Checks invoice ID is a valid invoice number or ends processing
	$invoiceId = checkCbInvoiceID($invoiceId,$gatewayParams["name"]); 	
	if($invoiceId){
		$results = Capsule::table('mod_telrpayment')->where('invoiceid', $invoiceId)->first();
		$telrRef = $results->telr_ref;
		
		$data = array(
			'ivp_method'  => "check",
			'ivp_store'   => $accountId ,
			'order_ref'   => $telrRef,
			'ivp_authkey' => $secretKey,
		);
		
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, 'https://secure.telr.com/gateway/order.json');
		curl_setopt($ch, CURLOPT_POST, count($data));
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Expect:'));
		$results = curl_exec($ch);
		curl_close($ch);
		$response = json_decode($results, true);	
		
		
		if (array_key_exists("order", $response)) {
			$order_status       = $response['order']['status']['code'];
			$transaction_status = $response['order']['transaction']['status'];
			$transactionRef = $response['order']['transaction']['ref'];
			$amount = $response['order']['transaction']['amount'];
			$fee = 0;
			
			$validTransactionRef = checkCbTransID($transactionRef); # Checks transaction number isn't already in the database and ends processing if it does
			if ($transaction_status == 'A') {
				switch ($order_status) {
					case '2':
					case '3':						
						addInvoicePayment($invoiceId,$transactionRef,$amount,$fee,'telr'); 
						logTransaction('telr',$_GET,"Successful");
						header("Location: $whcmsRedirectInvoice");
					default:
						$errorMsg = 'Invalid response status : '.$transaction_status;
						break;
				}
			}else{
				$errorMsg = 'Invalid response status : '.$transaction_status;
			}
				
		}else{
			$errorMsg = 'Order details not present in response';
		}	
	}else{
		$errorMsg = 'Invalid response invoiceid :'.$invoiceId;
	}
}else{
	$errorMsg = 'Invalid response invoiceid :'.$invoiceId;
}


logTransaction('telr',$errorMsg,"Error");
header("Location: $whcmsRedirectInvoice");

?>