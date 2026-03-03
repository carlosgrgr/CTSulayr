const COOKIE_BANNER_TOP = 'closed-banner-top';
const DESKTOP_BREAKPOINT = 992

document.onreadystatechange = function () {
  if (document.readyState == "complete") {
    jQuery('.menu-item').on('click', function () {
      if (this.classList.contains('menu-item-has-children')) {
        return
      }
      
      jQuery('.collapse').collapse('hide');
    });

    jQuery('.collapse.navbar-collapse').on('show.bs.collapse', function () {
      jQuery('.menu-overlay').addClass('show');
    });
    jQuery('.collapse.navbar-collapse').on('hide.bs.collapse', function () {
      jQuery('.menu-overlay').removeClass('show');
    });

    setHeight();

    window.onresize = setHeight;

    function setHeight() {
      let box = document.querySelector('.js-get-height');
      let list = document.getElementsByClassName('js-list-scroll');

      if (null !== box && list.length > 0) {
        let height = box.offsetHeight;
        if(list[0].offsetHeight > height){
          list[0].classList.add('pe-2');
        }
        list[0].style.height = height + "px";
      }
    }

    const disabledDropdownMobileClick = () => {
      if (window.innerWidth < DESKTOP_BREAKPOINT) {
        return
      }

      const dropdowns = document.querySelectorAll('.dropdown')
      
      dropdowns.forEach((dropdown) => {
        dropdown.addEventListener('mouseover', (event) => {
          for (const item of dropdown.children) {
            item.classList.add('show')
          }
        })

        dropdown.addEventListener('mouseout', (event) => {
          for (const item of dropdown.children) {
            item.classList.remove('show')
          }
        })
      })
    }
    disabledDropdownMobileClick()
  }
}

//TOP NAV BAR
//Navigating to anchor link and opening faq item by ID
const hash = window.location.hash;
document.querySelectorAll('.accordion-collapse').forEach((item) => hash !== `#${item.id}` ? null : document.querySelector(`#${item.id}`).classList.add('show'))

const hideTopNavbar = () => {
  if ( null !== document.querySelector('.top-navbar') ) {
    document.querySelector('.top-navbar').style.display = 'none'
  }
  if ( null !== document.querySelector('#home-navbar') ) {
    document.querySelector('#home-navbar').classList.remove('navbar-top-navbar--show')
  }
  if ( null !== document.querySelector('.container-main') ) {
    document.querySelector('.container-main').classList.remove('container-main-top-navbar')
  }
  
}

const showTopNavbar = () => {
  if ( null !== document.querySelector('.top-navbar') ) {
    document.querySelector('.top-navbar').style.display = 'flex'
  }
  if ( null !== document.querySelector('#home-navbar') ) {
    document.querySelector('#home-navbar').classList.add('navbar-top-navbar--show')
  }
  if ( null !== document.querySelector('.container-main') ) {
    document.querySelector('.container-main').classList.add('container-main-top-navbar')
  }
  
}

//TOP NAV BAR 
//if user dont contribute a top-banner
const topNavbar = document.querySelector('.top-navbar')
var displayBanner = 0;

if(topNavbar !== null){
  displayBanner = topNavbar.attributes['data-display-banner'].value ? topNavbar.attributes['data-display-banner'].value : false
}

if(displayBanner == "1") {

  if (null !== document.body && document.body.contains(topNavbar)) {
    document.querySelector('.container-main').classList.add('container-main-top-navbar')
    document.querySelector('#home-navbar').classList.add('navbar-top-navbar--show')
    //TOP NAV BAR 
    //Closing top banner when clicking the "x" icon
    const buttonClose = document.querySelector('.btn-close')

    buttonClose.addEventListener('click', () => {
      hideTopNavbar()
      const maxAge = typeof wpArgs !== 'undefined' && wpArgs.maxAge ? wpArgs.maxAge : '60'
      document.cookie = `${COOKIE_BANNER_TOP}=; max-age=${maxAge}; SameSite=Lax; path=/`
    })

  } else {
    if ( null !== document.querySelector('.container-main') ) {
      document.querySelector('.container-main').classList.remove('container-main-top-navbar')
    }
    if ( null !== document.querySelector('#home-navbar') ) {
      document.querySelector('#home-navbar').classList.remove('navbar-top-navbar--show')
    }
  }

  const checkCookie = (cookieName) => {
    if ( document.cookie.length <= 0 || (null !== document.body && !document.body.contains(topNavbar) ) ) {
      return
    }

    const cookies = document.cookie.split('; ')

    let isBannerDisplayed = true

    cookies.forEach(cookie => {
      const [key, value] = cookie.split('=')

      if (key === cookieName) {
        isBannerDisplayed = false
        return
      }
    })

    if (!isBannerDisplayed) {
      hideTopNavbar();
    }

    if (isBannerDisplayed) {
      showTopNavbar();
    }
  }

  checkCookie(COOKIE_BANNER_TOP);

  //Handling top navbar when wp adming bar is shown 
  const body = document.body;
  if ( null !== body && body.classList.contains('.logged-in') ) {

    document.querySelector('.top-navbar').classList.add('top-navbar--logged-in')

  }
}

//FEE FREE BANNER
//Adding image to mobile version
const view = window.matchMedia(('screen and (max-width: 480px)'))

const checkMediaQuery = (view) => {
  const picture = document.querySelector('.img-mobile');

  if(picture === null) {
    return 
  }

  if (view.matches) {

    picture.classList.remove('hidden')

  } else {

    picture.classList.add('hidden')

  }


}
view.addEventListener('change', checkMediaQuery)

checkMediaQuery(view)

// START NEWSLETTER
//Newsletter footer validation
let emailField           = document.getElementById("emailField");
let stateField           = document.getElementById("multiselectFooter");
let buttonEmail          = document.getElementById("buttonEmail");
let responseContent      = document.getElementById("responseEmailContent");
let response             = document.getElementById("responseEmail");
let responseState        = document.getElementById("responseState");
let responseValid        = document.getElementById("responseEmailValid");
let responseEmailContent = document.getElementById("responseEmailContent");
let responseStateContent = document.getElementById("responseStateContent");
let responseCheckLegalContent = document.getElementById("responseCheckLegalContent");
let responseCheckLegal = document.getElementById("responseCheckLegal");
// Newsletter Modal Validation 
let emailFieldModal           = document.getElementById("emailFieldModal");
let stateFieldModal           = document.getElementById("multiselectModal");
let buttonEmailModal          = document.getElementById("buttonEmailModal");
let responseEmailModal        = document.getElementById("responseEmailModal");
let responseStateModal        = document.getElementById("responseStateModal");
let responseEmailValidModal   = document.getElementById("responseEmailValidModal");
let responseEmailContentModal = document.getElementById("responseEmailContentModal");
let responseStateContentModal = document.getElementById("responseStateContentModal");
let closeButtonModal          = document.getElementById("closeButtonModal");
let modalWindow               = document.getElementById("modalWindow");
let modalInputButton               = document.getElementById("modalInputButton");
let responseCheckLegalContentModal = document.getElementById("responseCheckLegalContentModal");
let responseCheckLegalModal = document.getElementById("responseCheckLegalModal");
const COOKIE_CLOSE_BUTTON_MODAL = 'closed-newsletter-modal-guest';
const COOKIE_CLOSE_SUBSCRIBE    = 'closed-subscribe';

function initModal() {
    if (!modalWindow) {
      return
    }
    
    if (( -1 !== document.cookie.indexOf( COOKIE_CLOSE_BUTTON_MODAL ) ) || ( -1 !== document.cookie.indexOf( COOKIE_CLOSE_SUBSCRIBE ) ) ){
        modalWindow.style.display = 'none';
    }
}

initModal();

// Script de AJAX para enviar el formulario sin recargar la página
jQuery(document).ready(function($) {
    $('.subscribe-newsletter').submit(function(e) {
        e.preventDefault(); // Evitar el envío normal del formulario

            var formData = $(this).serialize(); // Serializar los datos del formulario

        $.ajax({
            url: wpArgs.ajaxurl, // URL del archivo de AJAX de WordPress
            type: 'POST',
            data: formData,
            dataType: 'json',
            beforeSend: function(xhr) {
                $(".subscribe-newsletter button.link-contact").prop('disabled', true);
            },
            success: function(response) {
                if (response.data.success) {
                    if ( 'footer' == response.data.form ) {
                        $("#responseEmailValid").attr("style", "display:block")
                        $("#validText").text(wpArgs.text_check_inbox +  $("#emailField").val());
                        $("#emailField").val("");
                        $("#modalWindow").hide();
                    } else {
                        $("#responseEmailValid").attr("style", "display:block")
                        $("#responseEmailValidModal").attr("style", "display:block");
                        $("#validTextModal").text(wpArgs.text_check_inbox +  $("#emailFieldModal").val());
                        $("#validText").text(wpArgs.text_check_inbox +  $("#emailFieldModal").val());
                        $("#emailFieldModal").val("");
                    }
                    $(".c-multiselect").hide();
                    $(".emailDiv").hide();
                    $(".form-check").hide();
                    $(".buttonNewsletter").hide();
                    document.cookie = `${COOKIE_CLOSE_SUBSCRIBE}=; max-age=31536000; SameSite=Lax; path=/`
                } else {
                    if ( 'footer' == response.data.form ) {
                        $("#responseEmailValid").hide();
                        $("#responseCheckLegalContent").show();
                        $("#responseCheckLegal").text(wpArgs.text_responseEmail);
                    } else {
                        $("#responseEmailValidModal").hide();
                        $("#responseCheckLegalContentModal").show();
                        $("#responseCheckLegalModal").text(wpArgs.text_responseEmail);
                    }
                }
                $(".subscribe-newsletter button.link-contact").prop('disabled', false);
            },
            error: function(xhr, status, error) {
                if ( 'footer' == response.data.form ) {
                    $("#responseEmailValid").hide();
                    $("#responseCheckLegalContent").show();
                    $("#responseCheckLegal").text(wpArgs.text_responseEmail);
                } else {
                    $("#responseEmailValidModal").hide();
                    $("#responseCheckLegalContentModal").show();
                    $("#responseCheckLegalModal").text(wpArgs.text_responseEmail);
                }
                $(".subscribe-newsletter button.link-contact").prop('disabled', false);
            }
        });
    });
   
    //Newsletter footer validation
    if (buttonEmail != null) {
      buttonEmail.addEventListener("click", function (e) {
          $("#modalWindow").hide();
          e.preventDefault()
          let state = stateField.value;
          if (state == "") {
            responseValid.style.display = 'none';
            responseStateContent.style.display = 'flex';
            responseState.innerHTML = wpArgs.text_insert_state;
          } else {
              responseStateContent.style.display = 'none';
          }

          let elementCheckLegal = document.getElementById('CheckLegalChecked');
          let isLegalChecked;

          if (elementCheckLegal !== undefined && elementCheckLegal !== null) {
            isLegalChecked = elementCheckLegal.checked;

            if (!isLegalChecked) {
              responseValid.style.display = 'none';
              responseCheckLegalContent.style.display = 'flex';
              responseCheckLegal.innerHTML = wpArgs.text_check_legal;
            } else {
              responseCheckLegalContent.style.display = 'none';
            }
          }

          let email = emailField.value;
          if (email == "") {
              responseValid.style.display = 'none';
              responseEmailContent.style.display = 'flex';
              response.innerHTML = wpArgs.text_insert_mail;

          } else {
              if (validateEmail(email)) {
                  //responseValid.style.display = 'flex';
                  responseEmailContent.style.display = 'none';
                  
              } else {
                  responseValid.style.display = 'none';
                  responseEmailContent.style.display = 'flex';
                  response.innerHTML = wpArgs.text_invalid_mail;
              }
          }

          if (elementCheckLegal !== undefined && elementCheckLegal !== null) {
            if (validateEmail(email) & isLegalChecked) {
              jQuery("#footerFormNewsletter").submit();
            }
          }else{
            if (validateEmail(email)) {
              jQuery("#footerFormNewsletter").submit();
            }
          }
          
      });
    }

    // Newsletter Modal Validation
    if (closeButtonModal != null) {
      closeButtonModal.addEventListener("click", function (e) {
          e.preventDefault();
          modalWindow.style.display = 'none';
          document.cookie = `${COOKIE_CLOSE_BUTTON_MODAL}=; max-age=86400; SameSite=Lax; path=/`
      });
    }

    if (buttonEmailModal != null) {
      buttonEmailModal.addEventListener("click", function (e) {
          e.preventDefault()
          let state = stateFieldModal.value;
          if (state == "") {
            responseEmailValidModal.style.display = 'none';
            responseStateContentModal.style.display = 'flex';
            responseStateModal.innerHTML = wpArgs.text_insert_state;
          } else {
            responseStateContentModal.style.display = 'none';
          }

          let elementCheckLegalModal = document.getElementById('CheckLegalCheckedModal');
          let isLegalCheckedModal;

          if (elementCheckLegalModal !== undefined && elementCheckLegalModal !== null) {
            isLegalCheckedModal = elementCheckLegalModal.checked;

            if (!isLegalCheckedModal) {
              responseValid.style.display = 'none';
              responseCheckLegalContentModal.style.display = 'flex';
              responseCheckLegalModal.innerHTML = wpArgs.text_check_legal;
            } else {
              responseCheckLegalContentModal.style.display = 'none';
            }
          }

          let email = emailFieldModal.value;
          if (email == "") {
              responseEmailValidModal.style.display = 'none';
              responseEmailContentModal.style.display = 'flex';
              responseEmailModal.innerHTML = wpArgs.text_insert_mail;
          } else {
              if (validateEmail(email)) {
                  //responseEmailValidModal.style.display = 'flex';
                  responseEmailContentModal.style.display = 'none';
              } else {
                  responseEmailValidModal.style.display = 'none';
                  responseEmailContentModal.style.display = 'flex';
                  responseEmailModal.innerHTML = wpArgs.text_invalid_mail;
              }
          }

          if (elementCheckLegalModal !== undefined && elementCheckLegalModal !== null) {
            if (validateEmail(email) & isLegalCheckedModal) {
              jQuery("#footerFormNewsletterModal").submit();
            }
          }else{
            if (validateEmail(email)) {
              jQuery("#footerFormNewsletterModal").submit();
            }
          }

      });
    }

    function validateEmail(email) {
        let re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
        return re.test(String(email).toLowerCase());
    }

    //Carga de select state
    jQuery(function() {
      jQuery('select[multiple].active.c-multiselect__select').multiselect({
        columns: 1,
        placeholder: wpArgs.text_select_state,
        search: true,
        searchOptions: {
          'default': wpArgs.text_select_search
        },
        selectAll: true
      });
    });
});
// END NEWSLETTER

// COVERED MAP
var showTooltipActive = false;
let stateName = '';
let stateDescription = '';
let mousePosX;
let mousePosY;
let showInfoMapActive = false;

function initMap() {
  const containerInfoMap = document.querySelector('.container-info-map')

  if (!containerInfoMap) {
    return
  }

  let isMobile = detectMob();
  if(!isMobile){
    this.addEventHover();
    return
  }
}
function detectMob() {
  const toMatch = [
      /Android/i,
      /webOS/i,
      /iPhone/i,
      /iPad/i,
      /iPod/i,
      /BlackBerry/i,
      /Windows Phone/i
  ];  
  return toMatch.some((toMatchItem) => {
      return navigator.userAgent.match(toMatchItem);
  });
}

function addEventHover() {
  let activePaths = document.querySelectorAll('.states .state-status-1');
  let tooltipPaths = document.querySelectorAll('.states g');

  activePaths.forEach((activePath) => {
    let idActivePath = activePath.getAttribute("id");
    activePath.addEventListener('click', (event) => {
      showInfoMap(idActivePath, event);
    });
  });
  tooltipPaths.forEach((tooltipPath) => {
    let idTooltipPath = tooltipPath.getAttribute("id");
    tooltipPath.addEventListener('mouseenter', (event) => {
      showTooltip(idTooltipPath, event);
    });
    tooltipPath.addEventListener('mouseleave', (event) => {
      hideTooltip();
    });
  });
}

function showTooltip(idActivePath, event) {
  if (showTooltipActive == false) {
    let stateID = document.querySelector('#'+idActivePath);
    if (stateID == null) {
      return
    }
    let stateName = stateID.getAttribute('data-state');
    let stateDescription = stateID.getAttribute('data-description');
    showTooltipActive = true;
    
    let containerTooltipMap = document.querySelector('#containerTooltipMap');
    containerTooltipMap.querySelector('.tooltip-map__title h4').textContent=stateName;
    containerTooltipMap.querySelector('.tooltip-map__text p').textContent=stateDescription;
    
    containerTooltipMap.classList.remove("d-none");
    containerTooltipMap.classList.add("d-block");
    mousePosY = parseInt(event.pageY) + "px";
    mousePosX = parseInt(event.pageX) + "px";
    containerTooltipMap.style.top = mousePosY;
    containerTooltipMap.style.left = mousePosX;
  }
}

function hideTooltip() {
  if (showTooltipActive == true) {
    showTooltipActive = false;
    let containerTooltipMap = document.querySelector('#containerTooltipMap');
    containerTooltipMap.classList.remove("d-block");
    containerTooltipMap.classList.add("d-none");
  }
}

function showInfoMap(idActivePath, event){
  if (showInfoMapActive == false) {
    document.body.style.cursor = 'wait';
    showInfoMapActive = true;
    let post_id = document.querySelector('#'+idActivePath).getAttribute('data-post_id');
    loadPopupContent( post_id );
  }
}

function hideInfoMap(){
  if (showInfoMapActive == true) {
    showInfoMapActive = false;
    let containerInfoMap = document.querySelector('#containerInfopMap');
    containerInfoMap.classList.remove("info-map-show");
    resetPopupContent(containerInfoMap);
  }
}

function showInfoMapByButton(state){
  if (showInfoMapActive == false) {  
    showInfoMapActive = true;
    let post_id = state.getAttribute('data-post_id');
    loadPopupContent( post_id );
  }
}

initMap();

function isValidImageUrl(url) {
    try {
        const urlObject = new URL(url);
        const currentDomain = window.location.hostname;

        // Verificar que el dominio coincida con el actual o subdominios permitidos
        const allowedDomains = [
            currentDomain,
            `cdn.${currentDomain}`,
            `images.${currentDomain}`,
            `static.${currentDomain}`
        ];

        const isDomainAllowed = allowedDomains.some(domain => 
            urlObject.hostname === domain || urlObject.hostname.endsWith(`.${domain}`)
        );

        if (!isDomainAllowed) {
            console.error('Domain not allowed:', urlObject.hostname);
            return false;
        }

        return true;
    } catch (e) {
        console.error('Invalid URL:', e);
        return false;
    }
}

function loadPopupContent( post_id ) {
  let containerInfoMap = document.querySelector('#containerInfopMap');    
  let action = 'block_coverage_map_popup';
  let xhr = new XMLHttpRequest();
  let url = wpArgs.ajaxurl;
  xhr.open('POST', url, true);
  xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
  xhr.send('action='+ action + '&post_id=' + post_id);
  xhr.onreadystatechange = () => {
    if(xhr.readyState === XMLHttpRequest.DONE) {
      if(xhr.status === 200) {
        let response = JSON.parse(xhr.response);
        containerInfoMap.querySelector('.popup-title').textContent = response.data.title;
        containerInfoMap.querySelector('.subtitle').textContent = response.data.subtitle;
        containerInfoMap.querySelector('.container-info-map-list').innerHTML = response.data.toll_roads; // Si modificamos innerHTML por textContent no muestra el contenido correctamente
        containerInfoMap.querySelector('.p-info').textContent = response.data.more_info;
        containerInfoMap.querySelector('.state-image').setAttribute('src', response.data.image); // Si modificamos no muestra correctamente la imagen
        containerInfoMap.querySelector('.container-popup-info-success').style.display = 'block';
        containerInfoMap.querySelector('.container-popup-info-error').style.display = 'none';

        if (response.data.coverage_status) {
          containerInfoMap.querySelector('.tag').classList.add('state-status-'+response.data.coverage_status.value);
          containerInfoMap.querySelector('.tag').textContent = response.data.coverage_status.label;
        }
      } else {
        containerInfoMap.querySelector('.container-popup-info-success').style.display = 'none';
        containerInfoMap.querySelector('.container-popup-info-error').style.display = 'block';
      }
    }
    containerInfoMap.classList.add("info-map-show");
    document.body.style.cursor = 'pointer';
  };
}

function resetPopupContent(popupNode) {
  popupNode.querySelector('.popup-title').textContent='';
  popupNode.querySelector('.subtitle').textContent='';
  popupNode.querySelector('.container-info-map-list').innerHTML = '';
  popupNode.querySelector('.p-info').textContent='';
  popupNode.querySelector('.state-image').removeAttribute('src');
  popupNode.querySelector('.tag').className = 'tag mt-3';
}

// Testimonials slide
window.onload = function () {
  
    function initializeSplides() {
      document.querySelectorAll('.splide-element').forEach((element, index) => {
          if (!element.classList.contains('is-initialized')) {
  
              const testimonial = element.closest('.c-testamonials');
  
              let autoplay = 'no';
              let autoplayDelay = 3000;
  
              if (testimonial) {
                  autoplay = testimonial.getAttribute('data-autoplay') || 'no';
                  autoplayDelay = parseInt(testimonial.getAttribute('data-autoplay-delay')) || 3000;
              }
  
              let splide = new Splide(element, {
                  type: "loop",
                  focus: "center",
                  autoWidth: true,
                  gap: "40px",
                  arrows: false,
                  breakpoints: {
                      1199: { gap: "24px" },
                      767: { gap: "16px" },
                      480: { gap: "10px" },
                  },
              }).mount();
  
              if (splide) {
                  element.closest('.c-testamonials')?.querySelector('.splide_left .prev')?.addEventListener('click', () => {
                      splide.go('-1');
                  });
  
                  element.closest('.c-testamonials')?.querySelector('.splide_right .next')?.addEventListener('click', () => {
                      splide.go('+1');
                  });
  
                  if (autoplay === 'yes') {
                      if (element.dataset.intervalId) {
                          clearInterval(element.dataset.intervalId);
                      }
  
                      let interval = setInterval(() => {
                          if (document.contains(element)) {
                              splide.go('+1');
                          } else {
                              clearInterval(interval);
                          }
                      }, autoplayDelay);
  
                      element.dataset.intervalId = interval;
                  }
              }
          }
      });
    }
  
    initializeSplides();

    // Toggles header CTA buttons based on scroll position
    let scrollCTAChange = wpArgs.scrollCTAChange || 0;
    let userAgent = navigator.userAgent || navigator.vendor || window.opera;
    if ( !/android/i.test( userAgent ) && !/iPhone|iPad|iPod/i.test( userAgent ) && scrollCTAChange > 0 ) {
      window.addEventListener( 'scroll', () => headerCTAHandler( scrollCTAChange ) );
    }

};

function loadStoreButtons(container){

  jQuery.ajax({
      url: wpArgs.ajaxurl,
      data: {
        action: 'load_store_buttons'
      },
      type: 'POST',
      dataType : 'json',
      beforeSend: function(xhr) {
      },
      success: function(response) {
          
          if(response.result == 'ok'){
            button_apple = response.content.button_apple;
            button_android = response.content.button_android;
            cont = document.querySelector(container);

            let userAgent = navigator.userAgent || navigator.vendor || window.opera;

            if (/android/i.test(userAgent)) {
              cont.insertAdjacentHTML("beforeend", button_android);
            } else if (/iPhone|iPad|iPod/i.test(userAgent)) {
              cont.insertAdjacentHTML("beforeend", button_apple);
            } else {
              cont.insertAdjacentHTML("beforeend", button_apple);
              cont.insertAdjacentHTML("beforeend", button_android);
            }

          }
          
      }
  });
}

function headerCTAHandler( scrollCTAChange ) {
  const scrollTop = window.scrollY;
  const createAccountButton = document.getElementById('createAccountButton');
  const appStoresButtons = document.getElementById('appStoresButtons');

  if (scrollTop > scrollCTAChange) {
    if (createAccountButton !== null) {
      createAccountButton.setAttribute('style', 'display:inline');
    }
    if (appStoresButtons !== null) {
      appStoresButtons.setAttribute('style', 'display:none !important');
    }
  } else {
    if (createAccountButton !== null) {
      createAccountButton.setAttribute('style', 'display:none');
    }
    if (appStoresButtons !== null) {
      appStoresButtons.setAttribute('style', 'display:flex !important');
    }
  }
}

function adjustLayoutForNavbar() {
  const topNavbarContainer = document.querySelector('.container-main');
  const overlay = document.querySelector('.menu-overlay');
  const menuMobile = document.querySelector('.navbar-collapse');
  const navbar = document.querySelector('.navbar');
  const wpAdminBar = document.getElementById('wpadminbar');
  const navbarHeight = navbar ? navbar.offsetHeight : 0;
  const wpAdminBarHeight = wpAdminBar ? wpAdminBar.offsetHeight : 0;
  const totalHeight = navbarHeight + wpAdminBarHeight;

  if (topNavbarContainer) {
    topNavbarContainer.style.paddingTop = `${totalHeight}px`;
  }

  if (overlay && menuMobile) {
    overlay.style.top = `${totalHeight}px`;
    menuMobile.style.top = `${totalHeight}px`;
  }
}

function bindEvents() {
  const closeBtn = document.querySelector('.top-navbar .btn-close');
  if (closeBtn) {
    closeBtn.addEventListener('click', adjustLayoutForNavbar);
  }

  window.addEventListener('resize', adjustLayoutForNavbar);
}

document.addEventListener('DOMContentLoaded', () => {
  adjustLayoutForNavbar();
  bindEvents();
});

// Open Zendesk chat
const openChat = () => {
  if (window.zE) {
    zE('messenger', 'open');
  } else {
    setTimeout(openChat, 200);
  }
}

const chatItem = document.querySelector('li[aria-label="Chat now"]');

if (chatItem) {
  chatItem.addEventListener('click', function (e) {
    e.preventDefault?.();
    openChat();
  });
}
 // ****
document.addEventListener('DOMContentLoaded', function() {
  document.addEventListener('click', function(e) {
    const btn = e.target.closest('.js-open-popup');
    if (!btn) return;
    e.preventDefault();

    const popupId = btn.dataset.popupId;
    if (!popupId) return;

    let popup = document.getElementById('generic-popup');
    if (!popup) {
      popup = document.createElement('div');
      popup.id = 'generic-popup';
      popup.style.position = 'fixed';
      popup.style.top = 0;
      popup.style.left = 0;
      popup.style.width = '100%';
      popup.style.height = '100%';
      popup.style.background = 'rgba(0,0,0,0.6)';
      popup.style.display = 'flex';
      popup.style.justifyContent = 'center';
      popup.style.alignItems = 'flex-start';
      popup.style.overflowY = 'auto';
      popup.style.padding = '2rem'
      popup.style.zIndex = 9999999;
      document.body.appendChild(popup);
    }

    const hiddenContent = document.querySelector('.hidden-cf7-shortcode[data-popup-id="' + popupId + '"]');
    if (!hiddenContent) return;

    const formElement = hiddenContent.querySelector('.wpcf7');
    if (!formElement) return;

    const contentWrapper = document.createElement('div');
    contentWrapper.style.position = 'relative';
    contentWrapper.style.background = '#fff';
    contentWrapper.style.padding = '2rem';
    contentWrapper.style.borderRadius = '8px';
    contentWrapper.style.maxWidth = '500px';
    contentWrapper.style.width = '90%';

    const closeBtn = document.createElement('span');
    closeBtn.classList.add('popup-close');
    closeBtn.textContent = '×';
    contentWrapper.appendChild(closeBtn);

    contentWrapper.appendChild(formElement);

    popup.innerHTML = '';
    popup.appendChild(contentWrapper);
    popup.style.display = 'flex';

    const closePopup = () => {
      popup.style.display = 'none';
      hiddenContent.appendChild(formElement);
      popup.innerHTML = '';
    };

    popup.addEventListener('click', function(ev) {
      if (ev.target === this || ev.target.classList.contains('popup-close')) {
        closePopup();
      }
    });
  });
});

