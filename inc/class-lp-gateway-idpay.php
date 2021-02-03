<?php
/**
 * payro24 payment gateway class.
 *
 * @author   payro24
 * @package  LearnPress/payro24/Classes
 * @version  1.0.1
 */

// Prevent loading this file directly
defined('ABSPATH') || exit;

if (!class_exists('LP_Gateway_payro24')) {
  /**
   * Class LP_Gateway_payro24
   */
  class LP_Gateway_payro24 extends LP_Gateway_Abstract
  {
    /**
     * @var array
     */
    private $form_data = array();

    /**
     * @var
     */
    private $link;

    /**
     * @var string
     */
    protected $payment_endpoint;

    /**
     * @var string
     */
    protected $verify_endpoint;

    /**
     * @var array|bool|mixed|null
     */
    private $api_key = null;

    /**
     * @var array|bool|mixed|null
     */
    private $sandbox = null;

    /**
     * @var array|null
     */
    protected $settings = null;

    /**
     * @var null
     */
    protected $order = null;

    /**
     * @var null
     */
    protected $posted = null;


    /**
     * LP_Gateway_payro24 constructor.
     */
    public function __construct()
    {
      $this->payment_endpoint = 'https://api.payro24.ir/v1.1/payment';
      $this->verify_endpoint = 'https://api.payro24.ir/v1.1/payment/verify';

      $this->id = 'payro24';
      $this->method_title = __('payro24', 'learnpress-payro24');;
      $this->method_description = __('Make a payment with payro24.', 'learnpress-payro24');
      $this->icon = '';

      // Get settings
      $this->title = LP()->settings->get("{$this->id}.title", $this->method_title);
      $this->description = LP()->settings->get("{$this->id}.description", $this->method_description);
      $this->api_key = LP()->settings->get("{$this->id}.api_key");
      $this->sandbox = LP()->settings->get("{$this->id}.sandbox");

      $settings = LP()->settings;
      // Add default values for fresh installs
      if (!$settings->get("{$this->id}.enable")) {
        $this->settings = array();
        $this->settings['api_key'] = $settings->get("{$this->id}.api_key");
        $this->settings['sandbox'] = $settings->get("{$this->id}.sandbox");
      }

      if (did_action('learn_press/payro24-add-on/loaded')) {
        return;
      }

      // check payment gateway enable
      add_filter('learn-press/payment-gateway/' . $this->id . '/available', array(
        $this,
        'payro24_available'
      ), 10, 2);

      do_action('learn_press/payro24-add-on/loaded');

      parent::__construct();

      // web hook
      if (did_action('init')) {
        $this->register_web_hook();
      } else {
        add_action('init', array($this, 'register_web_hook'));
      }

      add_action('learn_press_web_hooks_processed', array($this, 'web_hook_process_payro24'));
      add_action("learn-press/before-checkout-order-review", array($this, 'error_message'));
    }

    /**
     * Register web hook.
     *
     * @return array
     */
    public function register_web_hook()
    {
      learn_press_register_web_hook('payro24', 'learn_press_payro24');
    }

    /**
     * Admin payment settings.
     *
     * @return array
     */
    public function get_settings()
    {
      return apply_filters('learn-press/gateway-payment/payro24/settings',
        array(
          array(
            'title' => __('Enable', 'learnpress-payro24'),
            'id' => '[enable]',
            'default' => 'no',
            'type' => 'yes-no'
          ),
          array(
            'title' => __('sandbox', 'learnpress-payro24'),
            'id' => '[sandbox]',
            'default' => 'yes',
            'type' => 'yes-no'
          ),
          array(
            'type' => 'textarea',
            'title' => __('Description', 'learnpress-payro24'),
            'default' => __('Pay with payro24', 'learnpress-payro24'),
            'id' => '[description]',
            'editor' => array(
              'textarea_rows' => 5
            ),
            'css' => 'height: 100px;',
            'visibility' => array(
              'state' => 'show',
              'conditional' => array(
                array(
                  'field' => '[enable]',
                  'compare' => '=',
                  'value' => 'yes'
                )
              )
            )
          ),
          array(
            'title' => __('api_key', 'learnpress-payro24'),
            'id' => '[api_key]',
            'type' => 'text',
            'visibility' => array(
              'state' => 'show',
              'conditional' => array(
                array(
                  'field' => '[enable]',
                  'compare' => '=',
                  'value' => 'yes'
                )
              )
            )
          )
        )
      );
    }

    /**
     * Payment form.
     */
    public function get_payment_form()
    {
      ob_start();
      $template = learn_press_locate_template('form.php', learn_press_template_path() . '/addons/payro24-payment/', LP_ADDON_payro24_PAYMENT_TEMPLATE);
      include $template;

      return ob_get_clean();
    }

      /**
       * Error message
       */
    public function error_message()
    {
      if (isset($_SESSION['payro24_error']) && intval($_SESSION['payro24_error']) === 1) {
        $_SESSION['payro24_error'] = 0;
        $template = learn_press_locate_template('payment-error.php', learn_press_template_path() . '/addons/payro24-payment/', LP_ADDON_payro24_PAYMENT_TEMPLATE);
        include $template;
      }
    }

    /**
     * @return mixed
     */
    public function get_icon()
    {
      if (empty($this->icon)) {
        $this->icon = LP_ADDON_payro24_PAYMENT_URL . 'assets/images/payro24.png';
      }

      return parent::get_icon();
    }

    /**
     * Check gateway available.
     *
     * @return bool
     */
    public function payro24_available()
    {
      if (LP()->settings->get("{$this->id}.enable") != 'yes') {
        return false;
      }

      return true;
    }

    /**
     * Get form data.
     *
     * @return array
     */
    public function get_form_data()
    {
      if ($this->order) {
        $user = learn_press_get_current_user();
        $currency_code = learn_press_get_currency();
        if ($currency_code == 'IRR') {
          $amount = $this->order->order_total / 10;
        } else {
          $amount = $this->order->order_total;
        }

        $this->form_data = array(
          'amount' => $amount,
          'currency' => strtolower(learn_press_get_currency()),
          'token' => $this->token,
          'description' => sprintf(__("Charge for %s", "learnpress-payro24"), $user->get_data('email')),
          'customer' => array(
            'name' => $user->get_data('display_name'),
            'billing_email' => $user->get_data('email'),
          ),
          'errors' => isset($this->posted['form_errors']) ? $this->posted['form_errors'] : ''
        );
      }

      return $this->form_data;
    }

    /**
     * Validate form fields.
     *
     * @return bool
     * @throws Exception
     * @throws string
     */
    public function validate_fields()
    {
      $posted = learn_press_get_request('learn-press-payro24');
      $email = !empty($posted['email']) ? $posted['email'] : "";
      $mobile = !empty($posted['mobile']) ? $posted['mobile'] : "";

      $error_message = array();
      if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message[] = __('Invalid email format.', 'learnpress-payro24');
      }
      if (!empty($mobile) && !preg_match("/^(09)(\d{9})$/", $mobile)) {
        $error_message[] = __('Invalid mobile format.', 'learnpress-payro24');
      }

      if ($error = sizeof($error_message)) {
        throw new Exception(sprintf('<div>%s</div>', join('</div><div>', $error_message)), 8000);
      }
      $this->posted = $posted;

      return $error ? false : true;
    }


    /**
     * payro24 payment process.
     *
     * @param $order
     *
     * @return array
     * @throws string
     */
    public function process_payment($order)
    {
      $this->order = learn_press_get_order($order);
      $payro24 = $this->get_checkout_payment_url();
      $gateway_url = $this->link;

      $json = array(
        'result' => $payro24 ? 'success' : 'fail',
        'redirect' => $payro24 ? $gateway_url : ''
      );

      return $json;
    }

    /**
     * @return bool
     */
    public function get_checkout_payment_url()
    {
      if ($this->get_form_data()) {

        $order = $this->order;

        $customer_name = $order->get_customer_name();
        $callback = get_site_url() . '/?' . learn_press_get_web_hook('payro24') . '=1&order_id=' . $order->get_id();

        $currency_code = learn_press_get_currency();
        if ($currency_code == 'IRR') {
          $amount = $order->order_total;
        } else {
          $note=__("Currency is not supported", 'learnpress-payro24');
          $payment_error = $note;
          $payment_error .= "\n";
          $payment_error .= get_post_meta($order->id, 'payro24_payment_error', TRUE);
          update_post_meta($order->id, __('payro24_payment_error','learnpress-payro24'), $payment_error);
          learn_press_add_message($note, 'error');

          return false;
        }

        //Set params and headers
        $data = array(
          'order_id' => $order->get_id(),
          'amount' => $amount,
          'name' => $customer_name,
          'phone' => (!empty($this->posted['mobile'])) ? $this->posted['mobile'] : "",
          'mail' => (!empty($this->posted['email'])) ? $this->posted['email'] : "",
          'desc' => 'توضیحات پرداخت کننده',
          'callback' => $callback,
          'reseller' => null,
        );

        $headers = array(
          'Content-Type' => 'application/json',
          'P-TOKEN' => $this->api_key,
          'P-SANDBOX' => $this->sandbox,
        );

        $args = array(
          'body' => json_encode($data),
          'headers' => $headers,
          'timeout' => 15,
        );

        $response = $this->call_gateway_endpoint($this->payment_endpoint, $args);
        //Check error
        if (is_wp_error($response)) {
            $payment_error = $this->other_status_messages();
            $payment_error .= "\n";
            $payment_error .= get_post_meta($order->id, 'payro24_payment_error', TRUE);
            update_post_meta($order->id, __('payro24_payment_error','learnpress-payro24'), $payment_error);
            learn_press_add_message($response->get_error_message(), 'error');

          return false;
        }
        $http_status = wp_remote_retrieve_response_code($response);
        $result = wp_remote_retrieve_body($response);
        $result = json_decode($result);

        //Check http error
        if ($http_status != 201 || empty($result) || empty($result->id) || empty($result->link)) {
          $note = '';
          $note .= __('An error occurred while creating the transaction.', 'learnpress-payro24');
          $note .= '<br/>';
          $note .= sprintf(__('error status: %s', 'learnpress-payro24'), $http_status);

          if (!empty($result->error_code) && !empty($result->error_message)) {
            $note .= '<br/>';
            $note .= sprintf(__('error code: %s', 'learnpress-payro24'), $result->error_code);
            $note .= '<br/>';
            $note .= sprintf(__('error message: %s', 'learnpress-payro24'), $result->error_message);
            $payment_error = $result->error_message;
            $payment_error .= "\n";
            $payment_error .= get_post_meta($order->id, 'payro24_payment_error', TRUE);
            update_post_meta($order->id, __('payro24_payment_error','learnpress-payro24'), $payment_error);
            learn_press_add_message($note, 'error');
          }

          return false;
        }
        // Save ID of this transaction
        update_post_meta($order->id, __('payro24_transaction_id','learnpress-payro24'), $result->id);

        // Set remote status of the transaction to 1 as it's primary value.
        update_post_meta($order->id, __('payro24_transaction_status','learnpress-payro24'), 1);

        $note = sprintf(__('transaction id: %s', 'learnpress-payro24'), $result->id);
        $this->link = $result->link;
        return true;

      }

      return false;
    }

    /**
     * Handle a web hook
     *CallBack method
     */
    public function web_hook_process_payro24()
    {
      // Check method post or get
      $method = $_SERVER['REQUEST_METHOD'];
      if ($method == 'POST') {
        $status = sanitize_text_field($_POST['status']);
        $track_id = sanitize_text_field($_POST['track_id']);
        $id = sanitize_text_field($_POST['id']);
        $order_id = sanitize_text_field($_POST['order_id']);
      }
      elseif ($method == 'GET') {
        $status = sanitize_text_field($_GET['status']);
        $track_id = sanitize_text_field($_GET['track_id']);
        $id = sanitize_text_field($_GET['id']);
        $order_id = sanitize_text_field($_GET['order_id']);
      }

      //Check id or order_id is empty
      if (empty($id) || empty($order_id)) {
        learn_press_add_message($this->other_status_messages(), 'error');
        wp_redirect(esc_url(learn_press_get_page_link('checkout')));

        exit();
      }

      $order = LP_Order::instance($order_id);
      //Check order is empty
      if (empty($order)) {
        learn_press_add_message($this->other_status_messages(), 'error');
        wp_redirect(esc_url(learn_press_get_page_link('checkout')));

        exit();
      }

      //Check order status
      if ($order->has_status('completed')) {
        learn_press_add_message($this->other_status_messages(), 'error');
        wp_redirect(esc_url(learn_press_get_page_link('checkout')));

        exit();
      }

      //Failed transaction
      if ($status != 10) {
        $order = LP_Order::instance($order_id);
        $massage = $this->other_status_messages($status);
        $payment_error = $massage;
        $payment_error .= "\n";
        $payment_error .= get_post_meta($order_id, 'payro24_payment_error', TRUE);
        update_post_meta($order_id, __('payro24_payment_error','learnpress-payro24'), $payment_error);
        update_post_meta($order_id, 'payro24_transaction_status', $status);
        $order->update_status('failed');
        learn_press_add_message($massage, 'error');
        wp_redirect(esc_url(learn_press_get_page_link('checkout')));

        exit();
      }

      //Set params and headers
      $params = array(
        'id' => $id,
        'order_id' => $order->id,
      );

      $headers = array(
        'Content-Type' => 'application/json',
        'P-TOKEN' => $this->api_key,
        'P-SANDBOX' => $this->sandbox,
      );

      $args = array(
        'body' => json_encode($params),
        'headers' => $headers,
        'timeout' => 15,
      );

      $response = $this->call_gateway_endpoint($this->verify_endpoint, $args);
      //Check Error
      if (is_wp_error($response)) {
        $payment_error = $this->other_status_messages();
        $payment_error .= "\n";
        $payment_error .= get_post_meta($order->id, 'payro24_payment_error', TRUE);
        update_post_meta($order->id, __('payro24_payment_error'.'learnpress-payro24'), $payment_error);
        learn_press_add_message($response->get_error_message(), 'error');
        wp_redirect(esc_url(learn_press_get_page_link('checkout')));

        exit();
      }

      $http_status = wp_remote_retrieve_response_code($response);
      $result = wp_remote_retrieve_body($response);
      $result = json_decode($result);

      //Check http Error
      if ($http_status != 200) {
        $note = '';
        $note .= __('An error occurred while verifying the transaction.', 'learnpress-payro24');
        $note .= '<br/>';
        $note .= sprintf(__('error status: %s', 'learnpress-payro24'), $http_status);

        if (!empty($result->error_code) && !empty($result->error_message)) {
          $note = '';
          $note .= __('An error occurred while creating the transaction.', 'learnpress-payro24');
          $note .= '<br/>';
          $note .= sprintf(__('error status: %s', 'learnpress-payro24'), $http_status);
          $note .= '<br/>';
          $note .= sprintf(__('error message: %s', 'learnpress-payro24'), $result->error_message);
          $payment_error=$result->error_message;
          learn_press_add_message($note, 'error');
        }

        $payment_error .= "\n";
        $payment_error .= get_post_meta($order_id, 'payro24_payment_error', TRUE);
        update_post_meta($order_id, __('payro24_payment_error','learnpress-payro24'), $payment_error);
        learn_press_add_message($note, 'error');
        $order->update_status('failed');
        wp_redirect(esc_url(learn_press_get_page_link('checkout')));

        exit();
      } else {

        //Check Double Spending
        if ($this->double_spending_occurred($order->id, $id)) {
          $note = $this->other_status_messages(0);
          $payment_error = $note;;
          $payment_error .= "\n";
          $payment_error .= get_post_meta($order_id, 'payro24_payment_error', TRUE);
          update_post_meta($order_id, __('payro24_payment_error','learnpress-payro24'), $payment_error);
          $order->update_status('failed');
          learn_press_add_message($note, 'error');
          wp_redirect(esc_url(learn_press_get_page_link('checkout')));

          exit();
        }

        $order_id = $order->id;
        $verify_status = empty($result->status) ? NULL : $result->status;
        $verify_track_id = empty($result->track_id) ? NULL : $result->track_id;
        $verify_id = empty($result->id) ? NULL : $result->id;
        $verify_order_id = empty($result->order_id) ? NULL : $result->order_id;
        $verify_amount = empty($result->amount) ? NULL : $result->amount;
        $verify_card_no = empty($result->payment->card_no) ? NULL : $result->payment->card_no;
        $verify_hashed_card_no = empty($result->payment->hashed_card_no) ? NULL : $result->payment->hashed_card_no;
        $verify_date = empty($result->payment->date) ? NULL : $result->payment->date;

        // Updates order's meta data after verifying the payment.
        update_post_meta($order_id, __('payro24_transaction_status', 'learnpress-payro24'), $verify_status);
        update_post_meta($order_id, __('payro24_track_id','learnpress-payro24'), $verify_track_id);
        update_post_meta($order_id, __('payro24_transaction_id','learnpress-payro24'), $verify_id);
        update_post_meta($order_id, __('payro24_transaction_order_id','learnpress-payro24'), $verify_order_id);
        update_post_meta($order_id, __('payro24_transaction_amount','learnpress-payro24'), $verify_amount);
        update_post_meta($order_id, __('payro24_payment_card_no','learnpress-payro24'), $verify_card_no);
        update_post_meta($order_id, __('payro24_payment_hashed_card_no','learnpress-payro24'), $verify_hashed_card_no);
        update_post_meta($order_id, __('payro24_payment_date','learnpress-payro24'), $verify_date);
        $order->payment_complete($verify_track_id);
        wp_redirect(esc_url($this->get_return_url($order)));

        exit();
      }

    }

    /**
     * @param $order_id
     * @param $remote_id
     * @return bool
     */
    private function double_spending_occurred($order_id, $remote_id)
    {
      if (get_post_meta($order_id, 'payro24_transaction_id', TRUE) != $remote_id) {
        return TRUE;
      }

      return FALSE;
    }

    /**
     * Calls the gateway endpoints.
     *
     * Tries to get response from the gateway for 4 times.
     *
     * @param $url
     * @param $args
     *
     * @return array|\WP_Error
     */
    private function call_gateway_endpoint($url, $args)
    {
      $number_of_connection_tries = 4;
      while ($number_of_connection_tries) {
        $response = wp_safe_remote_post($url, $args);
        if (is_wp_error($response)) {
          $number_of_connection_tries--;
          continue;
        } else {
          break;
        }
      }

      return $response;
    }

    /**
     * @param null $status
     * @return string
     */
    public function other_status_messages($status = null)
    {
      switch ($status) {
        case "1":
          $msg = __("Payment has not been made. code:", 'learnpress-payro24');
          break;
        case "2":
          $msg = __("Payment has failed. code:", 'learnpress-payro24');
          break;
        case "3":
          $msg = __("An error has occurred. code:", 'learnpress-payro24');
          break;
        case "4":
          $msg = __("Blocked. code:", 'learnpress-payro24');
          break;
        case "5":
          $msg = __("Return to payer. code:", 'learnpress-payro24');
          break;
        case "6":
          $msg = __("Systematic return. code:", 'learnpress-payro24');
          break;
        case "7":
          $msg = __("Cancel payment. code:", 'learnpress-payro24');
          break;
        case "8":
          $msg = __("It was transferred to the payment gateway. code:", 'learnpress-payro24');
          break;
        case "10":
          $msg = __("Waiting for payment confirmation. code:", 'learnpress-payro24');
          break;
        case "100":
          $msg = __("Payment has been confirmed. code:", 'learnpress-payro24');
          break;
        case "101":
          $msg = __("Payment has already been confirmed. code:", 'learnpress-payro24');
          break;
        case "200":
          $msg = __("Deposited to the recipient. code:", 'learnpress-payro24');
          break;
        case "0":
          $msg = __("Abuse of previous transactions. code:", 'learnpress-payro24');
          break;
        case null:
          $msg = __("Unexpected error. code:", 'learnpress-payro24');
          $status = '1000';
          break;
      }
      $msg = sprintf("$msg %s", $status);

      return $msg;
    }

  }
}