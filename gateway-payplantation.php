<?php
/*
 * Plugin Name: PayPlantation Payment Gateway
 * Plugin URI: https://payplantation.com/wp/payplantation
 * Description: Accept all major credit cards directly on your WooCommerce site in a seamless and secure checkout environment with PayPlantation.
 * Version: 1.0.0
 * Author: payplantation
 * Author URI: https://payplantation.com/
 * Developer: khalilfareh
 * Developer URI: https://www.fiverr.com/khalilfareh
 * Text Domain: woocommerce-extension
 */
if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}


require 'lib/vendor/autoload.php';
use PayMoney\Api\Amount;
use PayMoney\Api\Payer;
use PayMoney\Api\Payment;
use PayMoney\Api\RedirectUrls;
use PayMoney\Api\Transaction;

add_action('plugins_loaded', 'init_payplantation');
function is_woocommerce_active() 
{
    // Check if WooCommerce is active.
    return class_exists( 'WooCommerce' ) && function_exists( 'WC' );
}


function init_payplantation() {
	if ( is_woocommerce_active() ) {
		class WC_Gateway_PAYPLANTAIONPAY extends WC_Payment_Gateway {
			/**
			 * Constructor for the gateway.
			 */
			public function __construct() {
				// Setup general properties.
				$this->setup_properties();

				// Load the settings.
				$this->init_form_fields();
				$this->init_settings();

				// Get settings.
				$this->title = $this->get_option('title');
				$this->description = $this->get_option('description');
				$this->instructions = $this->get_option('instructions');
				$this->enable_for_methods = $this->get_option('enable_for_methods', []);
				$this->client_id = $this->get_option('client_id');
				$this->client_secret = $this->get_option('client_secret');

				// Actions.
				add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
				add_action('woocommerce_api_wc_gateway_payplantation', [$this, 'check_callback'], 10, 2);
			}

			/**
			 * Setup general properties for the gateway.
			 */
			protected function setup_properties() {
				$this->id = 'payplantation';
				$this->icon = apply_filters('woocommerce_payplantation_icon', '');
				$this->method_title = __('PayPlantation', 'woocommerce');
				$this->method_description = __('Have your customers pay with PayPlantation via CreditCard or PayPlantation Wallet.', 'woocommerce');
				$this->has_fields = false;
			}

			/**
			 * Initialise Gateway Settings Form Fields.
			 */
			public function init_form_fields() {
				$this->form_fields = [
					'enabled' => [
						'title' => __('Enable/Disable', 'woocommerce'),
						'label' => __('Enable PayPlantation', 'woocommerce'),
						'type' => 'checkbox',
						'description' => '',
						'default' => 'no',
					],
					'title' => [
						'title' => __('Title', 'woocommerce'),
						'type' => 'safe_text',
						'description' => __('Payment method description that the customer will see on your checkout.', 'woocommerce'),
						'default' => __('PayPlantation', 'woocommerce'),
						'desc_tip' => true,
					],
					'description' => [
						'title' => __('Description', 'woocommerce'),
						'type' => 'textarea',
						'description' => __('Payment method description that the customer will see on your website.', 'woocommerce'),
						'default' => __('Pay via PayPlantation; you can pay with your credit card, or with PayPlantation wallet', 'woocommerce'),
						'desc_tip' => true,
					],
					'client_id' => [
						'title' => __('PayPlantation Client ID', 'woocommerce'),
						'type' => 'text',
						'description' => __('Get your API credentials from PayPlantation.', 'woocommerce'),
						'default' => '',
						'desc_tip' => true,
						'placeholder' => __('Optional', 'woocommerce'),
					],
					'client_secret' => [
						'title' => __('PayPlantation Client Secret', 'woocommerce'),
						'type' => 'text',
						'description' => __('Get your API credentials from PayPlantation.', 'woocommerce'),
						'default' => '',
						'desc_tip' => true,
						'placeholder' => __('Optional', 'woocommerce'),
					],
				];
			}

			/**
			 * Checks to see whether or not the admin settings are being accessed by the current request.
			 *
			 * @return bool
			 */
			private function is_accessing_settings() {
				if (is_admin()) {
					// phpcs:disable WordPress.Security.NonceVerification
					if (!isset($_REQUEST['page']) || 'wc-settings' !== $_REQUEST['page']) {
						return false;
					}
					if (!isset($_REQUEST['tab']) || 'checkout' !== $_REQUEST['tab']) {
						return false;
					}
					if (!isset($_REQUEST['section']) || 'payplantation' !== $_REQUEST['section']) {
						return false;
					}
					// phpcs:enable WordPress.Security.NonceVerification

					return true;
				}

				if (Constants::is_true('REST_REQUEST')) {
					global $wp;
					if (isset($wp->query_vars['rest_route']) && false !== strpos($wp->query_vars['rest_route'], '/payment_gateways')) {
						return true;
					}
				}

				return false;
			}

			/**
			 * Process the payment and return the result.
			 *
			 * @param int $order_id order ID
			 *
			 * @return array
			 */
			public function process_payment( $order_id ) {
				$order = wc_get_order($order_id);
				//Payer Object
				$payer = new Payer();
				$payer->setPaymentMethod('Payplantation'); //preferably, your system name, example - PayMoney

				//Amount Object
				$amountIns = new Amount();
				$amountIns->setTotal($order->get_total())->setCurrency($order->get_currency());

				//Transaction Object
				$trans = new Transaction();
				$trans->setAmount($amountIns);
				$succsesUrl = str_replace('https:', 'http:', add_query_arg(['wc-api' => 'wc_gateway_payplantation', 'order_id' => $order_id, 'token' => '='], home_url('/')));

				//RedirectUrls Object
				$urls = new RedirectUrls();
				$urls->setSuccessUrl($succsesUrl) //success url - the merchant domain page, to redirect after successful payment, see sample example-success.php file in sdk root, example - http://techvill.net/paymoney_sdk/example-success.php

				->setCancelUrl($succsesUrl); //cancel url - the merchant domain page, to redirect after cancellation of payment, example -  http://techvill.net/paymoney_sdk/

				//Payment Object
				$payment = new Payment();
				$payment->setCredentials([ //Client ID & Secret = Merchants->setting(gear icon)
					'client_id' => $this->client_id, //must provide correct client id of an express merchant
					'client_secret' => $this->client_secret, //must provide correct client secret of an express merchant
				])
				->setRedirectUrls($urls)
				->setPayer($payer)
				->setTransaction($trans);

				try {
					$payment->create(); //create payment

					return [
								'result' => 'success',
								'redirect' => $payment->getApprovedUrl(),
								];
				} catch (\Exception $ex) {
					wc_add_notice(__($ex->getMessage(), 'woocommerce-payplantation-payment-gateway'), 'error');

					return [
								'result' => 'failure',
								];
				}
			}

			public function check_callback() {
				global $woocommerce;

				if (isset($_GET['order_id']) && isset($_GET['token'])) {
					$order = wc_get_order(filter_var($_GET['order_id'], FILTER_SANITIZE_NUMBER_INT));
					$encoded = wp_json_encode(filter_var($_GET['token'], FILTER_SANITIZE_STRING));
					$encoded = substr($encoded, 1);
					$decoded = json_decode(base64_decode($encoded), true);

					if (200 === $decoded['status']) {
						$tranId = $decoded['transaction_id'];

						$this->process_success($order, $tranId);
					} else {
						$message = 'the payment failed Status :' . $decoded['status'];

						$this->process_failure($order, $message);
					}
				}

				wp_redirect(wc_get_checkout_url());

				exit;
			}

			public function process_success( $order, $tranId ) {
				global $woocommerce;

				$order->add_order_note(
					sprintf(
					__("The Order was successfully processed through PayPlantation, you can see this payment in PayPlantation transaction history \n",
						'wc_gateway_payplantation'),
					get_woocommerce_currency(),
					$order->get_total()
					));
				$order->payment_complete();
				$order->add_order_note(
					__(
					'Order status updated for payment completion. Transaction Id :' . $tranId,
					'wc_gateway_payplantation'
					)
				);
				$woocommerce->cart->empty_cart();

				wp_redirect($order->get_checkout_order_received_url());

				exit;
			}

			public function process_failure( $order, $message ) {
				if (empty($message)) {
					$message = __(
					'Could not connect to Payplantation server.',
					'wc_gateway_payplantation'
				);
				}
				// Cancel Order

				$order->update_status(
				'failed',
				sprintf(
					/* translators: %s: order_id */
					__('%s payment cancelled!', 'wc_gateway_payplantation'),
					get_woocommerce_currency() . ' ' . $order->get_total(),
					$order->get_id(),
					$message
				)
				);
				// Add WC Notice
				wc_add_notice($this->payplantation_wc_customer_error_msg, 'error');
				$this->set_wc_admin_notice('Payplantation Payment Gateway Error [Order# ' . $order->get_id() . ']: ' . $message);

				// Redirect to Cart Page
				wp_redirect(wc_get_checkout_url());

				exit;
			}

			public function set_wc_admin_notice( $message ) {
				$html = __('<h2 class="payplantation_pg_admin_notice">' . $message . '</h2>', 'payplantation_payment_gateway');
				WC_Admin_Notices::add_custom_notice('payplantation_payment_gateway', $html);
			}
		}
		add_filter('woocommerce_payment_gateways', 'payplantation_class');
		function payplantation_class( $methods ) {
			$methods[] = 'WC_Gateway_PAYPLANTAIONPAY';

			return $methods;
		}
	}
}
