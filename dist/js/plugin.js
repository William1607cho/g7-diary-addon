/*
 * g7-diary-addon — small helpers for the diary list (hand-written, no build step).
 *
 * 1. Infinite scroll: the "load more" button of a diary list (data-g7da-more="auto") is clicked when
 *    it comes into view. After a failed load the layout switches it to "manual" and it stays a button.
 * 2. Body fade: a card body block whose preview text is taller than the preview box gets `g7da-overflow`
 *    (the server flag `is-more-text` covers text it cut itself; photo +N is a server class too).
 * 3. Card title tooltip: set only while the title is cut, and not on devices without hover.
 * 4. 지난 오늘, narrow screens: the icon (data-g7da-memory-go) picks one post through the API and opens it;
 *    the post just shown is kept in session storage per board and left out of the next pick (works without it).
 * 5. 지난 오늘 popup (wide): focus moves into the dialog and stays there (Tab), Esc closes it, and focus goes
 *    back to the icon that opened it.
 * The infinite-scroll helper also serves the photo gallery (same attribute). Does nothing on pages without
 * diary nodes.
 */
(function () {
  'use strict';
  if (typeof window === 'undefined' || window.__g7daLoaded) {
    return;
  }
  window.__g7daLoaded = true;

  var observer = null;

  function onVisible(entries) {
    entries.forEach(function (entry) {
      var btn = entry.target;
      if (entry.isIntersecting && btn.getAttribute('data-g7da-more') === 'auto' && !btn.disabled && btn.isConnected) {
        btn.click();
      }
    });
  }

  function watchMoreButtons() {
    if (!('IntersectionObserver' in window)) {
      return;
    }
    observer = observer || new IntersectionObserver(onVisible, { rootMargin: '0px 0px 300px 0px' });
    // Observing again reports the current state, so a button that is still in view after a
    // page was appended (short pages) loads the next one too. While loading it is disabled.
    observer.disconnect();
    document.querySelectorAll('[data-g7da-more]').forEach(function (btn) {
      observer.observe(btn);
    });
  }

  // D72: the fade belongs to the card body block (preview + photo row); text that is longer than the
  // preview box marks the block, so a long kept text fades too.
  function measurePreviews() {
    document.querySelectorAll('.g7da-preview').forEach(function (box) {
      var body = box.closest('.g7da-body') || box;
      body.classList.toggle('g7da-overflow', box.scrollHeight > box.clientHeight + 1);
    });
  }

  // D73: the card title tooltip only while the title is cut (… shown), never where nothing can hover.
  var canHover = !window.matchMedia || window.matchMedia('(hover: hover)').matches;
  function measureTitles() {
    document.querySelectorAll('.g7da-title-wrap[data-g7da-full]').forEach(function (wrap) {
      var text = wrap.querySelector('.g7da-title-text');
      var cut = !!text && text.scrollWidth > text.clientWidth + 1;
      if (canHover && cut) {
        wrap.setAttribute('data-g7da-tip', wrap.getAttribute('data-g7da-full') || '');
      } else {
        wrap.removeAttribute('data-g7da-tip');
      }
    });
  }

  // 4. narrow 지난 오늘: one pick, straight to the post; the last shown id is excluded next time.
  function seenKey(slug) {
    return 'g7da.memory.' + slug;
  }
  function readSeen(slug) {
    try {
      var v = window.sessionStorage.getItem(seenKey(slug));
      return v && /^[0-9]+$/.test(v) ? v : '';
    } catch (e) {
      return '';
    }
  }
  function writeSeen(slug, id) {
    try {
      window.sessionStorage.setItem(seenKey(slug), String(id));
    } catch (e) { /* storage blocked: the pick still works, only the exclusion is lost */ }
  }
  function goMemory(btn) {
    var core = window.G7Core;
    var slug = btn.getAttribute('data-g7da-memory-go') || '';
    if (!core || typeof core.dispatch !== 'function' || !/^[A-Za-z0-9_-]+$/.test(slug) || btn.getAttribute('aria-busy') === 'true') {
      return;
    }
    btn.setAttribute('aria-busy', 'true');
    Promise.resolve(core.dispatch({
      handler: 'apiCall', auth_mode: 'optional', target: '/api/plugins/g7-diary-addon/boards/' + slug + '/memories',
      params: { method: 'GET', query: { count: 1, exclude: readSeen(slug) } }
    })).then(function (r) {
      var body = r && r.data;
      var data = body && body.data !== undefined ? body.data : body;
      var item = data && data.items && data.items[0];
      if (item && item.id) {
        writeSeen(slug, item.id);
        return core.dispatch({ handler: 'navigate', params: { path: '/board/' + slug + '/' + item.id } });
      }
      return core.dispatch({ handler: 'toast', params: { type: 'info', message: (data && data.empty_text) || '' } });
    }).catch(function () { /* the API error toast comes from the core */ }).then(function () {
      btn.removeAttribute('aria-busy');
    });
  }

  // 5. wide popup focus: into the dialog when it opens, back to the opener when it closes.
  var pop = { open: false, opener: null };
  function watchPopup() {
    var dialog = document.querySelector('.g7da-mem-pop');
    if (dialog && !pop.open) {
      pop.open = true;
      pop.opener = document.activeElement && document.activeElement !== document.body ? document.activeElement : null;
      dialog.focus();
    } else if (!dialog && pop.open) {
      pop.open = false;
      var back = pop.opener && pop.opener.isConnected ? pop.opener : document.querySelector('[data-g7da-mem-open]');
      if (back) {
        back.focus();
      }
    }
  }
  function onKey(e) {
    var dialog = document.querySelector('.g7da-mem-pop');
    if (!dialog) {
      return;
    }
    if (e.key === 'Escape') {
      var close = dialog.querySelector('[data-g7da-mem-close]');
      if (close) {
        close.click();
      }
      return;
    }
    if (e.key !== 'Tab') {
      return;
    }
    var items = Array.prototype.filter.call(dialog.querySelectorAll('button, a[href], [tabindex]:not([tabindex="-1"])'), function (el) {
      return !el.disabled && el.offsetParent !== null;
    });
    if (items.length === 0) {
      e.preventDefault();
      return;
    }
    var active = document.activeElement;
    var inside = dialog.contains(active) && active !== dialog;
    var edge = e.shiftKey ? items[0] : items[items.length - 1];
    if (!inside || active === edge) {
      e.preventDefault();
      (e.shiftKey ? items[items.length - 1] : items[0]).focus();
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
      watchPopup();
      if (!document.querySelector('[data-g7da]')) {
        return;
      }
      watchMoreButtons();
      measurePreviews();
      measureTitles();
    });
  }

  function start() {
    refresh();
    new MutationObserver(refresh).observe(document.body, { childList: true, subtree: true, attributes: true, attributeFilter: ['data-g7da-more', 'disabled'] });
    window.addEventListener('resize', refresh);
    document.addEventListener('keydown', onKey);
    document.addEventListener('click', function (e) {
      var btn = e.target && e.target.closest ? e.target.closest('[data-g7da-memory-go]') : null;
      if (btn) {
        e.preventDefault();
        goMemory(btn);
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start);
  } else {
    start();
  }
})();
