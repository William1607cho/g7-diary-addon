/*
 * g7-diary-addon — small helpers for the diary list (hand-written, no build step).
 *
 * 1. Infinite scroll: the "load more" button of a diary list (data-g7da-more="auto") is clicked when
 *    it comes into view. After a failed load the layout switches it to "manual" and it stays a button.
 * 2. Preview fade: a preview box whose content is taller than the box gets `g7da-overflow`
 *    (the server flag `is-clipped` covers text it cut itself; this covers long kept text and images).
 * Does nothing on pages without diary nodes.
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

  function measurePreviews() {
    document.querySelectorAll('.g7da-preview').forEach(function (box) {
      box.classList.toggle('g7da-overflow', box.scrollHeight > box.clientHeight + 1);
      box.querySelectorAll('img').forEach(function (img) {
        if (!img.complete && !img.__g7da) {
          img.__g7da = true;
          img.addEventListener('load', measurePreviews, { once: true });
        }
      });
    });
  }

  var pending = false;
  function refresh() {
    if (pending) {
      return;
    }
    pending = true;
    window.requestAnimationFrame(function () {
      pending = false;
      if (!document.querySelector('[data-g7da]')) {
        return;
      }
      watchMoreButtons();
      measurePreviews();
    });
  }

  function start() {
    refresh();
    new MutationObserver(refresh).observe(document.body, { childList: true, subtree: true, attributes: true, attributeFilter: ['data-g7da-more', 'disabled'] });
    window.addEventListener('resize', refresh);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start);
  } else {
    start();
  }
})();
