<?php

namespace App\Models\system\document;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
class SystemDocumentFields extends Model
{
    use HasFactory;
    protected $table = "document_fields";
    protected $fillable = [
        'form_id',
        'form_field',
        'value',
        'required',
        'form_field_type',
    ];
    static function find_($id){
        $result = SystemDocumentFields::where('form_id', $id)->get();
        return $result->toArray();
    }

    static function findByFilter($columns, $id){

        $result = SystemDocumentFields::selectRaw($columns)->where('form_id', $id)->get();
        return $result->toArray();

    }

    static function findManyByFilter($columns, $ids){

        $result = SystemDocumentFields::selectRaw($columns)->whereIn('form_id', $ids)->whereNot('value', null)->get();
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
       // DB::delete('document_fields')->execute(); // TEST SHIT

       
       foreach($pdf_fields as $field){
            $result = SystemDocumentFields::create(['form_id'=>$form_id,'form_field'=>$field]);
        }
        return $result;
    }

    static function addFields($document_fields){

        
        foreach($document_fields as $field){
            $result = SystemDocumentFields::create(['form_id'=>$field['form_id'],'form_field'=>$field['form_field'],'value'=>$field['value'],'required'=>$field['required']]);
        }
        return current($result);
    }

    static function replace($pdf, $form_id){

        // get PDF fields as array
        $output = shell_exec("pdftk ".$pdf." dump_data_fields");
        $lines = explode("\n", $output);
        $pdf_fields = array();

        foreach($lines as $line){
            if(strpos($line, 'FieldName:') !== false){
                $fields = explode(':',$line);
                $pdf_fields[] = trim($fields[1]);
            }
        }

        $current_fields = self::find($form_id);
        $old_pdf_fields = array();
        foreach($current_fields as $field){
            $old_pdf_fields[$field['id']] = $field['form_field'];
        }
        // find additional fields
        $additional_fields = array_diff($pdf_fields, $old_pdf_fields);
        /*foreach($pdf_fields as $pdf_field){
            if(!isset($old_pdf_fields[$pdf_field])){
                $additional_fields[] = $pdf_field;
            }
        }*/

        // find fields to remove
        $rm_fields = array_diff($old_pdf_fields ,$pdf_fields);
        $rm_fields = array_flip($rm_fields);
        foreach($rm_fields as $rm_field){
            $rm_field_ids[] =$rm_field;
        }

        $result = false;

        if(!empty($additional_fields)){
            foreach($additional_fields as $new_field){
                $result = SystemDocumentFields::create(['form_id'=>$form_id,'form_field'=>$new_field]);
            }
        }

        if(!empty($rm_field_ids)){
            $result =  SystemDocumentFields::whereIn('id', $rm_field_ids)->delete();
        }

        return $result;
    }

    static function update_($data){
        DB::beginTransaction();
        if(isset($data['value'])){
            foreach($data['value'] as $k => $v){
                if(empty($v)){
                    $v = NULL;
                }else{
                    $result = SystemDocumentFields::find($k)->fill(['value'=>$v]);
                    $result->update();
                    if(isset($data['required'][$k])){
                        $result = SystemDocumentFields::find($k)->fill(['required'=>$data['required'][$k]]);
                        $result->update();
                    }else{
                        $result = SystemDocumentFields::find($k)->fill(['required'=>null]);
                        $result->update();
                    }
                }
            }
        }
        DB::commit();
        return $result;
    }

    static function findFormFields($form_id){
        $fields = SystemDocumentFields::find_($form_id);
        $data = array();
        foreach($fields as $field){
            $data['value'][$field['id']] = $field['value'];
            $data['required'][$field['id']] = $field['required'];
        }
        return $data;
    }


    static function parse($form_ids, $case_fields){

            if(!is_array($form_ids)) {
                $document_fields = SystemDocumentFields::findByFilter(array('form_field', 'value', 'required'), $form_ids);
            }else{
                $document_fields = SystemDocumentFields::findManyByFilter(array('form_field', 'value', 'required'), $form_ids);
            }

            $template = array();
            $required = array();
            $errors = array();

            foreach($document_fields as $ff){
                $template[$ff['form_field']] = $ff['value'];
                if($ff['required']){
                    $required[$ff['form_field']] = true;
                }

            }

            foreach($template as $k => $v){

                if(preg_match_all('/{[^}]+}/', $v, $matches)){

                    $value = '';
                    // Has Dynamic Field
                    foreach($matches[0] as $match){
                        // Strip Curly Brackets
                        $path = str_replace(array('{','}'),'', $match);

                        // If field exists in case fields array
                        if(isset($case_fields[$path]) && !empty($case_fields[$path])){
                            // Replace with value
                            $template[$k] = str_replace($match, $case_fields[$path], $template[$k]);

                        }else{
                            // If not set and required
                            if(isset($required[$path])){
                                $errors[] = $path . ' is required';
                            }
                            $template[$k] = str_replace($match, '', $template[$k]);
                        }
                    }

                }else{
                    // Has Manually Set Field
                    if(isset($field['value']) && !empty($field['value'])){
                        $template[$k] = $field['value'];
                    }
                }

            }



        if(isset($errors) && !empty($errors)){

            throw new \Exception(implode(',', str_replace(array('{','}'),'', $errors)));

        }

        return $template;

    }

  /*
    static function add($data){
        $result = \DB::insert('forms')->set($data)->execute();
        return current($result);
    }
    
    static function update($id, $data){
        $result = \DB::update('forms')->set($data)->where('id', '=', $id)->execute();
    }
    
    static function delete($id){
        $result = \DB::delete('forms')->where('id','=',$id)->execute();
    }
    
    static function saveFile($name, $files_array){
        
        $parts = pathinfo($files_array['name']);
        $file = preg_replace('/[^0-9a-z-_]/i', '', str_replace(' ', '-', strtolower($name))).'_'.date('mdy').'.'.$parts['extension'];
        
        $form_folder = Config::get('pdf_folder');
        
        if(file_exists($form_folder.$file)){
            unlink($form_folder.$file);
        }
        
        $result = move_uploaded_file($files_array['tmp_name'], $form_folder.$file);
        
        if(!$result){
            throw new Exception('Could not save file');
        }
        
        return $file;
        
    }
    
    static function validate($factory){
        
        $val = \Validation::forge($factory);

        $val->add('name', 'Name')
            ->add_rule('required');

        return $val;
    }
    */
    static function validate($factory){

        $val = Validator::make($factory,[
            // add required
        ]);

        return $val;
    }
}
