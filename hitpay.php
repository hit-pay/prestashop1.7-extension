<?php
/**
* 2007-2021 PrestaShop
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
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2021 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

if (!defined('_PS_VERSION_')) {
    exit;
}

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

require_once _PS_MODULE_DIR_ . 'hitpay/vendor/autoload.php';
require_once _PS_MODULE_DIR_ . 'hitpay/classes/HitPayPayment.php';

/**
 * Class Hitpay
 */
class Hitpay extends PaymentModule
{
    protected $html = '';
    protected $postErrors = array();
    
    /**
     * Hitpay constructor.
     */
    public function __construct()
    {
        $this->name = 'hitpay';
        $this->tab = 'payments_gateways';
        $this->version = '1.1.5';
        $this->author = 'HitPay';
        $this->need_instance = 0;

        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('HitPay');
        $this->description = $this->l('Accept secure PayNow QR, Credit Card, WeChatPay and AliPay payments.');
        $this->limited_currencies = array('EUR', 'SGD');
        $this->ps_versions_compliancy = array('min' => '1.7.1.0', 'max' => _PS_VERSION_);
    }

    public function install()
    {
        if (extension_loaded('curl') == false) {
            $this->_errors[] = $this->l('You have to enable the cURL extension on your server to install this module');
            return false;
        }

        Configuration::updateValue('HITPAY_LIVE_MODE', false);

        $order_status = new OrderState();
        foreach (Language::getLanguages() as $lang) {
            $order_status->name[$lang['id_lang']] = $this->l('Waiting for payment confirmation');
        }
        $order_status->module_name = $this->name;
        $order_status->color = '#FF8C00';
        $order_status->send_email = false;
        if ($order_status->save()) {
            Configuration::updateValue('HITPAY_WAITING_PAYMENT_STATUS', $order_status->id);
        }

        return parent::install() &&
            $this->registerHook('header') &&
            $this->registerHook('backOfficeHeader') &&
            $this->registerHook('paymentOptions') &&
            $order_status->id &&
            HitPayPayment::install();
    }

    public function uninstall()
    {
        Configuration::deleteByName('HITPAY_LIVE_MODE');

        $order_status = new OrderState(Configuration::get('HITPAY_WAITING_PAYMENT_STATUS'));

        return parent::uninstall() &&
            $order_status->delete() &&
            HitPayPayment::uninstall();
    }

    /**
     * Load the configuration form
     */
    public function getContent()
    {
        /**
         * If values have been submitted in the form, process.
         */
        if (((bool)Tools::isSubmit('submitHitpayModule')) == true) {
            $this->postValidation();
            if (!count($this->postErrors)) {
                $this->postProcess();
            } else {
                foreach ($this->postErrors as $err) {
                    $this->html .= $this->displayError($err);
                }
            }
        }

        $this->html .=  $this->renderForm();
        return $this->html;
    }

    /**
     * Create the form that will be displayed in the configuration of your module.
     */
    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitHitpayModule';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(), /* Add values for your inputs */
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($this->getConfigForm()));
    }

    /**
     * Create the structure of your form.
     */
    protected function getConfigForm()
    {
        return array(
            'form' => array(
                'legend' => array(
                'title' => $this->l('Settings'),
                'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Live mode'),
                        'name' => 'HITPAY_LIVE_MODE',
                        'is_bool' => true,
                        'desc' => $this->l('Use this module in live mode'),
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled')
                            )
                        ),
                    ),
                    array(
                        'type' => 'text',
                        'name' => 'HITPAY_ACCOUNT_API_KEY',
                        'label' => $this->l('Api Key'),
                        'required' => true,
                    ),
                    array(
                        'type' => 'text',
                        'name' => 'HITPAY_ACCOUNT_SALT',
                        'label' => $this->l('Salt'),
                        'required' => true,
                    ),
                    array(
                        'type' => 'checkbox',
                        'name' => 'HITPAY_PAYMENTS',
                        'label' => $this->l('Payment Methods'),
                        'values' => array(
                            'query' => array(
                                array(
                                    'id' => 'paynow_online',
                                    'name' => "PayNow QR",
                                    'val' => '1'
                                ),
                                array(
                                    'id' => 'card',
                                    'name' => "Credit cards",
                                    'val' => '2'
                                ),
                                array(
                                    'id' => 'wechat',
                                    'name' => "WeChatPay and AliPay",
                                    'val' => '3'
                                ),
                            ),
                            'id' => 'id',
                            'name' => 'name'
                        )
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );
    }

    /**
     * Set values for the inputs.
     */
    protected function getConfigFormValues()
    {
        $LIVE_MODE = Configuration::get('HITPAY_LIVE_MODE');
        $API_KEY = Configuration::get('HITPAY_ACCOUNT_API_KEY');
        $SALT = Configuration::get('HITPAY_ACCOUNT_SALT');
        $paynow_online = Configuration::get('HITPAY_PAYMENTS_paynow_online');
        $card = Configuration::get('HITPAY_PAYMENTS_card');
        $wechat = Configuration::get('HITPAY_PAYMENTS_wechat');
            
        return array(
            'HITPAY_LIVE_MODE' => Tools::getValue('HITPAY_LIVE_MODE', $LIVE_MODE),
            'HITPAY_ACCOUNT_API_KEY' => Tools::getValue('HITPAY_ACCOUNT_API_KEY', $API_KEY),
            'HITPAY_ACCOUNT_SALT' => Tools::getValue('HITPAY_ACCOUNT_SALT', $SALT),
            'HITPAY_PAYMENTS_paynow_online' => Tools::getValue('HITPAY_PAYMENTS_paynow_online', $paynow_online),
            'HITPAY_PAYMENTS_card' => Tools::getValue('HITPAY_PAYMENTS_card', $card),
            'HITPAY_PAYMENTS_wechat' => Tools::getValue('HITPAY_PAYMENTS_wechat', $wechat),
        );
    }
    
    protected function postValidation()
    {
        $HITPAY_ACCOUNT_API_KEY = Tools::getValue('HITPAY_ACCOUNT_API_KEY');
        $HITPAY_ACCOUNT_API_KEY = trim($HITPAY_ACCOUNT_API_KEY);
        $HITPAY_ACCOUNT_API_KEY = strip_tags($HITPAY_ACCOUNT_API_KEY);
        
        $HITPAY_ACCOUNT_SALT = Tools::getValue('HITPAY_ACCOUNT_SALT');
        $HITPAY_ACCOUNT_SALT = trim($HITPAY_ACCOUNT_SALT);
        $HITPAY_ACCOUNT_SALT = strip_tags($HITPAY_ACCOUNT_SALT);
        
        if (empty($HITPAY_ACCOUNT_API_KEY)) {
            $this->postErrors[] = $this->l('Please provide API Key');
        }
        if (empty($HITPAY_ACCOUNT_SALT)) {
            $this->postErrors[] = $this->l('Please provide API Salt');
        }
    }

    /**
     * Save form data.
     */
    protected function postProcess()
    {
        $form_values = $this->getConfigFormValues();

        foreach (array_keys($form_values) as $key) {
            Configuration::updateValue($key, Tools::getValue($key));
        }

        Configuration::updateValue('HITPAY_ACCOUNT_SALT', Tools::getValue('HITPAY_ACCOUNT_SALT'));
        $this->html .= $this->displayConfirmation($this->l('Settings updated'));
    }

    /**
    * Add the CSS & JavaScript files you want to be loaded in the BO.
    */
    public function hookBackOfficeHeader()
    {
        if (Tools::getValue('module_name') == $this->name) {
            $this->context->controller->addJS($this->_path.'views/js/back.js');
            $this->context->controller->addCSS($this->_path.'views/css/back.css');
        }
    }

    /**
     * Add the CSS & JavaScript files you want to be added on the FO.
     */
    public function hookHeader()
    {
        $this->context->controller->addJS($this->_path.'/views/js/front.js');
        $this->context->controller->addCSS($this->_path.'/views/css/front.css');
    }

    /**
     * Return payment options available for PS 1.7+
     *
     * @param array Hook parameters
     *
     * @return array|null
     */
    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return;
        }
        if (!$this->checkCurrency($params['cart'])) {
            return;
        }
        $button_text = '';
        if (Configuration::get('HITPAY_PAYMENTS_paynow_online')) {
            $button_text .= 'PayNow QR, ';
        }
        if (Configuration::get('HITPAY_PAYMENTS_card')) {
            $title = 'Credit cards, ';
            if (!empty($button_text)) {
                $title =  Tools::strtolower($title);
            }
            $button_text .= $title;
        }
        if (Configuration::get('HITPAY_PAYMENTS_wechat')) {
            $button_text .= 'WeChatPay and AliPay';
        }

        trim($button_text, ', ');

        $option = new PaymentOption();
        $option->setCallToActionText($this->l($button_text))
            ->setAction($this->context->link->getModuleLink($this->name, 'validation', array(), true));

        return [
            $option
        ];
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
}
