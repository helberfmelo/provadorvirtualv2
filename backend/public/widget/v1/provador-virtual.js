(function () {
  'use strict';

  var script = document.currentScript || document.getElementById('provadorVirtualScript');
  if (!script) {
    return;
  }

  var config = readConfig(script);
  var root = null;
  var activeBackdrop = null;
  var suppressDrawerOpenUntil = 0;
  var profileStorageKey = 'pv_shopper_profile_v2';
  var profileStoragePrefix = 'pv_shopper_profile_v2_table_';
  var widgetSessionStorageKey = 'pv_widget_session_v1';
  var widgetVisitStorageKey = 'pv_widget_visit_v1';
  var trackedEventCache = {};
  var state = initialState();

  exposePublicApi();
  loadCss(config.cssUrl);
  boot();

  function initialState() {
    return {
      configured: false,
      recommendation: null,
      config: null,
      loading: false,
      recommendationTimer: null,
      recommendationSignature: '',
      dirty: false,
      step: 1,
      form: {},
      precision: 0,
      celebrated: false,
      feedback: {
        wasHelpful: null,
        selectedSize: '',
        comment: '',
        sent: false,
      },
    };
  }

  function boot() {
    var container = resolveContainer(config.containerId);
    if (!mountRoot(container)) {
      return;
    }

    configCheck()
      .then(function (result) {
        state.configured = Boolean(result.configured);
        state.config = result;
        config.theme = Object.assign({}, config.theme, result.theme || {});
        mountRoot(resolveContainer(config.containerId));
        emitConfigEvent(result);

        if (state.configured) {
          renderTriggers();
        } else if (config.debug) {
          root.innerHTML = '<div class="pv-warning">Provador Virtual indispon&iacute;vel para este produto.</div>';
        }
      })
      .catch(function (error) {
        var failure = failurePayload('load_error', error);
        emitConfigEvent(failure);

        if (config.debug) {
          root.innerHTML = debugFailureHtml(failure);
        }
      });
  }

  function readConfig(scriptEl) {
    var src = new URL(scriptEl.src, window.location.href);
    var basePath = src.pathname.replace(/\/widget\/v1\/provador-virtual\.js$/, '');
    var apiBase = scriptEl.dataset.apiBaseUrl || defaultApiBase(src.origin, basePath);

    return {
      apiBase: apiBase.replace(/\/$/, ''),
      cssUrl: scriptEl.dataset.cssUrl || src.origin + basePath + '/widget/v1/provador-virtual.css',
      assetBaseUrl: (scriptEl.dataset.assetBaseUrl || src.origin + basePath + '/widget/v1/assets/body-shapes').replace(/\/$/, ''),
      merchantId: valueFor(scriptEl, 'merchantId', 'lojistaId'),
      storeId: valueFor(scriptEl, 'storeId'),
      productId: valueFor(scriptEl, 'productId'),
      variantId: valueFor(scriptEl, 'variantId'),
      sku: valueFor(scriptEl, 'sku', 'skuGrade', 'produtoIdGrade'),
      platform: valueFor(scriptEl, 'platform') || 'custom',
      containerId: valueFor(scriptEl, 'containerId') || 'provador-virtual-container',
      debug: valueFor(scriptEl, 'debug') === 'true',
      theme: parseTheme(valueFor(scriptEl, 'theme')),
    };
  }

  function defaultApiBase(origin, basePath) {
    if (/\/public$/.test(basePath)) {
      return origin + basePath + '/api/v1';
    }

    if (basePath) {
      return origin + basePath + '/public/api/v1';
    }

    return origin + '/api/v1';
  }

  function exposePublicApi() {
    var publicApi = window.ProvadorVirtual || {};

    publicApi.reload = function (nextConfig) {
      applyConfigToScript(nextConfig || {});
      config = readConfig(script);
      state = initialState();

      if (root && root.parentNode) {
        root.parentNode.removeChild(root);
      }

      root = null;
      activeBackdrop = null;
      loadCss(config.cssUrl);
      boot();
    };

    publicApi.diagnostics = function () {
      return {
        api_base: config.apiBase,
        css_url: config.cssUrl,
        payload: identityPayload(),
        configured: state.configured,
        current_step: state.step,
        precision: state.precision,
        last_config: state.config,
      };
    };

    window.ProvadorVirtual = publicApi;
  }

  function applyConfigToScript(nextConfig) {
    setDatasetValue(nextConfig, 'merchantId', 'merchant_id');
    setDatasetValue(nextConfig, 'storeId', 'store_id');
    setDatasetValue(nextConfig, 'productId', 'product_id');
    setDatasetValue(nextConfig, 'variantId', 'variant_id');
    setDatasetValue(nextConfig, 'sku');
    setDatasetValue(nextConfig, 'platform');
    setDatasetValue(nextConfig, 'containerId', 'container_id');
    setDatasetValue(nextConfig, 'theme');
  }

  function setDatasetValue(source, camelKey, snakeKey) {
    var value = source[camelKey];

    if (value === undefined && snakeKey) {
      value = source[snakeKey];
    }

    if (value === undefined || value === null || value === '') {
      return;
    }

    script.dataset[camelKey] = typeof value === 'object' ? JSON.stringify(value) : String(value);
  }

  function valueFor(scriptEl) {
    for (var i = 1; i < arguments.length; i += 1) {
      var value = scriptEl.dataset[arguments[i]];
      if (value !== undefined && value !== '') {
        return value;
      }
    }

    return null;
  }

  function parseTheme(theme) {
    if (!theme) {
      return {};
    }

    try {
      return JSON.parse(theme);
    } catch (error) {
      return {};
    }
  }

  function resolveContainer(containerId) {
    var placement = placementConfig(containerId);
    var container = document.getElementById(placement.containerId);
    var target = resolvePlacementTarget(placement, container);

    if (container && target && container.contains(target)) {
      return container;
    }

    if (target && target !== container) {
      if (!container) {
        container = document.createElement('div');
        container.id = placement.containerId;
      }

      placeContainer(container, target, placement.mode);
      return container;
    }

    if (container) {
      return container;
    }

    var fallback = document.createElement('div');
    fallback.id = containerId;
    script.parentNode.insertBefore(fallback, script);
    return fallback;
  }

  function mountRoot(container) {
    if (!container) {
      return false;
    }

    container.querySelectorAll('.pv-widget-root[data-pv-root="true"]').forEach(function (node) {
      if (node !== root && node.parentNode) {
        node.parentNode.removeChild(node);
      }
    });

    if (!root) {
      root = document.createElement('div');
      root.className = 'pv-widget-root';
      root.setAttribute('data-pv-root', 'true');
    }

    if (root.parentNode !== container) {
      container.appendChild(root);
    }

    return true;
  }

  function placementConfig(containerId) {
    var theme = config.theme || {};
    var placement = theme.placement && typeof theme.placement === 'object' ? theme.placement : {};
    var mode = String(placement.mode || 'inside').toLowerCase();

    if (['inside', 'after', 'before'].indexOf(mode) < 0) {
      mode = 'inside';
    }

    return {
      mode: mode,
      selector: placement.selector ? String(placement.selector).trim() : '#' + containerId,
      containerId: placement.container_id ? String(placement.container_id).trim() : containerId,
    };
  }

  function resolvePlacementTarget(placement, container) {
    if (!placement.selector) {
      return container;
    }

    try {
      var target = document.querySelector(placement.selector);

      return target || container;
    } catch (error) {
      return container;
    }
  }

  function placeContainer(container, target, mode) {
    if (mode === 'before' && target.parentNode) {
      target.parentNode.insertBefore(container, target);
      return;
    }

    if (mode === 'after' && target.parentNode) {
      target.parentNode.insertBefore(container, target.nextSibling);
      return;
    }

    if (target !== container) {
      target.appendChild(container);
    }
  }

  function loadCss(url) {
    if (document.querySelector('link[data-pv-widget-css="true"]')) {
      return;
    }

    var link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = url;
    link.setAttribute('data-pv-widget-css', 'true');
    document.head.appendChild(link);
  }

  function request(path, body) {
    var url = config.apiBase + path;

    return fetch(url, {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(body),
    }).then(function (response) {
      if (!response.ok) {
        return response.text().then(function (text) {
          var httpError = new Error('HTTP ' + response.status);
          httpError.pvUrl = url;
          httpError.pvStatus = response.status;
          httpError.pvResponseBody = text.slice(0, 500);
          throw httpError;
        });
      }

      return response.json();
    }).catch(function (error) {
      if (!error.pvUrl) {
        error.pvUrl = url;
      }

      throw error;
    });
  }

  function identityPayload() {
    return {
      merchant_id: config.merchantId ? Number(config.merchantId) : null,
      store_id: config.storeId ? Number(config.storeId) : null,
      product_id: config.productId,
      variant_id: config.variantId,
      sku: config.sku,
      platform: config.platform,
    };
  }

  function emitConfigEvent(result) {
    var detail = Object.assign({}, identityPayload(), result || {});

    emitWidgetEvent('provadorvirtual:config', detail);
  }

  function emitWidgetEvent(name, detail) {
    try {
      window.dispatchEvent(new CustomEvent(name, { detail: detail }));
    } catch (error) {
      var event = document.createEvent('CustomEvent');
      event.initCustomEvent(name, false, false, detail);
      window.dispatchEvent(event);
    }
  }

  function configCheck() {
    return request('/public/recommendations/config-check', identityPayload());
  }

  function trackWidgetEvent(eventName, options) {
    options = options || {};

    var eventId = options.eventId || widgetEventId(eventName, options.unique || {});

    if (trackedEventCache[eventId]) {
      return Promise.resolve({ tracked: true, client_event_id: eventId, cached: true });
    }

    trackedEventCache[eventId] = true;

    var payload = Object.assign(identityPayload(), {
      event_name: eventName,
      event_id: eventId,
      recommendation_id: options.recommendationId || null,
      selected_size: options.selectedSize || null,
      session_key: widgetSessionKey(),
      visit_key: widgetVisitKey(),
      occurred_at: new Date().toISOString(),
      payload: eventPayload(options.payload || {}),
    });

    emitWidgetEvent('provadorvirtual:usage', Object.assign({}, payload, { event_name: eventName }));

    return request('/public/widget-events', payload).catch(function (error) {
      delete trackedEventCache[eventId];
      throw error;
    });
  }

  function widgetEventId(eventName, unique) {
    return 'pv-' + eventName + '-' + simpleHash(JSON.stringify({
      event_name: eventName,
      identity: identityPayload(),
      visit_key: widgetVisitKey(),
      unique: unique || {},
    }));
  }

  function widgetSessionKey() {
    return readOrCreateStorageKey('localStorage', widgetSessionStorageKey);
  }

  function widgetVisitKey() {
    return readOrCreateStorageKey('sessionStorage', widgetVisitStorageKey);
  }

  function readOrCreateStorageKey(storageName, key) {
    var storage = safeStorage(storageName);

    if (!storage) {
      return 'pv-fallback-' + simpleHash(key + '-' + window.location.pathname + '-' + window.location.host);
    }

    var current = storage.getItem(key);
    if (current) {
      return current;
    }

    var generated = 'pv-' + simpleHash(key + '-' + Date.now() + '-' + Math.random());
    storage.setItem(key, generated);

    return generated;
  }

  function safeStorage(storageName) {
    try {
      return window[storageName] || null;
    } catch (error) {
      return null;
    }
  }

  function simpleHash(value) {
    var hash = 0;

    for (var index = 0; index < value.length; index += 1) {
      hash = ((hash << 5) - hash) + value.charCodeAt(index);
      hash |= 0;
    }

    return Math.abs(hash).toString(36);
  }

  function eventPayload(payload) {
    if (!payload || typeof payload !== 'object') {
      return {};
    }

    return payload;
  }

  function failurePayload(reason, error) {
    return {
      configured: false,
      reason: reason,
      api_base: config.apiBase,
      request_url: error && error.pvUrl ? error.pvUrl : config.apiBase + '/public/recommendations/config-check',
      error_name: error && error.name ? error.name : null,
      error_message: error && error.message ? String(error.message) : null,
      http_status: error && error.pvStatus ? error.pvStatus : null,
      response_body: error && error.pvResponseBody ? error.pvResponseBody : null,
    };
  }

  function debugFailureHtml(failure) {
    return [
      '<div class="pv-warning">',
      '<strong>N&atilde;o foi poss&iacute;vel carregar o Provador Virtual.</strong>',
      '<pre class="pv-debug">' + escapeHtml(JSON.stringify(failure, null, 2)) + '</pre>',
      '</div>',
    ].join('');
  }

  function buttonIconSvg(themeKey, fallback) {
    var icons = {
      hanger: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 7.5a2.6 2.6 0 1 0-2.55-3.08"/><path d="M12 7.5v3.1"/><path d="M4.2 19.3 12 10.6l7.8 8.7"/><path d="M5.6 19.3h12.8"/></svg>',
      ruler: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3.8 16.2 16.2 3.8l4 4L7.8 20.2z"/><path d="m8 17-1.5-1.5"/><path d="m11 14-1.5-1.5"/><path d="m14 11-1.5-1.5"/><path d="m17 8-1.5-1.5"/></svg>',
      tape: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4.4 10.6a6.2 6.2 0 1 1 11.1 3.8"/><path d="M10.6 10.6a2.2 2.2 0 1 1 4.4 0 2.2 2.2 0 0 1-4.4 0Z"/><path d="M14.8 14.8h4.4c1.2 0 2 .8 2 2v2.4H9.4v-2.4c0-1.2.8-2 2-2h1.2"/><path d="M12.8 18.4v-1.8"/><path d="M16 18.4v-1.8"/><path d="M19.2 18.4v-1.8"/></svg>',
      ruler_combined: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4.5 20V4.5L20 20z"/><path d="M8 13v3h3"/><path d="M4.5 8h3"/><path d="M4.5 11h2"/><path d="M4.5 14h3"/><path d="M4.5 17h2"/></svg>',
      shirt: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 4.5 12 6l4-1.5 4 4-3 3V20H7v-8.5l-3-3z"/><path d="M9.5 5.2c.7 1.5 1.5 2.2 2.5 2.2s1.8-.7 2.5-2.2"/></svg>',
      body: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 6.8a2.4 2.4 0 1 0 0-4.8 2.4 2.4 0 0 0 0 4.8Z"/><path d="M7.2 21.5 9 9.2h6l1.8 12.3"/><path d="M5.3 12.2 9 9.2"/><path d="m15 9.2 3.7 3"/><path d="M9 14h6"/></svg>',
      chart: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16v14H4z"/><path d="M4 10h16"/><path d="M4 15h16"/><path d="M9 5v14"/><path d="M15 5v14"/></svg>',
      size_tag: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4.5 12.2V5h7.2l7.8 7.8-7.2 7.2z"/><path d="M8.2 8.2h.1"/><path d="M10 15.2h4.2"/></svg>',
    };
    var theme = config.theme || {};
    var key = theme[themeKey] ? String(theme[themeKey]).toLowerCase() : fallback;

    return icons[key] || icons[fallback] || icons.hanger;
  }

  function buttonIconAnimationEnabled() {
    var theme = config.theme || {};
    var key = theme.button_primary_icon ? String(theme.button_primary_icon).toLowerCase() : 'hanger';

    return key === 'hanger' && (
      theme.button_icon_animation === undefined
      || theme.button_icon_animation === null
      || theme.button_icon_animation === true
      || theme.button_icon_animation === 'true'
      || theme.button_icon_animation === '1'
    );
  }

  function renderTriggers() {
    root.innerHTML = '';
    applyTheme(root);

    var group = document.createElement('div');
    group.className = 'pv-trigger-group pv-trigger-style-' + buttonStyle() + (buttonIconAnimationEnabled() ? ' pv-trigger-icon-animated' : '');
    var virtualTryOnEnabled = !state.config || state.config.virtual_try_on_enabled !== false;
    var measurementTableEnabled = !state.config || state.config.measurement_table_enabled !== false;

    if (virtualTryOnEnabled) {
      var discoverButton = document.createElement('button');
      discoverButton.className = 'pv-trigger pv-trigger-primary';
      discoverButton.type = 'button';
      discoverButton.innerHTML = '<span class="pv-trigger-icon" aria-hidden="true">' + buttonIconSvg('button_primary_icon', 'hanger') + '</span><span>Descubra seu tamanho</span>';
      discoverButton.addEventListener('click', function (event) {
        if (Date.now() < suppressDrawerOpenUntil) {
          event.preventDefault();
          return;
        }

        openRecommendationDrawer();
      });

      group.appendChild(discoverButton);
    }

    if (measurementTableEnabled) {
      var tableButton = document.createElement('button');
      tableButton.className = 'pv-trigger pv-trigger-secondary';
      tableButton.type = 'button';
      tableButton.innerHTML = '<span class="pv-trigger-icon" aria-hidden="true">' + buttonIconSvg('button_secondary_icon', 'ruler') + '</span><span>Tabela de Medidas</span>';
      tableButton.addEventListener('click', openTableModal);

      group.appendChild(tableButton);
    }

    if (!group.childNodes.length) {
      root.innerHTML = config.debug ? '<div class="pv-warning">Provador Virtual indispon&iacute;vel para este produto.</div>' : '';
      return;
    }

    root.appendChild(group);
    trackWidgetEvent('button_impression', {
      unique: {
        product_id: config.productId || null,
        variant_id: config.variantId || null,
        presentation_mode: presentationMode(),
        virtual_try_on_enabled: virtualTryOnEnabled,
        measurement_table_enabled: measurementTableEnabled,
      },
      payload: {
        product_id: config.productId || null,
        variant_id: config.variantId || null,
        sku: config.sku || null,
        presentation_mode: presentationMode(),
        virtual_try_on_enabled: virtualTryOnEnabled,
        measurement_table_enabled: measurementTableEnabled,
      },
    }).catch(function () {
      // Widget usage tracking cannot block the storefront.
    });
  }

  function applyTheme(element) {
    var theme = config.theme || {};
    var modal = theme.modal || {};
    var map = {
      primary: '--pv-primary',
      secondary: '--pv-secondary',
      accent: '--pv-accent',
      background: '--pv-bg',
      text: '--pv-text',
      font_family: '--pv-font-family',
      button_background: '--pv-button-bg',
      button_text: '--pv-button-text',
    };

    Object.keys(map).forEach(function (key) {
      if (theme[key]) {
        element.style.setProperty(map[key], theme[key]);
      }
    });

    if (theme.font_size) {
      element.style.setProperty('--pv-font-size', Number(theme.font_size) + 'px');
    }

    if (theme.font_weight) {
      element.style.setProperty('--pv-font-weight', String(theme.font_weight));
    }

    if (theme.button_radius !== undefined && theme.button_radius !== null && theme.button_radius !== '') {
      element.style.setProperty('--pv-radius', Number(theme.button_radius) + 'px');
    }

    if (modal.background) {
      element.style.setProperty('--pv-modal-bg', modal.background);
    }

    if (modal.surface) {
      element.style.setProperty('--pv-modal-surface', modal.surface);
    }

    if (modal.text) {
      element.style.setProperty('--pv-modal-text', modal.text);
    }

    if (modal.accent) {
      element.style.setProperty('--pv-modal-accent', modal.accent);
    }

    if (modal.border) {
      element.style.setProperty('--pv-modal-border', modal.border);
    }

    if (modal.font_family) {
      element.style.setProperty('--pv-modal-font-family', modal.font_family);
    }

    if (modal.font_size) {
      element.style.setProperty('--pv-modal-font-size', Number(modal.font_size) + 'px');
    }

    if (modal.font_weight) {
      element.style.setProperty('--pv-modal-font-weight', String(modal.font_weight));
    }

    if (modal.radius !== undefined && modal.radius !== null && modal.radius !== '') {
      element.style.setProperty('--pv-modal-radius', Number(modal.radius) + 'px');
    }
  }

  function buttonStyle() {
    var style = config.theme && config.theme.button_style ? String(config.theme.button_style).toLowerCase() : 'gallery_1_text_icons';
    var allowed = [
      'gallery_1_text_icons',
      'gallery_2_side_icons',
      'gallery_3_dark_outline',
      'gallery_4_underlined_icons',
      'gallery_5_pills',
      'gallery_6_split_line',
      'gallery_7_editorial_links',
      'gallery_8_dotted_stack',
      'gallery_9_light_block',
      'gallery_10_badge_tooltip',
      'gallery_11_icon_chips',
      'gallery_12_dual_cards',
      'gradient',
      'clean',
      'outline',
      'soft',
    ];

    return allowed.indexOf(style) >= 0 ? style : 'gallery_1_text_icons';
  }

  function openRecommendationDrawer() {
    var savedProfile = readSavedProfile();

    var savedStep = savedProfile && savedProfile.max_step ? Number(savedProfile.max_step) : 1;
    state.step = Number.isFinite(savedStep) ? Math.max(1, Math.min(3, savedStep)) : 1;
    state.recommendation = null;
    state.loading = false;
    state.form = formFromSavedProfile();
    if (!canVisitStep(state.step)) {
      state.step = 1;
    }
    state.precision = calculatePrecision(state.form, state.step);
    state.celebrated = false;
    state.dirty = false;
    state.recommendationSignature = '';
    state.feedback = {
      wasHelpful: null,
      selectedSize: '',
      comment: '',
      sent: false,
    };

    activeBackdrop = createBackdrop('', recommendationBackdropClass());
    activeBackdrop.innerHTML = drawerFrameHtml();
    trackWidgetEvent('virtual_try_on_open', {
      unique: {
        product_id: config.productId || null,
        variant_id: config.variantId || null,
        presentation_mode: presentationMode(),
      },
      payload: {
        product_id: config.productId || null,
        variant_id: config.variantId || null,
        sku: config.sku || null,
        presentation_mode: presentationMode(),
        measurement_table_id: state.config ? state.config.measurement_table_id : null,
      },
    }).catch(function () {
      // Widget usage tracking cannot block the storefront.
    });
    renderDrawer(activeBackdrop);
    scheduleAutoRecommendation(activeBackdrop);
  }

  function presentationMode() {
    var mode = config.theme && config.theme.presentation_mode ? String(config.theme.presentation_mode).toLowerCase() : 'drawer';

    return mode === 'modal' ? 'modal' : 'drawer';
  }

  function modalTheme() {
    var theme = config.theme && config.theme.modal ? config.theme.modal : {};

    return {
      logo_text: theme.logo_text || 'Provador Virtual',
      logo_url: theme.logo_url || '',
      kicker: theme.kicker || 'Provador Virtual',
      title: theme.title || 'Descubra seu tamanho',
      subtitle: theme.subtitle || 'Uma jornada rápida para melhorar a precisão da recomendação.',
      step_labels: modalStepLabels(theme.step_labels),
      table_title: theme.table_title || 'Tabela de Medidas',
      table_unit_label: theme.table_unit_label || 'cm',
      footer_note: theme.footer_note || 'Toque no tamanho para aplicar na página do produto.',
      background: theme.background || '#ffffff',
      surface: theme.surface || '#f8fafc',
      text: theme.text || '#111827',
      accent: theme.accent || '#ff4d5e',
      border: theme.border || '#e5e7eb',
      radius: theme.radius !== undefined && theme.radius !== null ? Number(theme.radius) : 16,
      font_family: theme.font_family || 'Manrope, Inter, Arial, sans-serif',
      font_size: theme.font_size !== undefined && theme.font_size !== null ? Number(theme.font_size) : 15,
      font_weight: theme.font_weight !== undefined && theme.font_weight !== null ? Number(theme.font_weight) : 700,
      table_style: ['clean', 'compact', 'cards'].indexOf(String(theme.table_style).toLowerCase()) >= 0
        ? String(theme.table_style).toLowerCase()
        : 'clean',
    };
  }

  function modalStepLabels(stepLabels) {
    var defaults = ['Medidas', 'Corpo', 'Detalhes', 'Resultado'];
    var labels = Array.isArray(stepLabels) ? stepLabels : defaults;

    labels = labels.map(function (label, index) {
      var value = String(label || '').trim();
      return value || defaults[index] || defaults[defaults.length - 1];
    });

    while (labels.length < 4) {
      labels.push(defaults[labels.length] || defaults[defaults.length - 1]);
    }

    return labels.slice(0, 4);
  }

  function modalPreviewBrandHtml() {
    var modal = modalTheme();
    var logoUrl = modal.logo_url ? escapeHtml(modal.logo_url) : '';

    if (logoUrl) {
      return [
        '<span class="pv-modal-brand pv-modal-brand-image">',
        '<img src="' + logoUrl + '" alt="' + escapeHtml(modal.logo_text) + '" loading="lazy" decoding="async" />',
        '<strong>' + escapeHtml(modal.logo_text) + '</strong>',
        '</span>',
      ].join('');
    }

    return '<span class="pv-modal-brand"><strong>' + escapeHtml(modal.logo_text) + '</strong></span>';
  }

  function modalFooterNoteHtml() {
    var footerNote = modalTheme().footer_note;

    return footerNote ? '<p class="pv-modal-footer-note">' + escapeHtml(footerNote) + '</p>' : '';
  }

  function modalTableStyleClass() {
    var classes = {
      clean: 'pv-table-style-clean',
      compact: 'pv-table-style-compact',
      cards: 'pv-table-style-cards',
    };

    return classes[modalTheme().table_style] || classes.clean;
  }

  function modalPreviewTableRows() {
    var table = state.config && state.config.measurement_table ? state.config.measurement_table : null;
    var rows = table && Array.isArray(table.rows) ? table.rows.slice(0, 4) : [];

    if (rows.length === 0) {
      rows = [
        { size_label: 'P', bust: [84, 90], waist: [66, 72], hip: [90, 96], height: [156, 164], weight: [48, 56], length: [null, null], shoulder: [null, null] },
        { size_label: 'M', bust: [90, 96], waist: [72, 78], hip: [96, 104], height: [162, 170], weight: [56, 64], length: [null, null], shoulder: [null, null] },
        { size_label: 'G', bust: [96, 104], waist: [78, 86], hip: [104, 112], height: [168, 176], weight: [64, 74], length: [null, null], shoulder: [null, null] },
        { size_label: 'GG', bust: [104, 112], waist: [86, 94], hip: [112, 120], height: [174, 182], weight: [74, 84], length: [null, null], shoulder: [null, null] },
      ];
    }

    return rows;
  }

  function recommendationBackdropClass() {
    return presentationMode() === 'modal'
      ? 'pv-drawer-backdrop pv-recommendation-modal-backdrop'
      : 'pv-drawer-backdrop';
  }

  function drawerFrameHtml() {
    var frameClass = presentationMode() === 'modal'
      ? 'pv-drawer pv-recommendation-modal'
      : 'pv-drawer';
    var modal = modalTheme();

    return [
      '<section class="' + frameClass + '" role="dialog" aria-modal="true" aria-labelledby="pv-title">',
      '<header class="pv-drawer-header">',
      '<div>',
      modalPreviewBrandHtml(),
      '<span class="pv-kicker">' + escapeHtml(modal.kicker) + '</span>',
      '<h2 id="pv-title">' + escapeHtml(modal.title) + '</h2>',
      '<p>' + escapeHtml(modal.subtitle) + '</p>',
      '</div>',
      '<button class="pv-close" type="button" data-pv-close aria-label="Fechar">x</button>',
      '</header>',
      '<div class="pv-drawer-body" data-pv-content></div>',
      '<footer class="pv-drawer-footer" data-pv-footer></footer>',
      '</section>',
    ].join('');
  }

  function renderDrawer(backdrop, statusHtml) {
    var content = backdrop.querySelector('[data-pv-content]');
    var html = stepperHtml();

    if (statusHtml) {
      html += statusHtml;
    }

    if (state.step === 1) {
      html += stepOneHtml();
      html += browserStorageNoticeHtml();
    } else if (state.step === 2) {
      html += stepTwoHtml();
    } else if (state.step === 3) {
      html += stepThreeHtml();
    } else {
      html += resultStepHtml();
    }

    content.innerHTML = html;
    content.scrollTop = 0;
    updateFooter(backdrop);
    wireDrawer(backdrop);
  }

  function stepperHtml() {
    var labels = modalTheme().step_labels;
    var steps = [
      [1, labels[0]],
      [2, labels[1]],
      [3, labels[2]],
      [4, labels[3]],
    ];

    return [
      '<nav class="pv-stepper" aria-label="Etapas do provador">',
      steps.map(function (item) {
        var active = item[0] === state.step ? ' active' : '';
        var disabled = canVisitStep(item[0]) ? '' : ' disabled';
        return '<button type="button" class="' + active + '" data-pv-step="' + item[0] + '"' + disabled + '><strong>' + item[0] + '</strong>' + escapeHtml(item[1]) + '</button>';
      }).join(''),
      '</nav>',
    ].join('');
  }

  function stepOneHtml() {
    var profile = readSavedProfile();
    var note = profile
      ? '<div class="pv-known"><span>Encontramos medidas salvas neste navegador. Voc&ecirc; pode revisar antes de continuar.</span><button type="button" data-pv-clear-profile>Limpar</button></div>'
      : '';
    var disabled = hasStarterMeasures() ? '' : ' disabled';

    return [
      '<section class="pv-step-panel">',
      '<h3>Suas medidas</h3>',
      note,
      recommendationBannerHtml(),
      '<div class="pv-grid">',
      numberField('height', 'Altura (cm)', 'Ex: 168', true, tooltipText('height')),
      numberField('weight', 'Peso (kg)', 'Ex: 62', true, tooltipText('weight')),
      numberField('age', 'Idade', 'Opcional', false, tooltipText('age')),
      '</div>',
      '<div class="pv-step-actions">',
      '<div><p class="pv-action-hint">Quer uma recomenda&ccedil;&atilde;o ainda mais precisa?</p><button type="button" class="pv-button pv-action-button" data-pv-next' + disabled + '>Aumentar precis&atilde;o</button></div>',
      '</div>',
      '</section>',
    ].join('');
  }

  function browserStorageNoticeHtml() {
    return '<p class="pv-browser-note">Ao usar o Provador Virtual, voc&ecirc; concorda em salvar seus dados neste navegador.</p>';
  }

  function stepTwoHtml() {
    var disabled = hasStepTwoData() ? '' : ' disabled';

    return [
      '<section class="pv-step-panel">',
      '<button type="button" class="pv-back-link" data-pv-back>&larr; Voltar</button>',
      '<h3>G&ecirc;nero e formato do corpo</h3>',
      '<label class="pv-field pv-field-full">G&ecirc;nero<select data-pv-input="gender">' + genderOptions(state.form.gender || '') + '</select></label>',
      stepTipHtml('Selecione a silhueta que melhor descreve seu corpo. Os desenhos mudam conforme o g&ecirc;nero informado.'),
      '<div class="pv-shape-grid">',
      bodyShapeCards(),
      '</div>',
      '<label class="pv-field pv-field-full">Caimento desejado<select data-pv-input="fit_preference">' + fitOptions(state.form.fit_preference || 'regular') + '</select></label>',
      '<div class="pv-step-actions">',
      '<button type="button" class="pv-button pv-action-button" data-pv-next' + disabled + '>Aumentar precis&atilde;o</button>',
      '</div>',
      '</section>',
    ].join('');
  }

  function stepThreeHtml() {
    var fields = detailedFields();

    return [
      '<section class="pv-step-panel">',
      '<button type="button" class="pv-back-link" data-pv-back>&larr; Voltar</button>',
      '<h3>Medidas detalhadas</h3>',
      '<p>Preencha todas as medidas para chegar a 100% de precis&atilde;o.</p>',
      recommendationBannerHtml(),
      '<div class="pv-grid">',
      fields.map(function (meta) {
        return numberField(meta.key, meta.label, meta.placeholder, false, meta.tooltip);
      }).join(''),
      '</div>',
      '<div class="pv-detail-note">Dica: use uma fita m&eacute;trica sem apertar o corpo. Se estiver em d&uacute;vida, mantenha a recomenda&ccedil;&atilde;o parcial do rodap&eacute; e complete depois.</div>',
      '<div class="pv-step-actions">',
      '<button type="button" class="pv-button pv-action-button" data-pv-final' + (hasAllDetailedMeasures() ? '' : ' disabled') + '>Finalizar e ver resultado</button>',
      '</div>',
      '</section>',
    ].join('');
  }

  function stepTipHtml(text) {
    return '<div class="pv-detail-note pv-tip-note">' + text + '</div>';
  }

  function recommendationBannerHtml() {
    if (!hasStarterMeasures()) {
      return '<div class="pv-known pv-compact-note" data-pv-recommendation-banner>Preencha altura e peso para ver o tamanho inicial.</div>';
    }

    if (state.loading && !state.recommendation) {
      return '<div class="pv-known pv-compact-note" data-pv-recommendation-banner>Calculando a sugest&atilde;o inicial. Voc&ecirc; pode continuar para aumentar a precis&atilde;o.</div>';
    }

    if (state.recommendation && state.recommendation.recommended_size) {
      return [
        '<div class="pv-recommendation-inline" data-pv-recommendation-banner>',
        '<span>Recomendamos o tamanho <button type="button" class="pv-inline-size-button" data-pv-select-recommended-size>' + escapeHtml(state.recommendation.recommended_size) + '</button>.</span>',
        state.precision < 100 ? '<small>Continue preenchendo para aumentar a precis&atilde;o.</small>' : '<small>Voc&ecirc; chegou &agrave; precis&atilde;o m&aacute;xima.</small>',
        '</div>',
      ].join('');
    }

    return '<div class="pv-known pv-compact-note" data-pv-recommendation-banner>Dados m&iacute;nimos recebidos. A sugest&atilde;o aparece no rodap&eacute; em instantes.</div>';
  }

  function updateRecommendationBanner(backdrop) {
    backdrop.querySelectorAll('[data-pv-recommendation-banner]').forEach(function (banner) {
      banner.outerHTML = recommendationBannerHtml();
    });
  }

  function resultStepHtml() {
    if (!state.recommendation) {
      return '<div class="pv-warning">Ainda n&atilde;o h&aacute; recomenda&ccedil;&atilde;o calculada.</div>';
    }

    var data = state.recommendation;
    var notes = (data.fit_notes || []).concat(data.warnings || []).map(function (note) {
      return '<li>' + escapeHtml(note) + '</li>';
    }).join('');

    return [
      '<section class="pv-result-step">',
      '<button type="button" class="pv-back-link" data-pv-back>&larr; Ajustar dados</button>',
      '<div class="pv-result-card">',
      '<span>Tamanho recomendado</span>',
      '<button type="button" class="pv-result-size" data-pv-select-recommended-size>' + escapeHtml(data.recommended_size || '-') + '</button>',
      '<small>Toque no tamanho para aplicar na p&aacute;gina do produto.</small>',
      '<small>' + Math.round(data.confidence || 0) + '% de confian&ccedil;a</small>',
      data.shopper_profile && data.shopper_profile.message ? '<small>' + escapeHtml(data.shopper_profile.message) + '</small>' : '',
      notes ? '<ul>' + notes + '</ul>' : '',
      '</div>',
      feedbackHtml(data),
      '</section>',
    ].join('');
  }

  function feedbackHtml(data) {
    if (state.feedback.sent) {
      return '<div class="pv-feedback-card pv-feedback-done">Obrigado! Seu feedback foi registrado e vai ajudar a melhorar o Provador Virtual.</div>';
    }

    return [
      '<div class="pv-feedback-card" data-pv-feedback>',
      '<h3>Essa recomenda&ccedil;&atilde;o ajudou?</h3>',
      '<p>Seu retorno ajuda a melhorar o provador para esta loja e para pr&oacute;ximas compras.</p>',
      '<div class="pv-choice-row">',
      choiceButton(true, 'Sim, ajudou'),
      choiceButton(false, 'N&atilde;o ajudou'),
      '</div>',
      '<label class="pv-field pv-field-full">Tamanho escolhido<select data-pv-feedback-size>' + sizeOptions(data.recommended_size) + '</select></label>',
      '<label class="pv-field pv-field-full">Coment&aacute;rio<textarea data-pv-feedback-comment rows="3" placeholder="Conte se a sugest&atilde;o fez sentido, se ficou grande/pequeno ou o que podemos melhorar.">' + escapeHtml(state.feedback.comment || '') + '</textarea></label>',
      '<div class="pv-step-actions">',
      '<button type="button" class="pv-button" data-pv-send-feedback>Enviar feedback</button>',
      '</div>',
      '<div data-pv-feedback-status></div>',
      '</div>',
    ].join('');
  }

  function choiceButton(value, label) {
    var active = state.feedback.wasHelpful === value ? ' active' : '';
    return '<button type="button" class="pv-choice' + active + '" data-pv-helpful="' + value + '">' + label + '</button>';
  }

  function sizeOptions(selectedSize) {
    var sizes = state.config && Array.isArray(state.config.available_sizes) ? state.config.available_sizes : [];
    var selected = state.feedback.selectedSize || selectedSize || '';

    if (sizes.indexOf(selected) === -1 && selected) {
      sizes = [selected].concat(sizes);
    }

    return sizes.map(function (size) {
      return '<option value="' + escapeHtml(size) + '"' + (String(selected) === String(size) ? ' selected' : '') + '>' + escapeHtml(size) + '</option>';
    }).join('');
  }

  function numberField(name, label, placeholder, required, tooltip) {
    return [
      '<label class="pv-field">',
      '<span>',
      label,
      required ? ' <b>*</b>' : '',
      tooltip ? '<i class="pv-info" tabindex="0" aria-label="' + escapeHtml(tooltip) + '" data-pv-tooltip="' + escapeHtml(tooltip) + '">i</i>' : '',
      '</span>',
      '<input data-pv-input="' + name + '" type="number" inputmode="decimal" min="1" placeholder="' + escapeHtml(placeholder) + '" value="' + escapeHtml(valueOrEmpty(state.form[name])) + '" />',
      '</label>',
    ].join('');
  }

  function detailedFields() {
    var supported = availableMeasurementKeys().filter(function (key) {
      return key !== 'height' && key !== 'weight';
    });

    if (supported.length === 0) {
      supported = ['bust', 'waist', 'hip', 'length', 'shoulder'];
    }

    return supported.map(measureFieldMeta);
  }

  function availableMeasurementKeys() {
    var table = state.config && state.config.measurement_table ? state.config.measurement_table : null;
    var rows = table && Array.isArray(table.rows) ? table.rows : [];
    var keys = ['bust', 'waist', 'hip', 'height', 'weight', 'length', 'shoulder'];

    return keys.filter(function (key) {
      return rows.some(function (row) {
        var range = row[key];
        return Array.isArray(range) && (range[0] !== null || range[1] !== null);
      });
    });
  }

  function measureFieldMeta(key) {
    var gender = state.form.gender || '';
    var labels = {
      bust: gender === 'male' ? 'Peito/T&oacute;rax (cm)' : 'Busto/T&oacute;rax (cm)',
      waist: 'Cintura (cm)',
      hip: 'Quadril (cm)',
      height: 'Altura (cm)',
      weight: 'Peso (kg)',
      length: 'Comprimento da pe&ccedil;a (cm)',
      shoulder: 'Ombro a ombro (cm)',
    };
    var placeholders = {
      bust: 'Ex: 92',
      waist: 'Ex: 74',
      hip: 'Ex: 100',
      height: 'Ex: 168',
      weight: 'Ex: 62',
      length: 'Ex: 95',
      shoulder: 'Ex: 40',
    };

    return {
      key: key,
      label: labels[key] || key,
      placeholder: placeholders[key] || 'Ex: 90',
      tooltip: tooltipText(key),
    };
  }

  function tooltipText(key) {
    var texts = {
      height: 'Altura total, do topo da cabe\u00e7a ao ch\u00e3o.',
      weight: 'Peso aproximado em quilos.',
      age: 'Opcional. Ajuda a calibrar recomenda\u00e7\u00f5es futuras.',
      bust: 'Me\u00e7a a volta na parte mais cheia do busto ou t\u00f3rax.',
      waist: 'Me\u00e7a a volta da cintura natural, sem apertar.',
      hip: 'Me\u00e7a a volta da parte mais larga do quadril.',
      length: 'Comprimento da pe\u00e7a que costuma vestir bem em voc\u00ea.',
      shoulder: 'Dist\u00e2ncia entre as extremidades dos ombros.',
    };

    return texts[key] || '';
  }

  function bodyShapeCards() {
    if (!state.form.gender) {
      return '<div class="pv-empty-shapes">Escolha o g&ecirc;nero para visualizar as silhuetas.</div>';
    }

    var isMale = state.form.gender === 'male';
    var options = isMale
      ? [
        ['straight', 'Retangular', 'Ombros, cintura e quadril parecidos.', 'masc_retangular.png'],
        ['triangle', 'Tri&acirc;ngulo', 'Quadril proporcionalmente maior.', 'masc_triangulo.png'],
        ['inverted_triangle', 'Tri&acirc;ngulo invertido', 'Ombros ou t&oacute;rax maiores.', 'masc_tri_invertido.png'],
        ['oval', 'Oval', 'Regi&atilde;o central mais arredondada.', 'masc_oval.png'],
      ]
      : [
        ['straight', 'Retangular', 'Ombros, cintura e quadril parecidos.', 'retangular.png'],
        ['triangle', 'Tri&acirc;ngulo', 'Quadril maior que ombros.', 'triangulo.png'],
        ['inverted_triangle', 'Tri&acirc;ngulo invertido', 'Ombros ou t&oacute;rax maiores.', 'triangulo_invertido.png'],
        ['oval', 'Oval', 'Regi&atilde;o central mais arredondada.', 'oval.png'],
        ['hourglass', 'Ampulheta', 'Cintura mais marcada.', 'ampulheta.png'],
      ];

    return options.map(function (option) {
      var active = state.form.body_shape === option[0] ? ' active' : '';
      var image = config.assetBaseUrl + '/' + option[3];
      return [
        '<button type="button" class="pv-shape-card pv-shape-card-' + (isMale ? 'male' : 'female') + active + '" data-pv-shape="' + option[0] + '" aria-pressed="' + (active ? 'true' : 'false') + '">',
        '<img class="pv-shape-image" src="' + escapeHtml(image) + '" alt="" loading="eager" decoding="async" aria-hidden="true" />',
        '<strong>' + option[1] + '</strong>',
        '<small>' + option[2] + '</small>',
        '</button>',
      ].join('');
    }).join('');
  }

  function updateFooter(backdrop) {
    state.precision = calculatePrecision(state.form, state.step);

    if (!hasStarterMeasures() && state.recommendation) {
      state.recommendation = null;
      state.recommendationSignature = '';
    }

    var footer = backdrop.querySelector('[data-pv-footer]');
    if (!footer) {
      return;
    }

    var disabled = state.loading || !hasStarterMeasures();
    var label = footerButtonLabel();

    if (state.recommendation && state.recommendation.recommended_size) {
      disabled = false;
      label = 'Usar tamanho ' + escapeHtml(state.recommendation.recommended_size || '-');
    }

    footer.innerHTML = [
      precisionHtml(state.precision),
      '<button type="button" class="' + footerButtonClass() + '" data-pv-footer-action' + (disabled ? ' disabled' : '') + '>' + label + '</button>',
      modalFooterNoteHtml(),
      attributionHtml(),
    ].join('');

    var footerButton = footer.querySelector('[data-pv-footer-action]');
    if (footerButton) {
      footerButton.addEventListener('click', function () {
        var pointerHandledAt = Number(backdrop.dataset.pvPointerHandledAt || 0);
        if (pointerHandledAt && Date.now() - pointerHandledAt < 650) {
          return;
        }

        collectCurrentInputs(backdrop);
        handleFooterAction(backdrop);
      });
    }

    updateStepNavigationState(backdrop);
    maybeCelebrate(backdrop);
  }

  function updateStepNavigationState(backdrop) {
    backdrop.querySelectorAll('[data-pv-step]').forEach(function (button) {
      button.disabled = !canVisitStep(Number(button.dataset.pvStep));
    });

    backdrop.querySelectorAll('[data-pv-next]').forEach(function (button) {
      var target = Math.min(4, state.step + 1);
      button.disabled = !canVisitStep(target);
    });

    backdrop.querySelectorAll('[data-pv-final]').forEach(function (button) {
      button.disabled = !canVisitStep(4);
    });
  }

  function precisionHtml(quality) {
    return [
      '<div class="pv-precision">',
      '<span>N&iacute;vel de precis&atilde;o da IA:</span>',
      '<div><i style="width:' + quality + '%"></i></div>',
      '<strong>' + quality + '%</strong>',
      '</div>',
    ].join('');
  }

  function footerButtonLabel() {
    if (state.loading) {
      return 'Calculando...';
    }

    if (!hasStarterMeasures()) {
      return 'Digite medidas';
    }

    if (!state.recommendation) {
      return 'Calculando tamanho';
    }

    return 'Usar tamanho ' + escapeHtml(state.recommendation.recommended_size || '-');
  }

  function footerButtonClass() {
    var isFinalMax = state.step === 4 && state.precision >= 100 && state.recommendation && state.recommendation.recommended_size;
    return 'pv-button pv-main-button' + (isFinalMax ? '' : ' pv-main-button-subtle');
  }

  function handleFooterAction(backdrop) {
    if (!hasStarterMeasures()) {
      renderDrawer(backdrop, '<div class="pv-warning">Informe pelo menos altura e peso para calcular seu tamanho.</div>');
      focusFirstMissing(backdrop);
      return;
    }

    if (state.recommendation && state.recommendation.recommended_size) {
      selectRecommendedSize(backdrop);
      return;
    }

    refreshRecommendation(backdrop, { reason: 'footer' });
  }

  function selectRecommendedSize(backdrop) {
    if (!state.recommendation || !state.recommendation.recommended_size) {
      return;
    }

    collectCurrentInputs(backdrop);

    var size = String(state.recommendation.recommended_size || '');
    var detail = Object.assign({}, identityPayload(), {
      selected_size: size,
      recommended_size: size,
      confidence: state.recommendation.confidence || null,
      precision: state.precision,
      recommendation: state.recommendation,
    });

    trackWidgetEvent('size_selected', {
      recommendationId: state.recommendation.recommendation_id,
      selectedSize: size,
      unique: {
        recommendation_signature: recommendationSignature(),
        selected_size: size,
      },
      payload: {
        recommendation_id: state.recommendation.recommendation_id,
        recommended_size: state.recommendation.recommended_size || null,
        selected_size: size,
        confidence: state.recommendation.confidence || null,
        precision: state.precision,
      },
    }).catch(function () {
      // Widget usage tracking cannot block the storefront.
    });

    suppressDrawerOpenUntil = Date.now() + 900;
    closeBackdrop(backdrop);
    emitWidgetEvent('provadorvirtual:size-selected', detail);
  }

  function wireDrawer(backdrop) {
    if (!backdrop.dataset.pvPointerDelegated) {
      backdrop.dataset.pvPointerDelegated = 'true';
      backdrop.addEventListener('pointerup', function (event) {
        if (event.pointerType === 'mouse') {
          return;
        }

        var target = event.target && event.target.nodeType === 1 ? event.target : event.target.parentElement;
        var footerActionButton = target && target.closest ? target.closest('[data-pv-footer-action]') : null;
        var selectSizeButton = target && target.closest ? target.closest('[data-pv-select-recommended-size]') : null;

        if (footerActionButton && backdrop.contains(footerActionButton)) {
          backdrop.dataset.pvPointerHandledAt = String(Date.now());
          event.preventDefault();
          collectCurrentInputs(backdrop);
          handleFooterAction(backdrop);
          return;
        }

        if (selectSizeButton && backdrop.contains(selectSizeButton)) {
          backdrop.dataset.pvPointerHandledAt = String(Date.now());
          event.preventDefault();
          selectRecommendedSize(backdrop);
        }
      });
    }

    if (!backdrop.dataset.pvDelegated) {
      backdrop.dataset.pvDelegated = 'true';
      backdrop.addEventListener('click', function (event) {
        var target = event.target && event.target.nodeType === 1 ? event.target : event.target.parentElement;
        var footerActionButton = target && target.closest ? target.closest('[data-pv-footer-action]') : null;
        var recommendButton = target && target.closest ? target.closest('[data-pv-recommend]') : null;
        var stepButton = target && target.closest ? target.closest('[data-pv-step]') : null;
        var finalButton = target && target.closest ? target.closest('[data-pv-final]') : null;
        var selectSizeButton = target && target.closest ? target.closest('[data-pv-select-recommended-size]') : null;
        var pointerHandledAt = Number(backdrop.dataset.pvPointerHandledAt || 0);

        if (pointerHandledAt && Date.now() - pointerHandledAt < 650 && (footerActionButton || selectSizeButton)) {
          event.preventDefault();
          return;
        }

        if (footerActionButton && backdrop.contains(footerActionButton)) {
          event.preventDefault();
          collectCurrentInputs(backdrop);
          handleFooterAction(backdrop);
          return;
        }

        if (stepButton && backdrop.contains(stepButton)) {
          event.preventDefault();
          collectCurrentInputs(backdrop);
          goToStep(backdrop, Number(stepButton.dataset.pvStep));
          return;
        }

        if (finalButton && backdrop.contains(finalButton)) {
          event.preventDefault();
          collectCurrentInputs(backdrop);
          goToStep(backdrop, 4);
          return;
        }

        if (selectSizeButton && backdrop.contains(selectSizeButton)) {
          event.preventDefault();
          selectRecommendedSize(backdrop);
          return;
        }

        if (recommendButton && backdrop.contains(recommendButton)) {
          event.preventDefault();
          collectCurrentInputs(backdrop);
          refreshRecommendation(backdrop, { reason: 'body' });
        }
      });
    }

    backdrop.querySelectorAll('[data-pv-close]').forEach(function (button) {
      button.addEventListener('click', function () {
        closeBackdrop(backdrop);
      });
    });

    backdrop.addEventListener('click', function (event) {
      if (event.target === backdrop) {
        closeBackdrop(backdrop);
      }
    });

    backdrop.querySelectorAll('[data-pv-input]').forEach(function (input) {
      input.addEventListener('input', function () {
        collectCurrentInputs(backdrop);
        state.dirty = true;
        updateFooter(backdrop);
        scheduleAutoRecommendation(backdrop);
      });
      input.addEventListener('change', function () {
        collectCurrentInputs(backdrop);
        state.dirty = true;
        if (input.dataset.pvInput === 'gender') {
          state.form.body_shape = '';
          renderDrawer(backdrop);
          scheduleAutoRecommendation(backdrop);
          return;
        }

        updateFooter(backdrop);
        scheduleAutoRecommendation(backdrop);
      });
    });

    var backButton = backdrop.querySelector('[data-pv-back]');
    if (backButton) {
      backButton.addEventListener('click', function () {
        collectCurrentInputs(backdrop);
        state.step = Math.max(1, state.step - 1);
        renderDrawer(backdrop);
      });
    }

    backdrop.querySelectorAll('[data-pv-next]').forEach(function (button) {
      button.addEventListener('click', function () {
        collectCurrentInputs(backdrop);
        goToStep(backdrop, Math.min(4, state.step + 1));
      });
    });

    backdrop.querySelectorAll('[data-pv-shape]').forEach(function (button) {
      button.addEventListener('click', function () {
        collectCurrentInputs(backdrop);
        state.form.body_shape = button.dataset.pvShape;
        state.dirty = true;
        renderDrawer(backdrop);
        scheduleAutoRecommendation(backdrop);
      });
    });

    backdrop.querySelectorAll('.pv-drawer-body [data-pv-recommend]').forEach(function (button) {
      button.addEventListener('click', function () {
        collectCurrentInputs(backdrop);
        refreshRecommendation(backdrop, { reason: 'body' });
      });
    });

    var clearProfile = backdrop.querySelector('[data-pv-clear-profile]');
    if (clearProfile) {
      clearProfile.addEventListener('click', function () {
        forgetSavedProfile();
        state.form = formFromSavedProfile();
        state.precision = calculatePrecision(state.form, state.step);
        renderDrawer(backdrop);
      });
    }

    wireFeedback(backdrop);
  }

  function wireFeedback(backdrop) {
    backdrop.querySelectorAll('[data-pv-helpful]').forEach(function (button) {
      button.addEventListener('click', function () {
        state.feedback.wasHelpful = button.dataset.pvHelpful === 'true';
        collectFeedback(backdrop);
        renderDrawer(backdrop);
      });
    });

    var sendButton = backdrop.querySelector('[data-pv-send-feedback]');
    if (!sendButton || !state.recommendation) {
      return;
    }

    sendButton.addEventListener('click', function () {
      collectFeedback(backdrop);
      sendButton.disabled = true;
      sendButton.textContent = 'Enviando...';
      setFeedbackStatus(backdrop, 'Salvando seu feedback...', false);

      request('/public/recommendations/' + state.recommendation.recommendation_id + '/feedback', {
        was_helpful: state.feedback.wasHelpful,
        selected_size: state.feedback.selectedSize || state.recommendation.recommended_size,
        comment: state.feedback.comment || null,
      }).then(function () {
        state.feedback.sent = true;
        trackWidgetEvent('feedback_submitted', {
          recommendationId: state.recommendation.recommendation_id,
          selectedSize: state.feedback.selectedSize || state.recommendation.recommended_size,
          unique: {
            recommendation_id: state.recommendation.recommendation_id,
          },
          payload: {
            recommendation_id: state.recommendation.recommendation_id,
            was_helpful: state.feedback.wasHelpful,
            selected_size: state.feedback.selectedSize || state.recommendation.recommended_size,
            has_comment: Boolean(state.feedback.comment),
          },
        }).catch(function () {
          // Widget usage tracking cannot block the storefront.
        });
        renderDrawer(backdrop);
      }).catch(function () {
        sendButton.disabled = false;
        sendButton.textContent = 'Enviar feedback';
        setFeedbackStatus(backdrop, 'N&atilde;o foi poss&iacute;vel salvar agora. Tente novamente em instantes.', true);
      });
    });
  }

  function setFeedbackStatus(backdrop, message, error) {
    var status = backdrop.querySelector('[data-pv-feedback-status]');
    if (status) {
      status.innerHTML = '<div class="' + (error ? 'pv-warning' : 'pv-known') + '">' + message + '</div>';
    }
  }

  function collectCurrentInputs(backdrop) {
    backdrop.querySelectorAll('[data-pv-input]').forEach(function (input) {
      var key = input.dataset.pvInput;
      state.form[key] = input.type === 'checkbox' ? input.checked : input.value;
    });
  }

  function collectFeedback(backdrop) {
    var size = backdrop.querySelector('[data-pv-feedback-size]');
    var comment = backdrop.querySelector('[data-pv-feedback-comment]');

    if (size) {
      state.feedback.selectedSize = size.value;
    }

    if (comment) {
      state.feedback.comment = comment.value;
    }
  }

  function scheduleAutoRecommendation(backdrop) {
    if (!backdrop || !hasStarterMeasures()) {
      if (state.recommendationTimer) {
        window.clearTimeout(state.recommendationTimer);
      }
      return;
    }

    if (state.recommendationTimer) {
      window.clearTimeout(state.recommendationTimer);
    }

    state.recommendationTimer = window.setTimeout(function () {
      refreshRecommendation(backdrop, { reason: 'auto' });
    }, 500);
  }

  function submitRecommendation(backdrop) {
    refreshRecommendation(backdrop, { showResult: true, reason: 'result' });
  }

  function refreshRecommendation(backdrop, options) {
    options = options || {};

    if (state.loading) {
      return;
    }

    if (!hasStarterMeasures()) {
      renderDrawer(backdrop, '<div class="pv-warning">Informe pelo menos altura e peso para calcular seu tamanho.</div>');
      focusFirstMissing(backdrop);
      return;
    }

    var signature = recommendationSignature();
    if (!options.showResult && state.recommendation && state.recommendationSignature === signature) {
      updateFooter(backdrop);
      return;
    }

    state.loading = true;
    updateFooter(backdrop);
    updateRecommendationBanner(backdrop);

    var savedProfile = readSavedProfile();
    var measurements = normalizedMeasurements(state.form);
    var consent = state.form.consent !== false && state.form.consent !== 'false';

    request('/public/recommendations', recommendationPayload(measurements, savedProfile, consent, options.reason || 'manual'))
      .then(function (data) {
        state.loading = false;
        state.recommendation = data;
        state.recommendationSignature = signature;
        state.dirty = false;
        state.feedback.selectedSize = data.recommended_size || '';

        trackWidgetEvent('recommendation_generated', {
          recommendationId: data.recommendation_id,
          unique: {
            recommendation_signature: signature,
            recommended_size: data.recommended_size || null,
          },
          payload: {
            recommendation_id: data.recommendation_id,
            recommended_size: data.recommended_size || null,
            confidence: data.confidence || null,
            precision: state.precision,
            reason: options.reason || 'manual',
            steps_completed: completedSteps(),
            measurement_table_id: state.config ? state.config.measurement_table_id : null,
          },
        }).catch(function () {
          // Widget usage tracking cannot block the storefront.
        });

        persistProfileFromRecommendation(data, savedProfile, measurements);

        if (options.showResult && canVisitStep(4)) {
          state.step = 4;
        }

        if (options.showResult && state.step === 4) {
          renderDrawer(backdrop);
        } else {
          updateFooter(backdrop);
          updateRecommendationBanner(backdrop);
        }
        maybeCelebrate(backdrop);
      })
      .catch(function () {
        state.loading = false;
        updateFooter(backdrop);
        if (options.reason !== 'auto') {
          renderDrawer(backdrop, '<div class="pv-warning">N&atilde;o foi poss&iacute;vel recomendar agora. Tente novamente em instantes.</div>');
        }
      });
  }

  function recommendationPayload(measurements, savedProfile, consent, reason) {
    return Object.assign(identityPayload(), {
      measurements: measurements,
      shopper_profile: {
        profile_id: savedProfile && savedProfile.id ? savedProfile.id : null,
        profile_token: savedProfile && savedProfile.token ? savedProfile.token : null,
        consent_measurements: consent,
        fit_preference: state.form.fit_preference || 'regular',
        gender: state.form.gender || null,
        body_shape: state.form.body_shape || null,
        known_profile: Boolean(savedProfile && savedProfile.id),
        raw_widget_data: rawWidgetData(measurements, reason),
      },
    });
  }

  function persistProfileFromRecommendation(data, savedProfile, measurements) {
    if (data.shopper_profile && data.shopper_profile.consent) {
      saveProfile(Object.assign({}, measurements, {
        id: data.shopper_profile.id,
        token: data.shopper_profile.token || (savedProfile && savedProfile.token),
        age: valueOrEmpty(state.form.age),
        fit_preference: state.form.fit_preference || 'regular',
        gender: state.form.gender || '',
        body_shape: state.form.body_shape || '',
        max_step: maxCompletedStep(),
        measurement_table_id: state.config ? state.config.measurement_table_id : null,
        quality_score: data.shopper_profile.quality_score,
        outlier_score: data.shopper_profile.outlier_score,
      }));
    } else {
      clearSavedProfile();
    }
  }

  function persistJourneyOnClose() {
    if (!hasStarterMeasures()) {
      return;
    }

    if (state.form.consent !== false && state.form.consent !== 'false') {
      saveProfile(Object.assign({}, normalizedMeasurements(state.form), {
        age: valueOrEmpty(state.form.age),
        fit_preference: state.form.fit_preference || 'regular',
        gender: state.form.gender || '',
        body_shape: state.form.body_shape || '',
        max_step: maxCompletedStep(),
        measurement_table_id: state.config ? state.config.measurement_table_id : null,
      }));
    }

    if (!state.recommendation || !state.dirty || state.loading || state.recommendationSignature === recommendationSignature()) {
      return;
    }

    var savedProfile = readSavedProfile();
    var measurements = normalizedMeasurements(state.form);
    var consent = state.form.consent !== false && state.form.consent !== 'false';

    request('/public/recommendations', recommendationPayload(measurements, savedProfile, consent, 'close'))
      .then(function (data) {
        state.recommendation = data;
        state.recommendationSignature = recommendationSignature();
        state.dirty = false;
        persistProfileFromRecommendation(data, savedProfile, measurements);
      })
      .catch(function () {
        // Closing should not block the shopper; a previous recommendation remains logged.
      });
  }

  function recommendationSignature() {
    return JSON.stringify({
      identity: identityPayload(),
      measurements: normalizedMeasurements(state.form),
      age: valueOrEmpty(state.form.age),
      gender: state.form.gender || '',
      body_shape: state.form.body_shape || '',
      fit_preference: state.form.fit_preference || 'regular',
      step: state.step,
      table: state.config ? state.config.measurement_table_id : null,
    });
  }

  function rawWidgetData(measurements, reason) {
    return {
      version: 'v2_sprint_68',
      source: 'widget_v2_staged',
      event: reason || 'manual',
      precision: state.precision,
      steps_completed: completedSteps(),
      max_step: maxCompletedStep(),
      identity: identityPayload(),
      measurements: measurements,
      raw_measurements: {
        altura: valueOrNull(state.form.height),
        peso: valueOrNull(state.form.weight),
        idade: valueOrNull(state.form.age),
        genero: state.form.gender || null,
        formato_corpo: state.form.body_shape || null,
        caimento: state.form.fit_preference || null,
        busto_cm: valueOrNull(state.form.bust),
        cintura_cm: valueOrNull(state.form.waist),
        quadril_cm: valueOrNull(state.form.hip),
        comprimento_cm: valueOrNull(state.form.length),
        ombro_a_ombro_cm: valueOrNull(state.form.shoulder),
      },
      detailed_fields: detailedFields().map(function (fieldMeta) {
        return fieldMeta.key;
      }),
      measurement_table_id: state.config ? state.config.measurement_table_id : null,
    };
  }

  function completedSteps() {
    var steps = ['step_1'];

    if (state.step >= 2 || hasStepTwoData()) {
      steps.push('step_2');
    }

    if (state.step >= 3 || hasAnyDetailedMeasure()) {
      steps.push('step_3');
    }

    if (state.recommendation) {
      steps.push('result');
    }

    return steps;
  }

  function maxCompletedStep() {
    if (hasAllDetailedMeasures()) {
      return 3;
    }

    if (hasStepTwoData()) {
      return 2;
    }

    if (hasStarterMeasures()) {
      return 1;
    }

    return 0;
  }

  function canVisitStep(step) {
    if (step <= 1) {
      return true;
    }

    if (step === 2) {
      return hasStarterMeasures();
    }

    if (step === 3) {
      return hasStarterMeasures() && hasStepTwoData();
    }

    return hasStarterMeasures() && hasStepTwoData() && hasAllDetailedMeasures();
  }

  function goToStep(backdrop, step) {
    if (!canVisitStep(step)) {
      var message = step === 2
        ? 'Informe altura e peso para avan&ccedil;ar.'
        : step === 3
          ? 'Informe g&ecirc;nero e formato do corpo para avan&ccedil;ar.'
          : 'Preencha todas as medidas detalhadas para ver o resultado final.';
      renderDrawer(backdrop, '<div class="pv-warning">' + message + '</div>');
      if (step === 2) {
        focusFirstMissing(backdrop);
      }
      return;
    }

    if (step === 4) {
      refreshRecommendation(backdrop, { showResult: true, reason: 'result' });
      return;
    }

    state.step = step;
    renderDrawer(backdrop);
    scheduleAutoRecommendation(backdrop);
  }

  function normalizedMeasurements(form) {
    var map = {
      bust: form.bust,
      waist: form.waist,
      hip: form.hip,
      height: form.height,
      weight: form.weight,
      length: form.length,
      shoulder: form.shoulder,
    };
    var normalized = {};

    Object.keys(map).forEach(function (key) {
      var number = numberValue(map[key]);
      if (number !== null) {
        normalized[key] = number;
      }
    });

    return normalized;
  }

  function calculatePrecision(form, maxStep) {
    var score = 0;
    var detailFields = detailedFields();
    var visibleStep = maxStep || 4;

    if (numberValue(form.height) !== null) {
      score += 20;
    }

    if (numberValue(form.weight) !== null) {
      score += 20;
    }

    if (numberValue(form.age) !== null) {
      score += 5;
    }

    if (visibleStep < 2) {
      return Math.max(0, Math.min(45, score));
    }

    if (form.gender) {
      score += 10;
    }

    if (form.body_shape) {
      score += 10;
    }

    if (visibleStep < 3) {
      return Math.max(0, Math.min(65, score));
    }

    if (hasAllDetailedMeasures()) {
      return 100;
    }

    if (detailFields.length > 0) {
      var filledDetails = detailFields.filter(function (fieldMeta) {
        return numberValue(form[fieldMeta.key]) !== null;
      }).length;
      score += Math.round((filledDetails / detailFields.length) * 35);
    }

    return Math.max(0, Math.min(100, score));
  }

  function hasStarterMeasures() {
    return numberValue(state.form.height) !== null && numberValue(state.form.weight) !== null;
  }

  function hasStepTwoData() {
    return Boolean(state.form.gender && state.form.body_shape);
  }

  function hasAnyDetailedMeasure() {
    return detailedFields().some(function (fieldMeta) {
      return numberValue(state.form[fieldMeta.key]) !== null;
    });
  }

  function hasAllDetailedMeasures() {
    var fields = detailedFields();

    if (fields.length === 0) {
      return hasStarterMeasures() && hasStepTwoData();
    }

    return hasStarterMeasures() && hasStepTwoData() && fields.every(function (fieldMeta) {
      return numberValue(state.form[fieldMeta.key]) !== null;
    });
  }

  function focusFirstMissing(backdrop) {
    var field = numberValue(state.form.height) === null ? 'height' : 'weight';
    var input = backdrop.querySelector('[data-pv-input="' + field + '"]');
    if (input) {
      input.focus();
    }
  }

  function formFromSavedProfile() {
    var profile = readSavedProfile();

    return {
      height: savedValue(profile, 'height', ''),
      weight: savedValue(profile, 'weight', ''),
      age: savedValue(profile, 'age', ''),
      bust: savedValue(profile, 'bust', ''),
      waist: savedValue(profile, 'waist', ''),
      hip: savedValue(profile, 'hip', ''),
      length: savedValue(profile, 'length', ''),
      shoulder: savedValue(profile, 'shoulder', ''),
      gender: savedValue(profile, 'gender', ''),
      body_shape: savedValue(profile, 'body_shape', ''),
      fit_preference: savedValue(profile, 'fit_preference', 'regular'),
      consent: true,
    };
  }

  function genderOptions(selected) {
    var options = [
      ['', 'Selecione...'],
      ['female', 'Feminino'],
      ['male', 'Masculino'],
    ];

    return options.map(function (option) {
      return '<option value="' + option[0] + '"' + (selected === option[0] ? ' selected' : '') + '>' + option[1] + '</option>';
    }).join('');
  }

  function fitOptions(selected) {
    var options = [
      ['regular', 'Regular'],
      ['tight', 'Mais justo'],
      ['loose', 'Mais solto'],
    ];

    return options.map(function (option) {
      return '<option value="' + option[0] + '"' + (selected === option[0] ? ' selected' : '') + '>' + option[1] + '</option>';
    }).join('');
  }

  function openTableModal() {
    var backdrop = createBackdrop(tableModalHtml(), 'pv-modal-backdrop');

    trackWidgetEvent('measurement_table_open', {
      unique: {
        product_id: config.productId || null,
        variant_id: config.variantId || null,
        measurement_table_id: state.config ? state.config.measurement_table_id : null,
      },
      payload: {
        product_id: config.productId || null,
        variant_id: config.variantId || null,
        sku: config.sku || null,
        measurement_table_id: state.config ? state.config.measurement_table_id : null,
      },
    }).catch(function () {
      // Widget usage tracking cannot block the storefront.
    });

    backdrop.querySelector('[data-pv-close]').addEventListener('click', function () {
      backdrop.remove();
    });

    backdrop.addEventListener('click', function (event) {
      if (event.target === backdrop) {
        backdrop.remove();
      }
    });
  }

  function createBackdrop(html, className) {
    var backdrop = document.createElement('div');
    backdrop.className = 'pv-backdrop pv-open ' + (className || '');
    backdrop.innerHTML = html;
    root.appendChild(backdrop);
    return backdrop;
  }

  function closeBackdrop(backdrop) {
    var targetBackdrop = backdrop || activeBackdrop;

    if (targetBackdrop && targetBackdrop.parentNode) {
      collectCurrentInputs(targetBackdrop);
      persistJourneyOnClose();
      targetBackdrop.parentNode.removeChild(targetBackdrop);
    }

    if (!backdrop || backdrop === activeBackdrop || (activeBackdrop && !activeBackdrop.parentNode)) {
      activeBackdrop = null;
    }
  }

  function tableModalHtml() {
    var table = state.config && state.config.measurement_table ? state.config.measurement_table : null;
    var rows = table && Array.isArray(table.rows) ? table.rows : [];
    var modal = modalTheme();
    var rowTemplate = modalPreviewTableRows();

    return [
      '<div class="pv-modal pv-table-modal ' + modalTableStyleClass() + '" role="dialog" aria-modal="true" aria-labelledby="pv-table-title">',
      '<div class="pv-header">',
      '<div>',
      modalPreviewBrandHtml(),
      '<span>' + escapeHtml(modal.table_title) + '</span>',
      '<h2 id="pv-table-title">' + escapeHtml(table ? table.name : modal.table_title) + '</h2>',
      '<span>Medidas em ' + escapeHtml(table ? table.unit : modal.table_unit_label) + '.</span>',
      '</div>',
      '<button class="pv-close" type="button" data-pv-close title="Fechar">x</button>',
      '</div>',
      '<div class="pv-body">',
      '<div class="pv-table-wrap">',
      '<table class="pv-size-table">',
      '<thead><tr><th>Tam.</th><th>Busto/t&oacute;rax</th><th>Cintura</th><th>Quadril</th><th>Altura</th><th>Peso</th><th>Compr.</th><th>Ombro</th></tr></thead>',
      '<tbody>',
      rows.map(tableRowHtml).join('') || rowTemplate.map(tableRowHtml).join('') || '<tr><td colspan="8">Tabela indispon&iacute;vel para este produto.</td></tr>',
      '</tbody>',
      '</table>',
      '</div>',
      modalFooterNoteHtml(),
      attributionHtml(),
      '</div>',
      '</div>',
    ].join('');
  }

  function tableRowHtml(row) {
    return [
      '<tr>',
      '<td><strong>' + escapeHtml(row.size_label || '-') + '</strong></td>',
      '<td>' + rangeText(row.bust) + '</td>',
      '<td>' + rangeText(row.waist) + '</td>',
      '<td>' + rangeText(row.hip) + '</td>',
      '<td>' + rangeText(row.height) + '</td>',
      '<td>' + rangeText(row.weight) + '</td>',
      '<td>' + rangeText(row.length) + '</td>',
      '<td>' + rangeText(row.shoulder) + '</td>',
      '</tr>',
    ].join('');
  }

  function rangeText(value) {
    if (!Array.isArray(value) || (value[0] === null && value[1] === null)) {
      return '-';
    }

    return escapeHtml([value[0], value[1]].filter(function (item) {
      return item !== null && item !== undefined && item !== '';
    }).join(' - '));
  }

  function attributionHtml() {
    return [
      '<div class="pv-attribution">',
      '<a href="https://provadorvirtual.online/" target="_blank" rel="noopener">desenvolvido por provadorvirtual.online</a>',
      '<span>&copy; 2026</span>',
      '<a href="https://provadorvirtual.online/privacidade" target="_blank" rel="noopener">Privacidade</a>',
      '<a href="https://provadorvirtual.online/termos" target="_blank" rel="noopener">Termos</a>',
      '</div>',
    ].join('');
  }

  function maybeCelebrate(backdrop) {
    if (state.step === 4 && state.precision >= 100 && !state.celebrated && confettiEnabled()) {
      state.celebrated = true;
      triggerCelebration(backdrop);
    }
  }

  function confettiEnabled() {
    var setting = config.theme ? config.theme.confetti_enabled : undefined;

    return setting === undefined || setting === null || setting === '' || setting === true || setting === 'true' || setting === '1' || setting === 1;
  }

  function triggerCelebration(backdrop) {
    var layer = document.createElement('div');
    var colors = ['#ff4d5e', '#ff7a1a', '#0f172a', '#22c55e', '#38bdf8'];

    layer.className = 'pv-confetti-layer';

    for (var i = 0; i < 42; i += 1) {
      var piece = document.createElement('i');
      piece.style.left = Math.round(Math.random() * 100) + '%';
      piece.style.background = colors[i % colors.length];
      piece.style.animationDelay = (Math.random() * 0.35).toFixed(2) + 's';
      piece.style.transform = 'rotate(' + Math.round(Math.random() * 180) + 'deg)';
      layer.appendChild(piece);
    }

    backdrop.appendChild(layer);

    window.setTimeout(function () {
      if (layer.parentNode) {
        layer.parentNode.removeChild(layer);
      }
    }, 2200);
  }

  function readSavedProfile() {
    try {
      var scopedRaw = window.localStorage.getItem(scopedProfileStorageKey());
      if (scopedRaw) {
        return JSON.parse(scopedRaw);
      }

      var raw = window.localStorage.getItem(profileStorageKey);
      return raw ? JSON.parse(raw) : null;
    } catch (error) {
      return null;
    }
  }

  function saveProfile(profile) {
    try {
      var payload = JSON.stringify(Object.assign({}, profile, {
        updated_at: new Date().toISOString(),
      }));

      window.localStorage.setItem(scopedProfileStorageKey(), payload);
      window.localStorage.setItem(profileStorageKey, payload);
    } catch (error) {
      // localStorage can be blocked by the browser; recommendation still works.
    }
  }

  function clearSavedProfile() {
    try {
      window.localStorage.removeItem(scopedProfileStorageKey());
      window.localStorage.removeItem(profileStorageKey);
      window.localStorage.removeItem('pv_shopper_profile_v1');
    } catch (error) {
      // localStorage can be blocked by the browser; recommendation still works.
    }
  }

  function scopedProfileStorageKey() {
    var tableId = state.config ? state.config.measurement_table_id : null;

    return tableId ? profileStoragePrefix + tableId : profileStorageKey;
  }

  function forgetSavedProfile() {
    var profile = readSavedProfile();
    clearSavedProfile();

    if (!profile || !profile.id || !profile.token) {
      return;
    }

    request('/public/shopper-profiles/forget', Object.assign(identityPayload(), {
      profile_id: profile.id,
      profile_token: profile.token,
    })).catch(function () {
      // The local profile was already removed; server cleanup can retry in a future interaction.
    });
  }

  function savedValue(profile, key, fallback) {
    if (!profile || profile[key] === undefined || profile[key] === null || profile[key] === '') {
      return fallback;
    }

    return profile[key];
  }

  function valueOrEmpty(value) {
    return value === undefined || value === null ? '' : value;
  }

  function valueOrNull(value) {
    var number = numberValue(value);
    return number === null ? null : number;
  }

  function numberValue(value) {
    if (value === undefined || value === null || value === '') {
      return null;
    }

    var number = Number(String(value).replace(',', '.'));
    return Number.isFinite(number) && number > 0 ? number : null;
  }

  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }
})();
