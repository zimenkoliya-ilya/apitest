<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Assignment extends Model
{
    use HasFactory;
    protected $table = "case_assignments";
    static function find_($case_id){

        $result = \DB::select(
            'ca.*',
            array(DB::expr('CONCAT(u.first_name, " ", u.last_name)'), 'user'),
            array('u.id','user_id'),
            array('d.name','department_name'),
            array('d.id','department_id')
        )
            ->from(array('case_assignments', 'ca'))
            ->join(array('departments', 'd'), 'left')->on('d.id','=','ca.department_id')
            ->join(array('users', 'u'), 'left')->on('u.id','=','ca.user_id')
            ->where('id','=', $case_id)
            ->execute();

        return $result->as_array();
    }



    static function findByCase($case_id){

        $result = \DB::select(
            'ca.*',
            array(DB::expr('CONCAT(u.first_name, " ", u.last_name)'), 'user'),
            array(DB::expr('CONCAT(u.first_name, " ", u.last_name)'), 'full_name'),
            array('u.id','user_id'),
            array('d.name','department_name'),
            array('d.clean_name','department_clean_name'),
            array('d.id','department_id'),
            array('u.extension','user_extension'),
            array('u.mobile','user_mobile'),
            array('u.email','email')
        )
            ->from(array('case_assignments', 'ca'))
            ->join(array('departments', 'd'), 'left')->on('d.id','=','ca.department_id')
            ->join(array('users', 'u'), 'left')->on('u.id','=','ca.user_id')
            ->where('case_id','=', $case_id)
            ->execute();

        return $result->as_array();
    }

    static function findByCasesAndDept($case_ids, $department_id){

        $result = \DB::select(
            'ca.*',
            array(DB::expr('CONCAT(u.first_name, " ", u.last_name)'), 'user'),
            array('u.id','user_id'),
            array('d.name','department_name'),
            array('d.clean_name','department_clean_name'),
            array('d.id','department_id')
        )
            ->from(array('case_assignments', 'ca'))
            ->join(array('departments', 'd'), 'left')->on('d.id','=','ca.department_id')
            ->join(array('users', 'u'), 'left')->on('u.id','=','ca.user_id')
            ->where('case_id','IN', $case_ids)
            ->where('ca.department_id','=', $department_id)
            ->execute();

        return $result->as_array();
    }


    static function findByUntouchedAssignments($params){

        $result = \DB::select(
            'ca.*',
            array(DB::expr('CONCAT(u.first_name, " ", u.last_name)'), 'user'),
            array('u.id','user_id'),
            array('d.name','department_name'),
            array('d.clean_name','department_clean_name'),
            array('d.id','department_id'),
            array(DB::expr('CONCAT(cc.first_name, " ", cc.last_name)'), 'client'),
            array('ca.created', 'assigned'),
            array('ca.updated', 'updated'),
            array('ca.case_id', 'id')
        )
            ->from(array('case_assignments', 'ca'))
            ->join(array('departments', 'd'), 'left')->on('d.id','=','ca.department_id')
            ->join(array('users', 'u'), 'left')->on('u.id','=','ca.user_id')
            ->join(array('case_contact', 'cc'), 'left')->on('cc.case_id','=','ca.case_id');

        if(isset($params['department_id'])){
            $result->where('ca.department_id','=', $params['department_id']);
        }

         if(isset($params['user_id'])){
             $result->where('ca.user_id','=', $params['user_id']);
         }

        $result->where(DB::expr('NOT EXISTS (SELECT * FROM log_actions
                    WHERE user_id = u.id AND case_id = ca.case_id)'));

        $result->and_where_open();
        $result->where('ca.created', '>=', '2014-08-12');
        $result->or_where('ca.updated', '>=', '2014-08-12');
        $result->and_where_close();


        $result->order_by('ca.created','desc')->order_by('ca.updated', 'desc');
        $result->group_by('ca.case_id');

        return $result->execute()->as_array();
    }


    static function findByCaseAndDept($case_id, $dep_id){

        $result = \DB::select(
            'ca.*',
            array(DB::expr('CONCAT(u.first_name, " ", u.last_name)'), 'user'),
            array('u.id','user_id'),
            array('d.name','department_name'),
            array('d.id','department_id')
        )
            ->from(array('case_assignments', 'ca'))
            ->join(array('departments', 'd'), 'left')->on('d.id','=','ca.department_id')
            ->join(array('users', 'u'), 'left')->on('u.id','=','ca.user_id')
            ->where('ca.case_id','=', $case_id)
            ->where('ca.department_id','=', $dep_id)
            ->limit(1);

        return current($result->execute()->as_array());
    }


    static function findByCompanyID($company_id){

        $result = \DB::select('ca.case_id', array(DB::expr('CONCAT(u.first_name, " ", u.last_name)'), 'assigned_user'), array('d.name','department_name'),'ca.created','ca.updated')
            ->from(array('case_assignments', 'ca'))
            ->join(array('departments', 'd'), 'left')->on('d.id','=','ca.department_id')
            ->join(array('users', 'u'), 'left')->on('u.id','=','ca.user_id')
            ->join('cases','left')->on('cases.id','=','ca.case_id')
            ->where('cases.company_id','=', $company_id);

        return $result->execute()->as_array();
    }

    static function findByUser($case_id){

        $result = \DB::select()
            ->from('case_assignments')
            ->where('user_id','=', $case_id)
            ->execute();

        return $result->as_array();

    }

    static function findByDepartment($case_id, $company_id){

        $result = \DB::query("SELECT dept.name as department_name, dept.clean_name as department_clean_name, dept.id, ca.case_id, ca.department_id,
                                ca.user_id, CONCAT(users.first_name, ' ', users.last_name) as user
                                from (select departments.* from departments 
                                where company_id = :company_id
                                UNION
                                select departments.* from departments_shared
                                join departments on departments.id = departments_shared.department_id
                                where departments_shared.company_id = :company_id) as dept
                                left join case_assignments ca on ca.department_id = dept.id AND (ca.case_id = :case_id or ca.case_id IS NULL)
                                left join users on users.id = ca.user_id
                                order by dept.id ASC")
            ->bind('case_id', $case_id)
            ->bind('company_id', $company_id)
            ->execute();

       /** $result = \DB::select(array('d.name','department_name'), array('d.clean_name','department_clean_name'), 'd.id', 'ca.case_id', 'ca.department_id', 'ca.user_id', \DB::EXPR('CONCAT(users.first_name, " ", users.last_name) as user'))
                ->from(array('departments','d'))
                ->join(array('case_assignments', 'ca'), 'left')
                ->on('ca.department_id','=','d.id')
                ->on(\DB::EXPR('(ca.case_id = '.$case_id),'',\DB::EXPR('or ca.case_id IS NULL)'))
                ->join('users','left')->on('users.id', '=','ca.user_id')
                ->where('d.company_id','=', 118)
            ->execute();**/

        return $result->as_array();
    }




    static function upsert($case_id,$data,$user_id=false){

         $i = 0;

        if(!$user_id){
            $user_id = $_SESSION['user']['id'];
        }

         foreach($data as $k => $v){

             $dept_id = $k;
             if($k == 'sales_rep' || $k == 'Sales Rep'){
                 $dept_id = 8;
             }

             if(empty($v) || $v == '' || (is_numeric($v) && $v == 0)){
                 continue;
             }

             try{

                 $record = self::findByCaseAndDept($case_id, $dept_id);

                    if($record){
                        if($v == 'unassign') {
                            // Unassign the person from file
                            $result = self::delete($record['case_id'], $record['department_id']);

                            self::notifyAssignmentChange($case_id, array(
                                'department_id' => $dept_id,
                                'user_id' => $record['user_id']
                            ), true);

                            continue;
                        }else{
                            // Update the person assigned
                            if($record['department_id'] == $dept_id && $record['user_id'] == $v){
                                // Record Exists for User and Department ... skip
                                $result = false;
                            }else {

                                self::changeProcessorCompany($case_id, $v, $dept_id);

                                $result = self::update($case_id, array(
                                    'case_id' => $case_id,
                                    'department_id' => $dept_id,
                                    'user_id' => $v,
                                    'updated' => date('Y-m-d H:i:s'),
                                    'updated_by' => $user_id
                                ));

                                if($result){
                                    \Notification\Notify::success('Assignment Updated');
                                }

                            }

                        }
                    }else{
                        // Record doesn't exist add user


                        self::changeProcessorCompany($case_id, $v, $dept_id);

                        $result =  self::add(array(
                            'case_id' => $case_id,
                            'department_id' => $dept_id,
                            'user_id' => $v,
                            'created' => date('Y-m-d H:i:s'),
                            'created_by' => $user_id,
                            'updated' => date('Y-m-d H:i:s'),
                            'updated_by' => $user_id
                        ));

                        if($result){
                            \Notification\Notify::success('Assignment Added');
                        }

                    }


                 if(!$result){
                     $i++;
                 }else{
                     self::notifyAssignmentChange($case_id, array(
                         'department_id' => $dept_id,
                         'user_id' => $v
                     ));
                 }

             }catch(Exception $e){

                \Notification\Notify::error($e);

             }

         }

    }


    static function collector(){

    }


    static function changeProcessorCompany($case_id, $user_id, $dept_id){

        if($dept_id != 3){ //Processor
            return false;
        }

        Model_Action::changeProcessor($case_id, null);

    }


    static function batch_upsert($case_id, $department_id, $user_id){

        $i = 0;

            $record = self::findByCaseAndDept($case_id, $department_id);

            if($record){

                $result = self::update($case_id, array(
                    'case_id' => $case_id,
                    'department_id' => $department_id,
                    'user_id' => $user_id,
                    'updated' => date('Y-m-d H:i:s'),
                    'updated_by' => (isset($_SESSION['user']['id']) ? $_SESSION['user']['id'] : 1)

                ));

            }else{
                if((int)$user_id == 0){
                    return false;
                }
                $result =  self::add(array(
                    'case_id' => $case_id,
                    'department_id' => $department_id,
                    'user_id' => $user_id,
                    'created' => date('Y-m-d H:i:s'),
                    'created_by' => (isset($_SESSION['user']['id'])?$_SESSION['user']['id']:1),
                    'updated' => date('Y-m-d H:i:s'),
                    'updated_by' => $user_id
                ));
            }


            if(!$result){
                $i++;
            }else{
                self::notifyAssignmentChange($case_id, array(
                    'department_id' => $department_id,
                    'user_id' => $user_id
                ));
            }


        if($i == 0){
            return true;
        }else{
            return false;
        }


    }


    private static function notifyAssignmentChange($case_id, $data, $unassign = null){
        $message = '';

        if(isset($data['user_id'])){
            $user = Model_System_User::find($data['user_id']);
            $message .= $user['first_name']. ' '.$user['last_name'].' ';
        }

        if($unassign){
            $message .= 'unassigned from ';
        }else{
            $message .= 'assigned as ';
        }

        if(isset($data['department_id'])){
            $department = Model_System_Departments::find($data['department_id']);
            $message .= $department['name'];
        }
        Model_Log::addActivity($case_id,'Assignment',$message);
    }

    static function update_($case_id, $data){
        return DB::update('case_assignments')->set($data)->where('case_id','=',$case_id)->where('department_id','=',$data['department_id'])->execute();
    }

    static function add($data){
        return DB::insert('case_assignments')->set($data)->execute();
    }

    static function delete_($case_id, $department_id){
        return DB::delete('case_assignments')->where('case_id','=',$case_id)->where('department_id','=',$department_id)->execute();
    }

    /* Helper Function */
    static function in_array_r($needle, $haystack, $strict = false) {
        foreach ($haystack as $item) {
            if (($strict ? $item === $needle : $item == $needle) || (is_array($item) && self::in_array_r($needle, $item, $strict))) {
                return true;
            }
        }

        return false;
    }
}
