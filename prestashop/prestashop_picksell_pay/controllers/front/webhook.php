<?php

class Prestashop_picksell_payWebhookModuleFrontController extends ModuleFrontController
{
    /**
     * @see FrontController::postProcess()
     */
    public function postProcess()
    {
        $this->ajax = true;
        $this->json = true;
        $inputJSON = file_get_contents('php://input');
        $input = json_decode($inputJSON, TRUE); //convert JSON into array

        if (!$this->is_valid_request($this->get_request_headers(), $inputJSON)) {
            http_response_code(400);
            die('bad request');
        }

        $transactionId = $input['transaction']['id'];
        $totalAmount = $input['transaction']['totalAmount'];
        $status = $input['transaction']['status'];
        $isStatusSuccessful = $status === 'PAYMENT_SUCCESS';
        $isStatusFailed = $status === 'PAYMENT_FAILED';

        $order = $this->get_order_by_picksell_id($transactionId);
        if (!$order) {
            http_response_code(404);
            die('order not found');
        }

        if (!$this->check_total_amount($order, $totalAmount)) {
            http_response_code(400);
            die('invalid order amount');
        }

        if ($isStatusSuccessful) {
            $order->setCurrentState(Configuration::get('PS_OS_PAYMENT'));
        } else if ($isStatusFailed) {
            $order->setCurrentState(Configuration::get('PS_OS_ERROR'));
        } else {
            http_response_code(400);
            die('invalid order status');
        }

        die('success');
    }

    private function is_valid_request($request_headers, $raw_request_body)
    {
        if (empty($request_headers) || empty($raw_request_body)) {
            return false;
        }

        $sign = empty($request_headers['PICKSELL-SIGNATURE']) ? $request_headers['picksell-signature'] : $request_headers['PICKSELL-SIGNATURE'];
        $ts = empty($request_headers['PICKSELL-TIMESTAMP']) ? $request_headers['picksell-timestamp'] : $request_headers['PICKSELL-TIMESTAMP'];
        if (empty($sign) || empty($ts)) {
            return false;
        }

        $timestamp = intval($ts);
        if (abs($timestamp - time()) < 5 * 60 /* 5 minutes */) {
            return false;
        }

        $signed_payload = $timestamp . '.' . $raw_request_body;
        $expected_signature = hash_hmac('sha256', $signed_payload, Configuration::get('PRESTASHOP_PICKSELL_PAY_API_SECRET'));

        if (hash_equals($sign, $expected_signature)) {
            return true;
        }

        return false;
    }

    private function get_request_headers()
    {
        if (!function_exists('getallheaders')) {
            $headers = array();

            foreach ($_SERVER as $name => $value) {
                if ('HTTP_' === substr($name, 0, 5)) {
                    $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
                }
            }
            return $headers;

        } else {
            return getallheaders();
        }
    }

    private function get_order_by_picksell_id($picksell_order_id)
    {
		    $orderPaymentRef = Db::getInstance()->getRow('SELECT id_order_payment FROM `' . _DB_PREFIX_ . 'order_payment` WHERE transaction_id = \'' . pSQL($picksell_order_id) . '\'');
		    if (!$orderPaymentRef || !isset($orderPaymentRef['id_order_payment'])) {
		      return false;
        }

		    $orderIdRef = Db::getInstance()->getRow('SELECT id_order FROM `' . _DB_PREFIX_ . 'order_invoice_payment` WHERE id_order_payment = ' . (int)$orderPaymentRef['id_order_payment']);;
        if (!$orderIdRef || !isset($orderIdRef['id_order'])) {
            return false;
        }

		    return new Order($orderIdRef['id_order']);
    }

    private function check_total_amount($order, $request_total_amount)
    {
        if (number_format($order->getTotalPaid(), 2, '.', '') === $request_total_amount) {
            return true;
        }

        return false;
    }
}