<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cases extends Model
{
    use HasFactory;
    protected $table = "cases";
    static protected $last_result_count;
        /**
         * @param $id
         * @return mixed
         */
        static function find_($id){

            $result = \DB::select(
                'c.id','c.company_id','c.is_duplicate','c.last_action','c.action_count','c.created','c.updated',
                'cc.first_name','cc.last_name','cc.middle_name','cc.email','cc.primary_phone','cc.secondary_phone','cc.mobile_phone','cc.ssn','cc.dob','cc.fax','cc.address','cc.address_2','cc.city','cc.state','cc.zip','cc.country','cc.timezone','cc.title','cc.dpp_contact_id','cc.direct_mail_id','cc.credit_score','cc.vendor_id',
                'cs.is_company','cs.status_id','cs.campaign_id','cs.is_client','cs.docs_status','cs.is_deleted','cs.accounting_status_id','cs.doc_submission','cs.status_updated','cs.dialer_updated','cs.accounting_updated','cs.financed','cs.renewal_date',array('actype.name','accounting_type'),'cs.submission_ready','cs.account_type_id','cs.poi_id','cs.shark_tank','cs.shark_tank_date', array('c.id','case_id'),

                array('s.name', 'status'),
                array('s.description', 'status_description'),
                array('dstatus.name', 'dialer_status'),
                array('m.name', 'milestone'),
                array('camp.name','campaign_name'),
                array('d.clean_name', 'assignment_name'),
                array('cau.id', 'assignment_user_id'),
                array('cau.email', 'assignment_user_email'),
                array(DB::expr('CONCAT(cau.first_name, " ", cau.last_name)'), 'assignment_user'),
                array(DB::expr('COALESCE(TIMESTAMPDIFF(DAY,cs.status_updated,"'.date('Y-m-d H:i:s').'"),0)'), 'days_status'),
                array('f.status', 'financing_status'),
                array('f.application_id', 'financing_application_id'),
                array('f.track', 'financing_track'),
                array('f.track', 'ea_track'),
                array('processor_co.name', 'processor_company'),
                array('cs.processor_id','processor_id'),
                array('acstatus.name', 'accounting_status_name'),
                array('actype.name','account_type_name'),
                /* COMPANY */
                array('comp.name','company'),
                'comp.case_email_domain'
            )
                ->from(array('cases','c'))
                /* 1:1 */
                ->join(array('case_contact','cc'), 'LEFT')->on('cc.case_id', '=', 'c.id')
                ->join(array('case_statuses','cs'), 'LEFT')->on('cs.case_id', '=', 'c.id')
                ->join(array('statuses','s'), 'LEFT')->on('s.id', '=', 'cs.status_id')
                ->join(array('statuses', 'dstatus'), 'LEFT')->on('cs.dialer_id','=','dstatus.id')
                ->join(array('statuses', 'acstatus'), 'LEFT')->on('cs.accounting_status_id','=','acstatus.id')
                ->join(array('milestones','m'), 'LEFT')->on('m.id', '=', 's.milestone_id')
                ->join(array('companies','comp'), 'LEFT')->on('comp.id', '=', 'c.company_id')
                ->join(array('campaigns', 'camp'), 'LEFT')->on('cs.campaign_id','=','camp.id')
                ->join(array('accounting_types', 'actype'), 'LEFT')->on('actype.id', '=', 'cs.account_type_id')
                /* Many:Many */
                ->join(array('case_assignments', 'ca'),'LEFT')->on('ca.case_id','=','c.id')
                ->join(array('departments', 'd'),'LEFT')->on('d.id','=','ca.department_id')
                ->join(array('users', 'cau'),'LEFT')->on('cau.id','=','ca.user_id')
                ->join(array('financing', 'f'), 'LEFT')->on('f.case_id', '=', 'c.id')
                ->join(array('companies','processor_co'), 'LEFT')->on('processor_co.id', '=', 'cs.processor_id')

                ->where('c.id', '=', $id);

            $result = \Model_System_Access::queryAccess($result);

            return current(self::buildResult($result));

        }

        /**
         * @param $ids
         * @return array
         */
        static function findByIDs($ids, $sort_field=false, $order=false, $fields=null, $additional=true){
            if(!$sort_field){
                $sort_field = 'c.created';
            }
            if(!$order){
                $order = 'DESC';
            }
            if(!is_array($ids)){
                $ids = array($ids);
            }
            $user = Account::getUserId();
            $result = Cases::selectRaw(
                'c.id, c.company_id,c.is_duplicate,c.last_action,c.action_count,c.created,c.updated,
                cc.first_name,cc.last_name,cc.middle_name,cc.email,cc.primary_phone,cc.secondary_phone,cc.mobile_phone,cc.ssn,cc.dob,cc.fax,cc.address,cc.address_2,cc.city,cc.state,cc.zip,cc.country,cc.timezone,cc.title,cc.dpp_contact_id,cc.direct_mail_id,cc.credit_score,cc.vendor_id,
                cs.is_company,cs.status_id,cs.campaign_id,cs.is_client,cs.docs_status,cs.is_deleted,cs.accounting_status_id,cs.doc_submission,cs.status_updated,cs.dialer_updated,cs.accounting_updated,cs.financed, actype.name as accounting_type,cs.renewal_date,cs.shark_tank,cs.shark_tank_date,
                s.name as status,
                dialer_s.name as dialer_status,
                m.name as milestone,
                camp.name as campaign,
                com.name as company,
                es.updated as signed_date,
                cs.doc_submission as submission_date,
                COALESCE(TIMESTAMPDIFF(DAY,cs.status_updated,"'.date('Y-m-d H:i:s').'"),0) as days_status,
                inv.total as invoice_amount,
                f.status as financing_status,
                f.score as financing_score,
                f.application_id as financing_application_id,
                fpayments.payments_status as financing_payment_status,
                f.track as ea_track,
                p.paid as paid,
                c.id as case_id,
                acstatus.name as accounting_status_name,
                cv.timestamp as viewed')
                ->from('cases as c')
                ->leftJoin('case_contacts as cc', 'cc.case_id', 'c.id')
                ->leftJoin('case_statuses as cs', 'cs.case_id', 'c.id')
                ->leftJoin('statuses as s', 's.id', 'cs.status_id')
                ->leftJoin('milestones as m', 'm.id', 's.milestone_id')
                ->leftJoin('companies as com','com.id', 'c.company_id')
                ->leftJoin('campaigns as camp', 'cs.campaign_id', 'camp.id')
                ->leftJoin('esign_docs as es', 'es.case_id', 'c.id')
                ->leftJoin('case_todos as cto', 'cto.case_id', 'c.id')
                ->leftJoin('accounting_statuses as acstatus', 'acstatus.id', 'cs.accounting_status_id')
                ->leftJoin('invoices as inv', 'inv.case_id', 'c.id')
                ->leftJoin('accounting_types as actype', 'actype.id', 'cs.account_type_id')
                ->leftJoin('case_views as cv', function($q) use ($user) { 
                    $q->on('cv.case_id','c.id')->on('cv.user_id',null, $user);
                })
                ->leftJoin('case_labels as clb', 'clb.case_id', 'c.id')
                ->leftJoin('financing as f', 'f.case_id', 'c.id')
                ->leftJoin('(select SUM(p.amount) as paid, p.case_id
                        from payments p
                        where p.status_id = 3
                        group by p.case_id) as p', 'p.case_id', 'c.id')

                ->leftJoin('financing_payments as fpayments', 'fpayments.case_id','c.id')
                ->leftJoin('statuses as dialer_s', 'dialer_s.id', 'cs.dialer_id');
            dd($result->get()->toArray());
            $sfl = 0;
            // TODO fix static assignments, dynamic departments based on company or network
            $user_assignments = array(
                array('clean_name' => 'sales_rep', 'id' => 8),
                array('clean_name' => 'processor', 'id' => 3),
                array('clean_name' => 'ast_processor', 'id' => 12),
                array('clean_name' => 'collector', 'id' => 1),
                array('clean_name' => 'renewal_account_manager', 'id' => 31)

            );
            foreach ($user_assignments as $ua) {
                $alias = 'cass_'  . $sfl;
                $ualias = 'user_' . $sfl;
                $dalias = 'dept_' . $sfl;
                $result->select_array(array(
                    array($ualias.'.id', 'dept_'.$ua['clean_name'].'_id'),
                    array($ualias.'.email', 'dept_'.$ua['clean_name'].'_email'),
                    array($ualias.'.first_name', 'dept_'.$ua['clean_name'].'_first_name'),
                    array($ualias.'.last_name', 'dept_'.$ua['clean_name'].'_last_name'),
                    array(DB::expr('CONCAT('.$ualias.'.first_name'.', " ", '.$ualias.'.last_name'.')'), 'dept_'.$ua['clean_name'].'_full_name'),
                    array(DB::expr('CONCAT('.$ualias.'.first_name'.', " ", '.$ualias.'.last_name'.')'), 'dept_'.$ua['clean_name'])
                ));
                $result->join(array('case_assignments', $alias), 'LEFT')
                    ->on($alias . '.case_id', '=', 'c.id')
                    ->on($alias . '.department_id', '=', "'" . $ua['id'] . "'");
                $result->join(array('departments', $dalias),'LEFT')
                    ->on($dalias . '.id','=', $alias.'.user_id');
                $result->join(array('users', $ualias),'LEFT')
                    ->on($ualias . '.id','=', $alias.'.user_id');
                $sfl++;
            }
            if(isset($fields) && is_array($fields)) {
                if(in_array('last_payment_amount', $fields) || in_array('last_payment_date', $fields) || in_array('last_payment_date_due', $fields) ){
                    $result->select_array(array(
                        array('last_payment.amount', 'last_payment_amount'),
                        array('last_payment.created', 'last_payment_date'),
                        array('last_payment.date_due', 'last_payment_date_due'),
                        array('last_payment_statuses.name','last_payment_status')
                    ));
                    $result->join(array(\DB::expr('(select MAX(payments.id) payment_id, payments.case_id
                        from payments
                        group by payments.case_id)'),'last_payment_max'), 'LEFT')->on('last_payment_max.case_id','=','c.id');
                    $result->join(array('payments','last_payment'))->on('last_payment.id','=','last_payment_max.payment_id');
                    $result->join(array('payment_statuses','last_payment_statuses'))->on('last_payment_statuses.id','=','last_payment.status_id');
                }
                if (in_array('first_schedule_amount', $fields) || in_array('first_schedule_date', $fields) || in_array('first_schedule_status', $fields)) {
                    $result->select_array(array(
                        array('fpschedules.amount', 'first_schedule_amount'),
                        array('fpschedules.date_due', 'first_schedule_date'),
                        array('fp_ps.name', 'first_schedule_status')
                    ));
                    $result->join(array('payment_schedules', 'fpschedules'), 'left')->on('fpschedules.case_id', '=', 'c.id')->on('fpschedules.pmt_num', '=', \DB::expr('1'));
                    $result->join(array('payment_statuses', 'fp_ps'), 'left')->on('fp_ps.id', '=', 'fpschedules.status_id');
                }
                if (in_array('second_schedule_amount', $fields) || in_array('second_schedule_date', $fields) || in_array('second_schedule_status', $fields)) {
                    $result->select_array(array(
                        array('fpschedules2.amount', 'second_schedule_amount'),
                        array('fpschedules2.date_due', 'second_schedule_date'),
                        array('fp_ps2.name', 'second_schedule_status')
                    ));
                    $result->join(array('payment_schedules', 'fpschedules2'), 'left')->on('fpschedules2.case_id', '=', 'c.id')->on('fpschedules2.pmt_num', '=', \DB::expr('2'));
                    $result->join(array('payment_statuses', 'fp_ps2'), 'left')->on('fp_ps2.id', '=', 'fpschedules2.status_id');
                }
                if (in_array('third_schedule_amount', $fields) || in_array('third_schedule_date', $fields) || in_array('third_schedule_status', $fields)) {
                    $result->select_array(array(
                        array('fpschedules3.amount', 'third_schedule_amount'),
                        array('fpschedules3.date_due', 'third_schedule_date'),
                        array('fp_ps3.name', 'third_schedule_status')
                    ));
                    $result->join(array('payment_schedules', 'fpschedules3'), 'left')->on('fpschedules3.case_id', '=', 'c.id')->on('fpschedules3.pmt_num', '=', \DB::expr('3'));
                    $result->join(array('payment_statuses', 'fp_ps3'), 'left')->on('fp_ps3.id', '=', 'fpschedules3.status_id');
                }
                if (in_array('first_paid_amount', $fields) || in_array('first_paid_schedule_date', $fields) || in_array('first_paid_cleared_date', $fields)) {
                    $result->select_array(array(
                        array('fpschedulespaid.amount', 'first_paid_amount'),
                        array('fpschedulespaid.date_due', 'first_paid_schedule_date'),
                        array('fpschedulespaid.cleared_date', 'first_paid_cleared_date')
                    ));
                    $result->join(array('payment_schedules', 'fpschedulespaid'), 'left')->on('fpschedulespaid.case_id', '=', 'c.id')->on('fpschedulespaid.paid_pmt_num', '=', \DB::expr('1'));
                }
                if (in_array('second_paid_amount', $fields) || in_array('second_paid_schedule_date', $fields) || in_array('second_paid_cleared_date', $fields)) {
                    $result->select_array(array(
                        array('fpschedulespaid2.amount', 'second_paid_amount'),
                        array('fpschedulespaid2.date_due', 'second_paid_schedule_date'),
                        array('fpschedulespaid2.cleared_date', 'second_paid_cleared_date')
                    ));
                    $result->join(array('payment_schedules', 'fpschedulespaid2'), 'left')->on('fpschedulespaid2.case_id', '=', 'c.id')->on('fpschedulespaid2.paid_pmt_num', '=', \DB::expr('2'));
                }
                if (in_array('third_paid_amount', $fields) || in_array('third_paid_schedule_date', $fields) || in_array('third_paid_cleared_date', $fields)) {
                    $result->select_array(array(
                        array('fpschedulespaid3.amount', 'third_paid_amount'),
                        array('fpschedulespaid3.date_due', 'third_paid_schedule_date'),
                        array('fpschedulespaid3.cleared_date', 'third_paid_cleared_date')
                    ));
                    $result->join(array('payment_schedules', 'fpschedulespaid3'), 'left')->on('fpschedulespaid3.case_id', '=', 'c.id')->on('fpschedulespaid3.paid_pmt_num', '=', \DB::expr('3'));
                }
                if (in_array('invoices_total', $fields)) {
                    $result->select_array(array(array('invoices.invoices_total', 'invoices_total')));
                    $result->join(array(\DB::expr('(select SUM(i.total) as invoices_total, i.case_id
                        from invoices i
                        group by i.case_id)'),'invoices'), 'LEFT')->on('invoices.case_id','=','c.id');
                }
            }
            $result->where('c.id', 'in', $ids)->order_by($sort_field, $order);
            //Model_Log::append('contact_query_findByIds_PRE:', $result->compile());
            return self::buildResult($result, $fields, $additional);
        }


        static function findByCompanyID($company_id){

            $user = Model_Account::getUserId();

            $result = \DB::select(
                'c.id','c.company_id','c.is_duplicate','c.last_action','c.action_count','c.created','c.updated',
                'cc.first_name','cc.last_name','cc.middle_name','cc.email','cc.primary_phone','cc.secondary_phone','cc.mobile_phone','cc.ssn','cc.dob','cc.fax','cc.address','cc.address_2','cc.city','cc.state','cc.zip','cc.country','cc.timezone','cc.title','cc.dpp_contact_id','cc.direct_mail_id','cc.credit_score',
                'cs.is_company','cs.status_id','cs.campaign_id','cs.is_client','cs.docs_status','cs.is_deleted','cs.accounting_status_id','cs.doc_submission','cs.status_updated','cs.dialer_updated','cs.accounting_updated','cs.financed',array('actype.name','accounting_type'),'cs.renewal_date','cs.shark_tank','cs.shark_tank_date',

                array('s.name', 'status'),
                array('dialer_s.name', 'dialer_status'),
                array('m.name', 'milestone'),
                array('camp.name', 'campaign'),
                array('com.name', 'company'),
                array('d.clean_name', 'assignment_name'),
                array('cau.id', 'assignment_user_id'),
                array('cau.email', 'assignment_user_email'),
                array(DB::expr('CONCAT(cau.first_name, " ", cau.last_name)'), 'assignment_user'),
                array(DB::expr('CONCAT(cau.first_name, " ", cau.last_name)'), 'user'),
                array('d.name', 'department'),
                array('es.updated', 'signed_date'),
                array('cs.doc_submission', 'submission_date'),
                array(DB::expr('COALESCE(TIMESTAMPDIFF(DAY,cs.status_updated,"'.date('Y-m-d H:i:s').'"),0)'), 'days_status'),

                array(DB::expr('lb.name'), 'label_name'),
                array(DB::expr('f.status'), 'financing_status'),
                array(DB::expr('f.track'), 'ea_track'),
                array('p.paid', 'paid'),
                array('c.id', 'case_id'),
                array('acstatus.name', 'accounting_status_name'),
                array(DB::expr('GREATEST(45-(TIMESTAMPDIFF(DAY,f.created,"'.date('Y-m-d H:i:s').'")),0)'), 'financing_expiration')

            )
                ->from(array('cases','c'))
                ->join(array('case_contact','cc'), 'LEFT')->on('cc.case_id', '=', 'c.id')
                ->join(array('case_statuses','cs'), 'LEFT')->on('cs.case_id', '=', 'c.id')
                ->join(array('statuses','s'), 'LEFT')->on('s.id', '=', 'cs.status_id')
                ->join(array('milestones','m'), 'LEFT')->on('m.id', '=', 's.milestone_id')
                ->join(array('companies','com'), 'LEFT')->on('com.id', '=', 'c.company_id')
                ->join(array('campaigns', 'camp'), 'LEFT')->on('cs.campaign_id','=','camp.id')
                ->join(array('esign_docs','es'), 'LEFT')->on('es.case_id', '=', 'c.id')
                ->join(array('case_todos', 'cto'), 'LEFT')->on('cto.case_id', '=', 'c.id')
                ->join(array('statuses','acstatus'), 'LEFT')->on('acstatus.id', '=', 'cs.accounting_status_id')
                ->join(array('accounting_types', 'actype'), 'LEFT')->on('actype.id', '=', 'cs.account_type_id')
                ->join(array('case_assignments', 'ca'),'LEFT')->on('ca.case_id','=','c.id')
                ->join(array('departments', 'd'),'LEFT')->on('d.id','=','ca.department_id')
                ->join(array('users', 'cau'),'LEFT')->on('cau.id','=','ca.user_id')
                ->join(array('case_labels', 'clb'), 'LEFT')->on('clb.case_id', '=', 'c.id')
                ->join(array('labels', 'lb'), 'LEFT')->on('lb.id', '=', 'clb.label_id')
                ->join(array('financing', 'f'), 'LEFT')->on('f.case_id', '=', 'c.id')
                ->join(array(\DB::expr('(select SUM(p.amount) as paid, p.case_id
                        from payments p
                        where p.status_id = 3
                        group by p.case_id)'),'p'), 'LEFT')->on('p.case_id','=','c.id')
                ->join(array('statuses','dialer_s'), 'LEFT')->on('dialer_s.id', '=', 'cs.dialer_id')
                ->where('c.company_id', '=', $company_id);

            return self::buildResult($result, null, false);

        }


        /*

         foreach($ids as $k => $v){
             $ids[$k] = (int)$v;
         }

         $mdb = Mongo_Db::instance();
         $mdb->select(array('first_name', 'last_name', 'status', 'campaign', 'primary_phone'))->where_in('_id', $ids);
         $mresult = $mdb->get('cases');

         $cases = array();
         foreach($mresult as $row){
             $cases[$row['_id']] = $row;
         }

         return $cases;*/



        /**
         * @param $fields
         * @return mixed
         */
        static function findByFields($fields){

            if(isset($fields['id'])){
                $fields['c.id'] = (int)$fields['id'];
                unset($fields['id']);
            }

            $query = \DB::select('c.id','c.company_id','c.is_duplicate','c.last_action','c.action_count','c.created','c.updated',
                'cc.first_name','cc.last_name','cc.middle_name','cc.email','cc.primary_phone','cc.secondary_phone','cc.mobile_phone','cc.ssn','cc.dob','cc.fax','cc.address','cc.address_2','cc.city','cc.state','cc.zip','cc.country','cc.timezone','cc.title','cc.dpp_contact_id',
                'cs.is_company','cs.status_id','cs.campaign_id','cs.is_client','cs.docs_status','cs.is_deleted','cs.accounting_status_id',array('actype.name','accounting_type'),

                array('s.name', 'status'),
                array('m.name', 'milestone'),
                array('camp.name','campaign'),
                array('comp.name','company'),

                array('d.clean_name', 'assignment_name'),
                array('cau.id', 'assignment_user_id'),
                array('cau.email', 'assignment_user_email'),
                array(DB::expr('CONCAT(cau.first_name, " ", cau.last_name)'), 'assignment_user'),
                array(DB::expr('COALESCE(TIMESTAMPDIFF(DAY,cs.status_updated,"'.date('Y-m-d H:i:s').'"),0)'), 'days_status')
            )
                ->from(array('cases','c'))
                ->join(array('case_contact','cc'), 'LEFT')->on('cc.case_id', '=', 'c.id')
                ->join(array('case_statuses','cs'), 'LEFT')->on('cs.case_id', '=', 'c.id')

                ->join(array('statuses','s'), 'LEFT')->on('s.id', '=', 'cs.status_id')
                ->join(array('milestones','m'), 'LEFT')->on('m.id', '=', 's.milestone_id')
                ->join(array('companies','comp'), 'LEFT')->on('comp.id', '=', 'c.company_id')
                ->join(array('campaigns', 'camp'), 'LEFT')->on('cs.campaign_id','=','camp.id')
                ->join(array('accounting_types', 'actype'), 'LEFT')->on('actype.id', '=', 'cs.account_type_id')

                ->join(array('case_assignments', 'ca'),'LEFT')->on('ca.case_id','=','c.id')
                ->join(array('departments', 'd'),'LEFT')->on('d.id','=','ca.department_id')
                ->join(array('users', 'cau'),'LEFT')->on('cau.id','=','ca.user_id');


            foreach($fields as $k => $v){
                $query->where(array($k => $v));
            }

            $query->limit(1);

            return self::buildResult($query);

        }


        static function findStatuses($id){


            $query = \DB::select('cs.is_company','cs.status_id','cs.campaign_id','cs.is_client','cs.docs_status','cs.is_deleted','cs.accounting_status_id',
                array('s.name', 'status'),
                array('m.name', 'milestone'),
                array('camp.name','campaign')

            )
                ->from(array('case_statuses','cs'))
                ->join(array('statuses','s'), 'LEFT')->on('s.id', '=', 'cs.status_id')
                ->join(array('milestones','m'), 'LEFT')->on('m.id', '=', 's.milestone_id')
                ->join(array('campaigns', 'camp'), 'LEFT')->on('cs.campaign_id','=','camp.id');

            $query->where('cs.case_id', '=', $id);

            return current($query->execute()->as_array());

        }

        static function findAccountTypeId($case_id){

            $query = \DB::select('account_type_id')
                ->from('case_statuses')
                ->where('case_id', '=', $case_id);
            $result = current($query->execute()->as_array());
            return $result['account_type_id'];
        }

        /**
         * @param $phone
         * @return array
         */

        static function findByPhone($phone, $company_id = null){

            $query = \DB::select('c.id','c.company_id','c.is_duplicate','c.last_action','c.action_count','c.created','c.updated',
                'cc.first_name','cc.last_name','cc.middle_name','cc.email','cc.primary_phone','cc.secondary_phone','cc.mobile_phone','cc.ssn','cc.dob','cc.fax','cc.address','cc.address_2','cc.city','cc.state','cc.zip','cc.country','cc.timezone','cc.title','cc.dpp_contact_id',
                'cs.is_company','cs.status_id','cs.campaign_id','cs.is_client','cs.docs_status','cs.is_deleted','cs.accounting_status_id','cs.dialer_id','cs.shark_tank','cs.shark_tank_date',
                array('actype.name','accounting_type'),
                array('s.name', 'status'),
                array('m.name', 'milestone'),
                array('camp.name','campaign'),
                array('comp.name','company'),
                array('d.clean_name', 'assignment_name'),
                array('cau.id', 'assignment_user_id'),
                array('cau.email', 'assignment_user_email'),

                array(DB::expr('CONCAT(cau.first_name, " ", cau.last_name)'), 'assignment_user')
            )
                ->from(array('cases','c'))
                ->join(array('case_contact','cc'), 'LEFT')->on('cc.case_id', '=', 'c.id')
                ->join(array('case_statuses','cs'), 'LEFT')->on('cs.case_id', '=', 'c.id')

                ->join(array('statuses','s'), 'LEFT')->on('s.id', '=', 'cs.status_id')
                ->join(array('milestones','m'), 'LEFT')->on('m.id', '=', 's.milestone_id')
                ->join(array('companies','comp'), 'LEFT')->on('comp.id', '=', 'c.company_id')
                ->join(array('campaigns', 'camp'), 'LEFT')->on('cs.campaign_id','=','camp.id')
                ->join(array('accounting_types', 'actype'), 'LEFT')->on('actype.id', '=', 'cs.account_type_id')

                ->join(array('case_assignments', 'ca'),'LEFT')->on('ca.case_id','=','c.id')
                ->join(array('departments', 'd'),'LEFT')->on('d.id','=','ca.department_id')
                ->join(array('users', 'cau'),'LEFT')->on('cau.id','=','ca.user_id');

            $query->where('cc.primary_phone','=', $phone);

            if($company_id){
                $query->where('c.company_id','=', $company_id);
            }

            return current(self::buildResult($query));

        }


        static function findByAnyPhone($phone, $company_id = null){

            $query = \DB::select('c.id','c.company_id','c.is_duplicate','c.last_action','c.action_count','c.created','c.updated',
                'cc.first_name','cc.last_name','cc.middle_name','cc.email','cc.primary_phone','cc.secondary_phone','cc.mobile_phone','cc.ssn','cc.dob','cc.fax','cc.address','cc.address_2','cc.city','cc.state','cc.zip','cc.country','cc.timezone','cc.title','cc.dpp_contact_id',
                'cs.is_company','cs.status_id','cs.campaign_id','cs.is_client','cs.docs_status','cs.is_deleted','cs.accounting_status_id','cs.dialer_id'
            )
                ->from(array('cases','c'))
                ->join(array('case_contact','cc'), 'LEFT')->on('cc.case_id', '=', 'c.id')
                ->join(array('case_statuses','cs'), 'LEFT')->on('cs.case_id', '=', 'c.id');

            $query->and_where_open();
            $query->where('cc.primary_phone','=',$phone)->or_where('cc.mobile_phone','=', $phone);
            $query->and_where_close();
            
            if($company_id){
                $query->where('c.company_id','=', $company_id);
            }
            $query->limit(1);
            return current($query->execute()->as_array());

        }



        static function findOneOrFailByPhone($phone, $company_id){

            $query = \DB::select('c.id')
                ->from(array('cases','c'))
                ->join(array('case_contact','cc'), 'LEFT')->on('cc.case_id', '=', 'c.id');

            $query->where('cc.primary_phone','=', $phone);
            $query->where('c.company_id','=', $company_id);

            $result = $query->execute()->as_array();

            if($result){

                if(count($result) > 1){
                    // Duplicate Records
                    throw new \Exception('Duplicate Records');
                }

                $case = current($result);
                return $case['id'];
            }

            return false;

        }

        /**
         * @param int $offset
         * @param int $limit
         * @param string $sort_field
         * @param string $order
         * @return array
         */
        static function findAll($offset = 0, $limit = 100, $sort_field = 'c.created', $order = 'desc', $params = array(), $count_only = false, $columns = array()){


            if(!$count_only){

                if(!empty($columns)){
                    $result = \DB::select_array($columns);
                }else {
                    $result = \DB::select('c.id', 'c.company_id', 'c.is_duplicate', 'c.last_action', 'c.action_count', 'c.created', 'c.updated', 'c.company_id',
                        'cc.first_name', 'cc.last_name', 'cc.middle_name', 'cc.email', 'cc.primary_phone', 'cc.secondary_phone', 'cc.mobile_phone', 'cc.ssn', 'cc.dob', 'cc.fax', 'cc.address', 'cc.address_2', 'cc.city', 'cc.state', 'cc.zip', 'cc.country', 'cc.timezone', 'cc.title', 'cc.dpp_contact_id',
                        'cs.is_company', 'cs.status_id', 'cs.campaign_id', 'cs.is_client', 'cs.docs_status', 'cs.is_deleted', 'cs.accounting_status_id',

                        array('s.name', 'status'),
                        array('es.updated', 'signed_date'),
                        array('m.name', 'milestone'),
                        array('camp.name', 'campaign'),
                        array('com.name', 'company'),
                        array('d.clean_name', 'assignment_name'),
                        array('cau.id', 'assignment_user_id'),
                        array('cau.email', 'assignment_user_email'),
                        array(DB::expr('CONCAT(cau.first_name, " ", cau.last_name)'), 'assignment_user')

                    );
                }

            }else{
                //$result = \DB::select(DB::expr('COUNT(DISTINCT(c.id)) as total_records'));
                $result = \DB::select('c.id');

            }

            //$sort_field = 'statust.time';
            //$order = 'ASC';

            $result->from(array('cases','c'))

                ->join(array('case_contact','cc'), 'LEFT')->on('cc.case_id', '=', 'c.id')
                ->join(array('case_statuses','cs'), 'LEFT')->on('cs.case_id', '=', 'c.id')
                ->join(array('statuses','s'), 'LEFT')->on('s.id', '=', 'cs.status_id')

                ->join(array('milestones','m'), 'LEFT')->on('m.id', '=', 's.milestone_id')
                ->join(array('campaigns', 'camp'), 'LEFT')->on('cs.campaign_id','=','camp.id')
                ->join(array('companies','com'), 'LEFT')->on('com.id', '=', 'c.company_id')
                ->join(array('case_assignments', 'ca'),'LEFT')->on('ca.case_id','=','c.id')
                ->join(array('departments', 'd'),'LEFT')->on('d.id','=','ca.department_id')
                ->join(array('users', 'cau'),'LEFT')->on('cau.id','=','ca.user_id')
                ->join(array('esign_docs','es'), 'LEFT')->on('es.case_id', '=', 'c.id')
                ->join(array('case_todos', 'cto'), 'LEFT')->on('cto.case_id', '=', 'c.id')

                ->offset($offset)
                ->limit($limit);

            // Case Additional
           /* $result->join(array('case_additional', 'cad'),'LEFT')->on('c.id','=','cad.case_id')
                ->join(array('form_fields','of'), 'LEFT')->on('cad.field_id','=','of.id')
                ->join(array('form_field_types','oft'), 'LEFT')->on('of.field_type_id','=','oft.id');*/


            $result->where('cs.is_client', '=', 0);

            $result = Model_System_Access::queryAccess($result);

            if(!empty($params)){
                foreach($params as $k => $v){
                    $result->where($k, '=', $v);
                }
            }

            if(!$count_only){
                $result->offset($offset)
                    ->limit($limit)
                    ->order_by($sort_field, $order);
            }else{

                $result = $result->group_by('c.id')->execute();
                $row = $result->as_array();
                return $row;
                //return $row['total_records'];
            }


           // var_dump(self::buildResult($result));
            // exit;



            return self::buildResult($result);
            
        }



        static function findByFilter($filter, $offset = 0, $limit = 100, $sort_field = 'id', $order = 'desc', $count_only = false, $columns = array())
        {

            if (!$count_only) {
                if(!empty($columns)){
                    $query = \DB::select_array($columns);
                }else {
                    $query = \DB::select('c.id');
                }


            } else {
                $query = \DB::select(DB::expr('COUNT(DISTINCT c.id) as total_records'));
            }

            $query->from(array('cases', 'c'))
                ->join(array('case_contact', 'cc'), 'LEFT')->on('cc.case_id', '=', 'c.id')
                ->join(array('case_statuses', 'cs'), 'LEFT')->on('cs.case_id', '=', 'c.id')
                ->join(array('statuses', 's'), 'LEFT')->on('s.id', '=', 'cs.status_id')
                ->join(array('milestones', 'm'), 'LEFT')->on('m.id', '=', 's.milestone_id')
                ->join(array('campaigns', 'camp'), 'LEFT')->on('cs.campaign_id', '=', 'camp.id')
                ->join(array('companies', 'com'), 'LEFT')->on('com.id', '=', 'c.company_id');

            $hasFinancing = false;
            $hasFinancingPay = false;
            $hasFinancingPayments = false;
            $hasAssignment = false;
            $hasTasks = false;
            $hasLabel = false;
            $hasDocs = false;
            $hasEvent = false;
            $hasEmails = false;
            $isSharkTank = false;
            $hasAction = false;
            $hasPayments = false;
            $customField = false;
            $company_set = false;
            $hasStatusLabel = false;
            $hasStatusChange = false;
            $hasCaseLabel = false;



            if (isset($filter['accounting_status_id']) && !empty($filter['accounting_status_id']) && is_array($filter['accounting_status_id'])) {
                $accounting_status_ids = array();
                foreach ($filter['accounting_status_id'] as $ac_status_id) {
                    $accounting_status_ids[] = $ac_status_id;
                }
                $query->where('cs.accounting_status_id', 'IN', $accounting_status_ids);
            }


            if (isset($filter['campaign_id']) && !empty($filter['campaign_id']) && is_array($filter['campaign_id'])) {
                $campaigns = array();
                foreach ($filter['campaign_id'] as $campaign_id) {
                    $campaigns[] = $campaign_id;
                }
                $query->where('cs.campaign_id', 'IN', $campaigns);
            }

            // Lead, Client or Terminated?
            if (isset($filter['is_client']) && !empty($filter['is_client'])) {

                if (is_array($filter['is_client'])) {
                    $stages = array();
                    foreach ($filter['is_client'] as $stage) {
                        $stages[] = $stage;
                    }

                    $query->where('cs.is_client', 'IN', $stages);
                } else {
                    $query->where('cs.is_client', '=', $filter['is_client']);
                }

            }

            if (isset($filter['processor_id']) && strlen($filter['processor_id'])) {
                if($filter['processor_id'] == 'unassigned'){
                    $query->where('cs.processor_id', '=', null);
                }else{
                    $query->where('cs.processor_id', '=', $filter['processor_id']);
                }

            }

            if ((isset($filter['status_id']) && !empty($filter['status_id'])) && is_array($filter['status_id'])) {
                $statuses = array();
                foreach ($filter['status_id'] as $status_id) {
                    $statuses[] = $status_id;
                }


                if(isset($filter['date_field']) && $filter['date_field'] == 'status_change'){
                    $query->where('log_st.status_id', 'IN', $statuses);
                }else{
                    $query->where('cs.status_id', 'IN', $statuses);
                }

            }

            if ((isset($filter['status_labels']) && !empty($filter['status_labels'])) && is_array($filter['status_labels'])) {
                $hasStatusLabel = true;
                $labels = array();
                foreach ($filter['status_labels'] as $labed_id) {
                    $labels[] = $labed_id;
                }
                $query->where('s_labels.label_id', 'IN', $labels);

            }

            if ((isset($filter['case_label_ids']) && !empty($filter['case_label_ids'])) && is_array($filter['case_label_ids'])) {
                $hasCaseLabel = true;
                $case_labels = array();
                foreach ($filter['case_label_ids'] as $label_id) {
                    $case_labels[] = $label_id;
                }
                $query->where('labels_cases.label_id', 'IN', $case_labels);

            }


            if ((isset($filter['email_template_ids']) && !empty($filter['email_template_ids'])) && is_array($filter['email_template_ids'])) {
                $hasEmails = true;
                $eids = array();
                foreach ($filter['email_template_ids'] as $id) {
                    $eids[] = $id;
                }

                if(isset($filter['email_template_operator'])) {



                    switch ($filter['email_template_operator']) {
                        case 'IN':
                            $query->where('e.template_id', 'IN', $eids);
                            break;
                        case 'NOT IN':
                            $sub_query = \DB::select('case_id')->from('emails')->where('template_id','IN', $eids);
                            $query->where(\DB::expr('c.id NOT IN'),null,$sub_query);
                            break;
                    }


                }

            }


            // TOTAL PAID
            if ((isset($filter['payment_value']) && !empty($filter['payment_value']) && isset($filter['payment_value_operator']))) {

                $query->join(array(\DB::expr('(select SUM(p.amount) as paid, p.case_id
                        from payments p
                        where p.status_id = 3
                        group by p.case_id)'),'total_paid'), 'LEFT')->on('total_paid.case_id','=','c.id');

                if($filter['payment_value_operator'] == 'range'){
                    $query->where('total_paid.paid', 'between', array($filter['payment_value'],$filter['payment_value_2']));
                }else{
                    $query->where('total_paid.paid', $filter['payment_value_operator'], $filter['payment_value']);
                }


            }



            if ((isset($filter['financing_status']) && !empty($filter['financing_status']) && is_array($filter['financing_status']))) {
                $hasFinancing = true;
                $fstatus = array();
                foreach ($filter['financing_status'] as $fstat) {
                    $fstatus[] = $fstat;
                }
                $query->where('f.status', 'IN', $fstatus);
            }


            if ((isset($filter['financing_payment_status']) && !empty($filter['financing_payment_status']) && is_array($filter['financing_payment_status']))) {
                $hasFinancingPayments = true;
                $fpstatus = array();
                foreach ($filter['financing_payment_status'] as $fstat) {
                    $fpstatus[] = $fstat;
                }
                $query->where('fp.payments_status', 'IN', $fpstatus);
            }


            if ((isset($filter['financing_approval']) && !empty($filter['financing_approval']) && is_array($filter['financing_approval']))) {
                $hasFinancing = true;
                $fapproval = array();
                foreach ($filter['financing_approval'] as $fappro) {
                    $fapproval[] = $fappro;
                }
                $query->where('f.approval', 'IN', $fapproval);
            }

            if ((isset($filter['financing_score']) && !empty($filter['financing_score']) && is_array($filter['financing_score']))) {
                $hasFinancing = true;
                $fscore = array();
                foreach ($filter['financing_score'] as $fappro) {
                    $fscore[] = $fappro;
                }
                $query->where('f.score', 'IN', $fscore);
            }


            if ((isset($filter['tasks']) && !empty($filter['tasks'])) && is_array($filter['tasks'])) {
                $hasTasks = true;
                $items = array();
                foreach ($filter['tasks'] as $item_id) {
                    $items[] = $item_id;
                }

                $query->where('cto.type_id', 'IN', $items);
                $query->where('cto.complete', '=', 0);
                //$query->having(DB::EXPR('COUNT(DISTINCT(cto.type_id))'), '=', count($items));
            }

            if (isset($filter['milestone_id']) && !empty($filter['milestone_id'])) {
                $query->where('s.milestone_id', '=', $filter['milestone_id']);
            }

            if (isset($filter['action_id']) && !empty($filter['action_id'])) {
                $hasAction = true;
                $query->where('log_actions.action_id', '=', $filter['action_id']);
            }

            if (isset($filter['shark_tank']) && !empty($filter['shark_tank'])) {
                $isSharkTank = true;
                //$query->where('cs.shark_tank', '=', 1);
            }

            if (isset($filter['docs_status']) && !empty($filter['docs_status'])) {

                $esign_status_ids = array();
                foreach ($filter['docs_status'] as $status_id) {
                    $esign_status_ids[] = $status_id;
                }

                $query->where('cs.docs_status', 'IN', $esign_status_ids);
            }
            

            if ((isset($filter['dialer_id']) && !empty($filter['dialer_id'])) && is_array($filter['dialer_id'])) {
                $dialid = array();
                foreach ($filter['dialer_id'] as $diad) {
                    $dialid[] = $diad;
                }
                $query->where('cs.dialer_id', 'IN', $dialid);

            }

            if (isset($filter['client_status']) && !empty($filter['client_status'])) {
                $query->where('cs.is_client', '=', $filter['client_status']);
            }

            if (isset($filter['states']) && !empty($filter['states'])) {
                $state_set = array();
                foreach ($filter['states'] as $state) {
                    $state_set[] = $state;
                }
                $query->where('cc.state', 'IN', $state_set);
            }

            if (isset($filter['department_id']) && !empty($filter['department_id'])) {
                $hasAssignment = true;
                $query->where('ca.department_id', '=', $filter['department_id']);
            }

            if (isset($filter['account_type_id']) && !empty($filter['account_type_id'])) {
                if(is_array($filter['account_type_id'])){
                    $account_type = array();
                    foreach ($filter['account_type_id'] as $account_id) {
                        $account_type[] = $account_id;
                    }
                    $query->where('cs.account_type_id', 'IN', $account_type);
                }else{
                    $query->where('cs.account_type_id', '=', $filter['account_type_id']);
                }

            }



            if (isset($filter['payment_plan_time_operator']) && !empty($filter['payment_plan_time_operator'])) {


                if($filter['payment_plan_time_operator'] == 'custom'){
                    $dates = \Formatter\date::getRangeArray('custom', $filter['payment_plan_time_start'], $filter['payment_plan_time_end']);
                }else{
                    $dates = \Formatter\date::getRangeArray($filter['payment_plan_time_operator']);
                }

                $query->where('cs.accounting_updated', 'between', $dates);

            }


            if (isset($filter['workflow_status_days_operator']) && !empty($filter['workflow_status_days_operator'])) {
                if($filter['workflow_status_days_operator'] == 'range'){
                    $query->where(DB::EXPR("DATEDIFF('" . date('Y-m-d') . "', cs.status_updated)"), 'between', array($filter['workflow_status_days_value'], $filter['workflow_status_days_value_2']));
                }else {
                    $query->where(DB::EXPR("DATEDIFF('" . date('Y-m-d') . "', cs.status_updated)"), $filter['workflow_status_days_operator'], $filter['workflow_status_days_value']);
                }

            }

            if (isset($filter['payment_plan_days_operator']) && !empty($filter['payment_plan_days_operator'])) {

                if($filter['payment_plan_days_operator'] == 'range'){
                    $query->where(DB::EXPR("DATEDIFF('" . date('Y-m-d') . "', cs.accounting_updated)"), 'between', array($filter['payment_plan_days_value'], $filter['payment_plan_days_value_2']));
                }else {
                    $query->where(DB::EXPR("DATEDIFF('" . date('Y-m-d') . "', cs.accounting_updated)"), $filter['payment_plan_days_operator'], $filter['payment_plan_days_value']);
                }

            }

            if((isset($filter['last_payment_status']) && !empty($filter['last_payment_status'])) ||
                (isset($filter['last_payment_amount_operator']) && !empty($filter['last_payment_amount_operator'])) ||
                    (isset($filter['last_payment_date_operator']) && !empty($filter['last_payment_date_operator']))){

                /**$query->select_array(array(
                    array('last_payment.amount', 'last_payment_amount'),
                    array('last_payment.created', 'last_payment_date'),
                    array('last_payment.date_due', 'last_payment_date_due'),
                    array('last_payment_statuses.name','last_payment_status')
                )); **/

                $query->join(array(\DB::expr('(select MAX(payments.id) payment_id, payments.case_id
                        from payments
                        group by payments.case_id)'),'last_payment_max'), 'LEFT')->on('last_payment_max.case_id','=','c.id');
                $query->join(array('payments','last_payment'))->on('last_payment.id','=','last_payment_max.payment_id');
                $query->join(array('payment_statuses','last_payment_statuses'))->on('last_payment_statuses.id','=','last_payment.status_id');


                if(isset($filter['last_payment_status'])){
                    $query->where('last_payment.status_id', '=', $filter['last_payment_status']);
                }

                if(isset($filter['last_payment_amount_operator']) && !empty($filter['last_payment_amount_operator'])){

                    if($filter['last_payment_amount_operator'] == 'range'){
                        $query->where('last_payment.amount', 'between', array($filter['last_payment_amount_value'],$filter['last_payment_amount_value_2']));
                    }else{

                        $query->where('last_payment.amount', $filter['last_payment_amount_operator'], $filter['last_payment_amount_value']);

                    }

                }

                if(isset($filter['last_payment_date_operator']) && !empty($filter['last_payment_date_operator'])){

                    if($filter['last_payment_date_operator'] == 'custom'){
                        $dates = \Formatter\date::getRangeArray('custom', $filter['last_payment_date_start'], $filter['last_payment_date_end']);
                    }else{
                        $dates = \Formatter\date::getRangeArray($filter['last_payment_date_operator']);
                    }
                    $query->where('last_payment.created', 'between', $dates);

                }

            }

            if(isset($filter['membership_date_operator']) && !empty($filter['membership_date_operator'])){

                if($filter['membership_date_operator'] == 'custom'){
                    $dates = \Formatter\date::getRangeArray('custom', $filter['membership_date_value'], $filter['membership_date_value_2']);
                }else{
                    $dates = \Formatter\date::getRangeArray($filter['membership_date_operator']);
                }

                $alias = 'ca_membership';

                $query->and_where_open();
                $query->join(array('case_additional', 'ca_membership'), 'LEFT')->on('ca_membership' . '.case_id', '=', 'c.id')->on('ca_membership' . '.field_id', '=', "'" . 1761 . "'");
                $query->where('ca_membership.f_date', 'between', $dates);
                $query->and_where_close();

            }

            if(isset($filter['membership_renew_date_operator']) && !empty($filter['membership_renew_date_operator'])){

                if($filter['membership_renew_date_operator'] == 'custom'){
                    $dates = \Formatter\date::getRangeArray('custom', $filter['membership_renew_date_value'], $filter['membership_renew_date_value_2']);
                }else{
                    $dates = \Formatter\date::getRangeArray($filter['membership_renew_date_operator']);
                }

                $alias = 'ca_membership_renew';

                $query->and_where_open();
                $query->join(array('case_additional', 'ca_membership_renew'), 'LEFT')->on('ca_membership_renew' . '.case_id', '=', 'c.id')->on('ca_membership_renew' . '.field_id', '=', "'" . 1873 . "'");
                $query->where('ca_membership_renew.f_date', 'between', $dates);
                $query->and_where_close();

            }



            if (isset($filter['user_id']) && !empty($filter['user_id'])) {
                $hasAssignment = true;
                if(isset($filter['date_field'])) {

                    switch ($filter['date_field']) {
                        case 'appointment_date': //
                            $query->where('ev.user_id', '=', (isset($filter['user_id']) ? $filter['user_id'] : Model_Account::getUserId()));
                            break;
                        default:
                            $query->where('ca.user_id', '=', $filter['user_id']);
                            break;
                    }

                }else{

                    $query->where('ca.user_id', '=', $filter['user_id']);
                }

            }

            if(isset($filter['region_id']) && !empty($filter['region_id'])){
                $hasAssignment = true;
                $query->where('cau.region_id', '=', $filter['region_id']);
            }


            if (isset($filter['company_id']) && !empty($filter['company_id'])) {
                $company_set = true;
                if(is_array($filter['company_id'])){
                    $companies = array();
                    foreach ($filter['company_id'] as $company) {
                        $companies[] = $company;
                    }
                    $query->where('c.company_id', 'IN', $companies);
                }else{
                    $query->where('c.company_id', '=', $filter['company_id']);
                }

            }

            if(isset($filter['first_payment']) && !empty($filter['first_payment'])){

            }

            if (isset($filter['financed']) && strlen($filter['financed']) && is_numeric($filter['financed'])) {
                $hasFinancing = true;
                if($filter['financed'] == 1){
                    $query->where('f.track', null, \DB::expr('IS NOT NULL'));
                }else{
                    $query->where('f.track', null, \DB::expr('IS NULL'));
                }
            }

            if(isset($filter['user_assignments']) && !empty($filter['user_assignments'])) {
                
                $user_assignments = array();

                foreach ($filter['user_assignments'] as $k => $v) {

                    if (empty($v)) {
                        continue;
                    } else {
                        $user_assignments[$k] = $v;
                    }
                }



                if (!empty($user_assignments)) {

                    $query->and_where_open();

                    $sfl = 0;
                    foreach ($user_assignments as $k => $v) {

                        $alias = 'cass_' . $sfl;

                        $query->and_where_open();

                        $query->join(array('case_assignments', $alias), 'LEFT')
                            ->on($alias . '.case_id', '=', 'c.id')
                            ->on($alias . '.department_id', '=', "'" . $k . "'");

                        if ($v == 'unassigned') {
                            $query->where($alias . '.user_id', '=', null);
                        } else {
                            $query->where($alias . '.user_id', '=', $v);
                        }

                        $query->and_where_close();
                        $sfl++;
                    }

                    $query->and_where_close();
                }

            }



            if (!empty($filter['dates']) && $filter['dates'] != 'all_time') {

                switch ($filter['date_field']) {
                    case 'created':
                        $filter['date_field'] = 'c.created';
                        break;
                    case 'action_date':
                        $filter['date_field'] = 'log_actions.created';
                        break;
                    case 'doc_submission':
                        $filter['date_field'] = 'cs.doc_submission';
                        break;
                    case 'renewal_date':
                        $filter['date_field'] = 'cs.renewal_date';
                        break;
                    case 'activation_date':
                        $filter['date_field'] = 'cs.activation_date';
                        break;
                    case 'termination_date':
                        $filter['date_field'] = 'cs.termination_date';
                        break;
                    case 'last_action':
                        $filter['date_field'] = 'c.last_action';
                        break;
                    case 'accounting_updated':
                        $filter['date_field'] = 'cs.accounting_updated';
                        break;
                    case 'status_change':
                        $filter['date_field'] = 'log_st.created';
                        $hasStatusChange = true;
                        break;
                    case 'shark_tank':
                        $filter['date_field'] = 'cs.shark_tank_date';
                        break;
                    case 'purchase_date':
                        $hasFinancingPay = true;
                        $filter['date_field'] = 'fpay.purchase_date';
                        break;
                    case 'credit_date':
                        $hasFinancing = true;
                        $filter['date_field'] = 'f.created';
                        break;
                    case 'first_pay':
                        $hasFinancing = true;
                        $filter['date_field'] = 'f.initial_payment_date';
                        break;
                    case 'esign_date': // first signed doc
                        $hasDocs = true;
                        $filter['date_field'] = 'es.updated';
                        $query->where('es.category_id','=', 1);
                        $query->where('es.status','=', 'Signed');
                        break;
                    case 'esign_sent_date': // first signed doc
                        $hasDocs = true;
                        $filter['date_field'] = 'es.created';
                        //$query->where('es.category_id','=', 1);
                        $query->where('es.status','=', 'Sent');
                        break;
                    case 'appointment_date': // first signed doc
                        $hasEvent = true;
                        $filter['date_field'] = 'ev.at';
                        $query->where('ev.completed','=', NULL);
                        break;
                }

                if ($filter['dates'] == 'today') {
                    $query->where($filter['date_field'], 'between', array(date('Y-m-d 00:00:00'), date('Y-m-d 23:59:59')));
                } elseif ($filter['dates'] == 'yesterday') {
                    $yest = date('Y-m-d', strtotime('-1 day'));
                    $query->where($filter['date_field'], 'between', array($yest . ' 00:00:00', $yest . ' 23:59:59'));
                } elseif ($filter['dates'] == 'last7') {
                    $query->where($filter['date_field'], 'between', array(date('Y-m-d 00:00:00', strtotime('-7 days')),date('Y-m-d 23:59:59')));
                } elseif ($filter['dates'] == 'last30') {
                    $query->where($filter['date_field'], 'between', array(date('Y-m-d 00:00:00', strtotime('-30 days')),date('Y-m-d 23:59:59')));
                } elseif ($filter['dates'] == 'this_month') {
                    $query->where($filter['date_field'], 'between', array(date('Y-m-') . '01', date('Y-m-d H:59:59')));
                } elseif ($filter['dates'] == 'last_month') {
                    $query->where($filter['date_field'], 'between', array(date('Y-m-', strtotime('-1 month')) . '01', date('Y-m-t 23:59:59', strtotime('-1 month'))));
                } elseif ($filter['dates'] == 'custom') {
                    if(!isset($filter['start_date']) || !isset($filter['end_date'])){
                        $filter['start_date'] = date('Y-m-d 00:00:00');
                        $filter['end_date'] = date('Y-m-d 23:59:59');
                    }
                    $query->where($filter['date_field'], 'between', array(date('Y-m-d', strtotime($filter['start_date'])). ' 00:00:00', date('Y-m-d', strtotime($filter['end_date'])) . ' 23:59:59'));
                }
            }






            if(isset($filter['custom_field']['field'][0]) && !empty($filter['custom_field']['field'][0])){

                $custom_fields = array();
                foreach ($filter['custom_field']['field'] as $id => $v) {
                    if (!empty($filter['custom_field']['field'][$id]) && !empty($filter['custom_field']['value'][$id])) {
                        $custom_fields[$v] = $filter['custom_field']['value'][$id];
                    }
                }

                $field_objects = array();
                $form_fields = \Model_System_Form_Fields::getCustomObjectFields();
                foreach($form_fields as $obj_field){
                    $field_objects[$obj_field['id']] = $obj_field['f_field'];
                }

                if(!empty($custom_fields)){

                    $query->and_where_open();

                    $sfl = 0;
                    foreach ($custom_fields as $k => $v) {

                        if(isset($field_objects[$k])) {

                            $alias = 'cadd_' . $sfl;

                            $query->and_where_open();
                            $query->join(array('case_additional', $alias), 'LEFT')->on($alias . '.case_id', '=', 'c.id')->on($alias . '.field_id', '=', "'" . $k . "'");
                            if ($v == 'NOTEMPTY') {
                                $query->where($alias . '.' . $field_objects[$k], null, \DB::expr('IS NOT NULL'));
                            } elseif ($v == 'EMPTY') {
                                $query->where($alias . '.' . $field_objects[$k], null, \DB::expr('IS NULL'));
                            } else {
                                $query->where($alias . '.' . $field_objects[$k], '=', $v);
                            }


                            $query->and_where_close();

                            $sfl++;

                        }


                    }

                    $query->and_where_close();

                }

            }


            switch (Model_Account::getType()) {
                case 'Master':
                    // Can see all
                    break;
                case 'Network':
                    if(!$company_set) {
                        $query->where_open();
                        $query = Model_Networks::queryNetworkCases($query, Model_System_User::getSessionMeta('network_companies'));
                        $query->or_where('c.company_id', '=', Model_System_User::getSessionMeta('company_id'));
                        /*$query->or_where('ca.user_id','=', Model_System_User::getSessionMeta('id'));*/
                        $query->where_close();
                    }
                    break;
                case 'Company':
                    if(!$company_set) {
                        $query->where_open();
                        $query->where('c.company_id', '=', Model_System_User::getSessionMeta('company_id'));
                        /*$query->or_where('ca.user_id','=', Model_System_User::getSessionMeta('id'));*/
                        $query->where_close();
                    }
                    break;
                case 'Region':
                    $hasAssignment = true;
                    //$query->where('rg.region_id','=', Model_System_User::getSessionMeta('region_id'));
                    $query->where('ca.user_id', '=', Model_System_User::getSessionMeta('id'));
                    break;
                case 'Campaign':
                    //$query->where('rg.region_id','=', Model_System_User::getSessionMeta('region_id'));
                    $campaigns = Model_System_Campaign::findByGroup(Model_System_User::getSessionMeta('campaign_group_id'));

                    if (is_array($campaigns)){
                        foreach ($campaigns as $c) {
                            $ids[] = $c['id'];
                        }
                        $query->where('cs.campaign_id', 'IN', $ids);
                    }
                    break;
                case 'User':
                    $hasAssignment = true;

                    if (!$isSharkTank) {
                        $query->where('ca.user_id', '=', Model_System_User::getSessionMeta('id'));
                    }else{

                        $hasStatusLabel = true;

                        $query->where_open();
                        $query->where('ca.user_id', '=', Model_System_User::getSessionMeta('id'));
                        $query->or_where('s_labels.label_id', '=', 3); // Shark Tank
                        $query->where_close();
                    }

                    break;
            }


            if($hasAssignment){
                $query->join(array('case_assignments', 'ca'), 'LEFT')->on('ca.case_id', '=', 'c.id');
                $query->join(array('departments', 'd'), 'LEFT')->on('d.id', '=', 'ca.department_id');
                $query->join(array('users', 'cau'), 'LEFT')->on('cau.id', '=', 'ca.user_id');
            }
            if($hasFinancing){
                $query->join(array('financing', 'f'), 'LEFT')->on('f.case_id', '=', 'c.id');
            }
            if($hasFinancingPay){
                $query->join(array('financing_payouts', 'fpay'), 'LEFT')->on('fpay.case_id', '=', 'c.id');
            }
            if($hasTasks){
                $query->join(array('case_todos', 'cto'), 'LEFT')->on('cto.case_id', '=', 'c.id');
            }
            if($hasLabel){
                $query->join(array('case_labels', 'clb'), 'LEFT')->on('clb.case_id', '=', 'c.id');
            }

            if($hasCaseLabel){
                $query->join('labels_cases', 'LEFT')->on('labels_cases.case_id', '=', 'c.id');
            }


            if($hasStatusLabel){
                $query->join(array('statuses_labels', 's_labels'), 'LEFT')->on('s_labels.status_id', '=', 'cs.status_id');
            }
            if($hasDocs){
                $query->join(array('esign_docs', 'es'), 'LEFT')->on('es.case_id', '=', 'c.id');
            }
            if($hasStatusChange){
                $query->join(array('log_statuses', 'log_st'), 'LEFT')->on('log_st.case_id', '=', 'c.id');
            }
            if($hasEmails){
                $query->join(array('emails', 'e'), 'LEFT')->on('e.case_id', '=', 'c.id');
            }
            if($hasEvent){
                $query->join(array('events', 'ev'), 'LEFT')->on('ev.case_id', '=', 'c.id');
            }

            if($hasFinancingPayments){
                $query->join(array('financing_payments', 'fp'), 'LEFT')->on('fp.case_id', '=', 'c.id');
            }

            if($hasFinancingPayments){
                $query->join(array('payments', 'p'), 'LEFT')->on('p.case_id', '=', 'c.id');
            }

            if($hasAction){
                $query->join('log_actions', 'LEFT')->on('log_actions.case_id', '=', 'c.id');
            }


            switch($sort_field){
                case 'case_id':
                case 'id':
                    $sort_field = 'c.id';
                    break;
                case 'created':
                    $sort_field = 'c.created';
                    break;
                case 'campaign_id':
                    $sort_field = 'cs.campaign_id';
                    break;
                case 'status_id':
                    $sort_field = 'cs.status_id';
                    break;
                case 'action_count':
                    $sort_field = 'c.action_count';
                    break;
                case 'status_updated': // Days in status
                    $sort_field = 'cs.status_updated';
                    break;
                case 'last_action':
                    $sort_field = 'c.last_action';
                    break;
                case 'accounting_id':
                    $sort_field = 'cs.accounting_status_id';
                    break;
                case 'purchase_date':
                    $sort_field = 'fpay.purchase_date';
                    break;
                default:
                    $sort_field = 'c.id';
                    break;
            }

            $query = \Model_System_Access::queryAccess($query);

            if (!$count_only) {
                $query->offset($offset)
                      ->limit($limit)
                      ->order_by($sort_field, $order)
                      ->group_by('c.id');

            } else {
                $result = $query->execute();
                $row = current($result->as_array());
                return $row['total_records'];
            }

            $result = $query->execute();

            return $result->as_array();

            //return self::buildResult($query);
        }





        static function findTotalByFilter($filter, $group_by = '')
        {

            if(isset($filter['date_field'])) {

                switch ($filter['date_field']) {
                    case 'created':
                        $filter['date_field'] = 'c.created';
                        break;
                    case 'doc_submission':
                        $filter['date_field'] = 'cs.doc_submission';
                        break;
                    case 'last_action':
                        $filter['date_field'] = 'c.last_action';
                        break;
                }
            }

            $query = \DB::select(
                DB::expr('count(DISTINCT c.id) as total'),
                DB::expr('EXTRACT(YEAR FROM '.$filter['date_field'].') as year'),
                DB::expr('EXTRACT(MONTH FROM '.$filter['date_field'].') as month'),
                DB::expr('EXTRACT(DAY FROM '.$filter['date_field'].') as day')
            )
                ->from(array('cases', 'c'))
                ->join(array('case_contact', 'cc'), 'LEFT')->on('cc.case_id', '=', 'c.id')
                ->join(array('case_statuses', 'cs'), 'LEFT')->on('cs.case_id', '=', 'c.id')
                ->join(array('statuses', 's'), 'LEFT')->on('s.id', '=', 'cs.status_id')
                ->join(array('milestones', 'm'), 'LEFT')->on('m.id', '=', 's.milestone_id')
                ->join(array('campaigns', 'camp'), 'LEFT')->on('cs.campaign_id', '=', 'camp.id')
                ->join(array('case_assignments', 'ca'), 'LEFT')->on('ca.case_id', '=', 'c.id')
                ->join(array('departments', 'd'), 'LEFT')->on('d.id', '=', 'ca.department_id')
                ->join(array('users', 'cau'), 'LEFT')->on('cau.id', '=', 'ca.user_id')
                ->join(array('companies', 'com'), 'LEFT')->on('com.id', '=', 'c.company_id')
                ->join(array('esign_docs', 'es'), 'LEFT')->on('es.case_id', '=', 'c.id')
                ->join(array('case_todos', 'cto'), 'LEFT')->on('cto.case_id', '=', 'c.id');

            if (isset($filter['accounting_status_id']) && !empty($filter['accounting_status_id']) && is_array($filter['accounting_status_id'])) {
                $accounting_status_ids = array();
                foreach ($filter['accounting_status_id'] as $ac_status_id) {
                    $accounting_status_ids[] = $ac_status_id;
                }
                $query->where('cs.accounting_status_id', 'IN', $accounting_status_ids);
            }


            if (isset($filter['campaign_id']) && !empty($filter['campaign_id']) && is_array($filter['campaign_id'])) {
                $campaigns = array();
                foreach ($filter['campaign_id'] as $campaign_id) {
                    $campaigns[] = $campaign_id;
                }
                $query->where('cs.campaign_id', 'IN', $campaigns);
            }

            // Lead, Client or Terminated?
            if (isset($filter['is_client']) && !empty($filter['is_client'])) {
                $is_client = 0;
                switch($filter['is_client']){
                    case 'lead':
                        $is_client = 0;
                        break;
                    case 'client':
                        $is_client = 1;
                        break;
                    case 'terminated':
                        $is_client = 2;
                        break;
                }

                $query->where('cs.is_client', '=', $is_client);
            }

            if ((isset($filter['status_id']) && !empty($filter['status_id'])) && is_array($filter['status_id'])) {
                $statuses = array();
                foreach ($filter['status_id'] as $status_id) {
                    $statuses[] = $status_id;
                }
                $query->where('cs.status_id', 'IN', $statuses);

            }

            if ((isset($filter['tasks']) && !empty($filter['tasks'])) && is_array($filter['tasks'])) {
                $items = array();
                foreach ($filter['tasks'] as $item_id) {
                    $items[] = $item_id;
                }

                $query->where('cto.type_id', 'IN', $items);
                $query->where('cto.complete', '=', 0);
                //$query->having(DB::EXPR('COUNT(DISTINCT(cto.type_id))'), '=', count($items));
            }

            if (isset($filter['milestone_id']) && !empty($filter['milestone_id'])) {
                $query->where('s.milestone_id', '=', $filter['milestone_id']);
            }

            if (isset($filter['docs_status']) && !empty($filter['docs_status'])) {
                $query->where('cs.docs_status', '=', $filter['docs_status']);
            }

            if (isset($filter['client_status']) && !empty($filter['client_status'])) {
                $query->where('cs.is_client', '=', $filter['client_status']);
            }

            if (isset($filter['department_id']) && !empty($filter['department_id'])) {
                $query->where('ca.department_id', '=', $filter['department_id']);
            }

            if (isset($filter['user_id']) && !empty($filter['user_id'])) {
                $query->where('ca.user_id', '=', $filter['user_id']);
            }

            if(isset($filter['region_id']) && !empty($filter['region_id'])){
                $query->where('cau.region_id', '=', $filter['region_id']);
            }

            if (isset($filter['company_id']) && !empty($filter['company_id'])) {
                $query->where('c.company_id', '=', $filter['company_id']);
            }

            if (isset($filter['financed']) && !empty($filter['financed'])) {

                $query->where('cs.financed', '=',  ($filter['financed'] == 0 ?'NULL':$filter['financed']));
            }



            if (!empty($filter['dates']) && $filter['dates'] != 'all_time') {
                /* Fix for 2038 + */
                //$d = new DateTime( '2040-11-23' );
                // echo $d->format( 'Y-m-t' );

                if ($filter['dates'] == 'day') {
                    $query->where($filter['date_field'], 'between', array(
                        date('Y-m-d 00:00:00', strtotime($filter['date'])),
                        date('Y-m-d 23:59:59', strtotime($filter['date']))
                    ));
                } elseif ($filter['dates'] == 'month') {
                    $query->where($filter['date_field'], 'between', array(
                        date('Y-m-', strtotime($filter['date'])) . '01 00:00:00',
                        date('Y-m-t' , strtotime($filter['date'])). ' 23:59:59'
                    ));
                } elseif ($filter['dates'] == 'year') {
                    $query->where($filter['date_field'], 'between', array(
                        date('Y-', strtotime($filter['date'])) . '01-01 00:00:00',
                        date('Y-' , strtotime($filter['date'])). '12-31 23:59:59'
                    ));
                }elseif ($filter['dates'] == 'custom') {
                    $query->where($filter['date_field'], 'between', array(date('Y-m-d', strtotime($filter['start_date'])). ' 00:00:00', date('Y-m-d', strtotime($filter['end_date'])) . ' 23:59:59'));
                }
            }

            $query = \Model_System_Access::queryAccess($query);

            if($group_by) {
                $query->group_by($group_by)
                    ->order_by($group_by);
            }
            $results = $query->execute()->as_array();

            return $results;

        }



        static function findCompany($id){

            $result = current(DB::select('c.company_id')
                ->from(array('cases','c'))
                ->where('c.id', '=', $id)
                ->execute()->as_array());
            if($result){
                return $result['company_id'];
            }
            return false;

        }


        /**
         * @return mixed
         */
        static function getLastResultCount(){
            return self::$last_result_count;
        }


        /**
         * @param $query
         * @return array
         */
        static function search($query, $count_only = false){

            ini_set('memory_limit', '256M');

            $isEmail = filter_var($query, FILTER_VALIDATE_EMAIL);
            $phone_query = preg_replace('/[^0-9]/','', $query);
            $query = strtolower($query);
            if(isset($_GET['context'])) {
                $context = strtolower($_GET['context']);
            }else{
                $context = 'general';
            }


            /*

            VARCHAR vs.
            CHAR vs.
            INT

            ssn - 9
            phone - 10 or 11
            lead (any)
            email - XX@XX.com
            name - (letters only)
            dpp id - (5-7 digits)
            financing contract - XX-99999

            */

            if(!$count_only){

                $q = \DB::select('c.id','c.company_id','c.created','cc.primary_phone','c.action_count','c.last_action','cc.first_name', 'cc.last_name','cc.email','cc.vendor_id',
                    array('m.name', 'milestone'),
                    array('s.name','status'),
                    array('camp.name','campaign'),
                    array('com.name', 'company')
                );

            }else{
                $q = \DB::select(DB::expr('count(c.id) as total_records'));

            }

            $q->from(array('cases','c'))
                ->join(array('case_contact','cc'), 'LEFT')->on('cc.case_id', '=', 'c.id')
                ->join(array('case_statuses','cs'), 'LEFT')->on('cs.case_id', '=', 'c.id')
                ->join(array('statuses','s'), 'LEFT')->on('s.id', '=', 'cs.status_id')
                ->join(array('milestones', 'm'), 'LEFT')->on('m.id', '=', 's.milestone_id')
                ->join(array('campaigns', 'camp'), 'LEFT')->on('cs.campaign_id','=','camp.id')
                ->join(array('companies','com'), 'LEFT')->on('com.id', '=', 'c.company_id');

            // Searching for Email first since email can contain letters and numbers



            if($context == 'case_id'){
                $q->where('c.id','=', $query);

            }

            if($context == 'ssn'){
                $q->where('cc.ssn','=', $query);

            }

            if($context == 'vendor'){
                $q->where('cc.vendor_id','=', $query);

            }

            if($context == 'direct_mail'){
                $q->where('cc.direct_mail_id','=', $query);

            }

            $has_status_limit =  \Model_System_Status_Users::hasRecords(\Model_Account::getUserId());
            if($has_status_limit == '1') {
                $user_statuses = \Model_System_Status_Users::findByUser(\Model_Account::getUserId());
                $q->where('cs.status_id','IN', $user_statuses);

            }


            if($context == 'financing_contract'){
                // Matches Financing Contract ID Format

                $string_query = \DB::query("SELECT case_id as id FROM financing f WHERE contract_id = :contract_id UNION SELECT id FROM cases WHERE id = :contract_id")
                    ->bind('contract_id', $query)
                    ->execute();
                $result = $string_query->as_array();
                foreach($result as $r){
                    $ids[] = $r['id'];
                }
                if($result){
                    $q->where('c.id','IN', $ids);
                }else{
                    $q->where('c.id','=',0);
                }

                \Model_Log::append('q',\DB::last_query());

            }


            if($context == 'financing_track'){

                $string_query = \DB::query("SELECT case_id as id FROM financing f WHERE track = :track UNION SELECT id FROM cases WHERE id = :track")
                    ->bind('track', $query);
                $result = $string_query->execute()->as_array();
                if($result){
                    $q->where('c.id','IN', $result);
                }else{
                    $q->where('c.id','=',0);
                }


            }

            if($context == 'financing_api'){

                $string_query = \DB::query("SELECT case_id as id FROM financing f WHERE application_id = :track UNION SELECT id FROM cases WHERE id = :track")
                    ->bind('track', $query);
                $result = $string_query->execute()->as_array();
                if($result){
                    $q->where('c.id','IN', $result);
                }else{
                    $q->where('c.id','=',0);
                }


            }

            if($context == 'email'){
                $q->where('cc.email', '=', $query);

            }

            if($context == 'full_name'){

                $query = preg_replace('/\s+/', ' ', $query);
                $hasSpace = preg_match('/\s/', $query);
                if ($hasSpace) {
                    $exploded_query = explode(' ', $query);
                    //(first_name = $query OR last_name = $query OR (first_name = $exploded_query[0] AND last_name = $exploded_query[1]))
                    $string_query = \DB::query("SELECT case_id as id FROM case_contact cc WHERE (first_name = :fname OR last_name = :lname OR (first_name = :part1 AND last_name = :part2))")
                        ->bind('fname', $query)
                        ->bind('lname', $query)
                        ->bind('part1', $exploded_query[0])
                        ->bind('part2', $exploded_query[1]);
                } else {

                    // Matches only Letters (Probably First or Last Name)
                    if (preg_match("/[A-Za-z]/", $query) == TRUE) {
                        $string_query = \DB::query("SELECT case_id as id FROM case_contact cc WHERE first_name = :fname OR last_name = :lname")
                            ->bind('fname', $query)
                            ->bind('lname', $query);
                    }

                }
                if (isset($string_query) && !empty($string_query)) {
                    $result = $string_query->execute()->as_array();
                }else{
                    if($count_only){
                        return 0;
                    }
                    return array();
                }
                if (isset($result) && !empty($result)) {
                    $q->where('c.id', 'IN', $result);
                } else {
                    $q->where('c.id', '=', 0);
                }

            }

            if($context =='phone'){
                $digit_query = \DB::query("SELECT case_id as id FROM case_contact WHERE (primary_phone = :digits OR secondary_phone = :digits OR mobile_phone = :digits) UNION SELECT id FROM cases WHERE id = :digits")
                    ->bind('digits', $phone_query)
                    ->execute();
                $result = $digit_query->as_array();
                if ($result) {
                    $q->where('c.id', 'IN', $result);
                } else {
                    $q->where('c.id', '=', 0);
                }
            }


            if($context =='trust_account'){
                $digit_query = \DB::query("SELECT case_id as id FROM trust_accounts WHERE account_id = :digits UNION SELECT id FROM cases WHERE id = :digits")
                    ->bind('digits', $query)
                    ->execute();
                $result = $digit_query->as_array();
                if ($result) {
                    $q->where('c.id', 'IN', $result);
                } else {
                    $q->where('c.id', '=', 0);
                }
            }

            if($context =='first_name'){

                $string_query = \DB::query("select case_id as id from case_contact cc where first_name like concat('%', :fname, '%') order by first_name like concat(:fname, '%') desc,
                  ifnull(nullif(instr(first_name, concat(' ', :fname)), 0), 99999),
                  ifnull(nullif(instr(first_name, :fname), 0), 99999),
                  first_name")
                    ->bind('fname', $query);
                $result = $string_query->execute()->as_array();
                if ($result) {
                    $q->where('c.id', 'IN', $result);
                } else {
                    $q->where('c.id', '=', 0);
                }

            }

            if($context =='last_name'){


                $string_query = \DB::query("select case_id as id from case_contact cc where last_name like concat('%', :lname, '%') order by last_name like concat(:lname, '%') desc,
                  ifnull(nullif(instr(last_name, concat(' ', :lname)), 0), 99999),
                  ifnull(nullif(instr(last_name, :lname), 0), 99999),
                  last_name")
                    ->bind('lname', $query);
                $result = $string_query->execute()->as_array();
                if ($result) {
                    $q->where('c.id', 'IN', $result);
                } else {
                    $q->where('c.id', '=', 0);
                }
            }


            if($context == 'general'){

                if($isEmail){
                    $q->where('cc.email', '=', $query);
                }else {


                    if (!empty($phone_query)) {

                        $digit_query = \DB::query("SELECT case_id as id FROM case_contact WHERE (primary_phone = :digits OR mobile_phone = :digits OR ssn = :digits OR dpp_contact_id = :digits) UNION SELECT id FROM cases WHERE id = :digits")
                            ->bind('digits', $phone_query)
                            ->execute();
                        $result = $digit_query->as_array();
                        if ($result) {
                            $q->where('c.id', 'IN', $result);
                        } else {
                            $q->where('c.id', '=', 0);
                        }

                    } else {

                        $query = preg_replace('/\s+/', ' ', $query);
                        $hasSpace = preg_match('/\s/', $query);
                        $string_query = '';

                        if ($hasSpace || $context == 'full_name') {
                            $exploded_query = explode(' ', $query);
                            //(first_name = $query OR last_name = $query OR (first_name = $exploded_query[0] AND last_name = $exploded_query[1]))
                            $string_query = \DB::query("SELECT case_id as id FROM case_contact cc WHERE (first_name = :fname OR last_name = :lname OR (first_name = :part1 AND last_name = :part2))")
                                ->bind('fname', $query)
                                ->bind('lname', $query)
                                ->bind('part1', $exploded_query[0])
                                ->bind('part2', $exploded_query[1]);
                        } else {


                            // Matches Financing Contract ID Format
                            if (preg_match("/[a-z]{2}+[-][0-9]{5}/i", $query, $financing_match)) {
                                $string_query = \DB::query("SELECT case_id as id FROM financing f WHERE contract_id = :contract_id")
                                    ->bind('contract_id', $query);

                            }

                            // Matches only Letters (Probably First or Last Name)
                            if (preg_match("/[A-Za-z]/", $query) == TRUE) {

                                $string_query = \DB::query("SELECT case_id as id FROM case_contact cc WHERE first_name = :fname OR last_name = :lname")
                                    ->bind('fname', $query)
                                    ->bind('lname', $query);
                            }

                        }

                        if (isset($string_query) && !empty($string_query)) {
                            $result = $string_query->execute()->as_array();
                        }
                        if (isset($result) && !empty($result)) {
                            $q->where('c.id', 'IN', $result);
                        } else {
                            $q->where('c.id', '=', 0);
                        }
                    }
                }

            }

            $q = \Model_System_Access::queryAccess($q);

            $q->limit(100);

            if($count_only){

                $result = $q->execute();
                $row = current($result->as_array());
                return $row['total_records'];
            }

            return $q->execute()->as_array();

            //return self::buildResult($q);

        }



        /**
         * @param $query
         * @return mixed
         * @throws Exception
         */
        static function quickSearch($query){
            
            $query = preg_replace('/[^0-9]/','', $query);

            if(empty($query)){
                throw new Exception('This search only works with the Lead ID or a Phone Number');
            }

            $result = \DB::select('c.id','c.company_id','c.is_duplicate','c.last_action','c.action_count','c.created','c.updated','cc.first_name','cc.last_name','cc.middle_name','cc.email','cc.primary_phone','cc.secondary_phone','cc.mobile_phone','cc.ssn','cc.dob','cc.fax','cc.address','cc.address_2','cc.city','cc.state','cc.zip','cc.country','cc.timezone','cc.title','cc.dpp_contact_id','cs.is_company','cs.status_id','cs.campaign_id','cs.is_client','cs.docs_status','cs.is_deleted','cs.accounting_status_id',

                array('s.name', 'status'),
                array('camp.name','campaign'),
                array('com.name','company'))

                ->from(array('cases','c'))
                ->join(array('case_contact','cc'), 'LEFT')->on('cc.case_id', '=', 'c.id')
                ->join(array('case_statuses','cs'), 'LEFT')->on('cs.case_id', '=', 'c.id')
                ->join(array('statuses','s'), 'LEFT')->on('s.id', '=', 'cs.status_id')
                ->join(array('milestones','m'), 'LEFT')->on('m.id', '=', 's.milestone_id')
                ->join(array('companies','com'), 'LEFT')->on('com.id', '=', 'c.company_id')
                ->join(array('case_assignments', 'ca'),'LEFT')->on('ca.case_id','=','c.id')
                ->join(array('departments', 'd'),'LEFT')->on('d.id','=','ca.department_id')
                ->join(array('users', 'cau'),'LEFT')->on('cau.id','=','ca.user_id')
                ->join(array('campaigns','camp'), 'LEFT')->on('cs.campaign_id', '=', 'camp.id')

                ->where('cc.primary_phone', '=', $query)
                ->or_where('cc.secondary_phone', '=', $query)
                ->or_where('cc.mobile_phone', '=', $query)
                ->or_where('c.id', '=', $query)
                ->limit(1)
                ->execute();

/*

            $mdb = Mongo_Db::instance();
            $mdb->or_where(array('_id' => (int)$query, 'primary_phone' => $query, 'secondary_phone' => $query, 'mobile_phone' => $query));
            
            $result = $mdb->get('cases');*/

            //there was only 1 result from mongo, it's not a duplicate
            if(count($result->as_array()) == 1){

                $item = current($result->as_array());
                return $item['id'];

            }else{
                throw new Exception('No results for: '.$query);
            }
            
        }

        /**
         * @return mixed
         */
        static function countItems(){

            $result = \DB::select(DB::expr('count(DISTINCT(c.id)) as items'))
                ->from(array('cases','c'))
                ->join(array('case_statuses','cs'), 'LEFT')->on('cs.case_id', '=', 'c.id')
                ->join(array('case_assignments','ca'), 'LEFT')->on('ca.case_id','=','c.id')
                ->join(array('shared_cases','sc'), 'LEFT')->on('sc.case_id','=','c.id');

            // Checking Shared Permissions of Case
            $result = Model_System_Access::queryAccess($result);


            $result->where('cs.is_client', '=', 0);

            $row = current($result->execute()->as_array());
            return $row['items'];
            
        }

        /**
         * @param $result
         * @return mixed
         */
        static function setCriteria($result){
            return $result;
        }





        /**
         * @param $db_result
         * @param null $fields
         * @return array
         */
        static function buildResult($db_result, $column_names = null, $useAdditional = true){

            $data = array();
            $ids = array();

            $result = $db_result->execute()->as_array();


            foreach($result as $row){

                $ids[] = $row['id'];

                foreach($row as $key => $value){
                    $data[$row['id']][$key] = $value;
                }

                if(isset($row['clean_name'])) {
                    $data[$row['id']][$row['clean_name']] = $row[$row['f_field']];
                }


                if(isset($row['assignment_name']) && isset($row['assignment_user'])){
                    $data[$row['id']]['dept_'.$row['assignment_name']] =  $row['assignment_user'];
                    if(isset($row['assignment_user_id']) && !empty($row['assignment_user_id'])){
                        $data[$row['id']]['dept_'.$row['assignment_name'].'_id'] =  $row['assignment_user_id'];
                    }
                    if(isset($row['assignment_user_email']) && !empty($row['assignment_user_email'])){
                        $data[$row['id']]['dept_'.$row['assignment_name'].'_email'] =  $row['assignment_user_email'];
                    }


                }

            }

            if($useAdditional) {

                if (isset($ids) && !empty($ids)) {

                    $q = \DB::select('case_additional.*', 'form_field_types.f_field', 'form_fields.clean_name')
                        ->from('case_additional')
                        ->join('form_fields', 'left')->on('case_additional.field_id', '=', 'form_fields.id')
                        ->join('form_field_types', 'left')->on('form_fields.field_type_id', '=', 'form_field_types.id')
                        ->where('case_id', 'IN', $ids)
                        ->where('form_fields.system', '=', 0);

                    if (isset($column_names)) {

                        if (!empty($fnames)) {
                            $q->where('form_fields.clean_name', 'IN', $column_names);
                        }

                    }

                    $additional_records = $q->execute()->as_array();
                    if (isset($additional_records) && !empty($additional_records)) {
                        foreach ($additional_records as $obj) {
                            if (isset($obj[$obj['f_field']]) && !empty($obj[$obj['f_field']])) {
                                $data[$obj['case_id']][$obj['clean_name']] = $obj[$obj['f_field']];
                            }
                        }
                    }

                }
            }

            if(isset($ids) && !empty($ids)) {
                $student_program_sets = \Student\Model_Programs::findByCaseIds($ids);
                if ($student_program_sets) {
                    foreach ($student_program_sets as $student_programs) {
                        //
                        foreach ($student_programs as $spk => $spv) {
                            $data[$student_programs['case_id']]['student_programs_' . $spk] = $spv;
                        }
                    }
                }
            }

            return array_values($data);
        }

        /**
         * @param $mresult
         * @return array
         */
        /*static function buildResultFromMongo($mresult){
            $ids = array();
            foreach($mresult as $row){
                $holding[$row['_id']] = $row;
                $ids[] = $row['_id'];
            }
            
            if(empty($ids)){
                return array();
            }
            
            $result = \DB::select('c.*', array(DB::expr('CONCAT(u.first_name, " ", u.last_name)'), 'sales_rep_name'), array('m.name', 'milestone'))
                            ->from(array('cases','c'))
                            ->join(array('users','u'), 'LEFT')->on('u.id', '=', 'c.sales_rep_id')
                            ->join(array('statuses','s'), 'LEFT')->on('s.id', '=', 'c.status_id')
                            ->join(array('milestones', 'm'), 'LEFT')->on('m.id', '=', 's.milestone_id')
                            ->where('c.id', 'in', $ids);
            /* 
             * condition removed to allow users to search by phone or lead id to determine rep
            if(Model_Account::getType() == 'User'){
                $result->where('sales_rep_id', 'IN', array($_SESSION['user']['id'], 0));
            }

            $result = self::setCriteria($result);
            $result = $result->execute();
            
            $items = array();
            foreach($result->as_array() as $row){
                $items[$row['id']] = $holding[$row['id']];
                foreach($row as $k => $v){
                    $items[$row['id']][$k] = $v;
                }
            }
            
            unset($holding);
            
            return $items;
        }
*/
        /**
         * @param $data
         * @param bool $enforce_dup_filter
         * @return bool|mixed
         */
        static function add($data, $enforce_dup_filter = true, $auto_assign=false){

            try{
            
            $data_as_entered = $data;

            list($case, $status, $contact) = self::sortCaseVars($data);
            
            if(!isset($data['status'])){
                $status['status_id'] = 1;
            }

            /** Creation Dates */
            if(isset($data['created']) && !empty($data['created'])){
                $created_date = date('Y-m-d H:i:s', strtotime($data['created']));
            }else{
                $created_date = date('Y-m-d H:i:s');
            }

            $case['created'] = $created_date;
            $status['created'] = $created_date;
            $contact['created'] = $created_date;

            //Model_Log::append('debug', 'campaign:'.$data['campaign_id']);

                /** Campaign Assignment */
            $campaign = false;
            if(isset($data['campaign_id'])) {
                $campaign = Model_System_Campaign::find($data['campaign_id']);
                if(!$campaign){
                    throw new Exception("Campaign ID not in system");
                }
            }elseif(isset($data['campaign'])){
                $campaign = Model_System_Campaign::findByName($data['campaign']);
                if(!$campaign){
                    throw new Exception("No Campaign or Account Match");
                }
            }

            if($campaign){
                $status['campaign_id'] = $campaign['id'];
                $case['company_id'] = $campaign['company_id'];
                $case['source_id'] = $campaign['company_id'];
            }

            if(isset($data['company_id']) && !empty($data['company_id'])){
                $case['company_id'] = $data['company_id'];
                $case['source_id'] = $data['company_id'];
            }


           /** Phone Numbers */
            $data['all_phone_numbers'] = array();
            $phone_fields = array('primary_phone','secondary_phone','mobile_phone');
            foreach($phone_fields as $f){
                if(isset($data[$f])){
                    $regex = "/^(\d[\s-]?)?[\(\[\s-]{0,2}?\d{3}[\)\]\s-]{0,2}?\d{3}[\s-]?\d{4}$/i";
                    $number = preg_replace('/[^0-9]/','', $data[$f]);
                    $contact[$f] =  (preg_match( $regex, $number ) ? $number : null);
                    if(!empty($contact[$f])){
                        $data['all_phone_numbers'][] = preg_replace('/[^0-9]/','', $contact[$f]);
                    }
                }
            }

            /** Match Area Code */
            if(isset($data['primary_phone'])){
                $area_code = substr(preg_replace('/[^0-9]/','', $data['primary_phone']), 0, 3);
                $contact['timezone'] = Model_System_Timezone::findByAreaCode($area_code);
            }



            /** Dupe Filter */
            if($enforce_dup_filter) {
                // CHECK DUPLICATE LEAD
                $duplicate_case_id = Model_System_DuplicateFilter::checkDuplicate($data['all_phone_numbers'], \Model_Account::getCompanyId());

                if ($duplicate_case_id) {
                    //print '<p>Lead is a duplicate of '.$check_duplicate.'</p>';
                    $recapture = Model_System_DuplicateFilter::attemptRecapture($duplicate_case_id, $status['campaign_id'], $data_as_entered);
                    if ($recapture) {
                        //print '<p>Lead was successfully recaptured</p>';
                        \Notification\Notify::error("Duplicate case detected. You've been redirected to the original case.");
                        return $duplicate_case_id;
                    } else {

                        throw new Exception('Duplicate case detected, did not match recapture rule, ignored');
                    }

                }

            }

            /** Sales Rep Assignment */
            if($auto_assign) {

                // Distribute Lead to next Rep in line
                $rep = Model_Distribution::getNextDistributionRep($status['campaign_id']);
                if (!empty($rep) && $auto_assign == true) {
                    $data['department'] = array('sales_rep' => $rep['user_id']);
                }



            }

            /** Status Assignment */
            // Use Default status if there is none
            if(isset($data['status'])){
                $status_lookup = Model_System_Status::findByName($data['status']);
                $status['status_id'] = $status_lookup['id'];
            }elseif(isset($data['status_id']) && !empty($data['status_id'])){
                $status['status_id'] = $data['status_id'];  // Importing Status
            }else{
                $status['status_id'] = 1;  // New Status
            }



            if(!isset($case['company_id'])){
                throw new Exception('Cannot add case without company ID');
            }



            DB::start_transaction();

            $result = \DB::insert('cases')->set($case)->execute();
            $case_id = current($result);

            $contact['case_id'] = $case_id;
            DB::insert('case_contact')->set($contact)->execute();

            $status['case_id'] = $case_id;
            DB::insert('case_statuses')->set($status)->execute();

            DB::commit_transaction();

            Model_System_Additional::updateAdditional($case_id, $data);

            if(isset($data['department']) && !empty($data['department'])){
                Model_Assignments::upsert($case_id, $data['department']);
            }

            if(!empty($rep)){
                Model_Distribution::logDistributedCase($rep['user_id'], $case_id);
            }

             Model_System_Trigger::fireEventByTrigger('lead.new',$case_id);

            }catch(\Exception $e){

                DB::rollback_transaction();
                throw new \Exception($e->getMessage());
            }


            if(isset($case_id) && !empty($case_id)) {
                return $case_id;
            }

            return false;
            
        }

        /**
         * @param $id
         */
        static function increaseActionCount($id){
            self::update($id, array('action_count' => DB::expr('action_count + 1'), 'last_action' => date('Y-m-d H:i:s')));
        }


        static function sortCaseVars($data){

            $case = $status = $contact = array();

            $case_fields = array('company_id','is_duplicate','action_count','last_action');
            foreach($case_fields as $c){
                if(isset($data[$c])){
                    $case[$c] = $data[$c];
                }
            }

            $status_fields = array('is_company','status_id','campaign_id','docs_status','is_client','is_delete', 'accounting_status_id','dialer_id','doc_submission','dialer_updated','status_updated','accounting_updated', 'financed', 'renewal_date','accounting_type','activation_date','termination_date','submission_ready','account_type_id','doc_signed','poi_id');
            foreach($status_fields as $sf){
                if(isset($data[$sf])){
                    switch($sf){
                        case 'doc_submission':
                            if(empty($data['doc_submission'])){
                                $status[$sf] = NULL;
                                continue 2;
                            }
                          //  if(\Formatter\Date::validateDate($data['doc_submission']) == true) {
                                $status[$sf] = date('Y-m-d', strtotime($data[$sf]));
                           // }
                            break;
                        case 'renewal_date':
                            if(empty($data['renewal_date'])){
                                $status[$sf] = NULL;
                                continue 2;
                            }
                            //if(\Formatter\Date::validateDate($data['renewal_date']) == true) {
                                $status[$sf] = date('Y-m-d', strtotime($data[$sf]));
                           // }
                            break;


                        default:
                            $status[$sf] = $data[$sf];
                            break;
                    }
                }
            }

            $contact_fields = array('first_name','last_name','middle_name','email','primary_phone', 'secondary_phone','mobile_phone', 'ssn','dob','fax','address','address_2','city','state','zip','timezone', 'country','title','dpp_contact_id','credit_score','direct_mail_id','vendor_id','ip_address');
            foreach($contact_fields as $cf) {

                if(isset($data[$cf])){
                    switch($cf){
                        case 'dob':
                            if(empty($data['dob'])){
                                $contact[$cf] = NULL;
                                continue 2;
                            }
                            $contact[$cf] = date('Y-m-d',strtotime($data[$cf]));
                            break;
                        case 'email':
                            $contact[$cf] = (filter_var(trim($data['email']), FILTER_VALIDATE_EMAIL) == true ? $data['email'] : null);
                            break;
                        default:
                            $contact[$cf] = $data[$cf];
                            break;
                    }
                }

            }

            return array($case, $status, $contact);
        }

        /**
         * @param $id
         * @param $data
         */
        static function update_($id, $data, $dupe_filter=false){

            $case = array();
            $status = array();
            $contact = array();


            list($case, $status, $contact) = self::sortCaseVars($data);

            if(isset($data['campaign'])){
                $campaign = Model_System_Campaign::findByName($data['campaign']);
                if($campaign){
                    $data['campaign_id'] = $campaign['id'];
                }
            }

            $all_phone_numbers = array();
            $phone_fields = array('primary_phone','secondary_phone','mobile_phone');
            foreach($phone_fields as $f){
                if(isset($data[$f])){
                    $regex = "/^(\d[\s-]?)?[\(\[\s-]{0,2}?\d{3}[\)\]\s-]{0,2}?\d{3}[\s-]?\d{4}$/i";
                    $number = preg_replace('/[^0-9]/','', $data[$f]);
                    $contact[$f] =  (preg_match( $regex, $number ) ? $number : null);
                    if(!empty($contact[$f])){
                        $all_phone_numbers[] = preg_replace('/[^0-9]/','', $contact[$f]);
                    }
                }
            }

            if($dupe_filter) {
                // CHECK DUPLICATE LEAD
                $check_duplicate = Model_System_DuplicateFilter::checkDuplicateCase($id, $all_phone_numbers);
                if ($check_duplicate) {
                    throw new Exception('Duplicate Phone Number.');
                }
            }


            $log = array();

            if(isset($case) && !empty($case)){

                \DB::update('cases')->set($case)->where('id','=',$id)->execute();
            }

            if(isset($status) && !empty($status)){

                \DB::update('case_statuses')->set($status)->where('case_id','=',$id)->execute();
            }

            if(isset($contact) && !empty($contact)){

                $form_fields = Model_System_Form_Fields::findAllByObject(1);
                foreach($form_fields as $ob_field){
                    $field_names[$ob_field['clean_name']] = $ob_field['name'];
                    $field_ids[$ob_field['clean_name']] = $ob_field['id'];
                }
                $case_contact = current(DB::select()->from('case_contact')->where('case_id','=',$id)->execute()->as_array());
                foreach($contact as $k => $case_value){
                    if(isset($case_contact[$k]) && $case_contact[$k] != $case_value){
                        if(isset($field_names[$k])) {
                            if(empty($case_value) || $case_value==''){
                                $log[] = array($field_names[$k], 'delete', $case_contact[$k], $field_ids[$k]);
                            }else {
                                $log[] = array($field_names[$k], 'update', $case_value, $field_ids[$k]);
                            }
                        }
                    }
                }

                \DB::update('case_contact')->set($contact)->where('case_id','=',$id)->execute();
            }

            if(!empty($log)){
                \Model_Case_Interview_Log::addMany($log, $id);
            }

            Model_System_Additional::updateAdditional($id, $data);

            if(isset($data['department']) && !empty($data['department'])){
               Model_Assignments::upsert($id, $data['department']);
            }


            return true;

        }


        static function updateStatusOnce($id, $status_id, $status_field = NULL){

            $status = Model_System_Status::find($status_id);
            $case_status = Model_Case_Status::find($id);

            if(!isset($status_field) || empty($status_field)){
                $status_field = 'status_id';
            }


            if($case_status['status_id'] == $status_id){
                // Same status, ignore
                throw new \Exception('Case already in status');

            }

            $type='Status';

            switch($status_field){

                case 'status_id':
                    $update['status_updated'] = date('Y-m-d H:i:s');
                    $type = 'Status';
                    break;

                case 'dialer_id':
                    $update['dialer_updated'] = date('Y-m-d H:i:s');
                    /* Dont store in activity */
                    $type = 'Dialer';
                    break;

                case 'accounting_status_id':
                    $update['accounting_updated'] = date('Y-m-d H:i:s');
                   $type = 'Payment';
                    break;

            }

            $update[$status_field] = $status_id;

            // Update Status
            \DB::update('case_statuses')->set($update)->where('case_id','=',$id)->execute();

            Model_Log::log_status($id,$status_id, \Model_Account::getUserId());
            if($status_id != 1) {
                Model_Log::addActivity($id, $type, $status['name'], '', \Model_Account::getUserId(), $status_id);
            }

        }

        /**
         * @param $id
         * @param $status_id
         */
        static function updateStatus($id, $status_id, $status_field = NULL){

            $status = Model_System_Status::find($status_id);
            $case_status = Model_Case_Status::find($id);

            if(!isset($status_field) || empty($status_field)){
                $status_field = 'status_id';
            }


            if($case_status['status_id'] == $status_id){
                // Same status, ignore
                throw new \Exception('Case already in status');

            }

            $type='Status';

            switch($status_field){

                case 'status_id':
                    $update['status_updated'] = date('Y-m-d H:i:s');
                    $type = 'Status';
                    break;

                case 'dialer_id':
                    $update['dialer_updated'] = date('Y-m-d H:i:s');
                    /* Dont store in activity */
                    $type = 'Dialer';
                    break;

                case 'accounting_status_id':
                    $update['accounting_updated'] = date('Y-m-d H:i:s');
                    $type = 'Payment Plan';


                    switch($status_id){
                        case 200:
                            $status['name'] = 'ACTIVE';
                            break;
                        case 9:
                            $status['name'] = 'HOLD';
                            break;
                        case 5:
                            $status['name'] = 'NSF';
                            break;

                    }

                    break;

            }

            $update[$status_field] = $status_id;

            // Update Status
            \DB::update('case_statuses')->set($update)->where('case_id','=',$id)->execute();

            Model_Log::log_status($id,$status_id, \Model_Account::getUserId());

            if($status_id != 1) {
                Model_Log::addActivity($id, $type, $status['name'], '', \Model_Account::getUserId(), $status_id);
            }

            // Run Status Action Tasks
            if($status_field == 'status_id' && $status_id !== 1){
                // Run Action
                if(isset($status['action_id']) && !empty($status['action_id'])){
                    $action = new \Model_Action($id, $status['action_id']);
                    $action->run();

                }
            }

        }


        /**
         * @param $data
         * @param $case_id
         * @return bool|mixed
         */

        static function import($data, $case_id){
            if(empty($case_id)){
                return self::add($data, true);
            }else{
                return self::update($data, $case_id);
            }
        }


        /**
         * @param $case_ids
         * @param $rep_user_id
         * @return string
         * @throws Exception
         */
        static function assignToRep($case_id, $rep_user_id){
            
            if(empty($case_id)){
                return false;
            }
            
            $user = Model_System_User::find($rep_user_id);
            
            if(empty($user)){
                throw new Exception("Can't find user ID ".$rep_user_id);
            }
            // TODO update to not use static sales_rep but user
           // self::batchUpdate($case_ids, array('department' => array('sales_rep' => $rep_user_id)));
            self::update($case_id, array('department' => array('sales_rep' => $rep_user_id)));

            return true;

            //return count($case_ids). ' case'.(count($case_ids)==1?' was':'s were').' reassigned to REP: '.$user['first_name'].' '.$user['last_name'];
            
        }

        /**
         * @param $case_ids
         * @param $campaign_id
         * @return string
         * @throws Exception
         */
        static function assignToCampaign($case_id, $campaign_id){
            
            if(empty($case_id)){
                return false;
            }
            
            $campaign = Model_System_Campaign::find($campaign_id);
            
            if(empty($campaign)){
                throw new Exception("Can't find Campaign ID ".$campaign_id);
            }
            //self::batchUpdate($case_ids, array('campaign_id' => $campaign_id, 'campaign' => $campaign['name']));
            self::update($case_id, array('campaign_id' => $campaign_id, 'campaign' => $campaign['name']));

            return true;
            //return count($case_ids). ' case'.(count($case_ids)==1?' was':'s were').' reassigned to campaign: '.$campaign['name'];
            
        }

        /**
         * @param $case_ids
         * @param $data
         */
        static function batchUpdate($case_ids, $data){

            if(empty($case_ids)){
                return;
            }
            
            if(!is_array($case_ids)){
                $case_ids = array($case_ids);
            }

           /* $db_fields = self::getDBFields();

            $db_update = array();
            foreach($db_fields as $field){
                if(isset($data[$field])){
                    $db_update[$field] = $data[$field];
                    unset($data[$field]);
                }
            }

            if(!empty($db_update)){
                DB::update('cases')->set($db_update)->where('id', 'in', $case_ids)->execute();
            }*/

            if(!empty($data)){

                foreach($case_ids as $k => $id){
                    self::update($id, $data);
                    $case_ids[$k] = (int)$id;
                }


            }
            
        }

        /**
         * @param $id
         * @return bool
         */
        static function duplicateFilter($id){
            
            $new = self::find($id);
            
            //find phone fields
            $fields = Model_System_Form_Fields::findByType(1, 10);
            $select_fields = array();
            foreach($fields as $f){
                $select_fields[] = $f['clean_name'];
            }

            foreach($fields as $f){
                foreach($select_fields as $sfv){
                    if(!empty($new[$sfv])){
                        $cond = array($f['clean_name'] => (string)$new[$sfv]);

                    }
                }
            }

            $matches = \DB::select()->from('cases')->where('id','=',$id)->execute()->as_array();
            
            $matched_ids = array();
            foreach($matches as $m){
                if($m['id'] != $id){
                    $matched_ids[] = $m['_id'];
                }
            }
            
            if(!empty($matched_ids)){
                //self::update($id, array('duplicates' => $matched_ids, 'status' => 'Duplicate'));
                self::update($id, array('is_duplicate' => 1));
                return true;
            }
            
            return false;

        }
        /**
         * @return array
         */
        static function findDuplicates(){
            
            $result = \DB::select()->from('cases')->where('is_duplicate', '=', 1)->order_by('id','desc')->limit(100)->execute();
            return $result->as_array();
            
        }
        // TODO update this to reflect new invoice system
        static function getTotalFees($case_id){
            
            $case = self::find($case_id);
            $fee_fields = Model_System_Form_Fields::findFeeFields(1);
            
            $fees = array();
            foreach($fee_fields as $f){
                if(isset($case[$f['clean_name']]) && is_numeric($case[$f['clean_name']])){
                    $fees[] = $case[$f['clean_name']];
                }
            }
            
            return array_sum($fees);
            
        }

        /**
         * @param $data
         * @throws Exception
         */

        static function fileImport($data){


            try {
                if (in_array($data['action'], array('update_case', 'update_phone', 'update_vendor'))) {
                    self::caseUpdatestoQueue($data, $data['action']);
                } else {
                    self::fileImportQueue($data);
                }
            }catch(\Exception $e){
                throw new $e;
            }

        }




        static function caseUpdatestoQueue($data,$match_type=null){


            set_time_limit(1800);

            $import_file_path = Config::get('import_folder');
            $handle = fopen($import_file_path.$data['import_file'], 'r');

            if($handle === false){
               print "Couldn't open import file"; exit;
            }

            // Create Record in memory to store issues, report import

            $duplicates = 0;
            $errors = array();
            $current_line = 1;
            $queue = new Model_Queue();

            while (($line = fgetcsv($handle)) !== FALSE) {

                if($current_line==1){
                    // Headers
                    $current_line++;
                    continue;
                }

                $current_line++;

                $values = array(); //  CSV Mapped Values
                foreach($data['cols'] as $c => $v){
                    if(isset($v) && !empty($v)){
                        $values[$v] = $line[$c];
                    }
                }

                list($case, $status, $contact) = self::sortCaseVars($values);

                $assignment_fields = array(
                    'sales_rep_id' => 8
                );
                foreach($assignment_fields as $key => $field){
                    if(isset($values[$key])){
                        $assignments[$field] = $values[$key];
                    }
                }

                // Start Job Build
                $job = new stdClass();
                $job->type = 'update';
                $job->case = $case;
                $job->contact = $contact;
                $job->status = $status;
                $job->additional = $values;
                $job->company_id = $data['company_id'];

                if($match_type) {
                    switch($match_type){

                        case 'update_case':
                            break;

                        case 'update_phone':
                            $job->match_type = 'primary_phone';
                            $job->match_id = $case['primary_phone'];
                            break;

                        case 'update_vendor':
                            $job->match_type = 'vendor_id';
                            $job->match_id = $contact['vendor_id'];
                            break;
                    }
                }

                if(isset($assignments)){
                    $job->assignments = $assignments;
                }

                if(isset($data['action_id'])){
                    $job->action_id = $data['action_id'];
                }

                if(isset($values['case_id'])){
                    $job->case_id = $values['case_id'];
                }

                $job_data = json_encode($job);
                // Save Job Data
                $queue->putInTube('import', $job_data);

            }

            if($errors){

            }

            \Response::redirect('/system/case/import');



        }

        static function caseUpdatesFromQueue($payload)
        {
            try {

                if (isset($payload->case_id)) {
                    $case_record = self::find($payload->case_id);
                    if (!$case_record) {
                        throw new \Exception('No case found for case Update');
                    }

                    $case_id = $case_record['id'];
                }

                $errors = array();

               // \Model_Log::file('import', var_dump($payload));


                if (isset($payload->match_type) && isset($payload->match_id)) {
                    if ($payload->match_type == 'primary_phone') {
                        // Look up File By Primary Phone
                        $case_id = self::findOneOrFailByPhone($payload->match_id, $payload->company_id);
                       // \Model_Log::file('import', $case_id);
                        if(!$case_id){
                            throw new \Exception('No Phone Match for Lookup on Case Update');
                        }
                    }

                    if ($payload->match_type == 'vendor_id') {
                        // Look up File By Vendor ID
                        $case_id = Model_Case_Contact::findOneOrFailByVendorID($payload->match_id, $payload->company_id);
                       // \Model_Log::file('import', json_encode($case_id));
                        if(!$case_id){
                            throw new \Exception('No Vendor ID Match for Lookup on Case Update');
                        }
                    }
                }

                if (isset($payload->case) && !empty($payload->case)) {
                    $c_result = DB::update('cases')->set((array)$payload->case)->where('id', '=', $case_id)->execute();
                    if (!$c_result) {
                        $errors[] = "Updating cases table was unsuccessful using " . json_encode($payload->case);
                    }
                }
                if (isset($payload->status) && !empty($payload->status)) {
                    $cs_result = DB::update('case_statuses')->set((array)$payload->status)->where('case_id', '=', $case_id)->execute();
                    if (!$cs_result) {
                        $errors[] = "Updating case statuses table was unsuccessful" . json_encode($payload->status);
                    }
                }
                if (isset($payload->contact) && !empty($payload->contact)) {
                    $cc_result = DB::update('case_contact')->set((array)$payload->contact)->where('case_id', '=', $case_id)->execute();
                    if (!$cc_result) {
                        $errors[] = "Updating case contact table was unsuccessful" . json_encode($payload->contact);
                    }
                }

               // Model_Log::file('import_update', print_r($payload->additional, true));

                Model_System_Additional::updateAdditional($case_id, (array)$payload->additional);

                if (isset($payload->assignments) && !empty($payload->assignments)) {
                    Model_Assignments::upsert($case_id, (array)$payload->assignments);
                }

                if (isset($payload->action_id) && !empty($payload->action_id)) {
                    $action = new \Model_Action($case_id, $payload->action_id);
                    $action->run();
                }

            }catch(\Exception $e){
                // Log Exception of Case Update Failing
                \Model_Log::file('import', $e);
            }
            // Log Errors

            if(!empty($errors)){

                // Case Update Could Not Complete Because...

            }

            return true; // Close out Queue Job

        }

        /**
         * @param $data
         * @throws Exception
         */
        static function fileImportQueue($file){
            set_time_limit(1800);
            $import_file_path = \Config::get('import_folder');
            $handle = fopen($import_file_path.$file['import_file'], 'r');

            $bytes = filesize($import_file_path.$file['import_file']);
            $units = array('B', 'KB', 'MB', 'GB', 'TB');
            $bytes = max($bytes, 0);
            $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
            $pow = min($pow, count($units) - 1);
            // Uncomment one of the following alternatives
            $bytes /= pow(1024, $pow);
            // $bytes /= (1 << (10 * $pow));
            $filesize =  round($bytes) . ' ' . $units[$pow];

            \Contacts\Model_File_Imports::update($_SESSION['import']['file_id'], array(
                'filesize' => $filesize,
                'lines' => count(file($import_file_path.$file['import_file']))
            ));


            if($handle === false){
                throw new \Exception("Couldn't open import file");
            }
            
            $campaign_list = Model_System_Campaign::findAll();
            
            $campaigns = array();
            foreach($campaign_list as $c){
                $campaigns[$c['id']] = $c['name'];
            }
            
            $status_list = Model_System_Status::findAll(1);
            
            $statuses = array();
            foreach($status_list as $s){
                $statuses[$s['id']] = $s['name'];
            }
            
            $timezone_list = Model_System_Timezone::findAll();
            
            $timezones = array();
            foreach($timezone_list as $t){
                $timezones[$t['area_code']] = $t['timezone'];
            }
            
            $action_list = Model_System_Action::findSystemAndCompany(\Model_Account::getCompanyId());
            
            $actions = array();
            foreach($action_list as $a){
                $actions[$a['id']] = $a['name'];
            }
            
            $phone_fields = Model_System_Form_Fields::findByType(1, 10);
            
            $duplicates = 0;

            $current_line = 0;
            $passed = 0;
            $errors = array();
            $line_results = array();

            $queue = new \Model_Queue();

            while (($line = fgetcsv($handle)) !== FALSE) {
                
                $data_as_entered = $line;
                
                $current_line++;
                
                $action_id = false;
                
                $db = array('created' => date('Y-m-d H:i:s'));
                $ds = array();
                
                //use the mappings from the map import view
                $values = array();
                foreach($file['cols'] as $c => $v){
                    if(!empty($v)){
                        $values[$v] = $line[$c];
                    }
                }
                $data = $values;
                if(isset($file['company_id'])){
                    $data['company_id'] = $file['company_id'];
                }


                $case = array();
                $status = array();
                $contact = array();
                $assignments = array();

                list($case, $status, $contact) = self::sortCaseVars($data);

                if(!isset($data['status'])){
                    $status['status_id'] = 1;
                }

                $case['created'] = date('Y-m-d H:i:s');
                $status['created'] = date('Y-m-d H:i:s');
                $contact['created'] = date('Y-m-d H:i:s');

                if(isset($data['campaign']) && !empty($file['campaign'])) {
                    //$campaign = Model_System_Campaign::findByName($data['campaign']);
                    $campaign = array_search($values['campaign'], $campaigns);

                    if (isset($campaign)) {
                        // assign to company and account
                        $status['campaign_id'] = $campaign['id'];

                    } else {
                        $errors[] = "No Campaign or Account Match";
                    }

                }elseif(isset($file['campaign_id']) && !empty($file['campaign_id'])) {
                        $status['campaign_id'] = $file['campaign_id'];
                }else{
                        $status['campaign_id'] = 1; // NEW
                }

                $phone_fields = array('primary_phone', 'secondary_phone','mobile_phone');
                foreach($phone_fields as $f){
                    if(isset($data[$f])){
                        $number = preg_replace('/[^0-9]/','', $data[$f]);
                        $contact[$f] = (filter_var($number, FILTER_VALIDATE_INT) == true ? $number : null);

                    }
                }


                // Use Default status if there is none
                if(isset($data['status']) && !empty($data['status'])){
                    $status['status_id'] = array_search($values['status'], $statuses);
                }else{
                    // New Status
                    $status['status_id'] = 1; //array_search('New', $statuses);
                }

                if(isset($contact['primary_phone']) && !empty($contact['primary_phone'])){
                    $area_code = substr(preg_replace('/[^0-9]/','', $contact['primary_phone']), 0, 3);
                    $contact['timezone'] = Model_System_Timezone::findByAreaCode($area_code);
                }

                if(!isset($case['company_id'])){
                    throw new \Exception('No company ID set for file import case');
                }

                // Start Job Build
                $job = new stdClass();
                $job->type = 'import';
                $job->case = $case;
                $job->contact = $contact;
                $job->status = $status;
                $job->import_id = $_SESSION['import']['file_id'];
                $job->line = $current_line;

                //$job->assignments = $assignments;
                //$job->all_numbers = $data['all_phone_numbers'];


                if(isset($data['action_id'])){
                    $job->action_id = $data['action_id'];
                }elseif($file['action_id']){
                    $job->action_id = $file['action_id'];
                }

                $job_data = json_encode($job);
                // Save Job Data
                $queue->putInTube('import', $job_data);

                $passed++;
               
            }


            print '<p>'.$current_line . ' leads added to queue for import</p>';

            print '<p>Passed Validation: '.$passed.'</p>';

            print $filesize;

            fclose($handle);

            exit;
            
        }
        static function fileImportCase($data){

            $phone = null;
            if(isset($phone)) {
                $phone = $data->contact->primary_phone;
            }

            \Model_Log::file('import_payload',json_encode($data));

            if(isset($data->case->company_id) && !empty($data->case->company_id)) {

                try{

                    \DB::start_transaction();
                    $result = \DB::insert('cases')->set((array)$data->case)->execute();
                    $case_id = current($result);
                    $data->contact->case_id = $case_id;
                    \DB::insert('case_contact')->set((array)$data->contact)->execute();
                    $data->status->case_id =$case_id;
                    \DB::insert('case_statuses')->set((array)$data->status)->execute();
                    \DB::commit_transaction();


                    \Contacts\Model_File_Import_Details::add(
                        array('file_import_id' => $data->import_id, 'case_id' => $case_id, 'line' => $data->line, 'created' => date("Y-m-d H:i:s"))
                    );


                }catch(Exception $e){
                    \DB::rollback_transaction();

                    \Contacts\Model_File_Import_Details::add(
                        array('message' => $e->getMessage(), 'file_import_id' => $data->import_id, 'error' => 1, 'line' => $data->line, 'created' => date("Y-m-d H:i:s"))
                    );

                    return $e->getMessage();

                }

                if($case_id){
                    return 'Case ID: '.$case_id. ' Imported';
                }else{
                    \Contacts\Model_File_Import_Details::add(
                        array('message' => 'No company ID', 'file_import_id' => $data->import_id, 'error' => 1, 'line' => $data->line, 'created' => date("Y-m-d H:i:s"))
                    );
                    return $phone.' No company id or numbers';
                }

            }

            return $phone.' No company id or numbers';



        }
        /**
         * @param $factory
         * @return mixed
         */
        static function validate($factory){
            
            $val = \Validation::forge($factory);

            return $val;
        }
        /**
         * @param $cases
         * @param $group_id
         */
        static function distributeToGroup($case_id, $group_id, $department_id){

            // Get next user in distribution list
            $rep = Model_BatchDistribution::getNextUserGroup($group_id);
            // Update the records for distribution
            Model_Assignments::batch_upsert($case_id, $department_id, $rep['user_id']);
            //Log
            Model_BatchDistribution::logDistribution($rep['user_id'], $case_id);
            Model_BatchDistribution::saveLog();

            return true;
            
        }

        /**
         * @param $cases
         * @return mixed
         */
        static function findDBFields($cases){
            
            if(!is_array($cases)){
                $cases = array($cases);
            }
            
            $result = \DB::select()->from('cases')->where('id', 'IN', $cases)->execute();

            return $result->as_array();
            
        }

        static function saveHistory($case){

            if(isset($_SESSION['recentCases'])){

                if (!in_array($case['id'], $_SESSION['recentCases']))
                {
                    $count = count($_SESSION['recentCases']);
                   if($count>=5){
                       array_shift($_SESSION['recentCases']);
                       $_SESSION['recentCases'][] = $case['id'];
                   }else{
                       $_SESSION['recentCases'][] = $case['id'];
                   }

               }

            }else{
                $_SESSION['recentCases'][] = $case['id'];
            }
        }
        static function getCaseObjects($case_id){
            // Alerts
            // Calls
            // Notes
            // History
            // Emails
            // Billing
            // Invoices
            // Payments
            // Scheduled Payments

            $case = self::find($case_id);

            $company = Model_System_Company::find($case['company_id']);

            $case_additional = \Model_System_Additional::find($case_id);
            $case_fields = $case;
            if(isset($case_additional) && !empty($case_additional)){
                $case_fields = array_merge($case, $case_additional);
            }
            $financing = new \Financing\Model_Financing();
            $financing_record = $financing->findByCaseId($case_id);
            if(isset($financing_record) && !empty($financing_record)){
                foreach ($financing_record as $k => $v) {
                    $case_fields['financing_'.$k] = $v;
                }
            }

            if(isset($case['ssn']) && !empty($case['ssn'])){
                $case_fields['ssn_secure'] = 'XXXX'.substr($case['ssn'], -4);
            }

            if(isset($_SESSION['user'])){
               $case_fields = array_merge($case_fields, \Model_Account::getCurrentUserVars());
            }

            // Assignments on File by Department
            $assignments = Model_Assignments::findByCase($case_id);
            if($assignments){
                foreach ($assignments as $assignment) {
                    foreach($assignment as $k => $v){
                        $case_fields['dept_'.$assignment['department_clean_name'].'_'.$k] = $v;
                    }
                }
            }

            $student_program = \Student\Model_Programs::findByCase($case_id);
            if($student_program) {
                foreach ($student_program as $spk => $spv) {
                    $case_fields['student_programs_'.$spk] = $spv;
                    if($spk == 'total_amount'){
                        $case_fields['student_programs_'.$spk] = '$'.number_format($spv,2);
                    }
                }

            }

            // Get Standard Plan
            $student_quote_standard =  \Student\Model_Qualification::findByCaseQuote($case_id,5);

            \Model_Log::append('quote',json_encode($student_quote_standard), $case_id);
            if($student_quote_standard){
                $case_fields['student_quote_standard_term'] = $student_quote_standard['term'];
                $case_fields['student_quote_standard_term_years'] = $student_quote_standard['term_years'];
                $case_fields['student_quote_standard_amount'] = $student_quote_standard['amount'];
                $case_fields['student_quote_standard_total_interest'] = $student_quote_standard['total_interest'];
                $case_fields['student_quote_standard_total_amount'] = $student_quote_standard['total_amount'];
            }

            //$case_fields['student_quote_standard_term'] = 10;

            if($company) {
                foreach ($company as $k => $v) {
                    $case_fields['company_' . $k] = $v;
                }
                $case_fields['company_full_address'] = $company['office_address'] . ' ' . $company['office_address_2'] . ' ' . $company['office_city'] . ', '. $company['office_state'] . ' ' . $company['office_zip'];
            }

            if(isset($company['logo']) && !empty($company['logo'])){
                $case_fields['company_logo_html'] = '<img src="https://s3-us-west-1.amazonaws.com/student-advocates-crm/logos/company/'.$company['logo'].'" title="'.$company['long_name'].'"  />';
            }

            // Student Loans
            $loans = \Student\Model_Loans::findByCaseID($case_id, 1, 1);
            if($loans){
                $case_fields['student_loans_table'] = \View::forge('student::loans/html_table',array('loans' => $loans))->render();
            }


            $case_fields['full_name'] = $case['first_name'] . ' ' . $case['last_name'];
            $initial_schedule = \Accounting\Model_Payment_Schedules::findByCaseAndType($case_id, 3);
            $downpayment_schedule = \Accounting\Model_Payment_Schedules::findByCaseAndType($case_id, 12);
            $downpayment_total = \Accounting\Model_Payment_Schedules::findTotalByCaseAndType($case_id, 12);

            if($downpayment_schedule){
                foreach($downpayment_schedule as $k=>$v){
                    $case_fields['downpayment_schedule_'.$k] = $v;
                }
            }
            if($downpayment_total){

                $case_fields['downpayment_total'] = $downpayment_total['total'];
                /**** STuDENT ONLY ****/
                $case_fields['total_financed'] = number_format((800 + $case_fields['downpayment_total']), 2);
            }


            // Student Quotes
            $student_quotes = \Student\Model_Qualification::findActiveByCase($case_id);
            if($student_quotes){
                foreach($student_quotes as $k=>$v){
                    $case_fields['student_quotes_'.$k] = $v;
                }
            }

            $monthly_schedule = \Accounting\Model_Payment_Schedules::findByCaseAndType($case_id, 5);
            if($monthly_schedule){
                foreach($monthly_schedule as $k=>$v){
                    $case_fields['monthly_schedule_'.$k] = $v;
                }
                if(isset($monthly_schedule['date_due'])){
                    $case_fields['monthly_schedule_suffix'] = date('dS', strtotime($monthly_schedule['date_due']));
                }
            }

            // Format Birthday
            if(isset($case['dob']) && !empty($case['dob'])) {
                $case_fields['dob'] = date("m/d/Y", strtotime($case['dob']));
                $case_fields['dob_shortyear'] = date("m/d/y", strtotime($case['dob']));
            }

            $case_fields['total_pending'] = \Accounting\Model_Payment_Schedules::getTotalPendingPayments($case_id);

            $first_schedule_item = \Accounting\Model_Payment_Schedules::findByCaseAndType($case_id, 3);
            $monthly_schedule_item = \Accounting\Model_Payment_Schedules::findByCaseAndType($case_id, 1);
            $recurring_schedule_item = \Accounting\Model_Payment_Schedules::findByCaseAndType($case_id, 2);
            $payment_schedule = \Accounting\Model_Payment_Schedules::findPendingByCaseID($case_id);
            $monthly_payment_schedules = \Accounting\Model_Payment_Schedules::findAllByCaseAndType($case_id, 5);
            $downpayment_schedules = \Accounting\Model_Payment_Schedules::findAllByCaseAndType($case_id, 12);

            $tradelines = \CreditReport\Model_Tradelines::findAllByCaseId($case_id);
            if(isset($tradelines)){
                $i=1;
                foreach($tradelines as $tradeline){
                    $case_fields['tradeline_'.$i.'_account_number'] = $tradeline['account_number'];
                    $case_fields['tradeline_'.$i.'_balance'] = $tradeline['balance'];
                    $case_fields['tradeline_'.$i.'_firm_name'] = $tradeline['firm_name'];
                    $i++;
                }
            }


            $invoices = \Accounting\Model_Invoice::findByCaseID($case_id);

            if(isset($invoices)){
                $i=1;
                foreach($invoices as $inv){

                    $case_fields['invoice_'.$i.'_total'] = $inv['total'];
                    $case_fields['invoice_'.$i.'_created'] = $inv['created'];
                    $case_fields['invoice_'.$i.'_status'] = $inv['status'];

                    $i++;
                }
            }

            $last_payment = \Accounting\Model_Payments::findlastOne($case_id);
            if($last_payment){
                $case_fields['last_payment_amount'] = number_format($last_payment['amount'], 2);
                $case_fields['last_payment_date'] = \Formatter\Format::relative_date($last_payment['created'],'m/d/Y');
                $case_fields['last_payment_status'] = $last_payment['status_name'];
            }

            /* LOANS */
            $case_fields['fed_loan_balance'] = (isset($program['total_amount'])?$program['total_amount']:'');
            $case_fields['new_loan_payment'] = (isset($program['payment'])?$program['payment']:'');

            /* ACCOUNTING */
            $case_fields['company_program_payment'] = (isset($initial_invoice['amount'])?$initial_invoice['amount']:'');
            $case_fields['service_for_fees'] = 'Loan Consolidation';
            $case_fields['fee'] = (isset($recurring_invoice['amount'])?$recurring_invoice['amount']:'');

            $case_fields['first_monthly_payment_date'] = (isset($monthly_schedule_item['date_due'])?$monthly_schedule_item['date_due']:'');
            $case_fields['recurring_date'] = (isset($recurring_schedule_item['date_due'])?$recurring_schedule_item['date_due']:'');
            $case_fields['service_agreement_fee'] = (isset($initial_invoice['amount'])?$initial_invoice['amount']:'');
            $case_fields['service_agreement_fee_monthly'] = (isset($recurring_invoice['amount'])?$recurring_invoice['amount']:'');

            $case_fields['monthly_fee'] =  $case_fields['service_agreement_fee_monthly'];
            $case_fields['initial_fee'] =   $case_fields['service_agreement_fee'];

            $case_fields['program_fee_bank_name'] = (isset($first_schedule_item['bank_name'])?$first_schedule_item['bank_name']:'N/A');
            $case_fields['bank_name'] = $case_fields['program_fee_bank_name'];
            $case_fields['program_fee_account_type'] = (isset($first_schedule_item['type'])?$first_schedule_item['type']:'N/A');
            $case_fields['account_type'] = $case_fields['program_fee_account_type'];
            $case_fields['program_fee_account_number'] = (isset($first_schedule_item['account_number'])?$first_schedule_item['account_number']:'N/A');
            $case_fields['account_number'] = $case_fields['program_fee_account_number'];
            $case_fields['program_fee_routing_number'] = (isset($first_schedule_item['routing_number'])?$first_schedule_item['routing_number']:'N/A');
            $case_fields['routing_number'] = $case_fields['program_fee_routing_number'];
            $case_fields['fee1_start_date'] = (isset($first_schedule_item['date_due'])?$first_schedule_item['date_due']:'');
            $case_fields['fee1_total'] = (isset($initial_invoice['amount'])?$initial_schedule['amount']:'');

            $case_fields['initial_fee_date'] =$case_fields['fee1_start_date'];
            $case_fields['initial_fee_total'] =$case_fields['fee1_total'];
            $case_fields['current_date'] = date('m/d/Y');

            /* Billing */
            $case_fields['monthly_fee_bank_name'] = (isset($recurring_schedule_item['bank_name'])?$recurring_schedule_item['bank_name']:'N/A');
            $case_fields['monthly_fee_account_type'] =  (isset($recurring_schedule_item['type'])?$recurring_schedule_item['type']:'N/A');
            $case_fields['monthly_fee_account_number'] = (isset($recurring_schedule_item['account_number'])?$recurring_schedule_item['account_number']:'N/A');
            $case_fields['monthly_fee_routing_number'] = (isset($recurring_schedule_item['routing_number'])?$recurring_schedule_item['routing_number']:'N/A');
            $case_fields['fee2_start_date'] = (isset($monthly_schedule_item['date_due'])?$monthly_schedule_item['date_due']:'');
            $case_fields['fee2_total'] = (isset($recurring_invoice['amount'])?$recurring_invoice['amount']:'');

            $case_fields['name_on_card'] = (isset($first_schedule_item['name_on_card'])?$first_schedule_item['name_on_card']:'N/A');
            $case_fields['cc_billing_address'] = (isset($first_schedule_item['billing_street'])?$first_schedule_item['billing_street']:'N/A');
            $case_fields['credit_card_type'] = (isset($first_schedule_item['card_type'])?$first_schedule_item['card_type']:'N/A');
            $case_fields['credit_card_type'] = (isset($first_schedule_item['card_type'])?$first_schedule_item['card_type']:'N/A');
            $case_fields['invoice_number'] = (isset($initial_invoice['id'])?$initial_invoice['id']:'');
            $case_fields['services_provided'] = (isset($initial_invoice['invoice_type'])?$initial_invoice['invoice_type']:'');
            $case_fields['services_provided_l2'] = (isset($recurring_invoice['invoice_type'])?$recurring_invoice['invoice_type']:'');


            $next_payment = \Accounting\Model_Payment_Schedules::findNextPendingPayment($case_id);

            if($next_payment){
                // Pending Payment based on next in line to current date
                $case_fields['next_payment_date'] = date('m/d/y', strtotime($next_payment['date_due']));
                $case_fields['next_payment_amount'] = $next_payment['amount'];
                $case_fields['next_payment_billing_title'] = $next_payment['title'];
                $case_fields['next_payment_billing_type'] = $next_payment['type'];
                $case_fields['next_payment_billing_bank_name'] = $next_payment['bank_name'];
                $case_fields['next_payment_billing_routing_number'] = (isset($next_payment['routing_number'])? 'XXXXX'.substr($next_payment['routing_number'],-4): '');
                $case_fields['next_payment_billing_account_number'] = (isset($next_payment['account_number'])? 'XXXXX'.substr($next_payment['account_number'],-4): '');
                $case_fields['next_payment_billing_name_on_account'] = $next_payment['name_on_account'];
                $case_fields['next_payment_billing_name_on_card'] = $next_payment['name_on_card'];
            }

            $case_fields['day'] = date('d');
            $case_fields['month'] = date('F');
            $case_fields['year'] = date("Y");
            $case_fields['year_short'] = date("y");

            $cc = \Billing\Model_CreditCard::findActiveByCaseId($case_id);
            if($cc){
                foreach($cc as $k=>$v){
                    $case_fields['cc_'.$k] = $v;
                }

                $case_fields['cc_full_address'] = $cc['billing_street'] . ' ' . $cc['billing_street_2'] . ' ' . $cc['billing_city'] . ', '. $cc['billing_state'] . ' ' . $cc['billing_zip'];

                if(isset($cc['credit_card_number'])){
                    $case_fields['cc_last_four'] = 'XXXX'.substr($cc['credit_card_number'],-4);
                }
            }

            $ach = \Billing\Model_Banks::findActiveByCaseID($case_id);

            if($ach){
                $field_set = array('id','case_id','type','title','name_on_account','bank_name',
                    'routing_number','account_number','billing_street','billing_street_2','billing_city','billing_state','billing_zip',
                    'created','verify_transaction_id','verify_details');
                foreach($ach as $k=>$v){
                    $case_fields['ach_'.$k] = $v;
                }
                $case_fields['ach_full_address'] = $ach['billing_street'] . ' ' . $ach['billing_street_2'] . ' ' . $ach['billing_city'] . ', '. $ach['billing_state'] . ' ' . $ach['billing_zip'];

                if(isset($ach['account_number'])){
                    $case_fields['ach_last_four'] = 'XXXX'.substr($ach['account_number'],-4);
                }
                if(isset($ach['routing_number'])){
                    $case_fields['routing_last_four'] = 'XXXX'.substr($ach['routing_number'],-4);
                }
            }

            $case_fields['case_email'] = 'case+'.$case['id'].'@'.$company['case_email_domain'];

            // 60 PAYMENTS MAXMIMUM LISTED AND COUNTED

            if($payment_schedule){
                $i = 1;
                $total = 0;
                foreach($payment_schedule as $ps){
                    if($i >= 60){
                        continue;
                    }
                    $total += $ps['amount'];
                    $case_fields['pay_date_'.$i] = (isset($ps['date_due'])?date("m/d/Y", strtotime($ps['date_due'])):$ps['date_due']);
                    $case_fields['pay_amt_'.$i] = '$'.number_format($ps['amount'],2);
                    $i++;
                }
                $case_fields['pay_total'] = $total;
            }

            if($monthly_payment_schedules){
                $i = 1;
                $total = 0;
                foreach($monthly_payment_schedules as $mps){
                    if($i >= 60){
                        continue;
                    }
                    $total += $mps['amount'];
                    $case_fields['monthly_date_'.$i] = (isset($mps['date_due'])?date("m/d/Y", strtotime($mps['date_due'])):$mps['date_due']);
                    $case_fields['monthly_amount_'.$i] = number_format($mps['amount'],2);
                    $i++;
                }
                $case_fields['monthly_total'] = $total;
                $case_fields['monthly_count'] = count($monthly_payment_schedules);
            }

            if($downpayment_schedules){
                $i = 1;
                $total = 0;
                foreach($downpayment_schedules as $dps){
                    if($i >= 60){
                        continue;
                    }
                    $total += $dps['amount'];
                    $case_fields['downpayment_date_'.$i] = (isset($dps['date_due'])?date("m/d/Y", strtotime($dps['date_due'])):$dps['date_due']);
                    $case_fields['downpayment_amount_'.$i] = number_format($dps['amount'],2);
                    $i++;
                }

                $case_fields['downpayment_total'] = $total;
                $case_fields['downpayment_count'] = count($downpayment_schedules);

            }

            $case_fields['case_id'] = $case_fields['id'] = $case_id;


            return $case_fields;


        }
        static function convertColumnsToReadable($cases, $columns){

            $docs_status = array(
                'Not Sent' => array('class' => 'default', 'icon' => 'fa fa-file-o'),
                'Sent' => array('class' => 'warning', 'icon' => 'fa fa-paper-plane fa-inverse '),
                'Signed' => array('class' => 'success', 'icon' => 'fa fa-file-text fa-inverse '),
                'Canceled' => array('class' => 'danger', 'icon' => 'fa fa-bomb fa-inverse'),
            );

            $accounting_status_id = array(
                0 => array('class' => 'success', 'title' => 'Active'),
                1 => array('class' => 'warning', 'title' => 'Paused'),
                2 => array('class' => 'danger',  'title' => 'NSF')
            );

            $standing = array(
                0 => array('lead','default'),
                1 => array('client','success'),
                2 => array('terminated','danger')
            );
            $payload = array();
            $count = 0;
            foreach($cases as $c) {
                foreach ($columns as $column) {
                    $item = '--';
                    if (isset($c[$column['clean_name']])){

                        if (!is_null($c[$column['clean_name']]) && !empty($c[$column['clean_name']])) {
                            switch ($column['clean_name']) {
                                case 'created':
                                    $item = \Formatter\Format::relative_date($c[$column['clean_name']], 'Y-m-d H:i:s',false);
                                    break;
                                case 'company':
                                    $item = $c['company'];
                                    break;
                                case 'primary_phone':
                                    $item = \Formatter\Format::phone($c[$column['clean_name']]);
                                    break;
                                case 'financed':
                                    $item = ($c['financed'] == 1 ? 'Yes' : '--');
                                    break;
                                case 'last_action':
                                    $item = \Formatter\Format::relative_date($c[$column['clean_name']], 'Y-m-d H:i:s', false);
                                    break;
                                case 'signed_date':
                                    $item = \Formatter\Format::relative_date($c[$column['clean_name']], 'Y-m-d H:i:s',false);
                                    break;
                                case 'is_client':
                                    $item = $standing[$c['is_client']][0];
                                    break;
                                case 'accounting_status':
                                    $item = $c['accounting_status'];
                                    break;
                                default:
                                    $item = $c[$column['clean_name']];
                                    break;
                            }

                        }
                    }
                    $payload[$count][$column['clean_name']] = $item;
                }
                $count++;
            }

            return $payload;

        }


        private function merge_tags(){

        }
        static function convertCaseColumnsToReadable($c, $columns){

            $docs_status = array(
                'Not Sent' => array('class' => 'default', 'icon' => 'fa fa-file-o'),
                'Sent' => array('class' => 'warning', 'icon' => 'fa fa-paper-plane fa-inverse '),
                'Signed' => array('class' => 'success', 'icon' => 'fa fa-file-text fa-inverse '),
                'Canceled' => array('class' => 'danger', 'icon' => 'fa fa-bomb fa-inverse'),
            );

            $accounting_status_id = array(
                0 => array('class' => 'success', 'title' => 'Active'),
                1 => array('class' => 'warning', 'title' => 'Paused'),
                2 => array('class' => 'danger',  'title' => 'NSF')
            );

            $standing = array(
                0 => array('lead','default'),
                1 => array('client','success'),
                2 => array('terminated','danger')
            );
            $payload = array();
            $count = 0;

            foreach ($columns as $column) {
                $item = '--';
                if (isset($c[$column['clean_name']])){

                    if (!is_null($c[$column['clean_name']]) && !empty($c[$column['clean_name']])) {
                        switch ($column['clean_name']) {
                            case 'created':
                                $item = \Formatter\Format::relative_date($c[$column['clean_name']], 'Y-m-d H:i:s',false);
                                break;
                            case 'company':
                                $item = $c['company'];
                                break;
                            case 'primary_phone':
                                $item = \Formatter\Format::phone($c[$column['clean_name']]);
                                break;
                            case 'financed':
                                $item = ($c['financed'] == 1 ? 'Yes' : '--');
                                break;
                            case 'last_action':
                                $item = \Formatter\Format::relative_date($c[$column['clean_name']], 'Y-m-d H:i:s',false);
                                break;
                            case 'signed_date':
                                $item = \Formatter\Format::relative_date($c[$column['clean_name']], 'Y-m-d H:i:s',false);
                                break;
                            case 'is_client':
                                $item = $standing[$c['is_client']][0];
                                break;
                            case 'accounting_status':
                                $item = $c['accounting_status'];
                                break;
                            default:
                                $item = $c[$column['clean_name']];
                                break;
                        }

                    }
                }
                $payload[$column['clean_name']] = $item;
            }
            return $payload;

        }
        static function writeCSV($list_id){

            $list = current(\DB::select()->from('lists')->where('id','=',$list_id)->execute()->as_array());
            if(!isset($list) || empty($list)){
                print 'Cannot find list '.$list_id;
                die();
            }
            $filter = json_decode($list['filters'],true);

            $columns = \Model_System_Listfields::findByFilter(array('list_id' => $list_id), array(
                'of.name',
                'of.clean_name',
                'of.sortable',
                'of.sort_name'
            ));

            $column_header=array();
            foreach($columns as $col){
                $column_header[$col['clean_name']] = $col['name'];
            }

            $offset = 0;
            $paginate = 7000;
            $sort_field = 'c.id';
            $order = 'desc';

            //open the file stream
            $file = @fopen('/tmp/contacts-'.date('Y_m_d_H_i_s').'.csv', 'w' );
            $sch = @fopen('/tmp/schedules-'.date('Y_m_d_H_i_s').'.csv', 'w' );

            fputcsv($file, $column_header);

            $case_ids = \Model_Case::findByFilter($filter, $offset, $paginate, $sort_field, $order, false, array());
            $payload = array();
            $schedule_c = 0;
            foreach($case_ids as $case){
                $c = \Model_Case::find($case['id']);
                $a = \Model_System_Additional::find($case['id']);
                fputcsv($file, self::convertCaseColumnsToReadable(array_merge($c,$a), $columns));

                $schedules = \Accounting\Model_Payment_Schedules::findByCaseIDForExport($case['id'],2);
                foreach($schedules as $schedule){
                    if($schedule_c == 0) {
                        fputcsv($sch, array_keys($schedule));
                    }
                    fputcsv($sch, $schedule);
                    $schedule_c++;
                }

            }

            fclose($file);
        }
        static private function flag($msg, $case_id){

            $n = new \Notification\Model_Notification();
            $n->writeMessage(350,'USER ACTIVITY FLAG!',$msg,'General', $case_id);
            $n->writeMessage(72,'USER ACTIVITY FLAG!',$msg,'General', $case_id);

        }
}
