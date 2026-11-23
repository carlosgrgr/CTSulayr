window.onload = function () {
    var form = document.getElementById("3dsmethod-form"); 
    var iframe = document.getElementById("3dsmethod-iframe");

    iframe.onload = function () {
        setTimeout(() => {
            redirect(true);
        }, 500);
    };

    form.target = "3dsmethod"; 
    form.submit();
}

setTimeout(() => {
    redirect(false);
}, 10000);

function redirect(threedsmethod){
    var redirectURL = document.getElementById('redirect_url').value;

    if(threedsmethod) {
        
        const url = new URL(redirectURL);
        url.searchParams.set('threeDSCompInd', 'Y');
        
        parent.location.href = url.toString();

    } else {

        parent.location.href = redirectURL;
    }
}