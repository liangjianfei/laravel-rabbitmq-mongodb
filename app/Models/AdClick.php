<?php

namespace App\Models;

use Jenssegers\Mongodb\Eloquent\Model;

class AdClick extends Model
{
    protected $connection = 'mongodb';

    protected $collection = 'ad_clicks';

    protected $fillable = ['ip','ad_index','ip2long'];
}
