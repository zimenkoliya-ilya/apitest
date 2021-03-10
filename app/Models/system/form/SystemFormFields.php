<?php

namespace App\Models\system\form;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\system\SystemFormfieldoption;
use App\Models\system\form\section\SystemFormSectionFields;
use Illuminate\Support\Facades\DB;
class SystemFormFields extends Model
{
    use HasFactory;
    protected $table = "form_fields";
    static function find_($id){
        $result = SystemFormFields::find($id)->get();
        return $result->toArray();
    }
    
    static function findAll($object_id){
        
        $result = SystemFormFields::select('f.*', 'ofs.sort as section_sort',  'ofc.sort as container_sort')
                ->from('form_fields as f')
                ->leftJoin('form_sections as ofc', 'ofc.id', 'f.container_id')
                ->leftJoin('form_sections as ofs', 'ofs.id', 'ofc.parent_id')
                ->where('f.object_id', $object_id)
                ->orderBy('ofs.sort')
                ->orderBy('ofc.sort')
                ->orderBy('f.sort')
                ->get();
        return $result->toArray();
    }

    static function findByObject($object_id){

        $result = SystemFormFields::select('f.*', 'ofs.sort as section_sort', 'ofc.sort as container_sort')
            ->from('form_fields as f')
            ->leftJoin('form_sections as ofc', 'ofc.id', 'f.container_id')
            ->leftJoin('form_sections as ofs', 'ofs.id', 'ofc.parent_id')
            //->where('f.object_id', '=', $object_id)
            ->orderBy('f.name','Asc')
            ->get()
            ->toArray();
        return $result;
    }

    static function findBySection($section_id){
       $result =  SystemFormFields::select('f.*', 'ofs.sort as section_sort',  'ofc.sort as container_sort')
            ->from('form_fields as f')
            ->leftJoin('form_sections as ofc', 'ofc.id', 'f.container_id')
            ->leftJoin('form_sections as ofs', 'ofs.id', 'ofc.parent_id')
            ->where('ofs', $section_id)
            ->groupBy('f.id')
            ->get()
            ->toArray();
        return $result;
    }


    static function setIdsAsIndex($array, $key = 'id'){
        $new_array = array();
        foreach($array as $item){
            $new_array[$item[$key]] = $item;
        }
        return $new_array;
    }

    static function findAllByObject($object_id){

        $result = SystemFormFields::select('f.*')
            ->from('form_fields as f')
            ->where('f.object_id', $object_id)
            ->orderBy('f.name')
            ->get();

        return $result->toArray();
    }
    // ???
    static function findAllByIDs($field_ids){
        $implode = implode(',',$field_ids);
        $result = SystemFormFields::from('form_fields as f')
            ->whereIn('f.id', $field_ids)
            // ->orderBy(function($query) use($implode) {
            //    $result = $query->select('FIELD(id, '.$implode.')');
            // })
            ->get()
            ->toArray();
        return $result;
    }
    
    static function findAllGrouped($object_id){
        $fields = self::findAll($object_id);
        $grouped = array();
        foreach($fields as $f){
            $grouped[$f['section_id']][$f['container_id']][$f['id']] = $f;
        }
        return $grouped;
    }
    
    static function findBySectionIDs($section_ids){
        $fields = SystemFormFields::whereIn('section_id', $section_ids)
            ->orderBy('section_id')->orderBy('container_id')->orderBy('sort')->get();
        
        $grouped = array();
        foreach($fields->toArray() as $f){
            $grouped[$f['section_id']][$f['container_id']][$f['id']] = $f;
        }
        return $grouped;
    }
    
    static function findAllTypes(){
        $result = SystemFormFieldtypes::all();
        return $result->toArray();
    }

    static function findByCleanName($clean_name){
        $result = SystemFormFieldtypes::
            leftJoin('form_field_types as left', 'form_fields.field_type_id', 'form_field_types.id')
            ->where('clean_name', $clean_name)
            ->get();
        return current($result->toArray());
    }
    
    static function findAllGroups(){
        $result = SystemFormSection::all();
        
        $groups = array();
        foreach($result->as_array() as $row){
            $groups[$row['id']] = $row;
        }
        return $groups;
    }
    
    static function findGroupsByIDs($ids){
        $result = SystemFormSection::whereIn('id', $ids)->get();
        
        $groups = array();
        foreach($result->toArray() as $row){
            $groups[$row['id']] = $row;
        }
        return $groups;
    }
    
    static function findByType($object_id, $type_id){
        $result = SystemFormField::where('object_id', $object_id)->where('field_type_id', $type_id)->get();
        return $result->toArray();
    }

    static function findAdditional(){
        $result = SystemFormField::select('form_fields.*','form_field_types.f_field')
            ->leftJoin('form_field_types', 'form_fields.field_type_id','form_field_types.id')
            ->where('form_fields.system', '!=', 1)
            ->get()
            ->toArray();
        return $result;
    }

    static function getCustomObjectFields(){
        $result = SystemFormField::select('form_fields.*','form_field_types.f_field')
            ->leftJoin('form_field_types', 'form_fields.field_type_id', 'form_field_types.id')
            ->where('form_fields.system', '!=', 1)
            ->get();
        return $result->toArray();
    }
    
    static function findFeeFields($object_id){
        $result = SystemFormField::where('object_id', $object_id)->where('fee',1)->get();
        return $result->toArray();
    }
    
    static function parseTemplate($template, $fields, $additional_fields = array(), $verify = false){

        if(!is_array($template)){
            parse_str($template, $template);
        }
        
        foreach($additional_fields as $k => $v){
            $fields[$k] = $v;
        }

        $errors = array();
        
        foreach($template as $k => $v){
            if(preg_match_all('/{[^}]+}/', $v, $matches)){
                foreach($matches[0] as $match){
                    $path = str_replace(array('{','}'),'', $match);

                    if($path == "notes"){
                       $fields[$path] = urlencode($fields[$path]);
                    }

                    if($verify == true){
                        if(!isset($fields[$path]) || empty($fields[$path])){
                            $errors[] = $template[$k];
                            continue;
                        }
                    }

                    //$template[$k] = str_replace($match, $fields[$path], $template[$k]);

                    // If field exists in case fields array
                    if(isset($fields[$path])){
                        // Replace with value
                        $template[$k] = str_replace($match, $fields[$path], $template[$k]);

                    }else{
                        // If not set and required
                       /* if(isset($required[$k])){
                            $errors[] = $k . ' is required';
                        }*/
                        $template[$k] = str_replace($match, '', $template[$k]);
                    }

                }
            }
        }



        if($verify == true){
            if(isset($errors) && !empty($errors)){
               throw new \Exception(implode(',', str_replace(array('{','}'),'', $errors)));
            }
        }

        
        return $template;
    }
    
    static function findAllOptions($object_id){
        
        $result = SystemFormfieldoption::select('ofo.*')->from('form_field_options as ofo')
                ->join('form_fields as of', 'of.id', 'ofo.object_field_id')
                ->where('of.object_id', $object_id)
                ->orderBy('ofo.object_field_id')
                ->orderBy('ofo.sort')
                ->get();
        $options = array();
        foreach($result->toArray() as $row){
            $options[$row['object_field_id']][$row['id']] = $row['value'];
        }
        
        return $options;
        
    }
    
    static function add($data){
        
        //$data['clean_name'] = preg_replace('/[^0-9a-z_]/i','', str_replace(' ','_', strtolower($data['name'])));
        
        $result = SystemFormFields::create($data);
        return current($result);
    }
    
    static function update_($id, $data){
        $result = SystemFormFields::find($id)->fill($data);
        $result->update();
    }
    
    static function delete_($id){
        $result = SystemFormFields::find($id)->delete();
    }
       
    static function resort($sorts){
        
        foreach($sorts as $id => $sort){
            $result = SystemFormFields::find($id)->fill(['sort'=>$sort]);
            $result->update();
        }
        
    }


    static function convertToMultiArray($data, $field_data=false){

        $payload = array();

        foreach($data as $d){
            $payload[$d['section_id']] = array(
                'section_id' => $d['section_id'],
                'section_name' => $d['section_name'],
                'section_sort' => $d['section_sort'],
                'containers' => array()
            );
        }

        foreach($data as $d){
            $payload[$d['section_id']]['containers'][$d['container_id']] = array(
                'section_id' => $d['section_id'],
                'container_id' => $d['container_id'],
                'container_name' => $d['container_name'],
                'fields' => array()
            );
        }

        foreach($data as $d){

            $field_values = array(
                'field_id' => $d['field_id'],
                'field_name' => $d['field_name'],
                'field_clean_name' => $d['field_clean_name'],
                'field_required' => $d['field_required'],
                'field_read_only' => $d['field_read_only'],
                'field_value' => null,
                'field_type_id' => $d['field_type_id'],
                'options' => array()
            );

            if($field_data){
                if(isset($field_data[$d['field_clean_name']])){
                    $field_values['field_value'] = $field_data[$d['field_clean_name']];
                }
            }

            $payload[$d['section_id']]['containers'][$d['container_id']]['fields'][$d['field_id']] = $field_values;
        }

        foreach($data as $d){
            if(isset($d['option_id']) && !empty($d['option_id'])) {
                $payload[$d['section_id']]['containers'][$d['container_id']]['fields'][$d['field_id']]['options'][$d['option_id']] = array(
                    'option_id' => $d['option_id'],
                    'option_value' => $d['option_value'],
                    'option_sort' => $d['option_sort']
                );
            }
        }

        return $payload;

        // Sections
        // Containers
        // Fields
        // Options

    }


    static function findAllFieldDataByFieldIds($company_id=null,$field_ids = null){

        $query = SystemFormSectionFields::select('f.id as field_id', 'f.name as field_name', 'f.clean_name as field_clean_name', 'f.field_type_id as field_type_id',
            'f.field_type_id as field_type_id', 'fsf.required as field_required', 'fsf.sort as field_sort', 'fsf.read_only as field_read_only',
            'fsection.id as section_id', 'fsection.sort as section_sort', 'fsection.name as section_name',
            'fcontainer.sort as container_sort', 'fcontainer.id as container_id', 'fcontainer.name as container_name',
            'foptions.id as option_id', 'foptions.sort as option_sort', 'foptions.value as option_value')
         ->from('form_sections_fields as fsf')
         ->join('form_fields as f', 'f.id', 'fsf.field_id')
         ->join('form_sections as fcontainer', 'fcontainer.id', 'fsf.container_id')
         ->join('form_sections as fsection', 'fsection.id', 'fcontainer.parent_id')
         ->leftJoin('form_field_options as foptions', 'foptions.object_field_id', 'f.id');

        if($field_ids){
            $query->whereIn('f.id', $field_ids);
        }

        if($company_id){
            $query->where('fsection.company_id', $company_id);
        }

        $query->orderBy('fsection.sort', 'Asc');
        $query->orderBy('fcontainer.sort', 'Asc');
        $query->orderBy('fsf.sort', 'Asc');
        $query->orderBy('foptions.sort', 'Asc');

        $result = $query->get()->toArray();
        return $result;
    }

    static function findByFormId($form_section_id){

        $query = SystemFormSectionFields::select('f.id as field_id', 'f.name as field_name', 'f.clean_name as field_clean_name', 'f.field_type_id as field_type_id',
            'f.field_type_id as field_type_id', 'fsf.required as field_required', 'fsf.sort as field_sort', 'fsf.read_only as field_read_only',
            'fsection.id as section_id', 'fsection.sort as section_sort','fsection.name as section_name',
            'fcontainer.sort as container_sort', 'fcontainer.id as container_id', 'fcontainer.name as container_name',
            'foptions.id as option_id', 'foptions.sort as option_sort', 'foptions.value as option_value')
            ->from('form_sections_fields as fsf')
            ->join('form_fields as f', 'f.id', 'fsf.field_id')
            ->join('form_sections as fcontainer', 'fcontainer.id', 'fsf.container_id')
            ->join('form_sections as fsection', 'fsection.id', 'fcontainer.parent_id')
            ->leftJoin('form_field_options as foptions', 'foptions.object_field_id', 'f.id');

        $query->where('fsection.id', $form_section_id);

        $query->orderBy('fsection.sort', 'Asc');
        $query->orderBy('fcontainer.sort', 'Asc');
        $query->orderBy('fsf.sort', 'Asc');
        $query->orderBy('foptions.sort', 'Asc');

        $result = $query->get()->toArray();
        return $result;
    }




    static function validate($factory){
        $val = Validator::make($factory,[
            'name'=>'required',
            'field_type_id'=>'required',
        ],[
            'name.required'=>'Name',
            'field_type_id.required'=>'Field Type',
        ]);
        return $val;
    }
}
