<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\payments\PaymentsReminder;
use App\Models\account\AccountCompany;
use App\Models\account\Accounting_type;
use App\Models\cases\interview\Case_interview_log;
use App\Models\cases\Case_contact;
use App\Models\cases\CasesDocument;
use App\Models\cases\Case_additional;
use App\Models\cases\Case_status;
use App\Models\cases\Case_stage;
use App\Models\cases\CaseView;
use App\Models\cases\CasesFilter;
use App\Models\company\CompanyProfile;
use App\Models\document\DocumentCompanies;
use App\Models\document\DocumentDownloads;
use App\Models\document\DocumentLog;
use App\Models\document\DocumentTemplate;
use App\Models\reporting\ReportingAccounting;
use App\Models\reporting\ReportingActions;
use App\Models\system\document\SystemDocumentFields;
use App\Models\student\StudentLoans;
use App\Models\emailmarketing\EmailmarketingCampaign;
use App\Models\system\form\section\SystemFormSectionFields;
use App\Models\system\form\SystemFormFields;
use Aws\S3\S3Client;
class EventsController extends Controller
{
    /**
     * Constructor.
     *
     */
    public function __construct()
    {
        $_SESSION['user']['id'] = 6;
        $_SESSION['user']['first_name'] = "first";
        $_SESSION['user']['last_name'] = "last";

    }
    
    public function findById(Request $request)
    {
        $event_id = $request->event_id;
        $events = new Events();
        $data = $events->find($event_id);
        $query = $events->logger('last');
        return ['status' => 'success', 'data' => $data, 'query' => $query];
    }
    
    public function findByUser(Request $request)
    {
        $user_id = $request->user_id;
        $offset = $request->offset;
        $limit = $request->limit;
        $events = new Events();
        $data = $events->findByUser($user_id, $offset, $limit);
        $query = $events->logger('last');
        return ['status' => 'success', 'data' => $data, 'query' => $query];
    }
    
    public function findByCase(Request $request)
    {
        $case_id = $request->case_id;
        $events = new Events();
        $data = $events->findByCase($case_id);
        $query = $events->logger('last');
        return ['status' => 'success', 'data' => $data, 'query' => $query];
    }
    
    public function findByCompanyID(Request $request)
    {
        $company_id = $request->company_id;
        $events = new Events();
        $data = $events->findByCompanyID($company_id);
        $query = $events->logger('last');
        return ['status' => 'success', 'data' => $data, 'query' => $query];
    }
    
    public function findAllByCase(Request $request)
    {
        $case_id = $request->case_id;
        $events = new Events();
        $data = $events->findAllByCase($case_id);
        $query = $events->logger('last');
        return ['status' => 'success', 'data' => $data, 'query' => $query];
    }

    
    public function testAPI(Request $request)
    {
        $message = "";
        $events = new Event();
        //
        $message = "availabilityAlgorithm test";
        $event_data = array(
          array(
            "start"=> "2021-04-03 07:40:00",
            "end"=> "2021-04-03 07:40:00"
          )
        );
        $start = "6 April 2021";
        $end = "2021-04-10 07:40:00";
        $duration = 172800;
        $user_id = 1;
        
        $data = $events->availabilityAlgorithm($event_data, $start, $end, $duration, $user_id);
        //exit;
        $filter = array('amount'=>500, 'amount_operator'=>'>', 'amount_company'=>'3', 'dates'=>'2020-11-11 05:40:45');
        $payment = new SystemFormFields();
        $data = [
                    'company_id'=>3,
                    'processor_id'=>9,
                    'user_fee'=>0.98,
                    'processing_fee'=>0.33,
                    'renewal_fee'=>93
                ]; 
        $client = new S3Client([
            'region'  => 'us-west-2',
            'version' => 'latest',
            'http'    => ['cert' => '/path/to/cert.pem']
        ]);
        $update = $payment->findByFormId(1);
        dd($update);
        $query = $events->findCases();
        return ['status' => 'success', 'data' => $update, 'query' => $query, 'message' => $message];
    }
}
