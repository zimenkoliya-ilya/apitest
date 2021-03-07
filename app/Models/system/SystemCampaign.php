<?php

namespace App\Models\system;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemCampaign extends Model
{
    use HasFactory;
    protected $table = "campaigns";
    static function find($id){
        $result = \DB::select()->from('campaigns')->where('id', '=', $id)->execute();
        return current($result->as_array());
    }
    
    static function findAll(){
        
        $result = \DB::select()->from('campaigns');

        if(Model_System_User::getSessionMeta('type') == 'Campaign'){
            $result->where('company_id','=', Model_System_User::getSessionMeta('company_id'))
                ->where('group_id','=', Model_System_User::getSessionMeta('campaign_group_id'))
                ->order_by('name', 'ASC');
        }else{
            $result->where('company_id','=', Model_System_User::getSessionMeta('company_id'))->order_by('name', 'ASC');
        }

        $result->where('company_id','=', Model_System_User::getSessionMeta('company_id'))->order_by('name', 'ASC');
        return $result->execute()->as_array();
        
    }

    static function findByCompany($company_id){

        $query = \DB::select()->from('campaigns');
        $query->where('company_id','=',$company_id);
        $query->order_by('name', 'ASC');
        return $query->execute()->as_array();

    }

    static function findByCompanyCaseSum($company_id){

        $query = \DB::select(\DB::expr('COUNT(case_statuses.case_id) as cases'), 'campaigns.*')->from('campaigns');
        $query->join('case_statuses','left')->on('case_statuses.campaign_id','=','campaigns.id');
        $query->where('campaigns.company_id','=',$company_id);
        $query->order_by('campaigns.name', 'ASC');
        $query->group_by('campaigns.id');
        return $query->execute()->as_array();

    }


    static function findAllInNetwork(){
        $query = \DB::select()->from('campaigns');
        $query = Model_Networks::queryNetwork($query, Model_System_User::getSessionMeta('network_companies'));

        if(Model_System_User::getSessionMeta('type') == 'Campaign'){
        $query->where('group_id','=', Model_System_User::getSessionMeta('campaign_group_id'));
        }

        $query->order_by('name', 'ASC');
        return $query->execute()->as_array();
    }
    
    static function findByName($name){
        $result = \DB::select()->from('campaigns')->where('name', '=', $name)->execute();
        if(count($result)){
            return current($result->as_array());
        }else{
            return array();
        }
    }

    static function findByGroup($id){
        $result = \DB::select()->from('campaigns')->where('group_id', '=', $id)->execute();
        return $result->as_array();

    }
    
    static function add($data){
        
        $groups = array();
        if(isset($data['groups'])){
            $groups = $data['groups'];
            unset($data['groups']);
        }
        
        $result = \DB::insert('campaigns')->set($data)->execute();
        Model_System_DistributionCampaign::set($result[0], $groups);
        
        return current($result);
    }
    
    static function update($id, $data){
        
        $groups = array();
        if(isset($data['groups'])){
            $groups = $data['groups'];
            unset($data['groups']);
        }
        
        $result = \DB::update('campaigns')->set($data)->where('id', '=', $id)->execute();
        Model_System_DistributionCampaign::set($id, $groups);
    }
    
    static function delete($id){
        $result = \DB::delete('campaigns')->where('id','=',$id)->execute();
        Model_System_DistributionCampaign::delete($id);
    }
    
    static function validate($factory){
        
        $val = \Validation::forge($factory);

        $val->add('name', 'Name')
            ->add_rule('required');

        return $val;
    }
    
}
