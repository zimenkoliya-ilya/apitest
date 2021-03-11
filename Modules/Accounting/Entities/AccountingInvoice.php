<?php

namespace Modules\Accounting\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AccountingInvoice extends Model
{
    use HasFactory;

    protected $fillable = [];
    protected $table = "invoices";
    protected static function newFactory()
    {
        return \Modules\Accounting\Database\factories\AccountingInvoiceFactory::new();
    }
    static function find_($id){

        $result = \DB::select('i.*')
            ->from(array('invoices', 'i'))
            ->where('i.id', '=', $id)
            ->limit(1)
            ->execute();
        return current($result->as_array());

    }
    static function findAll($case_id){

    }

    static function findNoteTypes(){
        $result = \DB::select()
            ->from('invoices')
            ->execute();
        return $result->as_array();

    }

    static function getInvoiceTypeList(){
        $result = \DB::select()
            ->from('invoice_types')
            ->execute();
        return $result->as_array();
    }

    static function getTotalByCaseID($id){
        $query = AccountingInvoice::selectRaw('SUM(total) as total_amount')->where('case_id', $id);
        $result = current($query->get()->toArray());
        return $result['total_amount'];
    }


    static function findByFilter($filter, $offset = 0, $limit = 10000, $sort_field = 'i.id', $order = 'asc', $output='records')
    {

        switch($output){
            case 'sum':
                $solution = \DB::expr('sum(i.amount) as total');
                break;
            case 'count':
                $solution = \DB::expr('count(DISTINCT i.id) as total');
                break;
            case 'records':
                $solution = 'i.*';
                break;
        }

        switch ($filter['date_field']) {
            case 'created':
                $filter['date_field'] = 'i.created';
                break;
            case 'updated':
                $filter['date_field'] = 'i.created';
                break;
        }

        $query = \DB::select($solution)
            ->from(array('invoices', 'i'))
            ->join(array('cases', 'c'), 'LEFT')->on('c.id', '=', 'i.case_id')
            ->join(array('case_contact', 'cc'), 'LEFT')->on('cc.case_id', '=', 'c.id')
            ->join(array('case_statuses', 'cs'), 'LEFT')->on('cs.case_id', '=', 'c.id')
            ->join(array('companies', 'com'), 'LEFT')->on('com.id', '=', 'c.company_id');

        if($output == 'records') {
            $query->select(array('p.paid','case_paid'));
            $query->join(array(\DB::expr('(select SUM(p.amount) as paid, p.case_id
                        from payments p
                        where p.status_id = 3
                        group by p.case_id)'), 'p'), 'LEFT')->on('p.case_id', '=', 'c.id');
        }

        if(isset($filter['amount_value']) && isset($filter['amount_operator']) && !empty($filter['amount_value'])){
            $query->where('i.total', $filter['amount_operator'], $filter['amount_value']);
        }

        if(isset($filter['line_items']) && !empty($filter['line_items'])){

            foreach($filter['line_items'] as $item){
                $items_set[] = $item;
            }

            $query->join(array('invoices_items', 'items'), 'LEFT')->on('items.invoice_id', '=', 'i.id');
            $query->where('items.type_id', 'IN', $items_set);
        }

        if (isset($filter['milestone_id']) && !empty($filter['milestone_id'])) {
            $query->where('s.milestone_id', '=', $filter['milestone_id']);
        }


        if (isset($filter['company_id']) && !empty($filter['company_id'])) {
            $query->where('c.company_id', '=', $filter['company_id']);
        }


        if (!empty($filter['dates']) && $filter['dates'] != 'all_time') {

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
        }else{
            $query->where($filter['date_field'], 'between', array(date('Y-m-d 00:00:00', strtotime('-1 year')), date('Y-m-d 23:59:59')));
        }

        $query = \Model_System_Access::queryAccess($query);


        if($output=='records'){

            $query->offset($offset)
                ->limit($limit)
                ->order_by($sort_field, $order)
                ->group_by('i.id');

            return $query->execute()->as_array();

        }else{
            $result = $query->execute();
            $row = current($result->as_array());
            return $row['total'];
        }


    }


    static function findTotalByFilter($filter, $group_by = 'c.id', $solution = 'count')
    {

        switch($solution){
            case 'sum':
                $solution = 'sum(i.amount) as total';
                break;
            case 'count':
                $solution = 'count(DISTINCT c.id) as total';
                break;
        }

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

        $query = \DB::select(
            \DB::expr($solution),
            \DB::expr('EXTRACT(YEAR FROM ' . $filter['date_field'] . ') as year'),
            \DB::expr('EXTRACT(MONTH FROM ' . $filter['date_field'] . ') as month'),
            \DB::expr('EXTRACT(DAY FROM ' . $filter['date_field'] . ') as day'),
            'doc_submission',
            'c.id'
        )
            ->from(array('cases', 'c'))
            ->join(array('case_contact', 'cc'), 'LEFT')->on('cc.case_id', '=', 'c.id')
            ->join(array('case_statuses', 'cs'), 'LEFT')->on('cs.case_id', '=', 'c.id')
            ->join(array('invoices', 'i'), 'LEFT')->on('i.case_id', '=', 'c.id')
            ->join(array('invoice_types', 'itype'), 'LEFT')->on('itype.id', '=', 'i.type_id')
            ->join(array('companies', 'com'), 'LEFT')->on('com.id', '=', 'c.company_id');



        if (isset($filter['milestone_id']) && !empty($filter['milestone_id'])) {
            $query->where('s.milestone_id', '=', $filter['milestone_id']);
        }

        if (isset($filter['type_id']) && !empty($filter['type_id'])) {
            $query->where('i.type_Id', '=', $filter['type_id']);
        }

        if (isset($filter['docs_status']) && !empty($filter['docs_status'])) {
            $query->where('cs.docs_status', '=', $filter['docs_status']);
        }

        if (isset($filter['client_status']) && !empty($filter['client_status'])) {
            $query->where('cs.is_client', '=', $filter['client_status']);
        }


        if (isset($filter['company_id']) && !empty($filter['company_id'])) {
            $query->where('c.company_id', '=', $filter['company_id']);
        }

        if (isset($filter['financed']) && !empty($filter['financed'])) {

            $query->where('cs.financed', '=', ($filter['financed'] == 0 ? 'NULL' : $filter['financed']));
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
                    date('Y-m-t', strtotime($filter['date'])) . ' 23:59:59'
                ));
            } elseif ($filter['dates'] == 'year') {
                $query->where($filter['date_field'], 'between', array(
                    date('Y-', strtotime($filter['date'])) . '01-01 00:00:00',
                    date('Y-', strtotime($filter['date'])) . '12-31 23:59:59'
                ));
            } elseif ($filter['dates'] == 'custom') {
                $query->where($filter['date_field'], 'between', array(date('Y-m-d', strtotime($filter['start_date'])) . ' 00:00:00', date('Y-m-d', strtotime($filter['end_date'])) . ' 23:59:59'));
            }
        }

        $query->group_by($group_by)->order_by($group_by);
        $results = $query->execute()->as_array();

        return $results;

    }

    static function findByCaseID($case_id){

        $result = \DB::select('i.*')
            ->from(array('invoices', 'i'))
            ->where('i.case_id', '=', $case_id)
            ->order_by('i.id', 'desc')->execute();
        return $result->as_array();
    }



    static function findByCaseIDAndType($case_id, $type){

        $result = \DB::select('i.*',\DB::expr('CONCAT(u.first_name, " ", u.last_name) as user'))
            ->from(array('invoices', 'i'))
            ->join(array('users', 'u'), 'left')->on('i.created_by', '=', 'u.id')
            ->where('i.case_id', '=', $case_id)
            ->where('i.type_id', '=', $type)
            ->limit(1)
            ->execute()->as_array();
        return current($result);
    }

    static function add($case_id, $data){
        $invoice = array(
            'case_id' => $case_id,
            'total' => $data['total'],
            'title' => $data['title'],
            'status' => $data['status'],
            'created' => date('Y-m-d H:i:s')
        );

        \Model_Log::addActivity($case_id,'Accounting','Invoice Created : $'.$invoice['total']);

        return current(\DB::insert('invoices')->set($invoice)->execute());
    }



    static function update_($id, $data){
        \DB::update('invoices')->set($data)->where('id', '=', $id)->execute();
    }

    static function delete_($id){
        \DB::delete('invoices')->where('id', '=', $id)->execute();
        \DB::delete('invoices_items')->where('invoice_id', '=', $id)->execute();
    }

    static function deleteByCaseId($case_id){
        \DB::delete('invoices')->where('case_id', '=', $case_id)->execute();
    }

    static function validate($factory){

        $val = \Validation::forge($factory);

        return $val;
    }


}
