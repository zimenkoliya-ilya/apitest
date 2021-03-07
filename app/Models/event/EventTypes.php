<?php

namespace App\Models\event;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\system\SystemCompany;
class EventTypes extends Model
{
    use HasFactory;
    protected $table = "event_types";
    static function findAll()
    {
        $result = EventTypes::all()->toArray();
        return $result;

    }

    static function findByCompanyOwnerAndShared($company_id){

        // Get All Company Owned and Shared Events
        $result = EventTypes::select('event_types.*')
            ->leftJoin('event_types_co', 'event_types_co.event_type_id', 'event_types.id')
            ->where('event_types_co.company_id', $company_id) // Shared
            ->orWhere('event_types.company_id', $company_id) // Owned
            ->get()
            ->toArray();
        return $result;
    }


    static function setupCompanies(){
        $companies = SystemCompany::findAll();
        $event_types = self::findAll();
        foreach($companies as $co){
            foreach($event_types as $et){
                EventCompany::create(['company_id' => $co['id'], 'event_type_id' => $et['id']]);
            }
        }
    }
}
