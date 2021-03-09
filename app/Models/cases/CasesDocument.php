<?php

namespace App\Models\cases;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Esign_doc;
use App\Models\Account;
use App\Models\DocumentTypes;
use App\Models\Pdf;
use App\Models\Cases;
use App\Models\system\SystemSetting;
use App\Models\Logs;
use App\Models\document\DocumentActivity;
use App\Models\system\Access;
use Aws\EndpointDiscovery\Configuration;
use Aws\S3\S3Client;
class CasesDocument extends Model
{
    use HasFactory;
    protected $table = "case_documents";
    protected $fillable = [
        'case_id',
        'name',
        'comments',
        'file',
        'filesize',
        'ext',
        'created_by',
        'created_date',
        's3',
        'folder',
        'type_id',
        'updated',
        'updated_by',
        'email_id',
        'client',
        'uuid',
        'url'
    ];
    const DOC_VIEWDATE = '2016-11-03 10:30:59';

    public function find_($id){
        $result = CasesDocument::from('case_documents as cd')
            ->selectRaw('cd.*, cc.dpp_contact_id, u.id as user_id,
                CONCAT(u.first_name, " ", u.last_name) as user_name, u.email as user_email')
            ->leftJoin('cases as c', 'cd.case_id', 'c.id')
            ->leftJoin('case_contacts as cc', 'cc.case_id', 'c.id')
            ->leftJoin('users as u', 'u.id', 'cd.created_by')
            ->where('cd.id', $id)
            ->get()
            ->toArray();
        return current($result);
    }

    public function findByEmailID($id){
        $result = CasesDocument::selectRaw('cd.*, cc.dpp_contact_id, u.id as user_id, 
            CONCAT(u.first_name, " ", u.last_name) as user_name, u.email as user_email, 
            dt.name as document_type, dlog.viewed, dlog.viewed_by')
            ->from('case_documents as cd')
            ->leftJoin('cases as c', 'cd.case_id', 'c.id')
            ->leftJoin('document_log as dlog', 'dlog.document_id', 'cd.id')
            ->leftJoin('case_contacts as cc', 'cc.case_id', 'c.id')
            ->leftJoin('document_types as dt', 'dt.id', 'cd.type_id')
            ->leftJoin('users as u', 'u.id', 'cd.created_by')
            ->where('cd.email_id', $id)
            ->get()
            ->toArray();
        return $result;
    }

    public function update_($id, $data){
        $result = CasesDocument::find($id)->fill($data);
        $result->update();
    }

    public function findTotalByFilter($filter, $group_by = 'day'){
        $query = Esign_doc::
        selectRaw('COUNT(DISTINCT es.case_id) as total, 
            YEAR(es.updated) as year,
            MONTH(es.updated) as month,
            DAY(es.updated) as day')
        ->from('esign_docs as es')
        ->leftJoin('cases as c', 'c.id', 'es.case_id')
        ->leftJoin('case_contacts as cc', 'cc.case_id', 'c.id')
        ->leftJoin('case_statuses as cs', 'cs.case_id', 'c.id')
        ->leftJoin('case_assignments as ca', 'ca.case_id', 'c.id')
        ->leftJoin('shared_cases as sc', 'sc.case_id', 'c.id');
        
        if(isset($filter['docs_status']) && !empty($filter['docs_status'])){
            $query->where('es.status', $filter['docs_status']);
        }
        if(isset($filter['dates']) && !empty($filter['dates'])){
            $query->where('es.status', $filter['docs_status']);
        }
        if(isset($filter['company_id']) && !empty($filter['company_id'])){
            $query->where('c.company_id', $filter['company_id']);
        }
        if (!empty($filter['dates']) && $filter['dates'] != 'all_time') {
            $date_field = 'es.updated';
            /* Fix for 2038 + */
            //$d = new DateTime( '2040-11-23' );
            // echo $d->format( 'Y-m-t' );
            if ($filter['dates'] == 'day') {
                $query->whereBetween($date_field, array(
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
        $query = Access::queryAccess($query);
        $query->groupBy('es.updated')->groupBy($group_by)->orderBy($group_by);
        
        $results = $query->get()->toArray();
        return $results;
    }

    public function findByCaseID($case_id){
        $result = CasesDocument::from('case_documents as cd')
            ->selectRaw('cd.*, cc.dpp_contact_id, u.id as user_id, 
            CONCAT(u.first_name," ", u.last_name) as user_name,
            u.email as user_email, dt.name as document_type')
            ->leftJoin('cases as c', 'cd.case_id', 'c.id')
            ->leftJoin('case_contacts as cc', 'cc.case_id', 'c.id')
            ->leftJoin('document_types as dt', 'dt.id', 'cd.type_id')
            ->leftJoin('users as u', 'u.id', 'cd.created_by')
            ->where('cd.case_id', $case_id)
            ->orderBy('cd.id', 'Desc')
            ->get()
            ->toArray();
        return $result;
    }

    public function findByUUID($uuid){
        $result = CasesDocument::from('case_documents as cd')
                ->selectRaw('cd.*, cc.dpp_contact_id, u.id as user_id, 
                CONCAT(u.first_name, " ", u.last_name) as user_name, u.email as user_email, dt.name as document_type')
                ->leftJoin('cases as c', 'cd.case_id', 'c.id')
                ->leftJoin('case_contacts as cc', 'cc.case_id', 'c.id')
                ->leftJoin('document_types as dt', 'dt.id', 'cd.type_id')
                ->leftJoin('users as u', 'u.id', 'cd.created_by')
                ->where('cd.uuid', $uuid)
                ->get()
                ->toArray();
        return current($result);
    }

    public function findByCaseIDExt($case_id, $ext_list){
        $result = CasesDocument::from('case_documents as cd')
            ->selectRaw('cd.*, cc.dpp_contact_id, u.id as user_id, CONCAT(u.first_name, " ", u.last_name) as user_name,
            u.email as user_email, dt.name as document_type')
            ->leftJoin('cases as c', 'cd.case_id', 'c.id')
            ->leftJoin('case_contacts as cc', 'cc.case_id', 'c.id')
            ->leftJoin('document_types as dt', 'dt.id', 'cd.type_id')
            ->leftJoin('users as u', 'u.id', 'cd.created_by')
            ->where('cd.case_id', $case_id)
            ->whereIn('ext', $ext_list)
            ->orderBy('cd.id', 'Desc')
            ->get()
            ->toArray();
        return $result;
    }

    public function getUnseenCount($case_id){
        $query = CasesDocument::from('case_documents as cd')
            ->selectRaw('count(DISTINCT(cd.id)) as count')
            ->leftJoin('document_log as dl', 'dl.document_id', 'cd.id')
            ->where('dl.id', null)
            ->where('cd.case_id', $case_id)
            ->where('cd.created_date', '>', Case_document::DOC_VIEWDATE)
            ->get()
            ->toArray();
        $result = current($query);
        if($result){
            return $result['count'];
        }else{
            return 0;
        }
    }

    public function findByCaseIDWithLog($case_id){
        $result = CasesDocument::from('case_documents as cd')
            ->selectRaw('cd.*, cc.dpp_contact_id, u.id as user_id, CONCAT(u.first_name," ", u.last_name) as  user_name, u.email as user_email, dt.name as document_type')
            ->leftJoin('cases as c', 'cd.case_id', 'c.id')
            ->leftJoin('document_log as dlog', 'dlog.document_id','cd.id')
            ->leftJoin('case_contacts as cc', 'cc.case_id','c.id')
            ->leftJoin('document_types as dt', 'dt.id', 'cd.type_id')
            ->leftJoin('users as u', 'u.id', 'cd.created_by')
            ->where('cd.case_id', $case_id)
            ->orderBy('cd.id', 'Desc')
            ->groupBy('cd.id')
            ->get()
            ->toArray();
        return $result;
    }

    public function findByCompanyID($company_id){
        $result = CasesDocument::from('case_documents as cd')
            ->selectRaw('cd.*, u.id as user_id, CONCAT(u.first_name," ", u.last_name) as created_by_user, dt.name as document_type')
            ->leftJoin('cases as c', 'cd.case_id', 'c.id')
            ->leftJoin('case_contacts as cc', 'cc.case_id', 'c.id')
            ->leftJoin('document_types as dt', 'dt.id', 'cd.type_id')
            ->leftJoin('users as u', 'u.id', 'cd.created_by')
            ->where('c.company_id', '$company_id')
            ->get()
            ->toArray();
        return $result;
    }

    public function findIdsByCompanyID($company_id){
        $result = CasesDocument::from('case_documents as cd')
            ->select('cd.id', 'cd.case_id')
            ->leftJoin('cases as c', 'cd.case_id', 'c.id')
            ->leftJoin('case_contacts as cc', 'cc.case_id', 'c.id')
            ->leftJoin('document_types as dt', 'dt.id', 'cd.type_id')
            ->leftJoin('users as u', 'u.id', 'cd.created_by')
            ->where('c.company_id', $company_id)
            ->orderBy('cd.id', 'Desc')
            ->get()
            ->toArray();
        return $result;
    }

    public function findByCaseIDs($case_ids){
        $result = CasesDocument::from('case_documents as cd')
            ->selectRaw('cd.*, u.id as uer_id, CONCAT(u.first_name, " ", u.last_name) as user_name, u.email as user_email, dt.name as document_type')
            ->leftJoin('cases as c', 'cd.case_id', 'c.id')
            ->leftJoin('case_contacts as cc', 'cc.case_id', 'c.id')
            ->leftJoin('document_types as dt', 'dt.id', 'cd.type_id')
            ->leftJoin('users as u', 'u.id', 'cd.created_by')
            ->whereIn('c.id', $case_ids)
            ->orderBy('cd.id', 'Desc')
            ->get()
            ->toArray();
        return $result;
    }

    public function findByFileName($file){
        $result = CasesDocument::where('file', $file)->get()->toArray();
        return current($result);

    }

    public function add($case_id, $title, $filename, $type_id=14, $comments=null, $filesize=null, $folder=null, $ext=null, $email_id=null){

        $extension = pathinfo($filename, PATHINFO_EXTENSION);

        $uuid = \Str::random('uuid');

        $data = array(
            'case_id' => $case_id,
            'name' => $title,
            'file' => $filename,
            'created_by' => Account::getUserId(),
            'created_date' => date('Y-m-d H:i:s'),
            'ext' => (!empty($extension)?$extension:null),
            'folder' => $folder,
            'email_id' => $email_id,
            'type_id' => $type_id,
            'filesize' => $filesize,
            'comments' => $comments,
            'uuid' => $uuid,
            's3' => 1  //TODO FIX
        );
        $result = CasesDocument::create($data);
        return current($result);
    }

    public function bulk_add($record){

        $uuid = \Str::random('uuid');

        $data = array(
            'case_id' => $record['case_id']??null,
            'name' => $record['name']??null,
            'file' => $record['file']??null,
            'created_by' => $record['created_by']??1,
            'created_date' => $record['created_date']??date('Y-m-d H:i:s'),
            'ext' => $record['ext']??null,
            'folder' => $record['folder']??null,
            'email_id' => $record['email_id']??null,
            'url' => $record['url']??null,
            'type_id' => $record['type_id']??1,
            'filesize' => $record['filesize']??null,
            'comments' => $record['comments']??null,
            'uuid' => $uuid,
            's3' => 1
        );
        $result = CasesDocument::create($data);
        dd($result);
        return current($result);
    }

    public function delete_($id){
        CasesDocument::find($id)->delete();
    }
    public function deleteS3($doc){

        //$case = \Model_Case::find($doc['case_id']);

        $json_settings = SystemSetting::get('s3');
        $setting = json_decode($json_settings['value'], true); // Manually set for SLS

        $client = \Aws\S3\S3Client::factory(array(
            'key'    => $setting['key'],
            'secret' => $setting['secret'],
        ));

        if(isset($doc['folder'])){
            $result = $client->deleteObject(
                array(
                    'Bucket' => $setting['bucket'],
                    'Key'    => $doc['folder'].'/'.$doc['case_id'].'/'.$doc['file']
                ));

        }else{
            $result = $client->deleteObject(
                array(
                    'Bucket' => $setting['bucket'],
                    'Key'    => 'documents/'.$doc['case_id'].'/'.$doc['file']
                ));

        }

        CaseDocument::find($doc['id'])->delete();

        return $result;

    }

    public function getObjectUrl($doc){

        if($doc['folder'] == 'emails'){
            $loc = 'emails' . DIRECTORY_SEPARATOR . $doc['case_id'] . DIRECTORY_SEPARATOR . $doc['file'];
        }else {
            $loc = 'documents' . DIRECTORY_SEPARATOR . $doc['case_id'] . DIRECTORY_SEPARATOR . $doc['file'];
        }

        return 'https://student-advocates-crm.s3.amazonaws.com/' . $loc . $doc['file'];
    }
    public function streamS3Object($doc){

        $json_settings =SystemSetting::get('s3');
        $setting = json_decode($json_settings['value'], true);

        $client = \Aws\S3\S3Client::factory(array(
            'key'    => $setting['key'],
            'secret' => $setting['secret'],
        ));


        $client->registerStreamWrapper();

        if($doc['folder'] == 'emails'){
            $loc = 'emails' . DIRECTORY_SEPARATOR . $doc['case_id'] . DIRECTORY_SEPARATOR . $doc['file'];
        }else {
            $loc = 'documents' . DIRECTORY_SEPARATOR . $doc['case_id'] . DIRECTORY_SEPARATOR . $doc['file'];
        }

        self::readObject($client, 'student-loan-support', $loc, $doc['file']);

    }
    public function streamCaseObject($case_id, $file){

        $json_settings = \SystemSetting::get('s3');
        $setting = json_decode($json_settings['value'], true);

        $client = \Aws\S3\S3Client::factory(array(
            'key'    => $setting['key'],
            'secret' => $setting['secret'],
        ));


        $client->registerStreamWrapper();

        $loc = $file;

        self::readObject($client, 'student-loan-support', $loc, $file);

    }
    public function readObject(S3Client $client, $bucket, $key, $file)
    {
        $content_type = self::getMimeType(strtolower($file));
      
        header('Content-Type: '.$content_type);
        header("Content-Disposition: inline; filename='{$file}'");
        // Stop output buffering
        if (ob_get_level()) {
            ob_end_flush();
        }
        flush();
        // Only send the body if the file was not modified
        readfile("s3://{$bucket}/{$key}");
    }


    /* AWS S3 SDK */
    public function getS3Object($doc){

        //$case = \Model_Case::find($doc['case_id']);
        $json_settings = SystemSetting::get('s3');
        $setting = json_decode($json_settings['value'], true);

        $client = \Aws\S3\S3Client::factory(array(
            'key'    => $setting['key'],
            'secret' => $setting['secret'],
        ));


        if(isset($doc['url']) && !empty($doc['url'])){
            return self::downloadFromUrl($doc['file'],$doc['url']);
        }

        $filename = $doc['file'];
        $new_file_name = md5(microtime().$doc['case_id'].$filename);

        // Get an object using the getObject operation
        if(isset($doc['folder'])){



            switch($doc['folder']){


                case 'dpp-contracts':
                    $result = $client->getObject(array(
                        'Bucket' => $setting['bucket'],
                        'Key'    => $doc['folder'].'/'.$filename,
                        'SaveAs' => '/tmp/'.$new_file_name
                    ));
                break;

                case 'emails':
                    $result = $client->getObject(array(
                        'Bucket' => $setting['bucket'],
                        'Key'    => $doc['folder'].'/'.$doc['case_id'].'/'.$filename,
                        'SaveAs' => '/tmp/'.$new_file_name . '_.emailattachment'
                    ));
                    break;

                case 'calls':

                    $result = $client->getObject(array(
                        'Bucket' => $setting['bucket'],
                        'Key'    => 'cases'.'/'.$doc['case_id'].'/'.$filename,
                        'SaveAs' => APPPATH.'tmp/calls/'.$new_file_name.'_.call'
                    ));
                    break;

                default:
                    $result = $client->getObject(array(
                        'Bucket' => $setting['bucket'],
                        'Key'    => $doc['folder'].'/'.$filename,
                        'SaveAs' => APPPATH.'tmp/'.$new_file_name.'_.s3obj'
                    ));
                    break;
            }

        }else{
            $result = $client->getObject(array(
                'Bucket' => $setting['bucket'],
                'Key'    => 'documents/'.$doc['case_id'].'/'.$filename,
                'SaveAs' => APPPATH.'tmp/'.$new_file_name
            ));
        }

        $data = array();
        $data['Content-Type'] = $result['ContentType'];
        $data['filename'] = $filename;
        $data['body'] = $result['Body']->getUri(); // tmp file location

        return $data;

    }

    public function downloadFromUrl($file_name, $url){

        $ext = pathinfo($file_name, PATHINFO_EXTENSION);
        $new_file_name = md5(microtime().$file_name) . '.'.$ext;
        $url = str_replace(' ', '%20', $url);
        $file_contents = file_get_contents($url);
        if($file_contents) {
            file_put_contents($new_file_name, $file_contents);
            $data = array();
            $data['Content-Type'] = mime_content_type($new_file_name);
            $data['filename'] = $file_name;
            $data['body'] = $new_file_name; // tmp file location
            return $data;
        }
        return false;
    }
    public function downloadS3Object($folder, $document_name){

        //$case = \Model_Case::find($doc['case_id']);
        $json_settings = SystemSetting::get('s3');
        $setting = json_decode($json_settings['value'], true);

        $client = \Aws\S3\S3Client::factory(array(
            'key'    => $setting['key'],
            'secret' => $setting['secret'],
        ));

        // Get an object using the getObject operation

        $result = $client->getObject(array(
            'Bucket' => $setting['bucket'],
            'Key'    => $folder.DIRECTORY_SEPARATOR.$document_name,
            'SaveAs' => APPPATH.'tmp/'.md5($document_name)
        ));


        $data = array();
        $data['Content-Type'] = $result['ContentType'];
        $data['filename'] = $document_name;
        $data['body'] = $result['Body']->getUri(); // tmp file location

        return $data;

    }

    public function download_merge($doc_ids){
        $doc_group = array();
        $doc_ids_set = explode(',',$doc_ids);
        // Merge Docs
        $pdf = new PDF();
        foreach($doc_ids_set as $doc_id){
            // Get Document Objects
            $document = self::find($doc_id);
            // Download Documents
            $document_file = self::getS3Object($document);
            if($document_file) {
                $document_location = $document_file['body'];
                if($ext = pathinfo($document_file['filename'], PATHINFO_EXTENSION)){
                    $ext_lower = strtolower($ext);
                    if(in_array($ext_lower, array('jpg','png'))){
                        // Convert to PDF
                        $coverted_response = $pdf->convert_to_pdf($document_location);
                        // New Document Location
                    }elseif($ext_lower == 'pdf'){
                    }
                }
                $doc_group[] = $document_location;
            }else{
                die('NOT WORKY');
            }
        }
        $merged_file = $pdf->merge_multiple($doc_group, true);
        // Upload New Document
        return $merged_file;
        // Return Success
    }
    // problem convert\image class not exist
    public function download_convert($doc_id){
        // Merge Docs
        $pdf = new PDF();

        // Get Document Objects
        $document = self::find($doc_id);
        if(!in_array($document['ext'], array('jpg', 'png','jpeg'))){
            throw new \Exception('Extension not allowed to convert '.$document['ext']);
        }
        // Download Documents
        $document_file = self::getS3Object($document);
        if($document_file) {
            $document_location = $document_file['body'];
            $new_location = 'tmp' . DIRECTORY_SEPARATOR . uniqid() . '.pdf';

            return \Converter\Image::imgToPdf($document_location, $new_location);
        }
        return false;
    }
    // Temporary Hack to get DPP Documents Downlaoded
    public function getS3DppObject($doc){
        //$case = \Model_Case::find($doc['case_id']);
        try {
            $json_settings = SystemSetting::get('s3');
            $settings = json_decode($json_settings['value'], true);
        }catch(\Exception $e){
            Log::error($e);
            exit;
        }

        $client = S3Client::factory(array(
            'key'    => $settings['key'],
            'secret' => $settings['secret'],
        ));

        $filename = $doc['file'];

        // Get an object using the getObject operation
        $result = $client->getObject(array(
            'Bucket' => $settings['bucket'],
            'Key'    => 'docs/'.$doc['dpp_contact_id'].'/'.$filename,
            'SaveAs' => '/tmp/9834598345'
        ));

        $data = array();

        $data['Content-Type'] = $result['ContentType'];
        $data['filename'] = $filename;
        $data['body'] = $result['Body']->getUri();

        return $data;

    }
    public function updateS3ObjectAcl($key, $acl = 'public-read'){

        $json_settings = SystemSetting::get('s3');
        $setting = json_decode($json_settings['value'], true);
        if(!$setting){
            throw new Exception('No S3 settings for this company');
        }
        $client = \Aws\S3\S3Client::factory(array(
            'key'    => $setting['key'],
            'secret' => $setting['secret'],
        ));
        //'private|public-read|public-read-write|authenticated-read|aws-exec-read|bucket-owner-read|bucket-owner-full-control'
        // Object Key


        $result = $client->putObjectAcl(array(
            'ACL'       => $acl,
            'Bucket'    => $setting['bucket'],
            'Key'       => $key
        ));


        return $result;

    }
    public function getUrl($key){

        $json_settings = SystemSetting::get('s3');
        $setting = json_decode($json_settings['value'], true);
        if(!$setting){
            throw new Exception('No S3 settings for this company');
        }
        $client = \Aws\S3\S3Client::factory(array(
            'key'    => $setting['key'],
            'secret' => $setting['secret'],
        ));
        // Object Key

        // Get Object Url
        $object_url = $client->getObjectUrl($setting['bucket'],$key);

        return $object_url;

    }

    public function getDownloadUrl($document){
        $host = "https://app.datacorecrm.com/download/document?";
        $host .= "uuid=" . $document['uuid'];
        $host .= "&type=document";
        return $host;
    }

    public function getStreamUrl($document){
        $host = "https://app.datacorecrm.com/download/stream?";
        $host .= "uuid=" . $document['uuid'];
        $host .= "&type=document";
        $host .= "&file=" . $document['uuid'].'.'.$document['ext'];
        return $host;
    }


    /**
     * @param int $case_id
     * @param array $file [name,tmp_name, type]
     * @param string $folder
     * @return bool|string
     */
    // problem settings table not exist
    public function addS3($case_id, $file, $folder=null, $email_id=null){

        $path_parts = pathinfo($file['name']);
        $clean_name = filter_var($path_parts['filename'], FILTER_SANITIZE_STRING);
        $filename = $clean_name. '_ '.uniqid().'.'.$path_parts['extension'];

        if(isset($file['document_type'])) {
            $doc_type = $file['document_type'];
        }else{
            $doc_type = 14; // General
        }


        $case = Cases::find($case_id);
        $json_settings = SystemSetting::get('s3');
        $setting = json_decode($json_settings['value'], true);
        if(!$setting){
            throw new Exception('No S3 settings for this company');
        }
        $client = \Aws\S3\S3Client::factory(array(
            'key'    => $setting['key'],
            'secret' => $setting['secret'],
        ));



        $filesize_bytes = filesize($file['tmp_name']);
        //$filesize = \Formatter\Format::formatSizeUnits($filesize_bytes);


        // Upload an object by streaming the contents of a file
        // $pathToFile should be absolute path to a file on disk
        if(!$folder){
            $key = 'documents/'.$case_id.'/'.$filename;
        }else{
            $key = $folder.'/'.$case_id.'/'.$filename;
        }

        $result = $client->putObject(array(
            'Bucket'     => $setting['bucket'],
            'Key'        => $key,
            'SourceFile' => $file['tmp_name'],
            'Body' => $file['tmp_name'],
            'ACL'        => 'private',
            'Metadata'  => array(
                'Content-Type' => $file['type']
            )
        ));

        // We can poll the object until it is accessible
        $client->waitUntilObjectExists(array(
            'Bucket' => $setting['bucket'],
            'Key'    => $key
        ));

        $user_filename = $file['name'];
        if(isset($file['user_filename']) && !empty($file['user_filename'])){
            $user_filename = preg_replace('/[^A-Za-z0-9_\-]/', '_', $file['user_filename']);
        }

        $folder = (isset($folder)?$folder:null);
        $ext = strtolower($path_parts['extension']);

        if($result){
            $doc_id = self::add($case_id, $user_filename,$filename,$doc_type, null,$filesize_bytes,$folder, $ext, $email_id);
            return $doc_id;
        }
            return false;

    }

    public function cleanFilename($filename, $ext=false){
        // Get File Information
        $clean_name = preg_replace('/[^A-Za-z0-9_\-]/', '_', $filename);
        if($ext){
            return $clean_name .'.'. $ext;
        }
        return $clean_name;
    }

    public function upload($case_id, $filename, $file, $folder=null){

        //SETTINGS
        $case = Cases::find($case_id);
        $json_settings = SystemSetting::get('s3');
        $setting = json_decode($json_settings['value'], true);
        if(!$setting){
            throw new Exception('No S3 settings for this company');
        }

        // Initiate S3 Wrapper
        $client = \Aws\S3\S3Client::factory(array(
            'key'    => $setting['key'],
            'secret' => $setting['secret'],
        ));


        // Upload an object by streaming the contents of a file
        // $pathToFile should be absolute path to a file on disk
        if(!$folder){
            $key = 'documents/'.$case_id.'/'.$filename;
        }else{
            $key = $folder.'/'.$case_id.'/'.$filename;
        }

        $result = $client->putObject(array(
            'Bucket'     => $setting['bucket'],
            'Key'        => $key,
            'SourceFile' => $file,
            'Body'       => $file,
            'ACL'        => 'private',
            'Metadata'   => array(
                'Content-Type' => self::getMimeType($file)
            )
        ));

        // We can poll the object until it is accessible
        $client->waitUntilObjectExists(array(
            'Bucket' => $setting['bucket'],
            'Key'    => $key
        ));

        return $result;


    }


    public function getAllTypes(){
        $result = DocumentTypes::orderBy('name','Asc')->get()->toArray();
        return $query;
    }

    public function getType($type_id){
        $result = DocumentTypes::find($type_id)->toArray();
        return current($result);
    }


    public function getMimeType($file) {
        // MIME types array
        $mimeTypes = array(
            "323"       => "text/h323",
            "acx"       => "application/internet-property-stream",
            "ai"        => "application/postscript",
            "aif"       => "audio/x-aiff",
            "aifc"      => "audio/x-aiff",
            "aiff"      => "audio/x-aiff",
            "asf"       => "video/x-ms-asf",
            "asr"       => "video/x-ms-asf",
            "asx"       => "video/x-ms-asf",
            "au"        => "audio/basic",
            "avi"       => "video/x-msvideo",
            "axs"       => "application/olescript",
            "bas"       => "text/plain",
            "bcpio"     => "application/x-bcpio",
            "bin"       => "application/octet-stream",
            "bmp"       => "image/bmp",
            "c"         => "text/plain",
            "cat"       => "application/vnd.ms-pkiseccat",
            "cdf"       => "application/x-cdf",
            "cer"       => "application/x-x509-ca-cert",
            "class"     => "application/octet-stream",
            "clp"       => "application/x-msclip",
            "cmx"       => "image/x-cmx",
            "cod"       => "image/cis-cod",
            "cpio"      => "application/x-cpio",
            "crd"       => "application/x-mscardfile",
            "crl"       => "application/pkix-crl",
            "crt"       => "application/x-x509-ca-cert",
            "csh"       => "application/x-csh",
            "css"       => "text/css",
            "csv"       => "text/csv",
            "dcr"       => "application/x-director",
            "der"       => "application/x-x509-ca-cert",
            "dir"       => "application/x-director",
            "dll"       => "application/x-msdownload",
            "dms"       => "application/octet-stream",
            "doc"       => "application/msword",
            "docx"       => "application/msword",
            "dot"       => "application/msword",
            "dvi"       => "application/x-dvi",
            "dxr"       => "application/x-director",
            "eps"       => "application/postscript",
            "etx"       => "text/x-setext",
            "evy"       => "application/envoy",
            "exe"       => "application/octet-stream",
            "fif"       => "application/fractals",
            "flr"       => "x-world/x-vrml",
            "gif"       => "image/gif",
            "gtar"      => "application/x-gtar",
            "gz"        => "application/x-gzip",
            "h"         => "text/plain",
            "hdf"       => "application/x-hdf",
            "hlp"       => "application/winhlp",
            "hqx"       => "application/mac-binhex40",
            "hta"       => "application/hta",
            "htc"       => "text/x-component",
            "htm"       => "text/html",
            "html"      => "text/html",
            "htt"       => "text/webviewhtml",
            "ico"       => "image/x-icon",
            "ief"       => "image/ief",
            "iii"       => "application/x-iphone",
            "ins"       => "application/x-internet-signup",
            "isp"       => "application/x-internet-signup",
            "jfif"      => "image/pipeg",
            "jpe"       => "image/jpeg",
            "jpeg"      => "image/jpeg",
            "jpg"       => "image/jpeg",
            "js"        => "application/x-javascript",
            "latex"     => "application/x-latex",
            "lha"       => "application/octet-stream",
            "lsf"       => "video/x-la-asf",
            "lsx"       => "video/x-la-asf",
            "lzh"       => "application/octet-stream",
            "m13"       => "application/x-msmediaview",
            "m14"       => "application/x-msmediaview",
            "m3u"       => "audio/x-mpegurl",
            "man"       => "application/x-troff-man",
            "mdb"       => "application/x-msaccess",
            "me"        => "application/x-troff-me",
            "mht"       => "message/rfc822",
            "mhtml"     => "message/rfc822",
            "mid"       => "audio/mid",
            "mny"       => "application/x-msmoney",
            "mov"       => "video/quicktime",
            "movie"     => "video/x-sgi-movie",
            "mp2"       => "video/mpeg",
            "mp3"       => "audio/mpeg",
            "mpa"       => "video/mpeg",
            "mpe"       => "video/mpeg",
            "mpeg"      => "video/mpeg",
            "mpg"       => "video/mpeg",
            "mpp"       => "application/vnd.ms-project",
            "mpv2"      => "video/mpeg",
            "ms"        => "application/x-troff-ms",
            "mvb"       => "application/x-msmediaview",
            "nws"       => "message/rfc822",
            "oda"       => "application/oda",
            "p10"       => "application/pkcs10",
            "p12"       => "application/x-pkcs12",
            "p7b"       => "application/x-pkcs7-certificates",
            "p7c"       => "application/x-pkcs7-mime",
            "p7m"       => "application/x-pkcs7-mime",
            "p7r"       => "application/x-pkcs7-certreqresp",
            "p7s"       => "application/x-pkcs7-signature",
            "pbm"       => "image/x-portable-bitmap",
            "pdf"       => "application/pdf",
            "pfx"       => "application/x-pkcs12",
            "pgm"       => "image/x-portable-graymap",
            "pko"       => "application/ynd.ms-pkipko",
            "pma"       => "application/x-perfmon",
            "pmc"       => "application/x-perfmon",
            "pml"       => "application/x-perfmon",
            "pmr"       => "application/x-perfmon",
            "pmw"       => "application/x-perfmon",
            "pnm"       => "image/x-portable-anymap",
            "pot"       => "application/vnd.ms-powerpoint",
            "ppm"       => "image/x-portable-pixmap",
            "pps"       => "application/vnd.ms-powerpoint",
            "ppt"       => "application/vnd.ms-powerpoint",
            "prf"       => "application/pics-rules",
            "ps"        => "application/postscript",
            "pub"       => "application/x-mspublisher",
            "qt"        => "video/quicktime",
            "ra"        => "audio/x-pn-realaudio",
            "ram"       => "audio/x-pn-realaudio",
            "ras"       => "image/x-cmu-raster",
            "rgb"       => "image/x-rgb",
            "rmi"       => "audio/mid",
            "roff"      => "application/x-troff",
            "rtf"       => "application/rtf",
            "rtx"       => "text/richtext",
            "scd"       => "application/x-msschedule",
            "sct"       => "text/scriptlet",
            "setpay"    => "application/set-payment-initiation",
            "setreg"    => "application/set-registration-initiation",
            "sh"        => "application/x-sh",
            "shar"      => "application/x-shar",
            "sit"       => "application/x-stuffit",
            "snd"       => "audio/basic",
            "spc"       => "application/x-pkcs7-certificates",
            "spl"       => "application/futuresplash",
            "src"       => "application/x-wais-source",
            "sst"       => "application/vnd.ms-pkicertstore",
            "stl"       => "application/vnd.ms-pkistl",
            "stm"       => "text/html",
            "svg"       => "image/svg+xml",
            "sv4cpio"   => "application/x-sv4cpio",
            "sv4crc"    => "application/x-sv4crc",
            "t"         => "application/x-troff",
            "tar"       => "application/x-tar",
            "tcl"       => "application/x-tcl",
            "tex"       => "application/x-tex",
            "texi"      => "application/x-texinfo",
            "texinfo"   => "application/x-texinfo",
            "tgz"       => "application/x-compressed",
            "tif"       => "image/tiff",
            "tiff"      => "image/tiff",
            "tr"        => "application/x-troff",
            "trm"       => "application/x-msterminal",
            "tsv"       => "text/tab-separated-values",
            "txt"       => "text/plain",
            "uls"       => "text/iuls",
            "ustar"     => "application/x-ustar",
            "vcf"       => "text/x-vcard",
            "vrml"      => "x-world/x-vrml",
            "wav"       => "audio/x-wav",
            "wcm"       => "application/vnd.ms-works",
            "wdb"       => "application/vnd.ms-works",
            "wks"       => "application/vnd.ms-works",
            "wmf"       => "application/x-msmetafile",
            "wps"       => "application/vnd.ms-works",
            "wri"       => "application/x-mswrite",
            "wrl"       => "x-world/x-vrml",
            "wrz"       => "x-world/x-vrml",
            "xaf"       => "x-world/x-vrml",
            "xbm"       => "image/x-xbitmap",
            "xla"       => "application/vnd.ms-excel",
            "xlc"       => "application/vnd.ms-excel",
            "xlm"       => "application/vnd.ms-excel",
            "xls"       => "application/vnd.ms-excel",
            "xlsx"      => "vnd.ms-excel",
            "xlt"       => "application/vnd.ms-excel",
            "xlw"       => "application/vnd.ms-excel",
            "xof"       => "x-world/x-vrml",
            "xpm"       => "image/x-xpixmap",
            "xwd"       => "image/x-xwindowdump",
            "z"         => "application/x-compress",
            "zip"       => "application/zip",
            "png"       => "image/png"
        );
        $extension = pathinfo($file, PATHINFO_EXTENSION);
        return $mimeTypes[$extension]; // return the array value
    }

    public function save_note($data){
        $d = array();
        parse_str($data,$d);
        $data_set = array('comments'=>$d['value']);
        $doc = CasesDocument::find($d['pk']);
        $result = CasesDocument::find($d['pk'])->fill($data_set);
        $result->update();
        if($result){
            $user_name = Account::getUserFullName();
            $add_result = DocumentActivity::create(['name'=> $user_name, 'message'=> $d['value'], 'case_id'=>$doc['case_id']]);
            if(!$add_result){
                Logs::append('document','Could not add note to document activity log',$doc['case_id']);
            }
        }
        return $result;
    }

    public function strip_pages($doc_id, $pages){
        $document = self::find($doc_id);
        // Download Documents
        $document_file = self::getS3Object($document);
        if($document_file) {
            $document_location = $document_file['body'];
            $new_filename = 'resources/pdfs/'.self::getCaseUID($document['case_id']);
            $output = shell_exec("pdftk " . $document_location . " cat " . $pages . " output " . $new_filename);
            if(is_file($new_filename)){
                return $new_filename;
            }else{
                return false;
            }
        }
    }

    public function findByFilter($filter){

        $query = CasesDocument::select('d.*','dt.name as type','dt.poi as poi')
            ->from('case_documents as d')
            ->leftJoin('cases as c', 'c.id', 'd.case_id')
            ->leftJoin('case_contacts as cc', 'cc.case_id', 'c.id')
            ->leftJoin('case_statuses as cs','cs.case_id', 'c.id')
            ->leftJoin('document_types as dt', 'dt.id', 'd.type_id');
        if(isset($filter['type_id']) && !empty($filter['type_id'])){
            if(is_array($filter['type_id'])){
                $query->whereIn('d.type_id', $filter['type_id']);
            }else{
                $query->where('d.type_id', $filter['type_id']);
            }
        }
        if(isset($filter['case_id']) && !empty($filter['case_id'])){
            $query->where('d.case_id', $filter['case_id']);
            
        }
        $results = $query->get()->toArray();
        return $results;
    }

    public function findOneByFilter($filter){
        $query = CasesDocument::select('d.*','dt.name as type')
            ->from('case_documents as d')
            ->leftJoin('cases as c', 'c.id', 'd.case_id')
            ->leftJoin('case_contact','cc', 'cc.case_id', 'c.id')
            ->leftJoin('case_statuses','cs', 'cs.case_id', 'c.id')->leftJoin('document_types as dt', 'dt.id', 'd.type_id');
        if(isset($filter['type_id']) && !empty($filter['type_id'])){
            if(is_array($filter['type_id'])){
                $query->whereIn('d.type_id', $filter['type_id']);
            }else{
                $query->where('d.type_id', $filter['type_id']);
            }
        }
        if(isset($filter['case_id']) && !empty($filter['case_id'])){
            $query->where('d.case_id', $filter['case_id']);
        }
        $results = current($query->get()->toArray());
        return $results;
    }

    private static function getCaseUID($case_id){
        return $case_id.'_'.uniqid().'.pdf';
    }

}
