<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Log extends Model
{
    use HasFactory;
    static function addActivity($case_id, $type, $message, $note = '', $user_id = 0, $pkid=false){

        $activity_type = Model_System_Activity::findIdByName($type);

        if(strlen($message) > 250){
            $message = substr($message, 0, 247) . '...';
        }

        $data = array(
                      'case_id' => $case_id,
                      'message' => $message,
                      'note' => (isset($note) && !empty($note) ? $note:''),
                      'type_id' => $activity_type['id'],
                      'created_by' => (!empty($_SESSION['user']['id'])&& empty($user_id)?$_SESSION['user']['id']:$user_id),
                      'created' => date('Y-m-d H:i:s')
                     );

        if($pkid){
            $data['pkid'] = $pkid;
        }
        
        $result = \DB::insert('log_activity')->set($data)->execute();
        return current($result);
        
    }

    static function checkpoint($case_id, $type, $message){

        $result = \DB::insert('log_loans')->set(array(
            'case_id' => $case_id,
            'checkpoint' => $type,
            'message' => $message,
            'user' => \Model_Account::getUserId(),
            'created' => date("Y-m-d H:i:s")
        ))->execute();

        return current($result);

    }

    static function history($record){

        $data = array(
            'case_id' => $record['case_id'],
            'message' => $record['message'],
            'note' => (isset($record['note']) && !empty($record['note']) ? $record['note']:null),
            'type_id' => $record['type_id'],
            'created_by' => (!empty($record['created_by'])? $record['created_by']:1),
            'created' => (!empty($record['created']) ? date('Y-m-d H:i:s', strtotime($record['created'])) : date('Y-m-d H:i:s')),
            'pkid' => (!empty($record['pkid'])? $record['pkid']:null),
        );

        $result = \DB::insert('log_activity')->set($data)->execute();

        return current($result);

    }

    static function userCasesActivity($user_id, $case_ids){

        foreach($case_ids as $k=>$v){
            $c_ids[] = $v;
        }

        $query = \DB::select('case_id')->from('log_activity')
            ->where('case_id','IN', $c_ids)
            ->where('created_by','=',$user_id)
            ->where('created','between',array(date("Y-m-d").' 00:00:00', date("Y-m-d").' 23:59:59'))
            ->group_by('case_id');
        $logs =  $query->execute()->as_array();

       // \Model_Log::append('activity', \DB::last_query());
      //  \Model_Log::append('activity', print_r($logs, true));

        if($logs){
            $payload = array();
            foreach($logs as $l){
                $payload[$l['case_id']] = $l['case_id'];
            }
            return $payload;
        }

        return false;

    }

    static function commission($case_id, $payment_id, $message){

        $data = array(
            'case_id' => $case_id,
            'payment_id' => $payment_id,
            'message' => $message,
            'created' => date('Y-m-d H:i:s')
        );

        $result = \DB::insert('log_commission')->set($data)->execute();
        return current($result);
    }

    static function misc($case_id, $type, $message, $pkid=null, $error=null){

        $data = array(
            'case_id' => $case_id,
            'type' => $type,
            'message' => $message,
            'user' => Model_Account::getUserId(),
            'pkid' => $pkid,
            'created' => date('Y-m-d H:i:s'),
            'error' => $error
        );

        $result = \DB::insert('log_misc')->set($data)->execute();
        return current($result);
    }

    public function append($log_name, $data, $case_id=null){
        //$data = date('Y-m-d H:i:s')." - ".$data."\r\n";
        //$result  = file_put_contents(APPPATH . '/logs/'.$log_name.'.log', $data, FILE_APPEND);
        $data = array(
            'case_id' => $case_id,
            'name' => $log_name,
            'message' => $data,
            'user' => Account::getUserId(),
            'created' => date('Y-m-d H:i:s')
        );
        $result = Log_error::create($data);
        return current($result);

    }

    static function systemEvent($event_name, $result, $company_id=false, $priority_level=false){
        $data = array(
            'event' => $event_name,
            'result' => $result,
            'ts' => date('Y-m-d H:i:s')
        );

        if(isset($company_id)){
            $data['company_id'] = $company_id;
        }

        $result = \DB::insert('log_system')->set($data)->execute();
        return current($result);
    }

    static function email($direction, $to, $from, $subject, $body, $case_id, $user_id = 0, $template_id=0){

        if(!in_array($direction, array('in','out'))){
            $direction = 'out';
        };

        if($user_id){

        }

        $data = array(
            'case_id' => $case_id,
            'direction' => $direction,
            'email_to' =>$to,
            'from_email' => $from,
            'template_id' => $template_id,
            'raw_additional_properties' => $body,
            'created' => date('Y-m-d H:i:s')
        );

        //$result = \DB::insert('mailing_activity_logs')->set($data)->execute();
        $data = date('Y-m-d H:i:s')." - ".json_encode($data)."\r\n";
        $result  = file_put_contents(APPPATH . '/logs/emails.log', $data, FILE_APPEND);
       return $result;

    }
    
    static function findActivity($case_id, $type = null, $limit = null){
        
        $result = \DB::select(
            'a.*',DB::expr('CONCAT(u.first_name, " ", u.last_name) as name'), array('at.color', 'color'),array('at.name', 'type')
        )
                        ->from(array('log_activity','a'))
                        ->join(array('users', 'u'), 'left')->on('u.id', '=', 'a.created_by')
                        ->join(array('activity_types', 'at'), 'left')->on('a.type_id', '=', 'at.id')
                        ->where('a.case_id', '=', $case_id)
                        ->order_by('a.created', 'DESC');
        
        if(!empty($type)){
           // $result->where('a.type', '=', $type);
        }
        
        if(!empty($limit)){
            $result->limit($limit);
        }
        
        $result = $result->execute();
        
        return $result->as_array();
        
    }

    static function findByCompanyID($company_id){

        $result = \DB::select('a.*',array('at.name','activity_type'), DB::expr('CONCAT(u.first_name, " ", u.last_name) as created_by_user'))
            ->from(array('log_activity','a'))
            ->join(array('users', 'u'), 'left')->on('u.id', '=', 'a.created_by')
            ->join(array('cases', 'c'), 'left')->on('c.id', '=', 'a.case_id')
            ->join(array('activity_types', 'at'), 'left')->on('a.type_id', '=', 'at.id')
            ->where('c.company_id', '=', $company_id);

        $result = $result->execute();
        return $result->as_array();

    }

    static function findByCaseIDs($case_ids){

        $result = \DB::select('a.*',DB::expr('CONCAT(u.first_name, " ", u.last_name) as name'), array('at.color', 'color'),array('at.name', 'type'))
            ->from(array('log_activity','a'))
            ->join(array('users', 'u'), 'left')->on('u.id', '=', 'a.created_by')
            ->join(array('cases', 'c'), 'left')->on('c.id', '=', 'a.case_id')
            ->join(array('activity_types', 'at'), 'left')->on('a.type_id', '=', 'at.id')
            ->where('c.id', 'IN', $case_ids);

        $result = $result->execute();
        return $result->as_array();

    }


    static function findTimeInStatus($case_id){

        $result = \DB::select(DB::expr('MAX(created) as time'))
            ->from(array('log_activity','a'))
            ->where('a.case_id', '=', $case_id)
            ->and_where('a.type_id', '=', 2)
            ->order_by('a.created', 'DESC');

        return current($result->execute()->as_array());
    }

    
    static function addImport($type, $content, $request_uri, $remote_addr){

        $data = array(
                        'uri' => $request_uri,
                        'type' => $type,
                        'content' => $content,
                        'ip' => $remote_addr,
                        'created' => date("Y-m-d H:i:s")
                     );

        $id = current(\DB::insert('log_imports')->set($data)->execute());

        return $id;

    }

    static function update_import_log($id, $data){

        return \DB::update('log_imports')->set($data)->where('id','=', $id)->execute();
    }



    static function recording($data){
        $data = date('Y-m-d H:i:s')." - ".$data."\r\n";
        $result  = file_put_contents(APPPATH . '/logs/recordings.log', $data, FILE_APPEND);
        return $result;
    }

    static function html_file($title, $data){
        $result  = file_put_contents(APPPATH . '/logs/loans/'.$title.'_'.date('Y-m-d-H-i-s').'.html', $data, FILE_APPEND);
        return $result;
    }

    static function save_file($title, $data){
        $result  = file_put_contents(APPPATH . '/logs/'.$title.'_'.date('Y-m-d-H').'.html', $data, FILE_APPEND);
        return $result;
    }


    static function esign($data){
        $data = date('Y-m-d H:i:s')." - ".$data."\r\n";
        $result  = file_put_contents(APPPATH . '/logs/esign.txt', $data, FILE_APPEND);
        return $result;
    }

    static function queue($result){
        $data = date('Y-m-d H:i:s')." - ".$result."\r\n";
        $result  = file_put_contents(APPPATH . '/logs/queue.txt', $data, FILE_APPEND);
        return $result;
    }

    /*static function transfer($type, $content){

        $data = array(
            'uri' => $_SERVER['REQUEST_URI'],
            'type' => $type,
            'content' => $content,
            'ip' => ip2long($_SERVER['REMOTE_ADDR']),
            'created' => date("Y-m-d H:i:s")
        );

        $result = \DB::insert('log_imports')->set($data)->execute();

    }

    static function addQuery($type, $content){

        $filename = APPPATH . '/logs/queries.txt';

        $f = @fopen($filename, 'a+');
        if (!$f) {
            return false;
        } else {
            $data = date('Y-m-d H:i:s')." - ".$_SESSION['user']['id']." : ".$content."\n";
            $bytes = fwrite($f, $data);
            fclose($f);
            return $bytes;
        }


    }
    */
    
    static function logAction($type, $user_id, $case_id, $action_id, $action_group_id, $to_user){
        
        if(!empty($action_group_id) && $type == 'first_group'){
            $action_ids = Model_System_Action::findIDsByGroup($action_group_id);
            $result = \DB::select('id')->from('log_actions')->where('case_id','=',$case_id)->where('action_id', 'in', $action_ids)->execute();
        }else{
            $result = \DB::select('id')->from('log_actions')->where('case_id','=',$case_id)->where('action_id','=',$action_id)->execute();
        }

        $first = 0;
        if(in_array($type, array('first','first_group'))){
            $first = 1;
            if(count($result)){
                return false;
            }
        }else{
            if(!count($result)){
                $first = 1;
            }
        }
        
        $data = array(
                      'case_id' => $case_id,
                      'action_id' => $action_id,
                      'created_by' => (!empty($_SESSION['user']['id'])&& empty($user_id)?$_SESSION['user']['id']:$user_id),
                      'first' => $first,
                      'user_id' => ($to_user==0?null:$to_user),

                      'created' => date("Y-m-d H:i:s")
                     );
        
        $result = \DB::insert('log_actions')->set($data)->execute();

        return current($result);
        
    }

    static function logActionReason($case_id, $log_action_id, $reason_id){

        if(!$log_action_id){
            return false;
        }

        $data = array(
            'case_id' => $case_id,
            'log_action_id' => $log_action_id,
            'reason_id' => ($reason_id==0?null:$reason_id),
            'created_by' => (!empty($_SESSION['user']['id'])&& empty($user_id)?$_SESSION['user']['id']:$user_id),
            'created' => date("Y-m-d H:i:s")
        );

        $result = \DB::insert('log_actions_reasons')->set($data)->execute();
        return current($result);

    }


    static function log_status($case_id, $status_id, $user_id){

        $data = array(
            'case_id' => $case_id,
            'status_id' => $status_id,
            'created_by' => $user_id,
            'created' => date("Y-m-d H:i:s")
        );

        $result = \DB::insert('log_statuses')->set($data)->execute();
        return current($result);

    }

    static function user_activity($uri){

        $result = \DB::insert('log_user_activity')->set(array(
            'uri' => $uri,
            'user' => \Model_Account::getUserId(),
            'ip' => $_SERVER['REMOTE_ADDR'],
            'created' => date("Y-m-d H:i:s")
        ))->execute();
        return current($result);

    }

    static function migration($data){
        $filename = APPPATH . '/logs/migration.txt';

        $f = @fopen($filename, 'a+');
        if (!$f) {
            return false;
        } else {
            $data = date('Y-m-d H:i:s')." - ".$data."\n";
            $bytes = fwrite($f, $data);
            fclose($f);
            return $bytes;
        }

    }
    /*
            static function loans($data){
                $filename = APPPATH . '/logs/import/loans.txt';

                $f = @fopen($filename, 'a+');
                if (!$f) {
                    return false;
                } else {
                    $data = date('Y-m-d H:i:s')." - ".$data."\n";
                    $bytes = fwrite($f, $data);
                  fclose($f);
                    return $bytes;
                }

            }


            static function exceptions($data){
                $filename = APPPATH . '/logs/import/exceptions.txt';

                $f = @fopen($filename, 'a+');
                if (!$f) {
                    return false;
                } else {
                    $data = date('Y-m-d H:i:s')." - ".$data."\n";
                    $bytes = fwrite($f, $data);
                    fclose($f);
                    return $bytes;
                }

            }
    */
    static function transaction($data){
        $filename = '/logs/transactions.txt';

        $f = @fopen($filename, 'a+');
        if (!$f) {
            return false;
        } else {
            $data = date('Y-m-d H:i:s')." - ".$data."\n";
            $bytes = fwrite($f, $data);
            fclose($f);
            return $bytes;
        }

    }

    static function file($filename, $data){
        $filename = APPPATH . '/logs/'.$filename.'.txt';

        $f = @fopen($filename, 'a+');
        if (!$f) {
            return false;
        } else {
            $data = date('Y-m-d H:i:s')." - ".$data."\n";
            $bytes = fwrite($f, $data);
            fclose($f);
            return $bytes;
        }

    }


    /*
           static function paymentCheck($data){
               $filename = APPPATH . '/logs/import/payment_checks.txt';

               $f = @fopen($filename, 'a+');
               if (!$f) {
                   return false;
               } else {
                   $data = date('Y-m-d H:i:s')." - ".$data."\n";
                   $bytes = fwrite($f, $data);
                   fclosf);
                   return $bytes;
               }

           }
           /*
           static function cron($data, $filepath = null ){
               if ($filepath) $filename = $filepath;
               else $filename = APPPATH . '/logs/import/transactions.txt';

               $f = @fopen($filename, 'a+');
               if (!$f) {
                   return false;
               } else {
                   $data = date('Y-m-d H:i:s')." - ".$data."\n";
                   $bytes = fwrite($f, $data);
                   fclose($f);
                   return $bytes;
               }

           }

                  static function curl($data){
                      $fh = fopen(APPPATH . '/logs/curl.txt', 'a');
                      $data = date('Y-m-d H:i:s')." - ".$data;
                      $result = fwrite($fh, $data."\n");
                      fclose($fh);
                   return $result;
                  }
                  /*
                         static function call($data){
                             $data = date('Y-m-d H:i:s')." - ".$data."\r\n";
                             $result  = file_put_contents(APPPATH . '/logs/import/calls.txt', $data, FILE_APPEND);
                             returnesult;
                         }

                         /*static function esign($data){
                             $data = date('Y-m-d H:i:s')." - ".$data."\r\n";
                             $result  = file_put_contents(APPPATH . '/logs/import/esign.txt', $data, FILE_APPEND);
                             return $result;
                         }

                         static function additional($data){
                             $data = date('Y-m-d H:i:s')." - ".$data."\r\n";
                             $result  = file_put_contents(APPPATH . '/logs/import/additional.txt', $data, FILE_APPEND);
                             return $result;
                         }*/

}
