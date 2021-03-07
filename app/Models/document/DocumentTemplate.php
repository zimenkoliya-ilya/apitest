<?php

namespace App\Models\document;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\system\SystemDocument;
use App\Models\Pdf;
use App\Models\system\document\SystemDocumentBundles;
class DocumentTemplate extends Model
{
    use HasFactory;
    protected $table = "documents";
    public function find_($document_id){
        $result = DocumentTemplate::select('documents.*')
            ->leftJoin('document_groups', 'document_groups.group_id', 'documents.group_id')
            ->leftJoin('documents_companies', 'documents_companies.form_id', 'documents.id')
            ->where('documents.active', 1)
            ->where('documents.id', $document_id)
            ->get()
            ->toArray();
        return current($result);
    }

    public function findByCompany($company_id){

        $result = DocumentTemplate::select('documents.*')
            ->leftJoin('document_groups', 'document_groups.group_id', 'documents.group_id')
            ->leftJoin('documents_companies', 'documents_companies.form_id', 'documents.id')
            ->where('documents.active', 1)
            ->where('documents.company_id', $company_id)
            ->get()
            ->toArray();

        return $result;
    }

    public function getFileObjectById($document_id){
        $doc = SystemDocument::find_($document_id);
        if($doc['is_bundle'] == 1){
            // Get Bundle Form Requirements
            $form_bundles = new SystemDocumentBundles();
            $bundle_result = $form_bundles->findByBundleId($document_id);
            //var_dump($bundle_result); die();
            if($bundle_result){

                //$form_ids = $form_bundles->extractFormIdsFromResult($bundle_result);
                //$bundle_forms = \Model_System_Document::findByIds($form_ids);
                $form_bundle_group = array();
                $form_data = array();

                foreach ($bundle_result as $bundle_form) {
                    $form_bundle_group[] =  'resources/pdfs/' . $bundle_form['file'];
                }
                // Merge Docs and Set form location
                $pdf = new Pdf();
                $file = $pdf->merge_multiple($form_bundle_group);
            }else{
                $file = '/resources/pdfs/'.$doc['file'];
            }

        }else{
            $file = '/resources/pdfs/'.$doc['file'];
        }
        return $file;
    }

    public function findByUUID($uuid){
        $result = DocumentTemplate::where('documents.uuid', $uuid)->get()->toArray();
        return current($result);
    }

    public function findESign($company_id){
        $result = DocumentTemplate::
            leftJoin('document_groups', 'document_groups.group_id', 'documents.group_id')
            ->leftJoin('documents_companies', 'documents_companies.form_id', 'documents.id')
            ->where('esign',  1)
            ->where('active', 1)
            ->where('documents_companies.company_id', $company_id)
            ->get()
            ->toArray();
        return $result;
    }

    public function findAllActive($company_id){

        $result = DocumentTemplate::select('documents.*')
            ->leftJoin('document_groups', 'document_groups.group_id', 'documents.group_id')
            ->leftJoin('documents_companies', 'documents_companies.form_id', 'documents.id')
            ->where('active', 1)
            ->where('documents_companies.company_id', $company_id)
            ->get()
            ->toArray();
        return $result;
    }

    public function findAll($active=1){
        $result = DocumentTemplate::where('active', '=', $active)->get()->toArray();
        return $result;
    }

    public function findByGroup($group_id){
        $result = DocumentTemplate::
            join('document_groups', 'document_groups.group_id', 'documents.group_id')
            ->where('esign',  1)
            ->where('active', 1)
            ->where('documents.group_id', $group_id)
            ->get()
            ->toArray();
        return $result;
    }

    public function findServicer($document_id){
        $result = DocumentTemplate::
            leftJoin('esign_servicers', 'esign_servicers.id', 'documents.esign_servicer')
            ->where('documents.id',  $document_id)
            ->get()
            ->toArray();
        return current($result);
    }
}
