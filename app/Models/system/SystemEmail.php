<?php

namespace App\Models\system;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Mail;
class SystemEmail extends Model
{
    use HasFactory;
    static function find_($id){
        $result = \DB::select()->from('template_emails')->where('id', '=', $id)->execute();
        return current($result->as_array());
    }
    
    static function findAll(){
        
        $result = \DB::select()->from('template_emails')
            ->execute();
        return $result->as_array();
        
    }

    static function findAllCompanyAndIndustry($company_id){

        $result = \DB::select()->from('template_emails')
            ->where('company_id','in', array(1, $company_id))
            ->order_by('name','ASC')
            ->execute();
        return $result->as_array();
        
    }

    static function findAllByCompany($company_id){

        $result = \DB::select()->from('template_emails')
            ->where('company_id','=', $company_id)
            ->order_by('name','ASC')
            ->execute();
        return $result->as_array();

    }

    static function findStatusTemplates($company_id,$status_id){

        $result = \DB::select('template_emails.*')->from('template_emails')
            ->join('statuses_emails')->on('statuses_emails.email_template_id','=','template_emails.id')
            ->where('statuses_emails.company_id','=', $company_id)
            ->where('statuses_emails.status_id','=', $status_id)
            ->order_by('template_emails.name','ASC')
            ->execute();
        return $result->as_array();

    }
    
    static function add($data){
        $result = \DB::insert('template_emails')->set($data)->execute();
        return current($result);
    }
    
    static function update_($id, $data){
        $result = \DB::update('template_emails')->set($data)->where('id', '=', $id)->execute();
    }
    
    static function delete_($id){
        $result = \DB::delete('template_emails')->where('id','=',$id)->execute();
    }
    
    static function validate($factory){
        
        $val = \Validation::forge($factory);

        $val->add('name', 'Name')
            ->add_rule('required');

        $val->add('from', 'From')
            ->add_rule('required');

        $val->add('to', 'To')
            ->add_rule('required');

        $val->add('subject', 'Subject')
            ->add_rule('required');

        $val->add('message', 'Message')
            ->add_rule('required');

        return $val;
    }

    static function sendEmail($to, $from, $subject, $message, $company_id){
        $headers['name'] = 'System Administrator';
        $headers['from'] = $from;
        Mail::send($to, $subject, $message, $headers, $company_id);
    }
}
