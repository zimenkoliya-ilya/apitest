<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Networks extends Model
{
    use HasFactory;
    protected $table = "networks";
    static function queryNetwork($query, $company_ids,$alias=null,$column=null){

        if(Account::getUserId() == 1 || Account::getCompanyId() == 1 || !isset($_SESSION['user'])){
            return $query;
        }

        if(!$column){
            $column = 'company_id';
        }

        if(is_array($company_ids)) {
            if($alias){
                $query->where($alias.'.'.$column, 'IN', $company_ids);
            }else {
                $query->where($column, 'IN', $company_ids);
            }
        }else{
            if($alias){
                $query->where($alias.'.'.$column, '=', $company_ids);
            }else {
                $query->where($column, '=', $company_ids);
            }
        }
        return $query;
    }

    static function hasAccess($user_company, $network_company){
        if($user_company === $network_company){
            return true;
        }

        if(self::inNetwork($user_company, $network_company)){
            return true;
        }

        return false;
    }


    static function inNetwork($user_company, $network_company){

        $query = Networks::select('networks.id')
            ->join('networks_companies', 'networks_companies.network_id', 'networks.id')
            ->where('networks.company_id', $user_company)
            ->where('networks_companies.company_id', $network_company)
            ->get()
            ->toArray();
        return current($query);
    }
    // ???
    static function queryNetworkCases($queryObject, $company_ids){
        //var_dump($company_ids); exit;
        if(is_array($company_ids)) {
            $queryObject->whereIn('c.company_id', $company_ids);
        }else{
            $queryObject->where('c.company_id', $company_ids);
        }
        return $queryObject;
    }


    static function getNetworksByCompany($company_id){
        $network_ids = self::findByCompany($company_id);
        if($network_ids){
            $network_companies = self::findCompaniesByNetworks($network_ids);
            return $network_companies;
        }
        return false;
    }


    static function findByCompany($company_id){
        $query = Networkscompanies::select('network_id')
            ->where('company_id', $company_id);
        return $query->get()->toArray();
    }

    static function findCompaniesByNetworks($network_ids){
        $query = Networkscompanies::select('company_id');
    
        foreach($network_ids as $id){
            $query->orWhere('network_id', $id);
        }

        $query->groupBy('company_id');
        return $query->get()->toArray();
    }

    static function findCompaniesByNetwork($network_id){

        $query = Networkscompanies::where('network_id', $network_id);
        $query->groupBy('company_id');

        return $query->get()->toArray();
    }

    static function findCompanyNetworkIds($company_id){

        if($company_id != 1) {
            $query = Networks::select('nc.company_id')->from('networks as n')
                ->leftJoin('networks_companies as nc', 'nc.network_id', 'n.id')
                ->where('n.company_id', $company_id)->groupBy('nc.company_id');
        }else{
            // System Access
            $query = Company::select('id as company_id');
        }

        $result = $query->get()->toArray();

        if($result){
            $ids = array();
            foreach($result as $cid){
                $ids[] = $cid['company_id'];
            }
            return $ids;
        }

        return array($company_id);
    }

    static function findAllByCompanyNetworks($company_id){

        $query = Networkscompanies::
            leftJoin('networks_companies as nc', 'nc.network_id', 'networks_companies.network_id')
            ->where('company_id', $company_id)
            ->groupBy('nc.company_id');
        $result = $query->get()->toArray();
        if($result){
            $ids = array();
            foreach($result as $co){
                $ids[] = $co['company_id'];
            }
            return $ids;
        }

        return false;
    }

}
