<?php

namespace App\Models\reporting;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReportingCampaigns extends Model
{
    use HasFactory;
    protected $table = "campaigns";
    function setQuery($option){



        switch($option){

            case 'default':

                $this->filters->setFilter('Search By','date_field',array(array('id'=>'ca.created','name'=>'Created Date')),'select', true);

                $this->query = \DB::select(
                    array('ca.count','clients'), array('cc.count','leads'), 'c.name'
                )->from(array('campaigns', 'c'))
                    ->join(array(DB::expr('(select count(DISTINCT(ca.id)) as count, cs.campaign_id
                            from cases ca
                            inner join case_contact cc on cc.case_id = ca.id
                            inner join case_statuses cs on cs.case_id = ca.id
                            inner join case_assignments cas on cas.case_id = ca.id
                            inner join departments d on d.id = cas.department_id
                            inner join users cau on cau.id = cas.user_id
                            left join shared_cases sc on sc.case_id = ca.id
                            where cs.is_client = 1
                            and ca.created between "'.date('Y-m-d', strtotime($this->dates['start_date'])).'" and "'.date('Y-m-d', strtotime($this->dates['end_date'])).'"
                             and (ca.company_id = '.Model_System_User::getSessionMeta('company_id').' OR sc.company_id = '.Model_System_User::getSessionMeta('company_id').')
                        group by cs.campaign_id)'),'ca'), 'INNER')
                    ->on('ca.campaign_id','=','c.id')
                    ->join(array(DB::expr('(select count(DISTINCT(cc.id)) as count, cs.campaign_id
                            from cases cc
                            inner join case_contact con on con.case_id = cc.id
                            inner join case_statuses cs on cs.case_id = cc.id
                            inner join case_assignments cas on cas.case_id = cc.id
                            inner join departments d on d.id = cas.department_id
                            inner join users cau on cau.id = cas.user_id
                            left join shared_cases sc on sc.case_id = cc.id
                            where cs.is_client = 0
                            and cc.created between "'.date('Y-m-d', strtotime($this->dates['start_date'])).'" and "'.date('Y-m-d', strtotime($this->dates['end_date'])).'"
                             and (cc.company_id = '.Model_System_User::getSessionMeta('company_id').' OR sc.company_id = '.Model_System_User::getSessionMeta('company_id').')
                        group by cs.campaign_id)'),'cc'), 'INNER')
                    ->on('cc.campaign_id','=','c.id')

            ->group_by('c.id');

                $this->useACL();

                $this->dateIsSet = true;
                break;

            case 'campaigns_transfers':

                $this->filters->setFilter('Search By','date_field',array(array('id'=>'la.created','name'=>'Created Date')),'select', true);

                $this->query = \DB::select('cp.name',  DB::expr('COALESCE(log.count, 0) as transfers'), DB::expr('COALESCE(es.count, 0) as signed'), DB::expr('COALESCE(ess.count, 0) as sent'), DB::expr('COALESCE(TRUNCATE((ess.count/log.count)*100, 2),0) as sent_ratio'), DB::expr('COALESCE(TRUNCATE((es.count/log.count)*100, 2),0) as signed_ratio')
                )
                    ->from(array('campaigns', 'cp'))

                    ->join(array(DB::expr('(select count(DISTINCT(es.id)) as count, cs.campaign_id
                            from esign_docs es
                            inner join case_statuses cs on es.case_id = cs.case_id
                            where es.updated between "'.date('Y-m-d', strtotime($this->dates['start_date'])).' 00:00:00" and "'.date('Y-m-d', strtotime($this->dates['end_date'])).' 23:59:59"
                             and es.status = "Signed"
                        group by cs.campaign_id)'),'es'), 'LEFT')
                    ->on('es.campaign_id','=','cp.id')

                    ->join(array(DB::expr('(select count(DISTINCT(es.case_id)) as count, cs.campaign_id
                            from esign_docs es
                            inner join case_statuses cs on es.case_id = cs.case_id
                            where es.updated between "'.date('Y-m-d', strtotime($this->dates['start_date'])).' 00:00:00" and "'.date('Y-m-d', strtotime($this->dates['end_date'])).' 23:59:59"
                             and es.status = "Sent"
                        group by cs.campaign_id)'),'ess'), 'LEFT')
                    ->on('ess.campaign_id','=','cp.id')

                    ->join(array(DB::expr('(select count(DISTINCT(la.id)) as count, cs.campaign_id, la.created
                            from log_actions la
                            inner join actions a on a.id = la.action_id
                            inner join case_statuses cs on cs.case_id = la.case_id
                            where la.action_id = 8
                            and la.created between "'.date('Y-m-d', strtotime($this->dates['start_date'])).' 00:00:00" and "'.date('Y-m-d', strtotime($this->dates['end_date'])).' 23:59:59"
                        group by cs.campaign_id)'),'log'), 'LEFT')

                    ->on('log.campaign_id','=','cp.id')
                    ->order_by('transfers', 'DESC')
                    ->group_by('cp.id');

                $this->dateIsSet = true;
                $this->useACL();

                break;

            case 'campaigns_overview':

                $this->filters->setFilter('Search By','date_field',array(array('id'=>'la.created','name'=>'Default Date')),'select', true);

                $this->query = \DB::select('cp.name',DB::expr('COALESCE(c.count, 0) as leads'),  DB::expr('COALESCE(log.count, 0) as transfers'), DB::expr('COALESCE(es.count, 0) as first_signed'), DB::expr('COALESCE(ld.count, 0) as dupes'),DB::expr('COALESCE(TRUNCATE((es.count/c.count)*100, 2),0) as leads_signed_ratio'),DB::expr('COALESCE(TRUNCATE((es.count/log.count)*100, 2),0) as transfer_signed_ratio'), DB::expr('COALESCE(TRUNCATE((ld.count/c.count)*100, 2),0) as dupes_to_lead_ratio')
                )
                  ->from(array('campaigns', 'cp'))

                  ->join(array(DB::expr('(select count(DISTINCT(c.id)) as count, cs.campaign_id
                            from cases c
                            inner join case_statuses cs on c.id = cs.case_id
                            where c.created between "'.date('Y-m-d', strtotime($this->dates['start_date'])).' 00:00:00" and "'.date('Y-m-d', strtotime($this->dates['end_date'])).' 23:59:59"
                            group by cs.campaign_id)'),'c'), 'LEFT')
                    ->on('c.campaign_id','=','cp.id')

                    ->join(array(DB::expr('(select count(DISTINCT(es.case_id)) as count, cs.campaign_id
                            from esign_docs es
                            inner join case_statuses cs on es.case_id = cs.case_id
                            where es.updated between "'.date('Y-m-d', strtotime($this->dates['start_date'])).' 00:00:00" and "'.date('Y-m-d', strtotime($this->dates['end_date'])).' 23:59:59"
                             and es.status = "Signed"
                              and es.category_id = 1
                        group by cs.campaign_id)'),'es'), 'LEFT')
                    ->on('es.campaign_id','=','cp.id')

                    ->join(array(DB::expr('(select count(DISTINCT(la.id)) as count, cs.campaign_id, la.created
                            from log_actions la
                            inner join actions a on a.id = la.action_id
                            inner join case_statuses cs on cs.case_id = la.case_id
                            where la.action_id = 8
                            and la.created between "'.date('Y-m-d', strtotime($this->dates['start_date'])).' 00:00:00" and "'.date('Y-m-d', strtotime($this->dates['end_date'])).' 23:59:59"
                        group by cs.campaign_id)'),'log'), 'LEFT')
                    ->on('log.campaign_id','=','cp.id')

                    ->join(array(DB::expr('(select count(DISTINCT(ld.case_id)) as count, ld.new_campaign_id, ld.created
                            from log_duplicates ld
                            where ld.created between "'.date('Y-m-d', strtotime($this->dates['start_date'])).' 00:00:00" and "'.date('Y-m-d', strtotime($this->dates['end_date'])).' 23:59:59"
                        group by ld.new_campaign_id)'),'ld'), 'LEFT')

                    ->on('ld.new_campaign_id','=','cp.id')
                    ->order_by('transfers', 'DESC')
                    ->group_by('cp.id');

                $this->dateIsSet = true;
                $this->useACL();

                break;

            case 'leads_closing_ratio':

                $this->filters->setFilter('Search By','date_field',array(array('id'=>'la.created','name'=>'Created Date')),'select', true);

                $this->query = \DB::select('cp.name',DB::expr('COALESCE(c.count, 0) as leads'), DB::expr('COALESCE(log.count, 0) as transfers'), DB::expr('es.count as closes'), DB::expr('TRUNCATE((es.count/c.count)*100, 2) as ratio')
                )
                    ->from(array('campaigns', 'cp'))
                    ->join(array(DB::expr('(select count(DISTINCT(c.id)) as count, c.campaign_id
                            from cases c
                            inner join case_contact cc on cc.case_id = c.id
                            inner join case_statuses cs on cs.case_id = c.id
                            inner join case_assignments cas on ca.case_id = c.id
                            inner join departments d on d.id = cas.department_id
                            inner join users cau on cau.id = cas.user_id
                            left join shared_cases sc on sc.case_id = c.id
                            where c.created between "'.date('Y-m-d', strtotime($this->dates['start_date'])).'" and "'.date('Y-m-d', strtotime($this->dates['end_date'])).'"
                             and (c.company_id = '.Model_System_User::getSessionMeta('company_id').' OR sc.company_id = '.Model_System_User::getSessionMeta('company_id').')
                        group by c.campaign_id)'),'c'), 'LEFT')
                    ->on('c.campaign_id','=','cp.id')


                    ->join(array(DB::expr('(select count(DISTINCT(la.id)) as count, c.campaign_id, la.ts
                            from log_actions la
                        inner join actions a on a.id = la.action_id
                        inner join cases c on c.id = la.case_id
                        inner join case_contact cc on cc.case_id = c.id
                            inner join case_statuses cs on cs.case_id = c.id
                            inner join case_assignments cas on ca.case_id = c.id
                            inner join departments d on d.id = cas.department_id
                            inner join users cau on cau.id = cas.user_id
                            left join shared_cases sc on sc.case_id = c.id
                            where la.action_id = 8
                            and la.ts between "'.date('Y-m-d', strtotime($this->dates['start_date'])).'" and "'.date('Y-m-d', strtotime($this->dates['end_date'])).'"
                             and (c.company_id = '.Model_System_User::getSessionMeta('company_id').' OR sc.company_id = '.Model_System_User::getSessionMeta('company_id').')
                        group by c.campaign_id)'),'log'), 'LEFT')

                    ->on('log.campaign_id','=','cp.id')


                    ->join(array(DB::expr('(select count(DISTINCT(e.id)) as count, c.campaign_id, e.updated
                        from esign_docs e
                    inner join cases c on c.id = e.case_id
                    inner join case_contact cc on cc.case_id = c.id
                            inner join case_statuses cs on cs.case_id = c.id
                            inner join case_assignments cas on ca.case_id = c.id
                            inner join departments d on d.id = cas.department_id
                            inner join users cau on cau.id = cas.user_id
                            left join shared_cases sc on sc.case_id = c.id
                        where e.status = "Signed"
                         and e.updated between "'.date('Y-m-d', strtotime($this->dates['start_date'])).'" and "'.date('Y-m-d', strtotime($this->dates['end_date'])).'"
                          and (c.company_id = '.Model_System_User::getSessionMeta('company_id').' OR sc.company_id = '.Model_System_User::getSessionMeta('company_id').')
                    group by c.campaign_id)'),'es'), 'LEFT')

                    ->on('es.campaign_id','=','cp.id')

                    ->group_by('cp.id');

                $this->useACL();

                $this->dateIsSet = true;
                break;
        }

    }
}
