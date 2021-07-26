<?php
/*
* 2007-2015 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author PrestaShop SA <contact@prestashop.com>
*  @copyright  2007-2015 PrestaShop SA
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

/**
 * @since 1.5.0
 */

use Symfony\Component\HttpClient\HttpClient;

class Prestashop_picksell_payValidationModuleFrontController extends ModuleFrontController
{
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
        $response = $client->request('POST', 'https://sdk.psd2.club/transactions', [
            'json' => [
                'totalAmount' => (string)$cart->getOrderTotal(true, Cart::BOTH),
                'currency' => Currency::getIsoCodeById($cart->id_currency),
                'description' => 'New PrestaShop Order cart id ' . $cart->id,
                'returnUrl' => Tools::getHttpHost(true).__PS_BASE_URI__.'index.php?controller=order-confirmation&id_cart='.$cart->id.'&id_module='.$this->module->id.'&id_order='.$this->module->currentOrder.'&key='.$customer->secure_key,
            ],
            'auth_basic' => '247:Q~-dSdP0S6l9itWClUGw13V7g23H8MADCjRdGfIq'
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
