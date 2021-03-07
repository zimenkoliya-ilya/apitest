<?php

namespace App\Models\cases;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\system\form\SystemFormFields;
class CasesInterview extends Model
{
    use HasFactory;
    protected $table = "case_additional";
    public function findByCompanyID($company_id){
        $result = CasesInterview::select('case_additional.*', 'form_field_types.f_field', 'form_fields.clean_name')
            ->leftJoin('form_fields', 'case_additional.field_id', 'form_fields.id')
            ->leftJoin('form_field_types', 'form_fields.field_type_id', 'form_field_types.id')
            ->leftJoin('cases', 'cases.id', 'case_additional.case_id')
            ->where('cases.company_id', $company_id)
            ->where('form_fields.system', 0)
            ->get()
            ->toArray();
        return self::build($result);
    }

    public function findByCaseIds($case_ids){
        $result = CasesInterview::select('case_additional.*', 'form_field_types.f_field', 'form_fields.clean_name')
            ->leftJoin('form_fields', 'case_additional.field_id', 'form_fields.id')
            ->leftJoin('form_field_types', 'form_fields.field_type_id', 'form_field_types.id')
            ->leftJoin('cases', 'cases.id', 'case_additional.case_id')
            ->whereIn('cases.id', $case_ids)
            ->where('form_fields.system', 0)
            ->get()
            ->toArray();
        return self::build($result);
    }
    
    public function build($result)
    {
        $form_fields = SystemFormFields::findAdditional();
        $payload = array();
        foreach ($result as $r) {
            $payload[$r['case_id']]['case_id'] = $r['case_id'];
            foreach ($form_fields as $o) {
                if ($o['clean_name'] == $r['clean_name']) {
                    $payload[$r['case_id']][$o['clean_name']] = $r[$r['f_field']];
                } elseif(!isset($payload[$r['case_id']][$o['clean_name']]) || empty($payload[$r['case_id']][$o['clean_name']])){
                    $payload[$r['case_id']][$o['clean_name']] = '-';
                }
            }
        }
        return $payload;
    }
}
