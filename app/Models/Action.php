<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Action extends Model
{
    use HasFactory;
    private $case_id;
        private $action_id;
        private $note;
        private $to_user;
        private $action_obj;
        private $action_user_id;
        private $reason_ids;
        /**
         * @param $case_id
         * @param $action_id
         * @param string $note
         * @param int $user_id
         * @param bool $inc_count
         */

        function __construct($case_id, $action_id, $reason_ids=null){

            if(empty($action_id)){
                throw new Exception('No Action ID set');
            }

            if(empty($case_id)){
                throw new Exception('No Case ID set to run Action');
            }

            $this->case_id = $case_id;
            $this->action_id = $action_id;
            $this->reason_ids = $reason_ids;
            $this->action_obj = Model_System_Action::find($action_id);
            $this->action_user_id = Model_Account::getUserId();
        }

        function setToUser($user_id){
            $this->to_user = $user_id;
        }

        function setNote($note){
            $this->note = $note;
        }

        function setCase($case_id){
            $this->case_id = $case_id;
        }

        function setReasonIDs(array $reason_ids){
            $this->reason_ids = $reason_ids;
        }

        function run(){

                $action_id = $this->logAction();
                //$this->logActionReasons($action_id);
                //$this->addActivity();
                //$this->addNote();
                $this->increaseActionCount();
                //$this->workflowNotification();
                $this->performTasks();

            return true;
        }


        function workflowNotification(){
            // Workflow Notification
           /* if (isset($this->reason_ids) && !empty($this->reason_ids)) {
                Model_Log::append('reasons', $this->case_id. ' '. implode(', ',$this->reason_ids));
                foreach($this->reason_ids as $reason_id){
                    $notification = new \Workflow\Notification($this->case_id, \Workflow\Notification::EVENT_RAN_ACTION_WITH_REASON, $reason_id);
                    \Workflow\EventObserver::notify($notification);
                }
            } else { */
            $notification = new \Workflow\Notification($this->case_id, \Workflow\Notification::EVENT_RAN_ACTION, $this->action_id);
            \Workflow\EventObserver::notify($notification);

        }

        function addNote(){
            if(!isset($this->note) || empty($this->note)){
                return false;
            }
            $note = array(
                'note_type' => 1, // Sales
                'note' => $this->note
            );
            Model_Note::add($this->case_id, $note);
        }

        function reasonIDsToString($ids){
            if(isset($this->reason_ids) && !empty($this->reason_ids)){
                $reasons = Model_System_ReasonListItem::findByIds($this->reason_ids);
                if($reasons && is_array($reasons)){
                    $reason_names = array();
                    foreach($reasons as $reason){
                        $reason_names[] = $reason['name'];
                    }
                    return implode(", ",  $reason_names);
                }

            }
            return false;
        }

        function addActivity(){
            $activity = Model_System_Activity::find($this->action_obj['activity_id']);
            $activity_msg = $this->action_obj['name'];
            $reason_text = $this->reasonIDsToString($this->reason_ids);
            if($reason_text){
               $activity_msg = $this->action_obj['name'] . ' - Reasons: ' . $reason_text;
            }
            if($activity) {
                Model_Log::addActivity($this->case_id, $activity['name'], $activity_msg);
            }
        }

        function logAction(){
            if(!empty($this->action_obj['tracking']) && $this->action_obj['tracking'] != 'none'){
               $action_id =  Model_Log::logAction($this->action_obj['tracking'],$this->action_user_id, $this->case_id, $this->action_id, $this->action_obj['group_id'], $this->to_user);
                return $action_id;
            }
        }

        static function logActionIDs($action, $case_id, $target_id, $failed=null){
            $error = null;
            if($failed){
                $error = 1;
            }

           \DB::insert('log_action_ids')->set(array('action' => $action, 'case_id' => $case_id, 'target_id' => $target_id, 'error' => $error, 'created' => date("Y-m-d H:i:s")))->execute();
        }

        function logActionReasons($log_action_id){
            if(!empty($this->reason_ids) && isset($this->reason_ids)){
                $reasons = Model_System_ReasonListItem::findByIds($this->reason_ids);
                if($reasons && is_array($reasons)) {
                    foreach ($reasons as $reason) {
                        Model_Log::logActionReason($this->case_id, $log_action_id, $reason['reason_list_item_id']);
                    }
                }
            }
        }

        function increaseActionCount(){
            Model_Case::increaseActionCount($this->case_id);
        }

        function performTasks(){

            $action_tasks = \DB::select('at.label','asts.target_id')
                ->from(array('actions_tasks','asts'))
                ->join(array('action_tasks', 'at'))->on('at.id', '=', 'asts.task_id')
                ->where('asts.action_id', '=', $this->action_id)
                ->execute()
                ->as_array();

            if($action_tasks){
                // gets each action function in set as label and runs it
                foreach($action_tasks as $task){
                    $action = $task['label'];
                    try {
                        self::logActionIDs($action, $this->case_id, $task['target_id']);
                         self::$action($this->case_id, $task['target_id']);
                    }catch(\Exception $e){
                        self::logActionIDs($action, $this->case_id, $task['target_id'], $e->getMessage());
                        Model_Log::file('actions',$action.':'.$e->getMessage());
                        \Fuel\Core\Log::error($e);
                    }

                }
                return true;
            }

            return false;
        }


        static function performTask($task, $case_id, $target_id){
            try {
                self::logActionIDs($task, $case_id, $target_id);
                self::$task($case_id, $target_id);
            }catch(\Exception $e){
                self::logActionIDs($task, $case_id, $target_id, $e->getMessage());
                Model_Log::file('actions',$task.':'.$e->getMessage());
                \Fuel\Core\Log::error($e);
            }
        }

        /**
         * @param $case_ids
         * @param $action_id
         * @return string
         */
        static function runBatch($case_id, $action_id){

            $action = Model_System_Action::find($action_id);
            
            $result = \DB::select('at.label','asts.target_id')
                                ->from(array('actions_tasks','asts'))
                                ->join(array('action_tasks', 'at'))->on('at.id', '=', 'asts.task_id')
                                ->where('asts.action_id', '=', $action_id)
                                ->execute();

                Model_Log::addActivity($case_id, 'Action', $action['name'], 'Batch Action', Model_System_User::getSessionMeta('id'));
                
                foreach($result->as_array() as $row){
                    $action_task = $row['label'];
                    self::$action_task($case_id, $row['target_id']);
                }

            return true;

        }

        /**
         * @param $case_id
         * @param $target_id
         */
        static function changeStatus($case_id, $target_id){
            $type = Model_System_Status::find($target_id);
            Model_Case::UpdateStatusOnce($case_id, $target_id, $type['status_field']);
        }
        /**
         * @param $case_id
         * @param $target_id
         */
        static function sellCase($case_id, $target_id){
            $data = array('company_id' => $target_id);
            Model_Case::update($case_id, $data);
        }

        static function refundCase($case_id, $target_id){
            $data = array('company_id' => $target_id);
            Model_Case::update($case_id, $data);
        }

        static function shareCase($case_id, $target_id){
            Model_Case::share($case_id, array('company_id' => $target_id));
        }

        static function calculateRenewal($case_id, $target_id){

           $field_id = 265;
           $repayment_start_date = Model_System_Additional::findByField($case_id, $field_id);

            if (isset($repayment_start_date) && !empty($repayment_start_date)) {
                $renewal_date = date('Y-m-d', strtotime($repayment_start_date . ' +9 months'));
                Model_CaseStatuses::update($case_id, array('renewal_date' => $renewal_date));
            }

        }


        static function clearIssues($case_id, $target_id){
           \Workflow\Model_Issue::deleteAll($case_id);
        }

        /**
         * @param $case_id
         * @param $target_id
         */
        // problem Module class not exist
        static function sendEmail($case_id, $target_id, $extra_fields = array()) {

            \Module::load('emailmanager');

            $case_fields = Model_Case::getCaseObjects($case_id);

            if(!\Model_System_Services_Access::check(\Model_System_Services_Access::EMAIL_SERVICE, $case_fields['company_id'])){
                return false;
            }

            $interview_fields = Model_System_Additional::find($case_id);
            $company_fields = Model_System_Company::find($case_fields['company_id']);

            $all_fields = array_merge($case_fields, $interview_fields, $company_fields, $extra_fields);
            $template = Model_System_Email::find($target_id);

            if(!isset($template) || empty($template)){
                return false;
            }

            // Check if email sent within 24 hours, stop it. Look at Template ID AND Email sent to


            $email = Model_System_Form_Fields::parseTemplate($template, $all_fields);
            
            if(!isset($email['to']) || empty($email['to'])){
                return false;
            }

            if(isset($email) && !empty($email)){

                // Build Record
                $email_data = array(
                    'case_id' => $case_id,
                    'direction' => 'outbound',
                    'subject' => $email['subject'] . ' {MSG:}',
                    'body' => $email['message'],
                    'cc_to' => (isset($email['cc'])?$email['cc']:null),
                    'bcc_to' => (isset($email['bcc'])?$email['bcc']:null),
                    'created_date' => date('Y-m-d H:i:s'),
                    'created_by' => 1,
                    'in_queue' => 1,
                    'email_to' => $email['to'],
                    'message_user_id' => 1,
                    'viewed_date' => date('Y-m-d H:i:s'),
                    'viewed_by' => 1,
                    'template_id' => $target_id
                );

                $email_id = \EmailManager\Model_Emails::add($email_data);

                if($email_id){
                    $msg_update = array(
                        'subject' => $email['subject'] . ' {MSG:'. $email_id.'}',
                    );
                    \EmailManager\Model_Emails::update($email_id, $msg_update);

                    \Model_Log::addActivity($case_id, 'EMAIL', 'SENT - Subject: ' . strip_tags(substr($msg_update['subject'], 0, 200)));

                }

            }
        }

        /**
         * @param $case_id
         * @param $target_id
         * @throws Exception
         */

        static function sendText($case_id, $target_id, $extra_fields = array()){

            //throw new Exception('Send Text is not currently available in production');
            $fields = Cases::getCaseObjects($case_id);

            // Check SMS Access
            if(!\Model_System_Services_Access::check(\Model_System_Services_Access::SMS, $fields['company_id'])){
                throw new Exception('Company Not Authorized '.$fields['company_id']);
            }

            // Find Template
            $text_template = Model_System_SMS::find($target_id);
            $ct_record = \Model_Sms::getById($text_template['ct_id']);

            // If Owner of Number doesnt match owner of file, look up number for owner of file
            if($fields['company_id'] !== $ct_record['company_id']){

               $ct_record = Model_Sms::findDefaultByCompany($fields['company_id']);

               if(!$ct_record){
                   throw new Exception('No Default Company SMS Number '.$fields['company_id']);
               }

            }

            // Merge Any Extra Fields with Case Fields
            $fields = array_merge($fields, $extra_fields);
            // Parse Template with Field Set
            $template = Model_System_Form_Fields::parseTemplate($text_template, $fields);
            // Clean To Number
            $regex = "/^(\d[\s-]?)?[\(\[\s-]{0,2}?\d{3}[\)\]\s-]{0,2}?\d{3}[\s-]?\d{4}$/i";
            $number = preg_replace('/[^0-9]/','', $template['to']);
            $to_number =  (preg_match( $regex, $number ) ? $number : null);

            if(!$to_number) {
                throw new Exception('No Valid SMS mobile number');
            }

            if(strlen($ct_record['number']) == 5){
                // Short Code
                $from = $ct_record['number'];
            }else{
                // Long Code
                $from = '+1' . $ct_record['number'];
            }

            // Send it!
            $result = Model_SMS::send('+1' . $to_number, $ct_record['number'], $template['message'], $ct_record);

            // Count Message Characters
            $smsCounter = new \Instasent\SMSCounter\SMSCounter();
            $sms_counter = $smsCounter->count($template['message']);

            // Add to SMS Records
            Model_SMS::add(array(
                'to' => $template['to'],
                'from' => $ct_record['number'],
                'ct_id' => $ct_record['id'],
                'message' => $template['message'],
                'segments' => $sms_counter->messages,
                'sid' => $result,
                'case_id' => $case_id,
                'direction' => 'outbound',
                'message_user_id' => \Model_Account::getUserId(),
                'viewed_date' => date('Y-m-d H:i:s'),
                'viewed_by' => \Model_Account::getUserId(),
                'archived' => 1,
                'template_id' => $target_id
            ));

            // Record Activity on File
            if ($result) {
                Model_Log::addActivity($case_id, 'SMS', 'Texted to: ' . $template['to'] . ' from ' . $from . ' reads "' . $template['message']);
                return true;
            }

            return false;

        }

        /**
         * @param $case_id
         * @param $target_id
         */
        static function addEmailCampaign($case_id, $target_id){
            Model_EmailMarketing_Templates::addCampaignToCase($target_id, $case_id);
        }

        static function sendExpressDeposit($case_id, $target_id=null){
           $result =  \Financing\Model_Financing::sendOneExpressReceipt($case_id);
            if($result){
                \Financing\Model_Workflow::upsert($case_id, array('express_sent' => date('Y-m-d H:i:s'),'case_id' => $case_id));
            }

        }

        static function releaseTrust($case_id, $target_id=null){
            $result =  \Financing\Model_Financing::sendOneExpressReceipt($case_id);

        }

        static function dial($case_id, $target_id){

            /**$queue = new Model_Queue_Timers();
            if($queue->isAfterHours()){
                $queue->addToQueue('dial', $case_id, $target_id);
                return false;
            }**/

            $fields = \Model_Case::getCaseObjects($case_id);
            $template = Model_System_Autocalls::find($target_id);
            // Parse Template with Field Set
            $parsed_template = Model_System_Form_Fields::parseTemplate($template, $fields);
            // Clean To Number
            $regex = "/^(\d[\s-]?)?[\(\[\s-]{0,2}?\d{3}[\)\]\s-]{0,2}?\d{3}[\s-]?\d{4}$/i";
            $number = preg_replace('/[^0-9]/','', $parsed_template['to_phone']);
            $to_number =  (preg_match( $regex, $number ) ? $number : null);

            if(!$to_number) {
                throw new Exception('No Valid SMS mobile number');
            }

            \CallCenter\Model_Dialer::caller_id_dial($fields['company_id'],$to_number, $template['extension'], $template['caller_id']);

        }

        static function broadcast($case_id, $target_id){

            /**$queue = new Model_Queue_Timers();
            if($queue->isAfterHours()){
                $queue->addToQueue('broadcast', $case_id, $target_id);
                return false;
            }**/

            $fields = \Model_Case::getCaseObjects($case_id);
            $template = Model_System_Autocalls::find($target_id, $fields['company_id']);
            // Parse Template with Field Set
            $parsed_template = Model_System_Form_Fields::parseTemplate($template, $fields);
            // Clean To Number
            $regex = "/^(\d[\s-]?)?[\(\[\s-]{0,2}?\d{3}[\)\]\s-]{0,2}?\d{3}[\s-]?\d{4}$/i";
            $number = preg_replace('/[^0-9]/','', $parsed_template['to_phone']);
            $to_number =  (preg_match( $regex, $number ) ? $number : null);

            if(!$to_number) {
                throw new Exception('Invalid number to broadcast to');
            }

            if($template['is_script'] == 1){
                // IVR SCRIPT
                \CallCenter\Model_Dialer::ivr_dial($fields['company_id'],$to_number, $template['extension'], $template['caller_id'], $case_id, $template['script_name'], $template['dial_code']);
            }else{
                \CallCenter\Model_Dialer::broadcast($fields['company_id'],$to_number, $template['extension'], $template['caller_id'], $case_id);
            }


        }

        /**
         * @param $case_id
         * @param $target_id
         */
        static function notify($case_id, $target_id){

            $fields = Model_Case::getCaseObjects($case_id);
            $template = Model_System_Notification::find($target_id);

            if(!isset($template['department_id']) || empty($template['department_id'])){
                throw new \Exception ('Notification department not set on template');
            }

            $assignment = Model_Assignments::findByCaseAndDept($case_id,$template['department_id']);

            try {

                $note = Model_System_Form_Fields::parseTemplate($template, $fields);

                if(isset($note) && !empty($note) && isset($assignment['user_id']) && !empty($assignment['user_id'])) {

                    $notify = new \Notification\Model_Notification();
                    $notify->writeMessage($assignment['user_id'], $note['name'], $note['message'], $template['type'],$case_id, $template['icon'], false, $template['size'], $template['color']);

                }

            }catch(Exception $e){
                \Fuel\Core\Log::error($e);
            }

        }

        static function payment_plan($case_id, $value){

            switch($value){
                case 200:
                    $status = 'ACTIVE';
                    break;
                case 9:
                    $status = 'HOLD';
                    break;
                case 5:
                    $status = 'NSF';
                    break;
            }

            $update['accounting_updated'] = date('Y-m-d H:i:s');
            $update['accounting_status_id'] = $value;


            // Update Status
            \DB::update('case_statuses')->set($update)->where('case_id','=',$case_id)->execute();
            // Log for Reporting
            Model_Log::log_status($case_id, $value, \Model_Account::getUserId());
            // Log Activity
            if($value != 1) {
                $type = 'Payment Plan';
                Model_Log::addActivity($case_id, $type, $status, '', \Model_Account::getUserId(), $value);
            }

        }

        /**
         * @param $case_id
         * @param $target_id
         */
        static function flag($case_id, $target_id){

            $flag = Model_System_Flag::find($target_id);
            switch($flag['field_value']){
                case 'DATETIME':
                    $f = date('Y-m-d H:i:s');
                    break;
                case 'DATE':
                    if(isset($flag['inc_value']) && !empty($flag['inc_value'])){
                        $f = date('Y-m-d', strtotime($flag['inc_value']));
                    }else{
                        $f = date('Y-m-d');
                    }
                    break;
                default:
                    $f = $flag['field_value'];
                    break;
            }

            if(in_array($flag['id'], array(1,2))){
                \Model_Case_Stage::update($case_id, $flag['field_value']);
                $stage_name = Model_Case_Stage::getStageName($flag['field_value']);
                Model_Log::addActivity($case_id,'Stage',$stage_name);
                return true;

            }

            $data = array($flag['case_field'] => $f);
            if($flag['case_field'] == 'accounting_status_id'){
                $data['accounting_updated'] = date("Y-m-d H:i:s");
                self::payment_plan($case_id, $flag['field_value']);
                return false;

            }

            $result = \DB::update('case_statuses')->set($data)->where('case_id', '=', $case_id)->execute();

            if($flag['in_activity'] == 1){
                Model_Log::addActivity($case_id,'Flag',$flag['name']);
            }

        }

        static function changeCompany($case_id, $target_id){

            $case = Model_Case::find($case_id);
            $company = \Model_System_Company::find($target_id);

            if($case['company_id'] != $company['id']){

                Model_Log::addActivity($case_id,'Assignment','COMPANY assignment changed from '.$case['company'].' to '.$company['long_name'].' ('.$company['name'].')','',Model_Account::getUserId(),$case['company_id']);

            }

            $result = \DB::update('cases')->set(array('company_id'=>$target_id))->where('id', '=', $case_id)->execute();

        }

        static function changeProcessor($case_id, $target_id){

            $case = Model_Case::find($case_id);
            $company_profile = \Model_Company_Profile::find($case['company_id']);
            $new_processor = \Model_System_Company::find($company_profile['processor_id']);

            if(isset($case['processor_id']) && !empty($case['processor_id'])) {

                $current_processor = \Model_System_Company::find($case['processor_id']);

                if($current_processor['id'] != $new_processor['id']){
                    Model_Log::addActivity($case_id,'Assignment','Processor assignment changed from '.$current_processor['name'].' to '.$new_processor['long_name'].' ('.$new_processor['name'].')','',Model_Account::getUserId(),$current_processor['id']);
                }

            }else{

                Model_Log::addActivity($case_id,'Assignment','Processor assignment changed to '.$new_processor['long_name'].' ('.$new_processor['name'].')','',Model_Account::getUserId(),null);

            }

            $result = \DB::update('case_statuses')->set(array('processor_id'=>$new_processor['id']))->where('case_id', '=', $case_id)->execute();

        }


        static function assignTask($case_id, $target_id){

                \Tasks\Model_Tasks::assignToCaseUseTemplate($case_id, $target_id);

        }


        /**
         * @param $case_id
         * @param $target_id
         */
        static function export($case_id, $target_id){

            $queue = new Model_Queue();

            $job = new stdClass();
            $job->type = 'export';
            $job->target_id = $target_id;
            $job->case_id = $case_id;
            $job->user = \Model_Account::getUserObject();
            $job_data = json_encode($job);

            $queue->putInTube('export', $job_data);
            
        }

        static function addCaseLabel($case_id, $target_id){
           \Labels\Model_Case_Labels::upsert($target_id, $case_id);
        }
        static function removeCaseLabel($case_id, $target_id){
            \Labels\Model_Case_Labels::deleteByCaseAndType( $case_id, $target_id);
        }


        static function resetPaymentPlan($case_id, $target_id){
           \Accounting\Model_Payment_Schedules::resetPaymentPlan($case_id);
        }


        static function distributeToGroup($case_id, $group_id){

            // Get next user in distribution list
            $rep = Model_BatchDistribution::getNextUserGroup($group_id);
            $group  = Model_System_DistributionGroup::find($group_id);
            // Update the records for distribution
            Model_Assignments::batch_upsert($case_id, $group['department_id'], $rep['user_id']);
            //Log
            Model_BatchDistribution::logDistribution($rep['user_id'], $case_id);
            Model_BatchDistribution::saveLog();

            return true;

        }


        static function sendFinanceFiles($case_id)
        {

               $account_type_id = \Model_Case::findAccountTypeId($case_id);
               if(in_array($account_type_id, array(\Model_Account_Types::FINANCING, \Model_Account_Types::FINANCING_EXPRESS))) {
                   self::queueFinanceAudio($case_id);
                   self::queueFinancePOI($case_id);
               }

        }

        static function queueFinanceAudio($case_id)
        {

            $account_type_id = \Model_Case::findAccountTypeId($case_id);
            if(in_array($account_type_id, array(\Model_Account_Types::FINANCING, \Model_Account_Types::FINANCING_EXPRESS))) {
                \Financing\Model_Queue::sendFinanceAudio($case_id);
            }
            /*$queue = new Model_Queue();

            $job = new stdClass();
            $job->type = 'financing';
            $job->actions = 'sendFinanceAudio';
            $job->case_id = $case_id;
            $job->user = \Model_Account::getUserObject();
            $job_data = json_encode($job);

            $queue->putInTube('action', $job_data);*/
        }

        static function queueStudentBenefit($case_id)
        {

            $account_type_id = \Model_Case::findAccountTypeId($case_id);
            if(in_array($account_type_id, array(\Model_Account_Types::FINANCING, \Model_Account_Types::FINANCING_EXPRESS))) {
                \Financing\Model_Queue::sendStudentBenefit($case_id);
            }
            /*$queue = new Model_Queue();

            $job = new stdClass();
            $job->type = 'financing';
            $job->actions = 'sendFinanceAudio';
            $job->case_id = $case_id;
            $job->user = \Model_Account::getUserObject();
            $job_data = json_encode($job);

            $queue->putInTube('action', $job_data);*/
        }

        static function queueFinancePOI($case_id)
        {
            $account_type_id = \Model_Case::findAccountTypeId($case_id);
            if(in_array($account_type_id, array(\Model_Account_Types::FINANCING, \Model_Account_Types::FINANCING_EXPRESS))) {
                \Financing\Model_Queue::sendFinancePOI($case_id);
            }
            /*$queue = new Model_Queue();

            $job = new stdClass();
            $job->type = 'financing';
            $job->actions = 'sendFinancePOI';
            $job->case_id = $case_id;
            $job->user = \Model_Account::getUserObject();
            $job_data = json_encode($job);

            $queue->putInTube('action', $job_data);*/
        }

        static function queueFinanceConsolidation($case_id)
        {
            $account_type_id = \Model_Case::findAccountTypeId($case_id);
            if(in_array($account_type_id, array(\Model_Account_Types::FINANCING, \Model_Account_Types::FINANCING_EXPRESS))) {
                \Financing\Model_Queue::sendIDR($case_id);
            }
            /*$queue = new Model_Queue();

            $job = new stdClass();
            $job->type = 'financing';
            $job->actions = 'sendFinanceConsolidation';
            $job->case_id = $case_id;
            $job->user = \Model_Account::getUserObject();
            $job_data = json_encode($job);

            $queue->putInTube('action', $job_data);*/

        }

        static function queueFinanceWarranty($case_id)
        {
            $account_type_id = \Model_Case::findAccountTypeId($case_id);
            if(in_array($account_type_id, array(\Model_Account_Types::FINANCING, \Model_Account_Types::FINANCING_EXPRESS))) {
                \Financing\Model_Queue::sendFinanceWarranty($case_id);
            }
            /*$queue = new Model_Queue();

            $job = new stdClass();
            $job->type = 'financing';
            $job->actions = 'sendFinanceWarranty';
            $job->case_id = $case_id;
            $job->user = \Model_Account::getUserObject();
            $job_data = json_encode($job);

            $queue->putInTube('action', $job_data);*/

        }

        static function queueTrustPayment($case_id)
        {
            \Financing\Model_Queue::releaseTrustPayment($case_id);
            /*$queue = new Model_Queue();

            $job = new stdClass();
            $job->type = 'financing';
            $job->actions = 'releaseTrustPayment';
            $job->case_id = $case_id;
            $job->user = \Model_Account::getUserObject();
            $job_data = json_encode($job);

            $queue->putInTube('action', $job_data);*/

        }

        static function queueFinancingAgreement($case_id)
        {
            $account_type_id = \Model_Case::findAccountTypeId($case_id);
            if(in_array($account_type_id, array(\Model_Account_Types::FINANCING, \Model_Account_Types::FINANCING_EXPRESS))) {
                \Financing\Model_Queue::sendAgreement($case_id);
            }
            /*$queue = new Model_Queue();

            $job = new stdClass();
            $job->type = 'financing';
            $job->actions = 'sendAgreement';
            $job->case_id = $case_id;
            $job->user = \Model_Account::getUserObject();
            $job_data = json_encode($job);

            $queue->putInTube('action', $job_data);*/

        }



        static function lastActionId($case_id){
            $query = \DB::select('log_actions.id')
                ->from('log_actions')
                ->order_by('log_actions.created','DESC')
                ->where('log_actions.case_id','=', $case_id)
                ->limit(1);
            $data = current($query->execute()->as_array());
            if(isset($data['id'])){
                return $data['id'];
            }
            return false;

         }

        static function lastActionReasons($log_action_id){
            $query = \DB::select(array('reason_list_items.name', 'reason_name'))
                ->from('log_actions_reasons')
                ->join('reason_list_items','LEFT')->on('reason_list_items.id','=','log_actions_reasons.reason_id')
                ->where('log_actions_reasons.log_action_id','=',$log_action_id);
            return $query->execute()->as_array();
        }




}
