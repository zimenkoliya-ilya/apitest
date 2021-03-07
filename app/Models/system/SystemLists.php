<?php

namespace App\Models\system;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemLists extends Model
{
    use HasFactory;
    protected $table = "lists";
    static function find_($id){
        $result = SystemLists::find($id)->toArray();
        return $result;
    }
    
    static function findAll(){
        
        $result = \DB::select()->from('lists')
            ->execute();
        return $result->as_array();
        
    }

    static function findByUser($user_id){

        $result = \DB::select()->from('lists')
            ->where('created_by','=',$user_id)
            ->execute();
        return $result->as_array();

    }

    static function findByUserSystem($user_id){

        $result = \DB::select()->from('lists')
            ->where('created_by','=',$user_id)
            ->or_where('system','=',1)->order_by('id', 'ASC')
            ->execute();
        return $result->as_array();

    }

    static function findByFilter($filter, $columns){

        $query = \DB::select_array($columns)
            ->from(array('lists', 'l'));

        if(isset($filter['list_id'])){
            $query->where('list_id','=',$filter['list_id']);
        }

        if(isset($filter['created_by'])){
            $query->where('created_by','=',$filter['created_by']);
        }

        $result = $query->execute()->as_array();
        return $result;

    }

    static function findAllInNetwork(){

        $query = \DB::select()->from(array('lists', 'l'));
        $query = Model_Networks::queryNetwork($query, Model_System_User::getSessionMeta('network_companies'));
        $query->where('l.company_id','!=',Model_System_User::getSessionMeta('company_id'));
        $result = $query->execute()->as_array();
        return $result;

    }

    static function findByCompany($company_id){

        $query = \DB::select()->from(array('lists', 'l'));
        $query->where('l.company_id','=',$company_id)->where('company_shared', '=', 1);
        $result = $query->execute()->as_array();
        return $result;

    }

    static function findByType($type, $user_id = null, $company_id = null){

        $query = \DB::select()->from(array('lists', 'l'));

        $query->where('type','=',$type);

        if($user_id){
            $query->where('created_by','=',$user_id);
        }

        if($company_id){
            $query->where('company_id','=',$company_id);
        }

        $result = $query->execute()->as_array();
        return $result;

    }

    static function findAllShared($user_id){

        $query = \DB::select('l.*')->from(array('lists', 'l'))
            ->join('list_users')->on('list_users.list_id','=','l.id');
        $query->where('list_users.user_id', '=', $user_id);
        $result = $query->execute()->as_array();
        return $result;

    }
    
    static function add($data){
        $result = \DB::insert('lists')->set($data)->execute();
        return current($result);
    }
    
    static function update_($id, $data){
        $result = \DB::update('lists')->set($data)->where('id', '=', $id)->execute();
    }
    
    static function delete_($id){
        $result = \DB::delete('lists')->where('id','=',$id)->execute();
    }
    
    static function validate($factory){
        
        $val = \Validation::forge($factory);


        return $val;
    }
    
}
