<?php

namespace App\Models\system;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemActiontask extends Model
{
    use HasFactory;
    protected $table = "action_tasks";
    static function findTypes(){
            
        $result = \DB::select()->from('action_tasks')->execute();
        return $result->as_array();
        
    }
    
    static function findTargets($task_id){
        
        $result = \DB::select()->from('action_tasks')->where('id', '=', $task_id)->execute();
        
        if(!count($result)){
            return array();
        }
        
        $row = current($result->as_array());
        
        if(empty($row['options_table'])){
            return array();
        }
        
        $query = \DB::select('id', 'name')->from($row['options_table']);
        $parent_id = Model_System_Company::findParentId(\Model_Account::getCompanyId());

        switch($row['options_table']){

            case 'statuses':
                $query->where('company_id', 'IN', array(1, \Model_Account::getCompanyId(), $parent_id));
                $query->where('active', '=', 1);
                break;
            case 'template_emails':
                $query->where('company_id', '=', \Model_Account::getCompanyId());
                break;
            case 'distribution_user_groups':
                $query->where('company_id', '=', \Model_Account::getCompanyId());
                break;
            case 'template_sms':
                $query->where('company_id', '=', \Model_Account::getCompanyId());
                break;
            case 'template_exports':
                $query->where('company_id', '=', \Model_Account::getCompanyId());
                break;
            case 'template_flags':
                //$query->where('company_id', '=', \Model_Account::getCompanyId());
                break;
            case 'template_notifications':
                $query->where('company_id', '=', \Model_Account::getCompanyId());
                break;
            case 'companies':
                $query->where('id', 'IN', \Model_Account::getNetworkIds());
                break;
            case 'template_tasks':
                $query->where('company_id', 'IN', \Model_Account::getNetworkIds());
                break;
            case 'labels':
                $query->where('company_id', '=', \Model_Account::getCompanyId());
                break;
            case 'payment_statuses':

                break;
            case 'form_fields':

                break;
        }


        $query->order_by('name','ASC');
        
        $result = $query->execute();
        
        return $result->as_array();
        
    }
    
    static function find($id){
        
        $result = \DB::select()->from('actions_tasks')->where('id', '=', $id)->execute();
        return current($result->as_array());
        
    }
    
    static function findByActionID($action_id){
        
        $result = \DB::select('ast.id','ast.action_id','ast.task_id','ast.target_id','at.name','at.options_table')
                ->from(array('actions_tasks', 'ast'))
                ->join(array('action_tasks', 'at'))->on('ast.task_id', '=', 'at.id')
                ->where('action_id', '=', $action_id)
                ->execute();
        
        $tasks = array();
        $tables = array();
        foreach($result->as_array() as $row){
            $tasks[$row['id']] = $row;
            $tables[$row['options_table']][] = $row['target_id'];
        }
        
        $names = array();
        foreach($tables as $table => $ids){
            $task_res = \DB::select('id', 'name')->from($table)->where('id', 'IN', $ids)->execute();
            foreach($task_res->as_array() as $row){
                $names[$table][$row['id']] = $row['name'];
            }
        }
        
        foreach($tasks as $id => $t){

            if(isset($names[$t['options_table']][$t['target_id']])) {
                $tasks[$id]['target'] = $names[$t['options_table']][$t['target_id']];
            }else{
                $tasks[$id]['target'] = 'NOT FOUND IN SYSTEM ' . $t['options_table'] .' Id:'. $t['target_id'];
            }

        }
        
        return $tasks;
        
    }
    
     static function add($data){
        $result = \DB::insert('actions_tasks')->set($data)->execute();
        return current($result);
    }
    
    static function update($id, $data){
        $result = \DB::update('actions_tasks')->set($data)->where('id', '=', $id)->execute();
    }
    
    static function delete($id){
        $result = \DB::delete('actions_tasks')->where('id','=',$id)->execute();
    }
    
    static function validate($factory){
        
        $val = \Validation::forge($factory);

        $val->add('task_id', 'Task')
            ->add_rule('required');

        $val->add('target_id', 'Target')
            ->add_rule('required');

        return $val;
    }
    
}
