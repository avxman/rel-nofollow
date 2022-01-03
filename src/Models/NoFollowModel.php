<?php

namespace Avxman\NoFollow\Models;

use Illuminate\Database\Eloquent\Model;

class NoFollowModel extends Model
{

    protected $table = 'nofollow';
    protected $guarded = [];
    public $timestamps = false;

    public function scopeEnabled($query){
        return $query->where('enabled', 1);
    }

}
