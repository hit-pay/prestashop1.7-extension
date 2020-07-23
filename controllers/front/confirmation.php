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
 * Class HitpayConfirmationModuleFrontController
 */
class HitpayConfirmationModuleFrontController extends ModuleFrontController
{
    /**
     * @return bool
     */
    public function postProcess()
    {
        if ((Tools::isSubmit('cart_id') == false)
            || (Tools::isSubmit('secure_key') == false)
            || (Tools::isSubmit('reference') == false)) {
            return false;
        }

        $cart_id = Tools::getValue('cart_id');
        $secure_key = Tools::getValue('secure_key');

        $cart = new Cart((int) $cart_id);
        $customer = new Customer((int) $cart->id_customer);

        if ($secure_key != $customer->secure_key) {
            $this->errors[] = $this->module->l(
                'An error occured. Please contact the merchant to have more informations'
            );

            return $this->setTemplate('error.tpl');
        }

        /**
         * Since it's an example we are validating the order right here,
         * You should not do it this way in your own module.
         */
        $payment_status = Configuration::get('HITPAY_WAITING_PAYMENT_STATUS'); // Default value for a payment that succeed.
        $message = null; // You can add a comment directly into the order so the merchant will see it in the BO.
        $transaction_id = null;

        try {
            $hitpay_client = new Client(
                Configuration::get('HITPAY_ACCOUNT_API_KEY'),
                Configuration::get('HITPAY_LIVE_MODE')
            );
            $payment_id = Tools::getValue('reference');
            $payment = HitPayPayment::getById($payment_id);
            if ($payment->status == 'completed' && $payment->amount == $cart->getOrderTotal()) {
                $result = $hitpay_client->getPaymentStatus($payment_id);
                if ($result->getStatus() == 'completed') {
                    $payments = $result->getPayments();
                    $payment = array_shift($payments);
                    if ($payment->status == 'succeeded') {
                        $transaction_id = $payment->id;
                    } else {
                        throw new \Exception(sprintf('HitPay: sent payment status is %s', $payment->status));
                    }
                    $payment_status = Configuration::get('PS_OS_PAYMENT');
                } elseif ($result->getStatus() == 'failed') {
                    $payment_status = Configuration::get('PS_OS_ERROR');
                } elseif ($result->getStatus() == 'pending') {
                    $payment_status = Configuration::get('HITPAY_WAITING_PAYMENT_STATUS');
                } else {
                    throw new \Exception(sprintf('HitPay: sent status is %s', $result->getStatus()));
                }
            } else {
                throw new \Exception(sprintf('HitPay: amount is %s, status is %s', $payment->amount, $payment->status));
            }
        } catch (\Exception $e) {
            PrestaShopLogger::addLog(
                $e->getMessage(),
                3,
                null,
                'HitPay'
            );

            $this->errors[] = $this->module->l('Something went wrong, please contact the merchant');

            return $this->setTemplate('error.tpl');
        }

        /**
         * Converting cart into a valid order
         */
        $module_name = $this->module->displayName;
        $currency_id = (int) Context::getContext()->currency->id;

        $this->module->validateOrder(
            $cart_id,
            $payment_status,
            $cart->getOrderTotal(),
            $module_name,
            $message,
            array(
                'transaction_id' => $transaction_id
            ),
            $currency_id,
            false,
            $secure_key
        );

        /**
         * If the order has been validated we try to retrieve it
         */
        $order_id = Order::getOrderByCartId((int) $cart->id);

        if ($order_id && ($secure_key == $customer->secure_key)) {
            /**
             * The order has been placed so we redirect the customer on the confirmation page.
             */
            $module_id = $this->module->id;
            Tools::redirect(
                'index.php?controller=order-confirmation&id_cart='
                . $cart_id
                . '&id_module='
                . $module_id
                . '&id_order='
                . $order_id
                . '&key='
                . $secure_key
            );
        } else {
            /*
             * An error occured and is shown on a new page.
             */
            $this->errors[] = $this->module->l(
                'An error occured. Please contact the merchant to have more informations'
            );

            return $this->setTemplate('error.tpl');
        }
    }
}
