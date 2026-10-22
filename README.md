# g7-diary-addon

그누보드7 `sirsoft-board` 게시판에 **일기(`diary`) 유형**을 더하는 애드온입니다.
`sirsoft-board`와 방문자 템플릿은 수정하지 않고, 애드온 전용 표·API·훅으로만 동작합니다.

> 개발 중(0.1.0-dev)입니다. 아직 릴리스하지 않았습니다.

## 지금 들어 있는 것 (①묶음)

| 기능 | 내용 |
|---|---|
| 게시판 유형 `diary` | 설치 때 `board_types`에 `diary`(일기) 행을 넣고, `sirsoft-board` 유형 재시드에서도 살아남게 필터로 끼워 넣습니다. 이 유형을 쓰는 게시판이 있으면 제거를 거부합니다. |
| 자동 제목 | 일기 게시판에서는 제목이 선택 입력입니다. 비워 두면 본문 첫 블록의 글자 앞 N자(기본 30자, 넘으면 `…`)로 채우고, 글자 없이 사진만 있으면 "(사진 일기)"로 채웁니다. 사용자가 직접 쓴 제목은 자동으로 덮어쓰지 않습니다. |
| 일기 메타 | 같은 저장 요청의 `diary_category_id`(분류)·`diary_weather`(날씨 키)·`diary_date`(일기 날짜, `Y-m-d`)를 검증해 애드온 표에 저장합니다. 일기 날짜는 사이트 시간대 기준 오늘 이후를 거부하고, 비우면 오늘로 둡니다. |
| 사진 색인 | 글의 에디터 이미지와 이미지 첨부를 순서대로 색인합니다(사진 모아보기용). |
| 분류 | 기본 분류 7개(행복일기·우울일기·개빡치는 날·평온한 하루(기본)·스페샬 이벤트·맛집탐방·포토스팟)를 고정 id로 넣습니다. `GET /api/plugins/g7-diary-addon/categories`로 읽습니다. |

목록·글 보기·글쓰기 화면, 달력·사진 모아보기·지난 오늘, 분류 관리 화면은 다음 묶음에서 더합니다.

## 표

| 표(접두어 전) | 용도 |
|---|---|
| `g7_diary_addon_categories` | 분류(고정 id, 이모지/이미지 아이콘, 색, 순서, 기본 여부, 소프트 삭제) |
| `g7_diary_addon_entries` | 글별 일기 메타(분류·날씨·일기 날짜·자동 제목 여부) |
| `g7_diary_addon_photos` | 글별 사진 색인(에디터 이미지 해시 또는 첨부 id, 순서) |

## 라이선스

MIT — [LICENSE](./LICENSE)

---

## English summary

A Gnuboard7 add-on that adds a **diary** board type to `sirsoft-board`: optional titles
filled automatically from the first text block (or "(사진 일기)" for photo-only posts),
per-post diary metadata (category, weather, diary date validated against the site
time zone), and a photo index. It never modifies `sirsoft-board` or the template.
Work in progress (0.1.0-dev).
