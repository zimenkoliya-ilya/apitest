<?php

namespace App\Models\system\serivices;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemServicesCompanies extends Model
{
    use HasFactory;
    static function findByServiceIdCompanyId($service_id, $company_id)
    {

        $query = \DB::select()->from('services_companies');
        $query->where('service_id', '=', $service_id)->where('company_id','=',$company_id);
        $result = $query->execute();
        return current($result->as_array());
    }

    static function getCompanyIdsByServiceId($service_key)
    {

        $query = \DB::select()->from('services_companies');
        $query->join('services','LEFT')->on('services.id','=','services_companies.service_id');
        $query->where('services.key', '=', $service_key);
        $result = $query->execute()->as_array();

        if($result){
            $ids = array();
            foreach($result as $id){
                $ids[] = $id['company_id'];
            }
            return $ids;
        }

        return false;
    }


    static function findAllByCompany($company_id, $format='form'){

        $query = \DB::select('services.*')->from('services_companies')
            ->join('services','LEFT')->on('services.id','=','services_companies.service_id')
            ->where('company_id','=',$company_id)
            ->execute();
        $result = $query->as_array();


        if($result){
            if($format == 'form') {

                foreach ($result as $item) {
                    $ids[] = $item['service_id'];
                }
                return $ids;
            }else{
                return $result;
            }
        }

        return false;

    }


    static function upsert($company_id, $service_ids)
    {

        \DB::delete('services_companies')->where('company_id', '=', $company_id)->execute();

        $query = \DB::insert('services_companies')->columns(array('service_id', 'company_id'));

        // Exclude Dupes
        $ids = array_unique($service_ids);

        foreach ($ids as $id) {
            $query->values(array($id, $company_id));
        }

        $result = $query->execute();

        return $result;

    }

}
