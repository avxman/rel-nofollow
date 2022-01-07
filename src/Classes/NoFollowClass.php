<?php

namespace Avxman\NoFollow\Classes;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection as Collections;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NoFollowClass
{

    // Свойства конфигурационные
    /**
     * Вкл./Откл. работы индексации ссылок
     * @var bool $enabled = true
     */
    protected bool $enabled = true;

    /**
     * Модель - все ссылки с работой по индексации
     * @var array $model = []
     */
    protected array $model = [];

    /**
     * Список исключаемых моделей
     * @var array $except_model = []
     */
    protected array $except_model = [];

    /**
     * Список паттернов для поиска и замены ссылок в текстах
     * @var array $pattern = []
     */
    protected array $pattern = [];

    /**
     * Список исключаемых доменов
     * @var array $except_domain = []
     */
    protected array $except_domain = [];

    /**
     * Поля (свойства объекта), где ищем текст по работе с индексацией
     * @var array $fields = []
     */
    protected array $fields = [];

    // Свойства общие
    /**
     * Модель - где будет искать текст
     * @var ?Model
     */
    protected ?Model $initModel;

    /**
     * Список всех найденных текстов для работы с индексацией
     * @var array $description = []
     */
    protected array $description = [];

    /**
     * Статус ошибки и его сообщения
     * @var array $error = ['status'=>false, 'message'=>[]]
     */
    protected array $error = ['status'=>false, 'message'=>[]];

    /**
     * Список всех ссылок сгруппированные на два типа: закрытие от индексации; открытые от индексации
     * @var array $listLink = ['enabled'=>[], 'disabled'=>[]]
     */
    protected array $listLink = ['enabled'=>[], 'disabled'=>[]];

    /**
     * Паттерн свойства rel="(.*)" тега <a>
     * @var string $pattern_rel = ''
     */
    protected string $pattern_rel = '';

    /**
     * Количество обрабатываемых моделей за один подход
     * @var int $lazy_count = 500
     */
    protected int $lazy_count = 500;

    /**
     * Список найденных новый ссылок в текстах
     * @var array $link_new = []
     */
    protected array $link_new = [];

    // Закрытые общие методы
    /**
     * Записываем найдены ошибки в работе индексации ссылок
     * @param string $message
     * @return void
     */
    protected function setErrorMessage(string $message) : void{
        $this->error['status'] = true;
        $this->error['message'][] = $message;
    }

    /**
     * Получаем сообщение об ошибке в работе индексации ссылок
     * @return array
     */
    protected function getErrorMessage() : array{
        return $this->error['status'] ? $this->error['message'] : [];
    }

    /**
     * Инициализация параметров (свойств) класса
     * @return void
     */
    protected function initParams() : void{
        if(!config()->has('nofollow')) {
            $this->setErrorMessage('Отсутствует конфигурационный файл');
            return;
        }
        $config = collect(config()->get('nofollow'));
        $this->enabled = $config->get('enabled');
        $this->model = $config->get('model');
        $this->except_model = $config->get('except_model');
        $this->pattern = $config->get('pattern');
        $this->except_domain = $config->get('except_domain');
        $this->fields = $config->get('fields');
        $this->initModel = null;
        $this->description = $this->link_new = [];
        $this->pattern_rel = implode(' ', $this->pattern);
    }

    /**
     * Проверяем на ошибку при индексации ссылок
     * @return bool
     */
    protected function isError() : bool{
        return $this->error['status'];
    }

    /**
     * Проверка на работоспособность класса
     * @param bool $is_string = false
     * @return bool
     */
    protected function isValid(bool $is_string = false) : bool{
        $is_error = $this->isError();
        $is_enabled = $this->enabled;
        $is_pattern = count($this->pattern) > 0;
        $is_all = !$is_error && $is_enabled && $is_pattern;
        if($is_string){
            $is_except_model = $this->initModel && in_array($this->initModel, $this->except_model);
            $is_field = count($this->fields) > 0;
            $is_all += !$is_except_model && $is_field;
        }
        return $is_all;
    }

    /**
     * Сброс всех найденных текстов
     * @return NoFollowClass
     */
    protected function resetDescription() : self{
        $this->description = [];
        return $this;
    }

    /**
     * Поиск и замена ссылок по паттернам
     * @return void
     */
    protected function listLinks() : void{
        $skipLinks = $this->model['nofollow']::enabled()->where(function ($query){
            if(!count($this->except_domain)) return $query;
            $query->where('domain', 'like', '%'.$this->except_domain[0].'%');
            foreach (collect($this->except_domain)->forget(0)->toArray() as $domain){
                $query->orWhere('domain', 'like', '%'.$domain.'%');
            }
            $query->orWhereNull('domain')->orWhere('domain', '');
        })->get('id')->pluck('id')->toArray();
        $links = $this->model['nofollow']::enabled()->whereNotIn('id', $skipLinks)->get();
        $this->listLink['enabled'] = $links->where('follow', 1)->map(function ($link){
            $domain = addcslashes($link->domain, "%:./'#\"");
            $link->pattern = "#(.*?)href=\"(https|http)\:\/\/".$domain.".*?\>(.*?)#ui";
            $link->replace = "$1href=\"https://".$link->domain."\" rel=\"".$this->pattern_rel."\">$4";
            return $link;
        })->toArray();
        $this->listLink['disabled'] = $links->where('follow', 0)->map(function ($link){
            $domain = addcslashes($link->domain, "%:./'#\"");
            $link->pattern = "#(.*?)href=\"(https|http)\:\/\/".$domain.".*?\>(.*?)#ui";
            $link->replace = "$1href=\"https://".$link->domain."\">$3";
            return $link;
        })->toArray();
    }

    /**
     * Добавляем текст для обработки при найденных ссылок в тексте из модели
     * @param Model $model
     * @return NoFollowClass
     */
    protected function setDescriptionModel(Model $model) : self{
        collect($this->fields)->each(function ($field) use ($model){
            $description = '';
            if(Str::contains($field, '.')){
                $relation = explode('.', $field);
                if($model->isRelation($relation[0])){
                    $description = $model->{$relation[0]}->{$relation[1]}??'';
                }
            }
            else $description = $model->{$field}??'';
            if(
                Str::contains($description, collect($this->listLink['enabled'])->pluck('domain')->toArray())
                || Str::contains($description, collect($this->listLink['disabled'])->pluck('domain')->toArray())
                || Str::contains($description, '<a')
            )
                $this->description[$model->id][$field] = $description;
        });
        return $this;
    }

    /**
     * Добавляем текст для обработки при найденных ссылок в тексте для метода getString()
     * @param string $text
     * @return NoFollowClass
     */
    protected function setDescriptionString(string $text) : self{
        if(
            Str::contains($text, collect($this->listLink['enabled'])->pluck('domain')->toArray())
            || Str::contains($text, collect($this->listLink['disabled'])->pluck('domain')->toArray())
        )
            $this->description[0]['default'] = $text;
        return $this;
    }

    /**
     * Изменяем свойство rel ссылки в зависимости от типа закрытие/открытие индексации ссылки
     * @param string $description
     * @param string $key
     * @param string $index
     * @param bool $is_disabled = false
     * @return NoFollowClass
     */
    protected function writeDescriptionResult(string $description, string $key, string $index, bool $is_disabled = false) : self{
        $links = collect($is_disabled ? $this->listLink['disabled'] : $this->listLink['enabled']);
        $pattern = $links->pluck('pattern')->toArray();
        $replace = $links->pluck('replace')->toArray();
        $desc = preg_replace($pattern, $replace, $description);
        if($desc !== $description) $this->description[$key][$index] = $desc;
        return $this;
    }

    /**
     * Вызываем изменения текста при найденных ссылок для закрытия / открытия индексации ссылок
     * @return NoFollowClass
     */
    protected function writeDescription() : self{
        if(!$this->description) return $this;
        collect($this->description)->each(function ($desc, $index){
            collect($desc)->each(function ($description, $key) use ($index){
                if(!empty($description)) $this
                    ->writeDescriptionResult($description, $index, $key)
                    ->writeDescriptionResult($description, $index, $key, true);
            });
        });
        return $this;
    }

    /**
     * Получаем измененный текст для модели
     * @param Model $model
     * @param array $description
     * @return void
     */
    protected function writeDescriptionModel(Model $model, array $description) : void{
        collect($description)->each(function ($desc, $index) use ($model){
            if(Str::contains($index, '.')){
                $relation = explode('.', $index);
                if($model->isRelation($relation[0])){
                    $model->{$relation[0]}->{$relation[1]} = $desc;
                }
            }
            else $model->{$index} = $desc;
        });
    }

    /**
     * Получаем измененный текст для метода getString()
     * @param string $description = ''
     * @return string
     */
    protected function writeDescriptionString(string $description = '') : string{
        return $this->description[0]['default']??$description;
    }

    /**
     * Проверяем и добавляем новую ссылку взятую из текста для сохранения в таблицу
     * @param string $description
     * @return void
     */
    protected function initLinkFind(string $description) : void{
        $links = [];
        preg_match_all('/\"(https\:\/\/|http\:\/\/)(.*?)\"/ui', $description, $links);
        if(is_array($links) && ($links[2]??false)){
            foreach ($links[2]??[] as $key=>$url){
                $link = trim($links[0][$key], '"');
                $domain = trim($url, '"');
                if(($dom = stristr($domain, '/', true)) === false)
                    $dom = $domain;
                if(filter_var($link, FILTER_VALIDATE_URL) !== false
                    && !in_array($dom, $this->except_domain)
                    && !in_array($domain, collect($this->listLink['enabled'])->pluck('domain')->toArray())
                    && !in_array($domain, collect($this->listLink['disabled'])->pluck('domain')->toArray())
                    && !in_array($domain, collect($this->link_new)->pluck('domain')->toArray())
                ) $this->link_new[] = ['enabled'=>1, 'follow'=>1, 'domain'=>Str::limit($domain, 255)];
            }
        }
    }

    /**
     * Поиск новой ссылки найденной в тексте
     * @return NoFollowClass
     */
    protected function linkNewFind() : self{
        if(!$this->description) return $this;
        collect($this->description)->each(function ($desc, $index){
            collect($desc)->each(function ($description) use ($index){
                if(!empty($description)) $this->initLinkFind($description);
            });
        });
        return $this;
    }

    /**
     * Сохраняем найденные ссылки в таблицу
     * @return bool
     */
    protected function save() : bool{
        $status = false;
        DB::transaction(function() use (&$status) {
            $status = $this->model['nofollow']::insert($this->link_new);
        });
        return $status;
    }

    /**
     * Конструктор класса
     */
    public function __construct(){
        $this->initParams();
        $this->listLinks();
    }

    // Перезаписывающие методы
    /**
     * Сброс параметров класса (очищаем свойства класса)
     * @param bool $resetLinks = false
     * @return NoFollowClass
     */
    public function reset(bool $resetLinks = false) : self{
        $this->initParams();
        if($resetLinks) $this->listLinks();
        return $this;
    }

    /**
     * Вкл./Откл. закрытия от индексации ссылок
     * @param bool $enabled
     * @return NoFollowClass
     */
    public function setEnabled(bool $enabled) : self{
        $this->enabled = $enabled;
        return $this;
    }

    /**
     * Указываем модель (таблица), где хранятся данные о ссылках для индексации
     * @param array $models
     * @return NoFollowClass
     */
    public function setModel(array $models) : self{
        $this->model = $models;
        return $this;
    }

    /**
     * Перечисляем модели, где поиск ссылок в тексте не будет учитываться
     * @param array $models
     * @param bool $overwrite = false
     * @return NoFollowClass
     */
    public function setExceptModel(array $models, bool $overwrite = false) : self{
        $this->except_model = $overwrite ? $models : array_merge($this->except_model, $models);
        return $this;
    }

    /**
     * Перечисляем значения в свойстве rel="(.*)" в теге <a>
     * @param array $pattern
     * @param bool $overwrite = false
     * @return NoFollowClass
     */
    public function setPattern(array $pattern, bool $overwrite = false) : self{
        $this->pattern = $overwrite ? $pattern : array_merge($this->pattern, $pattern);
        return $this;
    }

    /**
     * Перечисляем домены без протокола, которые не будут учитываться при поиске в тексте
     * @param array $except_domain
     * @param bool $overwrite = false
     * @return NoFollowClass
     */
    public function setExceptDomain(array $except_domain, bool $overwrite = false) : self{
        $this->except_domain = $overwrite ? $except_domain : array_merge($this->except_domain, $except_domain);
        return $this;
    }

    /**
     * Перечисляем ключи, где производится поиск текста
     * @param array $fields
     * @param bool $overwrite = false
     * @return NoFollowClass
     */
    public function setFields(array $fields, bool $overwrite = false) : self{
        $this->fields = $overwrite ? $fields : array_merge($this->fields, $fields);
        return $this;
    }

    /**
     * Инициализация класса для одиночной модели
     * @param Model $model
     * @return Model
     */
    public function getOne(Model $model) : Model{
        $this->initModel = $model;
        if(!$this->isValid()) return $model;
        $this->resetDescription()->setDescriptionModel($model)->writeDescription();
        if(!$this->description) return $model;
        $this->writeDescriptionModel($model, $this->description[$model->id]);
        $model->save();
        return $model;
    }

    /**
     * Инициализация класса для множественных моделей
     * @param Collections $models
     * @return Collections
     */
    public function getMany(Collections $models) : Collections{
        if(!$this->isValid()) return $models;
        $models->map(function ($_self){
            return $this->getOne($_self);
        });
        return $models;
    }

    /**
     * Инициализация класса для множественных моделей при использовании ленивой загрузки
     * @param
     * @param bool $is_query = false
     * @return void
     */
    public function lazyMany($model, bool $is_query = false) : void{
        ($is_query ? $model->lazyById($this->lazy_count) : $model::lazyById($this->lazy_count))->each(function ($model, $index){
            $this->getOne($model);
            return true;
        });
    }

    /**
     * Результат без привязки модели или моделей обработка напрямую
     * @param string $description = ''
     * @return string
     */
    public function getString(string $description = '') : string{
        if(empty($description) || !$this->isValid(true)) return $description;
        return $this->setDescriptionString($description)->writeDescription()->writeDescriptionString($description);
    }

    /**
     * Сохранение ссылок в базу данных
     * @param Model $model
     * @return bool
     */
    public function saveOne(Model $model) : bool{
        $this->initModel = $model;
        if(!$this->isValid()) return false;
        $this->resetDescription()->setDescriptionModel($model)->linkNewFind();
        if(!count($this->link_new)) return false;
        return $this->save();
    }

    /**
     * Список ошибок
     * @return array
     */
    public function errorMessage() : array{
        return $this->getErrorMessage();
    }

}
