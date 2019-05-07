<?php
 /**
 * Merrco Gateway
 *
 * Provides a Merrco Payment Gateway.
 *
 * @class       mer_merrco_Init
 * @extends     WC_Payment_Gateway
 * @package     WooCommerce/Classes/Payment
 * @author      Merrco
 */
class Merrco_Gateway_Init extends WC_Payment_Gateway_CC {
    protected $order                     = null;
    protected $form_data                 = null;
    protected $transaction_id            = null;
    protected $transaction_error_message = null;

    /**
	 * Is test mode active?
	 *
	 * @var bool
	 */
	public $testmode;

	public function __construct() {
		global $mer_merrco;
		
		$this->id = "mer_merrco";
		$this->method_title = __( "Merrco  Payment Gateway", 'mer-merrcopayments-aim' );
		$this->method_description = __( "Merrco(Credit Card & Tokenisation) setting fields", 'mer-merrcopayments-aim' );
		$this->title = __( "Merrco", 'mer-merrcopayments-aim' );
		$this->icon = null;
		$this->has_fields = true;

		/* For Default */
		//$this->supports = array( 'default_credit_card_form','tokenization' );

		/* For Subscription Only */
		$this->supports = array( 
           'products', 
           'subscriptions',
           'subscription_cancellation', 
           'subscription_suspension', 
           'subscription_reactivation',
           'subscription_amount_changes',
           'subscription_date_changes',
           'subscription_payment_method_change',
           'default_credit_card_form',
           'save_cards',
           'tokenization',
		   'add_payment_method' 
      	);

		$this->testmode = 'yes' === $this->get_option( 'environment' );

		$paysafeApiKeyId              = $this->get_option( 'api_login' );
        $paysafeApiKeySecret          = $this->get_option( 'trans_key' );
        $paysafeAccountNumber         = $this->get_option( 'acc_number' );
        $environment                  = $this->testmode ? 'TEST' : 'LIVE';
        $currencyBaseUnitsMultiplier  = $this->get_option( 'currency_base_units_multiplier' );
        $currencyCode                 = $this->get_option( 'currency_code' );
        $auth_capture_settlement	  = ( $this->get_option( 'auth_capture_settlement' ) == 'yes' ) ? true : false;

		WC_Gateway_Paysafe_Request::set_api_keys( $paysafeApiKeyId, $paysafeApiKeySecret, $paysafeAccountNumber, $environment, $currencyBaseUnitsMultiplier, $currencyCode, $auth_capture_settlement );


		 // Init settings
		$this->init_form_fields();

		 // Use settings
		$this->init_settings();

			foreach ( $this->settings as $setting_key => $value ) {
			$this->$setting_key = $value;
		}
	
		//add_action( 'admin_notices', array( $this,'do_ssl_check' ) );
		if ( is_admin() ) {
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		}

		
				
	}
	/**
     * Get a field name supports
     *
     * @access      public
     * @param       string $name
     * @return      string
     */
	public function field_name( $name ) {
		return $this->supports( 'tokenization' ) ? '' : ' name="' . esc_attr( $this->id . '-' . $name ) . '" ';
	}
    /**
     * Output payment fields, optional additional fields and woocommerce cc form
     *
     * @access      public
     * @return      void
     */
    public function payment_fields() {
    	$user                 = wp_get_current_user();
    	$display_tokenization = $this->supports( 'tokenization' ) && is_checkout() && $this->saved_cards;

		
		$this->form();

		if ( $display_tokenization ) {

			$this->save_payment_method_checkbox();
			$this->tokenization_script();
			$this->saved_payment_methods();
		}
		
    }

    public function save_payment_method_checkbox() {
		printf(
			'<p class="form-row woocommerce-SavedPaymentMethods-saveNew">
				<input id="wc-%1$s-new-payment-method" name="wc-%1$s-new-payment-method" type="checkbox" value="true" style="width:auto;" />
				<label for="wc-%1$s-new-payment-method" style="display:inline;">%2$s</label>
			</p>',
			esc_attr( $this->id ),
			esc_html( apply_filters( 'wc_paysafe_save_to_account_text', __( 'Save payment information to my account for future purchases.', 'woocommerce-gateway-paysafe' ) ) )
		);
	}

    /**
     * Form Credit Card
     * 
     * @access     public
     * @return      void
     */
	public function form() {
		wp_enqueue_script( 'wc-credit-card-form' );

		echo "4530910000012345";

		$fields = array();

		$cvc_field = '<p class="form-row form-row-last">
			<label for="' . esc_attr( $this->id ) . '-card-cvc">' . esc_html__( 'Card code', 'woocommerce' ) . ' <span class="required">*</span></label>
			<input id="' . esc_attr( $this->id ) . '-card-cvc" name="mer_merrco-card-cvc" class="input-text wc-credit-card-form-card-cvc" inputmode="numeric" autocomplete="off" autocorrect="no" autocapitalize="no" spellcheck="no" type="tel" maxlength="4" placeholder="' . esc_attr__( 'CVC', 'woocommerce' ) . '" ' . $this->field_name( 'card-cvc' ) . ' style="width:100px" />
		</p>';
		

		$default_fields = array(
			'card-number-field' => '<p class="form-row form-row-wide">
				<label for="' . esc_attr( $this->id ) . '-card-number">' . esc_html__( 'Card number', 'woocommerce' ) . ' <span class="required">*</span></label>
				<input id="' . esc_attr( $this->id ) . '-card-number" name="mer_merrco-card-number" class="input-text wc-credit-card-form-card-number" inputmode="numeric" autocomplete="cc-number" autocorrect="no" autocapitalize="no" spellcheck="no" type="tel" placeholder="&bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull;" ' . $this->field_name( 'card-number' ) . ' />
			</p>',
			'card-expiry-field' => '<p class="form-row form-row-first">
				<label for="' . esc_attr( $this->id ) . '-card-expiry">' . esc_html__( 'Expiry (MM/YYYY)', 'woocommerce' ) . ' <span class="required">*</span></label>
				<input id="' . esc_attr( $this->id ) . '-card-expiry" name="mer_merrco-card-expiry" class="input-text wc-credit-card-form-card-expiry" inputmode="numeric" autocomplete="cc-exp" autocorrect="no" autocapitalize="no" spellcheck="no" type="tel" placeholder="' . esc_attr__( 'MM / YYYY', 'woocommerce' ) . '" ' . $this->field_name( 'card-expiry' ) . ' />
			</p>',
		);

		if ( ! $this->supports( 'credit_card_form_cvc_on_saved_method' ) ) {
			$default_fields['card-cvc-field'] = $cvc_field;
		}

		$fields = wp_parse_args( $fields, apply_filters( 'woocommerce_credit_card_form_fields', $default_fields, $this->id ) );
		?>
         <input id="payment_method_cc" class="input-radio" name="mer_merrco_payment_method" value="mer_merrco_credit_card" data-order_button_text="" type="radio" checked="checked" onclick="merrcocc()">
         <label for="payment_method_cc" onclick="merrcocc()"> Credit Card </label>
         <?php 
          $customer_orders = get_posts( array( 'numberposts' => -1, 'meta_key' => '_customer_user', 'meta_value' => get_current_user_id(), 'post_type' => wc_get_order_types(), 'post_status' => array_keys( wc_get_order_statuses() ), ) );
        $i=0;
         foreach($customer_orders as $oid) {
				if(metadata_exists('post', $oid->ID, '_merrco_token_card_info')) {
        			 $listcard[$i]=get_post_meta($oid->ID,'_merrco_token_card_info');
       			}
        $i++;
         } 

        if ( !is_add_payment_method_page() ) {
	         if(is_user_logged_in() && !empty($listcard) && $this->saved_cards == "yes")
	         { ?>
	         <input id="payment_method_token" class="input-radio" name="mer_merrco_payment_method" value="mer_merrco_token" data-order_button_text="" type="radio" onclick="merrcotoken()">
	         <label for="payment_method_token" onclick="merrcotoken()"> Save Card </label>
	         <?php  }?>
	    <?php } ?>
		<fieldset id="wc-<?php echo esc_attr( $this->id ); ?>-cc-form" class='wc-credit-card-form wc-payment-form'>
			<?php do_action( 'woocommerce_credit_card_form_start', $this->id ); ?>
			<?php
				foreach ( $fields as $field ) {
				echo $field;
				}
			?>
			<?php do_action( 'woocommerce_credit_card_form_end', $this->id ); ?>
			<div class="clear"></div>
		</fieldset>
		<?php

		if ( $this->supports( 'credit_card_form_cvc_on_saved_method' ) ) {
			echo '<fieldset>' . $cvc_field . '</fieldset>';
		}
	
	
	}    

    /**
     * Initialise Gateway Settings Form Fields
     *
     * @access      public
     * @return      void
     */
	public function init_form_fields() {
        $prefix = 'sample_';

		$this->form_fields = array(
			'enabled' => array(
				'title'		=> __( 'Enable / Disable', 'mer-merrcopayments-aim' ),
				'label'		=> __( 'Enable this payment gateway', 'mer-merrcopayments-aim' ),
				'type'		=> 'checkbox',
				'default'	=> 'no',
			),
			'title' => array(
				'title'		=> __( 'Title', 'mer-merrcopayments-aim' ),
				'type'		=> 'text',
				'desc_tip'	=> __( 'Payment title the customer will see during the checkout process.', 'mer-merrcopayments-aim' ),
				'default'	=> __( 'Merrco', 'mer-merrcopayments-aim' ),
			),
			'description' => array(
				'title'		=> __( 'Description', 'mer-merrcopayments-aim' ),
				'type'		=> 'textarea',
				'desc_tip'	=> __( 'Payment description the customer will see during the checkout process.', 'mer-merrcopayments-aim' ),
				'default'	=> __( 'Pay securely using your credit card.', 'mer-merrcopayments-aim' ),
				'css'		=> 'max-width:350px;'
			),
			'api_login' => array(
				'title'		=> __( 'Merrco API Login ID', 'mer-merrcopayments-aim' ),
				'type'		=> 'text',
				'desc_tip'	=> __( 'This is the API Login provided by Paysafe User id when you signed up for an account.', 'mer-merrcopayments-aim' ),
			),
			'trans_key' => array(
				'title'		=> __( ' Merrco Transaction Key', 'mer-merrcopayments-aim' ),
				'type'		=> 'password',
				'desc_tip'	=> __( 'This is the Transaction Key provided by Paysafe transaction key when you signed up for an account.', 'mer-merrcopayments-aim' ),
			),


			'acc_number' => array(
				'title'		=> __( ' Merrco Account Number', 'mer-merrcopayments-aim' ),
				'type'		=> 'text',
				'desc_tip'	=> __( 'This is the Account Number provided by Paysafe Merrco when you signed up for an account.', 'mer-merrcopayments-aim' ),
			),


			'currency_code' => array(
				'title'		=> __( ' Currency code', 'mer-merrcopayments-aim' ),
				'type'		=> 'text',
				'desc_tip'	=> __( 'The Currency Code should match the currency of your Paysafe account.', 'mer-merrcopayments-aim' ),
			),

			'currency_base_units_multiplier' => array(
				'title'		=> __( ' Currency Base Units Multipler', 'mer-merrcopayments-aim' ),
				'type'		=> 'text',
				'desc_tip'	=> __( 'Transactions are actually measured in fractions of the currency specified in the $currencyCode; for example, USD transactions are measured in cents. This multiplier is how many of these smaller units make up one of the specified currency. For example, with the $currencyCode USD the value is 100 but for Japanese YEN the multiplier would be 1 as there is no smaller unit.', 'mer-merrcopayments-aim' ),
			),

			'auth_capture_settlement' => array(
				'title'		=> __( 'Authorization Settlement Yes/No', 'mer-merrcopayments-aim' ),
				'label'		=> __( 'Enable Authorization Settlement', 'mer-merrcopayments-aim' ),
				'type'		=> 'checkbox',
				'description'   => __( 'Check = yes This indicates whether the request is an Authorization only (no Settlement), or a Purchase )' ),
				'default'	=> 'no',
			),

			'saved_cards' => array(
            'type'          => 'checkbox',
            'title'         => __( 'Saved Cards', 'mer-merrcopayments-aim' ),
            'description'   => __( 'Allow customers to use saved cards for future purchases.', 'mer-merrcopayments-aim' ),
            'default'       => 'no',
            ),

            'card_type_field' => array(               
            'type'    => 'multiselect',
            'title'         => __( 'Cards Type 2', 'mer-merrcopayments-aim' ),
            'options' => array(
				        "vi"  => "Visa Card",
				        "mc"  => "MasterCard",
				        "ae"  => "American Express",
				        "di"  => "Discover",
				        "jcb" => "JCB",
				        "dn"  => "Dinner Card"
                         ),	
            'title'         => __( 'Cards Type', 'mer-merrcopayments-aim' ),
            'description'   => __( 'Press Ctrl and select for multiple Card type', 'mer-merrcopayments-aim' ),
            ), 

			'environment' => array(
				'title'		=> __( 'Merrco Test Mode', 'mer-merrcopayments-aim' ),
				'label'		=> __( 'Enable Test Mode', 'mer-merrcopayments-aim' ),
				'type'		=> 'checkbox',
				'description' => __( 'Place the payment gateway in test mode.', 'mer-merrcopayments-aim' ),
				'default'	=> 'no',
			)
		);


	}	
     /**
     * Process the payment and return the result
     *
     * @access      public
     * @param       int $order_id
     * @return      array/boolean
     */
    public function process_payment( $order_id ) {

    	$order = wc_get_order( $order_id );
    	$cardNumberExpiry 	= $_POST['mer_merrco-card-expiry'];
    	$expcardexp         = explode('/',$cardNumberExpiry);
        $cardMonth          = (int)str_replace(array(' ', ','), '', $expcardexp[0]);           
        $cardYear           = (int)str_replace(array(' ', ','), '', $expcardexp[1]);

        if( strlen($cardYear) != 4 ){
        	$cardYear = '20'.$cardYear; // paysafe require 4 digit year to process payment
        }
        
    	WC_Paysafe_Logger::log("POST DATA for order $order_id : ".print_r($_POST, true));
		
    	// check if payment method variable set for us
    	if( isset( $_POST['payment_method'] ) && isset( $_POST['payment_method'] ) == $this->id ){

    		if( $this->is_using_saved_payment_method() ){

    			$wc_token_id = wc_clean( $_POST[ 'wc-' . $this->id . '-payment-token' ] );
		        $wc_token    = WC_Payment_Tokens::get( $wc_token_id );

		        if ( ! $wc_token || $wc_token->get_user_id() !== get_current_user_id() ) {
		            WC()->session->set( 'refresh_totals', true );
		            wc_add_notice( __( 'Invalid payment method. please add new payment method and try again' ), 'error' );
		        }
    			$response = WC_Gateway_Paysafe_Request::make_token_payment_request($order, $wc_token->get_token());

    		} else {
    			$response = WC_Gateway_Paysafe_Request::make_cc_payment_request($order);
    		}
    		

			update_post_meta( $order_id, '_paysafe_status', $response->status );

			if( isset($response->status) && $response->status == 'COMPLETED'){

			    update_post_meta( $order_id, '_paysafe_transaction_id', $response->id );

			    if( $this->is_using_saved_payment_method() ){
			    	update_post_meta( $order_id, '_paysafe_token_id', $wc_token_id );
			    }

			    $order->payment_complete();

			    $order->add_order_note(
			        sprintf(
			            __( 'Payment completed with Transaction Id of "%s"', 'paysafe-for-woocommerce' ), $response->id
			        )
			    );

			    wc_reduce_stock_levels($order_id);

			    WC()->cart->empty_cart();

			    $result = array(
			        'result' => 'success',
			        'redirect' => $this->get_return_url( $order )
			    );

			    return $result;
			            

			} else {
			   
				$order->update_status( 'failed' );

			    WC_Paysafe_Logger::log("Payment faield for order $order_id, auth response is:".print_r($response, true));
			    
			    wc_add_notice( __( 'Transaction Error: Could not complete your payment: Please check the Payment Details and try again', 'paysafe-for-woocommerce' ), 'error' );

			    return array(
					'result'   => 'fail',
					'redirect' => '',
				);
			}  
    		

		} else {
			WC_Paysafe_Logger::log("payment_method variable is not set for us. - ".$_POST['payment_method']);
		}


    	    
    }
    /**
     * Send form data to merrco
     * Handles the ApI, Credit Card Payments, Token
     *
     * @access      protected
     * @param       int $order_id
     * @return      bool
     */
    protected function send_to_merrco_gateway( $order_id ) {
        // Get the order based on order_id
        $this->order = new WC_Order( $order_id );
        // Include the merrco payment files.
        
		$order = wc_get_order( $order_id );
        $totalAmount=(int)str_replace(array(' ', ','), '', $order->total);
        $billing_first_name=str_replace( array(' ', '-' ), '', $_POST['billing_first_name'] );
        $billing_last_name=str_replace( array(' ', '-' ), '', $_POST['billing_last_name'] );
        $billing_phone=str_replace( array(' ', '-' ), '', $_POST['billing_phone'] );
        $billing_email=str_replace( array(' ', '-' ), '', $_POST['billing_email'] );
        $billing_country=str_replace( array(' ', '-' ), '', $_POST['billing_country'] );
        $billing_city=str_replace( array(' ', '-' ), '', $_POST['billing_city'] );
        $billing_postcode=str_replace( array(' ', '-' ), '', $_POST['billing_postcode'] );
        $billing_address_1= $_POST['billing_address_1'];
        $cardNumber=str_replace( array(' ', '-' ), '', $_POST['mer_merrco-card-number'] );
        $cardNumberExpiry=str_replace( array(' ', '-' ), '', $_POST['mer_merrco-card-expiry'] );
        $cardCvv=str_replace( array(' ', '-' ), '', $_POST['mer_merrco-card-cvc'] );	       
        $merrcoMethod=$_POST['mer_merrco_payment_method']; 
	    $merrco_request_object = new WC_Gateway_Paysafe_Request;
	    
        $merrcoApiKeyId=$this->api_login;
        $merrcoApiKeySecret=$this->trans_key;
        $merrcoAccountNumber=$this->acc_number;
        $currencyBaseUnitsMultiplier=$this->currency_base_units_multiplier;
        $authCaptureSettlement=$this->auth_capture_settlement;
        $environment = ( $this->environment == "yes" ) ? 'TEST' : 'LIVE';            
        if(is_user_logged_in() || isset($_POST['createaccount'])) {
        	  $tokenRequest = ( $this->saved_cards == "yes" ) ? 'token' : 'cc';
        } else {
        	$tokenRequest='guestaccount';
          }                   
       if($merrcoMethod=="mer_merrco_credit_card") {
       	$expcardexp 	= explode('/',$cardNumberExpiry);
        $cardMonth 		= (int)str_replace(array(' ', ','), '', $expcardexp[0]);	       
        $cardYear 		= (int)str_replace(array(' ', ','), '', $expcardexp[1]);
        $merrco_request=$merrco_request_object->get_request_merrco_url_cc($order_id, $merrcoApiKeyId, $merrcoApiKeySecret, $merrcoAccountNumber, $environment, $totalAmount, $cardNumber, $cardMonth, $cardYear, $cardCvv, $billing_address_1, $billing_country, $billing_city, $billing_postcode, $currencyBaseUnitsMultiplier, $tokenRequest, $billing_first_name, $billing_last_name, $billing_email,$merrcoMethod, $authCaptureSettlement);

       } else {
            $tokenKeyId=str_replace( array(' ', '-' ), '', $_POST['mer_merrco-token-number'] );
       		$merrco_request=$merrco_request_object->get_request_merrco_url_token($merrcoApiKeyId, $merrcoApiKeySecret, $merrcoAccountNumber, $environment, $totalAmount, $currencyBaseUnitsMultiplier, $tokenKeyId, $authCaptureSettlement, $order_id);
         }
         //echo "Response Code: ".$merrco_request['responsecode'];
        if($merrco_request['responsecode']=='0'){
	        $transactionID=$merrco_request['transaction_id'];
	        $this->transaction_id = $transactionID;
	        $status=$merrco_request['status'];
	        update_post_meta( $order->get_id(), '_merrco_status', $status );
			update_post_meta( $order->get_id(), '_merrco_transaction_id', $transactionID );
	            if($tokenRequest === "token"  && $merrcoMethod=="mer_merrco_credit_card"){
                $tokenkeyID=$merrco_request['tokenkey'];
                $storeCc=substr($cardNumber, 0, 4) . str_repeat("*", strlen($cardNumber) - 8) . substr($cardNumber, -4);
                $merrco_trasc_store= array(
                              "merrco_cust_id"=>get_current_user_id(),
                              "merrco_cardnum"=>$storeCc,
                              "merrco_tokenno"=>$tokenkeyID,
                              "merrco_token_request"=>$tokenRequest,
                              "merrco_date_of_card_used"=>date("jS F Y")
                	);

	            update_post_meta( $order->get_id(), '_merrco_token_card_info', $merrco_trasc_store );

	            }

	         return true; 
        } else {
        	 if($environment!='LIVE') {
        	 $erro_info=$merrco_request['errormessage'];
        	 wc_add_notice( __( "$erro_info" ), 'error' );
        	 }
	      	  return false; 
          }
    }
    /**
     * Mark the payment as failed in the order notes
     *
     * @access      protected
     * @return      void
     */
    protected function payment_failed() {
        $this->order->add_order_note(
            sprintf(
                __( 'Payment failed', 'mer-merrcopayments-aim' ),
                get_class( $this ),
                $this->transaction_error_message
            )
        );
    }

    /**
 * Mark the payment as completed in the order notes
 *
 * @access      protected
 * @return      void
 */
protected function order_complete() {

    if ( $this->order->status == 'completed' ) {
        return;
    }
 //    $update_order_req = array(
	//       'ID'           => $this->order->get_id(),
	//       'post_status'   => 'wc-processing',
	//   );

	// wp_update_post( $update_order_req );

	$this->order->payment_complete();

    $this->order->add_order_note(
        sprintf(
            __( '%s payment completed with Transaction Id of "%s"', 'paysafe-for-woocommerce' ),
            get_class( $this ),
            $this->transaction_id
        )
    );
}

	
	public function is_using_saved_payment_method() {
		$payment_method = isset( $_POST['payment_method'] ) ? wc_clean( $_POST['payment_method'] ) : $this->id;

		return ( isset( $_POST[ 'wc-' . $payment_method . '-payment-token' ] ) && 'new' !== $_POST[ 'wc-' . $payment_method . '-payment-token' ] );
	}
	/**
     * Validate credit card and Token form fields
     *
     * @access      public
     * @return      bool
     */
	public function validate_fields() {

		if( !$this->is_using_saved_payment_method() ){

			$merrcoMethod=$_POST['mer_merrco_payment_method'];
	        if($merrcoMethod=="mer_merrco_credit_card"){
		      	if($_POST['mer_merrco-card-number']!="" && $_POST['mer_merrco-card-cvc']!="" && $_POST['mer_merrco-card-expiry']!="") {

	                 $accountNumber=str_replace( array(' ', '-' ), '', $_POST['mer_merrco-card-number'] );

	                 $cardCode = array(
					        "vi"  => "Visa Card",
					        "mc"  => "MasterCard",
					        "ae"  => "American Express",
					        "di"  => "Discover",
					        "jcb" => "JCB",
					        "dn"  => "Dinner Card"
					              );

					 $cardType = array(
					        "visa"       => "/^4[0-9]{12}(?:[0-9]{3})?$/",
					        "mastercard" => "/^5[1-5][0-9]{14}$/",
					        "amex"      => "/(^3[47])((\d{11}$)|(\d{13}$))/",
					        "discover"   => "/^6(?:011|5[0-9]{2})[0-9]{12}$/",
					        "jcb"   => "/(^(352)[8-9](\d{11}$|\d{12}$))|(^(35)[3-8](\d{12}$|\d{13}$))/",
					        "dn"  => "/(^(30)[0-5]\d{11}$)|(^(36)\d{12}$)|(^(38[0-8])\d{11}$)/"
					             );

						if (preg_match($cardType['visa'],$accountNumber)) {							
						    $result='vi'; 
						} elseif (preg_match($cardType['mastercard'],$accountNumber)) {							
						    		$result='mc';  
						    } elseif (preg_match($cardType['amex'],$accountNumber)) {							
						              $result='ae';							
						        } elseif (preg_match($cardType['discover'],$accountNumber)) {							
						                  $result='di';
						            } elseif (preg_match($cardType['jcb'],$accountNumber)) {
							                  $result='jcb';
						                } elseif (preg_match($cardType['dn'],$accountNumber)) {
							                  $result='dn';
						                    } else {
							    	            wc_add_notice( "Wrong card", 'error' );
							                    return false;
						                       }                                                
	                    $cardTypes=$this->card_type_field;
	                    if($cardTypes!="") {                               
	                                    $active_cards='';
	                                    foreach($cardTypes as $key) {
										    if(array_key_exists($key, $cardCode)) {
										        $active_cards.=$cardCode[$key].", ";
										    }
										}

		                        if (!in_array($result, $cardTypes)) {
		                        	 $cards_allow=rtrim($active_cards,', ');
									 wc_add_notice("Card type should be <b> $cards_allow </b>" , 'error' );
									 return false;
								} 
						}

	            } else {
			        wc_add_notice( "Provide the Card detials", 'error' );
		      		return false;
		      	  }           
	      }
    	}
	}

	public function add_payment_method() {

		$error     = false;
		$error_msg = __( 'There was a problem adding the payment method.', 'woocommerce-gateway-stripe' );
		$source_id = '';

		if ( !isset($_POST['woocommerce_add_payment_method']) || $_POST['woocommerce_add_payment_method'] != 1 
			|| !is_user_logged_in() ) {
			$error = true;
		}

		$user_info 	= wp_get_current_user();
		$fname 		= $user_info->user_firstname;
		$lname 		= $user_info->user_lastname;
		$email 		= $user_info->user_email;

		$cardNumber 		= str_replace( array(' ', '-' ), '', $_POST['mer_merrco-card-number'] );
        $cardNumberExpiry 	= str_replace( array(' ', '-' ), '', $_POST['mer_merrco-card-expiry'] );
        $cardCvv 			= str_replace( array(' ', '-' ), '', $_POST['mer_merrco-card-cvc'] );

        $expcardexp 		= explode('/',$cardNumberExpiry);
        $cardMonth 			= (int)str_replace(array(' ', ','), '', $expcardexp[0]);	       
        $cardYear 			= (int)str_replace(array(' ', ','), '', $expcardexp[1]);
        $card_last_four_digits = substr($cardNumber, -4);

        $response = WC_Gateway_Paysafe_Request::get_token();
		
		if ( !$response ) {
			wc_add_notice( $error_msg, 'error' );
			WC_Paysafe_Logger::log( 'Add payment method Error: ' . $error_msg );
			return;
		}

		WC_Paysafe_Logger::log("get_token response: ".print_r($response, true));


		// update user meta values for future user
		update_user_meta( get_current_user_id(), '_paysafe_profile_id', $response['profile']->id );

		if( isset($response['address']) ){
			$address_id = $response['profile']->id;
			$saved_addresses = get_user_meta( get_current_user_id(), '_paysafe_address_id' );
			if(!empty($saved_addresses)){
				$saved_addresses = array_push( $saved_addresses, $address_id );
			} else {
				$saved_addresses = $address_id;
			}

			update_user_meta( get_current_user_id(), '_paysafe_address_id', $saved_addresses );
				
		}

		$card_type = array(
			'VI' => 'visa',
			'MC' => 'mastercard',
			'DI' => 'discover',
			'DC' => 'diners',
			'AM' => 'american express',
		);

		if( isset($response['card']) && $response['card']->status == 'ACTIVE'){
			$token = new WC_Payment_Token_CC();
			$token->set_token( $response['card']->paymentToken );
			$token->set_gateway_id( $this->id );
			$token->set_last4( $response['card']->lastDigits );
			$token->set_expiry_year( $response['card']->cardExpiry->year );
			$token->set_expiry_month( sprintf("%02d", $response['card']->cardExpiry->month) );
			$token->set_card_type( $card_type[$response['card']->cardType] );
			$token->set_user_id( get_current_user_id() );
			$token->save();
			
			if($token->validate()){
				// Set this token as the users new default token
				WC_Payment_Tokens::set_users_default( get_current_user_id(), $token->get_id() );

				return array(
					'result'   => 'success',
					'redirect' => wc_get_endpoint_url( 'payment-methods' ),
				);
			} else {
				wc_add_notice( $error_msg, 'error' );
				WC_Paysafe_Logger::log( 'Could not create token ' . $response );
			}
		} else {
			wc_add_notice( $error_msg, 'error' );
			WC_Paysafe_Logger::log( 'get_token response ' . $$response );
			return;
		}		

		
	}
}