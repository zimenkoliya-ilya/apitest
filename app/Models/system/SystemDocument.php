<?php

namespace App\Models\system;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\document\DocumentTemplate;
class SystemDocument extends Model
{
    use HasFactory;
    // static
    static function find_($id){
            
        $result = DocumentTemplate::find($id)->toArray();
        return $result;
    }

    static function findByCompany($company_id){

        $result = \DB::select()->from('documents')->where('company_id', '=', $company_id)->execute();
        return current($result->as_array());

    }

    static function findAll(){
        
        $result = \DB::select()->from('documents');
        if(!in_array(Model_Account::getType(), array('Master'))) {
            $result->where('company_id','=', Model_System_User::getSessionMeta('company_id'));
        }

        return $result->execute()->as_array();
        
    }

    static function findByIds($ids){

        $result = \DB::select()->from('documents')->where('id', 'IN', $ids)->execute();
        return $result->as_array();

    }

    static function getList(){
        $result = \DB::select('id','name')->from('documents')->execute();
        return $result->as_array();
    }
    
    static function add($data){
        $data['uuid'] = \Str::random('uuid');
        $result = \DB::insert('documents')->set($data)->execute();
        return current($result);
    }
    
    static function update_($id, $data){
        $result = \DB::update('documents')->set($data)->where('id', '=', $id)->execute();
    }
    
    static function delete_($id){
        $result = \DB::delete('documents')->where('id','=',$id)->execute();
    }
    
    static function writeToFolder($name, $files_array){
        
        $parts = pathinfo($files_array['name']);
        $file = preg_replace('/[^0-9a-z-_]/i', '', str_replace(' ', '-', strtolower($name))).'_'.date('mdyhi').'.'.$parts['extension'];
        
        $form_folder = \Config::get('pdf_folder');
        
        if(file_exists($form_folder.$file)){
            unlink($form_folder.$file);
        }
        
        $result = move_uploaded_file($files_array['tmp_name'], $form_folder.$file);
        
        if(!$result){
            throw new Exception('Could not save file');
        }
        
        return $file;
        
    }
    
    static function validate($factory){
        
        $val = \Validation::forge($factory);

        $val->add('name', 'Name')->add_rule('required');
        //$val->add('file', 'File')->add_rule('required');
        $val->add('esign', 'Esign')->add_rule('required');

        return $val;
    }
    
}
