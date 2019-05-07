<?php
class Mer_Pay_Back_Functions {
	/**
     * Construct Function of Mer_Pay_Back_Functions
     * Initialize the hooks associated with WooCommerce.
     * @return      void
    */
	public function __construct() {
		add_filter( 'woocommerce_payment_gateways', array($this,'mer_pay_add_gateway' ));
		add_action( 'woocommerce_admin_order_data_after_billing_address', array($this,'mer_checkout_field_display_admin_order_meta'), 10, 1 );
	}
	/**
     * Initialize The Payment Gateway
     * Initialize the hooks associated with WooCommerce.
     * @return      array
    */
	public static function mer_pay_add_gateway( $methods ) {

     if ( class_exists( 'WC_Subscriptions_Order' ) ) {
    
        include_once( 'class-merrco_subscriptions_gateway.php' );

            $methods[] = 'Merrco_Subscriptions_Gateway';
        } else {
            $methods[] = 'Merrco_Gateway_Init';
        }
		return $methods;
	}
	/**
     * Show the Transacion ID details on Order Details meta box.
     * @return      void
    */
	public static function mer_checkout_field_display_admin_order_meta($order) { 
		$mer_pay_tran_id = get_post_meta( get_the_ID(), '_merrco_transaction_id', true ); 

		if ( ! empty( $mer_pay_tran_id ) )  { 
			echo '<p><strong>'. __("Paysafe Transaction key", "mer-merrcopayments-aim").':</strong> <br/>' . get_post_meta( get_the_ID(), '_merrco_transaction_id', true ) . '</p>'; 
            echo '<p><strong>'. __("Paysafe Status", "mer-merrcopayments-aim").':</strong> <br/>' . get_post_meta( get_the_ID(), '_merrco_status', true ) . '</p>'; 
		} 
	}

}
new Mer_Pay_Back_Functions;

class WC_Paysafe_Logger {

    public static $logger;
    const WC_LOG_FILENAME = 'woocommerce-gateway-paysafe';

    /**
     * Utilize WC logger class
     *
     * @since 4.0.0
     * @version 4.0.0
     */
    public static function log( $message, $start_time = null, $end_time = null ) {
        if ( ! class_exists( 'WC_Logger' ) ) {
            return;
        }

        if ( apply_filters( 'wc_paysafe_logging', true, $message ) ) {
            if ( empty( self::$logger ) ) {
                if ( version_compare( WC_VERSION, '3.0.0', '>=' ) ) {
                    self::$logger = wc_get_logger();
                } 
            }

            $settings = get_option( 'woocommerce_paysafe_settings' );

            if ( empty( $settings ) || isset( $settings['logging'] ) && 'yes' !== $settings['logging'] ) {
                return;
            }

            if ( ! is_null( $start_time ) ) {

                $formatted_start_time = date_i18n( get_option( 'date_format' ) . ' g:ia', $start_time );
                $end_time             = is_null( $end_time ) ? current_time( 'timestamp' ) : $end_time;
                $formatted_end_time   = date_i18n( get_option( 'date_format' ) . ' g:ia', $end_time );
                $elapsed_time         = round( abs( $end_time - $start_time ) / 60, 2 );

                $log_entry  = "\n" . '====Paysafe Version: ' . WC_PAYSAFE_VERSION . '====' . "\n";
                $log_entry .= '====Start Log ' . $formatted_start_time . '====' . "\n" . $message . "\n";
                $log_entry .= '====End Log ' . $formatted_end_time . ' (' . $elapsed_time . ')====' . "\n\n";

            } else {
                $log_entry  = "\n" . '====Paysafe Version: ' . WC_PAYSAFE_VERSION . '====' . "\n";
                $log_entry .= '====Start Log====' . "\n" . $message . "\n" . '====End Log====' . "\n\n";

            }

            if ( version_compare( WC_VERSION, '3.0.0', '>=' ) ) {
                self::$logger->debug( $log_entry, array( 'source' => self::WC_LOG_FILENAME ) );
            } else {
                self::$logger->add( self::WC_LOG_FILENAME, $log_entry );
            }
        }
    }
}