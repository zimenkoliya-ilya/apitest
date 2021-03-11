<?php

namespace Modules\Accounting\Entities\payment;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AccountingPaymentSchedule extends Model
{
    use HasFactory;

    protected $fillable = [];
    protected $table = "payment_schedules";
    protected static function newFactory()
    {
        return \Modules\Accounting\Database\factories\Payment\PaymentScheduleFactory::new();
    }
    static function getPaymentFields(){
        $fields = array('id','case_id','dpp_id','first_name', 'last_name' ,'date_due','amount','collector','payment_status');
        return $fields;
    }

    static function find_($id){
        $result = \DB::select()->from('payment_schedules')->where('id', '=', $id)->execute();
        return current($result->as_array());
    }


    static function getPaymentTypes($active=null){
        $query = \DB::select()->from('payment_schedule_types');
        if($active == 'active'){
            $query->where('active','=', 1);
        }
        $result = $query->execute();
        return $result->as_array();
    }


    static function hasPendingByCase($case_id){
        $query = \DB::select()->from('payment_schedules')->where('case_id', '=', $case_id)->where('status_id','=',2)->limit(1)->execute();
        return current($query->as_array());
    }

    static function getNextPendingByDays($days){
        $result = \DB::select('case_id','amount','date_due')->from('payment_schedules')
            ->join('cases','LEFT')->on('cases.id','=','payment_schedules.case_id')
            ->where('cases.company_id', 'IN', array(102,103))
            ->where('date_due','=',date('Y-m-d', strtotime("+".$days." day")))
            ->execute()->as_array();
        return $result;
    }


    static function batchUpdate($data){

        if(!isset($data['schedule_ids'])){
            return false;
        }

        $update = array();
        if(isset($data['billing_account_id']) && !empty($data['billing_account_id'])){
            $update['billing_account_id'] = $data['billing_account_id'];
        }

        if(isset($data['type_id']) && !empty($data['type_id'])){
            $update['type_id'] = $data['type_id'];
        }

        if(isset($data['date_due']) && !empty($data['date_due'])){
            $update['date_due'] = $data['date_due'];
        }

        if(isset($data['status_id']) && !empty($data['status_id'])){
            $update['status_id'] = $data['status_id'];
        }

       // var_dump($update); die();

        if(isset($update) && !empty($update)){

            $query = \DB::update('payment_schedules');
            $query->set($update);
            $query->where('id','IN', $data['schedule_ids']);
            $result = $query->execute();

            return $result;
        }

        return false;

    }

    static function batchReschedule($data){

        if(!isset($data['schedule_ids'])){
            return false;
        }

        $update = array();

        if(isset($data['date_due']) && !empty($data['date_due'])){
            $update['date_due'] = $data['date_due'];
        }

        $schedules = self::findByScheduleIds($data['schedule_ids']);


        if(isset($update) && !empty($update)){

            foreach($schedules as $schedule){

                unset($schedule['id']);
                $schedule['date_due'] = date("Y-m-d", strtotime($update['date_due']));
                $schedule['status_id'] = 2;
                $schedule['transaction_id'] = null;
                $schedule['gateway_id'] = null;
                $schedule['gateway_response'] = null;
                $schedule['process_date'] = null;
                $schedule['created_by'] = \Model_Account::getUserId();
                $schedule['created'] =date("Y-m-d H:i:s");


                self::add($schedule);
            }

            return true;
        }

        return false;

    }


    function getTodaysPendingTotal($company_id=null){
        $query = \DB::select(\DB::expr('sum(ps.amount) as total'))
            ->from(array('payment_schedules','ps'))
            ->join(array('cases','c'), 'LEFT')->on('c.id', '=', 'ps.case_id')
            ->join(array('case_contact','cc'), 'LEFT')->on('cc.case_id', '=', 'ps.case_id')
            ->join(array('case_statuses','cs'), 'LEFT')->on('cs.case_id', '=', 'ps.case_id')
            ->where('ps.status_id','=', 2)
            ->and_where('ps.date_due','=', date('Y-m-d'))
            ->and_where('cs.is_client','=', 1)
            ->and_where('cs.accounting_status_id','=', 200);

        if($company_id){
            $query->where('c.company_id','=',$company_id);
        }

        $result = current($query->execute()->as_array());

        if(empty($result)){
            return 0;
        }

        return $result['total'];
    }


    static function findTotalByFilter($filter, $group_by = 'day', $solution='count'){

        switch($solution){
            case 'sum':
                $solution = 'sum(ps.amount) as total';
                break;
            case 'count':
                $solution = 'count(DISTINCT ps.id) as total';
                break;
        }

        $query = \DB::select(
            \DB::expr($solution),
            \DB::expr('EXTRACT(YEAR FROM ps.date_due) as year'),
            \DB::expr('EXTRACT(MONTH FROM ps.date_due) as month'),
            \DB::expr('EXTRACT(DAY FROM ps.date_due) as day')
        )
            ->from(array('payment_schedules', 'ps'))
            ->join(array('cases','c'), 'LEFT')->on('c.id', '=', 'ps.case_id')
            ->join(array('case_contact','cc'), 'LEFT')->on('cc.case_id', '=', 'c.id')
            ->join(array('case_statuses','cs'), 'LEFT')->on('cs.case_id', '=', 'c.id');


        if(isset($filter['amount']) && !empty($filter['amount'])){
            $query->where('ps.amount',$filter['amount_operator'],$filter['amount']);
        }

        if(isset($filter['status_id']) && !empty($filter['status_id'])){
            $query->where('ps.status_id','=',$filter['status_id']);
        }

        if(isset($filter['company_id']) && !empty($filter['company_id'])){
            $query->where('c.company_id','=',$filter['company_id']);
        }

        if (!empty($filter['dates']) && $filter['dates'] != 'all_time') {

            $date_field = 'ps.date_due';


            if ($filter['dates'] == 'day') {
                $query->where($date_field, 'between', array(
                    date('Y-m-d 00:00:00', strtotime($filter['date'])),
                    date('Y-m-d 23:59:59', strtotime($filter['date']))
                ));
            } elseif ($filter['dates'] == 'month') {
                $query->where($date_field, 'between', array(
                    date('Y-m-', strtotime($filter['date'])) . '01 00:00:00',
                    date('Y-m-t' , strtotime($filter['date'])). ' 23:59:59'
                ));
            } elseif ($filter['dates'] == 'year') {
                $query->where($date_field, 'between', array(
                    date('Y-', strtotime($filter['date'])) . '01-01 00:00:00',
                    date('Y-' , strtotime($filter['date'])). '12-31 23:59:59'
                ));
            }elseif ($filter['dates'] == 'custom') {
                $query->where($date_field, 'between', array(date('Y-m-d', strtotime($filter['start_date'])). ' 00:00:00', date('Y-m-d', strtotime($filter['end_date'])) . ' 23:59:59'));
            }
        }

        if($group_by) {
            $query->group_by($group_by);
        }

        $results = current($query->execute()->as_array());

        return $results;
    }

    function getTodaysPendingTotalByCompany(){
        $result = \DB::select(\DB::expr('sum(ps.amount) as total'),'c.company_id')
            ->from(array('payment_schedules','ps'))
            ->join(array('cases','c'), 'LEFT')->on('c.id', '=', 'ps.case_id')
            ->join(array('case_contact','cc'), 'LEFT')->on('cc.case_id', '=', 'ps.case_id')
            ->join(array('case_statuses','cs'), 'LEFT')->on('cs.case_id', '=', 'ps.case_id')
            ->where('ps.status_id','=', 2)
            ->and_where('ps.date_due','=', date('Y-m-d'))
            ->and_where('cs.is_client','=', 1)
            ->and_where('cs.accounting_status_id','=', 200)
            ->group_by('c.company_id')
            ->execute()
            ->as_array();

        if(empty($result)){
            return 0;
        }

        return $result;
    }

    function getTodayDueTotal($company_id=null){

        $query = \DB::select(\DB::expr('sum(amount) as total'))
            ->from('payment_schedules')
            ->join(array('cases','c'), 'LEFT')->on('c.id', '=', 'payment_schedules.case_id')
            ->where('status_id','IN', array(1,3))
            ->where('date_due','=', date('Y-m-d'));

        if($company_id){
            $query->where('c.company_id','=',$company_id);
        }

        $result = current($query->execute()->as_array());

        if(empty($result)){
            return 0;
        }

        return $result['total'];
    }

    function getTodayDueTotalByCompany(){
        $result = \DB::select(\DB::expr('sum(amount) as total'),'c.company_id')
            ->from('payment_schedules')
            ->join(array('cases','c'), 'LEFT')->on('c.id', '=', 'payment_schedules.case_id')
            ->where('status_id','IN', array(1,3))
            ->where('date_due','=', date('Y-m-d'))
            ->group_by('c.company_id')
            ->execute()
            ->as_array();

        if(empty($result)){
            return 0;
        }

        return $result;
    }


    static function findAll(){

        $query = \DB::select('ps.*',
            array(\DB::expr('CONCAT(u.first_name, " ", u.last_name)'), 'created_by_user'),
            array(\DB::expr('CONCAT(cc.first_name, " ", cc.last_name)'), 'client_name'),
            array('ps.id', 'schedule_id'),
            array('pst.name', 'payment_status'),
            array('s.name', 'status'),
            array('cc.dpp_contact_id', 'dpp_id'),
           'c.company_id','c.is_duplicate','c.last_action','c.action_count','c.created','c.updated','c.company_id',
            'cc.first_name','cc.last_name','cc.middle_name','cc.email','cc.primary_phone','cc.secondary_phone','cc.mobile_phone','cc.ssn','cc.dob','cc.fax','cc.address','cc.address_2','cc.city','cc.state','cc.zip','cc.country','cc.timezone','cc.title','cc.dpp_contact_id',
            'cs.is_company','cs.status_id','cs.campaign_id','cs.is_client','cs.docs_status','cs.is_deleted','cs.accounting_status_id',
            array('b.type', 'billing_type'),
            array('com.name', 'company')

        )
            ->from(array('payment_schedules','ps'))
            ->join(array('cases','c'), 'LEFT')->on('c.id', '=', 'ps.case_id')
            ->join(array('case_contact','cc'), 'LEFT')->on('cc.case_id', '=', 'ps.case_id')
            ->join(array('case_statuses','cs'), 'LEFT')->on('cs.case_id', '=', 'ps.case_id')
            ->join(array('statuses','s'), 'LEFT')->on('s.id', '=', 'cs.status_id')
            ->join(array('users','u'), 'LEFT')->on('u.id', '=', 'ps.created_by')
            ->join(array('companies','com'), 'LEFT')->on('com.id', '=', 'c.company_id')
            ->join(array('payment_statuses','pst'), 'LEFT')->on('pst.id', '=', 'ps.status_id')
            ->join(array('billing', 'b'), 'LEFT')->on('ps.billing_account_id','=','b.id')
            ->join(array('case_assignments', 'ca'),'LEFT')->on('ca.case_id','=','ps.case_id')
            ->join(array('users','u2'), 'LEFT')->on('u2.id', '=', 'ca.user_id')
            ->join(array('shared_cases','sc'), 'LEFT')->on('sc.case_id','=','c.id');;

        $query->where('ps.date_due', '=', date('Y-m-d'));
        $query->where('cs.is_client','=', 1);
        $query->where('cs.accounting_status_id','=', 200);

        switch(\Model_Account::getType()){
            case 'Account':
                break;
            case 'Company':
                $query->where_open();
                $query->where('c.company_id','=', \Model_System_User::getSessionMeta('company_id'));
                $query->or_where('sc.company_id','=', \Model_System_User::getSessionMeta('company_id'));
                $query->or_where('c.source_id','=', \Model_System_User::getSessionMeta('company_id'));
                $query->where_close();
                break;
            case 'Region':
                //$query->where('rg.region_id','=', Model_System_User::getSessionMeta('region_id'));
                $query->where('ca.user_id','=', \Model_System_User::getSessionMeta('id'));
                break;
            case 'User':
                $query->where('ca.user_id','=', \Model_System_User::getSessionMeta('id'));
                break;
        }

        $query->group_by('ps.id');
        $query->order_by('client_name', 'ASC');

        return $query->execute()->as_array();

        /*$fields = array('first_name','last_name');
        $result = $query->execute();
        return self::buildResult($result, $fields)*/
    }

    static function findAllByStatusId($status_id){

        $query = \DB::select('ps.*',
            array(\DB::expr('CONCAT(u.first_name, " ", u.last_name)'), 'created_by_user'),
            array('ps.id', 'schedule_id'),
            array('pst.name', 'payment_status'),
            array('c.dpp_contact_id', 'dpp_id'),
            'c.*',
            array(\DB::expr('CONCAT(co.first_name, " ", co.last_name)'), 'collector')
        )
            ->from(array('payment_schedules','ps'))
            ->join(array('cases','c'), 'LEFT')->on('c.id', '=', 'ps.case_id')
            ->join(array('case_contact','cc'), 'LEFT')->on('cc.case_id', '=', 'ps.case_id')
            ->join(array('case_statuses','cs'), 'LEFT')->on('cs.case_id', '=', 'ps.case_id')
            ->join(array('users','u'), 'LEFT')->on('u.id', '=', 'ps.created_by')
            ->join(array('users','co'), 'LEFT')->on('co.id', '=', 'ps.collector_id')
            ->join(array('payment_statuses','pst'), 'LEFT')->on('pst.id', '=', 'ps.status_id');

        $query->where('ps.status_id','=', $status_id);
        $query->where('c.is_client','=', 1);
        $query->where('c.paused','=', 0);

        $query->order_by('c.first_name', 'ASC');
        $query->limit(10);
        return $query->execute()->as_array();
    }

    static function findByCaseID($case_id){
        $query = \DB::select('ps.*',
            'b.type','b.card_type','b.name_on_account','b.bank_name','b.routing_number','b.account_number',
            'b.name_on_card','b.billing_street','b.billing_city','b.billing_state','b.billing_zip','b.verify_status_id',
            array(\DB::expr('CONCAT(u.first_name, " ", u.last_name)'), 'created_by_user'),
            array('pst.name', 'payment_status'),
            array(\DB::expr('CONCAT(co.first_name, " ", co.last_name)'), 'collector'),
            array('ptype.name', 'payment_type'),
            array('b.active','billing_account_active')
        )
            ->from(array('payment_schedules','ps'))
            ->join(array('cases','c'), 'LEFT')->on('c.id', '=', 'ps.case_id')
            ->join(array('case_contact','cc'), 'LEFT')->on('cc.case_id', '=', 'ps.case_id')
            ->join(array('case_statuses','cs'), 'LEFT')->on('cs.case_id', '=', 'ps.case_id')
            ->join(array('users','u'), 'LEFT')->on('u.id', '=', 'ps.created_by')
            ->join(array('users','co'), 'LEFT')->on('co.id', '=', 'ps.collector_id')
            ->join(array('payment_statuses','pst'), 'LEFT')->on('pst.id', '=', 'ps.status_id')
            ->join(array('payment_schedule_types','ptype'), 'LEFT')->on('ptype.id', '=', 'ps.type_id')
            ->join(array('billing', 'b'), 'LEFT')->on('ps.billing_account_id','=','b.id')
            ->where('ps.case_id', '=', $case_id)
            ->order_by('ps.date_due', 'ASC');
        return $query->execute()->as_array();

    }

    static function findPendingByCaseID($case_id){
        $query = \DB::select('ps.*',
            'b.type','b.card_type','b.name_on_account','b.bank_name','b.routing_number','b.account_number',
            'b.name_on_card','b.billing_street','b.billing_city','b.billing_state','b.billing_zip','b.verify_status_id',
            array(\DB::expr('CONCAT(u.first_name, " ", u.last_name)'), 'created_by_user'),
            array('pst.name', 'payment_status'),
            array(\DB::expr('CONCAT(co.first_name, " ", co.last_name)'), 'collector'),
            array('ptype.name', 'payment_type')
        )
            ->from(array('payment_schedules','ps'))
            ->join(array('cases','c'), 'LEFT')->on('c.id', '=', 'ps.case_id')
            ->join(array('case_contact','cc'), 'LEFT')->on('cc.case_id', '=', 'ps.case_id')
            ->join(array('case_statuses','cs'), 'LEFT')->on('cs.case_id', '=', 'ps.case_id')
            ->join(array('users','u'), 'LEFT')->on('u.id', '=', 'ps.created_by')
            ->join(array('users','co'), 'LEFT')->on('co.id', '=', 'ps.collector_id')
            ->join(array('payment_statuses','pst'), 'LEFT')->on('pst.id', '=', 'ps.status_id')
            ->join(array('payment_schedule_types','ptype'), 'LEFT')->on('ptype.id', '=', 'ps.type_id')
            ->join(array('billing', 'b'), 'LEFT')->on('ps.billing_account_id','=','b.id')
            ->where('ps.case_id', '=', $case_id)
            ->where('ps.status_id','=', 2)
            ->order_by('ps.date_due', 'ASC');
        return $query->execute()->as_array();

    }


    static function findByCompanyID($company_id){
        $query = \DB::select('ps.*',
            array(\DB::expr('CONCAT(u.first_name, " ", u.last_name)'), 'created_by_user'),
            array('pst.name', 'payment_status'),
            array('ptype.name', 'payment_type')
        )
            ->from(array('payment_schedules','ps'))
            ->join(array('cases','c'), 'LEFT')->on('c.id', '=', 'ps.case_id')
            ->join(array('case_contact','cc'), 'LEFT')->on('cc.case_id', '=', 'ps.case_id')
            ->join(array('case_statuses','cs'), 'LEFT')->on('cs.case_id', '=', 'ps.case_id')
            ->join(array('users','u'), 'LEFT')->on('u.id', '=', 'ps.created_by')
            ->join(array('users','co'), 'LEFT')->on('co.id', '=', 'ps.collector_id')
            ->join(array('payment_statuses','pst'), 'LEFT')->on('pst.id', '=', 'ps.status_id')
            ->join(array('payment_schedule_types','ptype'), 'LEFT')->on('ptype.id', '=', 'ps.type_id')
            ->join(array('billing', 'b'), 'LEFT')->on('ps.billing_account_id','=','b.id')
            ->where('c.company_id', '=', $company_id);
        return $query->execute()->as_array();

    }

    static function findByCaseIDs($case_ids){
        $query = \DB::select('ps.*',
            array(\DB::expr('CONCAT(u.first_name, " ", u.last_name)'), 'created_by_user'),
            array('pst.name', 'payment_status'),
            array('ptype.name', 'payment_type')
        )
            ->from(array('payment_schedules','ps'))
            ->join(array('cases','c'), 'LEFT')->on('c.id', '=', 'ps.case_id')
            ->join(array('case_contact','cc'), 'LEFT')->on('cc.case_id', '=', 'ps.case_id')
            ->join(array('case_statuses','cs'), 'LEFT')->on('cs.case_id', '=', 'ps.case_id')
            ->join(array('users','u'), 'LEFT')->on('u.id', '=', 'ps.created_by')
            ->join(array('users','co'), 'LEFT')->on('co.id', '=', 'ps.collector_id')
            ->join(array('payment_statuses','pst'), 'LEFT')->on('pst.id', '=', 'ps.status_id')
            ->join(array('payment_schedule_types','ptype'), 'LEFT')->on('ptype.id', '=', 'ps.type_id')
            ->join(array('billing', 'b'), 'LEFT')->on('ps.billing_account_id','=','b.id')
            ->where('c.id', 'IN', $case_ids);
        return $query->execute()->as_array();

    }

    static function findDeclinedByDate($start_date, $end_date){
        $query = \DB::select('ps.*',
            'b.type','b.card_type','b.name_on_account','b.bank_name','b.routing_number','b.account_number',
            'b.name_on_card','b.billing_street','b.billing_city','b.billing_state','b.billing_zip','b.verify_status_id',
            array(\DB::expr('CONCAT(u.first_name, " ", u.last_name)'), 'created_by_user'),
            array('pst.name', 'payment_status'),
            array(\DB::expr('CONCAT(co.first_name, " ", co.last_name)'), 'collector'),
            array('ptype.name', 'payment_type')
        )
            ->from(array('payment_schedules','ps'))
            ->join(array('cases','c'), 'LEFT')->on('c.id', '=', 'ps.case_id')
            ->join(array('case_contact','cc'), 'LEFT')->on('cc.case_id', '=', 'ps.case_id')
            ->join(array('case_statuses','cs'), 'LEFT')->on('cs.case_id', '=', 'ps.case_id')
            ->join(array('users','u'), 'LEFT')->on('u.id', '=', 'ps.created_by')
            ->join(array('users','co'), 'LEFT')->on('co.id', '=', 'ps.collector_id')
            ->join(array('payment_statuses','pst'), 'LEFT')->on('pst.id', '=', 'ps.status_id')
            ->join(array('payment_schedule_types','ptype'), 'LEFT')->on('ptype.id', '=', 'ps.type_id')
            ->join(array('billing', 'b'), 'LEFT')->on('ps.billing_account_id','=','b.id')

            ->order_by('ps.date_due', 'ASC');
        return $query->execute()->as_array();

    }


    static function findByCaseAndType($case_id, $type_id){
        $query = \DB::select('ps.*',
            'b.type','b.card_type','b.name_on_account','b.bank_name','b.routing_number','b.account_number',
            'b.name_on_card','b.billing_street','b.billing_city','b.billing_state','b.billing_zip','b.verify_status_id',
            array(\DB::expr('CONCAT(u.first_name, " ", u.last_name)'), 'created_by_user'),
            array('pst.name', 'payment_status'),
            array(\DB::expr('CONCAT(co.first_name, " ", co.last_name)'), 'collector'),
            array('ptype.name', 'payment_type'),
            array('gateways.name','gateway_name'),
            array('gateways.label','gateway_label'),
            array('psg.gateway_id','schedule_gateway_id')
        )
            ->from(array('payment_schedules','ps'))
            ->join(array('cases','c'), 'LEFT')->on('c.id', '=', 'ps.case_id')
            ->join(array('case_contact','cc'), 'LEFT')->on('cc.case_id', '=', 'ps.case_id')
            ->join(array('case_statuses','cs'), 'LEFT')->on('cs.case_id', '=', 'ps.case_id')
            ->join(array('users','u'), 'LEFT')->on('u.id', '=', 'ps.created_by')
            ->join(array('users','co'), 'LEFT')->on('co.id', '=', 'ps.collector_id')
            ->join(array('payment_statuses','pst'), 'LEFT')->on('pst.id', '=', 'ps.status_id')
            ->join(array('payment_schedule_types','ptype'), 'LEFT')->on('ptype.id', '=', 'ps.type_id')
            ->join(array('billing', 'b'), 'LEFT')->on('ps.billing_account_id','=','b.id')
            ->join(array('payment_schedule_gateways','psg'), 'LEFT')
            ->on('psg.account_type_id', '=', 'cs.account_type_id')
            ->on('psg.schedule_type_id', '=', 'ps.type_id')
            ->on('psg.billing_type_id', '=', 'b.type_id')
            ->on('psg.company_id', '=', 'c.company_id')
            ->join('gateways','LEFT')->on('gateways.id','=','psg.gateway_id')

            ->where('ps.case_id', '=', $case_id)
            ->where('ps.type_id', '=', $type_id);
        return current($query->execute()->as_array());

    }

    static function findTotalByCaseAndType($case_id, $type_id){
        $query = \DB::select(\DB::expr('SUM(ps.amount) as total'))
            ->from(array('payment_schedules','ps'))
            ->where('ps.case_id', '=', $case_id)
            ->where('ps.type_id', '=', $type_id);
        return current($query->execute()->as_array());

    }

    static function findAllByCaseAndType($case_id, $type_id){
        $query = \DB::select('ps.*',
            'b.type','b.card_type','b.name_on_account','b.bank_name','b.routing_number','b.account_number',
            'b.name_on_card','b.billing_street','b.billing_city','b.billing_state','b.billing_zip','b.verify_status_id',
            array(\DB::expr('CONCAT(u.first_name, " ", u.last_name)'), 'created_by_user'),
            array('pst.name', 'payment_status'),
            array(\DB::expr('CONCAT(co.first_name, " ", co.last_name)'), 'collector')
        )
            ->from(array('payment_schedules','ps'))
            ->join(array('cases','c'), 'LEFT')->on('c.id', '=', 'ps.case_id')
            ->join(array('case_contact','cc'), 'LEFT')->on('cc.case_id', '=', 'ps.case_id')
            ->join(array('case_statuses','cs'), 'LEFT')->on('cs.case_id', '=', 'ps.case_id')
            ->join(array('users','u'), 'LEFT')->on('u.id', '=', 'ps.created_by')
            ->join(array('users','co'), 'LEFT')->on('co.id', '=', 'ps.collector_id')
            ->join(array('payment_statuses','pst'), 'LEFT')->on('pst.id', '=', 'ps.status_id')
            ->join(array('billing', 'b'), 'LEFT')->on('ps.billing_account_id','=','b.id')
            ->where('ps.case_id', '=', $case_id)
            ->where('ps.type_id', '=', $type_id);
        return $query->execute()->as_array();

    }

    static function findAllPendingByCaseAndType($case_id, $type_id){
        $query = \DB::select('ps.*',
            'b.type','b.card_type','b.name_on_account','b.bank_name','b.routing_number','b.account_number',
            'b.name_on_card','b.billing_street','b.billing_city','b.billing_state','b.billing_zip','b.verify_status_id',
            array(\DB::expr('CONCAT(u.first_name, " ", u.last_name)'), 'created_by_user'),
            array('pst.name', 'payment_status'),
            array(\DB::expr('CONCAT(co.first_name, " ", co.last_name)'), 'collector')
        )
            ->from(array('payment_schedules','ps'))
            ->join(array('cases','c'), 'LEFT')->on('c.id', '=', 'ps.case_id')
            ->join(array('case_contact','cc'), 'LEFT')->on('cc.case_id', '=', 'ps.case_id')
            ->join(array('case_statuses','cs'), 'LEFT')->on('cs.case_id', '=', 'ps.case_id')
            ->join(array('users','u'), 'LEFT')->on('u.id', '=', 'ps.created_by')
            ->join(array('users','co'), 'LEFT')->on('co.id', '=', 'ps.collector_id')
            ->join(array('payment_statuses','pst'), 'LEFT')->on('pst.id', '=', 'ps.status_id')
            ->join(array('billing', 'b'), 'LEFT')->on('ps.billing_account_id','=','b.id')
            ->where('ps.case_id', '=', $case_id)
            ->where('ps.status_id','=', 2)
            ->where('ps.type_id', '=', $type_id);
        return $query->execute()->as_array();

    }

    static function findByCaseAndTransactionID($case_id, $transaction_id){
        $query = \DB::select('ps.*', 'cc.address','cc.city','cc.state','cc.zip', 'cc.primary_phone',
            'cc.first_name','cc.last_name', 'cc.email', 'cc.dob', 'cc.ssn',
            'b.type','b.card_type','b.name_on_account','b.bank_name','b.routing_number','b.account_number',
            'b.name_on_card','b.billing_street','b.billing_city','b.billing_state','b.billing_zip','b.verify_status_id',
            array('pst.name', 'payment_status'),
            array('pst.id', 'payment_status_id'),
            array('ps.id','payment_schedule_id'),
            array('co.id', 'collector_id'),
            'c.company_id'
        )
            ->from(array('payment_schedules','ps'))
            ->join(array('cases','c'), 'LEFT')->on('c.id', '=', 'ps.case_id')
            ->join(array('case_contact','cc'), 'LEFT')->on('cc.case_id', '=', 'ps.case_id')
            ->join(array('case_statuses','cs'), 'LEFT')->on('cs.case_id', '=', 'ps.case_id')
            ->join(array('users','u'), 'LEFT')->on('u.id', '=', 'ps.created_by')
            ->join(array('users','co'), 'LEFT')->on('co.id', '=', 'ps.collector_id')
            ->join(array('payment_statuses','pst'), 'LEFT')->on('pst.id', '=', 'ps.status_id')
            ->join(array('billing', 'b'), 'LEFT')->on('b.id','=','ps.billing_account_id')
            ->where('ps.case_id', '=', $case_id)
            ->where('ps.transaction_id', '=', $transaction_id);
        return current($query->execute()->as_array());

    }

    static function findDetailedByIds($ids){

        $query = \DB::select('ps.*', 'cc.address','cc.address_2','cc.city','cc.state','cc.zip', 'cc.primary_phone',
            'cc.first_name','cc.last_name', 'cc.email', 'cc.dob', 'cc.ssn',
            'b.type','b.card_type','b.credit_card_number','b.name_on_card','b.exp_month','b.exp_year','b.name_on_account','b.bank_name','b.routing_number','b.account_number','b.cvv',
            'b.name_on_card','b.billing_street','b.billing_city','b.billing_state','b.billing_zip','b.verify_status_id',
            array('b.title','billing_account_title'),
            array('pst.name', 'payment_status'),
            array('b.id','billing_account_id'),
            array('pst.id', 'payment_status_id'),
            array('ps.id','payment_schedule_id'),
            array('co.id', 'collector_id'),
            'c.company_id',
            array('comp.legal_name','company_name'),
            array('gateways.name','gateway_name'),
            array('gateways.label','gateway_label'),
            array('psg.gateway_id','schedule_gateway_id'),
            'cs.account_type_id',
            array('at.name','account_type_name'),
            'comp.case_email_domain'
        )
            ->from(array('payment_schedules','ps'))
            ->join(array('cases','c'), 'LEFT')->on('c.id', '=', 'ps.case_id')
            ->join(array('case_contact','cc'), 'LEFT')->on('cc.case_id', '=', 'ps.case_id')
            ->join(array('case_statuses','cs'), 'LEFT')->on('cs.case_id', '=', 'ps.case_id')
            ->join(array('companies','comp'),'LEFT')->on('c.company_id','=','comp.id')
            ->join(array('users','u'), 'LEFT')->on('u.id', '=', 'ps.created_by')
            ->join(array('users','co'), 'LEFT')->on('co.id', '=', 'ps.collector_id')
            ->join(array('payment_statuses','pst'), 'LEFT')->on('pst.id', '=', 'ps.status_id')
            ->join(array('billing', 'b'), 'LEFT')->on('b.id','=','ps.billing_account_id')
            ->join(array('accounting_types','at'), 'LEFT')->on('cs.account_type_id', '=', 'at.id')
            ->join(array('payment_schedule_gateways','psg'), 'LEFT')
                ->on('psg.account_type_id', '=', 'cs.account_type_id')
                ->on('psg.schedule_type_id', '=', 'ps.type_id')
                ->on('psg.billing_type_id', '=', 'b.type_id')
                ->on('psg.company_id', '=', 'c.company_id')
            ->join('gateways','LEFT')->on('gateways.id','=','psg.gateway_id')
            ->where('ps.id', 'in', $ids);

        return $query->execute()->as_array();

    }


    static function findDetailedById($id){

        $query = \DB::select('ps.*', 'cc.address','cc.address_2','cc.city','cc.state','cc.zip', 'cc.primary_phone',
            'cc.first_name','cc.last_name', 'cc.email', 'cc.dob', 'cc.ssn',
            'b.type','b.card_type','b.credit_card_number','b.name_on_card','b.exp_month','b.exp_year','b.name_on_account','b.bank_name','b.routing_number','b.account_number','b.cvv',
            'b.name_on_card','b.billing_street','b.billing_city','b.billing_state','b.billing_zip','b.verify_status_id',
            array('pst.name', 'payment_status'),
            array('pst.id', 'payment_status_id'),
            array('ps.id','payment_schedule_id'),
            array('co.id', 'collector_id'),
            'c.company_id',
            array('comp.legal_name','company_name'),
            array('gateways.name','gateway_name'),
            array('gateways.label','gateway_label'),
            array('psg.gateway_id','schedule_gateway_id'),
            'cs.account_type_id',
            array('at.name','account_type_name'),
            'comp.case_email_domain'

        )
            ->from(array('payment_schedules','ps'))
            ->join(array('cases','c'), 'LEFT')->on('c.id', '=', 'ps.case_id')
            ->join(array('case_contact','cc'), 'LEFT')->on('cc.case_id', '=', 'ps.case_id')
            ->join(array('case_statuses','cs'), 'LEFT')->on('cs.case_id', '=', 'ps.case_id')
            ->join(array('companies','comp'),'LEFT')->on('c.company_id','=','comp.id')
            ->join(array('users','u'), 'LEFT')->on('u.id', '=', 'ps.created_by')
            ->join(array('users','co'), 'LEFT')->on('co.id', '=', 'ps.collector_id')
            ->join(array('payment_statuses','pst'), 'LEFT')->on('pst.id', '=', 'ps.status_id')
            ->join(array('billing', 'b'), 'LEFT')->on('b.id','=','ps.billing_account_id')
            ->join(array('accounting_types','at'), 'LEFT')->on('cs.account_type_id', '=', 'at.id')
            ->join(array('payment_schedule_gateways','psg'), 'LEFT')
            ->on('psg.account_type_id', '=', 'cs.account_type_id')
            ->on('psg.schedule_type_id', '=', 'ps.type_id')
            ->on('psg.billing_type_id', '=', 'b.type_id')
            ->on('psg.company_id', '=', 'c.company_id')
            ->join('gateways','LEFT')->on('gateways.id','=','psg.gateway_id')
            ->where('ps.id', '=', $id);

        return current($query->execute()->as_array());

    }

    /*
        static function findDetailedByIds($ids){

            $query = \DB::select('ps.*', 'b.bank_name','b.routing_number','b.account_number','b.credit_card_number','b.name_on_card','b.exp_month','b.exp_year','b.cvv','b.billing_street','b.billing_city','b.billing_state','b.billing_zip','b.credit_card_type', 'sp.first_name', 'sp.last_name',
                array('pst.name', 'payment_status'),
                array('pst.id', 'payment_status_id'),
                array('b.id','billing_id'),
                array('ps.id','payment_schedule_id'),
                array('co.id', 'collector_id')
            )
                ->from(array('payment_schedules','ps'))
                ->join(array('cases','c'), 'LEFT')->on('c.id', '=', 'ps.case_id')
                ->join(array('case_profile','cp'), 'LEFT')->on('cp.case_id', '=', 'ps.case_id')
                ->join(array('users','u'), 'LEFT')->on('u.id', '=', 'ps.created_by')
                ->join(array('users','co'), 'LEFT')->on('co.id', '=', 'ps.collector_id')
                ->join(array('payment_statuses','pst'), 'LEFT')->on('pst.id', '=', 'ps.status_id')
                ->join(array('billing','b'), 'LEFT')->on('b.case_id', '=', 'ps.case_id')
                ->join(array('student_profiles','sp'), 'LEFT')->on('b.case_id', '=', 'sp.case_id') // TODO get first and last from contact record
                ->where('ps.id', 'in', $ids);

            return $query->execute()->as_array();

        }*/

    static function findByIds($ids){

        $query = \DB::select('ps.*','c.company_id','c.is_duplicate','c.last_action','c.action_count',
            'cc.first_name','cc.last_name','cc.middle_name','cc.email','cc.primary_phone','cc.secondary_phone','cc.mobile_phone','cc.ssn','cc.dob','cc.fax','cc.address','cc.address_2','cc.city','cc.state','cc.zip','cc.country','cc.timezone','cc.title',
            'cs.is_company','cs.status_id','cs.campaign_id','cs.is_client','cs.docs_status','cs.is_deleted','cs.accounting_status_id',
            array(\DB::expr('CONCAT(u.first_name, " ", u.last_name)'), 'created_by_user'),
            array('pst.name', 'payment_status'),
            array('ps.id', 'schedule_id'),
            array('cc.dpp_contact_id', 'dpp_id'),
            array(\DB::expr('CONCAT(co.first_name, " ", co.last_name)'), 'collector'),
            array('gateways.name','gateway_name'),
            array('psg.gateway_id','schedule_gateway_id'),
            array('accs.name','accounting_status_name'),
            array('at.name','account_type_name')
        )
            ->from(array('payment_schedules','ps'))

            ->join(array('cases','c'), 'LEFT')->on('c.id', '=', 'ps.case_id')
            ->join(array('case_contact','cc'), 'LEFT')->on('cc.case_id', '=', 'ps.case_id')
            ->join(array('case_statuses','cs'), 'LEFT')->on('cs.case_id', '=', 'ps.case_id')
            ->join(array('users','u'), 'LEFT')->on('u.id', '=', 'ps.created_by')
            ->join(array('users','co'), 'LEFT')->on('co.id', '=', 'ps.collector_id')
            ->join(array('payment_statuses','pst'), 'LEFT')->on('pst.id', '=', 'ps.status_id')
            ->join(array('billing', 'b'), 'LEFT')->on('b.id','=','ps.billing_account_id')
            ->join(array('accounting_types','at'), 'LEFT')->on('cs.account_type_id', '=', 'at.id')
            ->join(array('accounting_statuses','accs'), 'LEFT')->on('accs.id', '=', 'cs.accounting_status_id')
            ->join(array('payment_schedule_gateways','psg'), 'LEFT')
            ->on('psg.account_type_id', '=', 'cs.account_type_id')
            ->on('psg.schedule_type_id', '=', 'ps.type_id')
            ->on('psg.billing_type_id', '=', 'b.type_id')
            ->on('psg.company_id', '=', 'c.company_id')
            ->join('gateways','LEFT')->on('gateways.id','=','psg.gateway_id')

            ->where('ps.id', 'in', $ids);
        return $query->execute()->as_array();

    }

    static function findByScheduleIds($ids){

        $query = \DB::select('ps.*')
            ->from(array('payment_schedules','ps'))
            ->where('ps.id', 'in', $ids);
        return $query->execute()->as_array();

    }


    static function recalculatePaymentNumForAll(){
        $ids = \DB::select('case_id')->from('payment_schedules')->group_by('case_id')->order_by('created','DESC')->limit(10000)->execute()->as_array();
        foreach($ids as $id){
            self::renumberPayments($id['case_id']);
            self::renumberPaidPayments($data['case_id']);
        }
    }


    static function findNextPayment($case_id){
        $result = \DB::select()->from('payment_schedules')->where('case_id', '=', $case_id)->where('status', 'in', array('pending','processing','hold'))->order_by('date_due')->limit(1)->execute();
        return current($result->as_array());
    }

    static function findNextPendingPayment($case_id){

        $result = \DB::select('payment_schedules.*', 'b.type','b.card_type','b.title','b.credit_card_number','b.name_on_card','b.exp_month','b.exp_year','b.name_on_account','b.bank_name','b.routing_number','b.account_number','b.cvv',
            'b.name_on_card','b.billing_street','b.billing_city','b.billing_state','b.billing_zip','b.verify_status_id')
            ->from('payment_schedules')
            ->join(array('billing', 'b'))->on('b.id','=','payment_schedules.billing_account_id')
            ->where('payment_schedules.case_id', '=', $case_id)
            ->where('payment_schedules.status_id', '=', 2)
            ->where('payment_schedules.date_due','>=',date("Y-m-d"))
            ->order_by('payment_schedules.date_due')->limit(1)->execute();

        return current($result->as_array());
    }


    static function findByFilter($filter, $offset = 0, $limit = 10000, $sort_field = 'created', $order = 'desc', $count_only = false, $columns = array()){

        if(!$count_only){

            $query = \DB::select('ps.*',
                array(\DB::expr('CONCAT(u.first_name, " ", u.last_name)'), 'created_by_user'),
                array(\DB::expr('CONCAT(cc.first_name, " ", cc.last_name)'), 'client_name'),
                array('pst.name', 'payment_status'),
                array('ps.id', 'schedule_id'),
                array('s.name', 'status'),
                'c.company_id','c.is_duplicate','c.last_action','c.action_count','c.created','c.updated',
                'cc.first_name','cc.last_name','cc.middle_name','cc.email','cc.primary_phone','cc.secondary_phone','cc.mobile_phone','cc.ssn','cc.dob','cc.fax','cc.address','cc.address_2','cc.city','cc.state','cc.zip','cc.country','cc.timezone','cc.title','cc.dpp_contact_id',
                'cs.is_company','cs.status_id','cs.campaign_id','cs.is_client','cs.docs_status','cs.is_deleted','cs.accounting_status_id',
                array('b.type', 'billing_type'),
                array(\DB::expr('CONCAT(b.exp_month, "/", b.exp_year)'), 'cc_exp'),
                array(\DB::expr('CONCAT(cau.first_name, " ", cau.last_name)'), 'dept_user'),
                array('com.name', 'company'),
                array('pscht.name', 'schedule_type'),
                array('gateways.name','gateway'),
                array('at.name','account_type_name'),
                array('accs.name','accounting_status_name'),
                array('camp.name','campaign_name'),
                'cs.activation_date',
                'app.payment_profile_id',
                array('p.created','receipt_date'),
                array('payment_sum.paid','payment_sum_total')
            );

        }else{
            $query = \DB::select(\DB::expr('COUNT(DISTINCT ps.id) as total_records'));
        }

        $query->from(array('payment_schedules','ps'))
            ->join(array('payments','p'), 'LEFT')->on('p.schedule_id', '=', 'ps.id')
            ->join(array('cases','c'), 'LEFT')->on('c.id', '=', 'ps.case_id')
            ->join(array('case_contact','cc'), 'LEFT')->on('cc.case_id', '=', 'ps.case_id')
            ->join(array('case_statuses','cs'), 'LEFT')->on('cs.case_id', '=', 'ps.case_id')
            ->join(array('campaigns','camp'), 'LEFT')->on('camp.id', '=', 'cs.campaign_id')
            ->join(array('statuses','s'), 'LEFT')->on('s.id', '=', 'cs.status_id')
            ->join(array('accounting_types','at'), 'LEFT')->on('cs.account_type_id', '=', 'at.id')
            ->join(array('users','u'), 'LEFT')->on('u.id', '=', 'ps.created_by')
            ->join(array('payment_statuses','pst'), 'LEFT')->on('pst.id', '=', 'ps.status_id')
            ->join(array('payment_schedule_types','pscht'), 'LEFT')->on('pscht.id', '=', 'ps.type_id')
            ->join(array('billing', 'b'), 'LEFT')->on('b.id','=','ps.billing_account_id')
            ->join(array('anet_payment_profiles', 'app'), 'LEFT')->on('app.billing_id','=','b.id')
            ->join(array('case_assignments', 'ca'),'LEFT')->on('ca.case_id','=','c.id')
            ->join(array('departments', 'd'),'LEFT')->on('d.id','=','ca.department_id')
            ->join(array('esign_docs', 'esign'),'LEFT')->on('esign.case_id','=','ps.case_id')
            ->join(array('users', 'cau'),'LEFT')->on('cau.id','=','ca.user_id')
            ->join(array('shared_cases','sc'), 'LEFT')->on('sc.case_id','=','ps.case_id')
            ->join(array('companies','com'), 'LEFT')->on('com.id', '=', 'c.company_id')
            ->join(array('accounting_statuses','accs'), 'LEFT')->on('accs.id', '=', 'cs.accounting_status_id')
            ->join(array('payment_schedule_gateways','psg'), 'LEFT')
                ->on('psg.account_type_id', '=', 'cs.account_type_id')
                ->on('psg.schedule_type_id', '=', 'ps.type_id')
                ->on('psg.billing_type_id', '=', 'b.type_id')
                ->on('psg.company_id', '=', 'c.company_id')
            ->join(array(\DB::expr('(select SUM(p.amount) as paid, p.case_id
                        from payments p
                        where p.status_id = 3
                        group by p.case_id)'),'payment_sum'), 'LEFT')->on('payment_sum.case_id','=','ps.case_id')
            ->join('gateways','LEFT')->on('gateways.id','=','psg.gateway_id');

        //$query->where('cs.is_client','=', 1);

        if(!isset($filter['date_field']) || empty($filter['date_field'])){
            $filter['date_field'] = 'ps.date_due'; // Manually set since we are not using last actions
        }



        if((isset($filter['status_id']) && !empty($filter['status_id'])) || (isset($filter['status_id']) && $filter['status_id'] === '0')){
            $query->where('ps.status_id', '=', $filter['status_id']);
        }

        if((isset($filter['status_ids']) && !empty($filter['status_ids'])) || (isset($filter['status_ids']) && $filter['status_ids'] === '0')){
            $query->where('ps.status_id', 'IN', $filter['status_ids']);
        }

        if((isset($filter['campaign_id']) && !empty($filter['campaign_id'])) || (isset($filter['campaign_id']) && $filter['campaign_id'] === '0')){
            $query->where('camp.id', '=', $filter['campaign_id']);
        }

        if((isset($filter['case_id']) && !empty($filter['case_id'])) || (isset($filter['case_id']) && $filter['case_id'] === '0')){
            $query->where('c.id', '=', $filter['case_id']);
        }

        if(isset($filter['pmt_num']) && !empty($filter['pmt_num'])){

            if(is_array($filter['pmt_num'])){
                $ids = array();
                foreach($filter['pmt_num']  as $num){
                    $ids[] = $num;
                }
                $query->where('ps.pmt_num', 'IN', $ids);
            }else {
                $query->where('ps.pmt_num', '=', $filter['pmt_num']);
            }
        }

        if(in_array('last_paid_payment_date', $columns)){

            $query->select_array(array(
                array('last_payment.amount', 'last_paid_payment_amount'),
                array('last_payment.created', 'last_paid_payment_date')
            ));

            $query->join(array(\DB::expr('(select MAX(payments.id) payment_id, payments.case_id
                        from payments
                        where payments.status_id = 3
                        group by payments.case_id)'),'last_payment_max'), 'LEFT')->on('last_payment_max.case_id','=','c.id');
            $query->join(array('payments','last_payment'), 'LEFT')->on('last_payment.id','=','last_payment_max.payment_id');

        }

        if(in_array('payments_remaining', $columns)){

            $query->select_array(array(
                array('payments_remaining_join.total', 'payments_remaining')
            ));

            $query->join(array(\DB::expr('(select COUNT(payment_schedules.id) as total, payment_schedules.case_id
                        from payment_schedules
                        where payment_schedules.status_id = 2
                        group by payment_schedules.case_id)'),'payments_remaining_join'), 'LEFT')->on('payments_remaining_join.case_id','=','c.id');
        }


        if((isset($filter['gateway_id']) && !empty($filter['gateway_id'])) || (isset($filter['gateway_id']) && $filter['gateway_id'] === '0')){
            $query->where('ps.gateway_id', '=', $filter['gateway_id']);
        }

        if((isset($filter['accounting_status_id']) && !empty($filter['accounting_status_id'])) || (isset($filter['accounting_status_id']) && $filter['accounting_status_id'] === '0')){
            $query->where('cs.accounting_status_id', '=', $filter['accounting_status_id']);
        }

        if((isset($filter['account_type_id']) && !empty($filter['account_type_id'])) || (isset($filter['account_type_id']) && $filter['account_type_id'] === '0')){
            $query->where('cs.account_type_id', '=', $filter['account_type_id']);
        }

        if((isset($filter['has_gateway']) && !empty($filter['has_gateway']))){
            if($filter['has_gateway'] == 1) {
                $query->where('psg.id', null, \DB::expr('IS NOT NULL'));
            }else{
                $query->where('psg.id', '=', NULL);
            }
        }

        if((isset($filter['doc_ids']) && !empty($filter['doc_ids'])) ){
            $query->where('esign.form_id','IN', $filter['doc_ids']);
            $query->where('esign.status','=', 'Signed');
        }


        if((isset($filter['amount']) && !empty($filter['amount'])) && isset($filter['amount_operator']) && !empty($filter['amount_operator'])){
            $query->where('ps.amount', $filter['amount_operator'], $filter['amount']);
        }

        if((isset($filter['billing_type']) && !empty($filter['billing_type'])) && $filter['billing_type'] != '0'){
            $query->where('b.type', '=', $filter['billing_type']);
        }

        if((isset($filter['verified']) && !empty($filter['verified']))){
            if($filter['verified'] == 1) {
                $query->where('app.payment_profile_id', null, \DB::expr('IS NOT NULL'));
            }else{
                $query->where('app.payment_profile_id', '=', NULL);
            }
        }

        if((isset($filter['cc_on_file']) && !empty($filter['cc_on_file']))){
            if($filter['cc_on_file'] == 'yes') {
                $query->where('b.credit_card_number', null, \DB::expr('IS NOT NULL'));
            }else{
                $query->where('b.credit_card_number', null, \DB::expr('IS NULL'));
            }
        }

        if(isset($filter['region_id']) && !empty($filter['region_id'])){
            $query->where('cau.region_id', '=', $filter['region_id']);
        }

        if(isset($filter['type_id']) && !empty($filter['type_id'])){
            $query->where('ps.type_id', '=', $filter['type_id']);
        }

        if(isset($filter['department_id']) && !empty($filter['department_id'])){
            $query->where('ca.department_id', '=', $filter['department_id']);
        }

        if(isset($filter['user_id']) && !empty($filter['user_id'])){
            $query->where('ca.user_id', '=', $filter['user_id']);
        }

        if(isset($filter['created_user_id']) && !empty($filter['created_user_id'])){
            $query->where('ps.created_by', '=', $filter['created_user_id']);
        }

        if(isset($filter['states']) && !empty($filter['states'])){
            $query->where('cc.state', 'IN', $filter['states']);
        }

        if(isset($filter['is_client']) && !empty($filter['is_client'])){
            $query->where('cs.is_client', 'IN', $filter['is_client']);
        }

        if(isset($filter['company_id']) && !empty($filter['company_id'])){
            if(is_array($filter['company_id'])){
                $query->where('c.company_id', 'IN', $filter['company_id']);
            }else {
                $query->where('c.company_id', '=', $filter['company_id']);
            }
        }

        if(isset($filter['auto_run']) && !empty($filter['auto_run'])){

            $query->join(array('services_companies','serv_com'), 'LEFT')->on('serv_com.company_id', '=', 'c.company_id');
            $query->where('serv_com.service_id', '=', 117);
        }


        if(isset($filter['start_date']) && !empty($filter['start_date']) && isset($filter['end_date']) && !empty($filter['end_date'])){

            $query->where($filter['date_field'], 'between', array(date('Y-m-d', strtotime($filter['start_date'])), date('Y-m-d', strtotime($filter['end_date'])) . ' 23:59:59'));

        }else {
            if (!empty($filter['dates']) && $filter['dates'] != 'all_time') {
                if ($filter['dates'] == 'today') {
                    $query->where($filter['date_field'], 'between', array(date('Y-m-d 00:00:00'), date('Y-m-d 23:59:59')));
                } elseif ($filter['dates'] == 'yesterday') {
                    $yest = date('Y-m-d', strtotime('-1 day'));
                    $query->where($filter['date_field'], 'between', array($yest . ' 00:00:00', $yest . ' 23:59:59'));
                } elseif ($filter['dates'] == 'last7') {
                    $query->where($filter['date_field'], 'between', array(date('Y-m-d', strtotime('-7 days')), date('Y-m-d', strtotime(date('Y-m-d'))) . ' 23:59:59'));
                } elseif ($filter['dates'] == 'last30') {
                    $query->where($filter['date_field'], 'between', array(date('Y-m-d', strtotime('-30 days')), date('Y-m-d', strtotime(date('Y-m-d'))) . ' 23:59:59'));
                } elseif ($filter['dates'] == 'this_month') {
                    $query->where($filter['date_field'], 'between', array(date('Y-m-') . '01', date('Y-m-d H:59:59')));
                } elseif ($filter['dates'] == 'last_month') {
                    $query->where($filter['date_field'], 'between', array(date('Y-m-', strtotime('-1 month')) . '01', date('Y-m-t 23:59:59', strtotime('-1 month'))));
                } elseif ($filter['dates'] == 'custom') {
                    $query->where($filter['date_field'], 'between', array(date('Y-m-d', strtotime($filter['start_date'])), date('Y-m-d', strtotime($filter['end_date'])) . ' 23:59:59'));
                }
            }
        }


        $query = \Model_System_Access::queryAccess($query);

        /*switch(\Model_Account::getType()){
            case 'Master':

                break;
            case 'Company':
                $query->where_open();
                $query->where('c.company_id','=', \Model_System_User::getSessionMeta('company_id'));
                $query->or_where('sc.company_id','=', \Model_System_User::getSessionMeta('company_id'));
                $query->or_where('c.source_id','=', \Model_System_User::getSessionMeta('company_id'));
                $query->where_close();
                break;
            case 'Region':
                //$query->where('rg.region_id','=', Model_System_User::getSessionMeta('region_id'));
                $query->where('ca.user_id','=', \Model_System_User::getSessionMeta('id'));
                break;
            case 'User':
                $query->where('ca.user_id','=', \Model_System_User::getSessionMeta('id'));
                break;
        }*/

        if(!$count_only){
            $query->offset($offset)->limit($limit)
                ->order_by($sort_field, $order)
                ->group_by('ps.id');
            //Model_Log::addQuery('payment_schedule', $query);
        }else{
            $result = $query->execute();
            $row = current($result->as_array());
            return $row['total_records'];
        }

        return $query->execute()->as_array();
    }


    static function getIdsFromSchedules($payment_schedules){
        $id_list = array();
        foreach($payment_schedules as $ps){
            $id_list[] = $ps['id'];
        }
        return $id_list;
    }

    static function countItems(){

        $query = \DB::select(\DB::expr('COUNT(DISTINCT ps.id) as items'),  array(\DB::expr('CONCAT(cc.first_name, " ", cc.last_name)'), 'client_name'))

            ->from(array('payment_schedules','ps'))
            ->join(array('cases','c'), 'LEFT')->on('c.id', '=', 'ps.case_id')
            ->join(array('case_contact','cc'), 'LEFT')->on('cc.case_id', '=', 'ps.case_id')
            ->join(array('case_statuses','cs'), 'LEFT')->on('cs.case_id', '=', 'ps.case_id')
            ->join(array('statuses','s'), 'LEFT')->on('s.id', '=', 'cs.status_id')
            ->join(array('users','u'), 'LEFT')->on('u.id', '=', 'ps.created_by')
            ->join(array('companies','com'), 'LEFT')->on('com.id', '=', 'c.company_id')
            ->join(array('payment_statuses','pst'), 'LEFT')->on('pst.id', '=', 'ps.status_id')
            ->join(array('billing', 'b'), 'LEFT')->on('ps.billing_account_id','=','b.id')
            ->join(array('case_assignments', 'ca'),'LEFT')->on('ca.case_id','=','ps.case_id')
            ->join(array('users','u2'), 'LEFT')->on('u2.id', '=', 'ca.user_id')
            ->join(array('shared_cases','sc'), 'LEFT')->on('sc.case_id','=','c.id');;

        $query->where('ps.date_due', '=', date('Y-m-d'));
        $query->where('cs.is_client','=', 1);
        $query->where('cs.accounting_status_id','=', 200);

        switch(\Model_Account::getType()){
            case 'Account':
                break;
            case 'Company':
                $query->where_open();
                $query->where('c.company_id','=', \Model_System_User::getSessionMeta('company_id'));
                $query->or_where('sc.company_id','=', \Model_System_User::getSessionMeta('company_id'));
                $query->or_where('c.source_id','=', \Model_System_User::getSessionMeta('company_id'));
                $query->where_close();
                break;
            case 'Region':
                //$query->where('rg.region_id','=', Model_System_User::getSessionMeta('region_id'));
                $query->where('ca.user_id','=', \Model_System_User::getSessionMeta('id'));
                break;
            case 'User':
                $query->where('ca.user_id','=', \Model_System_User::getSessionMeta('id'));
                break;
        }

        $query->order_by('client_name', 'ASC');

        $result = $query->execute();
        $row = current($result->as_array());
        return $row['items'];

    }


    static function update_($id, $data, $reset_numbers=null){

        $result = \DB::update('payment_schedules')->set($data)->where('id', '=', $id)->execute();

        if(isset($data['case_id']) && $reset_numbers){
            self::renumberPayments($data['case_id']);
            self::renumberPaidPayments($data['case_id']);
        }

        return $result;
    }

    static function resetPaymentPlan($case_id){

        $result = \DB::delete('payment_schedules')
            ->where('case_id', '=', $case_id)
            ->and_where('status_id', '=', 2) // PROCESSING, PAID, NSF, ERROR, DECLINE
            ->execute();

    }

    static function deleteByIds($ids){

        $result = \DB::delete('payment_schedules')
            ->where('id', 'IN', $ids)->execute();
        return $result;
    }

    static function renumberPayments($case_id){

        $result = \DB::query("SET @rownumber = 0; update payment_schedules ps set pmt_num = (@rownumber:=@rownumber+1) where ps.case_id = ".$case_id." order by date_due asc")->execute();
        return $result;

    }

    static function renumberPaidPayments($case_id){

        $result = \DB::query("SET @rownumber = 0; update payment_schedules ps set paid_pmt_num = (@rownumber:=@rownumber+1) where ps.case_id = ".$case_id." and ps.status_id=3 order by date_due asc")->execute();
        return $result;

    }

    static function getAllSchedulesSum($case_id, $all_scheduled = false){
        $query = AccountingPaymentSchedule::select('id', 'amount')->where('case_id', $case_id);
        $result = $query->get();
        $payments = array();
        foreach($result->toArray() as $p){
            $payments[] = $p['amount'];
        }
        return array_sum($payments);
    }


    static function getTotalPendingPayments($case_id){

        $query = \DB::select('id', 'amount')
            ->from('payment_schedules')
            ->where('status_id','=', 2)
            ->where('case_id', '=', $case_id);

        $result = $query->execute();

        $payments = array();
        foreach($result->as_array() as $p){
            $payments[] = $p['amount'];
        }

        return array_sum($payments);

    }


    static function process($case_id, $data){

        $payment = array(
            'status' => $data['status'],
            'amount_received' => $data['amount_received'],
            'received_by' => $_SESSION['user']['id'],
            'date_received' => date('Y-m-d H:i:s'),
            'received_note' => $data['received_note'],
            'updated' => date('Y-m-d H:i:s'),
            'updated_by' => $_SESSION['user']['id']
        );

        if(!isset($data['id']) || empty($data['id'])){

            $add = array(
                'case_id' => $case_id,
                'amount' => $data['amount_received'],
                'date_due' => date('Y-m-d'),
                'created' => date('Y-m-d H:i:s'),
                'created_by' => $_SESSION['user']['id']
            );

            $new = array_merge($payment, $add);

            \DB::insert('payment_schedules')->set($new)->execute();

        }else{
            \DB::update('payment_schedules')->set($payment)->where('id', '=', $data['id'])->where('case_id', '=', $case_id)->execute();
        }

    }


    static function add($data, $reset_numbers=null){

        $result = \DB::insert('payment_schedules')->set($data)->execute();

        if(isset($data['case_id']) && $reset_numbers){
            self::renumberPayments($data['case_id']);
            self::renumberPaidPayments($data['case_id']);
        }


        return $result;

    }

    static function generatePaymentPlan($data){

        $minimum_payment_amount = 5;

        /* Payments Received from Client */
        $payments_made = \Accounting\Model_Payments::getPaidSumByCaseId($data['case_id']);
        /* Payments Scheduled for Client */
        $payments_scheduled = self::getAllSchedulesSum($data['case_id'], true);

        switch ($data['plan']){
            case 'balance':
                $total_payments_due = \Model_Invoice::getTotalByCaseID($data['case_id']) - ($payments_made + $payments_scheduled);
                $payments_amount = $total_payments_due / $data['balance_payments'];
                $data['number_payments'] = $data['balance_payments'];
                break;
            case 'custom':
                $payments_amount = $data['payment_amount'];
                $data['number_payments'] = $data['custom_payments'];
                break;
        }

        /*
         * $total_payments_due = 20;
        $payments_amount = 20;
        $data['number_payments']= 4;
         *  if($data['generate_by'] == 'number'){
                $payments_amount = $total_payments_due / $data['number_payments'];
            }else{
                $payments_amount = $data['payment_amount'];
                $data['number_payments'] = ceil($total_payments_due / $payments_amount);
            }
         */

        // Balance Used Up
        // if($total_payments_due == 0){
        //throw new Exception('Payments are already scheduled for the entire balance due');
        //  }

        /* Set up vars for loop */
        $payments = array();
        $last_payment = false;
        $sch_payments = array();
        $last_pending_payment_date = ''; //self::getLastPendingDate($data['case_id']);

        /* Start and End date, otherwise: Last Pending Payment Date */
        $start_date = (empty($last_pending_payment_date)?$data['start_date']:$last_pending_payment_date);
        $date_due = date('Y-m-d', strtotime($start_date));

        /* Split from Total amount, or use Total Amount */
        for($i=1;$i<=$data['number_payments'];$i++){

            /* Getting increment of Date*/
            if($i>1 || !empty($last_pending_payment_date)){
                $date_due = date('Y-m-d', strtotime('+'.$data['payment_frequency'], strtotime($date_due)));
            }

            if(isset($data['type'])){
                $type_id = $data['type'];
            }else{
                $type_id = 0;
            }


            $payment = array(
                'case_id' => $data['case_id'],
                'amount' => $payments_amount,
                'date_due' => $date_due,
                'billing_account_id' => (isset($data['billing_account_id'])?$data['billing_account_id']:NULL),
                'created' => date('Y-m-d H:i:s'),
                'created_by' => $_SESSION['user']['id'],
                'updated' => date('Y-m-d H:i:s'),
                'updated_by' => $_SESSION['user']['id'],
                'type_id' => $type_id
            );

            if($data['plan'] == 'custom'){
                if($i==$data['number_payments']){
                    $last_payment = true;
                }
                //echo 'custom';

            }else{
                $next_period_due = $total_payments_due-(array_sum($sch_payments)+$payments_amount);
                if($next_period_due < $minimum_payment_amount){
                    $last_payment = true;
                    $payment['amount'] = $total_payments_due-array_sum($sch_payments);
                }

                //echo 'balance';
            }

            /* Looping error? Halt on creation of 1000 schedules or more */
            if($i >= 1000){
                exit;
            }


            \DB::insert('payment_schedules')->set($payment)->execute();

            //self::renumberPayments();

            if($last_payment){
                return;
            }

            $sch_payments[] = $payments_amount;
        }

    }



    static function getLastPendingDate($case_id){
        $result = AccountingPaymentSchedule::select('date_due')
            ->where('case_id', $case_id)
            ->where('status_id', 2) // pending
            ->orderBy('date_due','desc')
            ->get();
        $row = current($result->toArray());
        return $row['date_due'];
    }

    static function buildResult($db_result, $fields = null)
    {
        $ids = array();
        foreach($db_result->as_array() as $row){
            $items[$row['case_id']] = $row;
            $ids[] = (int)$row['case_id'];
        }

        if(empty($ids)){
            return array();
        }

        $mdb = \Mongo_Db::instance();
        $mdb->where_in('_id', $ids);

        if(!empty($fields)){
            $mdb->select($fields);
        }

        $mresult = $mdb->get('cases');

        //$fields = Model_System_Form_Fields::findAll(1);

        $results = array();
        foreach($mresult as $row){
            foreach($fields as $field){
                $items[$row['_id']][$field] = '';
                if(isset($row[$field])){
                    $items[$row['_id']][$field] = $row[$field];
                }
            }
        }

        return $items;
    }

    static function getPaymentPlanSummary($case_id){

        $payments_res = \DB::select('amount')->from('payment_schedules')->where('case_id', '=', $case_id)->order_by('date_due')->execute();

        $payments = $payments_res->as_array();

        $result = \DB::select(\DB::expr('MIN(date_due) as pay_start_date'), \DB::expr('MAX(date_due) as pay_end_date'), \DB::expr('COUNT(id) as pay_payments'))
            ->from('payment_schedules')
            ->where('case_id', '=', $case_id)
            ->group_by('case_id')
            ->execute();

        $plan = current($result->as_array());

        $i = 1;
        foreach($payments as $p){
            $plan['pay_payment_amount'.$i] = $p['amount'];
            $i++;
        }

        $plan['pay_total_payments'] = count($payments);

        if($plan['pay_payments'] == 0){
            $plan['pay_schedule'] = null;
            return $plan;
        }

        $start_date = new \DateTime($plan['pay_start_date']);
        $end_date = new \DateTime($plan['pay_end_date']);

        if(!empty($plan['pay_start_date'])){
            $plan['pay_start_date'] = date('m/d/Y', strtotime($plan['pay_start_date']));
        }

        if(!empty($plan['pay_end_date'])){
            $plan['pay_end_date'] = date('m/d/Y', strtotime($plan['pay_end_date']));
        }

        $month_test = clone $start_date;
        $month_test->modify('+'.($plan['pay_payments']-1).' Months');

        $seconds = $end_date->getTimestamp() - $start_date->getTimestamp();
        $weeks = round($seconds / 60 / 60 / 24 / 7)+1;

        if($month_test->format('Y-m-d') == $end_date->format('Y-m-d')){
            $plan['pay_schedule'] = 'Monthly';
        }elseif($plan['pay_payments'] == $weeks){
            $plan['pay_schedule'] = 'Weekly';
        }else{
            $plan['pay_schedule'] = 'Bimonthly';
        }

        return $plan;
    }

    static function delete_($id){
        \DB::delete('payment_schedules')->where('id', '=', $id)->execute();
    }

    static function validate($factory){

        $val = \Validation::forge($factory);

        $val->add('date_due', 'Date Due')
            ->add_rule('required');

        $val->add('amount', 'Amount')
            ->add_rule('required');


        return $val;
    }




    static function findByCaseIDForExport($case_id, $status_id = null){

        $query = \DB::select('ps.id','ps.case_id','ps.amount','ps.date_due',
            'b.type','b.card_type','b.title','b.bank_name','b.routing_number','b.account_number','b.billing_street','b.billing_city','b.billing_state','b.billing_zip',
            array('pst.name', 'payment_status'),
            array('ptype.name', 'payment_type'),
            array('acp.profile_id','anet_profile_id'),
            array('app.payment_profile_id','anet_payment_id')
        )
            ->from(array('payment_schedules','ps'))
            ->join(array('cases','c'), 'LEFT')->on('c.id', '=', 'ps.case_id')
            ->join(array('case_contact','cc'), 'LEFT')->on('cc.case_id', '=', 'ps.case_id')
            ->join(array('case_statuses','cs'), 'LEFT')->on('cs.case_id', '=', 'ps.case_id')
            ->join(array('users','u'), 'LEFT')->on('u.id', '=', 'ps.created_by')
            ->join(array('users','co'), 'LEFT')->on('co.id', '=', 'ps.collector_id')
            ->join(array('payment_statuses','pst'), 'LEFT')->on('pst.id', '=', 'ps.status_id')
            ->join(array('payment_schedule_types','ptype'), 'LEFT')->on('ptype.id', '=', 'ps.type_id')
            ->join(array('billing', 'b'), 'LEFT')->on('ps.billing_account_id','=','b.id')
            ->join(array('anet_customer_profiles','acp'), 'LEFT')->on('acp.case_id', '=', 'ps.case_id')
            ->join(array('anet_payment_profiles','app'), 'LEFT')->on('app.billing_id', '=', 'b.id')
            ->where('ps.case_id', '=', $case_id)
            ->order_by('ps.date_due', 'ASC');

            if($status_id){
                $query->where('ps.status_id','=', $status_id);
            }

        return $query->execute()->as_array();

    }

    static function filterforPaymentReminder($filter) {

            $sort_field = 'created';
            $order = 'desc';
            $count_only = false;
            $limit = 10000;
            $offset = 0;
            $days = 6;
            $current_date = date("Y-m-d");

            $query = \DB::select(
                array('ps.amount','next_payment_amount'),
                array('ps.date_due','next_payment_date'),
                array('ps.id','next_payment_id'),
                array('b.name_on_card','next_payment_billing_noc'),
                array('b.name_on_account','next_payment_billing_noa'),
                array('b.title','next_payment_billing_title'),
                array('b.type','next_payment_billing_type'),
                array('b.routing_number','next_payment_billing_rounting_number'),
                array('b.account_number','next_payment_billing_account_number'),
                array(\DB::expr('CONCAT(cc.first_name, " ", cc.last_name)'), 'client_full_name'),
                array('cc.first_name','first_name'),
                array('cc.last_name','last_name'),
                array('cc.case_id', 'case_id'),
                array(\DB::expr('CONCAT(b.exp_month, "/", b.exp_year)'), 'next_payment_billing_exp')
            );


            $query->from(array('payment_schedules','ps'))
                ->join(array('payments','p'), 'LEFT')->on('p.schedule_id', '=', 'ps.id')
                ->join(array('cases','c'), 'LEFT')->on('c.id', '=', 'ps.case_id')
                ->join(array('case_contact','cc'), 'LEFT')->on('cc.case_id', '=', 'ps.case_id')
                ->join(array('case_statuses','cs'), 'LEFT')->on('cs.case_id', '=', 'ps.case_id')
                ->join(array('billing', 'b'), 'LEFT')->on('b.id','=','ps.billing_account_id')
                ->join(array('companies','com'), 'LEFT')->on('com.id', '=', 'c.company_id')
                ->join(array('accounting_statuses','accs'), 'LEFT')->on('accs.id', '=', 'cs.accounting_status_id')
                ->join(array('payment_schedule_gateways','psg'), 'LEFT')

                ->on('psg.account_type_id', '=', 'cs.account_type_id')
                ->on('psg.schedule_type_id', '=', 'ps.type_id')
                ->on('psg.billing_type_id', '=', 'b.type_id')
                ->on('psg.company_id', '=', 'c.company_id')

                ->join('gateways','LEFT')->on('gateways.id','=','psg.gateway_id');


            $query->where('date_due','=', \DB::expr('DATE(DATE_ADD('.$current_date.', INTERVAL '.$days.' DAY))'));

            if((isset($filter['status_id']) && !empty($filter['status_id'])) || (isset($filter['status_id']) && $filter['status_id'] === '0')){
                $query->where('ps.status_id', '=', $filter['status_id']);
            }


            if((isset($filter['accounting_status_id']) && !empty($filter['accounting_status_id'])) || (isset($filter['accounting_status_id']) && $filter['accounting_status_id'] === '0')){
                $query->where('cs.accounting_status_id', '=', $filter['accounting_status_id']);
            }

            if((isset($filter['account_type_id']) && !empty($filter['account_type_id'])) || (isset($filter['account_type_id']) && $filter['account_type_id'] === '0')){
                $query->where('cs.account_type_id', '=', $filter['account_type_id']);
            }

            if((isset($filter['has_gateway']) && !empty($filter['has_gateway']))){
                if($filter['has_gateway'] == 1) {
                    $query->where('psg.id', null, \DB::expr('IS NOT NULL'));
                }else{
                    $query->where('psg.id', '=', NULL);
                }
            }

            if((isset($filter['amount']) && !empty($filter['amount'])) && isset($filter['amount_operator']) && !empty($filter['amount_operator'])){
                $query->where('ps.amount', $filter['amount_operator'], $filter['amount']);
            }

            if((isset($filter['billing_type']) && !empty($filter['billing_type'])) && $filter['billing_type'] != '0'){
                $query->where('b.type', '=', $filter['billing_type']);
            }

            if(isset($filter['type_id']) && !empty($filter['type_id'])){
                $query->where('ps.type_id', '=', $filter['type_id']);
            }


            if(isset($filter['company_id']) && !empty($filter['company_id'])){
                if(is_array($filter['company_id'])){
                    $query->where('c.company_id', 'IN', $filter['company_id']);
                }else {
                    $query->where('c.company_id', '=', $filter['company_id']);
                }
            }

            if(isset($filter['auto_run']) && !empty($filter['auto_run'])){

                $query->join(array('services_companies','serv_com'), 'LEFT')->on('serv_com.company_id', '=', 'c.company_id');
                $query->where('serv_com.service_id', '=', 117);
            }

            $query->limit($limit)
                ->order_by($sort_field, $order)
                ->group_by('ps.id');

            return $query->execute()->as_array();

    }

}
