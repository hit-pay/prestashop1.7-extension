var placeOrderButton = '';
var originalText = '';
var loadingText = '';
var hitpayPaymentId = '';
var hitpayRedirectUrl = '';

$(document).ready(function(){
    var hitpay_id = $('input[data-module-name="hitpay"]').attr('id');
    var hitpay_form = 'pay-with-'+hitpay_id+'-form form';

    $('#'+hitpay_form).submit(function() {
        placeOrderButton = $('#payment-confirmation button[type="submit"]');
        originalText = placeOrderButton.html();
        loadingText = 'Please wait. Presenting the Drop-In..';
        
        placeOrderButton.html(loadingText);
        placeOrderButton.attr('disabled', 'disabled');
        placeOrderButton.addClass('disabled');
        
        $.getJSON(create_payment_request_ajax_url, function (data) {
            $.ajaxSetup({
                cache: false
            });
            if (data.status == 'error') {
                alert(data.message);
                placeOrderButton.html(originalText);
                placeOrderButton.removeAttr('disabled');
                placeOrderButton.removeClass('disabled');
            } else{
                if (!window.HitPay.inited) {
                    window.HitPay.init(data.payment_url, {
                      domain: data.domain,
                      apiDomain: data.apiDomain,
                    },
                    {
                      onClose: onHitpayDropInClose,
                      onSuccess: onHitpayDropInSuccess,
                      onError: onHitpayDropInError
                    });
                }
                
                hitpayRedirectUrl = data.redirect_url;
                hitpayPaymentId = data.payment_request_id;
                
                window.HitPay.toggle({
                    paymentRequest: data.payment_request_id,          
                });
            }
        })
        .fail(function(jqXHR, textStatus, errorThrown) { 
            alert('Site server error while creating a payment request.');
            placeOrderButton.html(originalText);
            placeOrderButton.removeAttr('disabled');
            placeOrderButton.removeClass('disabled');
        });
        
        return false;

    })  ;
});

function onHitpayDropInSuccess (data) {
    location.href = hitpayRedirectUrl+'&reference='+hitpayPaymentId+'&status='
}

function onHitpayDropInClose (data) {
    placeOrderButton.html(originalText);
    placeOrderButton.removeAttr('disabled');
    placeOrderButton.removeClass('disabled');
}

function onHitpayDropInError (error) {
    alert('Site server error while creating a payment request. Error: ' + error);
    placeOrderButton.html(originalText);
    placeOrderButton.removeAttr('disabled');
    placeOrderButton.removeClass('disabled');
}