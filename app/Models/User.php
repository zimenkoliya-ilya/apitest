<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;
    protected $fillable = [
        'account_id',
        'company_id',
        'region_id',
        'last_name',
        'mobile',
        'email',
        'email_verified_at',
        'password',
        'passwd',
        'remember_token',
        'active',
        'extension',
        'type',
        'wildcard',
        'dashboard_id',
        'signature_id',
        'campaign_group_id',
        'department_id',
        'phone_pass',
        'caller_id',
        'created_by',
        'updated_by',
    ];  
    static function findAll(){

        $users = array();
        $result = User::select('id','first_name','last_name')
                    ->where('active', 1)
                    ->orderBy('last_name')
                    ->get();

        if(count($result)){
            $users = $result->toArray();
        }
        return $users;

    }

    static function findCompanyByUserMobile($mobile_phone){


        $result = current(User::select('company_id')
            ->where('active', 1)
            ->where('mobile', $mobile_phone)
            ->get()->toArray());

        if($result){
            return $result['company_id'];
        }
        return false;

    }

    static function findCompanyByUserId($user_id){
        $result = current(User::select('company_id')
            ->where('active', 1)
            ->where('id', $user_id)
            ->get()->toArray());

        if($result){
            return $result['company_id'];
        }
        return false;

    }



    static function findCompanyByRole($company_id, $role_id){

        $users = array();
        $result = User::select('id','first_name','last_name')
            ->join('rbac_')
            ->where('active', 1)
            ->orderBy('last_name')
            ->get();

        if(count($result)){
            $users = $result->toArray();
        }
        return $users;

    }
}
