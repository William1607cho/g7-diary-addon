# g7-diary-addon

그누보드7 `sirsoft-board` 게시판에 **일기(`diary`) 유형**을 더하는 애드온입니다.
`sirsoft-board`와 방문자 템플릿은 수정하지 않고, 애드온 전용 표·API·훅·레이아웃 필터로만 동작합니다.

## 기능

| 기능 | 내용 |
|---|---|
| 게시판 유형 `diary` | 설치 때 `board_types`에 `diary`(일기) 행을 넣고, `sirsoft-board` 유형 재시드에서도 살아남게 합니다. 이 유형을 쓰는 게시판이 있으면 제거를 거부합니다. |
| 자동 제목 | 일기 게시판에서는 제목이 선택 입력입니다. 비워 두면 본문 첫 줄의 글자 앞 N자(기본 30자, 넘으면 `…`)로 채우고, 글자 없이 사진만 있으면 "(사진 일기)"로 채웁니다. 직접 쓴 제목은 덮어쓰지 않습니다. |
| 일기 메타 | 글마다 분류·날씨·일기 날짜를 저장합니다. 일기 날짜는 사이트 시간대 기준이고 오늘 이후는 거부하며, 비우면 오늘로 둡니다. |
| 사진 색인 | 글의 에디터 이미지와 이미지 첨부를 순서대로 색인합니다(사진 모아보기용). |
| 목록 화면 | 일기 카드(분류 이모지·날짜·날씨·제목·미리보기·사진 줄), 옆 패널(연속 작성 일수·이달 달력·분류 상자), 분류·날짜 필터(주소에 남음), 페이지 또는 무한 스크롤. |
| 글쓰기·글 보기 | 글쓰기에 일기 날짜·날씨·분류 칸, 글 보기 제목 위에 일기 정보 줄. |
| 달력 화면 | `/diary/<게시판 slug>/calendar` — 1년 히트맵, 월 달력, 고른 날의 일기. |
| 사진 모아보기 | `/diary/<게시판 slug>/photos` — 달별 사진 격자, 페이지 또는 무한 스크롤. |
| 지난 오늘 | 지난해 같은 날의 일기. 넓은 화면은 팝업, 좁은 화면은 한 편으로 바로 이동. |
| 관리자 설정 | "일기장 설정" — 일기 분류, 날씨, 표시 방식·문구(아래 "설정"). |
| 비밀글 가림 | 보는 사람이 열 수 없는 비밀글은 건수에는 들어가지만 분류·날씨·미리보기·사진을 보이지 않습니다. |
| 일기화 명령 | 일기 유형으로 바꾸기 전에 쓴 글에 일기 메타와 사진 색인을 채웁니다(아래 "기존 게시판을 일기로 바꾸기"). |

### 기본 분류

설치 때 아래 7개를 고정 id로 넣습니다. 이름·이모지·색·순서는 관리자 설정에서 바꿀 수 있습니다.

| id | 분류 |
|---|---|
| 1 | 😊 행복일기 |
| 2 | 😢 우울일기 |
| 3 | 😡 분노일기 |
| 4 | 😌 평온한 하루 (기본) |
| 5 | 🎉 스페샬 이벤트 |
| 6 | 🍜 맛집탐방 |
| 7 | 📸 포토스팟 |

분류가 없는 글, 지운 분류의 글은 기본 분류로 보입니다.

## 요구 사항

- 그누보드7 코어 **7.1.2 이상**
- `sirsoft-board` **1.2.1 이상**
- `g7-image-delivery`(선택): 설치돼 있으면 사진에 변환본(240px)을 씁니다. 없어도 원본으로 동작합니다.

## 설치

1. [Releases](https://github.com/William1607cho/g7-diary-addon/releases)에서 태그 zip을 받습니다.
2. zip 내용을 `plugins/_pending/g7-diary-addon/`에 풀고 설치·활성화합니다.
   ```
   php artisan plugin:install g7-diary-addon
   php artisan plugin:activate g7-diary-addon
   ```
3. 설치·활성화 직후 훅 캐시(`bootstrap/cache/hooks.php`)에 이 애드온의 리스너가 들어가지 않는 경우가 있습니다(코어 7.1.2 실측).
   같은 zip으로 업데이트를 두 번 하면 들어갑니다.
   ```
   php artisan plugin:update g7-diary-addon --zip=<zip 경로> --force
   ```
4. 관리자 → 게시판 관리에서 게시판 유형을 **일기**로 고릅니다. 추천 설정: 첨부 업로드 끔(사진은 에디터로), 비밀글 모드 `enabled`(글쓰기에 비밀글 칸 표시).
5. 달력·사진 모아보기는 메뉴에 `/diary/<게시판 slug>/calendar`·`/diary/<게시판 slug>/photos`를 상대 경로로 넣어 엽니다.

업데이트는 새 태그 zip으로 `plugin:update`를 합니다. 리스너가 바뀐 판은 위 3번처럼 두 번 실행합니다.

## 설정

관리자 → 플러그인 관리 → 일기장 게시판 애드온 → 설정(`/admin/plugins/g7-diary-addon/settings`). 권한 `core.plugins.update`.

| 탭 | 내용 |
|---|---|
| 일기 분류 | 순서 바꾸기(끌기·버튼), 이모지 고르기(6묶음·직접 입력), 색(견본·hex), 기본 분류 지정, 삭제(그 분류의 글 수 확인). 삭제는 소프트 삭제이고 되살리기는 없습니다. 글 수는 삭제되지 않은 일기 글 전부(비밀글 포함) 기준입니다. |
| 날씨 | 날씨 칩 추가·이름·이모지 변경. 키는 고정입니다(기본 6종은 그대로, 새 칩은 서버가 `w_` + 6자를 붙임). 목록을 비워 저장하면 글쓰기의 날씨 줄이 숨겨집니다. |
| 표시 | 목록·사진 표시 방식(페이지·무한 스크롤)과 한 번에 보일 수, 자동 제목 길이, 사진 일기 제목, 지난 오늘이 비었을 때 문구. |

저장하지 않은 변경이 있으면 탭 이동·화면 안 링크 이동 때 화면 안 상자로 확인합니다(브라우저 뒤로·앞으로는 확인하지 않습니다).

설정 키(`config/settings/defaults.json`): `weather_options`, `auto_title_length`(30), `photo_diary_title`("(사진 일기)"), `memories_empty_text`, `list_display_mode`(`paginate`), `list_page_size`(0 = 게시판 설정), `photo_display_mode`(`infinite`), `photo_page_size`(30).

## 기존 게시판을 일기로 바꾸기

일기 유형으로 바꾸기 전에 쓴 글에는 일기 메타가 없습니다. 그런 글도 목록·달력·지난 오늘에는 **작성일**(사이트 시간대) 기준으로 이미 보이지만, 사진 모아보기에는 빠집니다. 일기화 명령으로 메타(일기 날짜 = 작성일, 분류 = 기본, 날씨 없음)와 사진 색인을 채웁니다. 글(`board_posts`)은 바꾸지 않습니다.

```
# 1) 먼저 숫자만 봅니다(쓰지 않음, 게시판 유형과 무관)
php artisan g7-diary-addon:backfill <게시판 slug> --dry-run

# 2) 관리자 화면에서 게시판 유형을 '일기'로 바꿉니다

# 3) 실행(일기 유형에서만 됨). 다시 실행하면 이미 채운 글은 건너뜁니다
php artisan g7-diary-addon:backfill <게시판 slug>

# 되돌리기: 이 명령이 만든 행만 지웁니다(그사이 작성자가 고친 글의 메타는 남음, 유형과 무관)
php artisan g7-diary-addon:backfill <게시판 slug> --undo
```

- 대상: 삭제되지 않은 글(비밀글 포함). 글 id 순으로 200편씩(`--batch=1~5000`) 트랜잭션으로 처리합니다.
- 출력은 편수·날짜 범위·연도별 편수·사진 장수뿐입니다(제목·본문 없음).
- 한 묶음이 실패하면 그 묶음만 되돌리고 멈춥니다. 원인을 고친 뒤 다시 실행하면 이어서 처리합니다.

## API

접두어 `/api/plugins/g7-diary-addon`. 분당 120회 제한(로그인 사용자는 사용자별, 비회원은 IP별).

| 구분 | 경로 |
|---|---|
| 공개 | `GET categories`, `GET boards/{slug}/summary`·`heatmap`·`calendar`·`day`·`photos`·`memories` |
| 관리자 | `GET admin/screen`, `PUT admin/categories`, `DELETE admin/categories/{id}` |

공개 API는 게시판 읽기 권한과 비밀글 규칙을 따릅니다. 달력·사진 화면은 검색 노출을 하지 않습니다.

## 표

| 표(접두어 전) | 용도 |
|---|---|
| `g7_diary_addon_categories` | 분류(고정 id, 이모지/이미지 아이콘, 색, 순서, 기본 여부, 소프트 삭제) |
| `g7_diary_addon_entries` | 글별 일기 메타(분류·날씨·일기 날짜·자동 제목 여부·만든 경로) |
| `g7_diary_addon_photos` | 글별 사진 색인(에디터 이미지 해시 또는 첨부 id, 순서) |

## 알려진 제약

- 브라우저 뒤로·앞으로 이동은 저장하지 않은 변경을 확인하지 않습니다.
- 지운 분류를 되살리는 화면은 없습니다.

## 라이선스

MIT — [LICENSE](./LICENSE)

---

## English summary

A Gnuboard7 add-on that adds a **diary** board type to `sirsoft-board` without modifying it or the template.
Diary boards get optional auto titles, per-post category / weather / diary date (site time zone), a photo index,
diary cards with a side panel (streak, month calendar, categories) and filters, a calendar screen (year heatmap,
month, day), a photo gallery, "On this day", and an admin settings screen for categories, weather and display.
Secret posts the viewer cannot open never reveal their details. `php artisan g7-diary-addon:backfill <slug>`
(`--dry-run`, `--undo`) fills diary data for posts written before a board became a diary.
Requires Gnuboard7 7.1.2+ and sirsoft-board 1.2.1+; g7-image-delivery is optional. MIT licence.
