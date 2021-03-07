<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Log_error extends Model
{
    use HasFactory;
    protected $fillable =[
        'case_id',
        'name',
        'message',
        'user',
        'created',
    ];
}
