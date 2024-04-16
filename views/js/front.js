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
*
* Don't forget to prefix your containers with your own identifier
* to avoid any conflicts with others containers.
*/

let is_status_received = false;
let is_payment_option_found = false;
$(document).ready(function(){
    
   check_hitpay_payment_option();
   check_hitpay_payment_status();
   
   function check_hitpay_payment_option() {
       function option_loop() {
            if (is_payment_option_found) {
                return;
            }

            if (typeof(hitpay_logo_path) !== "undefined") {
                var hitpayInput = $('input[data-module-name="hitpay"]');
                if (hitpayInput.length > 0) {

                    const pngs = new Array(
                        'pesonet',
                        'eftpos',
                        'doku',
                        'philtrustbank',
                        'allbank',
                        'aub',
                        'chinabank',
                        'instapay',
                        'landbank',
                        'metrobank',
                        'pnb',
                        'queenbank',
                        'ussc',
                        'bayad',
                        'cebuanalhuillier',
                        'psbank',
                        'robinsonsbank',
                        'doku_wallet',
                        'favepay',
                        'shopback_paylater'
                    );

                    is_payment_option_found = true;
                    hitpayInput.parent().next().next().addClass('hitpay-payment-option-label');
                    
                    if (typeof(hitpay_logos) !== "undefined" && hitpay_logos.length > 0) {
                        var logoArray = hitpay_logos.split(',');
                        var logoImages = '';
                        
                        for(var i = 0; i < logoArray.length; i++) {
                            var extension = 'svg';
                            var logoName = logoArray[i];
                            if (pngs.includes(logoName)) {
                                var extension = 'png';
                            }

                            logoImages += '<img src="'+hitpay_logo_path+logoName+'.'+extension+'" title="'+logoName+'" alt="'+logoName+'" class="hitpay-logo"/>';
                        }
                        if (logoImages.length > 0) {
                            hitpayInput.parent().next().next().children('img').addClass('hitpay-payment-default-logo');
                            hitpayInput.parent().next().next().children('img').after(logoImages);
                        }
                    }
                } else {
                    setTimeout(option_loop, 500);
                }
            }
        }
        option_loop();
   }
   function check_hitpay_payment_status() {

        function status_loop() {
            if (is_status_received) {
                return;
            }

            if (typeof(status_ajax_url) !== "undefined") {
                $.getJSON(status_ajax_url, {'payment_id' : hitpay_payment_id, 'cart_id' : hitpay_cart_id}, function (data) {
                    $.ajaxSetup({ cache: false });
                    if (data.status == 'wait') {
                        setTimeout(status_loop, 2000);
                    } else if (data.status == 'error') {
                        $('.payment_pending').hide();
                        $('.payment_error').show();
                        is_status_received = true;
                    } else if (data.status == 'pending') {
                        $('.payment_pending').hide();
                        $('.payment_status_pending').show();
                        is_status_received = true;
                        setTimeout(function(){window.location.href = data.redirect;}, 5000);
                    } else if (data.status == 'failed') {
                        $('.payment_pending').hide();
                        $('.payment_status_failed').show();
                        is_status_received = true;
                        setTimeout(function(){window.location.href = data.redirect;}, 5000);
                    } else if (data.status == 'completed') {
                        $('.payment_pending').hide();
                        $('.payment_status_complete').show();
                        is_status_received = true;
                        setTimeout(function(){window.location.href = data.redirect;}, 5000);
                    }
                });
            }
        }
        status_loop();
    }
});
