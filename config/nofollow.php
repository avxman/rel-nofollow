<?php

return [

    // Вкл./Откл. закрытие входящих ссылок на сайте
    'enabled' => env('NO_FOLLOW_ENABLED', true),

    // Модели закрытие входящих ссылок
    'model'=>[
        'nofollow'=>\Avxman\NoFollow\Models\NoFollowModel::class,
    ],

    // Исключаем работу входящих ссылок к привязанным моделям
    'except_model'=>[
        //\App\Models\User::class
    ],

    // Добавляем в теге rel значения
    'pattern'=>[
        'nofollow',
        'noopener',
    ],

    // Исключить домены для поиска
    'except_domain'=>[
        'ribalych.ru'
    ],

    // Ищем текст в указанных полях моделей
    'fields'=>[
        'body',
        'desc'
    ],

];
