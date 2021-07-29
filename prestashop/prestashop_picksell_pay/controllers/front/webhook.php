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

        $order = $this->get_order_by_picksell_id($input['transactionId']);
        if (!$order) {
            http_response_code(404);
            die('order not found');
        }

        if (!$this->check_total_amount($order, $input['totalAmount'])) {
            http_response_code(400);
            die('invalid order amount');
        }

        switch ($input['status']) {
            case 'PAYMENT_SUCCESS':
                $order->setCurrentState(Configuration::get('PS_OS_PAYMENT'));
                break;
            case 'PAYMENT_FAILED':
                $order->setCurrentState(Configuration::get('PS_OS_ERROR'));
                break;
            default:
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

        if (empty($request_headers['PICKSELL-SIGNATURE']) || empty($request_headers['PICKSELL-TIMESTAMP'])) {
            return false;
        }

        $timestamp = intval($request_headers['PICKSELL-TIMESTAMP']);
        if (abs($timestamp - time()) < 5 * 60 /* 5 minutes */) {
            return false;
        }

        $signed_payload = $timestamp . '.' . $raw_request_body;
        $expected_signature = hash_hmac('sha256', $signed_payload, Configuration::get('PRESTASHOP_PICKSELL_PAY_API_SECRET'));
        if (hash_equals($request_headers['PICKSELL-SIGNATURE'], $expected_signature)) {
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

    private function check_total_amount($order, $request_total_amount) {
    if ((string)$order->getTotalPaid() === $request_total_amount) {
        return true;
    }

    return false;
}
}