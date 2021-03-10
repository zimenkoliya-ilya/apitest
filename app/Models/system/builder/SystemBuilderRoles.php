<?php

namespace App\Models\system\builder;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemBuilderRoles extends Model
{
    use HasFactory;

    public static $starterRoles = array(
        array( 'title' => 'sales_rep', 'description' => 'Sales Representative' ),
        array( 'title' => 'admin', 'description' => 'Administrator' )
    );
        /** Initializing method that will insert the new tables if they don't exist
        *  and collect all starting permissions and roles */
    static function bootstrap(){
        /* Flow:
        1. Checks if the tables exist - if not creates them
        2. Gets all the methods and controllers
        3. Iterates all roles - If they don't exist, creates them
        4. Iterates all permissions - if they don't exist, creates them
        5. Verifies that the permission is in the role. If not will add it.
        */
        $controller_path = APPPATH . self::$controller_path;
        $rs_buildModel = self::_buildDataModel();
        $_SESSION['log'] .= "<br /> Model Building went through;<br />";
        $rs_bootstrapAccess = self::_setStartingRolesPermissions(self::$starterRoles, $controller_path);
        $_SESSION['log'] .= "<br /> Roles and Permissions are set;<br />";
        $rs_allUserRoles = self::_setAllUsersStartingRoles();
        $_SESSION['log'] = "<br /> All Users have roles;<br />";
        $result = ($rs_buildModel && $rs_bootstrapAccess && $rs_allUserRoles);
        return $result;
    }
        
        // Initiate the data model using the queries set on top as private property
    private static function _buildDataModel()
    {
        $result = true;
        foreach (array_keys(self::$dataTables) as $table) {
            $rs = (DB::query("SHOW TABLES LIKE '" . $table . "'")->execute()) > 0;
            if (!$rs) {
                $_SESSION['log'] .= "<br />".$table." does not exist. Going to create!";
                $q_drop = \DB::query("DROP TABLE IF EXISTS `" . $table . "`;")->execute();
                $_SESSION['log'] .= "<br />The drop shouldn't be necessary. Still the result is ".($q_drop?'dropped successfully!':"didn't dropped so all good!");
                $q_create = \DB::query(self::$dataTables[$table])->execute();
                $_SESSION['log'] .= "<br />The creation ".($q_create?"was ok!":"caught on fire!");
                $result &= (DB::query("SHOW TABLES LIKE '" . $table . "'")->execute()) > 0;
            } else {
                $_SESSION['log'] .= "<br />".$table." already exists. No creation necessary!";
            }
        }
        return $result;
    }
        
    private static function _setAllUsersStartingRoles()
        {
            $roles = Model_System_Roles::findAll();
            $users = Model_System_User::findAll();
            $accounts = Model_System_Account::findAll();
            foreach($roles as $role) {
            foreach($users as $user) {
                if (!Model_System_Roles::hasRole($role['id'],$user['id'])) {
                    Model_System_Roles::assignRoleToUser($role['id'],$user['id']);
                }
            }
        }
        return true;
    }
        
        
        /** Update Permissions for new Controllers */
    static function updatePermissionsFrom($controller)
        {
        $search_path = APPPATH .self::$controller_path. $controller;
        $data = self::_listControllers($search_path);
        $roles = self::$starterRoles;
        $accounts = Model_System_Access::findAll();
        try {
            foreach (array_keys($data) as $controller) {
            // Initializing id's again
                $permission_id=0;
                if (count($data[$controller]) > 0) { // has permissions
                
                foreach ($data[$controller] as $permission) {
                // Name for permission should be role name joined by simple name: eg: Controller_Accounting for action_add
                // permission name should be accounting_add
                $permission_name = (substr($permission,7)=='index'?'':substr($permission,6));
                $permission_title = strtolower($controller).$permission_name;
                // Description for permission should be first uppercased action name followed by controller name:
                // eg: Controller_Accounting for action_add, description should be Add accounting
                $permission_description = ucfirst(substr($permission,7))." ".strtolower(str_replace("_"," ", $controller));
                // Path for permission should be title lowercased and replaced the _ for / ; eg: Controller_Accounting for action_add
                // permission path should be /accounting/add
                $permission_path = '/'.str_replace('_','/',$permission_title);
                if (!Model_System_Permissions::existsByTitle($permission_title)) {
                    $_SESSION['log'] .= "<br /> $permission_description doesn't exist - going to create and it's ";
                    $permission_id = Model_System_Permissions::add(array('title'=>$permission_title,'description'=>$permission_description,'path'=>$permission_path));
                    $_SESSION['log'] .= (($permission_id>0)?'successful!':'NOT successful!');
                } else {
                    $permission_data = Model_System_Permissions::findByTitle($permission_title);
                    $permission_id = $permission_data['id'];
                    $_SESSION['log'] .= "<br /> $permission_description exists! No creation necessary!";
                }
                
                for($n=0;$n<count($roles);$n++) {
                // Description for role should be controller name without the underscores `_`
                    $role_description = $roles[$n]['description'];
                    // Name for role should be controller name lowercased - something_somethingelse
                    $role_title = $roles[$n]['title'];
                    $role_id = $roles[$n]['id'];
                    
                    if (!Model_System_Roles::hasRolePermissionByTitles($role_title, $permission_title)) {
                    $_SESSION['log'] .= "<br /> $permission_description doesn't exist on $role_description - going to create and it's ";
                    $result = Model_System_Roles::assignPermission($role_id, $permission_id);
                    $_SESSION['log'] .= (($result)?'successful!':'NOT successful!');
                    } else {
                        $_SESSION['log'] .= "<br /> $permission_description exists in $role_description ! No creation necessary!";
                    }
                }
                
                }
                }
            
            }
        } catch (Exception $e) {
        var_dump($e);
        die($_SESSION['log']);
        }
        return true;
        
        }
        
        // Creates / updates the roles and permissions by calling the methods of the controllers
        private static function _setStartingRolesPermissions($startingRoles, $controller_path)
        {
        $data = self::_listControllers($controller_path);
        $accounts = Model_System_Account::findAll();
        $permissions = 0;
        try {
        foreach($accounts as $account) {
        
        for($n=0;$n<count($startingRoles);$n++) {
        // Description for role should be controller name without the underscores `_`
        $role_description = $startingRoles[$n]['description'];
        // Name for role should be controller name lowercased - something_somethingelse
        $role_title = $startingRoles[$n]['title'];
        if (!Model_System_Roles::existsByDescription($startingRoles[$n]['description'], $account['id'])) {
        $_SESSION['log'] .= "<br /> $role_title doesn't exist - going to create and it's ";
        $role_id = Model_System_Roles::add(array('title'=>$role_title,'description'=>$role_description, 'account_id'=>$account['id']));
        $startingRoles[$n]['id'] = $role_id;
        $_SESSION['log'] .= (($role_id>0)?'successful!':'NOT successful!');
        } else {
        $role_data = Model_System_Roles::findByDescription($role_description, $account['id']);
        $startingRoles[$n]['id'] = $role_data['id'];
        $role_id = $role_data['id'];
        $_SESSION['log'] .= "<br /> $role_title exists! No creation necessary!";
        }
        }
        
        }
        
        foreach (array_keys($data) as $controller) {
        // Initializing id's again
        $permission_id=0;
        if (count($data[$controller]) > 0) { // has permissions
        
        foreach ($data[$controller] as $permission) {
        // Name for permission should be role name joined by simple name: eg: Controller_Accounting for action_add
        // permission name should be accounting_add
        $permission_name = (substr($permission,7)=='index'?'':substr($permission,6));
        $permission_title = strtolower($controller).$permission_name;
        // Description for permission should be first uppercased action name followed by controller name:
        // eg: Controller_Accounting for action_add, description should be Add accounting
        $permission_description = ucfirst(substr($permission,7))." ".strtolower(str_replace("_"," ", $controller));
        // Path for permission should be title lowercased and replaced the _ for / ; eg: Controller_Accounting for action_add
        // permission path should be /accounting/add
        $permission_path = '/'.str_replace('_','/',$permission_title);
        if (!Model_System_Permissions::existsByTitle($permission_title)) {
        $_SESSION['log'] .= "<br /> $permission_description doesn't exist - going to create and it's ";
        $permission_id = Model_System_Permissions::add(array('title'=>$permission_title,'description'=>$permission_description,'path'=>$permission_path));
        $_SESSION['log'] .= (($permission_id>0)?'successful!':'NOT successful!');
        } else {
        $permission_data = Model_System_Permissions::findByTitle($permission_title);
        $permission_id = $permission_data['id'];
        $_SESSION['log'] .= "<br /> $permission_description exists! No creation necessary!";
        }
        
        foreach($accounts as $account) {
        
        for($n=0;$n<count($startingRoles);$n++) {
        // Description for role should be controller name without the underscores `_`
        $role_description = $startingRoles[$n]['description'];
        // Name for role should be controller name lowercased - something_somethingelse
        $role_title = $startingRoles[$n]['title'];
        $role_id = $startingRoles[$n]['id'];
        
        if (!Model_System_Roles::hasRolePermissionByTitles($role_title, $permission_title, $account['id'])) {
        $_SESSION['log'] .= "<br /> $permission_description doesn't exist on $role_description - going to create and it's ";
        $result = Model_System_Roles::assignPermission($role_id, $permission_id);
        $_SESSION['log'] .= (($result)?'successful!':'NOT successful!');
        } else {
        $_SESSION['log'] .= "<br /> $permission_description exists in $role_description ! No creation necessary!";
        }
        }
        
        }
        }
        }
        }
        
        } catch (Exception $e) {
        var_dump($e);
        die($_SESSION['log']);
        }
        return true;
        }
        /*
        // Creates / updates the roles and permissions by calling the methods of the controllers
        private static function _setRolesPermissionsByController()
        {
        $data = self::_listControllers(APPPATH . self::$controller_path);
        $permissions = 0;
        $roles = 0;
        $log_result = "";
        try {
        $role_title = $permission_title = $role_description = $permission_description = "";
        $role_id=$permission_id=0;
        foreach (array_keys($data) as $role) {
        // Initializing id's again
        $role_id=$permission_id=0;
        if (count($data[$role]) > 0) { // has permissions
        // Description for role should be controller name without the underscores `_`
        $role_description = str_replace("_"," ", $role);
        // Name for role should be controller name lowercased - something_somethingelse
        $role_title = strtolower($role);
        
        if (!Model_System_Roles::existsByDescription($role)) {
        $log_result .= "<br /> $role_title doesn't exist - going to create and it's ";
        $role_id = Model_System_Roles::add(array('title'=>$role_title,'description'=>$role_description));
        $log_result .= (($role_id>0)?'successful!':'NOT successful!');
        } else {
        $role_data = Model_System_Roles::findByDescription($role_description);
        $role_id = $role_data['id'];
        $log_result .= "<br /> $role_title exists! No creation necessary!";
        }
        
        foreach ($data[$role] as $permission) {
        // Name for permission should be role name joined by simple name: eg: Controller_Accounting for action_add
        // permission name should be accounting_add
        $permission_name = (substr($permission,7)=='index'?'':substr($permission,6));
        $permission_title = strtolower($role).$permission_name;
        // Description for permission should be first uppercased action name followed by controller name:
        // eg: Controller_Accounting for action_add, description should be Add accounting
        $permission_description = ucfirst(substr($permission,7))." ".strtolower(str_replace("_"," ", $role));
        // Path for permission should be title lowercased and replaced the _ for / ; eg: Controller_Accounting for action_add
        // permission path should be /accounting/add
        $permission_path = '/'.str_replace('_','/',$permission_title);
        if (!Model_System_Permissions::existsByTitle($permission_title)) {
        $log_result .= "<br /> $permission_description doesn't exist - going to create and it's ";
        $permission_id = Model_System_Permissions::add(array('title'=>$permission_title,'description'=>$permission_description,'path'=>$permission_path));
        $log_result .= (($permission_id>0)?'successful!':'NOT successful!');
        } else {
        $permission_data = Model_System_Permissions::findByTitle($permission_title);
        $permission_id = $permission_data['id'];
        $log_result .= "<br /> $permission_description exists! No creation necessary!";
        }
        if (!Model_System_Roles::hasRolePermissionByTitles($role_title, $permission_title)) {
        $log_result .= "<br /> $permission_description doesn't exist on $role_description - going to create and it's ";
        $result = Model_System_Roles::assignPermission($role_id, $permission_id);
        $log_result .= (($result)?'successful!':'NOT successful!');
        } else {
        $log_result .= "<br /> $permission_description exists in $role_description ! No creation necessary!";
        }
        }
        }
        }
        } catch (Exception $e) {
        //var_dump($e);
        //die($log_result);
        }
        $_SESSION['log'] = $log_result;
        return array($roles, $permissions);
        
        }
        */
        
        
        
        /** Private method to iterate a given path and return the list of controllers **/
        private static function _listControllers($path, $parent = null)
        {
        $group = array();
        $ffs = scandir($path);
        foreach ($ffs as $ff) {
        if ($ff != '.' && $ff != '..') {
        $name = (isset($parent) ? ucfirst($parent) . '_' : "") . ucfirst(pathinfo($ff, PATHINFO_FILENAME));
        $controller_name = "Controller_" . $name;
        if (is_dir($path . '/' . $ff)) $group += self::_listControllers($path . '/' . $ff, $name); // For controller directories
        elseif (preg_match('/^[a-z]+\.php$/', $ff)) $group[$name] = self::_listMethods($path, $controller_name); // Checks only for valid controller files - removes *_bak.php files
        }
        }
        return $group;
        }
        
        /** Private method to iterate a given controller and return the list of methods **/
        private static function _listMethods($path, $controller)
        {
        $controller_path = $path . '/' . $controller; // this should be a config variable
        // Checks if class exists - if not tries to instantiate it
        if (!class_exists($controller)) {
        if (file_exists($controller_path)) include_once($controller_path);
        else return array();
        }
        $group = array();
        $methods = get_class_methods($controller);
        foreach ($methods as $method) {
        if (preg_match('/^action_([a-z]+)$/', $method, $matches)) {
        $group[$method] = $matches[0];
        }
        }
        return $group;
        }
        
        
}
