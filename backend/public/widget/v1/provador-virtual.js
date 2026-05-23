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
    loading: false,
  };

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
        if (state.configured) {
          renderButton();
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

  function renderButton() {
    root.innerHTML = '';
    applyTheme(root);

    var button = document.createElement('button');
    button.className = 'pv-trigger';
    button.type = 'button';
    button.innerHTML = '<span aria-hidden="true">PV</span><span>Qual meu tamanho?</span>';
    button.addEventListener('click', openModal);
    root.appendChild(button);
  }

  function applyTheme(element) {
    if (config.theme.primary) {
      element.style.setProperty('--pv-primary', config.theme.primary);
    }

    if (config.theme.accent) {
      element.style.setProperty('--pv-accent', config.theme.accent);
    }
  }

  function openModal() {
    var backdrop = document.createElement('div');
    backdrop.className = 'pv-backdrop pv-open';
    backdrop.innerHTML = modalHtml();
    root.appendChild(backdrop);

    backdrop.querySelector('[data-pv-close]').addEventListener('click', function () {
      backdrop.remove();
    });

    backdrop.querySelector('[data-pv-submit]').addEventListener('click', function () {
      submitRecommendation(backdrop);
    });
  }

  function modalHtml() {
    return [
      '<div class="pv-modal" role="dialog" aria-modal="true" aria-labelledby="pv-title">',
      '<div class="pv-header">',
      '<div><span>Provador Virtual</span><h2 id="pv-title">Descubra seu tamanho</h2><span>Use medidas aproximadas em cm.</span></div>',
      '<button class="pv-close" type="button" data-pv-close title="Fechar">x</button>',
      '</div>',
      '<div class="pv-body">',
      '<div class="pv-grid">',
      field('bust', 'Busto', 90),
      field('waist', 'Cintura', 72),
      field('hip', 'Quadril', 98),
      field('height', 'Altura', 165),
      field('weight', 'Peso', 60),
      '<label>Caimento<select data-pv-input="fit_preference"><option value="regular">Regular</option><option value="tight">Mais justo</option><option value="loose">Mais solto</option></select></label>',
      '</div>',
      '<div class="pv-actions"><button class="pv-button" type="button" data-pv-submit>Calcular tamanho</button></div>',
      '<div data-pv-output></div>',
      '</div>',
      '</div>',
    ].join('');
  }

  function field(name, label, value) {
    return '<label>' + label + '<input data-pv-input="' + name + '" type="number" min="1" value="' + value + '" /></label>';
  }

  function submitRecommendation(backdrop) {
    var button = backdrop.querySelector('[data-pv-submit]');
    var output = backdrop.querySelector('[data-pv-output]');
    var measurements = {};
    var fitPreference = 'regular';

    backdrop.querySelectorAll('[data-pv-input]').forEach(function (input) {
      if (input.dataset.pvInput === 'fit_preference') {
        fitPreference = input.value;
        return;
      }

      measurements[input.dataset.pvInput] = Number(input.value);
    });

    button.disabled = true;
    button.textContent = 'Calculando...';
    output.innerHTML = '';

    request('/public/recommendations', Object.assign(identityPayload(), {
      measurements: measurements,
      shopper_profile: {
        fit_preference: fitPreference,
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

  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }
})();
