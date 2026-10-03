<?php

// Exit if the file is accessed directly
if (!defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path(__FILE__) . 'lib/class-twittpay-client.php';

if (!class_exists('WPJobster_TwittPay_Loader')) {

    class WPJobster_TwittPay_Loader extends \stdClass
    {
        private $client;

        public function __construct($gateway = 'twittpay')
        {
            $this->unique_id = 'twittpay';

            add_filter('wpj_payment_gateways_filter', function ($payment_gateways_list) {
                $payment_gateways_list[$this->unique_id] = __('TwittPay', 'wpjobster');
                return $payment_gateways_list;
            }, 10, 1);

            add_filter('wpj_admin_settings_items_filter', function ($menu) {
                $menu['payment-gateways']['childs'][$this->unique_id] = ['order' => '02a', 'path' => get_template_directory() . '/lib/plugins/wpjobster-twittpay/admin-fields.php'];
                return $menu;
            }, 10, 1);

            add_action('wpjobster_taketo_' . $this->unique_id . '_gateway', [$this, 'initializePayment'], 10, 2);
            add_action('wpjobster_processafter_' . $this->unique_id . '_gateway', [$this, 'processPayment'], 10, 2);

            add_filter('wpjobster_take_allowed_currency_' . $this->unique_id, [$this, 'setGatewayCurrencies']);

            $this->client = new TwittPay_Client(wpj_get_option('wpjobster_twittpay_api_key'));
        }

        public static function init()
        {
            $class = __CLASS__;
            new $class;
        }

        public function initializePayment($payment_type, $order_details)
        {
            $user = wp_get_current_user();

            $payment_row = wpj_get_payment(['payment_type_id' => $order_details['id'], 'payment_type' => $payment_type]);

            $callback_url = get_bloginfo('url') . '/?payment_response=' . $this->unique_id . '&payment_id=' . $payment_row->id;

            $requestData = [
                'cus_name'    => $user->user_login,
                'cus_email'   => $user->user_email,
                'amount'      => number_format((float) $payment_row->final_amount_exchanged, 2, '.', ''),
                'metadata'    => [
                    'payment_id' => (string) $payment_row->id,
                ],
                'success_url' => $callback_url . '&action=paid',
                'cancel_url'  => $callback_url . '&action=cancelled',
                'webhook_url' => $callback_url . '&action=ipn',
            ];

            try {
                $paymentUrl = $this->client->createPayment($requestData);
                wp_redirect($paymentUrl);
                exit();
            } catch (\Exception $e) {
                die('Payment could not be started: ' . esc_html($e->getMessage()));
            }
        }

        public function processPayment($payment_type, $payment_type_class)
        {
            if (!isset($_REQUEST['payment_id'])) {
                return;
            }

            $payment_id  = (int) $_REQUEST['payment_id'];
            $payment_row = wpj_get_payment(['id' => $payment_id]);

            if (!$payment_row) {
                return;
            }

            $order_id     = $payment_row->payment_type_id;
            $payment_type = $payment_row->payment_type;
            $order        = wpj_get_order_by_payment_type($payment_type, $order_id);
            $action       = WPJ_Form::get('action');

            if ($action == 'cancelled') {
                if ($order->payment_status != 'cancelled') {
                    do_action('wpjobster_' . $payment_type . '_payment_failed', $order_id, $this->unique_id, 'Buyer clicked cancel', '');
                }
                return;
            }

            $transaction_id = $this->readTransactionId();

            if ($transaction_id === '') {
                return;
            }

            try {
                $response = $this->client->verifyPayment($transaction_id);
            } catch (\Exception $e) {
                die('Verification error: ' . esc_html($e->getMessage()));
            }

            $payment_response = wp_json_encode($response);
            $status           = strtoupper(trim((string) (isset($response['status']) ? $response['status'] : '')));

            $meta = isset($response['metadata']) ? $response['metadata'] : [];
            if (is_string($meta)) {
                $meta = json_decode($meta, true);
            }
            $meta = is_array($meta) ? $meta : [];

            // The payment has to belong to this exact payment record and cover its amount.
            $belongs = isset($meta['payment_id']) && (int) $meta['payment_id'] === $payment_id;
            $paid    = (float) (isset($response['amount']) ? $response['amount'] : 0);
            $covers  = $paid + 0.01 >= (float) $payment_row->final_amount_exchanged;

            wpj_save_webhook([
                'webhook_id'       => $transaction_id,
                'payment_id'       => $payment_id,
                'status'           => strtolower($status),
                'type'             => $action . ' ' . $transaction_id,
                'description'      => $payment_type,
                'amount'           => $paid,
                'amount_currency'  => 'BDT',
                'fees'             => isset($response['fee']) ? $response['fee'] : 0,
                'fees_currency'    => 'BDT',
                'create_time'      => current_time('timestamp', 1),
                'payment_response' => $payment_response,
                'payment_type'     => $payment_type,
                'order_id'         => $order_id,
            ]);

            if ($status === 'COMPLETED' && $belongs && $covers) {
                if ($order->payment_status != 'completed') {
                    do_action('wpjobster_' . $payment_type . '_payment_success', $order_id, $this->unique_id, $transaction_id, $payment_response);
                }
            } else {
                do_action('wpjobster_' . $payment_type . '_payment_other', $order_id, $this->unique_id, $transaction_id, $payment_response, strtolower($status));
            }

            if ($action == 'ipn') {
                status_header(200);
                echo 'OK';
                exit;
            }
        }

        /** The transaction id comes in the return link or in the webhook body. */
        private function readTransactionId()
        {
            foreach (['transactionId', 'transaction_id'] as $key) {
                if (!empty($_REQUEST[$key])) {
                    return trim(sanitize_text_field(wp_unslash($_REQUEST[$key])));
                }
            }

            $raw  = file_get_contents('php://input');
            $body = json_decode($raw, true);

            if (is_array($body)) {
                foreach (['transactionId', 'transaction_id'] as $key) {
                    if (!empty($body[$key])) {
                        return trim(sanitize_text_field($body[$key]));
                    }
                }
            }

            return '';
        }

        public function setGatewayCurrencies($currency)
        {
            return 'BDT';
        }
    }
}

add_action('after_setup_theme', ['WPJobster_TwittPay_Loader', 'init']);
