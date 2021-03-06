<?php
/**
 * PayPal Standard Gateway
 *
 * DISCLAIMER
 *
 * Do not edit or add directly to this file if you wish to upgrade Jigoshop to newer
 * versions in the future. If you wish to customise Jigoshop core for your needs,
 * please use our GitHub repository to publish essential changes for consideration.
 *
 * @package             Jigoshop
 * @category            Checkout
 * @author              Jigowatt
 * @copyright           Copyright © 2011-2012 Jigowatt Ltd.
 * @license             http://jigoshop.com/license/commercial-edition
 */

/**
 * Add the gateway to JigoShop
 **/
function add_paypal_gateway( $methods ) {
	$methods[] = 'paypal';
	return $methods;
}
add_filter( 'jigoshop_payment_gateways', 'add_paypal_gateway', 10 );


class paypal extends jigoshop_payment_gateway {
	
	public function __construct() {
		
		parent::__construct();
		
		$this->id			= 'paypal';
		$this->icon 		= jigoshop::assets_url() . '/assets/images/icons/paypal.png';
		$this->has_fields 	= false;
	  	$this->enabled		= Jigoshop_Base::get_options()->get_option('jigoshop_paypal_enabled');
		$this->title 		= Jigoshop_Base::get_options()->get_option('jigoshop_paypal_title');
		$this->email 		= Jigoshop_Base::get_options()->get_option('jigoshop_paypal_email');
		$this->description  = Jigoshop_Base::get_options()->get_option('jigoshop_paypal_description');
		$this->force_payment= Jigoshop_Base::get_options()->get_option('jigoshop_paypal_force_payment');

		$this->liveurl 		= 'https://www.paypal.com/webscr';
		$this->testurl 		= 'https://www.sandbox.paypal.com/webscr';
		$this->testmode		= Jigoshop_Base::get_options()->get_option('jigoshop_paypal_testmode');
		$this->testmail 	= Jigoshop_Base::get_options()->get_option('jigoshop_sandbox_email');

		$this->send_shipping = Jigoshop_Base::get_options()->get_option('jigoshop_paypal_send_shipping');

		add_action( 'init', array(&$this, 'check_ipn_response') );
		add_action('valid-paypal-standard-ipn-request', array(&$this, 'successful_request') );
		add_action( 'jigoshop_settings_scripts', array( &$this, 'admin_scripts' ) );
		add_action('receipt_paypal', array(&$this, 'receipt_page'));
		
	}


	/**
	 * Default Option settings for WordPress Settings API using the Jigoshop_Options class
	 *
	 * These will be installed on the Jigoshop_Options 'Payment Gateways' tab by the parent class 'jigoshop_payment_gateway'
	 *
	 */	
	protected function get_default_options() {
	
		$defaults = array();
		
		// Define the Section name for the Jigoshop_Options
		$defaults[] = array( 'name' => __('PayPal Standard', 'jigoshop'), 'type' => 'title', 'desc' => __('PayPal Standard works by sending the user to <a href="https://www.paypal.com/">PayPal</a> to enter their payment information.', 'jigoshop') );
		
		// List each option in order of appearance with details
		$defaults[] = array(
			'name'		=> __('Enable PayPal Standard','jigoshop'),
			'desc' 		=> '',
			'tip' 		=> '',
			'id' 		=> 'jigoshop_paypal_enabled',
			'std' 		=> 'yes',
			'type' 		=> 'checkbox',
			'choices'	=> array(
				'no'			=> __('No', 'jigoshop'),
				'yes'			=> __('Yes', 'jigoshop')
			)
		);
		
		$defaults[] = array(
			'name'		=> __('Method Title','jigoshop'),
			'desc' 		=> '',
			'tip' 		=> __('This controls the title which the user sees during checkout.','jigoshop'),
			'id' 		=> 'jigoshop_paypal_title',
			'std' 		=> __('PayPal','jigoshop'),
			'type' 		=> 'text'
		);
		
		$defaults[] = array(
			'name'		=> __('Description','jigoshop'),
			'desc' 		=> '',
			'tip' 		=> __('This controls the description which the user sees during checkout.','jigoshop'),
			'id' 		=> 'jigoshop_paypal_description',
			'std' 		=> __("Pay via PayPal; you can pay with your credit card if you don't have a PayPal account", 'jigoshop'),
			'type' 		=> 'longtext'
		);

		$defaults[] = array(
			'name'		=> __('PayPal email address','jigoshop'),
			'desc' 		=> '',
			'tip' 		=> __('Please enter your PayPal email address; this is needed in order to take payment!','jigoshop'),
			'id' 		=> 'jigoshop_paypal_email',
			'std' 		=> '',
			'type' 		=> 'email'
		);

		$defaults[] = array(
			'name'		=> __('Send shipping details to PayPal','jigoshop'),
			'desc' 		=> '',
			'tip' 		=> __('If your checkout page does not ask for shipping details, or if you do not want to send shipping information to PayPal, set this option to no. If you enable this option PayPal may restrict where things can be sent, and will prevent some orders going through for your protection.','jigoshop'),
			'id' 		=> 'jigoshop_paypal_send_shipping',
			'std' 		=> 'no',
			'type' 		=> 'checkbox',
			'choices'	=> array(
				'no'			=> __('No', 'jigoshop'),
				'yes'			=> __('Yes', 'jigoshop')
			)
		);

		$defaults[] = array(
			'name'		=> __('Force payment when free','jigoshop'),
			'desc' 		=> '',
			'tip' 		=> __('If product totals are free and shipping is also free (excluding taxes), this will force 0.01 to allow paypal to process payment. Shop owner is responsible for refunding customer.','jigoshop'),
			'id' 		=> 'jigoshop_paypal_force_payment',
			'std' 		=> 'no',
			'type' 		=> 'checkbox',
			'choices'	=> array(
				'no'			=> __('No', 'jigoshop'),
				'yes'			=> __('Yes', 'jigoshop')
			)
		);

		$defaults[] = array(
			'name'		=> __('Enable PayPal sandbox','jigoshop'),
			'desc' 		=> __('Turn on to enable the PalPal sandbox for testing.  Visit <a href="http://developer.paypal.com/">http://developer.paypal.com/</a> for more information and to register a merchant and customer testing account.','jigoshop'),
			'tip' 		=> '',
			'id' 		=> 'jigoshop_paypal_testmode',
			'std' 		=> 'no',
			'type' 		=> 'checkbox',
			'choices'	=> array(
				'no'			=> __('No', 'jigoshop'),
				'yes'			=> __('Yes', 'jigoshop')
			)
		);

		$defaults[] = array(
			'name'		=> __('Sandbox email address','jigoshop'),
			'desc' 		=> '',
			'tip' 		=> __('Please enter your Sandbox Merchant email address for use as your sandbox storefront if you have enabled the PayPal sandbox.','jigoshop'),
			'id' 		=> 'jigoshop_sandbox_email',
			'std' 		=> '',
			'type' 		=> 'midtext'
		);

		return $defaults;
	}

    
    public function admin_scripts() {
    	?>
		<script type="text/javascript">
			/*<![CDATA[*/
				jQuery(function($) {
					jQuery('input#jigoshop_paypal_testmode').click( function() {;
						if (jQuery(this).is(':checked')) {
							jQuery(this).parent().parent().next('tr').show();
						} else {
							jQuery(this).parent().parent().next('tr').hide();
						}
					});
				});
			/*]]>*/
		</script>
    	<?php
    }
	
	
	/**
	 * There are no payment fields for paypal, but we want to show the description if set.
	 **/
	function payment_fields() {
		if ($this->description) echo wpautop(wptexturize($this->description));
	}

	/**
	 * Generate the paypal button link
	 **/
	public function generate_paypal_form( $order_id ) {

		$order = new jigoshop_order( $order_id );
        
        $subtotal = (float)(Jigoshop_Base::get_options()->get_option('jigoshop_prices_include_tax') == 'yes' ? (float)$order->order_subtotal + (float)$order->order_tax : $order->order_subtotal);
        $shipping_total = (float)(Jigoshop_Base::get_options()->get_option('jigoshop_prices_include_tax') == 'yes' ? (float)$order->order_shipping + (float)$order->order_shipping_tax : $order->order_shipping);

		if ( $this->testmode == 'yes' ):
			$paypal_adr = $this->testurl . '?test_ipn=1&';
		else :
			$paypal_adr = $this->liveurl . '?';
		endif;

		$shipping_name = explode(' ', $order->shipping_method);

		if (in_array($order->billing_country, array('US','CA'))) :
			$order->billing_phone = str_replace(array('(', '-', ' ', ')'), '', $order->billing_phone);
			$phone_args = array(
				'night_phone_a' => substr($order->billing_phone,0,3),
				'night_phone_b' => substr($order->billing_phone,3,3),
				'night_phone_c' => substr($order->billing_phone,6,4),
				'day_phone_a' 	=> substr($order->billing_phone,0,3),
				'day_phone_b' 	=> substr($order->billing_phone,3,3),
				'day_phone_c' 	=> substr($order->billing_phone,6,4)
			);
		else :
			$phone_args = array(
				'night_phone_b' => $order->billing_phone,
				'day_phone_b' 	=> $order->billing_phone
			);
		endif;

		// filter redirect page
		$checkout_redirect = apply_filters( 'jigoshop_get_checkout_redirect_page_id', jigoshop_get_page_id('thanks') );

		$paypal_args = array_merge(
			array(
				'cmd' 					=> '_cart',
				'business' 				=> $this->testmode == 'yes' ? $this->testmail : $this->email,
				'no_note' 				=> 1,
				'currency_code' 		=> Jigoshop_Base::get_options()->get_option('jigoshop_currency'),
				'charset' 				=> 'UTF-8',
				'rm' 					=> 2,
				'upload' 				=> 1,
				'return' 				=> add_query_arg('key', $order->order_key, add_query_arg('order', $order_id, get_permalink( $checkout_redirect ))),
				'cancel_return'			=> $order->get_cancel_order_url(),
				//'cancel_return'			=> home_url(),

				// Order key
				'custom'				=> $order_id,

				// IPN
				'notify_url'			=> trailingslashit(get_bloginfo('wpurl')).'?paypalListener=paypal_standard_IPN',

				// Address info
				'first_name'			=> $order->billing_first_name,
				'last_name'				=> $order->billing_last_name,
				'company'				=> $order->billing_company,
				'address1'				=> $order->billing_address_1,
				'address2'				=> $order->billing_address_2,
				'city'					=> $order->billing_city,
				'state'					=> $order->billing_state,
				'zip'					=> $order->billing_postcode,
				'country'				=> $order->billing_country,
				'email'					=> $order->billing_email,

				// Payment Info
				'invoice' 				=> $order->order_key,
				'amount' 				=> $order->order_total,
				'discount_amount_cart'  => $order->order_discount
			),
			$phone_args
		);

		// only include tax if prices don't include tax
		if (Jigoshop_Base::get_options()->get_option('jigoshop_prices_include_tax') != 'yes') :
			$paypal_args['tax']					= $order->get_total_tax();
			$paypal_args['tax_cart']			= $order->get_total_tax();
		endif;

		if ($this->send_shipping=='yes') :
			$paypal_args['no_shipping'] = 1;
			$paypal_args['address_override'] = 1;
			$paypal_args['address1'] = $order->shipping_address_1;
			$paypal_args['address2'] = $order->shipping_address_2;
			$paypal_args['city'] = $order->shipping_city;
			$paypal_args['state'] = $order->shipping_state;
			$paypal_args['zip'] = $order->shipping_postcode;
			$paypal_args['country'] = $order->shipping_country;
		else :
			$paypal_args['no_shipping'] = 1;
			$paypal_args['address_override'] = 0;
		endif;

		// Cart Contents
		$item_loop = 0;
		if (sizeof($order->items)>0) : foreach ($order->items as $item) :

			if(!empty($item['variation_id'])) {
				$_product = new jigoshop_product_variation($item['variation_id']);
			} else {
				$_product = new jigoshop_product($item['id']);
			}

			if ($_product->exists() && $item['qty']) :

				$item_loop++;

				$title = $_product->get_title();

				//if variation, insert variation details into product title
				if ($_product instanceof jigoshop_product_variation) {
					$variation_details = array();

					foreach ($_product->get_variation_attributes() as $name => $value) {
						$variation_details[] = ucfirst(str_replace('tax_', '', $name)) . ': ' . ucfirst($value);
					}

					if (count($variation_details) > 0) {
						$title .= ' (' . implode(', ', $variation_details) . ')';
					}
				}

				$paypal_args['item_name_'.$item_loop] = $title;
				$paypal_args['quantity_'.$item_loop] = $item['qty'];
				// use product price since we want the base price if it's including tax or if it's not including tax
				$paypal_args['amount_'.$item_loop] = number_format( apply_filters( 'jigoshop_paypal_adjust_item_price' ,$_product->get_price(), $item), 2); //Apparently, Paypal did not like "28.4525" as the amount. Changing that to "28.45" fixed the issue.
			endif;
		endforeach; endif;

		// Shipping Cost
        if (jigoshop_shipping::is_enabled()) :
            $item_loop++;
            $paypal_args['item_name_'.$item_loop] = __('Shipping cost', 'jigoshop');
            $paypal_args['quantity_'.$item_loop] = '1';

            $shipping_tax = (float)($order->order_shipping_tax ? $order->order_shipping_tax : 0);

            $paypal_args['amount_'.$item_loop] = (Jigoshop_Base::get_options()->get_option('jigoshop_prices_include_tax') == 'yes' ? number_format((float)$order->order_shipping + $shipping_tax, 2) : number_format((float)$order->order_shipping, 2));
        endif; 
        
        if ($this->force_payment == 'yes') :

            $sum = 0;
            for ($i = 1; $i < $item_loop; $i++) :
                $sum += $paypal_args['amount_'.$i];
            endfor;
            
            $item_loop++;
            if ($sum == 0 || (isset($order->order_discount) && $sum - $order->order_discount == 0)) :
                $paypal_args['item_name_'.$item_loop] = __('Force payment on free', 'jigoshop');
                $paypal_args['quantity_'.$item_loop] = '1';
                $paypal_args['amount_'.$item_loop] = 0.01; // force payment
            endif;
            
        endif;
        
		$paypal_args_array = array();

		foreach ($paypal_args as $key => $value) {
			$paypal_args_array[] = '<input type="hidden" name="'.esc_attr($key).'" value="'.esc_attr($value).'" />';
		}

		return '<form action="'.$paypal_adr.'" method="post" id="paypal_payment_form">
				' . implode('', $paypal_args_array) . '
				<input type="submit" class="button-alt" id="submit_paypal_payment_form" value="'.__('Pay via PayPal', 'jigoshop').'" /> <a class="button cancel" href="'.esc_url($order->get_cancel_order_url()).'">'.__('Cancel order &amp; restore cart', 'jigoshop').'</a>
				<script type="text/javascript">
					jQuery(function(){
						jQuery("body").block(
							{
								message: "<img src=\"'.jigoshop::assets_url().'/assets/images/ajax-loader.gif\" alt=\"Redirecting...\" />'.__('Thank you for your order. We are now redirecting you to PayPal to make payment.', 'jigoshop').'",
								overlayCSS:
								{
									background: "#fff",
									opacity: 0.6
								},
								css: {
									padding:		20,
									textAlign:	  "center",
									color:		  "#555",
									border:		 "3px solid #aaa",
									backgroundColor:"#fff",
									cursor:		 "wait"
								}
							});
						jQuery("#submit_paypal_payment_form").click();
					});
				</script>
			</form>';

	}

	/**
	 * Process the payment and return the result
	 **/
	function process_payment( $order_id ) {

		$order = new jigoshop_order( $order_id );

		return array(
			'result' 	=> 'success',
			'redirect'	=> add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, get_permalink(jigoshop_get_page_id('pay'))))
		);

	}

	/**
	 * receipt_page
	 **/
	function receipt_page( $order ) {

		echo '<p>'.__('Thank you for your order, please click the button below to pay with PayPal.', 'jigoshop').'</p>';

		echo $this->generate_paypal_form( $order );

	}

	/**
	 * Check PayPal IPN validity
	 **/
	function check_ipn_request_is_valid() {

		 // Add cmd to the post array
		$_POST['cmd'] = '_notify-validate';

		// Send back post vars to paypal
		$params = array( 'body' => $_POST, 'sslverify' => apply_filters('https_local_ssl_verify', false));

		// Get url
	   	if ( $this->testmode == 'yes' ):
			$paypal_adr = $this->testurl;
		else :
			$paypal_adr = $this->liveurl;
		endif;

		// Post back to get a response
		$response = wp_remote_post( $paypal_adr, $params );

		 // Clean
		unset($_POST['cmd']);

		// check to see if the request was valid
		if ( !is_wp_error($response) && $response['response']['code'] >= 200 && $response['response']['code'] < 300 && (strcmp( $response['body'], "VERIFIED") == 0)) {
			return true;
		}

		return false;
	}

	/**
	 * Check for PayPal IPN Response
	 **/
	function check_ipn_response() {

		if (isset($_GET['paypalListener']) && $_GET['paypalListener'] == 'paypal_standard_IPN'):

			$_POST = stripslashes_deep($_POST);

			if (self::check_ipn_request_is_valid()) :

				do_action("valid-paypal-standard-ipn-request", $_POST);

	   		endif;

	   	endif;

	}

	/**
	 * Successful Payment!
	 **/
	function successful_request( $posted ) {

		// Custom holds post ID
		if ( !empty($posted['txn_type']) && !empty($posted['invoice']) ) {

			$accepted_types = array('cart', 'instant', 'express_checkout', 'web_accept', 'masspay', 'send_money');

			if (!in_array(strtolower($posted['txn_type']), $accepted_types)) exit;

			$order = new jigoshop_order( (int) $posted['custom'] );

			if ($order->order_key!==$posted['invoice']) exit;

			// Sandbox fix
			if ($posted['test_ipn']==1 && $posted['payment_status']=='Pending') $posted['payment_status'] = 'completed';


			if ($order->status !== 'completed') :
				// We are here so lets check status and do actions
				switch (strtolower($posted['payment_status'])) :
					case 'completed' :
						// Payment completed
						$order->add_order_note( __('IPN payment completed', 'jigoshop') );
						$order->payment_complete();
					break;
					case 'denied' :
					case 'expired' :
					case 'failed' :
					case 'voided' :
						// Hold order
						$order->update_status('on-hold', sprintf(__('Payment %s via IPN.', 'jigoshop'), strtolower($posted['payment_status']) ) );
					break;
					default:
						// No action
					break;
				endswitch;
			endif;

			exit;

		}

	}
    
    public function process_gateway($subtotal, $shipping_total, $discount = 0) {
        
        $ret_val = false;
        if (!(isset($subtotal) && isset($shipping_total))) return $ret_val;
        
        // check for free (which is the sum of all products and shipping = 0) Tax doesn't count unless prices
        // include tax
        if (($subtotal <= 0 && $shipping_total <= 0) || (($subtotal + $shipping_total) - $discount) == 0) :
            // true when force payment = 'yes'
            $ret_val = ($this->force_payment == 'yes');
        elseif(($subtotal + $shipping_total) - $discount < 0) :
            // don't process paypal if the sum of the product prices and shipping total is less than the discount
            // as it cannot handle this scenario
            $ret_val = false;
        else :
            $ret_val = true;
        endif;
        
        return $ret_val;
        
    }

}
