(function (global) {
  'use strict';

  function getConfig() {
    return global.KEYCRM_UTM_CONFIG || {};
  }

  function normalizeSourceKey(value) {
    return String(value || '')
      .trim()
      .toLowerCase()
      .replace(/\s+/g, '-');
  }

  function getFieldValue(form, fieldName) {
    if (!fieldName) {
      return '';
    }

    var field = form.querySelector('[name="' + fieldName + '"]');
    return field ? String(field.value || '').trim() : '';
  }

  function resolveSourceId(form, config, utm) {
    if (config.useFormSourceId !== false) {
      var attr = form.getAttribute('data-keycrm-source-id');
      if (attr) {
        var parsed = parseInt(attr, 10);
        if (!isNaN(parsed) && parsed > 0) {
          return parsed;
        }
      }
    }

    var map = config.sourceMap || {};
    var key = normalizeSourceKey(utm.utm_source);
    if (key && map[key]) {
      return map[key];
    }

    return config.sourceId || 0;
  }

  function buildPayload(form, config) {
    var fields = config.fields || {};
    var utm = typeof global.getKeyCrmUtm === 'function' ? global.getKeyCrmUtm() : {};
    var payload = {
      source_id: resolveSourceId(form, config, utm),
      name: getFieldValue(form, fields.name || 'name'),
      phone: getFieldValue(form, fields.phone || 'phone'),
      email: getFieldValue(form, fields.email || 'email'),
      product_sku: getFieldValue(form, fields.productSku || 'product_sku'),
      quantity: getFieldValue(form, fields.quantity || 'quantity'),
      price: getFieldValue(form, fields.price || 'price'),
      utm_source: utm.utm_source || '',
      utm_medium: utm.utm_medium || '',
      utm_campaign: utm.utm_campaign || '',
      utm_term: utm.utm_term || '',
      utm_content: utm.utm_content || ''
    };

    if (config.mode) {
      payload.mode = config.mode;
    }

    return payload;
  }

  function sendToKeyCrm(payload, config) {
    if (!config.endpoint) {
      return Promise.reject(new Error('KEYCRM_UTM_CONFIG.endpoint is not set'));
    }

    return fetch(config.endpoint, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json'
      },
      body: JSON.stringify(payload)
    }).then(function (response) {
      return response.json().catch(function () {
        return { ok: false, error: 'Invalid JSON response' };
      }).then(function (data) {
        if (!response.ok || data.ok === false) {
          var message = data.error || data.message || ('HTTP ' + response.status);
          throw new Error(message);
        }
        return data;
      });
    });
  }

  function bindForm(form, config) {
    if (!form || form.getAttribute('data-keycrm-bound') === '1') {
      return;
    }

    form.setAttribute('data-keycrm-bound', '1');

    form.addEventListener('submit', function (event) {
      if (typeof global.fillFormUtmFields === 'function') {
        global.fillFormUtmFields(form);
      }

      if (form.getAttribute('data-keycrm-native-submit') === '1') {
        return;
      }

      event.preventDefault();

      var submitButtons = form.querySelectorAll('[type="submit"]');
      submitButtons.forEach(function (button) {
        button.disabled = true;
      });

      var payload = buildPayload(form, config);

      sendToKeyCrm(payload, config)
        .then(function (data) {
          if (typeof config.onSuccess === 'function') {
            config.onSuccess(data, form);
          }
        })
        .catch(function (error) {
          if (typeof config.onError === 'function') {
            config.onError(error, form);
          }
        })
        .finally(function () {
          submitButtons.forEach(function (button) {
            button.disabled = false;
          });
        });
    });
  }

  function init() {
    var config = getConfig();
    var selector = config.formSelector || 'form[data-keycrm-form]';
    var forms = global.document.querySelectorAll(selector);

    Array.prototype.forEach.call(forms, function (form) {
      bindForm(form, config);
    });
  }

  if (global.document.readyState === 'loading') {
    global.document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  global.KeyCrmForm = {
    init: init,
    bindForm: bindForm,
    buildPayload: buildPayload,
    sendToKeyCrm: sendToKeyCrm,
    resolveSourceId: resolveSourceId
  };
})(window);
