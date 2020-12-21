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
     * @return bool|void
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function postProcess()
    {
        if ((Tools::isSubmit('cart_id') == false)
            || (Tools::isSubmit('secure_key') == false)
            || (Tools::isSubmit('hmac') == false)) {
            return false;
        }

        file_put_contents(
            _PS_ROOT_DIR_ . '/log.txt',
            "\n" . print_r('webhook: ', true) .
            "\n" . print_r($_POST, true) .
            "\n\nfile: " . __FILE__ .
            "\n\nline: " . __LINE__ .
            "\n\ntime: " . date('d-m-Y H:i:s'), 8
        );

        $cart_id = Tools::getValue('cart_id');
        $secure_key = Tools::getValue('secure_key');

        $cart = new Cart((int) $cart_id);
        $customer = new Customer((int) $cart->id_customer);

        if ($secure_key != $customer->secure_key) {
            $this->context->smarty->assign(
                'errors',
                array(
                    $this->module->l('An error occured. Please contact the merchant to have more informations')
                )
            );
            return $this->setTemplate('module:hitpay/views/templates/front/error.tpl');
        }

        $payment_status = Configuration::get('HITPAY_WAITING_PAYMENT_STATUS'); // Default value for a payment that succeed.
        $message = null; // You can add a comment directly into the order so the merchant will see it in the BO.
        $transaction_id = null;
        $module_name = $this->module->displayName;
        $currency_id = (int) Context::getContext()->currency->id;

        try {
            $data = $_POST;
            unset($data['hmac']);

            $salt = base64_decode(Configuration::get('HITPAY_ACCOUNT_SALT'));
            if (Client::generateSignatureArray($salt, $data) == Tools::getValue('hmac')) {
                $payment_request_id = Tools::getValue('payment_request_id');
                /**
                 * @var HitPayPayment $hitpay_payment
                 */
                if ($saved_payment = HitPayPayment::getById($payment_request_id)) {
                    $saved_payment->status = Tools::getValue('status');

                    if ($saved_payment->status == 'completed'
                        && $saved_payment->amount == Tools::getValue('amount')
                        && $saved_payment->cart_id == Tools::getValue('reference_number')
                        && $saved_payment->currency_id == Currency::getIdByIsoCode(Tools::getValue('currency'))
                        && !$saved_payment->is_paid) {
                        $payment_status = Configuration::get('PS_OS_PAYMENT');
                    } elseif ($saved_payment->status == 'failed') {
                        $payment_status = Configuration::get('PS_OS_ERROR');
                    } elseif ($saved_payment->status == 'pending') {
                        $payment_status = Configuration::get('HITPAY_WAITING_PAYMENT_STATUS');
                    } else {
                        throw new \Exception(
                            sprintf(
                                'HitPay: payment id: %s, amount is %s, status is %s, is paid: %s',
                                $saved_payment->payment_id,
                                $saved_payment->amount,
                                $saved_payment->status,
                                $saved_payment->is_paid ? 'yes' : 'no'
                            )
                        );
                    }

                    $order_id = Order::getIdByCartId((int) $cart->id);
                    if (!$order_id) {
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

                        $saved_payment->order_id = Order::getIdByCartId((int) $cart->id);
                        $saved_payment->is_paid = true;
                        $saved_payment->save();
                    } else {
                        $order_history = new OrderHistory();
                        $order_history->changeIdOrderState($payment_status, $order_id);
                    }

                    if ($order_id) {
                        $hitpay_client = new Client(
                            Configuration::get('HITPAY_ACCOUNT_API_KEY'),
                            Configuration::get('HITPAY_LIVE_MODE')
                        );

                        $result = $hitpay_client->getPaymentStatus($payment_request_id);

                        if ($payments = $result->getPayments()) {
                            $payment = array_shift($payments);
                            if ($payment->status == 'succeeded') {
                                $transaction_id = $payment->id;
                            }

                            $order_payments = OrderPayment::getByOrderId($saved_payment->order_id);
                            if (isset($order_payments[0])) {
                                $order_payments[0]->transaction_id = $transaction_id;
                                $order_payments[0]->save();
                            }
                        }
                    }

                } else {
                    throw new \Exception(
                        sprintf(
                            'HitPay has not the payment request id: %s',
                            $payment_request_id
                        )
                    );
                }
            } else {
                throw new \Exception(sprintf('HitPay: hmac is not the same like generated'));
            }
        } catch (\Exeption $e) {
            PrestaShopLogger::addLog(
                'HitPay: ' . $e->getMessage(),
                3,
                null,
                'HitPay'
            );
        }

        exit;
    }
}
