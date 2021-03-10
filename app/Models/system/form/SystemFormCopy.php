<?php

namespace App\Models\system\form;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\system\form\sectioin\SystemFormSectionFields;
class SystemFormCopy extends Model
{
    use HasFactory;
   
    static function duplicate_form($form_id, $company_to)
    {
        $new_company_id = $company_to;
        $old_section_ids = array();
        $old_container_ids = array();

        $sections = SystemFormContainers::where('id', $form_id)->orWhere('parent_id', $form_id)->get()->toArray();
        foreach ($sections as $section) {
            if($section['type'] == 'section'){
                $old_section_ids[] = $section['id'];
            }
            if($section['type'] == 'container'){
                $old_container_ids[] = $section['id'];
            }
            
            $old_id = $section['id'];
            $section['company_id'] = $new_company_id;
            unset($section['id']);
            $new_id = current(SystemFormContainers::create($section));
            $new_section_ids[$old_id] = $new_id;
        }

        $new_sections = SystemFormContainers::where('company_id', $new_company_id)->get()->toArray();
        foreach ($new_sections as $section) {
            if (isset($new_section_ids[$section['parent_id']]) && !empty($new_section_ids[$section['parent_id']])) {
                $result = SystemFormContainers::find($section['id'])->fill(['parent_id' => $new_section_ids[$section['parent_id']]]);
                $result->update();
            }
        }
        $form_sections_fields = SystemFormContainers::whereIn('section_id', $old_section_ids)->whereIn('container_id', $old_container_ids)->get()->toArray();
        foreach ($form_sections_fields as $fields) {
            $fields['company_id'] = $new_company_id;
            unset($fields['id']);
            $fields['section_id'] = $new_section_ids[$fields['section_id']] ?? 0;
            $fields['container_id'] = $new_section_ids[$fields['container_id']] ?? 0;
            $new_field_id = current(SystemFormContainers::create($fields));
        }
    }
}
