<?php

namespace App\Models\system;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemUser extends Model
{
    use HasFactory;
    protected $table = "users";
    static function find_($id){
        $result = \DB::select()->from('users')->where('id', '=', $id)->execute();
        return current($result->as_array());
    }

    static function is_active($id){
        $result = current(DB::select()->from('users')->where('id', '=', $id)->execute()->as_array());
        if($result && $result['active'] == 1){
            return true;
        }

        return false;
    }


    static function findByCompany($company_id){
        $query = \DB::select()->from('users');
        $result = $query->where('active', '=', 1)
            ->where('company_id', '=', $company_id)
            ->order_by('first_name', 'asc')
            ->execute()->as_array();
        return $result;
    }

    static function findByCompanySort($company_id, $sort){
        $query = \DB::select()->from('users');
        $result = $query->where('active', '=', 1)
            ->where('company_id', '=', $company_id)
            ->order_by($sort['order_by'], $sort['direction'])
            ->execute()->as_array();
        return $result;
    }


    static function findByIds($ids){

        $result = \DB::select()->from('users')->where('id', 'IN', $ids)->execute();
        return current($result->as_array());

    }
    
    static function findAllActive(){

        $query = \DB::select('users.*',array('companies.name','company_name'), \DB::expr('CONCAT(users.first_name, " ", users.last_name) as name'))->from('users')
            ->join('companies','left')->on('companies.id','=','users.company_id');

        $query = Model_Networks::queryNetwork($query, \Model_Account::getNetworkIds(),'users');

        $result = $query->where('active', '=', 1)->order_by('first_name', 'asc')->execute()->as_array();
        return $result;
        
    }


    static function findAllActiveByCompany(){

        $query = \DB::select('users.*',array('companies.long_name','company_name'), \DB::expr('CONCAT(users.first_name, " ", users.last_name) as name'))->from('users')
            ->join('companies','left')->on('companies.id','=','users.company_id')->where('active', '=', 1)->order_by('first_name', 'asc');
        $query = Model_Networks::queryNetwork($query, \Model_Account::getNetworkIds(),'users');
        $result = $query->execute()->as_array();

        //\Model_Log::append('access',\DB::last_query());

        if($result){
            $users = array();
            foreach($result as $u){
                $users[$u['company_name']][] = $u;
            }
            return $users;
        }

        return false;

    }

    static function findActiveListByCompany(){

        $query = \DB::select('users.*',array('companies.long_name','company_name'), \DB::expr('CONCAT(users.first_name, " ", users.last_name) as name'))->from('users')
            ->join('companies','left')->on('companies.id','=','users.company_id')->where('active', '=', 1)->order_by('first_name', 'asc');
        $query = Model_Networks::queryNetwork($query, \Model_Account::getNetworkIds(),'users');
        $result = $query->execute()->as_array();

        //\Model_Log::append('access',\DB::last_query());

        if($result){
            return $result;
        }

        return false;

    }

    static function findAllActiveWithRoles(){

        $query = \DB::select('users.*','companies.name', 'rbac_roles.description')
            ->from('users')
            ->join('rbac_userroles','left')->on('rbac_userroles.user_id','=','users.id')
            ->join('rbac_roles','left')->on('rbac_roles.id','=','rbac_userroles.role_id')
            ->join('companies','left')->on('companies.id','=','users.company_id');


        $query = Model_Networks::queryNetwork($query, \Model_Account::getNetworkIds(),'users');

        $result = $query->where('active', '=', 1)->order_by('first_name', 'asc')->execute()->as_array();

        if($result){
            $users = array();
            foreach($result as $r){
                $users[$r['id']] = $r;
            }
            foreach($result as $rr){
                $users[$rr['id']]['roles'][] = $rr['description'];
            }
            return $users;
        }

        return false;

    }

    static function findAllActiveInNetwork(){

        $query = \DB::select('users.*','companies.name')->from('users')
            ->join('companies','left')->on('companies.id','=','users.company_id');
        $query = Model_Networks::queryNetwork($query, \Model_Account::getNetworkIds(),'users');
        $result = $query->where('active', '=', 1)->order_by('first_name', 'asc')->execute()->as_array();
        return $result;

    }

    static function findAllActiveInCaseNetwork($company_id){

        $query = \DB::select('users.*','companies.name')
            ->from('users')
            ->join('companies','left')->on('companies.id','=','users.company_id');

        $query = Model_Networks::queryNetwork($query, \Model_Account::getNetworkIds(),'users');

        $result = $query->where('active', '=', 1)->order_by('first_name', 'asc')->execute()->as_array();
        return $result;

    }

    static function findAll(){

        $query = \DB::select('users.*','companies.name',array('companies.long_name','company_name'),'user_profile.picture_filename')
            ->from('users')
            ->join('companies','left')->on('companies.id','=','users.company_id')
            ->join('user_profile','left')->on('user_profile.user_id','=','users.id');

        $query = Model_Networks::queryNetwork($query, \Model_Account::getNetworkIds(),'users');

        $result = $query->order_by('users.first_name', 'asc')->execute()->as_array();
        return $result;

    }
    
    static function findAllSortLastName($account_id=null){
        
        $query = \DB::select()->from('users')->where('active', '=', 1);
        if ($account_id != null) {
            $query->where('account_id', '=', $account_id);
        }
        $query->order_by('last_name', 'asc');
        $result = $query->execute();
        return $result->as_array();
        
    }

    static function getName($id){
        $user = self::find($id);
        if (isset($user) && !empty($user)) {
            return $user['last_name'].', '.$user['first_name'];
        } else {
            return 'No user found.';
        }            
    }

    static function getSessionMeta($col){
       if(isset($_SESSION['user']) && isset($_SESSION['user'][$col]) && !empty($_SESSION['user'][$col])){
           return $_SESSION['user'][$col];
       }
        return false;
    }

    static function findByType($type){
        
        $result = \DB::select()->from('users')->where('type', '=', $type)->where('active', '=', 1)->order_by('first_name')->execute();
        return $result->as_array();
        
    }
    
    static function findByDepartment($dept){
        
        if(!is_array($dept)){
            $dept = array($dept);
        }
        
        $result = \DB::select()->from('users')->where('department', 'in', $dept)->where('active', '=', 1)->order_by('first_name')->execute();
        return $result->as_array();
        
    }
    
    static function findByEmail($email){
        
        $result = \DB::select()->from('users')->where('email', '=', $email)->execute();
        return current($result->as_array());
        
    }

    static function findByToken($token){

        $result = \DB::select()->from('users')->where('reset_token', '=', $token)->execute();
        return current($result->as_array());

    }

    static function findByExtension($extension){

        $result = \DB::select()->from('users')->where('extension', '=', $extension)->execute();
        return current($result->as_array());

    }

    static function findByExtensionAndCompany($extension, $company_id){

        $result = \DB::select()->from('users')->where('extension', '=', $extension)->where('company_id','=', $company_id)->execute();
        return current($result->as_array());
    }

    static function findByExtensions($extensions){

        $result = \DB::select()->from('users')->where('extension', 'IN', $extensions)->order_by('first_name','ASC')->execute();
        return $result->as_array();

    }

    static function getTemplateList(){
        return array();
    }
    
    static function findByField($field, $value,$company_id){
        
        $result = \DB::select()->from('users')->where($field, '=', $value)->where('company_id','=', $company_id)->execute();
        return current($result->as_array());
        
    }
    
    static function add($data){
        
        $data['passwd'] = sha1($data['passwd']);
        
        $result = \DB::insert('users')->set($data)->execute();
        return current($result);
    }
    
    static function update_($id, $data){
        
        if(!empty($data['passwd'])){
            $data['passwd'] = sha1($data['passwd']);
        }else{
            unset($data['passwd']);
        }
        
        $result = \DB::update('users')->set($data)->where('id', '=', $id)->execute();
    }


    
    static function delete_($id){

        $data = array(
            'active' => 0,
            'extension' => NULL
        );

        $result = \DB::update('users')->set($data)->where('id','=',$id)->execute();
    }

    static function getFilter(){
        $query = \DB::select(
            'u.id', array(DB::expr('CONCAT(u.first_name, " ", u.last_name)'), 'name'))
            ->from(array('users','u'))->where('active', '=', 1)
            ->order_by('first_name', 'asc');
        $query = Model_Networks::queryNetwork($query, \Model_Account::getNetworkIds(),'u');

        return $query->execute()->as_array();
    }

    static function reSortArrayByIndex($index_name, $data_array){
        //
    }

    static function getUserByRole($role){

        $query = \DB::select(
            'u.id', array(DB::expr('CONCAT(u.first_name, " ", u.last_name)'), 'name'))
            ->from(array('users','u'))->where('active', '=', 1)
            ->join(array('rbac_userroles','roles'),'LEFT')->on('roles.user_id','=','u.id')
            ->where('roles.role_id','=',$role)
            ->group_by('u.id')
            ->order_by('first_name', 'asc');

        $query = Model_Networks::queryNetwork($query, \Model_Account::getNetworkIds(),'u');

        return $query->execute()->as_array();

    }
    
    static function validate($factory){
        
        $val = \Validation::forge($factory);

        $val->add('first_name', 'Name')
            ->add_rule('required');

        $val->add('last_name', 'Name')
            ->add_rule('required');

        $val->add('email', 'email')
            ->add_rule('required');

        $val->add('type', 'User Type')
            ->add_rule('required');


        return $val;
    }

    static function getSignatureHTML(){

    }
}
