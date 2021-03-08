<?php

namespace App\Models\system\serivices;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemServicesAccess extends Model
{
    use HasFactory;
    const BULLETIN_EMAILS = 'email.bulletins';
    const SHARK_TANK = 'sharktank';
    const TRUST_RELEASE_PAYMENT = 'trust.release';
    const EMAIL_SERVICE = 'email';
    const FINANCING_CHARGEBACKS = 'financing.reports.chargebacks';
    const SMS = 'sms';

    function before(){

    }

    static function check($key, $company_id)
    {

        $query = SystemServicesCompany::select('services_companies.*','services.key','services.name')->leftJoin('services', 'services.id', 'services_companies.service_id');
        $query->where('key', $key)->where('company_id', $company_id);
        $result = $query->get();
        return current($result->toArray());
    }



    static function find($id){
        $result = \DB::select()->from('services')->where('id', '=', $id)->execute();
        return current($result->as_array());
    }

    static function findAll(){

        $result = \DB::select()->from('services')
            ->execute();
        return $result->as_array();

    }

    static function add($data){
        $result = \DB::insert('services')->set($data)->execute();
        return current($result);
    }

    static function update($id, $data){
        $result = \DB::update('services')->set($data)->where('id', '=', $id)->execute();
    }

    static function delete($id){
        $result = \DB::delete('services')->where('id','=',$id)->execute();
    }

    static function validate($factory){

        $val = \Validation::forge($factory);

        return $val;
    }

}
