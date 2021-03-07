<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentTypes extends Model
{
    use HasFactory;
    protected $table = "document_types";
    static function find_($id){
        $result = \DB::select()
            ->from('document_types')
            ->where('id', '=', $id)
            ->execute()->as_array();
        return current($result);
    }
}
