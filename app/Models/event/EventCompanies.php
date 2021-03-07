<?php

namespace App\Models\event;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventCompanies extends Model
{
    use HasFactory;
    protected $table = "event_types_co";
    protected $fillable = [
        'company_id',
        'event_type_id',
    ];
    static function add($event_type_id, $company_id){
        $data = array(
            'event_type_id' => $event_type_id,
            'company_id' => $company_id
        );
        $result = EventCompanies::create($data);
        return current($result);
    }
}
