<?php

namespace App\Models\system\document;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Validator;
use Illuminate\Support\Facades\DB;

class SystemDocumentBundles extends Model
{
    use HasFactory;
    protected $table = "documents_bundles";
    function findByBundleId($id){
        $result = SystemDocumentBundles::
            leftJoin('documents', 'documents_bundles.form_id', 'documents.id')
            ->where('documents_bundles.bundle_id', $id)
            ->orderBy('documents_bundles.sort', 'ASC')
            ->get()
            ->toArray();
        return $result;
        
   }

    function extractFormIdsFromResult($result){
        $ids = array();
        foreach($result as $form){
            $ids[] = $form['form_id'];
        }
        return $ids;
    }

    static function findByFilter($columns, $id){

        $result = SystemDocumentFields::selectRaw($columns)->where('form_id', $id)->get();
        return $result->toArray();

    }

    static function add($pdf, $form_id){
        //$pdf_form_id = 4444;
        //$pdf = APPPATH . '/resources/pdfs/client-agreement-revised---individual_042314.pdf';

        $output = shell_exec("pdftk ".$pdf." dump_data_fields");
        $lines = explode("\n", $output);
        $pdf_fields = array();

        foreach($lines as $line){
            if(strpos($line, 'FieldName:') !== false){
                $fields = explode(':',$line);
                $pdf_fields[] = trim($fields[1]);
            }
        }

        // Cleared for Testing
       // DB::delete('form_fields')->execute(); // TEST SHIT

        $query = SystemDocumentFields::create(array('form_id','form_field'));

        foreach($pdf_fields as $field){
            $result = SystemDocumentFields::create(['form_id'=>$form_id,'form_field'=>$field]);
        }
        return $result;
    }

    static function update_($data){
        DB::beginTransaction();
        if(isset($data['value'])){
            foreach($data['value'] as $k => $v){
                if(empty($v)){
                    $v = NULL;
                }
                    $result = SystemDocumentFields::find($id)->fill(['value'=> $v]);
                    $result->update();

                $query = SystemDocumentFields::find($k);
                if(isset($data['required'][$k])){
                    $query->fill(['required'=> $data['required'][$k]]);
                }else{
                    $query->value(['required'=> null]);
                }
                $result = $query->update();

            }
        }
        //problem function not exist
        DB::commit();

        return $result;
    }

    static function findFormFields($form_id){
        $fields = self::find($form_id);
        $data = array();
        foreach($fields as $field){
            $data['value'][$field['id']] = $field['value'];
            $data['required'][$field['id']] = $field['required'];
        }
        return $data;
    }


    static function validate($factory){
        $val = Validator::make($factory,[]);
        return $val;
    }
}
