<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Json_file extends Model
{
    protected $table = 'json_files';
    
    protected $fillable = [
        'file_path',
        'campaign_id',
    ];

   
}
