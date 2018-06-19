<?php
/*
  Plugin Name: WooCommerce Borgun Gateway
  Plugin URI: http://tactica.is
  Description: Extends WooCommerce with a <a href="http://www.borgun.com/" target="_blank">Borgun</a> gateway.
  Version: 1.3.5
  Author: Tactica
  Author URI: http://tactica.is
  Requires at least: 4.4
  Tested up to: 4.9.2
  WC tested up to: 3.2.6
  WC requires at least: 3.2.3
  License: GNU General Public License v3.0
  License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */
define( 'BORGUN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BORGUN_URL', plugin_dir_url( __FILE__ ) );
define( 'BORGUN_VERSION', '1.3.5' );
function borgun_wc_active() {
	if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
		return true;
	} else {
		return false;
	}
}

add_action( 'plugins_loaded', 'woocommerce_borgun_init', 0 );
function woocommerce_borgun_init() {
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	}
	//Add the gateway to woocommerce
	add_filter( 'woocommerce_payment_gateways', 'add_borgun_gateway' );
	function add_borgun_gateway( $methods ) {
		$methods[] = 'WC_Gateway_Borgun';

		return $methods;
	}

	/**
	 * @property string testmode
	 * @property string merchantid
	 * @property string paymentgatewayid
	 * @property string secretkey
	 * @property string langpaymentpage
	 * @property string successurl
	 * @property string cancelurl
	 * @property string errorurl
	 * @property string notification_email
	 */
	class WC_Gateway_Borgun extends WC_Payment_Gateway {

		const BORGUN_ENDPOINT_SANDBOX = 'https://test.borgun.is/SecurePay/default.aspx';
		const BORGUN_ENDPOINT_LIVE = 'https://securepay.borgun.is/securepay/default.aspx';

		public function __construct() {
			$this->id                 = 'borgun';
			$this->icon               = BORGUN_URL . '/cards.png';
			$this->has_fields         = false;
			$this->method_title       = 'Borgun';
			$this->method_description = 'Borgun Secure Payment Page enables merchants to sell products securely on the web with minimal integration effort';
			// Load the form fields
			$this->init_form_fields();
			$this->init_settings();
			// Get setting values
			$this->enabled            = $this->get_option( 'enabled' );
			$this->title              = $this->get_option( 'title' );
			$this->description        = $this->get_option( 'description' );
			$this->testmode           = $this->get_option( 'testmode' );
			$this->merchantid         = $this->get_option( 'merchantid' );
			$this->paymentgatewayid   = $this->get_option( 'paymentgatewayid' );
			$this->secretkey          = $this->get_option( 'secretkey' );
			$this->langpaymentpage    = $this->get_option( 'langpaymentpage' );
			$this->successurl         = $this->get_option( 'successurl' );
			$this->cancelurl          = $this->get_option( 'cancelurl' );
			$this->errorurl           = $this->get_option( 'errorurl' );
			$this->notification_email = $this->get_option( 'notification_email' );
			// Hooks
			add_action( 'woocommerce_receipt_borgun', array( $this, 'receipt_page' ) );
			add_action( 'woocommerce_update_options_payment_gateways_borgun', array( $this, 'process_admin_options' ) );
			add_action( 'woocommerce_api_wc_gateway_borgun', array( $this, 'check_borgun_response' ) );
			add_action( 'woocommerce_thankyou', array( $this, 'check_borgun_response' ) );
			if ( ! $this->is_valid_for_use() ) {
				$this->enabled = false;
			}
		}

		public function admin_options() {
			?>
			<h3>Borgun</h3>
			<p>Pay with your credit card via Borgun.</p>
			<?php if ( $this->is_valid_for_use() ) : ?>
				<table class="form-table"><?php $this->generate_settings_html(); ?></table>
			<?php else : ?>
				<div class="inline error"><p><strong><?php _e( 'Gateway Disabled', 'woocommerce' ); ?></strong>: Current
						Store currency is not valid for borgun gateway. Must be in ISK, USD, EUR, GBP, DKK, NOK, SEK,
						CHF, JPY, CAD, HUF</p></div>
				<?php
			endif;
		}

		//Check if this gateway is enabled and available in the user's country
		function is_valid_for_use() {
			if ( ! in_array( get_woocommerce_currency(), array(
				'ISK',
				'USD',
				'EUR',
				'GBP',
				'DKK',
				'NOK',
				'SEK',
				'CHF',
				'JPY',
				'CAD',
				'HUF'
			) )
			) {
				return false;
			}

			return true;
		}

		//Initialize Gateway Settings Form Fields
		function init_form_fields() {
			$this->form_fields = array(
				'enabled'            => array(
					'title'       => 'Enable/Disable',
					'label'       => 'Enable Borgun',
					'type'        => 'checkbox',
					'description' => '',
					'default'     => 'no'
				),
				'title'              => array(
					'title'       => 'Title',
					'type'        => 'text',
					'description' => 'This controls the title which the user sees during checkout.',
					'default'     => 'Borgun'
				),
				'description'        => array(
					'title'       => 'Description',
					'type'        => 'textarea',
					'description' => 'This controls the description which the user sees during checkout.',
					'default'     => 'Pay with your credit card via Borgun.'
				),
				'testmode'           => array(
					'title'       => 'Borgun Test Mode',
					'label'       => 'Enable Test Mode',
					'type'        => 'checkbox',
					'description' => 'Place the payment gateway in development mode.',
					'default'     => 'no'
				),
				'paymentgatewayid'   => array(
					'title'       => 'Payment Gateway ID',
					'type'        => 'text',
					'description' => 'This is the Payment Gateway ID supplied by Borgun.',
					'default'     => '16'
				),
				'merchantid'         => array(
					'title'       => 'Merchant ID',
					'type'        => 'text',
					'description' => 'This is the ID supplied by Borgun.',
					'default'     => '9275444'
				),
				'secretkey'          => array(
					'title'       => 'Secret Key',
					'type'        => 'text',
					'description' => 'This is the Secret Key supplied by Borgun.',
					'default'     => '99887766'
				),
				'notification_email' => array(
					'title'       => 'Notification Email',
					'type'        => 'text',
					'description' => 'This is the email Borgun will send payment receipts to."',
					'default'     => get_option( 'admin_email' )
				),
				'langpaymentpage'    => array(
					'title'       => 'Language of Payment Page',
					'type'        => 'select',
					'description' => 'Select which language to show on Payment Page.',
					'default'     => 'en',
					'options'     => array(
						'is' => 'Icelandic',
						'en' => 'English',
						'de' => 'German',
						'fr' => 'French',
						'it' => 'Italian',
						'pt' => 'Portugese',
						'ru' => 'Russian',
						'es' => 'Spanish',
						'se' => 'Swedish',
						'hu' => 'Hungarian',
						'si' => 'Slovene'
					)
				),
				'successurl'         => array(
					'title'       => 'Success Page URL',
					'type'        => 'text',
					'description' => 'Buyer will be sent to this page after a successful payment.',
					'default'     => ''
				),
				'cancelurl'          => array(
					'title'       => 'Cancel Page URL',
					'type'        => 'text',
					'description' => 'Buyer will be sent to this page if he pushes the cancel button instead of finalizing the payment.',
					'default'     => ''
				),
				'errorurl'           => array(
					'title'       => 'Error Page URL',
					'type'        => 'text',
					'description' => 'Buyer will be sent to this page if an unexpected error occurs.',
					'default'     => ''
				),
			);
		}

		/**
		 * @param WC_Order $order
		 *
		 * @return false|string
		 */
		function check_hash( $order ) {
			$ipnUrl           = WC()->api_request_url( 'WC_Gateway_Borgun' );
			$hash             = array();
			$hash[]           = $this->merchantid;
			$hash[]           = ( $this->successurl != "" ) ? esc_url_raw( $this->successurl ) : esc_url_raw( $this->get_return_url( $order ) );
			$hash[]           = $ipnUrl;
			$hash[]           = 'WC-' . ltrim( $order->get_order_number(), '#' );
			$hash[]           = number_format( $order->get_total(), wc_get_price_decimals(), '.', '' );
			$hash[]           = get_woocommerce_currency();
			$message          = implode( '|', $hash );
			$CheckHashMessage = utf8_encode( trim( $message ) );
			$Checkhash        = hash_hmac( 'sha256', $CheckHashMessage, $this->secretkey );

			return $Checkhash;
		}

		/**
		 * @param WC_Order $order
		 *
		 * @return false|string
		 */
		function check_order_hash( $order ) {
			$hash             = array();
			$hash[]           = 'WC-' . ltrim( $order->get_order_number(), '#' );
			$hash[]           = number_format( $order->get_total(), wc_get_price_decimals(), '.', '' );
			$hash[]           = get_woocommerce_currency();
			$message          = implode( '|', $hash );
			$CheckHashMessage = utf8_encode( trim( $message ) );
			$Checkhash        = hash_hmac( 'sha256', $CheckHashMessage, $this->secretkey );

			return $Checkhash;
		}

		/**
		 * @param WC_Order $order
		 *
		 * @return array
		 */
		function get_borgun_args( $order ) {
			//Borgun Args
			global $wp_version;
			$ipnUrl = WC()->api_request_url( 'WC_Gateway_Borgun' );

			$borgun_args = array(
				'merchantid'             => $this->merchantid,
				'paymentgatewayid'       => $this->paymentgatewayid,
				'checkhash'              => $this->check_hash( $order ),
				'orderid'                => 'WC-' . ltrim( $order->get_order_number(), '#' ),
				'currency'               => get_woocommerce_currency(),
				'language'               => $this->langpaymentpage,
				'SourceSystem'           => 'WP' . $wp_version . ' - WC' . WC()->version . ' - BRG' . BORGUN_VERSION,
				'buyeremail'             => $order->get_billing_email(),
				'returnurlsuccess'       => ( $this->successurl != "" ) ? esc_url_raw( $this->successurl ) : esc_url_raw( $this->get_return_url( $order ) ),
				'returnurlsuccessserver' => $ipnUrl,
				'returnurlcancel'        => ( $this->cancelurl != "" ) ? esc_url_raw( $this->cancelurl ) : esc_url_raw( $order->get_cancel_order_url() ),
				'returnurlerror'         => ( $this->errorurl != "" ) ? esc_url_raw( $this->errorurl ) : esc_url_raw( $this->get_return_url( $order ) ),
				'amount'                 => number_format( $order->get_total(), wc_get_price_decimals(), '.', '' ),
				'pagetype'               => '0',
				//If set as 1 then cardholder is required to insert email,mobile number,address.
				'skipreceiptpage'        => '1',
				'merchantemail'          => $this->notification_email,
			);
			// Cart Contents

			$include_tax = $this->tax_display();

			$item_loop = 0;
			if ( sizeof( $order->get_items( array( 'line_item', 'fee' ) ) ) > 0 ) {
				foreach ( $order->get_items( array( 'line_item', 'fee' ) ) as $item ) {
					if ( 'fee' === $item['type'] ) {
						$fee = $item->get_total();
						if ( $include_tax && $this->fee_tax_display($item) ){
							$fee += $item->get_total_tax();
						}
						$fee_total = $this->round( $fee, $order );
						$borgun_args[ 'itemdescription_' . $item_loop ] = html_entity_decode( $item->get_name(), ENT_NOQUOTES, 'UTF-8' );
						$borgun_args[ 'itemcount_' . $item_loop ]       = 1;
						$borgun_args[ 'itemunitamount_' . $item_loop ]  = $fee_total;
						$borgun_args[ 'itemamount_' . $item_loop ]      = $fee_total;

						$item_loop ++;
					}
					if ( $item['qty'] ) {
						$item_name = $item['name'];
						if ( $meta = wc_display_item_meta( $item ) ) {
							$item_name .= ' ( ' . $meta . ' )';
						}
						$item_subtotal = number_format( $order->get_item_subtotal( $item, $include_tax ), wc_get_price_decimals(), '.', '' );
						$itemamount = $item_subtotal * $item['qty'];
						$borgun_args[ 'itemdescription_' . $item_loop ] = html_entity_decode( $item_name, ENT_NOQUOTES, 'UTF-8' );
						$borgun_args[ 'itemcount_' . $item_loop ]       = $item['qty'];
						$borgun_args[ 'itemunitamount_' . $item_loop ]  = number_format( $item_subtotal, wc_get_price_decimals(), '.', '' );
						$borgun_args[ 'itemamount_' . $item_loop ]      = number_format( $itemamount, wc_get_price_decimals(), '.', '' );
						$item_loop ++;
					}
				}
				if ( $order->get_shipping_total() > 0 ) {
					$shipping_total = $order->get_shipping_total();
					if( $include_tax ) $shipping_total += $order->get_shipping_tax();
					$shipping_total = $this->round( $shipping_total, $order );
					$borgun_args[ 'itemdescription_' . $item_loop ] = 'Sendingarkostnaður (' . $order->get_shipping_method() . ')';
					$borgun_args[ 'itemcount_' . $item_loop ]       = 1;
					$borgun_args[ 'itemunitamount_' . $item_loop ]  = number_format( $shipping_total, wc_get_price_decimals(), '.', '' );
					$borgun_args[ 'itemamount_' . $item_loop ]      = number_format( $shipping_total, wc_get_price_decimals(), '.', '' );
					$item_loop ++;
				}
				if (!$include_tax && $order->get_total_tax() > 0){
					$borgun_args[ 'itemdescription_' . $item_loop ] = 'Virðisaukaskattur';
					$borgun_args[ 'itemcount_' . $item_loop ]       = 1;
					$borgun_args[ 'itemunitamount_' . $item_loop ]  = number_format( $order->get_total_tax(), wc_get_price_decimals(), '.', '' );
					$borgun_args[ 'itemamount_' . $item_loop ]      = number_format( $order->get_total_tax(), wc_get_price_decimals(), '.', '' );
					$item_loop ++;
				}
				if ( $order->get_total_discount() > 0 ) {
					$total_discount = $order->get_total_discount();
/*				Woocommerce can see any tax adjustments made thus far using subtotals.
					Since Woocommerce 3.2.3*/
					if(wc_tax_enabled() && method_exists('WC_Discounts','set_items') && $include_tax){
						$total_discount += $order->get_discount_tax();
					}
					if(wc_tax_enabled() && !method_exists('WC_Discounts','set_items') && !$include_tax){
						$total_discount -= $order->get_discount_tax();
					}

					$total_discount = $this->round($total_discount, $order);
					$borgun_args[ 'itemdescription_' . $item_loop ] = 'Discount';
					$borgun_args[ 'itemcount_' . $item_loop ]       = 1;
					$borgun_args[ 'itemunitamount_' . $item_loop ]  = - number_format( $total_discount, wc_get_price_decimals(), '.', '' );
					$borgun_args[ 'itemamount_' . $item_loop ]      = - number_format( $total_discount, wc_get_price_decimals(), '.', '' );
					$item_loop ++;
				}
			}

			return $borgun_args;
		}

		//Generate the borgun button link
		function generate_borgun_form( $order_id ) {
			global $woocommerce;
			if ( function_exists( 'wc_get_order' ) ) {
				$order = wc_get_order( $order_id );
			} else {
				$order = new WC_Order( $order_id );
			}
			if ( 'yes' == $this->testmode ) {
				$borgun_adr = self::BORGUN_ENDPOINT_SANDBOX;
			} else {
				$borgun_adr = self::BORGUN_ENDPOINT_LIVE;
			}
			$borgun_args       = $this->get_borgun_args( $order );
			$borgun_args_array = array();
			foreach ( $borgun_args as $key => $value ) {
				$borgun_args_array[] = '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '" />';
			}
			wc_enqueue_js( '
                $.blockUI({
                    message: "Thank you for your order. We are now redirecting you to Borgun to make payment.",
                    baseZ: 99999,
                    overlayCSS: { background: "#fff", opacity: 0.6 },
                    css: {
                        padding:        "20px",
                        zindex:         "9999999",
                        textAlign:      "center",
                        color:          "#555",
                        border:         "3px solid #aaa",
                        backgroundColor:"#fff",
                        cursor:         "wait",
                        lineHeight:     "24px",
                    }
                });

                jQuery("#borgun_payment_form").submit();
            ' );
			$html_form = '<form action="' . esc_url( $borgun_adr ) . '" method="post" id="borgun_payment_form">'
			             . implode( '', $borgun_args_array )
			             . '<input type="submit" class="button" id="wc_submit_borgun_payment_form" value="' . __( 'Pay via Borgun', 'tech' ) . '" /> <a class="button cancel" href="' . $order->get_cancel_order_url() . '">' . __( 'Cancel order &amp; restore cart', 'tech' ) . '</a>'
			             . '</form>';

			return $html_form;
		}

		function process_payment( $order_id ) {
			if ( function_exists( 'wc_get_order' ) ) {
				$order = wc_get_order( $order_id );
			} else {
				$order = new WC_Order( $order_id );
			}

			return array(
				'result'   => 'success',
				'redirect' => $order->get_checkout_payment_url( true )
			);


		}

		function check_borgun_response() {
			global $woocommerce;
			$posted = ! empty( $_POST ) ? $_POST : false;
			if ( $posted && $posted['status'] == 'OK' ) {
				if ( ! empty( $posted['orderid'] ) ) {
					$order_id = (int) str_replace( 'WC-', '', $posted['orderid'] );
					if ( function_exists( 'wc_get_order' ) ) {
						$order = wc_get_order( $order_id );
					} else {
						$order = new WC_Order( $order_id );
					}
					if ( ! $order->is_paid() ) {
						$hash = $this->check_order_hash( $order );
						if ( $hash == $posted['orderhash'] ) {
							$order->add_order_note( 'Borgun payment completed' );
							$order->payment_complete();
							$woocommerce->cart->empty_cart();
							if ( 'yes' == $this->testmode ) {
								$borgun_adr = self::BORGUN_ENDPOINT_SANDBOX;
							} else {
								$borgun_adr = self::BORGUN_ENDPOINT_LIVE;
							}
							if ( strpos( $posted['step'], 'Payment' ) !== false ) {
								$xml = '<PaymentNotification>Accepted</PaymentNotification>';
								wp_remote_post(
									$borgun_adr,
									array(
										'method'      => 'POST',
										'timeout'     => 45,
										'redirection' => 5,
										'httpversion' => '1.0',
										'headers'     => array( 'Content-Type' => 'text/xml' ),
										'body'        => array( 'postdata' => $xml, 'postfield' => 'value' ),
										'sslverify'   => false
									)
								);
							}
						} else {
							$order->add_order_note( 'Order hash doesn\'t match' );
						}
					}
				}
			}
		}

		function receipt_page( $order ) {
			echo '<p>Thank you - your order is now pending payment. We are now redirecting you to Borgun to make payment.</p>';
			echo $this->generate_borgun_form( $order );
		}

		/**
		 * Round prices.
		 * @param  double $price
		 * @param  WC_Order $order
		 * @return double
		 */
		protected function round( $price, $order ) {
			$precision = 2;

			if ( ! $this->currency_has_decimals( $order->get_currency() ) ) {
				$precision = 0;
			}

			return round( $price, $precision );
		}

		/**
		 * Check if currency has decimals.
		 * @param  string $currency
		 * @return bool
		 */
		protected function currency_has_decimals( $currency ) {
			if ( in_array( $currency, array( 'HUF', 'JPY', 'TWD', 'ISK' ) ) ) {
				return false;
			}

			return true;
		}

		/**
		 * Check tax display.
		 * @return bool
		 */
		protected function tax_display() {
			$tax_display = wc_tax_enabled() ? get_option( 'woocommerce_tax_display_cart' ) : 'incl';
			return ( $tax_display == 'incl' ) ? true : false ;
		}

		/**
		 * Check fee tax display.
		 * @param  WC_Order_Item_Fee $item
		 * @return bool
		 */
		protected function fee_tax_display( $item ) {
			$tax_display = $item->get_tax_status();
			return ( $tax_display == 'taxable' ) ? true : false ;
		}
	}
}
