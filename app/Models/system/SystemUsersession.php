<?php

namespace App\Models\system;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemUsersession extends Model
{
    use HasFactory;
    protected $table = "user_sessions";
    static function check($id){
        $session = current(DB::select()->from('user_sessions')->where('user_id','=', $id)->execute()->as_array());
        if($session){
           $result =  DB::update('user_sessions')->set(array('session_id'=>session_id()))->where('user_id','=',$id)->execute();
        }else{
           $result = \DB::insert('user_sessions')->set(
                array(
                    'user_id' => $id,
                    'session_id'=>session_id()
                )
            )->execute();
        }
        return current($result);
    }

    static function find_($id){
        $result = \DB::select()->from('user_sessions')->where('user_id','=', $id)->execute()->as_array();
        return current($result);
    }

    static function findOtherSessions($id, $session_id){
        $result = \DB::select()->from('user_sessions')->where('user_id','=', $id)->where('session_id', '!=', $session_id)->execute()->as_array();
        return $result;
    }

    static function findByCompany($company_id){
        $result = \DB::select('user_sessions.*')->from('user_sessions')
            ->join('users')->on('users.id','=','user_sessions.user_id')
            ->join('companies')->on('companies.id','=','users.company_id')
            ->where('companies.id','=', $company_id)->execute()->as_array();

        if($result){
            foreach($result as $user_session){
                $i[$user_session['user_id']] = $user_session;
            }
            return  $i;
        }

        return  false;
    }

    static function delete_($id){

        $usession = self::find($id);
        $session_file = \Config::get('session_path').'/sess_'.$usession['session_id'];

        if(is_file($session_file)) {
            Model_Log::append('sess','unlinking file');
            unlink($session_file);
        }else{
            Model_Log::append('sess','no active file: ' . $session_file);
        }
        Model_Log::append('sess',$usession['session_id']);

    }

    static function deleteBySessionId($session_id){

        $session_file = \Config::get('session_path').'/sess_'.$session_id;

        if(is_file($session_file)) {
            Model_Log::append('sess','unlinking file');
            unlink($session_file);
        }else{
            Model_Log::append('sess','no active file: ' . $session_file);
        }

    }

    static function update_($session_id, $data=array()){
        $data['timestamp'] = date('Y-m-d H:i:s');
        $result = SystemUsersessioin::where('session_id','=', $session_id)->fill($data);
        $result->update($data);
    }

    static function add($user_id, $session_id, $ip){
        \DB::insert('user_sessions')->set(array('timestamp' => date('Y-m-d H:i:s'),'session_id' => $session_id,'user_id' => $user_id, 'ip_address' => $ip))->execute();
    }

    static function upsert($uid, $session_id){

        $result = \DB::query("INSERT INTO user_sessions (user_id, session_id, timestamp) VALUES (".$uid.",'".session_id()."','".date('Y-m-d H:i:s')."')")->execute();
        return current($result);
    }

}
