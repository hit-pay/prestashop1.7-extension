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

require_once _PS_MODULE_DIR_ . 'hitpay/classes/HitPayPayment.php';

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
        if ((Tools::isSubmit('cart_id') == false)
            || (Tools::isSubmit('secure_key') == false)
            || (Tools::isSubmit('hmac') == false)) {
            return false;
        }

        $cart_id = Tools::getValue('cart_id');
        $secure_key = Tools::getValue('secure_key');

        $cart = new Cart((int) $cart_id);
        $customer = new Customer((int) $cart->id_customer);

        /*if (Client::generateSignatureArray(Configuration::get('HITPAY_ACCOUNT_SALT'), $_POST) == Tools::isSubmit('hmac')) {
            $payment_request_id = Tools::getValue('payment_request_id');
            if ($payment = HitPayPayment::getById($payment_request_id)) {
                $payment->status = Tools::getValue('status');
                $payment->save();
            }
        }*/

        try {
            $payment_request_id = Tools::getValue('payment_request_id');
            if ($payment = HitPayPayment::getById($payment_request_id)) {
                $payment->status = Tools::getValue('status');
                $payment->save();
            }
        } catch (\Exeption $e) {
            PrestaShopLogger::addLog(
                $e->getMessage(),
                3,
                null,
                'HitPay'
            );
        }

        if ($secure_key != $customer->secure_key) {
            $this->errors[] = $this->module->l('An error occured. Please contact the merchant to have more informations');

            return $this->setTemplate('error.tpl');
        }
    }
}
