<?php
use WHMCS\Database\Capsule;
if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}
function zoho_crm_MetaData()
{    
     try {
         if(!Capsule::schema()->hasTable('zoho_crm')){
    	       Capsule::schema()->create(
    	                                'zoho_crm',
    	                           function ($table) {
    	                                 $table->string('authtoken');
    	                                 $table->string('domain');
    	                                 $table->string('server');
    	                                 $table->string('zoid');
    	                                 $table->string('profileid');
    	                                 $table->string('superAdmin');
    	                               }
    	                        );
        }
        else {
            $pdo = Capsule::connection()->getPdo();
            $pdo->beginTransaction();
        }
	} catch (Exception $e) {
	logModuleCall(
	    'zoho_crm',
	    __FUNCTION__,
	    $params,
	    $e->getMessage(),
	    $e->getTraceAsString()
	);
    }
    return array(
    	'DisplayName' => 'Zoho CRM',
    	'APIVersion' => '1.1',
    	'RequiresServer' => true,
    	'DefaultNonSSLPort' => '1111',
    	'DefaultSSLPort' => '1112',
    	'ServiceSingleSignOnLabel' => 'Login to Panel as User',
    	'AdminSingleSignOnLabel' => 'Login to Panel as Admin',
    );
    
}
function zoho_crm_ConfigOptions()
{
    $config = array (
	              'Domain' => array('Type' => 'dropdown', 'Options' => 'com,eu', 'Description' => '<br>Domain Region'),
'Authtoken' => array('Type' => 'text', 'Description' => '<br><a href="https://accounts.zoho.com/apiauthtoken/create?SCOPE=ZohoPayments/partnerapi" target="_blank">Click here</a> to generate authtoken.')
	          );
	         
	   return $config;
}
function zoho_crm_CreateAccount(array $params)
{
	$addonid;
	//$test = $params['configoption2'];
	$test = "b3ee75bbcefc384a97a099920900f332";
	try {
	$curl = curl_init();
	$arrClient = $params['clientsdetails'];
	$planid = $params['configoptions']['Plan Type'];
	if($planid == 14815) {
	    $addonid = 14925;
	}
	else if($planid == 14816) {
	    $addonid = 14926;
	}
	else if($planid == 14817) {
	    $addonid = 14927;
	}
	else{
	    $addonid = 647;
	}
	$noofusers = $params['configoptions']['No of users'];
	 
	$bodyArr = json_encode(array(
		//'JSONString' => array(
		"serviceid" => 2,
		"email" => $arrClient['email'],
		"customer" => array(
		"companyname" => $arrClient['companyname'],
		"street" => $arrClient['address1'],
		"city" => $arrClient['city'],
		"state" => $arrClient['state'],
		"country" => $arrClient['countryname'],
		"zipcode" => $arrClient['postcode'],
		"phone" => $arrClient['phonenumber']
		),
		"subscription" => array(
		"plan" => $planid,
		"addons" => array(
		array(
		"id" => $addonid,
		"count" => $noofusers
		)
		),
		"payperiod" => "YEAR",
		"currency" => "1",
		"addprofile" => "true"
		),
		//),
	));
	$authtoken = array(
	"authtoken" => $test
	);
	 
	$bodyJson = array('JSONString' => $bodyArr, 'authtoken' => $test);
	$bodyJsn = json_encode($bodyJson);
   $curlOrg = curl_init();
   $urlOrg = 'https://payments.zoho.'.$params['configoption1'].'/restapi/partner/v1/json/subscription';
   curl_setopt_array($curlOrg, array(
      CURLOPT_URL => $urlOrg,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => "",
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => "POST",
      CURLOPT_POSTFIELDS => $bodyJson
   ));

		$responseOrg = curl_exec($curlOrg);
		//return array('success' => $responseOrg);
		$respOrgJson = json_decode($responseOrg); 
		$getInfo = curl_getinfo($curlOrg,CURLINFO_HTTP_CODE);
		curl_close($curlOrg);
		$result = $respOrgJson->result;
		if(($result == 'success') && ($getInfo == '200')) {
		    $customid = $respOrgJson->customid;
		    if($customid != '') {
		        $pdo = Capsule::connection()->getPdo();
		        $pdo->setAttribute(PDO::ATTR_AUTOCOMMIT, 0 );
		        //$pdo->beginTransaction();
		        try {
			        $statement = $pdo->prepare('insert into zoho_crm (authtoken,domain,server,zoid,profileid,superAdmin) values (:authtoken, :domain, :server, :zoid, :profileid, :superAdmin)');
	 
		            $statement->execute(
        		     [
        			   ':authtoken' => $test,
        			   ':domain' => $params['domain'],
        			   ':server' => $params['configoption1'],
        			   ':zoid' => $respOrgJson->customid,
        			   ':profileid' => $respOrgJson->profileid,
        			   ':superAdmin' => "true"              
        		    ]
        		 );
	 
        		 $pdo->commit();
        		 $pdo->setAttribute(PDO::ATTR_AUTOCOMMIT, 1 );
        		 } catch (\Exception $e) {
        			  return "Uh oh! {$e->getMessage()}".$urlChildPanel;
        			  $pdo->rollBack();
        		  }
	 
    		  return array ('success' => 'CRM Org has been created.');
    		    }
    		    else if(($result == 'success') && (isset($respOrgJson->ERRORMSG))) {
    		        return 'Failed  ->  '.$respOrgJson->ERRORMSG;
    		    }
    		    else if ($getInfo == '400') {
		            $updatedUserCount = Capsule::table('tblproducts')
		            ->where('servertype','zoho_crm')
		            ->update(
        			  [
        			   'configoption2' => '',
        			  ]
		            );
			    }
			    else
        		{
        		    return 'Failed -->Description: '.$respOrgJson->status->description.' --->More Information:'.$respOrgJson->data->moreInfo.'--------------'.$getInfo;
        	    }
    		        
    		    
    		}
    		else if($getInfo == '400') {
    		    //$errorMsg = $respOrgJson->ERRORMSG;
    		    return 'Failed -->  Invalid Authtoken.';
    		}
    		else{
    		    $errorMsg = $respOrgJson->ERRORMSG;
    		    return 'Failed -->  '.$errorMsg;
    		}
	 
	} catch (Exception $e) {
		logModuleCall(
		    'zoho_crm',
		    __FUNCTION__,
		    $params,
		    $e->getMessage(),
		    $e->getTraceAsString()
		);
		return $e->getMessage();
	    }
 
}
function zoho_crm_TestConnection(array $params)
{
    try {
	// Call the service's connection test function.
	$success = true;
	$errorMsg = '';
    } catch (Exception $e) {
	// Record the error in WHMCS's module log.
	logModuleCall(
	    'zoho_crm',
	    __FUNCTION__,
	    $params,
	    $e->getMessage(),
	    $e->getTraceAsString()
	);
	$success = false;
	$errorMsg = $e->getMessage();
    }
    return array(
	'success' => $success,
	'error' => $errorMsg,
    );
}
function zoho_crm_AdminServicesTabFields(array $params)
{
   try{
	$url = 'https://accounts.zoho.com/apiauthtoken/create?SCOPE=ZohoPayments/partnerapi';
	$cli = Capsule::table('zoho_crm')->where('domain',$params['domain'])->first();
	//$authtoken = $params['configoption2'];
	$authtoken = "b3ee75bbcefc384a97a099920900f332";
	if(!$authtoken == '') {
	$authtoken = '<h2 style="color:green;">Authenticated</h2>';
	}
	else {
	$authtoken = '<a href="'.$url.'" type="submit" target="_blank"> Click here </a> (Call only once for authenticating)';
	}
	$response = array();
	/*$verificationStatus;
	if (strcmp("true",$cli->isverified) == 0) {
		 $verificationStatus = '<b style=color:green>Verified</b>';
	} else {
		 $verificationStatus = '<b style=color:red>Not Verified</b>';
	}*/
	 
	return array(
	     'Authenticate' => $authtoken,
	     'Client Control Panel' => '<a href="'.$cli->url.'" target=_blank>Click here</a>',
	     'Super Administrator' => $cli->superAdmin,
	     'ZOID' => $cli->zoid,
	     'URL to manage customers' => '<a href="https://payments.zoho.com/zstore#customers" target="blank">Click here</a>'
	    );
	 
    } catch (Exception $e) {
	logModuleCall(
	    'zoho_crm',
	    __FUNCTION__,
	    $params,
	    $e->getMessage(),
	    $e->getTraceAsString()
	);
    }
	    return array();
}
function zoho_crm_AdminServicesTabFieldsSave(array $params)
{
    // Fetch form submission variables.
    $originalFieldValue = isset($_REQUEST['zoho_crm_original_uniquefieldname'])
	? $_REQUEST['zoho_crm_original_uniquefieldname']
	: '';
    $newFieldValue = isset($_REQUEST['zoho_crm_uniquefieldname'])
	? $_REQUEST['zoho_crm_uniquefieldname']
	: '';
return array('success' => $originalFieldValue);
    if ($originalFieldValue != $newFieldValue) {
	try {
	} catch (Exception $e) {
	    logModuleCall(
	        'zoho_crm',
	        __FUNCTION__,
	        $params,
	        $e->getMessage(),
	        $e->getTraceAsString()
	    );
	}
    }
}
function zoho_crm_ServiceSingleSignOn(array $params)
{
    try {
	$response = array();
	return array(
	    'success' => true,
	    'redirectTo' => $response['redirectUrl'],
	);
    } catch (Exception $e) {
	logModuleCall(
	    'zoho_crm',
	    __FUNCTION__,
	    $params,
	    $e->getMessage(),
	    $e->getTraceAsString()
	);
	return array(
	    'success' => false,
	    'errorMsg' => $e->getMessage(),
	);
    }
}
function zoho_crm_AdminSingleSignOn(array $params)
{
    try {
	// Call the service's single sign-on admin token retrieval function,
	// using the values provided by WHMCS in `$params`.
	$response = array();
	return array(
	    'success' => true,
	    'redirectTo' => $response['redirectUrl'],
	);
    } catch (Exception $e) {
	// Record the error in WHMCS's module log.
	logModuleCall(
	    'zoho_crm',
	    __FUNCTION__,
	    $params,
	    $e->getMessage(),
	    $e->getTraceAsString()
	);
	return array(
	    'success' => false,
	    'errorMsg' => $e->getMessage(),
	);
    }
}
function zoho_crm_ClientArea(array $params)
{
    $serviceAction = 'get_stats';
    $templateFile = 'templates/overview.tpl';
    try {
      $cli = Capsule::table('zoho_crm')->where('zoid',$params['zoid'])->first();
      $urlToPanel = $cli->url;
	return array(
	    'tabOverviewReplacementTemplate' => $templateFile,
	    'templateVariables' => array(
	     'crmUrl' => 'https://crm.zoho.com'
	     //'panelUrl' => $urlToPanel
	    ),
	);
    } catch (Exception $e) {
	// Record the error in WHMCS's module log.
	logModuleCall(
	    'zoho_crm',
	    __FUNCTION__,
	    $params,
	    $e->getMessage(),
	    $e->getTraceAsString()
	);
	// In an error condition, display an error page.
	return array(
	    'tabOverviewReplacementTemplate' => 'error.tpl',
	    'templateVariables' => array(
	        'usefulErrorHelper' => $e->getMessage(),
	    ),
	);
    }
}

