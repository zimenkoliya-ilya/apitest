<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Session;
use App\Models\system\SystemUsersession;
class Account extends Model
{
    use HasFactory;
    public function signIn($email, $password, $login_as=false){

        if(!$login_as) {
            $passwd = sha1($password);
        }else{
            $passwd = $password;
        }       
        $result = User::where('email', $email)
                ->where('passwd', $passwd)
                ->where('active', 1)
                ->get();
        if(count($result)){

            $user = current($result->as_array());
            $user_profile = Model_Profiles_User::find($user['id']);

            //self::signOutOtherSessions($user['id']); //

            $json_settings = \Model_System_Setting::get('s3');
            $settings = json_decode($json_settings['value'], true);

            if(isset($user_profile) && !empty($user_profile) && is_array($user_profile)){
                $_SESSION['user'] = array_merge($user, $user_profile);
            }else{
                $_SESSION['user'] = $user;
            }

            $_SESSION['user']['department_ids'] = \Model_System_Roles::getUserRoleIds($user['id']);

            if(isset($settings['url'])){
                $_SESSION['user']['image_path'] = $settings['url'];
            }

            $_SESSION['company'] = Model_System_Company::find($user['company_id']);
            $_SESSION['company']['profile'] = Model_Company_Profile::find($user['company_id']);

            if(isset($user['company_id'])){

                $network_ids = Model_Networks::findByCompany($user['company_id']);
                if($network_ids){
                    $network_companies = Model_Networks::findCompaniesByNetworks($network_ids);
                }else{
                    $network_companies = null;
                }
                $_SESSION['user']['network_companies'] = $network_companies;

                $role_ids = \Model_System_Roles::getUserRoleIds(\Model_Account::getUserId());

                /*if(in_array('8', $role_ids)) {
                    $user = \Model_System_User::find(\Model_Account::getUserId());

                    $asterisk = new \CallCenter\Model_Asterisk(\Model_Account::getCompanyId());
                    $asterisk->updateExtensionStatus($user['extension'], 1);
                }*/

            }else{
                throw new Exception('Need Company ID set for User');
            }

            return;
        }

        Model_Log::file('failed_logins','|Email|'.$email.'|Password|'.$passwd.'|IP|'.$_SERVER['REMOTE_ADDR']);
        
        throw New Exception('Login Failed');
        
    }
    
    public function signOut(){
        dd(session()->getId());
        // Set Phone status Unavailable
        SystemUserSession::update_(session()->getId(),array('active' => 0));
        unset($_SESSION['user']);
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
        session_destroy();
    }

   public function signOutOtherSessions($user_id){

        // Find other sessions not this one
       $sessions = Model_System_UserSession::findOtherSessions($user_id, session_id());
       foreach($sessions as $session){
           // Delete Session Cookies
           Model_System_UserSession::deleteBySessionId($session['session_id']);
           Model_System_UserSession::update($session['session_id'],array('active' => 0));
       }

   }
    
    public function isLoggedIn(){
        
        //allow users to use the login system without being logged in
        if(\Uri::string() == 'account/signin'){
            return true;
        }

        if (isset($_SESSION['user']['id']) && $_SESSION['user']['id']) {
            return true;
        }

        return false;
    }


    public function getCompanyAndParentIds(){
       $parent_id = Model_System_Company::findParentId(self::getCompanyId());
       if($parent_id){
           return array(self::getCompanyId(), $parent_id);
       }
        return array(self::getCompanyId());
    }

    public function getCompanyAndNetworkIds(){
        return array_merge(array(self::getCompanyId()), Model_Account::getNetworkIds());
    }

    static function getUserId() {
        if (isset($_SESSION['user']['id']) && $_SESSION['user']['id']) {
            return $_SESSION['user']['id'];
        }            
        return 0;
    }

    public function getUserDepartments() {
        $ids = array();

        if (isset($_SESSION['user']['department_ids']) && $_SESSION['user']['department_ids']) {

            foreach($_SESSION['user']['department_ids'] as $k => $v){
                $ids[] = "$v";
            }
        }
        return $ids;
    }

    public function isWilcard(){
        if (isset($_SESSION['user']['wildcard']) && $_SESSION['user']['wildcard'] == 1) {
            return true;
        }
        return false;
    }

    public function getCompanyId() {
        if (isset($_SESSION['user']['company_id']) && $_SESSION['user']['company_id']) {
            return $_SESSION['user']['company_id'];
        }
        return 0;
    }

    public function getProcessorId() {
        if (isset($_SESSION['company']['profile']['processor_id']) && $_SESSION['company']['profile']['processor_id']) {
            return $_SESSION['company']['profile']['processor_id'];
        }
        return 0;
    }

    public function getCompanyName() {
        if (isset($_SESSION['company']['long_name'])) {
            return $_SESSION['company']['long_name'];
        }
        return 0;
    }

    public function getCompanyNameTag() {
        if (isset($_SESSION['company']['name'])) {
            return $_SESSION['company']['name'];
        }
        return 0;
    }

    public function getNetworkIds(){

        return \Model_Networks::findCompanyNetworkIds(\Model_Account::getCompanyId());
    }

    public function getDashboard() {

           $user =  Model_System_User::find($_SESSION['user']['id']);
           return $user['dashboard_id'];

    }

    public function getExtension() {

        return $_SESSION['user']['extension'];

    }

    public function getRegion() {

        if (isset($_SESSION['user']['region_id']) && $_SESSION['user']['region_id']) {
            return $_SESSION['user']['region_id'];
        }else{
            $user =  Model_System_User::find($_SESSION['user']['id']);
            if(isset($user['region_id'])){
                return $user['region_id'];
            }else{
                return 0;

            }

        }

    }
    
    public function getType(){
        if(isset($_SESSION['user']['id']) && $_SESSION['user']['id'] == 1){
            return 'Master';
        }

        if(!isset($_SESSION['user']['type'])){
            self::signOut();
        }

        return $_SESSION['user']['type'];
    }

    public function getUserObject(){

        if(!isset($_SESSION['user']) || empty($_SESSION['user'])){
            return array(
               'id' => 1,
               'first_name' => 'System',
               'last_name' => 'Administrator'
            );
        }

        return $_SESSION['user'];

    }

    public function getCurrentUserVars(){

        if(isset($_SESSION['user']) && !empty($_SESSION['user'])){
            $set = array();
            foreach($_SESSION['user'] as $k => $v){
                $set['session_user_'.$k] = $v;
            }

            return $set;
        }

        return array();

    }

    public function getUserFullName(){

        return $_SESSION['user']['first_name'] .' '.$_SESSION['user']['last_name'];

    }

    public function getUserEmail(){

        return $_SESSION['user']['email'];

    }
    
}
