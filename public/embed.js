(function () {
  'use strict';

  var script = document.currentScript;
  if (!script || !script.src) return;

  var origin = (typeof GRAVMUD_EVENTZ_EMBED_ORIGIN === 'string' && GRAVMUD_EVENTZ_EMBED_ORIGIN)
    ? GRAVMUD_EVENTZ_EMBED_ORIGIN.replace(/\/$/, '')
    : new URL(script.src).origin;
  var prefix = (typeof GRAVMUD_EVENTZ_EMBED_PREFIX === 'string')
    ? GRAVMUD_EVENTZ_EMBED_PREFIX.replace(/\/$/, '')
    : script.src.replace(/\/embed\.js(?:\?.*)?$/, '').replace(origin, '');

  function assetUrl(name) {
    return origin + prefix + '/assets/' + name;
  }

  function ensureCss() {
    if (document.querySelector('link[data-mud-eventz-css]')) return;
    var link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = assetUrl('mud-eventz.css');
    link.setAttribute('data-mud-eventz-css', '1');
    document.head.appendChild(link);
  }

  function ensureJs(cb) {
    if (window.__mudEventzBooted) {
      cb();
      return;
    }
    if (document.querySelector('script[data-mud-eventz-js]')) {
      document.addEventListener('mud-eventz-ready', cb, { once: true });
      return;
    }
    var s = document.createElement('script');
    s.src = assetUrl('mud-eventz.js');
    s.defer = true;
    s.setAttribute('data-mud-eventz-js', '1');
    s.onload = function () {
      window.__mudEventzBooted = true;
      document.dispatchEvent(new Event('mud-eventz-ready'));
      cb();
    };
    document.head.appendChild(s);
  }

  var iframeMount = document.querySelector('[data-mud-eventz-iframe]');
  if (iframeMount) {
    var mode = iframeMount.getAttribute('data-mode') || 'list';
    var event = iframeMount.getAttribute('data-event') || '';
    var series = iframeMount.getAttribute('data-series') || 'getgrav-global';
    var height = iframeMount.getAttribute('data-height') || '520';
    var src = origin + prefix + '/embed?mode=' + encodeURIComponent(mode);
    if (event) src += '&event=' + encodeURIComponent(event);
    if (series) src += '&series=' + encodeURIComponent(series);
    var frame = document.createElement('iframe');
    frame.src = src;
    frame.title = 'GravMUD Eventz';
    frame.loading = 'lazy';
    frame.setAttribute('style', 'border:0;width:100%;max-width:42rem;height:' + height + 'px;border-radius:12px');
    iframeMount.innerHTML = '';
    iframeMount.appendChild(frame);
    return;
  }

  if (document.querySelector('[data-mud-eventz]')) {
    ensureCss();
    ensureJs(function () {});
  }
})();
