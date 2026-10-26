// Bundle 4: admin screen state (dist/js/admin-state.js) — node:test, no DOM, no network.
'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const S = require('../../dist/js/admin-state.js');

function data() {
  return {
    categories: [
      { uid: 'c1', id: 1, emoji: '😊', name: '행복일기', color: '#EAB308', is_default: false, posts: 5 },
      { uid: 'c3', id: 3, emoji: '😡', name: '개빡치는 날', color: '#EF4444', is_default: false, posts: 2 },
      { uid: 'c4', id: 4, emoji: '😌', name: '평온한 하루', color: '#22C55E', is_default: true, posts: 9 }
    ],
    weather: [{ uid: 'w:sunny', key: 'sunny', emoji: '☀️', label: '맑음', posts: 4 }, { uid: 'w:rain', key: 'rain', emoji: '🌧️', label: '비', posts: 1 }],
    display: { list_display_mode: 'paginate', list_page_size: 0, photo_display_mode: 'infinite', photo_page_size: 30, auto_title_length: 30, photo_diary_title: '(사진 일기)', memories_empty_text: '힘내서 일기 좀 더 써 주세요' }
  };
}

test('one emoji: sequences yes, text and pairs no (same rule as EmojiCheck)', () => {
  for (const ok of ['😊', '🇰🇷', '👍🏽', '👨‍👩‍👧‍👦', '1️⃣', '☀️']) assert.equal(S.isOneEmoji(ok), true, ok);
  for (const bad of ['a', '가', '1', '😊😊', ' 😊', '', null]) assert.equal(S.isOneEmoji(bad), false, String(bad));
});

test('init is clean; a rename makes the tab dirty and back again clean', () => {
  let s = S.init(data());
  assert.equal(s.anyDirty, false);
  assert.equal(s.disp.list_page_size, '');
  s = S.reduce(s, 'catField', { uid: 'c3', field: 'name', value: '분노일기' });
  assert.equal(s.dirty.cats, true);
  assert.equal(s.valid.cats, true);
  s = S.reduce(s, 'catField', { uid: 'c3', field: 'name', value: '  개빡치는 날 ' });
  assert.equal(s.dirty.cats, false);
});

test('add row: empty emoji and name block saving; picker and direct input fill it', () => {
  let s = S.reduce(S.init(data()), 'catAdd', {});
  const uid = s.cats[3].uid;
  assert.equal(s.valid.cats, false);
  assert.deepEqual(s.errors.cats.rows[uid], { emoji: 'emoji_single', name: 'name_length' });
  s = S.reduce(s, 'pickerOpen', { list: 'cats', uid });
  assert.equal(s.picker.group, 'faces');
  s = S.reduce(s, 'pickerInput', { value: '😀😀' });
  s = S.reduce(s, 'pickerApply', {});
  assert.equal(s.picker.error, 'emoji_single');
  s = S.reduce(s, 'pickerInput', { value: ' 🇰🇷 ' });
  s = S.reduce(s, 'pickerApply', {});
  assert.equal(s.picker, null);
  assert.equal(s.cats[3].emoji, '🇰🇷');
  s = S.reduce(s, 'catField', { uid, field: 'name', value: '여행' });
  s = S.reduce(s, 'colorOpen', { uid });
  s = S.reduce(s, 'pickerInput', { value: '14b8a6' });
  s = S.reduce(s, 'pickerApply', {});
  assert.equal(s.cats[3].color, '#14B8A6');
  assert.equal(s.valid.cats, true);
});

test('duplicate names and default rules', () => {
  let s = S.reduce(S.init(data()), 'catField', { uid: 'c1', field: 'name', value: '평온한 하루' });
  assert.equal(s.errors.cats.rows.c4.name, 'name_duplicate');
  s = S.reduce(S.init(data()), 'catDefault', { uid: 'c1' });
  assert.deepEqual(s.cats.map((c) => c.is_default), [true, false, false]);
  assert.equal(s.defaultName, '행복일기');
});

test('delete asks with the post count; default and last rows are never asked', () => {
  let s = S.init(data());
  assert.equal(S.reduce(s, 'catDeleteAsk', { uid: 'c4' }).dialog, null);
  s = S.reduce(s, 'catDeleteAsk', { uid: 'c3' });
  assert.deepEqual(s.dialog, { kind: 'delete_cat', uid: 'c3', count: 2, name: '개빡치는 날', defaultName: '평온한 하루' });
  s = S.reduce(s, 'dialogConfirm', {});
  assert.deepEqual(s.cats.map((c) => c.id), [1, 4]);
  assert.equal(s.dirty.cats, true);
  s = S.reduce(s, 'catDeleteAsk', { uid: 'c1' });
  s = S.reduce(s, 'dialogConfirm', {});
  assert.equal(S.reduce(s, 'catDeleteAsk', { uid: 'c4' }).dialog, null);
});

test('reorder by buttons and by drag result', () => {
  let s = S.reduce(S.init(data()), 'catMove', { uid: 'c4', dir: -1 });
  assert.deepEqual(s.cats.map((c) => c.id), [1, 4, 3]);
  s = S.reduce(s, 'catMove', { uid: 'c1', dir: -1 });
  assert.deepEqual(s.cats.map((c) => c.id), [1, 4, 3]);
  s = S.reduce(s, 'catSorted', { items: [s.cats[2], s.cats[0], s.cats[1]] });
  assert.deepEqual(s.cats.map((c) => c.id), [3, 1, 4]);
  assert.deepEqual(S.reduce(s, 'catSorted', { items: [s.cats[0]] }).cats.map((c) => c.id), [3, 1, 4]);
});

test('leaving a dirty tab asks; confirm drops that tab only', () => {
  let s = S.reduce(S.init(data()), 'catField', { uid: 'c3', field: 'name', value: '분노일기' });
  s = S.reduce(s, 'tab', { tab: 'weather' });
  assert.equal(s.tab, 'cats');
  assert.equal(s.dialog.kind, 'leave_tab');
  s = S.reduce(s, 'dialogCancel', {});
  assert.equal(s.cats[1].name, '분노일기');
  s = S.reduce(S.reduce(s, 'tab', { tab: 'weather' }), 'dialogConfirm', {});
  assert.equal(s.tab, 'weather');
  assert.equal(s.cats[1].name, '개빡치는 날');
  s = S.reduce(S.reduce(s, 'weatherField', { uid: 'w:rain', value: '소나기' }), 'leaveAsk', { path: '/admin/plugins' });
  assert.equal(s.dialog.kind, 'leave_page');
  s = S.reduce(s, 'dialogConfirm', {});
  assert.equal(s.leaveTo, '/admin/plugins');
  assert.equal(s.anyDirty, false);
});

test('weather: add opens the picker on nature, keys stay, saved list takes server keys', () => {
  let s = S.reduce(S.init(data()), 'weatherAdd', {});
  assert.equal(s.picker.group, 'nature');
  s = S.reduce(s, 'pickerPick', { value: '🌫️' });
  const uid = s.weather[2].uid;
  s = S.reduce(s, 'weatherField', { uid, value: '안개' });
  s = S.reduce(s, 'weatherField', { uid: 'w:sunny', value: '쨍쨍' });
  assert.equal(s.weather[0].key, 'sunny');
  assert.equal(s.valid.weather, true);
  s = S.reduce(s, 'weatherDeleteAsk', { uid: 'w:rain' });
  assert.deepEqual(s.dialog, { kind: 'delete_weather', uid: 'w:rain', count: 1, name: '비' });
  s = S.reduce(s, 'dialogConfirm', {});
  s = S.reduce(s, 'saved', { tab: 'weather', data: { weather_options: [{ key: 'sunny', emoji: '☀️', label: '쨍쨍' }, { key: 'w_ab12cd', emoji: '🌫️', label: '안개' }] } });
  assert.deepEqual(s.weather.map((w) => [w.uid, w.posts]), [['w:sunny', 4], ['w:w_ab12cd', 0]]);
  assert.equal(s.dirty.weather, false);
});

test('display fields: ranges, empty list size, saved values', () => {
  let s = S.reduce(S.init(data()), 'dispField', { field: 'photo_page_size', value: '5' });
  assert.equal(s.errors.disp.fields.photo_page_size, 'range_photo');
  s = S.reduce(s, 'dispField', { field: 'photo_page_size', value: '12' });
  s = S.reduce(s, 'dispField', { field: 'list_page_size', value: '101' });
  assert.equal(s.errors.disp.fields.list_page_size, 'range_list');
  s = S.reduce(s, 'dispField', { field: 'list_page_size', value: '' });
  s = S.reduce(s, 'dispField', { field: 'memories_empty_text', value: 'a{{b}}' });
  assert.equal(s.errors.disp.fields.memories_empty_text, 'text_length');
  s = S.reduce(s, 'dispField', { field: 'memories_empty_text', value: '더 써 주세요' });
  assert.equal(s.valid.disp, true);
  s = S.reduce(s, 'saved', { tab: 'disp', data: { list_display_mode: 'paginate', list_page_size: 0, photo_display_mode: 'infinite', photo_page_size: 12, auto_title_length: 30, photo_diary_title: '(사진 일기)', memories_empty_text: '더 써 주세요' } });
  assert.equal(s.dirty.disp, false);
});

test('save failure keeps the edits and shows the server messages', () => {
  let s = S.reduce(S.init(data()), 'catField', { uid: 'c3', field: 'name', value: '분노일기' });
  s = S.reduce(s, 'busy', { tab: 'cats' });
  assert.equal(s.busy, 'cats');
  s = S.reduce(s, 'saveFailed', { tab: 'cats', errors: { 'categories.1.name': ['같은 이름의 분류가 있습니다.'] } });
  assert.equal(s.busy, '');
  assert.deepEqual(s.serverErrors.cats, ['같은 이름의 분류가 있습니다.']);
  assert.equal(s.cats[1].name, '분노일기');
});
