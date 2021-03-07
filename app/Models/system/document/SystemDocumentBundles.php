<?php

namespace App\Models\system\document;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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

        $result = \DB::select_array($columns)->from('document_fields')->where('form_id', '=', $id)->execute();
        return $result->as_array();

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

        $query = \DB::insert('document_fields')->columns(array('form_id','form_field'));

        foreach($pdf_fields as $field){
            $query->values(array($form_id,$field));
        }

        $result = $query->execute();

        return $result;
    }



    static function update_($data){


        DB::start_transaction();

        if(isset($data['value'])){
            foreach($data['value'] as $k => $v){
                if(empty($v)){
                    $v = NULL;
                }
                    $query = \DB::update('document_fields');
                    $query->value('value', $v)
                          ->where('id', '=', $k);
                    $result = $query->execute();


                $query = \DB::update('document_fields');
                if(isset($data['required'][$k])){
                    $query->value('required', $data['required'][$k]);
                }else{
                    $query->value('required', null);
                }
                $result = $query->where('id', '=', $k)->execute();

            }
        }

        DB::commit_transaction();

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

        $val = \Validation::forge($factory);

        return $val;
    }
}
