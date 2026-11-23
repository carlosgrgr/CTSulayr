var last_request = [];
(() => {
    "use strict";

    const name = "redsys_insite";
    const paymentFieldsUrl = "/?wc-api=WC_redsys_insite_payment_fields";

    const {registerPaymentMethod} = window.wc.wcBlocksRegistry;
    const {getPaymentMethodData} = window.wc.wcSettings;
    const {decodeEntities} = wp.htmlEntities;
    const {createElement} = window.React;
    const {checkoutData} = window.wc.wcSettings.allSettings;

    const paymentMethodData = getPaymentMethodData(name);

    class Content extends window.React.Component{
        componentDidMount() {
            refreshData();
            document.addEventListener('change', this.handleBillingChange);
        }
    
        componentWillUnmount() {
            document.removeEventListener('change', this.handleBillingChange);
        }
    
        handleBillingChange = (event) => {
            const form = document.querySelector('form.wc-block-checkout__form');
            const form_payment_method = document.querySelector('.wc-block-checkout__payment-method');
            if (form && form.contains(event.target) && !form_payment_method.contains(event.target)) {
                refreshData();
            }
        };

        render(){
            return createElement('div', { 
                id: 'div-' + name
            });
        }
    }

    const options = {
        name: name,
        label: decodeEntities(paymentMethodData.title || ""),
        ariaLabel: decodeEntities(paymentMethodData.title || ""),
        placeOrderButtonLabel: decodeEntities(paymentMethodData.buttonLabel || ""),
        content: createElement(Content, null),
        edit: createElement(Content, null),
        canMakePayment: () => true,
        supports: {
            features: paymentMethodData.supports
        }
    };

    registerPaymentMethod(options);

    function refreshData(){
        if(!paymentFieldsUrl){
            setContent(paymentMethodData.description, true);
            return;
        }

        const use_address_for_billing = jQuery('.wc-block-checkout__use-address-for-billing input[type=checkbox]').is(":checked");
        const post_data = {
            order_id: checkoutData.order_id,
            billing_email: jQuery('#email').val(),
            billing_first_name: use_address_for_billing ? jQuery('#shipping-first_name').val() : jQuery('#billing-first_name').val(),
            billing_last_name: use_address_for_billing ? jQuery('#shipping-last_name').val() : jQuery('#billing-last_name').val(),
        }

        var data = {
            post_data: new URLSearchParams(post_data).toString()
        };
        
        const full_url = paymentFieldsUrl + "?" + data.post_data;
        if(last_request[full_url] == undefined){
            var jqXHR = jQuery.ajax({
                type:		'POST',
                url:		paymentFieldsUrl,
                data:		data,
                success:    function(result){
                    last_request[full_url] = result;
                    setContent(last_request[full_url]);
                },
                error:      function(){
                    setContent(paymentMethodData.description, true);
                }
            });
        }else{
            setContent(last_request[full_url], true);
        }
    }

    function setContent(htmlContent, withSleep = false){
        if(withSleep){
            setTimeout(() => {
                setContent(htmlContent);
            }, 50);
        }else{
            jQuery('#div-' + name).html(htmlContent);
        }
    }
})();