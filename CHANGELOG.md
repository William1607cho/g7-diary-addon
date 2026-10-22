# Changelog

## [0.1.0-dev] - 개발 중

### 추가
- 게시판 유형 `diary`(일기) 등록과 재시드 유지, 사용 중이면 제거 거부.
- 표 3개: 분류(`g7_diary_addon_categories`, 기본 7개 고정 id), 일기 메타(`g7_diary_addon_entries`), 사진 색인(`g7_diary_addon_photos`).
- diary 게시판 저장 흐름: 선택 제목 + 자동 제목, `diary_category_id`·`diary_weather`·`diary_date` 검증(일기 날짜는 사이트 시간대 기준 미래 거부)과 저장, 사진 색인, 게시판 삭제 때 행 정리.
- 최소 설정 스키마(날씨 목록·자동 제목 길이·사진 일기 제목·지난 일기 부족 문구·목록/사진 표시 방식·사진 한 번에).
- `GET /api/plugins/g7-diary-addon/categories`.
