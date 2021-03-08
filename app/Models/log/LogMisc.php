<?php

namespace App\Models\log;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LogMisc extends Model
{
    use HasFactory;
    protected $table = "log_misc";
    static function findByType($type, $case_id, $limit = null)
    {
        $query = LogMisc::where('case_id',$case_id)
            ->where('type', $type);
        if ($limit == 1) {
            $query->limit(1);
            return current($query->get()->toArray());
        }
        return $query->get()->toArray();
    }
}
