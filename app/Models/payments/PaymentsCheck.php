<?php

namespace App\Models\payments;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Log;
use App\Models\Cases;
use App\Models\system\SystemEmail;
class PaymentsCheck extends Model
{
    use HasFactory;
    var $total;
    var $count = 1;
    var $processed = 0;
    var $nsf = 0;
    var $paid = 0;
    var $error = 0;
    var $processing = 0;
    var $processorError;
    // problem Accounting\Model_Gateways not exist
    function run($schedules){
        $this->total = count($schedules);
        $this->setUser();
        $this->sendEmail('start');
        if (count($schedules)==0) {
            Log::transaction('No pending records to process');
            return 0;
        }

        $processed = 0;
        $errorProcessor = array();

        foreach($schedules as $schedule) {

            $case = Cases::find($schedule['case_id']);
            $company_id = $case['company_id'];
            $gateway = \Accounting\Model_Gateways::findGateway($schedule['gateway_id']);

            /* ERROR CHECKS */
            $errors = array();
            // Find out which is the payment processor
            if(!isset($schedule['gateway_id'])){
                $errors[] = 'Has no PROCESSOR set, please fix.';
            }
            if(!isset($company_id)){
                $errors[] = 'Need company ID';
            }
            if(isset($errorProcessor) && !empty($errorProcessor)){
                if(in_array($schedule['processor'], $errorProcessor)){
                    continue;
                }
            }
            if(!empty($errors)){
                Log::transaction($this->count.'/'.$this->total .':'."PAYMENT CHECK: ".$schedule['case_id']." - Errors: ".implode(",", $errors));
                $this->count++;
                continue;
            }

            // Execute the status check on the payment processor
            try {
                // Dynamically call the gateway
                $gateway_m = "\\Accounting\\Model_Gateway_".ucfirst(strtolower($gateway['module']));
                $processor = new $gateway_m();
                // Create the necessary information for ALL payment processors to be happy
                $processor_data = array(
                    'custom_1' => $schedule['custom_1'],
                    'transaction_id' => $schedule['transaction_id'],
                    'process_date' => $schedule['process_date'],
                    'case_id' => $schedule['case_id']
                );
                // Get transaction status
                $response = $processor->check($processor_data, $gateway);
                // If the status is paid:
                $this->processResponse($response, $schedule);

            } catch(\Exception $event) {
                // Gateway processor is non-existant - warn or let go ?
                $logmessage = $this->count.'/'.$this->total .':'."CASE ID: ".$schedule['case_id']." - Status: Error: " . $event->getMessage().' Line:'.$event->getLine().' Trace: '.$event->getTraceAsString(). ' File: '.$event->getFile();
                Log::transaction($logmessage);
            }


            $this->count++;


        }


        $this->sendEmail('end');
        return 'CRON JOB FOR PAYMENT TASK has finished. Paid: '.$this->paid.', Processing: '.$this->processing.', NSF: '.$this->nsf.' Errors: '.$this->error.' Count for run was ' . $this->total;

    }
    // problem Accounting foler not exist
    function single($schedule_id){

        $this->setUser();
        $schedule = \Accounting\Model_Payment_Schedules::findDetailedById($schedule_id);
        $case = \Model_Case::find($schedule['case_id']);
        $gateway = \Accounting\Model_Gateways::findGateway($schedule['gateway_id']);
        $company_id = $gateway['company_id'];
        /* ERROR CHECKS */
        $errors = array();
        // Find out which is the payment processor
        if(!isset($schedule['gateway_id'])){
            $errors[] = 'Has no PROCESSOR set, please fix.';
        }
        if(!isset($company_id)){
            $errors[] = 'Need company ID';
        }

        if(!empty($errors)){
            \Model_Log::transaction($this->count.'/'.$this->total .':'."PAYMENT CHECK: ".$schedule['case_id']." - Errors: ".implode(",", $errors));
            return false;

        }

        // Execute the status check on the payment processor
        try {
            // Dynamically call the gateway
            $gateway_m = "\\Accounting\\Model_Gateway_".ucfirst(strtolower($gateway['module']));
            $processor = new $gateway_m();
            // Create the necessary information for ALL payment processors to be happy
            $processor_data = array(
                'custom_1' => $schedule['custom_1'],
                'transaction_id' => $schedule['transaction_id'],
                'process_date' => $schedule['process_date'],
                'case_id' => $schedule['case_id']
            );
            // Get transaction status
            $response = $processor->check($processor_data, $gateway['params']);
            // If the status is paid:
            $logmessage = $this->processResponse($response, $schedule);

        } catch(\Exception $event) {
            // Gateway processor is non-existant - warn or let go ?
            $logmessage = $this->count.'/'.$this->total .':'."CASE ID: ".$schedule['case_id']." - Status: Error: " . $event->getMessage().' Line:'.$event->getLine().' Trace: '.$event->getTraceAsString(). ' File: '.$event->getFile();

            \Model_Log::transaction($logmessage);

        }

        return $logmessage;

    }

    // problem Accounting folder not exist
    function processResponse($response, $schedule){

        /* If Response is anything but pending */
        $payment_added_result = false;
        if (in_array($response['status_id'],array(3,4,5,6))) { // Not Processing

            $receipt_payload = array(
                'case_id' => $schedule['case_id'],
                'parent_id' => $schedule['company_id'],
                'schedule_id' => $schedule['id'],
                'amount' => $schedule['amount'],
                'status_id'=>$response['status_id'],
                'created'=>date('Y-m-d H:i:s'),
                'created_by'=>1, // System user ID
                'updated'=>date('Y-m-d H:i:s'),
                'updated_by'=>1, // System user ID
                'gateway_code'=>$response['code'],
                'gateway_response'=>$response['reason'],
                'comment' => NULL,
                'transaction_id'=> $schedule['transaction_id'],
                'date_due'=> $schedule['date_due'],
                'active'=>1,
                'gateway_id'=> $schedule['gateway_id'],
                'processor'=>strtoupper($schedule['processor'])
            );


            try {

                \DB::start_transaction();
                $receipt_id = \Accounting\Model_Payments::create($receipt_payload);
                $payment_added_result = \Controller_Accounting_Action::createPaymentAndUpdateSchedule($receipt_payload, $receipt_id);
                \DB::commit_transaction();

            }catch(\Exception $e){

                \DB::rollback_transaction();
                \Fuel\Core\Log::error($e);

            }

            if (in_array($response['status_id'], array(4, 5))) {
                $this->nsf++;
            }else if(in_array($response['status_id'], array(3))) {
                $this->paid++;
            }else if(in_array($response['status_id'], array(6))){
                $this->error++;
            }

        }else{
            // Still in Processing or has errors.
            if($response['status_id'] == 1){
                // Processing
                $update = array(
                    'updated'=>date('Y-m-d H:i:s'),
                    'updated_by'=>1, // System user ID
                    'gateway_response'=>$response['reason']
                );
                $this->processing++;
            }

            \Accounting\Model_Payment_Schedules::update($schedule['id'], $update);
            //\Accounting\Model_Payment_Schedules::renumberPayments($schedule['case_id']);

        }

        // Log the action
        $logmessage = $this->count.'/'.$this->total .':'. $schedule['processor'] . ":CASE ID: ".$schedule['case_id']." - Status: ";
        $logmessage .= "\n";
        $logmessage .= 'Update on the database '.(!$payment_added_result?'UN':'').'SUCCESSFUL.';

        return $logmessage;
    }


    function setUser(){
        $_SESSION['user']['id'] = 1;
        $_SESSION['user']['first_name'] = 'System';
        $_SESSION['user']['last_name'] = 'Administrator';
    }

    function hasErrors(){
        if(isset($this->error) && !empty($this->error)){

        }
    }

    function sendEmail($type){

        if($type == 'start') {
            SystemEmail::sendEmail('brian@aperturecode.com',
                'info@studentloansupport.us',
                'Payment:Check Task Started',
                'CRON JOB FOR PAYMENT TASK has started. Count for run is ' . $this->total, 100);
        }else{
            SystemEmail::sendEmail('brian@aperturecode.com',
                'info@studentloansupport.us',
                'Payment:Check Task Finished',
                'CRON JOB FOR PAYMENT TASK has finished. Paid: '.$this->paid.', Processing: '.$this->processing.', NSF: '.$this->nsf.' Errors: '.$this->error.' Count for run was ' . $this->total, 100);
        }


    }

}
