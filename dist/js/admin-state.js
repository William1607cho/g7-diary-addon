/*
 * g7-diary-addon — state of the admin settings screen "일기장 설정" (bundle 4, hand-written, no build step).
 *
 * Pure functions only (no DOM): the screen keeps everything under `_local.g7da` and every change goes through
 * `reduce(state, op, params, t)`, called by the `g7-diary-addon.admin` action handler (admin.js). The same rules
 * as the server (CategoryRules / WeatherKeys / EmojiCheck) decide what may be saved; the server checks again.
 * Loaded only on that screen (layout `scripts`), never on visitor pages. `module.exports` is for node tests.
 */
(function (root) {
  'use strict';

  var NAME_MAX = 20;
  var MAX_CATS = 30;
  var MAX_WEATHER = 20;
  var HEX = /^#[0-9A-Fa-f]{6}$/;
  var EMOJI = /\p{Extended_Pictographic}|\p{Regional_Indicator}|⃣/u;
  var BAD_TEXT = /[\u0000-\u001f\u007f]|\{\{|\}\}/;
  var TABS = ['cats', 'weather', 'disp'];
  var seq = 0;

  function graphemes(s) {
    if (typeof Intl !== 'undefined' && Intl.Segmenter) {
      return Array.from(new Intl.Segmenter('ko', { granularity: 'grapheme' }).segment(s)).length;
    }
    return Array.from(s).length;
  }

  // D103: one grapheme cluster that carries an emoji (flags, skin tones, ZWJ families, keycaps are one).
  function isOneEmoji(s) {
    return typeof s === 'string' && s !== '' && s.trim() === s && s.length <= 32 && graphemes(s) === 1 && EMOJI.test(s);
  }

  function clean(s) {
    return typeof s === 'string' ? s.replace(/^\s+|\s+$/g, '') : '';
  }

  function newUid() {
    seq += 1;
    return 'n' + Date.now().toString(36) + seq;
  }

  function copy(v) {
    return JSON.parse(JSON.stringify(v));
  }

  function init(data) {
    var d = data || {};
    var cats = (d.categories || []).map(function (c) { return { uid: c.uid, id: c.id, emoji: c.emoji, name: c.name, color: c.color, is_default: !!c.is_default, posts: c.posts || 0 }; });
    var weather = (d.weather || []).map(function (w) { return { uid: w.uid, key: w.key, emoji: w.emoji, label: w.label, posts: w.posts || 0 }; });
    var disp = displayOf(d.display || {});
    var s = { tab: 'cats', cats: cats, weather: weather, disp: disp, base: { cats: copy(cats), weather: copy(weather), disp: copy(disp) }, picker: null, dialog: null, busy: '', serverErrors: {} };
    return finish(s);
  }

  function displayOf(x) {
    var n = function (v) { return v === undefined || v === null ? '' : String(v); };
    return {
      list_display_mode: x.list_display_mode === 'infinite' ? 'infinite' : 'paginate',
      list_page_size: x.list_page_size ? String(x.list_page_size) : '',
      photo_display_mode: x.photo_display_mode === 'paginate' ? 'paginate' : 'infinite',
      photo_page_size: n(x.photo_page_size),
      auto_title_length: n(x.auto_title_length),
      photo_diary_title: n(x.photo_diary_title),
      memories_empty_text: n(x.memories_empty_text)
    };
  }

  // What is compared for "unsaved changes" (counts are display only).
  function sig(s, tab) {
    if (tab === 'cats') {
      return JSON.stringify(s.cats.map(function (c) { return [c.id, c.emoji, clean(c.name), String(c.color).toUpperCase(), c.is_default]; }));
    }
    if (tab === 'weather') {
      return JSON.stringify(s.weather.map(function (w) { return [w.key, w.emoji, clean(w.label)]; }));
    }
    return JSON.stringify(s.disp);
  }

  function intIn(v, min, max, emptyOk) {
    if (v === '' && emptyOk) {
      return true;
    }
    return /^[0-9]{1,4}$/.test(v) && Number(v) >= min && Number(v) <= max;
  }

  function validateCats(s) {
    var rows = {};
    var seen = {};
    s.cats.forEach(function (c) {
      var e = {};
      var name = clean(c.name);
      var len = graphemes(name);
      if (!isOneEmoji(c.emoji)) { e.emoji = 'emoji_single'; }
      if (len < 1 || len > NAME_MAX || BAD_TEXT.test(name)) { e.name = 'name_length'; } else if (seen[name.toLowerCase()]) { e.name = 'name_duplicate'; }
      seen[name.toLowerCase()] = true;
      if (!HEX.test(c.color || '')) { e.color = 'color_hex'; }
      if (Object.keys(e).length) { rows[c.uid] = e; }
    });
    var defaults = s.cats.filter(function (c) { return c.is_default; }).length;
    var general = s.cats.length === 0 ? 'categories_required' : s.cats.length > MAX_CATS ? 'categories_too_many' : defaults !== 1 ? (defaults === 0 ? 'default_missing' : 'default_many') : '';
    return { rows: rows, general: general };
  }

  function validateWeather(s) {
    var rows = {};
    s.weather.forEach(function (w) {
      var e = {};
      var label = clean(w.label);
      var len = graphemes(label);
      if (!isOneEmoji(w.emoji)) { e.emoji = 'emoji_single'; }
      if (len < 1 || len > NAME_MAX || BAD_TEXT.test(label)) { e.label = 'name_length'; }
      if (Object.keys(e).length) { rows[w.uid] = e; }
    });
    return { rows: rows, general: s.weather.length > MAX_WEATHER ? 'weather_too_many' : '' };
  }

  function validateDisp(s) {
    var d = s.disp;
    var e = {};
    if (!intIn(d.list_page_size, 1, 100, true)) { e.list_page_size = 'range_list'; }
    if (!intIn(d.photo_page_size, 6, 120, false)) { e.photo_page_size = 'range_photo'; }
    if (!intIn(d.auto_title_length, 5, 100, false)) { e.auto_title_length = 'range_title'; }
    ['photo_diary_title', 'memories_empty_text'].forEach(function (k) {
      var v = clean(d[k]);
      if (v === '' || v.length > 100 || BAD_TEXT.test(v)) { e[k] = 'text_length'; }
    });
    return { rows: {}, general: '', fields: e };
  }

  // Recomputes the derived parts: unsaved flags, errors, whether each tab may be saved.
  function finish(s) {
    s.dirty = {};
    s.valid = {};
    s.errors = {};
    var checks = { cats: validateCats(s), weather: validateWeather(s), disp: validateDisp(s) };
    TABS.forEach(function (tab) {
      var c = checks[tab];
      s.dirty[tab] = sig(s, tab) !== sig(s.base, tab);
      s.valid[tab] = !c.general && Object.keys(c.rows).length === 0 && Object.keys(c.fields || {}).length === 0;
      s.errors[tab] = c;
    });
    s.anyDirty = s.dirty.cats || s.dirty.weather || s.dirty.disp;
    s.defaultName = (s.cats.filter(function (c) { return c.is_default; })[0] || {}).name || '';
    return s;
  }

  function move(list, i, j) {
    if (i < 0 || j < 0 || i >= list.length || j >= list.length || i === j) {
      return list;
    }
    var out = list.slice();
    var item = out.splice(i, 1)[0];
    out.splice(j, 0, item);
    return out;
  }

  function indexOf(list, uid) {
    for (var i = 0; i < list.length; i++) {
      if (list[i].uid === uid) {
        return i;
      }
    }
    return -1;
  }

  function sorted(list, items) {
    if (!Array.isArray(items) || items.length !== list.length) {
      return list;
    }
    var by = {};
    list.forEach(function (x) { by[x.uid] = x; });
    var out = items.map(function (x) { return x && by[x.uid]; });
    return out.every(Boolean) ? out : list;
  }

  function revert(s, tab) {
    s[tab] = copy(s.base[tab]);
    s.serverErrors[tab] = null;
  }

  var OPS = {
    tab: function (s, p) {
      if (TABS.indexOf(p.tab) < 0 || p.tab === s.tab) { return; }
      if (s.dirty[s.tab]) { s.dialog = { kind: 'leave_tab', next: p.tab }; return; }
      s.tab = p.tab;
    },
    dialogCancel: function (s) { s.dialog = null; },
    dialogConfirm: function (s) {
      var d = s.dialog || {};
      s.dialog = null;
      if (d.kind === 'leave_tab') { revert(s, s.tab); s.tab = d.next; }
      if (d.kind === 'delete_cat') { s.cats = s.cats.filter(function (c) { return c.uid !== d.uid; }); }
      if (d.kind === 'delete_weather') { s.weather = s.weather.filter(function (w) { return w.uid !== d.uid; }); }
      if (d.kind === 'leave_page') { TABS.forEach(function (t) { revert(s, t); }); s.leaveTo = d.path; }
    },
    leaveAsk: function (s, p) { if (s.anyDirty) { s.dialog = { kind: 'leave_page', path: p.path }; } },
    catField: function (s, p) {
      var i = indexOf(s.cats, p.uid);
      if (i >= 0 && p.field === 'name') { s.cats[i].name = String(p.value === undefined || p.value === null ? '' : p.value).slice(0, 64); }
    },
    catDefault: function (s, p) { s.cats.forEach(function (c) { c.is_default = c.uid === p.uid; }); },
    catAdd: function (s) {
      if (s.cats.length >= MAX_CATS) { return; }
      s.cats.push({ uid: newUid(), id: null, emoji: '', name: '', color: '#6B7280', is_default: false, posts: 0 });
    },
    catMove: function (s, p) { var i = indexOf(s.cats, p.uid); s.cats = move(s.cats, i, i + (p.dir < 0 ? -1 : 1)); },
    catSorted: function (s, p) { s.cats = sorted(s.cats, p.items); },
    catDeleteAsk: function (s, p) {
      var c = s.cats[indexOf(s.cats, p.uid)];
      if (!c || c.is_default || s.cats.length <= 1) { return; }
      s.dialog = { kind: 'delete_cat', uid: c.uid, count: c.posts, name: c.name, defaultName: s.defaultName };
    },
    weatherField: function (s, p) { var i = indexOf(s.weather, p.uid); if (i >= 0) { s.weather[i].label = String(p.value || '').slice(0, 64); } },
    weatherAdd: function (s) {
      if (s.weather.length >= MAX_WEATHER) { return; }
      var uid = newUid();
      s.weather.push({ uid: uid, key: '', emoji: '', label: '', posts: 0 });
      s.picker = { kind: 'emoji', list: 'weather', uid: uid, group: 'nature', input: '', error: '' };
    },
    weatherMove: function (s, p) { var i = indexOf(s.weather, p.uid); s.weather = move(s.weather, i, i + (p.dir < 0 ? -1 : 1)); },
    weatherSorted: function (s, p) { s.weather = sorted(s.weather, p.items); },
    weatherDeleteAsk: function (s, p) {
      var w = s.weather[indexOf(s.weather, p.uid)];
      if (w) { s.dialog = { kind: 'delete_weather', uid: w.uid, count: w.posts, name: w.label }; }
    },
    dispField: function (s, p) { if (Object.prototype.hasOwnProperty.call(s.disp, p.field)) { s.disp[p.field] = String(p.value === undefined || p.value === null ? '' : p.value).slice(0, 120); } },
    pickerOpen: function (s, p) { s.picker = { kind: 'emoji', list: p.list === 'weather' ? 'weather' : 'cats', uid: p.uid, group: p.list === 'weather' ? 'nature' : 'faces', input: '', error: '' }; },
    colorOpen: function (s, p) {
      var c = s.cats[indexOf(s.cats, p.uid)];
      if (c) { s.picker = { kind: 'color', list: 'cats', uid: c.uid, group: '', input: c.color || '', error: '' }; }
    },
    pickerGroup: function (s, p) { if (s.picker) { s.picker.group = String(p.group || 'faces'); } },
    pickerInput: function (s, p) { if (s.picker) { s.picker.input = String(p.value || '').slice(0, 64); s.picker.error = ''; } },
    pickerPick: function (s, p) { pick(s, p.value); },
    pickerApply: function (s) { if (s.picker) { pick(s, clean(s.picker.input)); } },
    pickerClose: function (s) { s.picker = null; },
    busy: function (s, p) { s.serverErrors[p.tab] = null; s.busy = TABS.indexOf(p.tab) >= 0 ? p.tab : ''; },
    saved: function (s, p) {
      if (p.tab === 'cats') { s.cats = init({ categories: p.data && p.data.categories }).cats; }
      if (p.tab === 'weather') { s.weather = weatherFromSettings(p.data, s.weather); }
      if (p.tab === 'disp') { s.disp = displayOf(p.data || {}); }
      s.base[p.tab] = copy(s[p.tab]);
      s.busy = '';
    },
    saveFailed: function (s, p) { s.busy = ''; s.serverErrors[p.tab] = flatten(p.errors); }
  };

  // Picker result: an emoji (one grapheme, D103) or a colour (#RRGGBB, written upper-case).
  function pick(s, value) {
    var pk = s.picker;
    if (!pk) { return; }
    var color = pk.kind === 'color';
    var v = color && /^[0-9A-Fa-f]{6}$/.test(value || '') ? '#' + value : value;
    if (color ? !HEX.test(v || '') : !isOneEmoji(v)) { pk.error = color ? 'color_hex' : 'emoji_single'; return; }
    var list = pk.list === 'weather' ? s.weather : s.cats;
    var i = indexOf(list, pk.uid);
    if (i >= 0) { list[i][color ? 'color' : 'emoji'] = color ? v.toUpperCase() : v; }
    s.picker = null;
  }

  // Saved weather from the core settings response: keys come from the server (new items get theirs there).
  function weatherFromSettings(data, before) {
    var posts = {};
    before.forEach(function (w) { if (w.key) { posts[w.key] = w.posts; } });
    var list = data && Array.isArray(data.weather_options) ? data.weather_options : [];
    return list.map(function (w) { return { uid: 'w:' + w.key, key: w.key, emoji: w.emoji, label: w.label, posts: posts[w.key] || 0 }; });
  }

  function flatten(errors) {
    var out = [];
    Object.keys(errors || {}).forEach(function (k) {
      [].concat(errors[k]).forEach(function (m) { out.push(String(m)); });
    });
    return out;
  }

  function reduce(state, op, params) {
    var s = copy(state || {});
    if (!s.cats) {
      return state;
    }
    delete s.leaveTo;
    var fn = OPS[op];
    if (fn) {
      fn(s, params || {});
    }
    return finish(s);
  }

  var api = { init: init, reduce: reduce, isOneEmoji: isOneEmoji, graphemes: graphemes, ops: Object.keys(OPS) };
  if (typeof module !== 'undefined' && module.exports) {
    module.exports = api;
  } else {
    root.G7DiaryAdminState = api;
  }
})(typeof window !== 'undefined' ? window : this);
