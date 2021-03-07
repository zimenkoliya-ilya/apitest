<?php

namespace App\Models\system\form;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemFormFields extends Model
{
    use HasFactory;
    protected $table = "form_fields";
    static function find_($id){
        $result = \DB::select()->from('form_fields')->where('id', '=', $id)->execute();
        return current($result->as_array());
    }
    
    static function findAll($object_id){
        
        $result = \DB::select('f.*', array('ofs.sort', 'section_sort'), array('ofc.sort', 'container_sort'))
                ->from(array('form_fields','f'))
                ->join(array('form_sections', 'ofc'), 'LEFT')->on('ofc.id','=','f.container_id')
                ->join(array('form_sections', 'ofs'), 'LEFT')->on('ofs.id','=','ofc.parent_id')
                ->where('f.object_id', '=', $object_id)
                ->order_by('ofs.sort')
                ->order_by('ofc.sort')
                ->order_by('f.sort')
                ->execute();
        return $result->as_array();
    }

    static function findByObject($object_id){

        $result = \DB::select('f.*', array('ofs.sort', 'section_sort'), array('ofc.sort', 'container_sort'))
            ->from(array('form_fields','f'))
            ->join(array('form_sections', 'ofc'), 'LEFT')->on('ofc.id','=','f.container_id')
            ->join(array('form_sections', 'ofs'), 'LEFT')->on('ofs.id','=','ofc.parent_id')
            //->where('f.object_id', '=', $object_id)
            ->order_by('f.name','ASC')
            ->execute();

        return $result->as_array();
    }

    static function findBySection($section_id){
       $result =  \DB::select('f.*', array('ofs.sort', 'section_sort'), array('ofc.sort', 'container_sort'))
            ->from(array('form_fields','f'))
            ->join(array('form_sections', 'ofc'), 'LEFT')->on('ofc.id','=','f.container_id')
            ->join(array('form_sections', 'ofs'), 'LEFT')->on('ofs.id','=','ofc.parent_id')
            ->where('ofs', '=', $section_id)
            ->group_by('f.id')
            ->execute();
    }


    static function setIdsAsIndex($array, $key = 'id'){
        $new_array = array();
        foreach($array as $item){
            $new_array[$item[$key]] = $item;
        }
        return $new_array;
    }

    static function findAllByObject($object_id){

        $result = \DB::select('f.*')
            ->from(array('form_fields','f'))
            ->where('f.object_id', '=', $object_id)
            ->order_by('f.name')
            ->execute();

        return $result->as_array();
    }

    static function findAllByIDs($field_ids){

        $result = \DB::select()
            ->from(array('form_fields','f'))
            ->where('f.id', 'IN', $field_ids)
            ->order_by(DB::expr('FIELD(id, '.implode(',',$field_ids).')'))
            ->execute();

        return $result->as_array();
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
        $fields = \DB::select()->from('form_fields')->where('section_id', 'in', $section_ids)->order_by('section_id')->order_by('container_id')->order_by('sort')->execute();
        
        $grouped = array();
        foreach($fields->as_array() as $f){
            $grouped[$f['section_id']][$f['container_id']][$f['id']] = $f;
        }
        return $grouped;
    }
    
    static function findAllTypes(){
        $result = \DB::select()->from('form_field_types')->execute();
        return $result->as_array();
    }

    static function findByCleanName($clean_name){
        $result = \DB::select()
            ->from('form_fields')
            ->join('form_field_types', 'left')->on('form_fields.field_type_id','=','form_field_types.id')
            ->where('clean_name','=',$clean_name)
            ->execute();
        return current($result->as_array());
    }
    
    static function findAllGroups(){
        $result = \DB::select()->from('form_sections')->execute();
        
        $groups = array();
        foreach($result->as_array() as $row){
            $groups[$row['id']] = $row;
        }
        return $groups;
    }
    
    static function findGroupsByIDs($ids){
        $result = \DB::select()->from('form_sections')->where('id', 'in', $ids)->execute();
        
        $groups = array();
        foreach($result->as_array() as $row){
            $groups[$row['id']] = $row;
        }
        return $groups;
    }
    
    static function findByType($object_id, $type_id){
        $result = \DB::select()->from('form_fields')->where('object_id', '=', $object_id)->where('field_type_id','=',$type_id)->execute();
        return $result->as_array();
    }

    static function findAdditional(){
        $result = Form_field::select('form_fields.*','form_field_types.f_field')
            ->leftJoin('form_field_types', 'form_fields.field_type_id','form_field_types.id')
            ->where('form_fields.system', '!=', 1)
            ->get()
            ->toArray();
        return $result;
    }

    static function getCustomObjectFields(){
        $result = \DB::select('form_fields.*','form_field_types.f_field')
            ->from('form_fields')
            ->join('form_field_types', 'left')->on('form_fields.field_type_id','=','form_field_types.id')
            ->where('form_fields.system', '!=', 1)
            ->execute();
        return $result->as_array();
    }
    
    static function findFeeFields($object_id){
        $result = \DB::select()->from('form_fields')->where('object_id', '=', $object_id)->where('fee','=',1)->execute();
        return $result->as_array();
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
        
        $result = \DB::select('ofo.*')->from(array('form_field_options','ofo'))
                ->join(array('form_fields', 'of'))->on('of.id', '=', 'ofo.object_field_id')
                ->where('of.object_id', '=', $object_id)
                ->order_by('ofo.object_field_id')
                ->order_by('ofo.sort')
                ->execute();
        
        $options = array();
        foreach($result->as_array() as $row){
            $options[$row['object_field_id']][$row['id']] = $row['value'];
        }
        
        return $options;
        
    }
    
    static function add($data){
        
        //$data['clean_name'] = preg_replace('/[^0-9a-z_]/i','', str_replace(' ','_', strtolower($data['name'])));
        
        $result = \DB::insert('form_fields')->set($data)->execute();
        return current($result);
    }
    
    static function update_($id, $data){
        $result = \DB::update('form_fields')->set($data)->where('id', '=', $id)->execute();
    }
    
    static function delete_($id){
        $result = \DB::delete('form_fields')->where('id','=',$id)->execute();
    }
       
    static function resort($sorts){
        
        foreach($sorts as $id => $sort){
            DB::update('form_fields')->set(array('sort' => $sort))->where('id','=', $id)->execute();
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

        $query = \DB::select(array('f.id','field_id'),array('f.name','field_name'),array('f.clean_name','field_clean_name'),array('f.field_type_id','field_type_id'),
            array('f.field_type_id','field_type_id'), array('fsf.required', 'field_required'), array('fsf.sort','field_sort'), array('fsf.read_only', 'field_read_only'),
            array('fsection.id', 'section_id'), array('fsection.sort', 'section_sort'), array('fsection.name', 'section_name'),
            array('fcontainer.sort', 'container_sort'), array('fcontainer.id', 'container_id'), array('fcontainer.name', 'container_name'),
            array('foptions.id','option_id'), array('foptions.sort','option_sort'),array('foptions.value','option_value'))
         ->from(array('form_sections_fields', 'fsf'))
         ->join(array('form_fields','f'))->on('f.id','=','fsf.field_id')
         ->join(array('form_sections', 'fcontainer'))->on('fcontainer.id','=','fsf.container_id')
         ->join(array('form_sections', 'fsection'))->on('fsection.id','=','fcontainer.parent_id')
         ->join(array('form_field_options', 'foptions'),'LEFT')->on('foptions.object_field_id','=','f.id');

        if($field_ids){
            $query->where('f.id','IN', $field_ids);
        }

        if($company_id){
            $query->where('fsection.company_id','=', $company_id);
        }

        $query->order_by('fsection.sort', 'asc');
        $query->order_by('fcontainer.sort', 'asc');
        $query->order_by('fsf.sort', 'asc');
        $query->order_by('foptions.sort', 'asc');

        $result = $query->execute()->as_array();

        return $result;
    }

    static function findByFormId($form_section_id){

        $query = \DB::select(array('f.id','field_id'),array('f.name','field_name'),array('f.clean_name','field_clean_name'),array('f.field_type_id','field_type_id'),
            array('f.field_type_id','field_type_id'), array('fsf.required', 'field_required'), array('fsf.sort','field_sort'), array('fsf.read_only', 'field_read_only'),
            array('fsection.id', 'section_id'), array('fsection.sort', 'section_sort'), array('fsection.name', 'section_name'),
            array('fcontainer.sort', 'container_sort'), array('fcontainer.id', 'container_id'), array('fcontainer.name', 'container_name'),
            array('foptions.id','option_id'), array('foptions.sort','option_sort'),array('foptions.value','option_value'))
            ->from(array('form_sections_fields', 'fsf'))
            ->join(array('form_fields','f'))->on('f.id','=','fsf.field_id')
            ->join(array('form_sections', 'fcontainer'))->on('fcontainer.id','=','fsf.container_id')
            ->join(array('form_sections', 'fsection'))->on('fsection.id','=','fcontainer.parent_id')
            ->join(array('form_field_options', 'foptions'),'LEFT')->on('foptions.object_field_id','=','f.id');

        $query->where('fsection.id','=', $form_section_id);

        $query->order_by('fsection.sort', 'asc');
        $query->order_by('fcontainer.sort', 'asc');
        $query->order_by('fsf.sort', 'asc');
        $query->order_by('foptions.sort', 'asc');

        $result = $query->execute()->as_array();

        return $result;
    }




    static function validate($factory){
        
        $val = \Validation::forge($factory);

        $val->add('name', 'Name')
            ->add_rule('required');

        $val->add('field_type_id', 'Field Type')
            ->add_rule('required');

        return $val;
    }
}
