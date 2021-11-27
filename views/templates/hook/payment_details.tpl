<div class="row" id="hitpay-payment-details" style="margin-top:15px">
    <div class="col-sm text-center">
        <p class="text-muted mb-0"><strong>{l s='HitPay Payment Type' mod='hitpay'}</strong></p>
        <strong id="orderProductsTotal">{$payment_method|escape:'htmlall':'UTF-8'}</strong>
    </div>
    <div class="col-sm text-center">
        <p class="text-muted mb-0"><strong>{l s='HitPay Fee' mod='hitpay'}</strong></p>
        <strong id="orderProductsTotal">{$hitpay_fee|escape:'htmlall':'UTF-8'}</strong>
    </div>
</div>
<script type="text/javascript">
$(document).ready(function() {
    $("#hitpay-payment-details").insertAfter($('#order-shipping-total-container').parent());
});
</script>