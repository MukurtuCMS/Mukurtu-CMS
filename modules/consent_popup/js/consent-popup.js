(($, Drupal) => {
  Drupal.behaviors.consent_popup = {
    attach(context, settings) {
      function getCookie(cName) {
        const name = `${cName}=`;
        const cDecoded = decodeURIComponent(document.cookie);
        const cArr = cDecoded.split('; ');
        let res;
        cArr.forEach((val) => {
          if (val.indexOf(name) === 0) {
            res = val.substring(name.length);
          }
        });
        return res;
      }

      const popupOptions = drupalSettings.consent_popup;
      const cookieName = popupOptions.cookie_name;
      if (getCookie(cookieName) !== 'true') {
        const d = new Date();
        const days = popupOptions.cookie_life;
        const doc = document.documentElement;
        const toBlur = popupOptions.to_blur;
        const redirect = popupOptions.redirect;
        const declineText = popupOptions.text_decline;
        const nonBlocking = popupOptions.non_blocking;
        const redirectUrl = popupOptions.redirect_url;
        for (let i = 0; i < toBlur.length; i++) {
          const element = $(toBlur[i]);
          if (element.length) {
            element.addClass('blurred-element');
          }
        }
        doc.style.setProperty(
          '--consent-popup-bg-color',
          popupOptions.bg_color,
        );
        d.setTime(d.getTime() + days * 24 * 60 * 60 * 1000);
        const expiration = d.toUTCString();
        $('body').addClass('consent-popup-opened');
        $('#javali-popup .accept', context).on('click', function () {
          $('body').removeClass('consent-popup-opened');
          document.cookie = `${cookieName}=true; expires=${expiration}; path=/`;
        });
        $('#javali-popup .decline', context).on('click', function () {
          const cookieValue = nonBlocking ? 'true' : 'false';
          if (nonBlocking) {
            document.cookie = `${cookieName}=${cookieValue}; expires=${expiration}; path=/`;
          }
          if (redirect) {
            // make sure the cookie is set
            setTimeout(function () {
              window.location.replace(redirectUrl);
            }, 500);
          } else {
            $('#javali-popup .consent-text').html(`<h2>${declineText}</h2>`);
            $('#javali-popup .consent-buttons').remove();
            $('#javali-popup a').removeClass('visually-hidden');
            document.cookie = `${cookieName}=${cookieValue}; expires=${expiration}; path=/`;
          }
        });
      }
    },
  };
})(jQuery, Drupal);
