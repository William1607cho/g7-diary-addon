/*
 * g7-diary-addon — admin settings screen "일기장 설정" (bundle 4, hand-written, no build step).
 *
 * Loaded only by that screen (layout `scripts`, D102) after admin-state.js. It
 * 1. registers the `g7-diary-addon.admin` action handler: every change of the screen goes through
 *    G7DiaryAdminState.reduce() and replaces `_local.g7da` (shallow merge, so removed rows really go);
 * 2. fills `_local.g7da` from the screen data source once it has arrived;
 * 3. loads the admin stylesheet (dist/css/admin.css) once;
 * 4. keeps focus inside the emoji picker and the confirm box, closes them with Esc and gives focus back
 *    to the button that opened them;
 * 5. unsaved changes (D101): a link click that leaves the screen asks in the screen's own confirm box
 *    (no browser confirm/alert); reload or closing the tab gets the browser's own leave prompt.
 */
(function () {
  'use strict';
  if (typeof window === 'undefined' || window.__g7daAdminLoaded) {
    return;
  }
  window.__g7daAdminLoaded = true;

  var ID = 'g7-diary-addon';
  var SOURCE = 'g7daAdmin';
  var ROOT = '[data-g7da="admin"]';
  var CODES = ['emoji_single', 'name_length', 'name_duplicate', 'color_hex', 'categories_required', 'categories_too_many',
    'default_missing', 'default_many', 'weather_too_many', 'range_list', 'range_photo', 'range_title', 'text_length'];

  function core() {
    return window.G7Core;
  }

  function messages() {
    var c = core();
    var out = {};
    CODES.forEach(function (code) {
      var key = ID + '.admin.err.' + code;
      var text = c && typeof c.t === 'function' ? c.t(key) : '';
      out[code] = text && text !== key ? text : code;
    });
    return out;
  }

  function current() {
    var c = core();
    var local = c && c.state && typeof c.state.getLocal === 'function' ? c.state.getLocal() : null;
    return local && local.g7da ? local.g7da : null;
  }

  function put(state) {
    var c = core();
    state.msg = messages();
    c.state.setLocal({ g7da: state }, { merge: 'shallow' });
    if (state.leaveTo) {
      c.dispatch({ handler: 'navigate', params: { path: state.leaveTo, bypassLeaveGuard: true } });
    }
  }

  // 1. One handler for the whole screen; params.op names the change (see admin-state.js OPS).
  function handle(action) {
    var p = (action && action.params) || {};
    var S = window.G7DiaryAdminState;
    if (!S) {
      return;
    }
    if (p.op === 'init') {
      put(S.init(p.data));
      return;
    }
    var state = current();
    if (state) {
      put(S.reduce(state, String(p.op || ''), p));
    }
  }

  function initPlugin() {
    var tries = 0;
    (function attempt() {
      var c = core();
      var d = c && c.getActionDispatcher && c.getActionDispatcher();
      if (d) {
        d.registerHandler(ID + '.admin', handle, { category: 'plugin', source: ID });
      } else if (++tries < 50) {
        window.setTimeout(attempt, 100);
      }
    })();
  }
  window.__G7DiaryAddon = { identifier: ID, initPlugin: initPlugin };

  // 2. Fill from the data source (the screen draws nothing editable until then). A new fetch (the screen was
  // opened again — refetchOnMount) fills again unless something is being edited.
  var filledFrom = null;
  function fillOnce() {
    var c = core();
    if (!document.querySelector(ROOT) || !c || !c.state || !window.G7DiaryAdminState) {
      return;
    }
    var src = typeof c.state.getDataSource === 'function' ? c.state.getDataSource(SOURCE) : null;
    var data = src && (src.data !== undefined ? src.data : src);
    var state = current();
    if (!data || !data.categories || data === filledFrom || (state && state.anyDirty)) {
      return;
    }
    filledFrom = data;
    put(window.G7DiaryAdminState.init(data));
  }

  // 3. The admin stylesheet, only on this screen.
  function loadStyle() {
    if (document.getElementById('g7da-admin-css')) {
      return;
    }
    var c = core();
    var href = c && c.asset && typeof c.asset.plugin === 'function'
      ? c.asset.plugin(ID, 'dist/css/admin.css')
      : '/api/plugins/assets/' + ID + '/dist/css/admin.css';
    var link = document.createElement('link');
    link.id = 'g7da-admin-css';
    link.rel = 'stylesheet';
    link.href = href;
    document.head.appendChild(link);
  }

  // 4. Picker / confirm box focus.
  var layer = { el: null, opener: null };
  function openLayer() {
    return document.querySelector('.g7da-adm-dialog') || document.querySelector('.g7da-adm-picker');
  }
  function focusables(box) {
    return Array.prototype.filter.call(box.querySelectorAll('button, input, select, [tabindex]:not([tabindex="-1"])'), function (el) {
      return !el.disabled && el.offsetParent !== null;
    });
  }
  function watchLayer() {
    var box = openLayer();
    if (box && box !== layer.el) {
      layer.opener = layer.el ? layer.opener : (document.activeElement !== document.body ? document.activeElement : null);
      layer.el = box;
      var first = box.querySelector('[data-g7da-autofocus]') || focusables(box)[0];
      if (first) {
        first.focus();
      }
    } else if (!box && layer.el) {
      layer.el = null;
      var back = layer.opener;
      layer.opener = null;
      if (back && back.isConnected) {
        back.focus();
      }
    }
  }
  function onKey(e) {
    var box = openLayer();
    if (!box) {
      return;
    }
    if (e.key === 'Escape') {
      var close = box.querySelector('[data-g7da-close]');
      if (close) {
        e.preventDefault();
        close.click();
      }
      return;
    }
    if (e.key !== 'Tab') {
      return;
    }
    var items = focusables(box);
    if (items.length === 0) {
      e.preventDefault();
      return;
    }
    var edge = e.shiftKey ? items[0] : items[items.length - 1];
    if (!box.contains(document.activeElement) || document.activeElement === edge) {
      e.preventDefault();
      (e.shiftKey ? items[items.length - 1] : items[0]).focus();
    }
  }

  // 5. Leaving with unsaved changes.
  function leavingPath(e) {
    if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) {
      return null;
    }
    var a = e.target && e.target.closest ? e.target.closest('a[href]') : null;
    if (!a || (a.target && a.target !== '_self') || a.hasAttribute('download')) {
      return null;
    }
    var url = new URL(a.getAttribute('href'), window.location.href);
    if (url.origin !== window.location.origin || url.pathname === window.location.pathname) {
      return null;
    }
    return url.pathname + url.search + url.hash;
  }
  function onClickCapture(e) {
    var state = current();
    if (!state || !state.anyDirty || !document.querySelector(ROOT)) {
      return;
    }
    var path = leavingPath(e);
    if (path === null) {
      return;
    }
    e.preventDefault();
    e.stopPropagation();
    put(window.G7DiaryAdminState.reduce(state, 'leaveAsk', { path: path }));
  }
  function onBeforeUnload(e) {
    var state = current();
    if (state && state.anyDirty && document.querySelector(ROOT)) {
      e.preventDefault();
      e.returnValue = '';
    }
  }

  var pending = false;
  function refresh() {
    if (pending) {
      return;
    }
    pending = true;
    window.requestAnimationFrame(function () {
      pending = false;
      if (!document.querySelector(ROOT)) {
        return;
      }
      loadStyle();
      fillOnce();
      watchLayer();
    });
  }

  function start() {
    initPlugin();
    refresh();
    new MutationObserver(refresh).observe(document.body, { childList: true, subtree: true });
    document.addEventListener('keydown', onKey);
    window.addEventListener('click', onClickCapture, true);
    window.addEventListener('beforeunload', onBeforeUnload);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start);
  } else {
    start();
  }
})();
