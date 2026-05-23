(function () {
  'use strict';

  var script = document.currentScript || document.getElementById('provadorVirtualScript');
  if (!script) {
    return;
  }

  var config = readConfig(script);
  var root = null;
  var state = {
    configured: false,
    recommendation: null,
    config: null,
    loading: false,
  };
  var profileStorageKey = 'pv_shopper_profile_v1';

  loadCss(config.cssUrl);
  boot();

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

        if (state.configured) {
          renderTriggers();
        } else if (config.debug) {
          root.innerHTML = '<div class="pv-warning">Provador Virtual indisponivel para este produto.</div>';
        }
      })
      .catch(function () {
        if (config.debug) {
          root.innerHTML = '<div class="pv-warning">Nao foi possivel carregar o Provador Virtual.</div>';
        }
      });
  }

  function readConfig(scriptEl) {
    var src = new URL(scriptEl.src, window.location.href);
    var basePath = src.pathname.replace(/\/widget\/v1\/provador-virtual\.js$/, '');
    var apiBase = scriptEl.dataset.apiBaseUrl || src.origin + basePath + '/api/v1';

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
    return fetch(config.apiBase + path, {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(body),
    }).then(function (response) {
      if (!response.ok) {
        throw new Error('HTTP ' + response.status);
      }

      return response.json();
    });
  }

  function identityPayload() {
    return {
      merchant_id: Number(config.merchantId),
      store_id: config.storeId ? Number(config.storeId) : null,
      product_id: config.productId,
      variant_id: config.variantId,
      sku: config.sku,
      platform: config.platform,
    };
  }

  function configCheck() {
    return request('/public/recommendations/config-check', identityPayload());
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
    discoverButton.addEventListener('click', openRecommendationModal);

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

  function openRecommendationModal() {
    var backdrop = createBackdrop(recommendationModalHtml());

    backdrop.querySelector('[data-pv-close]').addEventListener('click', function () {
      backdrop.remove();
    });

    backdrop.querySelector('[data-pv-submit]').addEventListener('click', function () {
      submitRecommendation(backdrop);
    });
  }

  function openTableModal() {
    var backdrop = createBackdrop(tableModalHtml());

    backdrop.querySelector('[data-pv-close]').addEventListener('click', function () {
      backdrop.remove();
    });
  }

  function createBackdrop(html) {
    var backdrop = document.createElement('div');
    backdrop.className = 'pv-backdrop pv-open';
    backdrop.innerHTML = html;
    root.appendChild(backdrop);
    return backdrop;
  }

  function recommendationModalHtml() {
    var profile = readSavedProfile();
    var note = profile
      ? '<div class="pv-known">Usamos suas medidas salvas neste navegador. Para mudar, edite os campos abaixo.</div>'
      : '<div class="pv-known">Quanto mais dados voce informar, melhor fica a precisao da recomendacao.</div>';

    return [
      '<div class="pv-modal" role="dialog" aria-modal="true" aria-labelledby="pv-title">',
      '<div class="pv-header">',
      '<div><span>Provador Virtual</span><h2 id="pv-title">Descubra seu tamanho</h2><span>Medidas aproximadas em cm.</span></div>',
      '<button class="pv-close" type="button" data-pv-close title="Fechar">x</button>',
      '</div>',
      '<div class="pv-body">',
      '<div class="pv-steps"><span class="active">1 medidas</span><span>2 preferencia</span><span>3 resultado</span></div>',
      note,
      '<div class="pv-grid">',
      field('bust', 'Busto/torax', savedValue(profile, 'bust', 90)),
      field('waist', 'Cintura', savedValue(profile, 'waist', 72)),
      field('hip', 'Quadril', savedValue(profile, 'hip', 98)),
      field('height', 'Altura', savedValue(profile, 'height', 165)),
      field('weight', 'Peso', savedValue(profile, 'weight', 60)),
      '<label>Caimento<select data-pv-input="fit_preference">' + fitOptions(savedValue(profile, 'fit_preference', 'regular')) + '</select></label>',
      '</div>',
      '<div class="pv-actions"><button class="pv-button" type="button" data-pv-submit>Calcular tamanho</button></div>',
      '<div data-pv-output></div>',
      attributionHtml(),
      '</div>',
      '</div>',
    ].join('');
  }

  function field(name, label, value) {
    return '<label>' + label + '<input data-pv-input="' + name + '" type="number" min="1" value="' + value + '" /></label>';
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

  function submitRecommendation(backdrop) {
    var button = backdrop.querySelector('[data-pv-submit]');
    var output = backdrop.querySelector('[data-pv-output]');
    var measurements = {};
    var fitPreference = 'regular';
    var hadSavedProfile = Boolean(readSavedProfile());

    backdrop.querySelectorAll('[data-pv-input]').forEach(function (input) {
      if (input.dataset.pvInput === 'fit_preference') {
        fitPreference = input.value;
        return;
      }

      measurements[input.dataset.pvInput] = Number(input.value);
    });

    saveProfile(Object.assign({}, measurements, { fit_preference: fitPreference }));

    button.disabled = true;
    button.textContent = 'Calculando...';
    output.innerHTML = '';

    request('/public/recommendations', Object.assign(identityPayload(), {
      measurements: measurements,
      shopper_profile: {
        fit_preference: fitPreference,
        known_profile: hadSavedProfile,
      },
    }))
      .then(function (data) {
        state.recommendation = data;
        output.innerHTML = resultHtml(data);
        wireFeedback(output, data);
      })
      .catch(function () {
        output.innerHTML = '<div class="pv-warning">Nao foi possivel recomendar agora. Tente novamente em instantes.</div>';
      })
      .finally(function () {
        button.disabled = false;
        button.textContent = 'Calcular tamanho';
      });
  }

  function resultHtml(data) {
    var notes = (data.fit_notes || []).concat(data.warnings || []).map(function (note) {
      return '<small>' + escapeHtml(note) + '</small>';
    }).join('');

    return [
      '<div class="pv-result">',
      '<span>Tamanho recomendado</span>',
      '<strong>' + escapeHtml(data.recommended_size || '-') + '</strong>',
      '<small>' + Math.round(data.confidence || 0) + '% de confianca</small>',
      notes,
      '<div class="pv-feedback" data-pv-feedback>',
      '<span class="pv-help">Ajudou?</span>',
      '<button type="button" data-pv-helpful="true" title="Ajudou">✓</button>',
      '<button type="button" data-pv-helpful="false" title="Nao ajudou">x</button>',
      '</div>',
      '</div>',
    ].join('');
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
      '<thead><tr><th>Tam.</th><th>Busto/torax</th><th>Cintura</th><th>Quadril</th><th>Altura</th><th>Peso</th></tr></thead>',
      '<tbody>',
      rows.map(tableRowHtml).join('') || '<tr><td colspan="6">Tabela indisponivel para este produto.</td></tr>',
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

  function wireFeedback(output, data) {
    output.querySelectorAll('[data-pv-helpful]').forEach(function (button) {
      button.addEventListener('click', function () {
        request('/public/recommendations/' + data.recommendation_id + '/feedback', {
          was_helpful: button.dataset.pvHelpful === 'true',
          selected_size: data.recommended_size,
        }).finally(function () {
          var holder = output.querySelector('[data-pv-feedback]');
          if (holder) {
            holder.innerHTML = '<span class="pv-help">Feedback registrado.</span>';
          }
        });
      });
    });
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

  function savedValue(profile, key, fallback) {
    if (!profile || profile[key] === undefined || profile[key] === null || profile[key] === '') {
      return fallback;
    }

    return profile[key];
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
