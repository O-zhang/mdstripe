<?php
/**
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
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2015 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

require_once dirname(__FILE__).'/../../vendor/autoload.php';

class MdstripeConfirmationModuleFrontController extends ModuleFrontController
{
    /** @var MDStripe $module */
    public $module;

    public function postProcess()
    {
        if ((Tools::isSubmit('id_cart') == false) || (Tools::isSubmit('stripe-token') == false)) {
            $this->errors[] = $this->module->l('An error occurred. Please contact us for more information.');
            $this->setTemplate('error.tpl');
            return false;
        }

        $token = Tools::getValue('stripe-token');
        $id_cart = Tools::getValue('id_cart');

        $cart = new Cart((int)$id_cart);
        $customer = new Customer((int)$cart->id_customer);
        $currency = new Currency((int)$cart->id_currency);

        $stripe = array(
            'secret_key' => 'sk_test_j1SR7Wkm2mreT22WCFisSF20',
            'publishable_key' => 'pk_test_g4xEGpWUVb8DZSdophAK4jcD',
        );

        \Stripe\Stripe::setApiKey($stripe['secret_key']);

        $stripe_customer = \Stripe\Customer::create(array(
            'email' => $customer->email,
            'source'  => $token
        ));

        $stripe_charge = \Stripe\Charge::create(array(
            'customer' => $stripe_customer->id,
            'amount'   => (int)($cart->getOrderTotal(true) * 100),
            'currency' => $currency->iso_code
        ));

        /**
         * Since it's an example we are validating the order right here,
         * You should not do it this way in your own module.
         */
        $payment_status = Configuration::get('PS_OS_PAYMENT');
        $message = null;

        /**
         * Converting cart into a valid order
         */

        $currency_id = (int)Context::getContext()->currency->id;

        $this->module->validateOrder($id_cart, $payment_status, $cart->getOrderTotal(), 'Stripe', $message, array(), $currency_id, false, $cart->secure_key);

        /**
         * If the order has been validated we try to retrieve it
         */
        $order_id = Order::getOrderByCartId((int)$cart->id);

        if ($order_id) {
            /**
             * The order has been placed so we redirect the customer on the confirmation page.
             */
            $this->context->smarty->assign(array(
                'status' => 'ok',
                'stripe_charge' => $stripe_charge,
            ));
            $this->setTemplate('confirmation.tpl');
        } else {
            /**
             * An error occurred and is shown on a new page.
             */
            $this->errors[] = $this->module->l('An error occurred. Please contact us for more information.');
            $this->setTemplate('error.tpl');
            return false;
        }
        return true;
    }
}
