<?php

namespace Avxman\NoFollow\Facades;

use Avxman\NoFollow\Providers\NoFollowServiceProvider;
use Illuminate\Database\Eloquent\Collection as Collections;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Facade;
use Avxman\NoFollow\Classes\NoFollowClass;

/**
 * Фасад вкл./откл. внешних ссылок в контексте на сайте
 *
 * @method static NoFollowClass reset(bool $resetLinks = false)
 * @method static NoFollowClass setEnabled(bool $enabled)
 * @method static NoFollowClass setModel(array $models)
 * @method static NoFollowClass setExceptModel(array $models, bool $overwrite = false)
 * @method static NoFollowClass setPattern(array $pattern, bool $overwrite = false)
 * @method static NoFollowClass setExceptDomain(array $except_domain, bool $overwrite = false)
 * @method static NoFollowClass setFields(array $fields, bool $overwrite = false)
 * @method static Model getOne(Model $model)
 * @method static Collections getMany(Collections $models)
 * @method static void lazyMany($model, bool $is_query = false)
 * @method static string getString(string $description = '')
 * @method static bool saveOne(Model $model)
 * @method static array errorMessage()
 *
 * @see NoFollowClass
 */
class NoFollowFacade extends Facade
{

    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return NoFollowServiceProvider::class;
    }

}
