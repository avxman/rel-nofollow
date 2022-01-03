# Модуль вкл./откл. индексацию внешних ссылок в контенте laravel >= 8
#### Работа с индексацией внешних ссылок в контенте на сайте. Вывод и сохранение внешних ссылок.

## Установка модуля с помощью composer
```dotenv
composer require avxman/rel-nofollow
```

## Настройка модуля
После установки модуля не забываем объязательно запустить команды artisan:
`php artisan vendor:publish --tag="avxman-rel-nofollow-config"`,
`php artisan vendor:publish --tag="avxman-rel-nofollow-migrate"`
и после `php artisan migrate`.
Это установит таблицу ссылок для индексации.

### Команды artisan
- Выгружаем все файлы
```dotenv
php artisan vendor:publish --tag="avxman-rel-nofollow-all"
```
- Выгружаем миграционные файлы
```dotenv
php artisan vendor:publish --tag="avxman-rel-nofollow-migrate"
```
- Выгружаем файлы моделек
```dotenv
php artisan vendor:publish --tag="avxman-rel-nofollow-model"
```
- Выгружаем конфигурационные файлы
```dotenv
php artisan vendor:publish --tag="avxman-rel-nofollow-config"
```

## Методы
### Дополнительные (очерёдность вызова метода - первичная)
- **`reset()`** - Сброс параметров класса (очищаем свойства класса)
- **`setEnabled()`** - Вкл./Откл. закрытия от индексации ссылок
- **`setModel()`** - Указываем модель (таблица), где хранятся данные о ссылках для индексации
- **`setExceptModel()`** - Перечисляем модели, где поиск ссылок в тексте не будет учитываться
- **`setPattern()`** - Перечисляем значения в свойстве rel="(.*)" в теге <a\>
- **`setExceptDomain()`** - Перечисляем домены без протокола, которые не будут учитываться при поиске в тексте
- **`setFields()`** - Перечисляем ключи, где производится поиск текста

### Вывод (очерёдность вызова метода - последняя)
- **`getOne()`** - Открываем/Закрываем индексацию для одиночной модели
- **`getMany()`** - Открываем/Закрываем индексацию для множественных моделей
- **`lazyMany()`** - Открываем/Закрываем индексацию для множественных моделей при использовании ленивой загрузки
- **`getString()`** - Результат без привязки модели или моделей - обработка напрямую
- **`errorMessage()`** - Получить список ошибок
- **`saveOne()`** - Сохранение ссылок в базу данных


## Использование метода `saveOne(Model $model) : bool`
Метод может наследовать `Дополнительные методы (очерёдность первичная)`
перед вызовом saveOne()<br>
К примеру:<br>
```injectablephp
// Вариант 1
\Avxman\NoFollow\Facades\NoFollowFacade::saveOne($model::find($model_id));
// Вариант 2
\Avxman\NoFollow\Facades\NoFollowFacade::saveOne($model::first());
// Вариатн 3
$model::lazyById(100)->each(function ($model, $index){
    // Сохраняем отсутствующие ссылки из текста
    // взяты из любой записи (блог, товар, категория и т.д.)
    if(\Avxman\NoFollow\Facades\NoFollowFacade::reset()->saveOne($model)) {
        // После сохранение, запускаем обновление индексации для текущей записи
        // reset(true) - сброс старого списка ссылок для индексации, так как
        // в тексте может появится новая ссылки
        \Avxman\NoFollow\Facades\NoFollowFacade::reset(true)->getOne($model);
    }
});
// Вариант 4
\Avxman\NoFollow\Facades\NoFollowFacade::setFields(['desc', 'title'])->saveOne($model_id);
// Вариант 5
// Можно получать текст из полей взяты из связей
// В модели(ях), где получаем текст для обработки, нужно указать соответствующие связи
// К примеру, в модели \Models\User добавляем связь public function comment(){}
// Вызываем код
// Сохраняем отсутствующие ссылки из текста
if(\Avxman\NoFollow\Facades\NoFollowFacade::setFields(['desc', 'comment.title'])->saveOne($model_id)){
    // Обновляем индексацию в полях ('desc', 'comment.title')
    \Avxman\NoFollow\Facades\NoFollowFacade::setFields(['desc', 'comment.title'])->getOne($model);
}
// Вариант 6
$model::lazyById(100)->each(function ($mod, $index){
    if(NoFollowFacade::reset()->saveOne($mod)) {
        NoFollowFacade::reset(true)->getOne($mod);
    }
});
```

## Примеры получения результатов
#### Вызов в controllers
```injectablephp
use Models\Users;
use Avxman\NoFollow\Facades\NoFollowFacade;

// Очерёдность первичная
NoFollowFacade::reset();
NoFollowFacade::setEnabled(true);
NoFollowFacade::setModel(\Models\Users::class);
NoFollowFacade::setExceptModel([\Models\Users::class]);
NoFollowFacade::setPattern(['nofollow', 'noreferrer']);
NoFollowFacade::setExceptDomain(['text.com', 'test.online']);
NoFollowFacade::setFields(['body', 'name', 'comment.body']);
// Очередность последняя
NoFollowFacade::getOne(Users::find(1));
NoFollowFacade::getMany(Users::limit(50)->get());
NoFollowFacade::lazyMany(Users::class);
NoFollowFacade::getString('либо-какой текст');
NoFollowFacade::errorMessage();

// Также можно вызывать одновременно несколько первичных методов
// и комбинировать методы с первичными и последним
NoFollowFacade::reset()->setExceptDomain(['test.com']);
NoFollowFacade::getOne(Users::first());
// ИЛИ
NoFollowFacade::reset()->setExceptDomain(['test.com'])->getOne(Users::first());

```
