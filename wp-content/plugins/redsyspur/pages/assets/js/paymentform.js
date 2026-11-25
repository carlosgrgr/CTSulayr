$ = jQuery;
allowReference = document.getElementById('allowReference').value == "true";
hasReference = document.getElementById('hasReference').value == "true";
insiteUnificado = document.getElementById('insiteUnificado').value == "true";

/*if(!parseInt(document.getElementById('orderId').value)){
    document.getElementById('div_paymentform').innerHTML = document.getElementById('errorText').value;
}*/

if(insiteUnificado){
    getInSiteForm(
        'insite-form-container',
        document.getElementById('btnStyle').value,
        document.getElementById('body_style').value,
        document.getElementById('form_style').value,
        document.getElementById('form_text_style').value,
        document.getElementById('btnText').value,
        document.getElementById('merchantCode').value,
        document.getElementById('merchantTerminal').value,
        document.getElementById('idCart').value,
        document.getElementById('idioma_tpv').value,
        true, 
        true, 
        'inline',
        true
    );
    $("#insite-form-partes").hide();
}else{
    estiloCaja = 'padding: 8px; padding-right: 0px!important;';
    getCardInput('card-number', estiloCaja + 'width: 96%;', '#', 'width: 95%');
    getExpirationInput('card-expiration', estiloCaja, 'MM/AA');
    getCVVInput('cvv', estiloCaja, 'CVV');
    getPayButton('boton', document.getElementById('btnStyle').value, document.getElementById('btnText').value, document.getElementById('merchantCode').value, document.getElementById('merchantTerminal').value, document.getElementById('idCart').value);
    $("#insite-form-container").hide();
}

function processPayment(useRerefence, operId = ''){
    var dataToSend={
        "idCart": document.getElementById('idCart').value,
        "orderId": document.getElementById('orderId').value,
        "orderTotal": document.getElementById('orderTotal').value,
        "billingFirstName": document.getElementById('billingFirstName').value,
        "billingLastName": document.getElementById('billingLastName').value,
        "email": document.getElementById('email').value,
        "valores3DS": cargaValoresBrowser3DS(),
        "operId": operId,
        "useReference": useRerefence,
        "saveReference": allowReference ? document.getElementById("save_reference").checked : false,
    };
    
    $.ajax({
        url: document.getElementById('procUrl').value,
        type: "POST",
        data: dataToSend,
        dataType: 'json',
        success: function (data) {
            placeOrder();
        },
        error: function (request, status, error){
            placeOrder();
        }
    });
};

function cargaValoresBrowser3DS() {

    var valores3DS = new Object();

    //browserJavaEnabled
    valores3DS.browserJavaEnabled = navigator.javaEnabled();

    //browserJavascriptEnabled
    valores3DS.browserJavascriptEnabled = true;

    //browserLanguage
    var userLang = navigator.language || navigator.userLanguage;
    valores3DS.browserLanguage = userLang;

    //browserColorDepth
    valores3DS.browserColorDepth = screen.colorDepth;

    //browserScreenHeight
    //browserScreenWidth
    var myWidth = 0,
        myHeight = 0;
    if (typeof window.innerWidth == "number") {
        //Non-IE
        myWidth = window.innerWidth;
        myHeight = window.innerHeight;
    } else if (
        document.documentElement &&
        (document.documentElement.clientWidth ||
        document.documentElement.clientHeight)
    ) {
        //IE 6+ in 'standards compliant mode'
        myWidth = document.documentElement.clientWidth;
        myHeight = document.documentElement.clientHeight;
    } else if (
        document.body &&
        (document.body.clientWidth || document.body.clientHeight)
    ) {
        //IE 4 compatible
        myWidth = document.body.clientWidth;
        myHeight = document.body.clientHeight;
    }
    valores3DS.browserScreenHeight = myHeight;
    valores3DS.browserScreenWidth = myWidth;

    //browserTZ
    var d = new Date();
    valores3DS.browserTZ = d.getTimezoneOffset();

    //browserUserAgent
    valores3DS.browserUserAgent = navigator.userAgent;

    var valores3DSstring = JSON.stringify(valores3DS);

    return valores3DSstring;
}

function idOperOK(idOper) {
    processPayment(false, idOper);
}

function idOperKO() {
    placeOrder();
}

function merchantValidation() {
    /*if ( parseInt(document.getElementById('orderId').value) )
        return true;
    else
        return false;*/
    return true;
}

function getPlaceOrderButton(){
    if($("#place_order").length){
        return $("#place_order");
    }else{
        return $('.wc-block-components-checkout-place-order-button');
    }
}

function placeOrder(){
    checkout_place_order_success = true;
    getPlaceOrderButton().click();
}

if (typeof receiveMessageListener !== "undefined"){
    window.removeEventListener("message", receiveMessageListener);
}

receiveMessageListener = function(event){
    if(event.origin.includes("redsys.es")){
        storeIdOper(event, "token", "errorCode", merchantValidation);
        if(document.getElementById("errorCode").value != ""){
            alert(errorCodeLiteral(document.getElementById("errorCode").value));
        }
        if (document.getElementById("token").value != "") {
            idOperOK(document.getElementById("token").value);
        }
    }
}
window.addEventListener("message", receiveMessageListener);

if(allowReference){
    $("#div_save_reference").show();

    if(hasReference){
        $('input[name=payment_type]').show();

        $("#div_radio_reference").show();
        $("#div_radio_usual").show();

        $('input[name=payment_type]').change(function(){
            selected_value = $('input[name=payment_type]:checked').val();
            if(selected_value == "reference"){
                $("#div_reference").show();
                $("#div_usual").hide();
            }else{
                $("#div_reference").hide();
                $("#div_usual").show();
            }
        });
    }
}

function onPlaceOrder(){
    var payment_method = jQuery( 'form.checkout input[name=payment_method]:checked' ).val();
    if(!payment_method){
        payment_method = jQuery( 'input[name=radio-control-wc-payment-method-options]:checked' ).val();
    }
    if ( payment_method == 'redsys_insite' && !checkout_place_order_success /*&& parseInt(document.getElementById('orderId').value)*/) {
        selected_value = $('input[name=payment_type]:checked').val();
        if(selected_value == "reference"){
            processPayment(true);
        }else{
            payFromModule();
        }
        return false;
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
    if ( payment_method == 'redsys_insite' && checkout_place_order_success) {
        jQuery( '.woocommerce-error' ).remove();
    }
    return true;
}

if(insiteUnificado){
    $("#redsys-hosted-pay-button").css("min-height","240px");
}

const errorCodeLiterales = JSON.parse(document.getElementById('errorCodeLiterales').value);

function errorCodeLiteral(errorCode){
    if(errorCode in errorCodeLiterales){
        return errorCodeLiterales[errorCode];
    }else{
        return errorCode;
    }
}