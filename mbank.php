<?php
/*
  Plugin Name: MBank
  Plugin URI:  http://maorif.com
  Description: MBank Plugin for WooCommerce
  Version: 1.0.1
  Author: richman@mail.ru
 */
if ( ! defined( 'ABSPATH' ) ) exit;
add_action('plugins_loaded', 'woocommerce_mbank', 0);
function woocommerce_mbank(){
    load_plugin_textdomain( 'mbank', false, dirname( plugin_basename( __FILE__ ) ) . '/lang/' );
    if (!class_exists('WC_Payment_Gateway'))
        return;
    if(class_exists('WC_MBANK'))
        return;
    class WC_MBANK extends WC_Payment_Gateway{
        public function __construct()
		{
            $plugin_dir = plugin_dir_url(__FILE__);
            global $woocommerce;
            $this->id = 'mbank';
            $this->icon = apply_filters('woocommerce_mbank_icon', ''.$plugin_dir.'mbank.png');
            $this->has_fields = false;
            $this->init_form_fields();
            $this->init_settings();
            $this->public_key = $this->get_option('public_key');
            $this->secret_key = $this->get_option('secret_key');
            $this->title = __('MBank', 'mbank');
            $this->description = __('Payment system MBank', 'mbank');
			$this->type=$this->get_option('type');
            add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
            add_action('woocommerce_api_wc_' . $this->id, array($this, 'callback'));
        }
        public function admin_options()
		{
            ?>
            <h3><?php _e('MBANK', 'mbank'); ?></h3>
            <p><?php _e('Setup payments parameters.', 'mbank'); ?></p>
            <table class="form-table">
                <?php
                $this->generate_settings_html();
                ?>
            </table>
            <?php
        }
        function init_form_fields()
		{
            $this->form_fields = array('enabled' =>
			array('title' => __('Enable/Disable', 'mbank'),
				  'type' => 'checkbox','label' => __('Enabled', 'mbank'),
				  'default' => 'yes'),
				  
                'public_key' => array('title' => __('MERCHANT ID', 'mbank'),
									  'type' => 'text',
									  'description' => __('Copy Merchant ID from your account page in MBank system', 'mbank'),
									  'default' => ''),
				'secret_key' => array('title' => __('KEY', 'woocommerce'),
									  'type' => 'text',
									  'description' => __('Copy KEY from your account page in MBank system', 'mbank'),
									  'default' => ''),
				'type' => array('title' => __('TYPE', 'woocommerce'),
									  'type' => 'text',
									  'description' => __('TYPE', 'mbank'),
									  'default' => ''),
				'title' => array(
										'title'       => __( 'Title', 'woocommerce' ),
										'type'        => 'text',
										'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
										'default'     => __( 'Check payments', 'Check payment method', 'woocommerce' ),
										'desc_tip'    => true,	),
				'description' => array(
										'title'       => __( 'Description', 'woocommerce' ),
										'type'        => 'textarea',
										'description' => __( 'Payment method description that the customer will see on your checkout.', 'woocommerce' ),
										'default'     => __( 'Please send a check to Store Name, Store Street, Store Town, Store State / County, Store Postcode.', 'woocommerce' ),
										'desc_tip'    => true,
			));
        }
        public function generate_form($order_id)
		{
           $order = new WC_Order( $order_id );
            $sum = number_format($order->order_total, 0, '.', '');
            $desc = __('Payment for Order №', 'mbank') . $order_id;
			add_post_meta($order->id, '_order_create_date', $create_time, true);
			$mac= md5($sum.$this->secret_key);
			$redirect="http://itrade.uz/checkout/order-received/".$order_id;
			return
                '<form action="http://check.mbank.uz/" method="POST" id="mbank_form">'.
				'<input type="hidden" name="merchant" value="' . $this->public_key . '" />'.
				'<input type="hidden" name="type" value="' . $this->type . '" />'.
				'<input type="hidden" name="summa" value="' .  $sum . '" />'.
				'<input type="hidden" name="summahash" value="' . $mac . '" />'.
				'<input type="hidden" name="referrer" value="' . $redirect . '"/>'.
				'<input type="hidden" name="number" value="' .  $order_id . '" />'.
				'<input type="hidden" name="other_info" value="" />'.
				'<input type="submit" class="button alt" id="submit_mbank_form" value="'.__('Pay', 'mbank').'" />
				<a class="button cancel" href="'.$order->get_cancel_order_url().'">'.__('Cancel payment and return back to card', 'mbank').'</a>'."\n".
                '</form>';
        }
        function process_payment($order_id)
		{
            $order = new WC_Order($order_id);
            return array(
                'result' => 'success',
                'redirect'	=> add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, get_permalink(woocommerce_get_page_id('pay')))));
        }
        function receipt_page($order)
		{
            echo '<p>'.__('Thank you for your order, press button to pay.', 'mbank').'</p>';
            echo $this->generate_form($order);
        }
        function callback()
		{
			$responsedata = (array)json_decode(file_get_contents('php://input'), true);
				$order = new WC_Order( $order );
				$method = '';
				$params = array();
				$method = $responsedata['action'];
					switch ($method) {
						case '1':
							$result = $this->GetInfo($responsedata);
							break;
						case '2':
							$result = $this->Perform($responsedata);
							break;
						case '3':
							$result = $this->Check($responsedata);
							break;
						default:
							$result = $this->Error_unknown( $responsedata );
							break;
					}
				header('Content-Type: application/json');
				echo json_encode($result);
				die();
        }
        function GetInfo($responsedata)
        {
			try {
				$order = new WC_Order($responsedata['client'] );
				$md5_digest_check=md5('1'.$sum.$this->secret_key);
				$order_id=$order->id;
				if ($md5_digest_check==$responsedata['md5_digest']){
					if ($order_id == $responsedata['client']) {		
						$result = array(
                           						 'status' => 0,
                           						 'error_message'=>array(),
												 'information'=>array()
                        						);
						return $result;							
					}else{
						$result = array(
                           						 'status' => 90,
                           						 'error_message'=>array("ru"=>"Ошибка","en"=>"Error","uz"=>"Xato"),
												 'information'=>array()
                        						);
						return $result;	
					}
				}else{
					$result = array(
                           						 'status' => 20,
                           						 'error_message'=>array("ru"=>"Ошибка","en"=>"Error","uz"=>"Xato"),
												 'information'=>array()
                        						);
						return $result;	
					
				}
			}
          	catch (Exception $ex) {
          		$result = array(
                           						 'status' => 80,
                           						 'error_message'=>array("ru"=>"Ошибка","en"=>"Error","uz"=>"Xato"),
												 'information'=>array()
                        						);
						return $result;	
          	}
        }
 		function Perform( $responsedata )
        {
			try {
				$order = new WC_Order($responsedata['client'] );
				$sum = number_format($order->order_total, 0, '.', '');
				$md5_digest_check=md5('2'.$responsedata['transaction_id'].$responsedata['client'].$responsedata['amount'].$responsedata['req_time'].$this->secret_key);
				$order_id=$order->id;
				if ($md5_digest_check == $responsedata['md5_digest'] && $sum == $responsedata['amount']){
					if ($order_id == $responsedata['client']) {		
						$result = array(
                           						 'status' => 0,
                           						 'message'=>"Нет ошибки. Операция выполнена успешно",
												 'provider_transaction_id'=>$order_id
                        						);
						
						return $result;							
					}else{
						$result = array(
                           						 'status' => 90,
                           						 'error_message'=>array("ru"=>"Ошибка","en"=>"Error","uz"=>"Xato"),
												 'information'=>array()
                        						);
						return $result;	
					}
				}elseif ($md5_digest_check != $responsedata['md5_digest']){
					$result = array(
                           						 'status' => 20,
                           						 'error_message'=>array("ru"=>"Ошибка","en"=>"Error","uz"=>"Xato"),
												 'information'=>array()
                        						);
						return $result;	
					
				}elseif ($sum != $responsedata['amount']){
					$result = array(
                           						 'status' => 10,
                           						 'error_message'=>array("ru"=>"Ошибка","en"=>"Error","uz"=>"Xato"),
												 'information'=>array()
                        						);
						return $result;
				}else{
					$result = array(
                           						 'status' => 10,
                           						 'error_message'=>array("ru"=>"Ошибка","en"=>"Error","uz"=>"Xato"),
												 'information'=>array()
                        						);
						return $result;
				}
			}
          	catch (Exception $ex) {
          		$result = array(
                           						 'status' => 80,
                           						 'error_message'=>array("ru"=>"Ошибка","en"=>"Error","uz"=>"Xato"),
												 'information'=>array()
                        						);
						return $result;	
          	}
        }
		function Check($responsedata)
        {
			try {
				$order = new WC_Order($responsedata['client'] );
				$md5_digest_check=md5('3'.$responsedata['mbank_transaction_id'].$responsedata['provider_transaction_id'].$this->secret_key);
				if ($md5_digest_check == $responsedata['md5_digest']){	
						$result = array(
                           						 'status' => 0,
                           						 'message'=>"Нет ошибки. Операция выполнена успешно"
                        						);
						//$order->payment_complete($order_id);
						//$order->update_status('completed');
						//$order->reduce_order_stock();
						return $result;							
				}else{
					$result = array(
                           						 'status' => 20,
                           						 'error_message'=>array("ru"=>"Ошибка","en"=>"Error","uz"=>"Xato"),
												 'information'=>array()
                        						);
						return $result;	
				}
			}
          	catch (Exception $ex) {
          		$result = array(
                           						 'status' => 80,
                           						 'error_message'=>array("ru"=>"Ошибка","en"=>"Error","uz"=>"Xato"),
												 'information'=>array()
                        						);
						return $result;	
          	}
        }
		function Error_unknown( $responsedata )
        {
			$result = array(
                           						 'status' => 330,
                           						 'error_message'=>array("ru"=>"Ошибка","en"=>"Error","uz"=>"Xato"),
												 'information'=>array()
                        						);
			return $result;						
			
        }
    }
    function add_mbank_gateway($methods)
	{
		$methods[] = 'WC_MBANK';
		return $methods;
    }
    add_filter('woocommerce_payment_gateways', 'add_mbank_gateway');
}
?>