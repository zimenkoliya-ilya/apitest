<?php

namespace App\Models\system\form\section;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemFormSectionShared extends Model
{
    use HasFactory;
    protected $table = "form_sections_shared";
    static function find_($section_id)
    {
        $result = SystemFormSectionShared::find($section_id);
        return $result->toArray();
    }

    static function findCompanyIdsBySection($section_id){
        $result = SystemFormSectionShared::select('company_id')
            ->where('form_section_id', $section_id)
            ->get();
        return $result->toArray();

    }


    static function addMultiple($company_ids, $section_id){
        
        foreach($company_ids as $company_id){
            $query = SystemFormSectionShared::
            create(['form_section_id'=>$section_id,'company_id'=>$company_id,'active'=>1]);
        };

        return $query;
    }


    static function deleteByCompanyIds($company_ids, $section_id){
        $query = SystemFormSectionShared::where('form_section_id', $section_id)
            ->whereIn('company_id', $company_ids)->delete();
        return $query;
    }

    static function update_($id, $data)
    {
        $data['updated'] = date("Y-m-d H:i:s");
        $result = SystemFormSectionShared::find($id)->fill($data);
        $result->update();
    }

    static function upsert($new_company_ids, $section_id)
    {

        foreach($new_company_ids as $id){
            // new list User Ids
            $new_ids[] = $id['company_id'];
        }

        $old_ids = self::findCompanyIdsBySection($section_id);

        if($old_ids) {
            foreach ($old_ids as $old_id) {
                // current list User Ids
                $old_ids[] = $old_id['company_id'];
            }
        }else{
            $old_ids = array();
        }

        $ids_to_add = array_diff($new_ids, $old_ids);
        $ids_to_remove = array_diff($old_ids, $new_ids);

        if(isset($ids_to_add) && !empty($ids_to_add)){
            // Add
            self::addMultiple($ids_to_add, $section_id);
        }

        if(isset($ids_to_remove) && !empty($ids_to_remove)){
            // Remove Users
            self::deleteByCompanyIds($ids_to_remove,$section_id);
        }

    }

    static function findAllBySection($section_id){
        $forms_shared = SystemFormSectionShared::select('fss.*','co.name as company_name', 'co.id as company_id')
            ->from('form_sections_shared as fss')
            ->join('companies as co', 'co.id', 'fss.company_id')
            ->join('form_sections as fs', 'fs.id','fss.form_section_id')
            ->where('fss.form_section_id', $section_id)
            ->get()->toArray();
        return $forms_shared;
    }

    static function deleteFromNotInResult($section_id, $company_ids){
        $delete_companies = SystemFormSectionShared::from('form_sections_shared as fss')
            ->where('fss.form_section_id', $section_id)
            ->whereNotIn('fss.company_id', $company_ids)
            ->delete();
        return $delete_companies;
    }
}
