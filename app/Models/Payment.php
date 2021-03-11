<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Entities\Payment\AccountingPaymentSchedule;
use Modules\Accounting\Entities\AccountingInvoice;
use DateTime;
class Payment extends Model
{
    use HasFactory;
    
    protected $table = 'payments';
    
		public function __construct() {
      DB::enableQueryLog();
    }
    public function logger($last = true) {
      $queries = DB::getQueryLog();
      if($last) {
        $queries = array(end($queries));
      }
      $formattedQueries = [];
      if($queries[0]):
        foreach ($queries as $query) :
            $prep = $query['query'];

            foreach ($query['bindings'] as $binding) :

                if (is_bool($binding)) {
                    $val = $binding === true ? 'TRUE' : 'FALSE';
                } else if (is_numeric($binding)) {
                    $val = $binding;
                } else {
                    $val = "'$binding'";
                }

                $prep = preg_replace("#\?#", $val, $prep, 1);
            endforeach;
            $formattedQueries[] = $prep;
        endforeach;
      endif;
      if($last && $formattedQueries) {
        $formattedQueries = $formattedQueries[0];
      }
      return $formattedQueries;
    }
		
    public function find_($case_id, $id) {
      $result = Payment::where('case_id', $case_id)
                    ->where('active','!=', 0)
                    ->where('id', $id)->get()->toArray();
                   
      return current($result->toArray());
    }
    public function findAll() {
      return array();
    }
    public function findByCaseID($case_id){
      $result = Payment::where('case_id', $case_id)
            ->where('active','!=',0)
            ->get()->toArray();
            return $result; 
    }
    public function findTotalByFilter($filter, $group_by = 'day', $solution='count'){

        switch($solution){
            case 'sum':
                $solution = 'sum(p.amount) as total';
                break;
            case 'count':
                $solution = 'count(DISTINCT p.id) as total';
                break;
            case 'filecount':
                $solution = 'count(p.case_id) as total';
                break;
        }
        $query = Payment::from('payments as p')
        ->selectRaw('YEAR(p.created) as year, MONTH(p.created) as month, DAY(p.created) as day')
        ->leftJoin('cases as c', 'c.id', '=', 'p.case_id')
        ->leftJoin('case_contact as cc', 'cc.case_id', '=', 'c.id')
        ->leftJoin('case_statuses as cs', 'cs.case_id', '=', 'c.id');    
        
        if(isset($filter['amount']) && !empty($filter['amount'])){
            $query->where('p.amount',$filter['amount_operator'],$filter['amount']);
        }

        if(isset($filter['status']) && !empty($filter['status'])){
            $query->where('p.status_id','=',$filter['status']);
        }

        if(isset($filter['company_id']) && !empty($filter['company_id'])){
            $query->where('p.parent_id','=',$filter['company_id']);
        }

        if (!empty($filter['dates']) && $filter['dates'] != 'all_time') {
            $date_field = 'p.created';
            if ($filter['dates'] == 'day') {
                $query->whereBetween($date_field, array(
                    date('Y-m-d 00:00:00', strtotime($filter['date'])),
                    date('Y-m-d 23:59:59', strtotime($filterp['date']))
                ));
            } elseif ($filter['dates'] == 'month') {
                $query->whereBetween($date_field, array(
                    date('Y-m-', strtotime($filter['date'])) . '01 00:00:00',
                    date('Y-m-t' , strtotime($filter['date'])). ' 23:59:59'
                ));
            } elseif ($filter['dates'] == 'year') {
                $query->whereBetween($date_field, array(
                    date('Y-', strtotime($filter['date'])) . '01-01 00:00:00',
                    date('Y-' , strtotime($filter['date'])). '12-31 23:59:59'
                ));
            }elseif ($filter['dates'] == 'custom') {
                $query->whereBetween($date_field, array(
                    date('Y-m-d', strtotime($filter['start_date'])). ' 00:00:00', 
                    date('Y-m-d', strtotime($filter['end_date'])) . ' 23:59:59'
                ));
            }
        }
        
            $query = system\Access::queryAccess($query);
            if($group_by) {
                $results = $query->get()->groupBy($group_by)->toArray();
            }else{
                $results = $query->get()->toArray();
            }
            
        return $results;
    }


    public function update_($id, $data){
        if(isset($data['date_due'])){
            $data['date_due'] = date('Y-m-d H:i:s', strtotime($data['date_due']));
        }
        $db = Payment::find($id);  
        $db->date_due = date('Y-m-d H:i:s', strtotime($data['date_due']));
        $db->save();
    }

    public function deleted_($id){
        $db = Payment::find($id);
        $db->active = 0;
        $db->save();
    }


    public function findNextPayment($case_id){
        $result = Payment_schedule::where('case_id', $case_id)
                ->whereIn('status', ['pending','processing','hold'])
                //   ->where('active', 1)
                ->orderBy('date_due', 'Asc')
                ->limit(1)
                ->get();
    }


    public function getTotalPayments($case_id, $all_scheduled = false){
        $query = Payment::select('id', 'amount')
                ->where('case_id', $case_id)
                ->where('active', '!=', '0');
        if($all_scheduled){
            $query->whereNotIn('status_id', array(5,3));
        }else{
            $query->where('status_id', 3);
        }
        $result = $query->get();
        
        $payments = array();
        foreach($result->toArray() as $p){
            $payments[] = $p['amount'];
        }
        return array_sum($payments);
    }


    public function process($case_id, $data){

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
            $db = new Payment();
            $db->status_id = $new['status'];
            $db->paid_to_id = $new['received_by'];
            $db->paid_date = $new['date_received'];
            $db->comment = $new['received_note'];
            $db->updated = $new['updated'];
            $db->updated_by = $new['updated_by'];
            $db->case_id = $new['case_id'];
            $db->amount = $new['amount'];
            $db->date_due = $new['date_due'];
            $db->created = $new['created'];
            $db->created_by = $new['created_by'];
            $db->save();
            
        }else{
            $db = Payment::where('case_id', $case_id)->where('id', $data['id'])->first();
            $db->status_id = $payment['status'];
            $db->paid_to_id = $payment['received_by'];
            $db->paid_date = $payment['date_received'];
            $db->comment = $payment['received_note'];
            $db->updated = $payment['updated'];
            $db->updated_by = $payment['updated_by'];
            $db->amount = $payment['amount_received'];
            $db->save();
        }

    }
    // problem
    public function generatePaymentPlan($data){

        $minimum_payment_amount = 25;

        $payments_made = self::getTotalPayments($data['case_id']);
        $payments_scheduled = AccountingPaymentSchedule::getAllSchedulesSum($data['case_id'], true);
        $total_payments_due = AccountingInvoice::getTotalByCaseID($data['case_id']) - ($payments_made + $payments_scheduled); // revisit
        /*if($total_payments_due == 0){
            \Notification\Notify::error('Payments are already scheduled for the entire balance due');
        }*/
        if($data['generate_by'] == 'number'){
            $payments_amount = $total_payments_due / $data['number_payments'];
        }else{
            $payments_amount = $data['payment_amount'];
            $data['number_payments'] = ceil($total_payments_due / $payments_amount);
        }
        $payments = array();
        $last_payment = false;
        $sch_payments = array();
        $last_pending_payment_date = AccountingPaymentSchedule::getLastPendingDate($data['case_id']);
        $start_date = (empty($last_pending_payment_date)?$data['start_date']:$last_pending_payment_date);
        $date_due = date('Y-m-d', strtotime($start_date));
        for($i=1;$i<=$data['number_payments'];$i++){

            if($i>1 || !empty($last_pending_payment_date)){
                $date_due = date('Y-m-d', strtotime('+'.$data['payment_frequency'], strtotime($date_due)));
            }

            $next_period_due = $total_payments_due-(array_sum($sch_payments)+$payments_amount);

            if($next_period_due < $minimum_payment_amount){
                $last_payment = true;
            }elseif($i==$data['number_payments']){
                $last_payment = true;
            }

            $payment = array(
                'case_id' => $data['case_id'],
                'amount' => ($last_payment?$total_payments_due-array_sum($sch_payments):$payments_amount),
                'date_due' => $date_due,
                'created' => date('Y-m-d H:i:s'),
                'created_by' => $_SESSION['user']['id'],
                'updated' => date('Y-m-d H:i:s'),
                'updated_by' => $_SESSION['user']['id']
            );

            AccountingPaymentSchedule::create($payment);
            if($last_payment){
                return;
            }
            $sch_payments[] = $payments_amount;
        }
    }

    public function getPaymentPlanSummary($case_id){
        $payments = Payment_schedule::select('amount')
        ->where('case_id', $case_id)
        ->orderBy('date_due', 'Asc')
        ->get()
        ->toArray();
        
        $plan = Payment::selectRaw('MIN(date_due) as pay_start_date, MAX(date_due) as pay_end_date,
                COUNT(id) as pay_payments')
                ->where('case_id', $case_id)
                ->groupBy('case_id')
                ->get()
                ->toArray();
                
        $i = 1;  
        foreach($payments as $p){
            $hhh['pay_payment_amount'.$i] = $p['amount'];
            $i++;
        }
        $plan = array_merge($plan[0], $hhh);
        $plan['pay_total_payments'] = count($payments);
        
        if($plan['pay_payments'] == 0){
            $plan['pay_schedule'] = null;
            return $plan;
        }
        
        $start_date = new DateTime($plan['pay_start_date']);
        $end_date = new DateTime($plan['pay_end_date']);
        
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

  /** 
   *  public method that delivers all
   *  payments with status = Pending
   *  @return mix     matrix of records
   **/
  public function getProcessingPayments($processor=false){
      $query = Payment_schedule::from('payment_schedules as ps')
              ->select('ps.*', 'c.company_id')
              ->leftJoin('cases as c', 'c.id', 'ps.case_id')
              ->where('ps.status_id', 1)
              ->where('ps.transaction_id', null)
              ->orderBy('ps.updated', 'Desc');

      if($processor){
          $query->orWhere('ps.processor', $processor);
      }
      $result = $query->get();
      return $result;
  }


  // Ready to Purchase
  public function findAccountsPaidDownIds(){
      $result = Payment::from('payments as p')
                      ->select('p.case_id')
                      ->leftJoin('case_statuses as cs', 'cs.case_id','p.case_id')
                      ->where('p.status_id', 3)
                      ->where('p.active', 1)
                      ->where('p.processor', 'EQUITABLE')
                      ->whereIn('cs.status_id',[452, 453])
                      ->get()
                      ->toArray();
      $ids = array();
      if($result){
          foreach($result as $file){
              $ids[] = $file['case_id'];
          }
      }
      return $ids;
  }


  public function findPendingDepositTrustReady(){
      $result = Payment::from('payments as p')
              ->select('p.case_id')
              ->leftJoin('case_statuses as cs', 'cs.case_id', 'p.case_id')
              ->leftJoin('payment_schedules as ps', 'ps.id', 'p.schedule_id')
              ->where('p.status_id', 3)
              ->whereIn('cs.account_type_id', [3,2])
              ->whereIn('ps.type_id', [12,14])
              ->where('p.amount','>', 99)
              ->where('cs.status_id', 480)
              ->where('p.active', 1)
              ->groupBy('p.case_id')
              ->get()
              ->toArray();
      $ids = array();
      if($result){
          foreach($result as $file){
              $ids[] = $file['case_id'];
          }
      }
      return $ids;
  }

  public function findPendingDepositReady(){
      $result = Payment::from('payments as p')
              ->select('p.case_id')
              ->leftJoin('case_statuses as cs', 'cs.case_id', 'p.case_id')
              ->leftJoin('payment_schedules as ps','ps.id', 'p.schedule_id')
              ->where('p.status_id', 3)
              ->whereIn('cs.account_type_id', [1, 7])
              ->whereIn('ps.type_id', [12, 14])
              ->where('p.amount', '>', 100)
              ->where('cs.status_id', 480)
              ->where('p.active', 1)
              ->groupBy('p.case_id')
              ->get()
              ->toArray();
      $ids = array();
      if($result){
          foreach($result as $file){
              $ids[] = $file['case_id'];
          }
      }  
      return $ids;
  }
}
