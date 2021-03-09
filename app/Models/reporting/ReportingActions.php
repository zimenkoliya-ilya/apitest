<?php

namespace App\Models\reporting;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReportingActions extends Model
{
    use HasFactory;
    public function setQuery($option){
        switch($option){
            case 'default':
                $this->filters->setFilter('Companies', 'c.company_id', Model_System_Company::getList(), 'multiselect');
               // $this->filters->setFilter('Processing', 'cs.processor_id', Model_System_Company::getProcessors(), 'multiselect');
                $this->filters->setFilter('Users','u.id', Model_System_User::getFilter(),'select');
                $this->filters->setFilter('Actions','action_id',Model_System_Action::findAllTypes(),'select');
                $this->filters->setFilter('Search By','date_field',array(array('id'=>'la.created','name'=>'Created Date')),'select', true);
                $this->query = \DB::select(
                    array('la.id','log_number'), array('a.name', 'action'), 'la.case_id','la.created', DB::expr('CONCAT(u.first_name," ",u.last_name) as created_by')
                )->from(array('log_actions', 'la'))
                    ->join(array('users','u'), 'LEFT')->on('u.id','=','la.created_by')
                    ->join(array('actions','a'), 'LEFT')->on('a.id', '=', 'la.action_id')
                    ->join(array('cases','c'), 'LEFT')->on('c.id', '=', 'la.case_id')
                    ->join(array('case_contact','cc'), 'LEFT')->on('cc.case_id', '=', 'c.id')
                    ->join(array('case_statuses','cs'), 'LEFT')->on('cs.case_id', '=', 'c.id')
                    ->join(array('case_assignments', 'ca'),'LEFT')->on('ca.case_id','=','c.id')
                    ->group_by('la.case_id','la.action_id');
                if(!isset($this->date_field)){
                    $this->setDateField('la.created');
                }
                // Group by
                $this->setDateRange();
                $this->useACL();
                break;
            case 'total_by_type':
                $this->filters->setFilter('Users','u.id', Model_System_User::getFilter(),'select');
                $this->filters->setFilter('Actions','action_id',Model_System_Action::findAllTypes(),'select');
                $this->filters->setFilter('Search By','date_field',array(array('id'=>'la.created','name'=>'Created Date')),'select', true);
                //$this->filters->setFilter('Campaigns','campaign_id',Model_System_Campaign::findAll(),'select');
                $this->query = \DB::select(
                    array('a.name','name'), DB::expr('COUNT(la.id) as count'), DB::expr('COUNT(DISTINCT(c.id)) as uniques')
                )
                    ->from(array('log_actions', 'la'))
                    ->join(array('actions','a'),'LEFT')->on('a.id','=','la.action_id')
                    ->join(array('cases','c'), 'LEFT')->on('c.id', '=', 'la.case_id')
                    ->join(array('case_contact','cc'), 'LEFT')->on('cc.case_id', '=', 'c.id')
                    ->join(array('case_statuses','cs'), 'LEFT')->on('cs.case_id', '=', 'c.id')
                    ->join(array('case_assignments', 'ca'),'LEFT')->on('ca.case_id','=','c.id')
                    ->join(array('users','u'),'LEFT')->on('u.id','=','ca.user_id')
                    ->group_by('a.id');
                if(!isset($this->date_field)){
                    $this->setDateField('la.created');
                }
                $this->setDateRange();
                $this->useACL();
                //$filter->setFilter('Users','u.id', Model_System_User::getFilter(),'select');
                //$filter->setFilter('Search By','datefield',array(),'select');
                //$this->filters = $filter->getFilters();
                break;
            case 'file_count':
                $this->filters->setFilter('Users','u.id', Model_System_User::getFilter(),'select');
                $this->filters->setFilter('Actions','action_id',Model_System_Action::findAllTypes(),'select');
                $this->filters->setFilter('Search By','date_field',array(array('id'=>'la.created','name'=>'Created Date')),'select', true);
                $this->filters->setFilter('Campaigns','campaign_id',Model_System_Campaign::findAll(),'select');
                $this->query = \DB::select(
                    array('a.name','name'), DB::expr('COUNT(la.id) as count')
                )
                    ->from(array('log_actions', 'la'))
                    ->join(array('actions','a'),'LEFT')->on('a.id','=','la.action_id')
                    ->join(array('cases','c'), 'LEFT')->on('c.id', '=', 'la.case_id')
                    ->join(array('case_contact','cc'), 'LEFT')->on('cc.case_id', '=', 'c.id')
                    ->join(array('case_statuses','cs'), 'LEFT')->on('cs.case_id', '=', 'c.id')
                    ->join(array('campaigns','camp'), 'LEFT')->on('cs.campaign_id', '=', 'camp.id')
                    ->join(array('case_assignments', 'ca'),'LEFT')->on('ca.case_id','=','c.id')
                    ->join(array('shared_cases','sc'), 'LEFT')->on('sc.case_id','=','c.id')
                    ->group_by('a.id');
                if(!isset($this->date_field)){
                    $this->setDateField('la.created');
                }
                $this->setDateRange();
                $this->useACL();
                //$filter->setFilter('Users','u.id', Model_System_User::getFilter(),'select');
                //$filter->setFilter('Search By','datefield',array(),'select');
                //$this->filters = $filter->getFilters();
                break;
        }

    }
}
