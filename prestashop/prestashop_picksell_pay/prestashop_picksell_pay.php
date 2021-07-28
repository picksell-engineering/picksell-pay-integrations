<?php

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Prestashop_picksell_pay extends PaymentModule
{
    protected $_html = '';
    protected $_postErrors = array();

    public $details;
    public $owner;
    public $address;
    public $extra_mail_vars;

    public function __construct()
    {
        $this->name = 'prestashop_picksell_pay';
        $this->tab = 'payments_gateways';
        $this->version = '1.0.0';
        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);
        $this->author = 'PrestaShop';
        $this->controllers = array('validation');
        $this->is_eu_compatible = 1;

        $this->currencies = true;
        $this->currencies_mode = 'checkbox';

        $this->bootstrap = true;
        parent::__construct();

        $this->displayName = $this->l('Picksell Pay');
        $this->description = $this->l('A gateway to pay orders via Picksell Pay');

        if (!count(Currency::checkPaymentCurrencies($this->id))) {
            $this->warning = $this->l('No currency has been set for this module.');
        }
    }

    public function install()
    {
        if (
            !parent::install() ||
            !$this->registerHook('paymentOptions') ||
            !$this->registerHook('paymentReturn') ||
            !$this->registerHook('moduleRoutes')
        ) {
            return false;
        }
        return true;
    }

    public function hookModuleRoutes($params)
    {
        $my_routes = array(
            'module-prestashop_picksell_pay-webhook' => array(
                'controller' => 'webhook',
                'rule' => '/confirm',
                'params' => array(
                    'fc' => 'module',
                    'module' => 'prestashop_picksell_pay',
                ),
            )
        );

        return $my_routes;
    }

    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return;
        }

        if (!$this->checkCurrency($params['cart'])) {
            return;
        }

        if (!$this->checkMinimumAmount($params['cart'])) {
            return;
        }

        $payment_options = [
            $this->getExternalPaymentOption($params),
        ];

        return $payment_options;
    }

    public function checkCurrency($cart)
    {
        $currency_order = new Currency($cart->id_currency);
        $currencies_module = $this->getCurrency($cart->id_currency);
        $result = false;

        if (is_array($currencies_module)) {
            foreach ($currencies_module as $currency_module) {
                if ($currency_order->id == $currency_module['id_currency']) {
                    $result = true;
                }
            }
        }
        $result = $result && (Currency::getIsoCodeById((int)$cart-id_currency) == 'EUR');
        return $result;
    }

    public function checkMinimumAmount($cart)
    {
        $total = (float)$cart->getOrderTotal(true, Cart::BOTH);
        return $total >= 0.01;
    }

    public function getExternalPaymentOption()
    {
        $externalOption = new PaymentOption();
        $externalOption->setAction($this->context->link->getModuleLink($this->name, 'validation', array(), true))
                       ->setAdditionalInformation($this->context->smarty->fetch('module:prestashop_picksell_pay/views/templates/front/payment_infos.tpl'))
                       ->setLogo(Media::getMediaPath(_PS_MODULE_DIR_.$this->name.'/payment.png'))
                       ->setCallToActionText($this->l('Picksell Pay'));

        return $externalOption;
    }

    public function hookPaymentReturn($params)
    {
        $params['order']->setCurrentState(Configuration::get('PS_OS_BANKWIRE'));
    }

    public function getContent() {
        $output = '';

        // this part is executed only when the form is submitted
        if (Tools::isSubmit('submit' . $this->name)) {
            // retrieve the value set by the user
            $merchantId = (string) Tools::getValue('PRESTASHOP_PICKSELL_PAY_MERCHANT_ID');
            $apiKey = (string) Tools::getValue('PRESTASHOP_PICKSELL_PAY_API_KEY');
            $apiSecret = (string) Tools::getValue('PRESTASHOP_PICKSELL_PAY_API_SECRET');
            $devMode = (boolean) Tools::getValue('PRESTASHOP_PICKSELL_PAY_DEV_MODE');

            // check that the value is valid
            if (empty($merchantId) || empty($apiKey) || empty($apiSecret)) {
                // invalid value, show an error
                $output = $this->displayError($this->l('Invalid Configuration value'));
            } else {
                // value is ok, update it and display a confirmation message
                Configuration::updateValue('PRESTASHOP_PICKSELL_PAY_MERCHANT_ID', $merchantId);
                Configuration::updateValue('PRESTASHOP_PICKSELL_PAY_API_KEY', $apiKey);
                Configuration::updateValue('PRESTASHOP_PICKSELL_PAY_API_SECRET', $apiSecret);
                Configuration::updateValue('PRESTASHOP_PICKSELL_PAY_DEV_MODE', $devMode);
                $output = $this->displayConfirmation($this->l('Settings updated'));
            }
        }

        // display any message, then the form
        return $output . $this->displayForm();
    }

    public function displayForm() {
        // Init Fields form array
        $form = [
            'form' => [
                'legend' => [
                    'title' => $this->l('Settings'),
                ],
                'input' => [
                    [
                        'type' => 'text',
                        'label' => $this->l('Picksell Pay Merchant ID'),
                        'name' => 'PRESTASHOP_PICKSELL_PAY_MERCHANT_ID',
                        'size' => 20,
                        'required' => true,
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Picksell Pay API key'),
                        'name' => 'PRESTASHOP_PICKSELL_PAY_API_KEY',
                        'size' => 20,
                        'required' => true,
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Picksell Pay API secret'),
                        'name' => 'PRESTASHOP_PICKSELL_PAY_API_SECRET',
                        'size' => 20,
                        'required' => true,
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Picksell Pay Webhook URL'),
                        'name' => 'PRESTASHOP_PICKSELL_PAY_WEBHOOK_URL',
                        'size' => 20,
                        'disabled' => true,
                        'desc' => 'Use this link in Picksell Pay API key\'s Webhook URL'
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Dev mode'),
                        'desc' => 'Use this only for testing or development',
                        'name' => 'PRESTASHOP_PICKSELL_PAY_DEV_MODE',
                        'required' => true,
                        'values' => [[
                            'id' => 'active_on',
                            'value' => 1,
                            'label' => $this->l('Yes')], [
                            'id' => 'active_off',
                            'value' => 0,
                            'label' => $this->l('No')]]
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Save'),
                    'class' => 'btn btn-default pull-right',
                ],
            ],
        ];

        $helper = new HelperForm();

        // Module, token and currentIndex
        $helper->table = $this->table;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&' . http_build_query(['configure' => $this->name]);
        $helper->submit_action = 'submit' . $this->name;

        // Default language
        $helper->default_form_language = (int) Configuration::get('PS_LANG_DEFAULT');

        // Load current value into the form
        $helper->fields_value['PRESTASHOP_PICKSELL_PAY_MERCHANT_ID'] = Tools::getValue('PRESTASHOP_PICKSELL_PAY_MERCHANT_ID', Configuration::get('PRESTASHOP_PICKSELL_PAY_MERCHANT_ID'));
        $helper->fields_value['PRESTASHOP_PICKSELL_PAY_API_KEY'] = Tools::getValue('PRESTASHOP_PICKSELL_PAY_API_KEY', Configuration::get('PRESTASHOP_PICKSELL_PAY_API_KEY'));
        $helper->fields_value['PRESTASHOP_PICKSELL_PAY_API_SECRET'] = Tools::getValue('PRESTASHOP_PICKSELL_PAY_API_SECRET', Configuration::get('PRESTASHOP_PICKSELL_PAY_API_SECRET'));
        $helper->fields_value['PRESTASHOP_PICKSELL_PAY_DEV_MODE'] = Tools::getValue('PRESTASHOP_PICKSELL_PAY_DEV_MODE', Configuration::get('PRESTASHOP_PICKSELL_PAY_DEV_MODE'));
        $helper->fields_value['PRESTASHOP_PICKSELL_PAY_WEBHOOK_URL'] = $this->context->link->getModuleLink($this->name, 'webhook', array(), true);

        return $helper->generateForm([$form]);
    }
}
