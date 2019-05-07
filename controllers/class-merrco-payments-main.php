<?php

add_action( 'plugins_loaded', 'wc_merrco_payment_init' );

function wc_merrco_payment_init(){

class Merrco_Payments_Main {

	/**
	* @var Singleton The reference the *Singleton* instance of this class
	*/
	private static $instance;

	/**
	* Returns the *Singleton* instance of this class.
	*
	* @return Singleton The *Singleton* instance.
	*/
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
     * Construct Function of Merrco_Payments_Main
     * Initialize the hooks, run at the time of plugin initialization.
     * @return      void
    */
	public function __construct() {
		$this->mer_pay_woo_check();
		add_filter( 'plugin_action_links_' . MER_PAY_BASE, array($this,'mer_pay_action_links') );
		add_action( 'wp_footer', array($this,'merrco_footer_script' ));
	}
	/**
     * Add CSS and JS for Frontend.
     * @return      void
    */
	public static function merrco_footer_script() {
		wp_enqueue_script( 'custom-script', MER_PAY_JS . '/merrco.js', array( 'jquery' ) );
	 	wp_enqueue_style( 'slider', MER_PAY_CSS . '/merrco.css',false,'1.1','all');
    }
	/**
     * Check woocommerce active or not.
     * If Not-active then auto deactive the plugins, with message.  
     * @return      void
    */
	
	public static function install() {
		if ( !in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
			deactivate_plugins(__FILE__);
			$error_message = __('This plugin requires <a href="http://wordpress.org/extend/plugins/woocommerce/">WooCommerce</a> plugins to be active!', 'woocommerce');
			die($error_message);
		}
	}
	/**
     * Check woocommerce active or not.
     * If Active then include the required files.  
     * @return      void
    */
	public static function mer_pay_woo_check() {
		if ( !is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
			deactivate_plugins('woo-merrco-payments/woo-merrco-payments.php');
		}
		else{
			
			require( 'backend/includes/card-token-payment.php' );
			require( 'backend/mer-pay-back-functions.php');
			require( 'backend/merrco-gateway-init.php' );
			//require( 'backend/wooautoship-integration.php' );
		}
	}
	/**
     * Set Action Links for Action Links.
     * @return      array
    */
	public static function mer_pay_action_links( $links ) {
		$plugin_links = array(
			'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=mer_merrco' ) . '">' . __( 'Settings', 'mer-merrcopayments-aim' ) . '</a>',
		);
		return array_merge( $plugin_links, $links );	
	}

}
	Merrco_Payments_Main::get_instance();
}