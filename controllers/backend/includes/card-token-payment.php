<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
require_once('config.php');

use Paysafe\PaysafeApiClient;
use Paysafe\Environment;
use Paysafe\CardPayments\Authorization;
use Paysafe\CardPayments\Verification;
use Paysafe\CustomerVault\Profile;
use Paysafe\CustomerVault\Address;
use Paysafe\CustomerVault\Card;
use Paysafe\CustomerVault\Mandates;

 class WC_Gateway_Paysafe_Request {       
     /**
     * Payment via credit card request to the gateway.
     * @param  $order
     * @param  $merrcoApiKeyId
     * @param  $merrcoApiKeySecret
     * @param  $merrcoAccountNumber
     * @param  $environment
     * @param  $totalAmount
     * @param  $cardNumber
     * @param  $cardMonth
     * @param  $cardYear
     * @param  $cardCvv
     * @param  $billing_address_1
     * @param  $billing_country
     * @param  $billing_city
     * @param  $billing_postcode
     * @param  $currencyBaseUnitsMultiplier
     * @param  $tokenRequest
     * @param  $fname
     * @param  $lname
     * @param  $email
     * @param  $merrcoMethod
     * @return array
     */

    private static $paysafeApiKeyId = '';

    private static $paysafeApiKeySecret = '';

    private static $paysafeAccountNumber = '';

    private static $environment = '';

    private static $currencyBaseUnitsMultiplier = '';

    private static $currencyCode = '';

    private static $auth_capture_settlement;

    private static $PaysafeApiClient;

    /**
     * Set secret API Key.
     * @param string $key
     */
    public static function set_api_keys( $apiKeyId, $apiKeySecret, $accountNumber, $environment, $currencyBaseUnitsMultiplier, $currencyCode, $auth_capture_settlement ) {
 
        self::$paysafeApiKeyId              = $apiKeyId;
        self::$paysafeApiKeySecret          = $apiKeySecret;
        self::$paysafeAccountNumber         = $accountNumber;
        self::$environment                  = $environment;
        self::$currencyBaseUnitsMultiplier  = $currencyBaseUnitsMultiplier;
        self::$currencyCode                 = $currencyCode;
        self::$auth_capture_settlement      = $auth_capture_settlement;

        $environmentType = ($environment == 'LIVE') ? Environment::LIVE : Environment::TEST;

        self::$PaysafeApiClient = new PaysafeApiClient(
                                            self::$paysafeApiKeyId, 
                                            self::$paysafeApiKeySecret, 
                                            $environmentType, 
                                            self::$paysafeAccountNumber
                                        );

    }

    public static function make_cc_payment_request( $order ){

        $order_id           = $order->get_order_number();
        $order_total        = $order->get_total();

        $billing_fname      = $order->get_billing_first_name();
        $billing_lname      = $order->get_billing_last_name();
        $billing_company    = $order->get_billing_company();
        $billing_address_1  = $order->get_billing_address_1();
        $billing_address_2  = $order->get_billing_address_2();
        $billing_city       = $order->get_billing_city();
        $billing_state      = $order->get_billing_state();
        $billing_postcode   = $order->get_billing_postcode();
        $billing_country    = $order->get_billing_country();
        $billing_email      = $order->get_billing_email();
        $billing_phone      = $order->get_billing_phone();

        $cardNumber         = str_replace( array(' ', '-' ), '', $_POST['mer_merrco-card-number'] );
        $cardNumberExpiry   = str_replace( array(' ', '-' ), '', $_POST['mer_merrco-card-expiry'] );
        $cardCvv            = str_replace( array(' ', '-' ), '', $_POST['mer_merrco-card-cvc'] );           
        $expcardexp         = explode('/',$cardNumberExpiry);
        $cardMonth          = (int)str_replace(array(' ', ','), '', $expcardexp[0]);           
        $cardYear           = (int)str_replace(array(' ', ','), '', $expcardexp[1]);

        $auth_request = array(
                            'merchantRefNum' => $order_id.'_'.date('m/d/Y h:i:s a', time()),
                            'amount' => $order_total * self::$currencyBaseUnitsMultiplier,
                            'settleWithAuth' => self::$auth_capture_settlement,
                            'card' => array( 
                                      'cardNum' => $cardNumber,
                                      'cvv' => $cardCvv,
                                      'cardExpiry' => array(
                                            'month' => $cardMonth,
                                            'year' => $cardYear
                                        )
                                    ),
                            'billingDetails' => array(
                                "zip"       => $billing_postcode
                            )
                        );

        WC_Paysafe_Logger::log("Auth request created. auth_request : ".print_r($auth_request, true));

        $auth = new Authorization($auth_request);

        $auth_response = self::$PaysafeApiClient->cardPaymentService()->authorize($auth);

        WC_Paysafe_Logger::log("Auth response received. auth_response : ".print_r($auth_response, true));

        return $auth_response;    
    }

    public static function make_token_payment_request( $order, $wc_token ){

        $order_id           = $order->get_order_number();
        $order_total        = $order->get_total();
        $billing_postcode   = $order->get_billing_postcode();
        
        $auth_request = array(
                    'merchantRefNum' => $order_id.'_'.date('m/d/Y h:i:s a', time()),
                    'amount' => $order_total * self::$currencyBaseUnitsMultiplier,
                    'settleWithAuth' => self::$auth_capture_settlement,
                    'card' => array(
                        'paymentToken' => $wc_token
                    ),
                     'billingDetails' => array(
                        'zip' => $billing_postcode
                    )
                );

        WC_Paysafe_Logger::log("Auth request created. auth_request : ".print_r($auth_request, true));

        $auth = new Authorization($auth_request);

        $auth_response = self::$PaysafeApiClient->cardPaymentService()->authorize($auth);

        WC_Paysafe_Logger::log("Auth response received. auth_response : ".print_r($auth_response, true));

        return $auth_response;    
    }


	public static function get_token(){

                $return_response    = array();

                WC_Paysafe_Logger::log("POST DATA for get_token: ".print_r($_POST, true));

                $customer = new WC_Customer( get_current_user_id() );

                // Card Details
                $cardNumber         = str_replace( array(' ', '-' ), '', $_POST['mer_merrco-card-number'] );
                $cardNumberExpiry   = str_replace( array(' ', '-' ), '', $_POST['mer_merrco-card-expiry'] );
                $cardCvv            = str_replace( array(' ', '-' ), '', $_POST['mer_merrco-card-cvc'] );

                $expcardexp         = explode('/',$cardNumberExpiry);
                $cardMonth          = (int)str_replace(array(' ', ','), '', $expcardexp[0]);           
                $cardYear           = (int)str_replace(array(' ', ','), '', $expcardexp[1]);

                // account details
                $customer_email = $customer->get_email();
                $customer_fname = $customer->get_first_name();
                $customer_lname = $customer->get_last_name();

                // address details - Billing
                $customer_street    = $customer->get_billing_address();
                $customer_city      = $customer->get_billing_city();
                $customer_state     = $customer->get_billing_state();
                $customer_country   = $customer->get_billing_country();
                $customer_zip       = $customer->get_billing_postcode();


                $merchantCustomerId = uniqid('customer-' . date('mdYhisa', time()));

                // 1. Create user profile
                $profile_request = array(
                    "merchantCustomerId"    => $merchantCustomerId,
                    "locale"                => "en_US",
                    "firstName"             => $customer_fname,
                    "lastName"              => $customer_lname,
                    "email"                 => $customer_email
                );

                $profile = self::$PaysafeApiClient->customerVaultService()->createProfile(new Profile( $profile_request));
                $return_response['profile'] = $profile;
                

                // 2. Save address for profile
                if($customer_street && $customer_city && $customer_country && $customer_zip){
                    $address_request = array(
                        "nickName"      => "billing",
                        'street'        => $customer_street,
                        'city'          => $customer_city,
                        'state'         => $customer_state,
                        'country'       => $customer_country,
                        'zip'           => $customer_zip,
                        "profileID"     => $profile->id
                    );
                    $address = self::$PaysafeApiClient->customerVaultService()->createAddress(new Address( $address_request));
                    $return_response['address'] = $address;
                }

                // 3. Save card for profile
                $card_request = array(
                    "nickName"          => "$customer_fname's Card",
                    'cardNum'           => $cardNumber,
                    'cardExpiry'        => array(
                        'month'         => $cardMonth,
                        'year'          => $cardYear
                    ),
                    "profileID" => $profile->id
                );
                $card = self::$PaysafeApiClient->customerVaultService()->createCard(new Card( $card_request ));
                $return_response['card'] = $card;

                return $return_response;
    }

    public function get_request_merrco_url_cc( $order_id, $merrcoApiKeyId, $merrcoApiKeySecret, $merrcoAccountNumber, $environment, $totalAmount, $cardNumber, $cardMonth, $cardYear, $cardCvv, $billing_address_1, $billing_country, $billing_city, $billing_postcode, $currencyBaseUnitsMultiplier, $tokenRequest, $fname, $lname, $email,$merrcoMethod, $authCaptureSettlement) {

            $environmentType = $environment=='LIVE' ? Environment::LIVE : Environment::TEST;
            $settleWithAuth = $authCaptureSettlement=='yes' ? true : false;
            $client = new PaysafeApiClient($merrcoApiKeyId, $merrcoApiKeySecret, $environmentType, $merrcoAccountNumber);
        try {
               if($tokenRequest === "token") {

                          $profile = $client->customerVaultService()->createProfile(new Profile(array(
                                    "merchantCustomerId" => uniqid('cust-' . date('m/d/Y h:i:s a', time())),
                                    "locale" => "en_US",
                                    "firstName" => $fname,
                                    "lastName" => $lname,
                                    "email" => $email
                                )));
                             

                                $address = $client->customerVaultService()->createAddress(new Address(array(
                                    "nickName" => "home",
                                    'street' => $billing_address_1,
                                    'city' => $billing_city,
                                    'country' => $billing_country,
                                    'zip' => $billing_postcode,
                                    "profileID" => $profile->id
                                )));
                                
                                $card = $client->customerVaultService()->createCard(new Card(array(
                                    "nickName" => "Default Card",
                                    'cardNum' => $cardNumber,
                                    'cardExpiry' => array(
                                        'month' => $cardMonth,
                                        'year' => $cardYear
                                    ),
                                    'billingAddressId' => $address->id,
                                    "profileID" => $profile->id
                                )));
                                 

                                $auth = $client->cardPaymentService()->authorize(new Authorization(array(
                                    'merchantRefNum' => $order_id.'_'.date('m/d/Y h:i:s a', time()),
                                    'amount' => $totalAmount * $currencyBaseUnitsMultiplier,
                                    'settleWithAuth' => $settleWithAuth,
                                    'card' => array(
                                        'paymentToken' => $card->paymentToken
                                    )
                                )));

                             $responsearray=array('transaction_id'=>$auth->id,
                                             'status'=>$auth->status,
                                             'merchantrefnum'=>$auth->merchantRefNum,
                                             'txtime'=>$auth->txnTime,
                                             'tokenreq'=>$tokenRequest,
                                             'tokenkey'=>$profile->paymentToken,
                                             'responsecode'=>0
                                               );

                          return $responsearray;
                       
                 } else {

                            $auth_request = array(
                                 'merchantRefNum' => $order_id.'_'.date('m/d/Y h:i:s a', time()),
                                 'amount' => $totalAmount * $currencyBaseUnitsMultiplier,
                                 'settleWithAuth' => $settleWithAuth,
                                 'card' => array(
                                      'cardNum' => $cardNumber,
                                      'cvv' => $cardCvv,
                                      'cardExpiry' => array(
                                            'month' => $cardMonth,
                                            'year' => $cardYear
                                     )
                                 ),
                               'billingDetails' => array(
                               "street" => $billing_address_1,
                               "city" => $billing_city,
                               "country" => $billing_country,
                               'zip' => $billing_postcode
                            ));
                            WC_Paysafe_Logger::log("Actual auth request arrayf for $order_id:".print_r( $auth_request, true ));
                            $auth = $client->cardPaymentService()->authorize(new Authorization());
                            WC_Paysafe_Logger::log("auth response $order_id:".print_r( $auth, true ));
                            $responsearray=array('transaction_id'=>$auth->id,
                                             'status'=>$auth->status,
                                             'merchantrefnum'=>$auth->merchantRefNum,
                                             'txtime'=>$auth->txnTime,
                                             'tokenreq'=>$tokenRequest,
                                             'responsecode'=>0
                                               );
                            return $responsearray;
                    }      
        } catch (Paysafe\PaysafeException $e) {

            if($environment!='LIVE'){                   
                 $failedMessage='';                    
                if ($e->error_message) {
                     $failedMessage.= $e->error_message."<br>";
                }
                if ($e->fieldErrors) {
                    foreach ($e->fieldErrors as $message) {
                        $failedMessage.=$message['field']."-->".$message['error']."<br>";               
                    }
                }
                if ($e->links) {
                      foreach ($e->links as $message) {
                            $failedMessage.="error_info link --> ".$message['href']."<br>";              
                      }
                }  
                $responsearray=array('status'=>"failed",'responsecode'=>1,'errormessage'=>$failedMessage);
                } else {
                    $responsearray=array('status'=>"failed",'responsecode'=>1);
                  } 
            return $responsearray;
       }       
   }
    /**
     * Payment via token request to the gateway.
     * @param  $merrcoApiKeyId
     * @param  $merrcoApiKeySecret
     * @param  $merrcoAccountNumber
     * @param  $environment
     * @param  $totalAmount
     * @param  $currencyBaseUnitsMultiplier
     * @param  $tokenKeyId
     * @return array
     */
    public function get_request_merrco_url_token($merrcoApiKeyId, $merrcoApiKeySecret, $merrcoAccountNumber, $environment, $totalAmount, $currencyBaseUnitsMultiplier, $tokenKeyId, $authCaptureSettlement, $order_id) {
    $environmentType = $environment=='LIVE' ? Environment::LIVE : Environment::TEST;
    $settleWithAuth = $authCaptureSettlement=='yes' ? true : false;
    $client = new PaysafeApiClient($merrcoApiKeyId, $merrcoApiKeySecret, $environmentType, $merrcoAccountNumber);
    try {
         $auth = $client->cardPaymentService()->authorize(new Authorization(array(
                    'merchantRefNum' => $order_id.'_'.date('m/d/Y h:i:s a', time()),
                    'amount' => $totalAmount * $currencyBaseUnitsMultiplier,
                    'settleWithAuth' => $settleWithAuth,
                    'card' => array(
                        'paymentToken' => $tokenKeyId
                    )
                )));
             $responsearray=array('transaction_id'=>$auth->id,
                         'status'=>$auth->status,
                         'merchantrefnum'=>$auth->merchantRefNum,
                         'txtime'=>$auth->txnTime,
                         'responsecode'=>0
                         );
          return $responsearray;         
    } catch (Paysafe\PaysafeException $e) {
            if($environment!='LIVE'){
                 $failedMessage=''; 
                 if ($e->error_message) {
                     $failedMessage.= $e->error_message;
                     }
                 if ($e->fieldErrors) {
                    foreach ($e->fieldErrors as $message) {
                        $failedMessage.=$message['field']."-->".$message['error']."<br>";               
                    }
                 $responsearray=array('status'=>"failed",'responsecode'=>1,'errormessage'=>$failedMessage);
                } 
            } else {
                  $responsearray=array('status'=>"failed",'responsecode'=>1);
                } 
                           
            return $responsearray;
            
         }
    }  
}

/*

// Get Order ID
$order->get_id();
 
// Get Order Totals $0.00
$order->get_formatted_order_total();
$order->get_cart_tax();
$order->get_currency();
$order->get_discount_tax();
$order->get_discount_to_display();
$order->get_discount_total();
$order->get_fees();
$order->get_formatted_line_subtotal();
$order->get_shipping_tax();
$order->get_shipping_total();
$order->get_subtotal();
$order->get_subtotal_to_display();
$order->get_tax_location();
$order->get_tax_totals();
$order->get_taxes();
$order->get_total();
$order->get_total_discount();
$order->get_total_tax();
$order->get_total_refunded();
$order->get_total_tax_refunded();
$order->get_total_shipping_refunded();
$order->get_item_count_refunded();
$order->get_total_qty_refunded();
$order->get_qty_refunded_for_item();
$order->get_total_refunded_for_item();
$order->get_tax_refunded_for_item();
$order->get_total_tax_refunded_by_rate_id();
$order->get_remaining_refund_amount();
 
// Get Order Items
$order->get_items();
$order->get_items_key();
$order->get_items_tax_classes();
$order->get_item();
$order->get_item_count();
$order->get_item_subtotal();
$order->get_item_tax();
$order->get_item_total();
$order->get_downloadable_items();
 
// Get Order Lines
$order->get_line_subtotal();
$order->get_line_tax();
$order->get_line_total();
 
// Get Order Shipping
$order->get_shipping_method();
$order->get_shipping_methods();
$order->get_shipping_to_display();
 
// Get Order Dates
$order->get_date_created();
$order->get_date_modified();
$order->get_date_completed();
$order->get_date_paid();
 
// Get Order User, Billing & Shipping Addresses
$order->get_customer_id();
$order->get_user_id();
$order->get_user();
$order->get_customer_ip_address();
$order->get_customer_user_agent();
$order->get_created_via();
$order->get_customer_note();
$order->get_address_prop();
$order->get_billing_first_name();
$order->get_billing_last_name();
$order->get_billing_company();
$order->get_billing_address_1();
$order->get_billing_address_2();
$order->get_billing_city();
$order->get_billing_state();
$order->get_billing_postcode();
$order->get_billing_country();
$order->get_billing_email();
$order->get_billing_phone();
$order->get_shipping_first_name();
$order->get_shipping_last_name();
$order->get_shipping_company();
$order->get_shipping_address_1();
$order->get_shipping_address_2();
$order->get_shipping_city();
$order->get_shipping_state();
$order->get_shipping_postcode();
$order->get_shipping_country();
$order->get_address();
$order->get_shipping_address_map_url();
$order->get_formatted_billing_full_name();
$order->get_formatted_shipping_full_name();
$order->get_formatted_billing_address();
$order->get_formatted_shipping_address();
 
// Get Order Payment Details
$order->get_payment_method();
$order->get_payment_method_title();
$order->get_transaction_id();
 
// Get Order URLs
$order->get_checkout_payment_url();
$order->get_checkout_order_received_url();
$order->get_cancel_order_url();
$order->get_cancel_order_url_raw();
$order->get_cancel_endpoint();
$order->get_view_order_url();
$order->get_edit_order_url();
 
// Get Order Status
$order->get_status();

*/