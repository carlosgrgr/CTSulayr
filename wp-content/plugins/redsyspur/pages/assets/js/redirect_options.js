maskedCard = document.getElementById('maskedCard').value;

if (!maskedCard) {
    document.getElementById('div-options').remove();
} else {
    document.getElementById('div-noref').remove();
}

const inputs = document.querySelectorAll('.change-parameters');
inputs.forEach(function (input) {
    input.addEventListener('change', function () {
        onChangeParameters();
    });
});

onChangeParameters();

function onChangeParameters() {
    withRef = document.redsys_payment_form.redsys_payment ? document.redsys_payment_form.redsys_payment.value == "ref" : false;
    saveRef = document.redsys_payment_form.redsys_payment ? !withRef : document.redsys_payment_form.check_guardar.checked;

    var data = {
        withRef: withRef,
        saveRef: saveRef,
    };

    jQuery.ajax({
        type: "POST",
        url: document.getElementById('sessionVarsUrl').value,
        data: data,
    });
}