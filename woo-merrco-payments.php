<?php
/*
Plugin Name: Wocommerce Merrco Payments Gateway
Plugin URI: http://merrco.ca/
Description: Payment using Merrco (Credit Card & Tokenisation) method.
Version: 1.0
Author: Merrco
Author URI: #
*/
include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
define( 'WC_PAYSAFE_VERSION', '1.0.0' );
/** Define the Urls  **/
define( 'MER_PAY_BASE', plugin_basename( __FILE__ ) );
define( 'MER_PAY_DIR', plugin_dir_path( __FILE__ ) );
define( 'MER_PAY_URL', plugin_dir_url( __FILE__ ) );
define( 'MER_PAY_AST', plugin_dir_url( __FILE__ ).'assets/' );
define( 'MER_PAY_IMG', plugin_dir_url( __FILE__ ).'assets/images' );
define( 'MER_PAY_CSS', plugin_dir_url( __FILE__ ).'assets/css' );
define( 'MER_PAY_JS', plugin_dir_url( __FILE__ ).'assets/js' );
/**----- End -----  **/
require 'controllers/class-merrco-payments-main.php';
register_activation_hook( __FILE__, array('Merrco_Payments_Main', 'install') );