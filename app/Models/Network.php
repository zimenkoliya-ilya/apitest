<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Network extends Model
{
    use HasFactory;
    static function findAllInNetwork($user_id)
    {
        $query = User::where('created_by', $user_id);
        $result = $query->get()->toArray();
        if($result){
            return $result;
        }
        return false;
    }
}
