(function () {
  'use strict';

  var script = document.currentScript || document.getElementById('provadorVirtualScript');
  if (!script) {
    return;
  }

  var config = readConfig(script);
  var root = null;
  var activeBackdrop = null;
  var profileStorageKey = 'pv_shopper_profile_v2';
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
      step: 1,
      form: {},
      precision: 0,
      celebrated: false,
      feedback: {
        wasHelpful: null,
        rating: null,
        selectedSize: '',
        comment: '',
        sent: false,
      },
    };
  }

  function boot() {
    var container = resolveContainer(config.containerId);
    if (!container) {
      return;
    }

    root = document.createElement('div');
    root.className = 'pv-widget-root';
    container.appendChild(root);

    configCheck()
      .then(function (result) {
        state.configured = Boolean(result.configured);
        state.config = result;
        config.theme = Object.assign({}, config.theme, result.theme || {});
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
    var container = document.getElementById(containerId);

    if (container) {
      return container;
    }

    var fallback = document.createElement('div');
    fallback.id = containerId;
    script.parentNode.insertBefore(fallback, script);
    return fallback;
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

    try {
      window.dispatchEvent(new CustomEvent('provadorvirtual:config', { detail: detail }));
    } catch (error) {
      var event = document.createEvent('CustomEvent');
      event.initCustomEvent('provadorvirtual:config', false, false, detail);
      window.dispatchEvent(event);
    }
  }

  function configCheck() {
    return request('/public/recommendations/config-check', identityPayload());
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

  function renderTriggers() {
    root.innerHTML = '';
    applyTheme(root);

    var group = document.createElement('div');
    group.className = 'pv-trigger-group';

    var discoverButton = document.createElement('button');
    discoverButton.className = 'pv-trigger pv-trigger-primary';
    discoverButton.type = 'button';
    discoverButton.innerHTML = '<span aria-hidden="true">PV</span><span>Descubra seu tamanho</span>';
    discoverButton.addEventListener('click', openRecommendationDrawer);

    var tableButton = document.createElement('button');
    tableButton.className = 'pv-trigger pv-trigger-secondary';
    tableButton.type = 'button';
    tableButton.innerHTML = '<span aria-hidden="true">cm</span><span>Tabela de Medidas</span>';
    tableButton.addEventListener('click', openTableModal);

    group.appendChild(discoverButton);
    group.appendChild(tableButton);
    root.appendChild(group);
  }

  function applyTheme(element) {
    var theme = config.theme || {};
    var map = {
      primary: '--pv-primary',
      secondary: '--pv-secondary',
      accent: '--pv-accent',
      background: '--pv-bg',
      text: '--pv-text',
      font_family: '--pv-font-family',
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
  }

  function openRecommendationDrawer() {
    state.step = 1;
    state.recommendation = null;
    state.loading = false;
    state.form = formFromSavedProfile();
    state.precision = calculatePrecision(state.form, state.step);
    state.celebrated = false;
    state.feedback = {
      wasHelpful: null,
      rating: null,
      selectedSize: '',
      comment: '',
      sent: false,
    };

    activeBackdrop = createBackdrop('', 'pv-drawer-backdrop');
    activeBackdrop.innerHTML = drawerFrameHtml();
    renderDrawer(activeBackdrop);
  }

  function drawerFrameHtml() {
    return [
      '<section class="pv-drawer" role="dialog" aria-modal="true" aria-labelledby="pv-title">',
      '<header class="pv-drawer-header">',
      '<div>',
      '<span class="pv-kicker">Provador Virtual</span>',
      '<h2 id="pv-title">Descubra seu tamanho</h2>',
      '<p>Uma jornada r&aacute;pida para melhorar a precis&atilde;o da recomenda&ccedil;&atilde;o.</p>',
      '</div>',
      '<button class="pv-close" type="button" data-pv-close title="Fechar">x</button>',
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
    } else if (state.step === 2) {
      html += stepTwoHtml();
    } else if (state.step === 3) {
      html += stepThreeHtml();
    } else {
      html += resultStepHtml();
    }

    content.innerHTML = html;
    updateFooter(backdrop);
    wireDrawer(backdrop);
  }

  function stepperHtml() {
    var steps = [
      [1, 'Medidas'],
      [2, 'Corpo'],
      [3, 'Detalhes'],
      [4, 'Resultado'],
    ];

    return [
      '<nav class="pv-stepper" aria-label="Etapas do provador">',
      steps.map(function (item) {
        var className = item[0] === state.step ? ' class="active"' : '';
        return '<span' + className + '><strong>' + item[0] + '</strong>' + item[1] + '</span>';
      }).join(''),
      '</nav>',
    ].join('');
  }

  function stepOneHtml() {
    var profile = readSavedProfile();
    var note = profile
      ? '<div class="pv-known"><span>Encontramos medidas salvas neste navegador. Voc&ecirc; pode revisar antes de continuar.</span><button type="button" data-pv-clear-profile>Limpar</button></div>'
      : '<div class="pv-known">Com altura e peso j&aacute; conseguimos uma recomenda&ccedil;&atilde;o inicial. As pr&oacute;ximas etapas aumentam a precis&atilde;o.</div>';

    return [
      '<section class="pv-step-panel">',
      '<h3>Suas medidas</h3>',
      '<p>Informe medidas aproximadas em cent&iacute;metros e quilos.</p>',
      note,
      '<div class="pv-grid">',
      numberField('height', 'Altura (cm)', 'Ex: 168', true, tooltipText('height')),
      numberField('weight', 'Peso (kg)', 'Ex: 62', true, tooltipText('weight')),
      numberField('age', 'Idade', 'Opcional', false, tooltipText('age')),
      '</div>',
      '<label class="pv-consent"><input type="checkbox" data-pv-input="consent"' + (state.form.consent === false ? '' : ' checked') + ' />Salvar minhas medidas neste navegador para pr&oacute;ximas recomenda&ccedil;&otilde;es.</label>',
      '<div class="pv-step-actions">',
      '<button type="button" class="pv-button pv-button-secondary" data-pv-next>Aumentar precis&atilde;o</button>',
      '</div>',
      '</section>',
    ].join('');
  }

  function stepTwoHtml() {
    return [
      '<section class="pv-step-panel">',
      '<button type="button" class="pv-back-link" data-pv-back>&larr; Voltar</button>',
      '<h3>G&ecirc;nero e formato do corpo</h3>',
      '<p>Essas informa&ccedil;&otilde;es ajudam a IA a interpretar melhor as faixas da tabela.</p>',
      '<label class="pv-field pv-field-full">G&ecirc;nero<select data-pv-input="gender">' + genderOptions(state.form.gender || '') + '</select></label>',
      '<div class="pv-shape-grid">',
      bodyShapeCards(),
      '</div>',
      '<label class="pv-field pv-field-full">Caimento desejado<select data-pv-input="fit_preference">' + fitOptions(state.form.fit_preference || 'regular') + '</select></label>',
      '<div class="pv-step-actions">',
      '<button type="button" class="pv-button pv-button-secondary" data-pv-next>Aumentar precis&atilde;o</button>',
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
      '<p>Preencha o que souber. Campos vazios ser&atilde;o ignorados.</p>',
      '<div class="pv-grid">',
      fields.map(function (meta) {
        return numberField(meta.key, meta.label, meta.placeholder, false, meta.tooltip);
      }).join(''),
      '</div>',
      '<div class="pv-detail-note">Dica: use uma fita m&eacute;trica sem apertar o corpo. Se estiver em d&uacute;vida, pode pular esta etapa.</div>',
      '<div class="pv-step-actions">',
      '<button type="button" class="pv-link-button" data-pv-recommend>Pular e ver com dados atuais</button>',
      '</div>',
      '</section>',
    ].join('');
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
      '<strong>' + escapeHtml(data.recommended_size || '-') + '</strong>',
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
      '<div class="pv-rating-row" aria-label="Nota da recomendacao">',
      [1, 2, 3, 4, 5].map(function (rating) {
        var active = Number(state.feedback.rating) === rating ? ' active' : '';
        return '<button type="button" class="pv-rating' + active + '" data-pv-rating="' + rating + '">' + rating + '</button>';
      }).join(''),
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
      height: 'Sua altura total, do topo da cabe&ccedil;a at&eacute; o ch&atilde;o.',
      weight: 'Seu peso aproximado em quilos.',
      age: 'Opcional. Ajuda a calibrar recomenda&ccedil;&otilde;es futuras.',
      bust: 'Me&ccedil;a a volta na parte mais cheia do busto ou t&oacute;rax.',
      waist: 'Me&ccedil;a a volta da cintura natural, sem apertar.',
      hip: 'Me&ccedil;a a volta da parte mais larga do quadril.',
      length: 'Comprimento da pe&ccedil;a que costuma vestir bem em voc&ecirc;.',
      shoulder: 'Dist&acirc;ncia entre as extremidades dos ombros.',
    };

    return texts[key] || '';
  }

  function bodyShapeCards() {
    var options = [
      ['straight', 'Retangular', 'Ombros, cintura e quadril parecidos.'],
      ['hourglass', 'Ampulheta', 'Cintura mais marcada.'],
      ['triangle', 'Tri&acirc;ngulo', 'Quadril maior que ombros.'],
      ['inverted_triangle', 'Tri&acirc;ngulo invertido', 'Ombros ou t&oacute;rax maiores.'],
      ['oval', 'Oval', 'Regi&atilde;o central mais arredondada.'],
    ];

    return options.map(function (option) {
      var active = state.form.body_shape === option[0] ? ' active' : '';
      return [
        '<button type="button" class="pv-shape-card' + active + '" data-pv-shape="' + option[0] + '" aria-pressed="' + (active ? 'true' : 'false') + '">',
        '<span class="pv-shape-figure pv-shape-' + option[0] + '"></span>',
        '<strong>' + option[1] + '</strong>',
        '<small>' + option[2] + '</small>',
        '</button>',
      ].join('');
    }).join('');
  }

  function updateFooter(backdrop) {
    state.precision = calculatePrecision(state.form, state.step);

    var footer = backdrop.querySelector('[data-pv-footer]');
    if (!footer) {
      return;
    }

    var disabled = state.loading || !hasStarterMeasures();
    var label = footerButtonLabel();

    if (state.step === 4 && state.recommendation) {
      disabled = true;
      label = 'Seu tamanho &eacute; ' + escapeHtml(state.recommendation.recommended_size || '-');
    }

    footer.innerHTML = [
      precisionHtml(state.precision),
      '<button type="button" class="pv-button pv-main-button" data-pv-footer-action' + (disabled ? ' disabled' : '') + '>' + label + '</button>',
      attributionHtml(),
    ].join('');

    var footerButton = footer.querySelector('[data-pv-footer-action]');
    if (footerButton) {
      footerButton.addEventListener('click', function () {
        collectCurrentInputs(backdrop);
        handleFooterAction(backdrop);
      });
    }

    if (state.step >= 3 && state.precision >= 100 && !state.celebrated) {
      state.celebrated = true;
      triggerCelebration(backdrop);
    }
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

    if (state.step === 1) {
      return 'Continuar para corpo';
    }

    if (state.step === 2) {
      return 'Continuar para detalhes';
    }

    if (state.precision >= 95) {
      return 'Ver recomenda&ccedil;&atilde;o m&aacute;xima';
    }

    if (state.precision >= 60) {
      return 'Ver recomenda&ccedil;&atilde;o precisa';
    }

    return 'Ver recomenda&ccedil;&atilde;o b&aacute;sica';
  }

  function handleFooterAction(backdrop) {
    if (state.step === 1) {
      if (!hasStarterMeasures()) {
        renderDrawer(backdrop, '<div class="pv-warning">Informe pelo menos altura e peso para continuar.</div>');
        focusFirstMissing(backdrop);
        return;
      }

      state.step = 2;
      renderDrawer(backdrop);
      return;
    }

    if (state.step === 2) {
      state.step = 3;
      renderDrawer(backdrop);
      return;
    }

    if (state.step === 3) {
      submitRecommendation(backdrop);
    }
  }

  function wireDrawer(backdrop) {
    if (!backdrop.dataset.pvDelegated) {
      backdrop.dataset.pvDelegated = 'true';
      backdrop.addEventListener('click', function (event) {
        var target = event.target && event.target.nodeType === 1 ? event.target : event.target.parentElement;
        var footerActionButton = target && target.closest ? target.closest('[data-pv-footer-action]') : null;
        var recommendButton = target && target.closest ? target.closest('[data-pv-recommend]') : null;

        if (footerActionButton && backdrop.contains(footerActionButton)) {
          event.preventDefault();
          collectCurrentInputs(backdrop);
          handleFooterAction(backdrop);
          return;
        }

        if (recommendButton && backdrop.contains(recommendButton)) {
          event.preventDefault();
          collectCurrentInputs(backdrop);
          submitRecommendation(backdrop);
        }
      });
    }

    backdrop.querySelectorAll('[data-pv-close]').forEach(function (button) {
      button.addEventListener('click', closeActiveBackdrop);
    });

    backdrop.addEventListener('click', function (event) {
      if (event.target === backdrop) {
        closeActiveBackdrop();
      }
    });

    backdrop.querySelectorAll('[data-pv-input]').forEach(function (input) {
      input.addEventListener('input', function () {
        collectCurrentInputs(backdrop);
        updateFooter(backdrop);
      });
      input.addEventListener('change', function () {
        collectCurrentInputs(backdrop);
        if (input.type === 'checkbox' || input.tagName === 'SELECT') {
          updateFooter(backdrop);
        }
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
        state.step = Math.min(3, state.step + 1);
        renderDrawer(backdrop);
      });
    });

    backdrop.querySelectorAll('[data-pv-shape]').forEach(function (button) {
      button.addEventListener('click', function () {
        collectCurrentInputs(backdrop);
        state.form.body_shape = button.dataset.pvShape;
        renderDrawer(backdrop);
      });
    });

    backdrop.querySelectorAll('.pv-drawer-body [data-pv-recommend]').forEach(function (button) {
      button.addEventListener('click', function () {
        collectCurrentInputs(backdrop);
        submitRecommendation(backdrop);
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

    backdrop.querySelectorAll('[data-pv-rating]').forEach(function (button) {
      button.addEventListener('click', function () {
        state.feedback.rating = Number(button.dataset.pvRating);
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
        rating: state.feedback.rating,
        selected_size: state.feedback.selectedSize || state.recommendation.recommended_size,
        comment: state.feedback.comment || null,
      }).then(function () {
        state.feedback.sent = true;
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

  function submitRecommendation(backdrop) {
    if (state.loading) {
      return;
    }

    if (!hasStarterMeasures()) {
      renderDrawer(backdrop, '<div class="pv-warning">Informe pelo menos altura e peso para calcular seu tamanho.</div>');
      focusFirstMissing(backdrop);
      return;
    }

    state.loading = true;
    updateFooter(backdrop);

    var savedProfile = readSavedProfile();
    var measurements = normalizedMeasurements(state.form);
    var consent = state.form.consent !== false && state.form.consent !== 'false';

    request('/public/recommendations', Object.assign(identityPayload(), {
      measurements: measurements,
      shopper_profile: {
        profile_id: savedProfile && savedProfile.id ? savedProfile.id : null,
        profile_token: savedProfile && savedProfile.token ? savedProfile.token : null,
        consent_measurements: consent,
        fit_preference: state.form.fit_preference || 'regular',
        gender: state.form.gender || null,
        body_shape: state.form.body_shape || null,
        known_profile: Boolean(savedProfile && savedProfile.id),
        raw_widget_data: rawWidgetData(measurements),
      },
    }))
      .then(function (data) {
        state.loading = false;
        state.recommendation = data;
        state.step = 4;
        state.feedback.selectedSize = data.recommended_size || '';

        if (data.shopper_profile && data.shopper_profile.consent) {
          saveProfile(Object.assign({}, measurements, {
            id: data.shopper_profile.id,
            token: data.shopper_profile.token || (savedProfile && savedProfile.token),
            age: valueOrEmpty(state.form.age),
            fit_preference: state.form.fit_preference || 'regular',
            gender: state.form.gender || '',
            body_shape: state.form.body_shape || '',
            quality_score: data.shopper_profile.quality_score,
            outlier_score: data.shopper_profile.outlier_score,
          }));
        } else {
          clearSavedProfile();
        }

        renderDrawer(backdrop);
        if (state.precision >= 100 && !state.celebrated) {
          state.celebrated = true;
          triggerCelebration(backdrop);
        }
      })
      .catch(function () {
        state.loading = false;
        renderDrawer(backdrop, '<div class="pv-warning">N&atilde;o foi poss&iacute;vel recomendar agora. Tente novamente em instantes.</div>');
      });
  }

  function rawWidgetData(measurements) {
    return {
      version: 'v2_sprint_67',
      source: 'widget_v2_staged',
      precision: state.precision,
      steps_completed: completedSteps(),
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

    if (state.step >= 2 || state.form.gender || state.form.body_shape || state.form.fit_preference) {
      steps.push('step_2');
    }

    if (state.step >= 3 || detailedFields().some(function (fieldMeta) {
      return Boolean(valueOrNull(state.form[fieldMeta.key]));
    })) {
      steps.push('step_3');
    }

    if (state.recommendation) {
      steps.push('result');
    }

    return steps;
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
      ['', 'Prefiro n&atilde;o informar'],
      ['female', 'Feminino'],
      ['male', 'Masculino'],
      ['unisex', 'Unissex'],
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

  function closeActiveBackdrop() {
    if (activeBackdrop && activeBackdrop.parentNode) {
      activeBackdrop.parentNode.removeChild(activeBackdrop);
    }

    activeBackdrop = null;
  }

  function tableModalHtml() {
    var table = state.config && state.config.measurement_table ? state.config.measurement_table : null;
    var rows = table && Array.isArray(table.rows) ? table.rows : [];

    return [
      '<div class="pv-modal pv-table-modal" role="dialog" aria-modal="true" aria-labelledby="pv-table-title">',
      '<div class="pv-header">',
      '<div><span>Provador Virtual</span><h2 id="pv-table-title">' + escapeHtml(table ? table.name : 'Tabela de Medidas') + '</h2><span>Medidas em ' + escapeHtml(table ? table.unit : 'cm') + '.</span></div>',
      '<button class="pv-close" type="button" data-pv-close title="Fechar">x</button>',
      '</div>',
      '<div class="pv-body">',
      '<div class="pv-table-wrap">',
      '<table class="pv-size-table">',
      '<thead><tr><th>Tam.</th><th>Busto/t&oacute;rax</th><th>Cintura</th><th>Quadril</th><th>Altura</th><th>Peso</th><th>Compr.</th><th>Ombro</th></tr></thead>',
      '<tbody>',
      rows.map(tableRowHtml).join('') || '<tr><td colspan="8">Tabela indispon&iacute;vel para este produto.</td></tr>',
      '</tbody>',
      '</table>',
      '</div>',
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
    return '<a class="pv-attribution" href="https://provadorvirtual.online/" target="_blank" rel="noopener">desenvolvido por provadorvirtual.online</a>';
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
      var raw = window.localStorage.getItem(profileStorageKey);
      return raw ? JSON.parse(raw) : null;
    } catch (error) {
      return null;
    }
  }

  function saveProfile(profile) {
    try {
      window.localStorage.setItem(profileStorageKey, JSON.stringify(Object.assign({}, profile, {
        updated_at: new Date().toISOString(),
      })));
    } catch (error) {
      // localStorage can be blocked by the browser; recommendation still works.
    }
  }

  function clearSavedProfile() {
    try {
      window.localStorage.removeItem(profileStorageKey);
      window.localStorage.removeItem('pv_shopper_profile_v1');
    } catch (error) {
      // localStorage can be blocked by the browser; recommendation still works.
    }
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
