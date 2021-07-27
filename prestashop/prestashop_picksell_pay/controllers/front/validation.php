<?php

use Symfony\Component\HttpClient\HttpClient;


class Prestashop_picksell_payValidationModuleFrontController extends ModuleFrontController
{

    const SDK_URL_DEV = 'https://sdk.psd2.club/transactions';
    const SDK_URL_PROD = 'https://sdk.picksell.eu/transactions';

    /**
     * @see FrontController::postProcess()
     */
    public function postProcess()
    {
        if (!__PS_BASE_URI__) {
            die($this->module->l('invalid base url', 'validation'));
        }
        $cart = $this->context->cart;
        if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0 || !$this->module->active) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        // Check that this payment option is still available in case the customer changed his address just before the end of the checkout process
        $authorized = false;
        foreach (Module::getPaymentModules() as $module) {
            if ($module['name'] == 'prestashop_picksell_pay') {
                $authorized = true;
                break;
            }
        }

        if (!$authorized) {
            die($this->module->l('This payment method is not available.', 'validation'));
        }

        $this->context->smarty->assign([
            'params' => $_REQUEST,
        ]);

        $customer = new Customer($cart->id_customer);
        if (!Validate::isLoadedObject($customer))
            Tools::redirect('index.php?controller=order&step=1');

        $isAvailableCurrency = Currency::getIsoCodeById($cart->id_currency) == 'EUR';
        if (!$isAvailableCurrency) {
            die($this->module->l('This currency is not available for Picksell Pay.', 'validation'));
        }
        $total = (float)$cart->getOrderTotal(true, Cart::BOTH);

        $client = HttpClient::create(['verify_peer' => false, 'verify_host' => false]);
        $host = Configuration::get('PRESTASHOP_PICKSELL_PAY_DEV_MODE') === '1' ? self::SDK_URL_DEV : self::SDK_URL_PROD;
        $merchantId = Configuration::get('PRESTASHOP_PICKSELL_PAY_MERCHANT_ID');
        $apiKey = Configuration::get('PRESTASHOP_PICKSELL_PAY_API_KEY');

        $response = $client->request('POST', $host, [
            'json' => [
                'totalAmount' => (string)$cart->getOrderTotal(true, Cart::BOTH),
                'currency' => Currency::getIsoCodeById($cart->id_currency),
                'description' => 'New PrestaShop Order cart id ' . $cart->id,
                'returnUrl' => Tools::getHttpHost(true).__PS_BASE_URI__.'index.php?controller=order-confirmation&id_cart='.$cart->id.'&id_module='.$this->module->id.'&id_order='.$this->module->currentOrder.'&key='.$customer->secure_key,
            ],
            'auth_basic' => $merchantId.':'.$apiKey
        ]);
        PrestaShopLogger::addLog($response->getContent(false));
        $statusCode = $response->getStatusCode();
        if ($statusCode != 201) {
            PrestaShopLogger::addLog($statusCode);
            throw new PrestaShopPaymentException('invalid status code');
        }
        $responseArray = $response->toArray();
        $this->module->validateOrder(
            $cart->id,
            Configuration::get('PS_OS_PREPARATION'),
            $total,
            $this->module->displayName,
            NULL,
            ['transaction_id' => $responseArray['payload']['id']],
            (int)$this->context->currency,
            false,
            $customer->secure_key
        );
        Tools::redirect($responseArray['payload']['paymentUrl']);
    }
}
