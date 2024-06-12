<?php
/**
 * WHMCS Sample Payment Gateway Module
 *
 * Payment Gateway modules allow you to integrate payment solutions with the
 * WHMCS platform.
 *
 * This sample file demonstrates how a payment gateway module for WHMCS should
 * be structured and all supported functionality it can contain.
 *
 * Within the module itself, all functions must be prefixed with the module
 * filename, followed by an underscore, and then the function name. For this
 * example file, the filename is "gatewaymodule" and therefore all functions
 * begin "gatewaymodule_".
 *
 * If your module or third party API does not support a given function, you
 * should not define that function within your module. Only the _config
 * function is required.
 *
 * For more information, please refer to the online documentation.
 *
 * @see https://developers.whmcs.com/payment-gateways/
 *
 * @copyright Copyright (c) WHMCS Limited 2017
 * @license http://www.whmcs.com/license/ WHMCS Eula
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use WHMCS\Database\Capsule;

/**
 * Define module related meta data.
 *
 * Values returned here are used to determine module related capabilities and
 * settings.
 *
 * @see https://developers.whmcs.com/payment-gateways/meta-data-params/
 *
 * @return array
 */
function telr_MetaData()
{
    return array(
        'DisplayName' => 'Telr Payments',
        'APIVersion' => '1.1', // Use API Version 1.1
        'DisableLocalCreditCardInput' => true,
        'TokenisedStorage' => false,
    );
}

/**
 * Define gateway configuration options.
 *
 * The fields you define here determine the configuration options that are
 * presented to administrator users when activating and configuring your
 * payment gateway module for use.
 *
 * Supported field types include:
 * * text
 * * password
 * * yesno
 * * dropdown
 * * radio
 * * textarea
 *
 * Examples of each field type and their possible configuration parameters are
 * provided in the sample function below.
 *
 * @return array
 */
function telr_config()
{
	if(!(Capsule::schema()->hasTable('mod_telrpayment'))){
		Capsule::schema()
		->create(
			'mod_telrpayment',
			function ($table) {
				/** @var \Illuminate\Database\Schema\Blueprint $table */
				$table->increments('id');
				$table->integer('invoiceid');
				$table->string('telr_ref');
				$table->timestamps();
			}
		);
	}
			
    return array(
        // the friendly display name for a payment gateway should be
        // defined here for backwards compatibility
        'FriendlyName' => array(
            'Type' => 'System',
            'Value' => 'Telr Payments (Standard)',
        ),		
        'testMode' => array(
            'FriendlyName' => 'Test Mode',
            'Type' => 'yesno',
            'Description' => 'Tick to enable test mode',
        ),       
        'accountID' => array(
            'FriendlyName' => 'Store ID',
            'Type' => 'text',
            'Size' => '25',
            'Default' => '',
            'Description' => 'Enter your account ID here',
        ),
        'secretKey' => array(
            'FriendlyName' => 'Authentication Key',
            'Type' => 'text',
            'Size' => '25',
            'Default' => '',
            'Description' => 'Enter authentication key here',
        ),
        'remoteSecretKey' => array(
            'FriendlyName' => 'Remote Authentication Key',
            'Type' => 'text',
            'Size' => '25',
            'Default' => '',
            'Description' => 'Enter remote authentication key here. Required for Subscription and refund Payments.',
        ),   
        'language' => array(
            'FriendlyName' => 'Language',
            'Type' => 'dropdown',
            'Options' => array(
                'en' => 'English',
                'ar' => 'Arabic',
            ),
            'Description' => 'Choose one',
        )		      
    );
}


/**
 * Payment link.
 *
 * Required by third party payment gateway modules only.
 *
 * Defines the HTML output displayed on an invoice. Typically consists of an
 * HTML form that will take the user to the payment gateway endpoint.
 *
 * @param array $params Payment Gateway Module Parameters
 *
 * @see https://developers.whmcs.com/payment-gateways/third-party-gateway/
 *
 * @return string
 */
function telr_link($params)
{
	$pdo = Capsule::connection()->getPdo();
    $pdo->beginTransaction();

    // Gateway Configuration Parameters	
    $accountId      = $params['accountID'];
    $secretKey      = $params['secretKey'];
    $testMode       = ($params['testMode'] == 'on') ? 1 : 0;
    $language       = $params['language'];
    $paymentMode    = 0;

    // Invoice Parameters
    $invoiceId = $params['invoiceid'];
    $description = $params["description"];
    $payAmount = $params['amount'];
    $currencyCode = $params['currency'];

    // Client Parameters
    $firstname = $params['clientdetails']['firstname'];
    $lastname = $params['clientdetails']['lastname'];
    $email = $params['clientdetails']['email'];
    $address1 = $params['clientdetails']['address1'];
    $address2 = $params['clientdetails']['address2'];
    $city = $params['clientdetails']['city'];
    $state = $params['clientdetails']['state'];
    $postcode = $params['clientdetails']['postcode'];
    $country = $params['clientdetails']['country'];
    $phone = $params['clientdetails']['phonenumber'];

    // System Parameters
    $companyName = $params['companyname'];
    
    $langPayNow = $params['langpaynow'];
    $moduleDisplayName = $params['name'];
    $moduleName = $params['paymentmethod'];
    $whmcsVersion = $params['whmcsVersion'];
	
	$returnUrl       = $params['systemurl'].'modules/gateways/callback/telr.php?invoiceid='.$invoiceId;
	$declinedUrl     = $params['systemurl'].'viewinvoice.php?id='.$invoiceId;
	$cancelUrl       = $params['systemurl'].'viewinvoice.php?id='.$invoiceId;
	
	$data = array(
		'ivp_method'      => "create",
		'ivp_source'      => 'whmcs '.$whmcsVersion,
		'ivp_store'       => $accountId ,
		'ivp_authkey'     => $secretKey,
		'ivp_cart'        => $invoiceId,
		'ivp_test'        => $testMode,
		'ivp_framed'      => 0,
		'ivp_amount'      => $payAmount,
		'ivp_lang'        => $language,
		'ivp_currency'    => $currencyCode,
		'ivp_desc'        => $description,
		'return_auth'     => $returnUrl,
		'return_can'      => $cancelUrl,
		'return_decl'     => $declinedUrl,
		'bill_fname'      => $firstname,
		'bill_sname'      => $lastname,
		'bill_addr1'      => $address1,
		'bill_addr2'      => $address2,
		'bill_city'       => $city,
		'bill_region'     => $state,
		'bill_zip'        => $postcode,
		'bill_country'    => $country,
		'bill_email'      => $email,
		'bill_tel'        => $phone
	);
	
	$currentUser = new \WHMCS\Authentication\CurrentUser;
	if ($currentUser->isAuthenticatedUser()) {
		$user = $currentUser->user();
		$data['bill_custref'] = $user->id;
	}
	
	$response = api_request($data);
	
	if(!empty($response) && !empty($response['order']['ref'])  && !empty($response['order']['url'])){
        $telrRef = trim($response['order']['ref']);
        $telrUrl = trim($response['order']['url']);
		if(!empty($invoiceId)){
			$results = Capsule::table('mod_telrpayment')->where('invoiceid', $invoiceId)->first();	
            if(!empty($results) && $results->telr_ref != ''){
				$statement = $pdo->prepare('update mod_telrpayment set telr_ref = :telrref where invoiceid = :invoiceid');
				$statement->execute([':invoiceid' => $invoiceId,':telrref' => $telrRef]);
				if ($pdo->inTransaction()) {
					$pdo->commit();
				}
			}else{
				$statement = $pdo->prepare('insert into mod_telrpayment (invoiceid, telr_ref) values (:invoiceid, :telr_ref)');
				$statement->execute([':invoiceid' => $invoiceId,':telr_ref' => $telrRef]);
				if ($pdo->inTransaction()) {
					$pdo->commit();
				}	
			}					
		}		
		$htmlOutput = '<form method="post" action="' . $telrUrl . '">';
		$htmlOutput .= '<input type="submit" value="' . $langPayNow . '" />';
		$htmlOutput .= '</form>';
	}else{
		$error = $response['error']['message'].', '.$response['error']['note'];
		$htmlOutput = '<span>Payment has been failed, Please try again.</span>';
		$htmlOutput = "<span>$error</span>";
	}
	return $htmlOutput;	
}

/*
* api request to telr server
*
* @parem request data(array)
* @access public
* @return array
*/
function api_request($data)
{
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
	$results = json_decode($results, true);
	return $results;
}

/**
 * Refund transaction.
 *
 * Called when a refund is requested for a previously successful transaction.
 *
 * @param array $params Payment Gateway Module Parameters
 *
 * @return array Transaction response status
 */
function telr_refund($params)
{    
    $result = doTelrRefund($params);
    // perform API call to initiate refund and interpret result
    return $result;
}

function doTelrRefund($params){
	
	$status = 'error';
	$message = '';
	
	$accountId				= $params['accountID'];
	$secretKey				= $params['remoteSecretKey'];
	$invoiceId				= $params['invoiceid'];
	$reason					= 'Return ' . $params['description'];
	$testmode				= ($params['testMode'] == 'on') ? 1 : 0;
	$currencyCode			= $params['currency'];
	$refundAmount 			= $params['amount'];	 
	$transactionIdToRefund	= $params['transid'];
	
	if($secretKey != null && $secretKey != ''){		
		$url = "https://secure.telr.com/gateway/remote.xml";	
		$xmlData = "<?xml version='1.0' encoding='UTF-8'?>
						<remote>
							<store>$accountId</store>
							<key>$secretKey</key>
							<tran>
								<type>refund</type>
								<class>ecom</class>
								<cartid>$invoiceId</cartid>
								<description>$reason</description>
								<test>$testmode</test>
								<currency>$currencyCode</currency>
								<amount>$refundAmount</amount>
								<ref>$transactionIdToRefund</ref>
							</tran>
						</remote>";
							
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $xmlData);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'Content-Type: application/xml',
			'Content-Length: ' . strlen($xmlData)
		));
		
		$results = curl_exec($ch);
		$err = curl_error($ch);
		curl_close($ch);
		if (!$err && $results !== false) {
			$xml = simplexml_load_string($results);
			$json = json_encode($xml);
			$responseArray = json_decode($json, true);			
			if ($responseArray !== null) {					
				if($responseArray['auth']['status'] == 'A'){
					$out = array(
						'status' => 'success',
						'rawdata' => $responseArray,
						'transid' => $result->response->transaction_id,
						'fees' => 0,
					);
					$status = 'success';
				}else{
					$message = $responseArray['auth']['message'];			
				}
			}else{
				$message = $responseArray;
			}
		}else{
			$message = $err;
		}	
	}else{
		$message = 'Please check that the Remote API Authentication Key is not blank or incorrect.';				
	}
	
	if($status == 'error'){
		$out = array(
            'status' => 'error',
            'rawdata' => $message
        );
	}	
	return $out;		
}
