<?php

return [
    'validation' => [
        'category_invalid' => '선택할 수 없는 일기 분류입니다.',
        'weather_invalid' => '선택할 수 없는 날씨입니다.',
        'date_format' => '일기 날짜는 YYYY-MM-DD 형식의 날짜여야 합니다.',
        'date_future' => '일기 날짜는 오늘 이후로 정할 수 없습니다.',
    ],
    // Bundle 4: the admin settings screen API (CategoryRules / WeatherKeys codes) and the emoji groups.
    'admin' => [
        'categories_required' => '분류가 하나 이상 있어야 합니다.',
        'categories_too_many' => '분류는 30개까지 둘 수 있습니다.',
        'category_unknown' => '없거나 이미 지운 분류입니다.',
        'emoji_single' => '이모지 한 개만 넣을 수 있습니다.',
        'name_length' => '이름은 1~20자로 쓰세요.',
        'name_duplicate' => '같은 이름의 분류가 있습니다.',
        'color_hex' => '색은 #과 6자리 16진수로 넣으세요.',
        'default_missing' => '기본 분류를 하나 고르세요.',
        'default_many' => '기본 분류는 하나만 고를 수 있습니다.',
        'delete_default' => '기본 분류는 지울 수 없습니다.',
        'delete_last' => '분류가 하나뿐이면 지울 수 없습니다.',
        'weather_list' => '날씨 목록 형식이 올바르지 않습니다.',
        'weather_too_many' => '날씨는 20개까지 둘 수 있습니다.',
        'weather_key' => '날씨 키가 올바르지 않거나 겹칩니다.',
        'group_faces' => '표정',
        'group_people' => '사람·활동',
        'group_food' => '음식',
        'group_travel' => '여행·장소',
        'group_nature' => '날씨·자연',
        'group_objects' => '기념일·물건',
    ],
];
