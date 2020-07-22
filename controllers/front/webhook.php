<?php
/**
* 2007-2020 PrestaShop
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
*  @copyright 2007-2020 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

use HitPay\Client;

/**
 * Class HitpayWebhookModuleFrontController
 */
class HitpayWebhookModuleFrontController extends ModuleFrontController
{
    /**
     * @return bool
     */
    public function postProcess()
    {
//        if ((Tools::isSubmit('cart_id') == false)
//            || (Tools::isSubmit('secure_key') == false)
//            || (Tools::isSubmit('hmac') == false)) {
//            return false;
//        }

        $hitpay_client = new Client(
            Configuration::get('HITPAY_ACCOUNT_API_KEY'),
            Configuration::get('HITPAY_LIVE_MODE')
        );

        $_POST = array(
            'payment_id' => '911ad858-49f6-494e-8848-0285aef2bd18',
            'payment_request_id' => '911ad84e-9438-46c4-9302-c6ff7402e7f5',
            'phone' => '',
            'amount' => 73.68,
            'currency' => 'SGD',
            'status' => 'completed',
            'reference_number' => 20,
            'hmac' => '34f146467b332790ca278fdce89a747487a17b74933473cc708fc3bd00b0dbb6',
        );

        $data = $_POST;
        $data2 = array();

        $data2['phone'] = '';
        $data2['amount'] = '73.68';
        $data2['currency'] = 'SGD';
        $data2['reference_number'] = 20;

        unset($data['hmac']);

        file_put_contents(
            _PS_ROOT_DIR_ . '/log.txt',
            "\n" . print_r($data, true) .
            "\n" . print_r($_POST, true) .
            "\n" . print_r(Client::generateSignatureArray(Configuration::get('HITPAY_ACCOUNT_SALT'), $_POST), true) .
            "\n" . print_r(Client::generateSignatureArray(Configuration::get('HITPAY_ACCOUNT_SALT'), $data), true) .
            "\n" . print_r(Client::generateSignatureArray(Configuration::get('HITPAY_ACCOUNT_API_KEY'), $_POST), true) .
            "\n" . print_r(Client::generateSignatureArray(Configuration::get('HITPAY_ACCOUNT_API_KEY'), $data), true) .
            "\n" . print_r(Client::generateSignatureArray(Configuration::get('HITPAY_ACCOUNT_API_KEY'), $data2), true) .
            "\n" . print_r(Tools::getValue('hmac'), true) .
            "\n\nfile: " . __FILE__ .
            "\n\nline: " . __LINE__ .
            "\n\ntime: " . date('d-m-Y H:i:s'), 8
        );
        $cart_id = Tools::getValue('cart_id');
        $secure_key = Tools::getValue('secure_key');

        $cart = new Cart((int) $cart_id);
        $customer = new Customer((int) $cart->id_customer);

        if ($secure_key != $customer->secure_key) {
            $this->errors[] = $this->module->l('An error occured. Please contact the merchant to have more informations');

            return $this->setTemplate('error.tpl');
        }
    }
}
