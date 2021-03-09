<?php

namespace App\Models\cases;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\system\SystemLists;
use App\Models\system\SystemListfields;
use App\Models\system\SystemFormfields;
use App\Models\system\SystemStatus;
use App\Models\system\SystemUser;
use App\Models\system\SystemCampaign;
use App\Models\Cases;
use App\Models\Esign_doc;
use App\Models\Assignment;
use App\Models\Account;
use App\Models\reporting\ReportingFinancing;
use App\Models\reporting\ReportingCampaigns;
class CasesFilter extends Model
{
    use HasFactory;
    protected $ids;
    protected $list;
    protected $list_fields;
    protected $compiled_fields;
    protected $select_fields;
    protected $additional_fields;
    protected $dept_fields;
    protected $filters;
    protected $dates;
    protected $date_field;
    protected $date_field_table;
    protected $limit;
    protected $offset;
    protected $max_limit = 10000;
    protected $total_items;
    protected $paginate = false;
    protected $for_export = false;
    protected $values;
    protected $queries = array();
    protected $explain_queries = false;


    /**
     * @param $list_id
     * @return array
     */
    function findByListId($list_id){

        if(empty($this->list)) {
            $this->list = SystemLists::find_($list_id);
            $this->list['list_id'] = $this->list['id'];
        }
       
        if(empty($this->list_fields)) {
            $this->list_fields = SystemListfields::findByFilter($this->list,
                ['of.id', 'of.clean_name', 'of.field_type_id', 'lf.sort']);
            $this->setSelectFields();
        }
        return $this->findCases();

    }



    function unsetDateFields($filter){
        foreach(array('date_field','dates','start_date','end_date','workflow') as $date_field){
            if(isset($filter[$date_field])){
                unset($filter[$date_field]);
            }
        }
        return $filter;
    }

    /**
     * @param $date_field
     * @return $this
     */
    function setDateField($date_field){

        if(!in_array($date_field, array('last_action', 'created', 'doc_submission','renewal_date','esign_date'))){
            throw new InvalidArgumentException("Invalid date field selection");
        }

        $fields = $this->getTablesFromFields();
        $implied = $this->getImpliedFields();

        if(isset($implied[$date_field])){
            $converted = $implied[$date_field];
            $compiled = $fields[$date_field].'.'.$converted;
            $this->date_field_table ;
        }else{
            $compiled = $fields[$date_field].'.'.$date_field;
        }

        Log::append('test', $compiled);

        $this->date_field_table = $fields[$date_field];
        $this->date_field = $compiled;
        return $this;
    }

    function getDateField(){
        return $this->date_field;
    }

    function setFilters(array $filters){
        //TODO implement exporting with workflow and issue support
        if(!empty($filters['workflow'])){ unset($filters['workflow']); }
        if(!empty($filters['issue'])){ unset($filters['issue']); }
        $this->filters = $this->unsetDateFields($filters);
        //$this->filters = $filters;
        return $this;
    }

    function setDates(DateTime $start, DateTime $end){
        $this->dates = [$start, $end];
        return $this;
    }

    function getDates(){

        if($this->dates[0] instanceof DateTime &&
            $this->dates[1] instanceof DateTime) {

            return array(
                $this->dates[0]->format('Y-m-d H:i:s'),
                $this->dates[1]->format('Y-m-d H:i:s')
            );
        }

        return array();
    }

    function setLimit($limit){

        if($limit > $this->max_limit){
            throw new InvalidArgumentException("Max limit is set at ".$this->max_limit." records");
        }

        $this->limit = $limit;
        return $this;
    }

    function setMaxLimit($limit){
        $this->max_limit = $limit;
        return $this;
    }

    function setOffset($offset){
        $this->offset = $offset;
        return $this;
    }

    function setTotalItems($count){
        $this->total_items = $count;
        return $this;
    }

    function getTotalItems(){
        return $this->total_items;
    }

    function getList(){
        return $this->list;
    }

    function forExport($bool = true){
        $this->for_export = $bool;
        return $this;
    }

    function paginate($limit){
        $this->setLimit($limit);
        $this->paginate = true;
        return $this;
    }

    function getPaginatedIds(){
        return array_slice($this->ids, $this->offset, $this->limit);
    }

    function getIds(){
        if(empty($this->ids)){
            $this->findWithTableConditions($this->getTableConditionsFromFilters());
        }
        if($this->paginate && !$this->for_export){
            return $this->getPaginatedIds();
        }
        return $this->ids;
    }

    protected function getTableConditionsFromFilters(){

        $fields = $this->getTablesFromFields();
        $id_fields = $this->getIdFields();
        $tables = array();
        foreach($this->filters as $field => $value){
            $table = $fields[$field];
            if(isset($id_fields[$field])){
                $converted = $id_fields[$field];
                $tables[$table]['conditions'][$table.'.'.$converted] = $value;
            }else{
                $tables[$table]['conditions'][$table.'.'.$field] = $value;
            }
        }
        return $tables;
    }

    protected function setSelectFields(){

        $fields = $this->getTablesFromFields();
        $select_fields = array();
        $id_fields = $this->getIdFields();
        $implied_fields = $this->getImpliedFields();
        foreach($this->list_fields as $field){
            if(!empty($fields[$field['clean_name']])){
                $table = $fields[$field['clean_name']];
                $select_fields[$table][$field['clean_name']] = $table.'.'.$field['clean_name'];
            }else{
                $this->additional_fields[] = $field['id'];
                if(substr($field['clean_name'],0,4) == 'dept') {
                    $this->dept_fields[] = $field['clean_name'];
                }
                // Convert ID Fields -> Select Fields
                if(!empty($id_fields[$field['clean_name']])){
                    $converted = $id_fields[$field['clean_name']];
                    $table = $fields[$converted];
                    $select_fields[$table][$converted] = $table.'.'.$converted;
                }
                // Convert Implied Fields -> Select Fields
                if(!empty($implied_fields[$field['clean_name']])){
                    $converted = $implied_fields[$field['clean_name']];
                    $table = $fields[$converted];
                    $select_fields[$table][$converted] = $table.'.'.$converted;
                }
            }
        }
        $this->select_fields = $select_fields;
        return $this;
    }

    function addDateConditions($date_string, $start_string = null, $end_string = null){

        if(!empty($start_string) && !empty($end_string)){

            $end = new DateTime($end_string);

            $this->setDates(
                new DateTime($start_string),
                $end->modify('+1 day -1 second')
            );

            return $this;
        }

        $start = new DateTime('midnight today');
        $end = new DateTime('midnight today');

        switch($date_string) {

            case 'today':
                $end->modify('+1 day -1 second');
                break;

            case 'yesterday':
                $start->modify('-1 day');
                $end->modify('-1 second');
                break;

            case 'last7':
                $start->modify('-7 days');
                $end->modify('+1 day -1 second');
                break;

            case 'last30':
                $start->modify('-30 days');
                $end->modify('+1 day -1 second');
                break;

            case 'this_month':
                $start->modify('first day of');
                $end->modify('first day of next month -1 second');
                break;

            case 'last_month':
                $start->modify('first day of last month');
                $end->modify('first day of -1 second');
                break;

            default:
                return $this;

        }

        $this->setDates($start, $end);

        return $this;

    }

    protected function getTablesFromFields(){
        if(!empty($compiled_fields)){
            return $this->compiled_fields;
        }
        $tables = array(
            'cases' => ['id', 'company_id', 'action_count', 'last_action', 'created', 'updated'],
            'case_statuses' => ['is_company', 'status_id', 'campaign_id', 'dialer_id', 'docs_status', 'is_client', 'accounting_status_id', 'doc_submission','paused', 'financed', 'status_updated','renewal_date','termination_date','activation_date','accounting_type'],
            'statuses' => ['milestone_id'],
            'case_todos' => ['tasks'],
            'case_assignments' => ['user_id', 'department_id'],
            'case_contact' => ['first_name', 'middle_name', 'last_name', 'email', 'primary_phone', 'secondary_phone', 'mobile_phone', 'ssn', 'dob', 'fax', 'address', 'address2', 'city', 'state', 'zip', 'country', 'timezone', 'title', 'dpp_contact_id'],
            'esign_docs' => ['esign_date','signed_date'],
            'events' => ['appointment_date'],
            'financing' => ['financing_status', 'ea_track', 'financing_score']
        );

        $fields = array();
        foreach($tables as $table => $v){
            foreach($v as $field){
                $fields[$field] = $table;
            }
        }
        $this->compiled_fields = $fields;
        return $this->compiled_fields;
    }

    function findWithTableConditions($tables){

        $query = Cases::select('cases.id');
        $query->join('case_statuses', 'case_statuses.case_id','cases.id');

        if(empty($tables[$this->date_field_table])){
            $tables[$this->date_field_table] = array(1);
            // setting date field table
        }

        if(!empty($tables['case_contact'])){
            $query->join('case_contact', 'case_contact.case_id','cases.id');
        }

        if(!empty($tables['statuses'])){
            $query->leftJoin('statuses', 'statuses.id','case_statuses.status_id');
        }

        if(!empty($tables['case_todos'])){
            $query->leftJoin('case_todos', 'case_todos.case_id','cases.id');
        }

        if(!empty($tables['case_assignments'])){
            $query->leftJoin('case_assignments','case_assignments.case_id', 'cases.id');
        }

        if(!empty($tables['financing'])){
            $query->leftJoin('financing', 'financing.case_id', 'cases.id');
        }

        if(!empty($tables['esign_docs'])){
            $query->leftJoin('esign_docs', 'esign_docs.case_id', 'cases.id');
        }

        $query = $this->addConditionsToQuery($query, $tables);

        $query->limit(($this->limit?$this->limit:$this->max_limit))
            ->offset($this->offset);

        $result = $query->get();

        $this->logQuery($result);

        $ids = array();
        foreach($result->as_array() as $r){
            $ids[] = $r['id'];
        }

        if(empty($ids)){
            throw new Exception('No cases found');
        }

        $this->ids = array_unique($ids);

        $this->setTotalItems(count($this->ids));

        return $this;

    }
    // problem $filters undefined
    function findCases(){
        $this->ids = array();
        $ids = $this->getIds();
        if(empty($ids)){
            return false;
        }
       
        $base_result = array();
        if(!empty($this->select_fields['cases']) || !empty($this->select_fields['case_statuses'])) {
            $base_fields = array_merge(
                (!empty($this->select_fields['cases']) ? $this->select_fields['cases'] : array()),
                (!empty($this->select_fields['case_statuses']) ? $this->select_fields['case_statuses'] : array()),
                (!empty($this->select_fields['case_contact']) ? $this->select_fields['case_contact'] : array())
            );
            if(!in_array('cases.id', $base_fields)){
                $base_fields[] = 'cases.id';
            }
            $base = Cases::select($base_fields)
                ->join('case_statuses', 'case_statuses.case_id', 'cases.id')
                ->join('case_contact', 'case_contact.case_id', 'cases.id')
                ->whereIn('cases.id', $ids)
                ->get();
            $this->logQuery($base);
            $result = $base->toArray();
            foreach($result as $r){
                $base_result[$r['id']] = $r;
            }
            unset($result);
            unset($base);
        }
        if(!empty($this->additional_fields)){
            $types = SystemFormFields::findAllTypes();
            foreach($types as $t){
                $type[$t['id']] = $t['f_field'];
            }
            foreach($this->list_fields as $f){
                $fields[$f['id']] = $f['clean_name'];
                $sorted_fields[] = $f['clean_name'];
                //$sorted_fields[$f['sort']] = $f['clean_name'];
                $field_type[$f['id']] = $f['field_type_id'];
            }
            ksort($sorted_fields);
            $additional = CaseInterview::
                whereIn('case_id', $ids)
                ->whereIn('field_id', $this->additional_fields)
                ->get();
            $this->logQuery($additional);
            $additional_result = $additional->as_array();
            foreach($additional_result as $r){
                if(empty($field_type[$r['field_id']])) continue;
                $base_result[$r['case_id']][$fields[$r['field_id']]] = $r[$type[$field_type[$r['field_id']]]];
            }
            unset($additional);
            unset($additional_result);
        }
        if(!empty($this->dept_fields)){
            $result = Assignment::selectRaw('case_id, d.name as department, CONCAT(u.first_name, " ", u.last_name) as user')
                ->from('case_assignments as ca')
                ->join('departments as d', 'd.id', 'ca.department_id')
                ->join('users as u', 'u.id', 'ca.user_id')
                ->whereIn('case_id', $ids)
                ->get();
            $this->logQuery($result);
            $depts_result = $result->toArray();
            foreach($depts_result as $r){
                $k = 'dept_'.strtolower(str_replace(' ', '_', $r['department']));
                if(in_array($k, $this->dept_fields)){
                    $base_result[$r['case_id']][$k] = $r['user'];
                }
            }
        }
        if(!empty($this->select_fields['esign_docs'])){
            $result = Esign_doc::select('case_id', 'updated as signed_date')
                ->whereIn('case_id', $ids)
                ->get();
            $this->logQuery($result);
            $docs_result = $result->toArray();
            foreach($docs_result as $r){
                $base_result[$r['case_id']]['signed_date'] = $r['signed_date'];
            }
        }
        if(!empty($this->select_fields['financing'])){
            $result = ReportingFinancing::select('case_id', 'track as ea_track', 'status as financing_status','score as financing_score')
                ->whereIn('case_id', $ids)
                ->get();
            $this->logQuery($result);
            $result = $result->toArray();
            foreach($result as $r){
                $base_result[$r['case_id']]['ea_track'] = $r['ea_track'];
                $base_result[$r['case_id']]['financing_status'] = $r['financing_status'];
                $base_result[$r['case_id']]['financing_score'] = $r['financing_score'];
            }
        }
        $values = $this->getIdValueConversions();
        $value_fields = array_keys($values);
        $sorted_result = array();
        foreach($base_result as $rk => $row){
            foreach($row as $k => $v){
                if(in_array($k, $value_fields)) {
                    $vf = $values[$k];
                    $base_result[$rk][$vf['name']] = $this->convertValues($vf['values'], $v); // (!empty($vf['values'][$v])?$vf['values'][$v]:'');
                    $row[$vf['name']] = $base_result[$rk][$vf['name']];
                }
            }
            foreach($sorted_fields as $v){
                $sorted_result[$rk][$v] = (!empty($row[$v])?$row[$v]:'');
                unset($row[$v]);
            }
            if(!empty($row)){
                $sorted_result[$rk] = array_merge($sorted_result[$rk], $row);
            }
            unset($base_result[$rk]);
        }
        return $sorted_result;
    }

    protected function getIdFields(){
        return [
            'status' => 'status_id',
            'campaign' => 'campaign_id',
            'accounting_status' => 'paused',
            'company' => 'company_id',
            'dialer_status' => 'dialer_id',
            'financing_status' => 'status',
            'ea_track' => 'track',
            'financing_score' => 'score',
        ];

    }

    protected function getImpliedFields(){

        return [
            'days_status' => 'status_updated',
            'esign_date' => 'updated',
            'appointment_date' => 'at'
        ];

    }

    protected function getIdValueConversions(){

        if (!empty($this->values)) {
            return $this->values;
        }
        $campaign_result = ReportingCampaigns::select('id','name')->get();
        $campaign_set = $campaign_result->toArray();
        $campaigns = array();
        foreach($campaign_set as $c){
            $campaigns[$c['id']] = $c['name'];
        }
        $status_set = SystemStatus::findAll();
        $statuses = array();
        foreach($status_set as $s){
            $statuses[$s['id']] = $s['name'];
        }
        $company_result = ReportingCampaigns::select('id','name')->get();
        $company_set = $company_result->toArray();
        $companies = array();
        foreach($company_set as $c){
            $companies[$c['id']] = $c['name'];
        }
        $this->values = array(
            'status_id' => array('name' => 'status', 'values' => &$statuses),
            'campaign_id' => array('name' => 'campaign', 'values' => &$campaigns),
            'company_id' => array('name' => 'company', 'values' => &$companies),
            'is_client' => array('name' => 'is_client', 'values' => array('lead', 'client', 'terminated')),
            'paused' => array('name' => 'accounting_status', 'values' => array('Active', 'Paused')),
            /*'financed' => array('name' => 'financing_status', 'values' => function ($v){ return ($v?'Yes':'No'); }),*/
            'dialer_id' => array('name' => 'dialer_status', 'values' => &$statuses),
            'status_updated' => array('name' => 'days_status', 'values' => function ($date_string) { return floor((time() - strtotime($date_string))/3600/24); })
        );
        return $this->values;
    }

    protected function convertValues($field_values, $value){
        if($field_values instanceof Closure){
            return $field_values($value);
        }elseif(!empty($field_values[$value])){
            return $field_values[$value];
        }
        return '';

    }

    protected function addConditionsToQuery($query, $tables){
        foreach($tables as $table) {
            if(isset($table['conditions'])) {
                foreach ($table['conditions'] as $field => $value) {
                    if (is_array($value)) {
                        $query->whereIn($field, $value);
                    } else {
                        $query->where($field, $value);
                    }

                }
            }
        }
        $dates = $this->getDates();
        if(!empty($dates)){
            $query->whereBetween($this->getDateField(), $dates);
        }
        $query = $this->addAccessRestrictions($query, $tables);
        return $query;
    }
    function addAccessRestrictions($query, $tables){

        $required_tables = array();
        switch(Account::getType()) {

            case 'Network':

                $network_ids = SystemUser::getSessionMeta('network_companies') ?: array();
                $network_ids = (!is_array($network_ids)?array($network_ids):$network_ids);

                $company_ids = array();
                foreach($network_ids as $n){
                    $company_ids[] = $n['company_id'];
                }

                $company_ids[] = SystemUser::getSessionMeta('company_id');

                $query->where('cases.company_id', 'IN', $company_ids);
                break;

            case 'Company':

                $query->where('cases.company_id', '=', SystemUser::getSessionMeta('company_id'));
                break;

            case 'Region':

                $required_tables[] = 'case_assignments';

                $query->join('users')->on('users.id', '=', 'case_assignments.user_id');
                $query->join('regions')->on('regions.id', '=', 'users.region_id');
                $query->where('regions.id','=', SystemUser::getSessionMeta('region_id'));
                break;

            case 'Campaign':

                $campaigns = SystemCampaign::findByGroup(SystemUser::getSessionMeta('campaign_group_id'));

                $campaign_ids = array();

                foreach ($campaigns as $c) {
                    $campaign_ids[] = $c['id'];
                }

                $query->where('case_statuses.campaign_id', 'IN', $campaign_ids);
                break;

            case 'User':

                $required_tables[] = 'case_assignments';

                $query->where('case_assignments.user_id', '=', SystemUser::getSessionMeta('id'));
                break;

        }

        if(in_array('case_assignments', $required_tables) && !in_array('case_assignments', array_keys($tables))){
            $query->join('case_assignments', 'case_assignments.case_id', 'cases.id');
        }

        return $query;

    }

    function explainQueries($bool = true){
        $this->explain_queries = ($bool ? true : false);
        return $this;
    }

    protected function logQuery($string){
        if($this->explain_queries) {
            $this->queries[] = $string;
        }
        Log::append('queries',$string);
    }
    function queryReport(){
        $report =  '<h1>Query Report</h1><pre>';
        foreach($this->queries as $query){
            $report .= '<p>EXPLAIN '.$query.'</p>';
            $result = \DB::query('EXPLAIN '.$query, DB::SELECT)->execute();
            $report .= print_r($result->as_array(), true);
        }
        $report .= '</pre>';
        return $report;
    }
}
