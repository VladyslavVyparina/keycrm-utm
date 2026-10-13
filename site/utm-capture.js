(function (global) {
  'use strict';

  var UTM_KEYS = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];
  var COOKIE_NAME = 'keycrm_utm';
  var STORAGE_KEY = 'keycrm_utm';
  var MAX_AGE_DAYS = 30;

  function readCookie(name) {
    var match = global.document.cookie.match(
      new RegExp('(?:^|; )' + name.replace(/([.$?*|{}()[\]\\/+^])/g, '\\$1') + '=([^;]*)')
    );
    return match ? decodeURIComponent(match[1]) : null;
  }

  function writeCookie(name, value, days) {
    var maxAge = days * 24 * 60 * 60;
    global.document.cookie =
      name +
      '=' +
      encodeURIComponent(value) +
      '; path=/; max-age=' +
      maxAge +
      '; SameSite=Lax';
  }

  function readStored() {
    var raw = readCookie(COOKIE_NAME);

    if (!raw) {
      try {
        raw = global.localStorage.getItem(STORAGE_KEY);
      } catch (e) {
        raw = null;
      }
    }

    if (!raw) {
      return {};
    }

    try {
      return JSON.parse(raw);
    } catch (e2) {
      return {};
    }
  }

  function writeStored(data) {
    var serialized = JSON.stringify(data);
    writeCookie(COOKIE_NAME, serialized, MAX_AGE_DAYS);

    try {
      global.localStorage.setItem(STORAGE_KEY, serialized);
    } catch (e) {
      // localStorage може бути недоступний — cookie достатньо
    }
  }

  function getQueryParams() {
    var params = {};
    var search = global.location.search;

    if (!search || search.length < 2) {
      return params;
    }

    var query = new global.URLSearchParams(search);
    query.forEach(function (value, key) {
      params[key] = value;
    });

    return params;
  }

  function pickUtm(source) {
    var result = {};

    UTM_KEYS.forEach(function (key) {
      if (source[key] !== undefined && source[key] !== null && String(source[key]).trim() !== '') {
        result[key] = String(source[key]).trim();
      }
    });

    return result;
  }

  function hasUtm(data) {
    return UTM_KEYS.some(function (key) {
      return data[key] !== undefined;
    });
  }

  function mergeUtm(existing, incoming) {
    var merged = Object.assign({}, existing);
    UTM_KEYS.forEach(function (key) {
      if (incoming[key] !== undefined) {
        merged[key] = incoming[key];
      }
    });
    return merged;
  }

  function syncFromUrl() {
    var fromUrl = pickUtm(getQueryParams());
    var stored = readStored();
    var merged = hasUtm(fromUrl) ? mergeUtm(stored, fromUrl) : stored;

    if (hasUtm(merged)) {
      writeStored(merged);
    }

    return merged;
  }

  function getKeyCrmUtm() {
    var fromUrl = pickUtm(getQueryParams());

    if (hasUtm(fromUrl)) {
      var merged = mergeUtm(readStored(), fromUrl);
      writeStored(merged);
      return merged;
    }

    return readStored();
  }

  function fillFormUtmFields(form) {
    if (!form) {
      return;
    }

    var utm = getKeyCrmUtm();

    UTM_KEYS.forEach(function (key) {
      var input = form.querySelector('[name="' + key + '"]');

      if (!input) {
        input = global.document.createElement('input');
        input.type = 'hidden';
        input.name = key;
        form.appendChild(input);
      }

      input.value = utm[key] || '';
    });
  }

  syncFromUrl();

  global.getKeyCrmUtm = getKeyCrmUtm;
  global.fillFormUtmFields = fillFormUtmFields;
  global.KEYCRM_UTM_KEYS = UTM_KEYS.slice();
})(window);
