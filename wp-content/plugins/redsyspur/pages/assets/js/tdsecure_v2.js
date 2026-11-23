window.onload = function () {
    document.getElementById("redsys_iframe_acs").onload = function() {
        document.getElementById("redsysAcsForm").style.display="none";
        document.getElementById("redsys_iframe_acs").style.display="inline";
    }
    document.redsysAcsForm.submit();
}