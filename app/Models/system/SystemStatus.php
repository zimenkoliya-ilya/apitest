<?php

namespace App\Models\system;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemStatus extends Model
{
    use HasFactory;
    protected $table = "statuses";
    static function find_($id){
            
        $result = \DB::select('s.*',array('st.name','status_type_name'),'st.status_field')
            ->from(array('statuses','s'))
            ->join(array('status_types', 'st'), 'LEFT')->on('st.id','=','s.type')
            ->where('s.id', '=', $id)->execute();
        return current($result->as_array());
        
    }

    /**
     * @param $group_id
     * @return mixed
     */
    

    static function findAll($type=null){
        
        $query = SystemStatus::select('s.*', 'm.name as milestone', 'st.name as type_name')
            ->from('statuses as s')
            ->leftJoin('milestones as m', 's.milestone_id','m.id')
            ->leftJoin('status_types as st', 'st.id','s.type')
            ->where('s.active', 1)
            ->orderBy('s.name', 'Asc');
        if($type){
            $query->where('type', $type);
        }

        return $query->get()->toArray();
        
    }

    static function findByCompany($company_id, $active=1){

        $query = \DB::select('s.*', array('m.name', 'milestone'), array('st.name','type_name'))
            ->from(array('statuses','s'))
            ->join(array('milestones', 'm'), 'LEFT')->on('s.milestone_id','=','m.id')
            ->join(array('status_types', 'st'), 'LEFT')->on('st.id','=','s.type')
            ->where('s.active', '=', $active)
            ->where('s.company_id','=', $company_id)
            ->or_where('s.system','=', 1)
            ->order_by('s.name', 'ASC');

        return $query->execute()->as_array();

    }

    static function findCaseCountByCompany($company_id, $active=1){

        $query = \DB::select('s.*', array('m.name', 'milestone'), array('st.name','type_name'), \DB::expr('COUNT(case_statuses.case_id) as case_count'))
            ->from(array('statuses','s'))
            ->join(array('milestones', 'm'), 'LEFT')->on('s.milestone_id','=','m.id')
            ->join(array('status_types', 'st'), 'LEFT')->on('st.id','=','s.type')
            ->join('case_statuses','left')->on('case_statuses.status_id','=','s.id')
            ->join('cases')->on('cases.id','=','case_statuses.case_id')
            ->where('s.active', '=', $active)
            ->where('s.company_id','=', $company_id)
            ->where('cases.company_id','=', $company_id)
            ->or_where('s.system','=', 1)
            ->order_by('s.name', 'ASC')
            ->group_by('s.id');

        return $query->execute()->as_array();

    }

    static function findAnytimeByCompany($company_id){

        $query = \DB::select('s.*', array('m.name', 'milestone'),array('st.name','type_name'))
            ->from(array('statuses','s'))
            ->join(array('milestones', 'm'), 'LEFT')->on('s.milestone_id','=','m.id')
            ->join(array('status_types', 'st'), 'LEFT')->on('st.id','=','s.type')
            ->where('s.active', '=', 1)
            ->where('s.company_id','=', $company_id)
            ->where('s.anytime','=', 1)
            ->order_by('s.name', 'ASC');

        return $query->execute()->as_array();

    }


    static function findByCaseAndParentCompanyIds($company_id, $parent_id, $active=1, $format_list=null){

        $query = \DB::select('s.*', array('m.name', 'milestone'),array('st.name','type_name'))
            ->from(array('statuses','s'))
            ->join(array('milestones', 'm'), 'LEFT')->on('s.milestone_id','=','m.id')
            ->join(array('status_types', 'st'), 'LEFT')->on('st.id','=','s.type')
            ->where('s.active', '=', $active)

            ->and_where_open()

            ->where_open()
            ->where('s.company_id','=', $parent_id)
            ->where('s.shared','=', 1)
            ->where_close()

            ->or_where_open()
            ->where('s.company_id','=', $company_id)
            ->or_where_close()

            ->or_where_open()
            ->or_where('s.system','=', 1)
            ->or_where_close()

            ->and_where_close()

            ->order_by('s.name', 'ASC');

        $result = $query->execute()->as_array();

        if($result){

            if($format_list) {

                foreach ($result as $status) {
                    $payload[] = $status;
                }
            }else{
                foreach ($result as $status) {
                    $payload[$status['company_id']][] = $status;
                }
            }
            return $payload;
        }

        return false;

    }





    static function findByStatusUser($user_id){

        $query = \DB::select('s.*', array('m.name', 'milestone'),array('st.name','type_name'))
            ->from(array('statuses','s'))
            ->join(array('milestones', 'm'), 'LEFT')->on('s.milestone_id','=','m.id')
            ->join(array('status_types', 'st'), 'LEFT')->on('st.id','=','s.type')
            ->join(array('statuses_users','su'),'LEFT')->on('su.status_id','=', 's.id')
            ->where('s.active', '=', 1)
            //->where('su.company_id','=', $company_id)
            ->where('su.user_id','=', $user_id)
            ->order_by('s.name', 'ASC');

        $result = $query->execute()->as_array();



        if($result){

            foreach ($result as $status) {
                $payload[$status['company_id']][] = $status;
            }

            return $payload;
        }

        return false;

    }




    static function findByCompanies($company_ids){

        $query = \DB::select('s.*', array('m.name', 'milestone'),array('st.name','type_name'))
            ->from(array('statuses','s'))
            ->join(array('milestones', 'm'), 'LEFT')->on('s.milestone_id','=','m.id')
            ->join(array('status_types', 'st'), 'LEFT')->on('st.id','=','s.type')
            ->where('s.active', '=', 1)
            ->where('s.company_id','IN', $company_ids)
            ->or_where('s.system','=', 1)
            ->order_by('s.name', 'ASC');

        $result = $query->execute()->as_array();

        if($result){
            foreach($result as $status){
                $payload[$status['company_id']][] = $status;
            }
            return $payload;
        }

        return false;

    }

    static function findAllByCompanies($company_ids){

        $query = \DB::select('s.*', array('m.name', 'milestone'),array('st.name','type_name'))
            ->from(array('statuses','s'))
            ->join(array('milestones', 'm'), 'LEFT')->on('s.milestone_id','=','m.id')
            ->join(array('status_types', 'st'), 'LEFT')->on('st.id','=','s.type')
            ->where('s.active', '=', 1)
            ->where('s.company_id','IN', $company_ids)
            ->or_where('s.system','=', 1)
            ->order_by('s.name', 'ASC');

        $result = $query->execute()->as_array();

        return $result;

    }

    static function findAllInNetwork(){
        $query = \DB::select('s.*', array('m.name', 'milestone'))
            ->from(array('statuses','s'))
            ->join(array('milestones', 'm'), 'LEFT')->on('s.milestone_id','=','m.id');
        $query = Model_Networks::queryNetwork($query, Model_System_User::getSessionMeta('network_companies'));
        $query->where('s.active', '=', 1)
            ->order_by('s.name', 'ASC');
        return $query->execute()->as_array();
    }

    /**
     * @param $id
     * @param $new_position
     */
    static function resort($id, $new_position){
        $status = self::find($id);
        if($status['sort'] > $new_position){
            DB::update('statuses')->set(array('sort' => DB::expr('sort+1')))->where('group_id', '=', $status['group_id'])->where('active', '=', 1)->where('sort', '<', $status['sort'])->execute();
        }else{
            DB::update('statuses')->set(array('sort' => DB::expr('sort-1')))->where('group_id', '=', $status['group_id'])->where('active', '=', 1)->where('sort', '<=', $new_position)->execute();
        }
        print DB::last_query();
        DB::update('statuses')->set(array('sort' => $new_position))->where('id', '=', $id)->execute();
        print DB::last_query();
    }

    /**
     * @param $data
     * @return mixed
     */
    static function add($data){

        if(isset($data['action_ids'])) {
            $action_ids = $data['action_ids'];
            unset($data['action_ids']);
        }
        
        /*$result = \DB::select(array(DB::expr('MAX(sort)+1'), 'sort'))->from('statuses')->where('group_id','=',$data['group_id'])->execute();
        $sort = current($result->as_array());

        if(empty($sort['sort'])){
            $sort['sort'] = 1;
        }
        
        $data['sort'] = $sort['sort'];*/
        $result = \DB::insert('statuses')->set($data)->execute();
        $status_id = current($result);

        if(isset($action_ids)) {
            self::manageActions($status_id, $action_ids);
        }

        return $status_id;
    }

    /**
     * @param $id
     * @param $data
     */
    static function update_($id, $data){

        if(isset($data['action_ids'])) {
            self::manageActions($id, $data['action_ids']);
            unset($data['action_ids']);
        }
        
        $result = \DB::update('statuses')->set($data)->where('id', '=', $id)->execute();
    }

    /**
     * @param $id
     */
    static function delete_($id){
        $status = self::find($id);
        DB::update('statuses')->set(array('sort' => DB::expr('sort-1')))->where('sort','>',$status['sort'])->execute();
        $result = \DB::update('statuses')->set(array('active' => 0, 'sort' => 0))->where('id','=',$id)->execute();
    }

    /**
     * @param $status_id
     * @return array
     */
    static function getActions($status_id){
        
        $result = \DB::select('action_id')->from('actions_statuses')->where('status_id', '=', $status_id)->execute();
        
        $ids = array();
        foreach($result->as_array() as $row){
            $ids[] = $row['action_id'];
        }
        
        return $ids;
        
    }

    /**
     * @param $status_id
     * @param $action_ids
     */
    static function manageActions($status_id, $action_ids){
        
        DB::delete('actions_statuses')->where('status_id', '=', $status_id)->execute();

        $query = \DB::insert('actions_statuses')->columns(array('status_id','action_id'));

        foreach($action_ids as $id){
            $query->values(array($status_id,$id));
        }

        $result = $query->execute();
        
    }

    /**
     * @return mixed
     */
    static function findAllGroups(){
        
        $result = \DB::select()->from('status_groups')->execute();
        return $result->as_array();
        
    }

    /**
     * @param $id
     * @return mixed
     */
    static function findGroup($id){
        
        $result = \DB::select()->from('status_groups')->where('id', '=', $id)->execute();
        return current($result->as_array());
        
    }

    /**
     * @param $id
     * @return mixed
     */
    static function findGroupByStatus($id){

        $result = \DB::select()
            ->from('statuses')
            //->join(array('status_groups', 'stg'), 'LEFT')->on('statuses.group_id','=','stg.id')
            ->where('statuses.id', '=', $id)
            ->execute();
        return current($result->as_array());

    }

    /**
     * @param $name
     * @return array|mixed
     */
    static function findByName($name){
        
        $result = \DB::select()->from('statuses')->where('name', 'LIKE', $name)->execute();
        if(count($result)){
            return current($result->as_array());
        }else{
            return array();
        }
    }

    /**
     * @return array
     */
    static function findExpiryRules(){
        $result = \DB::select('id','name','expiry_days','expiry_action_id')->from('statuses')->where('active','=',1)->where('expiry_days', '>', 1)->where('expiry_action_id', '>', 1)->execute();
        
        $rules = array();
        foreach($result->as_array() as $row){
            $rules[$row['id']] = $row;
        }
        
        return $rules;
        
    }

    /**
     * @param $factory
     * @return mixed
     */
    static function validate($factory){
        
        $val = \Validation::forge($factory);

        $val->add('name', 'Name')
            ->add_rule('required');

        return $val;
    }
    
}
