<?php
/*
 *	Add paysafe to allowed gateway id list
 *
 *
 */
add_filter( 'autoship_valid_payment_method_ids', 'add_paysafe_pament_method_id');
function add_paysafe_pament_method_id( $payment_methods ){
	$payment_methods['mer_merrco'] = 'mer_merrco';
	echo "autoship_valid_payment_method_ids";
	print_r($payment_method);
	return $payment_methods;
}

/*
 *	Add paysafe to allowed gateway type list
 *
 *
 */
add_filter( 'autoship_valid_payment_method_types', 'add_paysafe_pament_method_type');
function add_paysafe_pament_method_type( $types ){
	$types['mer_merrco'] = 'Paysafe';
	echo "autoship_valid_payment_method_types";
	print_r($types);
	return $types;
}

/*
 *	Add paysafe to allowed gateway id list
 *
 *
 */
add_filter( 'autoshop_extend_gateway_id_types', 'add_paysafe_extend_gateway_id_types');
function add_paysafe_extend_gateway_id_types( $payment_methods ){
	$payment_methods['mer_merrco'] = 'Paysafe';
	echo "add_paysafe_extend_gateway_id_types";
	print_r($payment_methods);
	return $payment_methods;
}

function autoship_get_mer_merrco_order_payment_data( $order_id ){

	// Grab the Token from the order.
 	$token_id  = get_post_meta( $order_id, '_paysafe_token_id', true);

	if ( ! empty( $token_id ) ) {

    $token = autoship_get_related_tokenized_id( $token_id );

		if ( ! empty( $token ) ) {
  		$payment_data = new QPilotPaymentData();
  		$payment_data->description         = $token->get_display_name();
  		$payment_data->type                = 'Paysafe';
      	$payment_data->gateway_payment_id  = $token->get_token();
  		$payment_data->gateway_customer_id = null;
			return $payment_data;
		}

	}

	return null;

}

/**
* Modifies the Payment Info for a Customer before
* For Stripe payment methods added to QPilot.
* Hooked into the new {@see autoship_add_{$type}_payment_method} filter.
*
*
* @param array  $payment_method_data Current payment Method Data
* @param string $type     The QPilot Method Type
* @param WC_Payment_Token_CC $token
*
* @return array The modified Payment Method Data to Send to QPilot
*/
function autoship_add_mer_merrco_payment_method( $payment_method_data, $type, $token  ) {

  // Stripe uses the customer id from user meta as Gateway Customer ID
  $user_id = $token->get_user_id();
  $payment_method_data['GatewayCustomerId'] = get_user_meta( $user_id, '_paysafe_profile_id', true);
  return $payment_method_data;

}
//add_filter('autoship_add_mer_merrco_payment_method', 'autoship_add_mer_merrco_payment_method', 10 , 3 );
add_filter('autoship_add_Paysafe_payment_method', 'autoship_add_mer_merrco_payment_method', 10 , 3 );