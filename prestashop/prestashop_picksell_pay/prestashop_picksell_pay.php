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

        $payment_options = [
            $this->getExternalPaymentOption($params),
        ];

        return $payment_options;
    }

    public function checkCurrency($cart)
    {
        $currency_order = new Currency($cart->id_currency);
        $currencies_module = $this->getCurrency($cart->id_currency);

        if (is_array($currencies_module)) {
            foreach ($currencies_module as $currency_module) {
                if ($currency_order->id == $currency_module['id_currency']) {
                    return true;
                }
            }
        }
        return false;
    }

    public function getExternalPaymentOption()
    {
        if (!Configuration::hasKey('PS_OS_WAITING_PICKSELL_PAYMENT')) {
            $orderStateObj = new OrderState();
            $orderStateObj->send_email = 0;
            $orderStateObj->module_name = $this->name;
            $orderStateObj->invoice = 1;
            $orderStateObj->color = '#34209E';
            $orderStateObj->logable = 0;
            $orderStateObj->shipped = 0;
            $orderStateObj->unremovable = 1;
            $orderStateObj->delivery = 0;
            $orderStateObj->hidden = 0;
            $orderStateObj->paid = 0;
            $orderStateObj->pdf_delivery = 0;
            $orderStateObj->pdf_invoice = 1;
            $orderStateObj->deleted = 0;
            $orderStateObj->name = 'Waiting Picksell payment';
            $orderStateObj->add();
        }
        $externalOption = new PaymentOption();
        $externalOption->setCallToActionText($this->l('Picksell Pay'))
                       ->setAction($this->context->link->getModuleLink($this->name, 'validation', array(), true))
                       ->setAdditionalInformation($this->context->smarty->fetch('module:prestashop_picksell_pay/views/templates/front/payment_infos.tpl'))
                       ->setLogo(Media::getMediaPath(_PS_MODULE_DIR_.$this->name.'/payment.jpg'));

        return $externalOption;
    }

    public function hookActionObjectAddAfter($objectState)
    {
        if ($objectState->name == 'Waiting Picksell payment') {
            Configuration::updateValue('PS_OS_WAITING_PICKSELL_PAYMENT', $objectState->id);
        }
    }

    public function hookPaymentReturn($params)
    {
        $params['order']->setCurrentState(Configuration::get('PS_OS_WAITING_PICKSELL_PAYMENT'));
    }

    public function getContent() {
        $output = '';

        // this part is executed only when the form is submitted
        if (Tools::isSubmit('submit' . $this->name)) {
            // retrieve the value set by the user
            $configValue = (string) Tools::getValue('MYMODULE_CONFIG');

            // check that the value is valid
            if (empty($configValue) || !Validate::isGenericName($configValue)) {
                // invalid value, show an error
                $output = $this->displayError($this->l('Invalid Configuration value'));
            } else {
                // value is ok, update it and display a confirmation message
                Configuration::updateValue('MYMODULE_CONFIG', $configValue);
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
                        'label' => $this->l('Configuration value'),
                        'name' => 'MYMODULE_CONFIG',
                        'size' => 20,
                        'required' => true,
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
        $helper->fields_value['MYMODULE_CONFIG'] = Tools::getValue('MYMODULE_CONFIG', Configuration::get('MYMODULE_CONFIG'));

        return $helper->generateForm([$form]);
    }
}
