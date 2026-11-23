$ = jQuery;

const checkbox = document.getElementById('check_qr');
if (checkbox) {
    checkbox.addEventListener('change', function () {
        onChangeWithQR();
    });
}

function openQR(){
    $('#ifrRedsysQR').dialog({
        title: "",
        autoOpen: true,
        minWidth: 400,
        minHeight: 630,
        modal: true,
        draggable: false,
        resizable: false,
        close: function(event, ui) { $(this).remove();},
    }).width(400).height(630);

    document.getElementById("frmRedsysPayment").submit();

    $('#ifrRedsysQR').siblings('div.ui-dialog-titlebar').css("display","none");
    $('#ifrRedsysQR').siblings('div.ui-dialog-titlebar').children("button").remove();
}

function closeQR(){
    $('#ifrRedsysQR').dialog('close');
}

function placeOrder(){
    checkout_place_order_success = true;
    getPlaceOrderButton().click();
}

function getPlaceOrderButton(){
    if($("#place_order").length){
        return $("#place_order");
    }else{
        return $('.wc-block-components-checkout-place-order-button');
    }
}

window.addEventListener('message', function(event) {
    if(event.data.status === "CLOSE") {
        closeQR();
        location.reload();
    }else{
        jQuery.ajax({
            type: "POST",
            url: document.getElementById('backFromQRUrl').value,
            data: {
                orderId: document.getElementById('orderId').value,
                status: event.data.status,
            },
            dataType: 'json',
            success: function(response) {
                window.location.href = response.redirectUrl;
            }
        });
    }
});

function onPlaceOrder(){
    var payment_method = jQuery( 'form.checkout input[name=payment_method]:checked' ).val();
    if(!payment_method){
        payment_method = jQuery( 'input[name=radio-control-wc-payment-method-options]:checked' ).val();
    }
    var check_qr = jQuery( 'input[name=check_qr]:checked' ).val();
    if ( payment_method == 'redsys' && !checkout_place_order_success && check_qr) {
        openQR();
    }
    return true;
}

var checkout_place_order_success = false;
jQuery( 'form.checkout' ).on( 'checkout_place_order_success', onPlaceOrder);
jQuery('.wc-block-components-checkout-place-order-button').click(onPlaceOrder);

document.body.checkout_error = function(e){
    var payment_method = jQuery( 'form.checkout input[name=payment_method]:checked' ).val();
    if(!payment_method){
        payment_method = jQuery( 'input[name=radio-control-wc-payment-method-options]:checked' ).val();
    }
    if ( payment_method == 'redsys' && checkout_place_order_success) {
        jQuery( '.woocommerce-error' ).remove();
    }
    return true;
}

onChangeWithQR();

function onChangeWithQR() {
    withQR = jQuery('#check_qr').prop('checked');

    if(withQR){
        jQuery('#div-redirect-options').hide();
    }else{
        jQuery('#div-redirect-options').show();
    }

    var data = {
        withQR: withQR
    };

    jQuery.ajax({
        type: "POST",
        url: document.getElementById('sessionVarsUrl').value,
        data: data,
    });
}