<?php

namespace App\Models\system\form;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemFormSections extends Model
{
    use HasFactory;
    protected $table = "form_sections";
    protected $fillable = [
        'company_id',
        'object_id',
        'vertical_id',
        'type',
        'name',
        'template',
        'parent_id',
        'sort',
        'instruction',
        'enabled',
        'created',
        'updated',
    ];
    static function find($section_id)
    {
        $result = SystemFormSections::where('type', 'section')
            ->where('id', $section_id)
            ->get();
        return current($result->toArray());
    }

    static function findByCompany($company_id)
    {
        $result = SystemFormSections::where('form_sections.type', 'section')
            ->where('form_sections.company_id', $company_id)
            ->orderBy('form_sections.sort', 'Asc')
            ->where('form_sections.enabled', 1)
            ->get();
        return $result->toArray();
    }

    static function findByCompanyAndShared($company_id)
    {
        $result = SystemFormSections::
            leftJoin('form_sections_shared', 'form_sections_shared.form_section_id', 'form_sections.id')
            ->where('form_sections.type', 'section')
            // ->where_open()
            ->where('form_sections.company_id', '=', $company_id)
            ->or_where('form_sections_shared.company_id','=', $company_id)
            // ->where_close()
            ->where('form_sections.enabled','=', 1)
            ->orderBy('form_sections.sort', 'Asc')
            ->groupBy('form_sections.id')
            ->get();
        return $result->toArray();
    }

    static function findByCompanyAndFieldCount($company_id)
    {
        $result = SystemFormSections::selectRaw('form_sections.*, count(form_sections_fields.id) as field_count')
            ->leftJoin('form_sections_fields', 'form_sections_fields.section_id', 'form_sections.id')
            ->where('form_sections.type', 'section')
            ->where('form_sections.company_id', $company_id)
            ->groupBy('form_sections.id')
            ->get();
        return $result->toArray();
    }

    static function delete_($id){
        SystemFormSections::find($id)->delete();
    }
}
