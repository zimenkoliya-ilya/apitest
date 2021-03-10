<?php

namespace App\Models\system\form\section;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\system\form\SystemFormFields;
class SystemFormSectionFields extends Model
{
    use HasFactory;
    protected $table = "form_sections_fields";
    protected $fillable = [
        'company_id',
        'section_id',
        'container_id',
        'field_id',
        'sort',
        'read_only',
        'required',
        'created',
        'updated',
    ];
    public function update_($id, $data){
        $result = SystemFormSectionFields::find($id)->fill($data);
        $result->update();
    }
    public function upsert($update){
        $record = self::find_($update['company_id'], $update['container_id'], $update['field_id']);
        if($record){
            // update
            self::update_($record['id'], $update);
        }else{
            // add
            self::add($update);
        }
    }

    public function findAllFieldsAndSections($company_id){

        $result = SystemFormSectionFields::select('fsf.*',
            'sections.name as section_name',
            'containers.name as container_name',
            'containers.sort as container_sort',
            'ff.name as field_name',
            'ff.clean_name as field_tag',
            'fsf.required',
            'ffo.id as field_option_id',
            'ffo.value as field_option_name',
            'ft.f_field as field_option_type'
        )
            ->from('form_sections_fields as fsf')
            ->leftJoin('form_sections as sections', 'fsf.section_id', 'sections.id')
            ->leftJoin('form_sections as containers', 'fsf.container_id', 'containers.id')
            ->leftJoin('form_fields as ff', 'ff.id', 'fsf.field_id')
            ->leftJoin('form_field_types as ft', 'ft.id', 'ff.field_type_id')
            ->leftJoin('form_field_options as ffo', 'ffo.object_field_id', 'fsf.field_id')
            ->where('fsf.company_id', $company_id)
            ->orderBy('sections.sort','Asc')
            ->orderBy('containers.sort','Asc')
            ->orderBy('fsf.sort','Asc')
            ->orderBy('ffo.sort','Asc')
            ->get();
        return $result->toArray();

    }


    public function findAllFieldsBySection($company_id, $section_id){

        $result = SystemFormSectionFields::select('fsf.*',
           'sections.name as section_name',
           'containers.name as container_name',
           'containers.sort as container_sort',
           'ff.name as field_name',
           'ff.clean_name as field_tag',
            'fsf.required as ff.field_type_id',

           'ffo.id as field_option_id',
           'ffo.value as field_option_name',
           'ft.f_field as field_option_type')
            ->from('form_sections_fields as fsf')
            ->leftJoin('form_sections as sections', 'fsf.section_id', 'sections.id')
            ->leftJoin('form_sections as containers', 'fsf.container_id', 'containers.id')
            ->leftJoin('form_fields as ff', 'ff.id', 'fsf.field_id')
            ->leftJoin('form_field_types as ft', 'ft.id', 'ff.field_type_id')
            ->leftJoin('form_field_options as ffo', 'ffo.object_field_id', 'fsf.field_id')
            ->where('fsf.company_id', $company_id)
            ->where('sections.id', $section_id)
            ->orderBy('sections.sort','ASC')
            ->orderBy('containers.sort','ASC')
            ->orderBy('fsf.sort','ASC')
            ->orderBy('ffo.sort','ASC')
            ->get();
        return $result->toArray();
    }

    public function groupFieldSections($ar){

        $payload = array();

        foreach($ar as $s) {
            // Sections
            $payload[$s['section_id']] = array(
                'section_name' => $s['section_name'],
                'section_id' => $s['section_id']
            );
        }

        foreach($ar as $c) {
            // Containers

            $payload[$c['section_id']]['containers'][$c['container_id']] = array(
                'container_name' => $c['container_name'],
                'container_id' => $c['container_id'],
                'sort' => $c['container_sort']
            );

        }


        foreach($ar as $options) {
            // Options
            $payload[$options['section_id']]['containers'][$options['container_id']]['fields'][$options['field_id']] = $options;
        }


        foreach($ar as $options){
            // Options
            if(isset($options['field_option_id'])){
                $option = array(
                    'id' => $options['field_option_id'],
                    'name' => $options['field_option_name'],
                    'type' => $options['field_option_type']
                );
                $payload[$options['section_id']]['containers'][$options['container_id']]['fields'][$options['field_id']]['options'][] = $option;
            }
        }
        return $payload;
    }

    public function grouping($ar){
        $payload = array();
        $sections = array();
        foreach($ar as $s) {
            // Sections
            if(!isset($sections[$s['section_id']])) {
                $sections[$s['section_id']] = array(
                    'section_name' => $s['section_name'],
                    'section_id' => $s['section_id']
                );
            }

            if(!isset($sections[$s['section_id']]['containers'][$s['container_id']])){
                $sections[$s['section_id']]['containers'][$s['container_id']] = array(
                    'container_name' => $s['container_name'],
                    'container_id' => $s['container_id'],
                    'sort' => $s['container_sort']
                );
            }

            if(!isset($sections[$s['section_id']]['containers'][$s['container_id']]['fields'][$s['field_id']])){
                $sections[$s['section_id']]['containers'][$s['container_id']]['fields'][$s['field_id']] = array(
                    'field_id' => $s['field_id'],
                    'clean_name' => $s['field_tag'],
                  //  'name' => $s['field_name'],
                    'field_name' => $s['field_name'],
                    'required' => $s['required'],
                    'field_type_id' => $s['field_type_id']
                );
            }

        }

        $payload['sections'] = $sections;

        return $payload;
    }

    public function fix_keys($array) {
        $numberCheck = false;
        foreach ($array as $k => $val) {
            if (is_array($val)) $array[$k] = self::fix_keys($val); //recurse
            if (is_numeric($k)) $numberCheck = true;
        }
        if ($numberCheck === true) {
            return array_values($array);
        } else {
            return $array;
        }
    }

    public function findDiffBySection($section_id, $company_id){
        $result = SystemFormFields::select('fsf.id', 'f.id as field_id', 'f.name', 'f.clean_name', 'ft.structure', 'f.system', 'f.field_type_id',
            'f.name as field_name',
            'f.clean_name as field_tag', 'fsf.required')
            ->from('form_fields as f')
            ->leftJoin('form_sections_fields as fsf', function($query) use ($company_id, $section_id){
                $query->where('f.id', 'fsf.field_id')
                    ->where('fsf.company_id', $company_id)
                    ->where('fsf.section_id', $section_id);
            })
            ->join('form_field_types as ft', 'f.field_type_id', 'ft.id')
            ->where('fsf.id', NULL)
            ->orderBy('f.name','Asc')
            ->get();
        return $result->toArray();
    }

    public function find_($company_id, $container_id, $field_id){
        $query = SystemFormSectionFields::where('company_id', $company_id)
            ->where('container_id', $container_id)
            ->where('field_id', $field_id)->get();
        return current($query);
    }

    public function add($data){
        $data['created'] = date("Y-m-d H:i:s");
        $result = SystemFormSectionFields::create($data);
        return $result;
    }


    public function delete_($id){
        SystemFormSectionFields::find($id)->delete();
    }


    public function deleteGroup($container_fields){
        foreach($container_fields as $field){
            self::delete_($field['id']);
        }
    }
}
