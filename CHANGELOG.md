# Changelog

## [1.0.0] - 2026-10-27

First public release.

### Added
- Board type `diary` for sirsoft-board (kept through the board type re-seed; uninstall is refused while a board uses it).
- Diary metadata per post: category, weather and diary date (site time zone, no future dates); optional title filled
  from the first text line (or "(사진 일기)" for photo-only posts); photo index of editor images and image attachments.
- List screen for diary boards: diary cards (category emoji, date, weather, title, preview with photo row), side panel
  with writing streak, month calendar and category boxes, category / date filters kept in the address, pages or
  infinite scroll.
- Write and show screens: diary date, weather and category fields; diary info line above the title.
- Calendar screen (year heatmap, month calendar, entries of a day), photo gallery (by month, pages or infinite
  scroll) and "On this day" (popup on wide screens, straight to one entry on narrow ones).
- Admin settings screen "일기장 설정" (Admin → Plugins → settings): categories (drag or buttons to reorder, emoji
  picker with six groups and direct input, colour swatches or hex, default category, delete with post count), weather
  (chips with fixed keys), display modes and texts. Unsaved changes are confirmed inside the screen.
- Secret posts the viewer cannot open are counted but never show their category, weather, preview or photos.
- Artisan command `g7-diary-addon:backfill {board} [--dry-run] [--undo] [--batch=200]`: diary metadata (diary date =
  creation day in the site time zone, default category, no weather) and the photo index for posts written before the
  board became a diary; `--undo` removes only the rows it made and that were not edited since.

### Fixed
- List screens of non-diary boards no longer request the diary summary / calendar APIs (they returned 404 on every
  board list).

### Notes
- sirsoft-board and the templates are not modified; no repository is rebound.
- The admin screen's script, styles and emoji data load on that screen only.
- Requires Gnuboard7 7.1.2 or later and sirsoft-board 1.2.1 or later. g7-image-delivery is optional (used for photo
  thumbnails when it is installed).
