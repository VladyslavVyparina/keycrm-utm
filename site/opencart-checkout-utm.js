(function (global) {
  'use strict';

  var CHECKOUT_FORM_SELECTOR = '#checkout-form, form[action*="checkout/confirm"], form[action*="checkout/order"]';

  function ensureHidden(form, name, value) {
    var input = form.querySelector('input[name="' + name + '"]');
    if (!input) {
      input = global.document.createElement('input');
      input.type = 'hidden';
      input.name = name;
      form.appendChild(input);
    }
    input.value = value || '';
  }

  function syncCheckoutForms() {
    if (typeof global.fillFormUtmFields !== 'function' || typeof global.getKeyCrmUtm !== 'function') {
      return;
    }

    var utm = global.getKeyCrmUtm();
    var forms = global.document.querySelectorAll(CHECKOUT_FORM_SELECTOR);

    Array.prototype.forEach.call(forms, function (form) {
      global.fillFormUtmFields(form);
      ensureHidden(form, 'utm_source', utm.utm_source || '');
      ensureHidden(form, 'utm_medium', utm.utm_medium || '');
      ensureHidden(form, 'utm_campaign', utm.utm_campaign || '');
      ensureHidden(form, 'utm_term', utm.utm_term || '');
      ensureHidden(form, 'utm_content', utm.utm_content || '');
    });
  }

  if (global.document.readyState === 'loading') {
    global.document.addEventListener('DOMContentLoaded', syncCheckoutForms);
  } else {
    syncCheckoutForms();
  }

  global.document.addEventListener('click', function (event) {
    var target = event.target;
    if (!target) {
      return;
    }
    if (target.closest && target.closest('[href*="checkout"], [data-toggle="checkout"], button')) {
      syncCheckoutForms();
    }
  }, true);
})(window);
