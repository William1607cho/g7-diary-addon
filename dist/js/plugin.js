/*
 * g7-diary-addon — small helpers for the diary list (hand-written, no build step).
 *
 * 1. Infinite scroll: the "load more" button of a diary list (data-g7da-more="auto") is clicked when
 *    it comes into view. After a failed load the layout switches it to "manual" and it stays a button.
 * 2. Body fade: a card body block whose preview text is taller than the preview box gets `g7da-overflow`
 *    (the server flag `is-more-text` covers text it cut itself; photo +N is a server class too).
 * 3. Card title tooltip: set only while the title is cut, and not on devices without hover.
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
      measureTitles();
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
