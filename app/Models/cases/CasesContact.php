<?php

namespace App\Models\cases;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Exception;
class CasesContact extends Model
{
    use HasFactory;
    protected $table = 'case_contact';
    public function findByPhone($phone){
        $phone = preg_replace('/[^0-9]/','', $phone);
        $result = current(CasesContact::from('case_contact as cc')
                    ->select('cc.case_id')
                    ->where('cc.primary_phone', $phone)
                    ->orWhere('cc.secondary_phone', $phone)
                    ->get()
                    ->toArray());
         if($result){
             return $result['case_id'];
         }
        return false;
    }
 
     public function findOneOrFailByVendorID($vendor_id, $company_id = null){
        $query = CasesContact::from('case_contact as cc')
                ->select('cc.case_id')
                ->where('cc.vendor_id', $vendor_id);
        if($company_id){
            $query->leftJoin('cases','cases.id', 'cc.case_id');
            $query->where('cases.company_id', $company_id);
        }
        $result = $query->get()->toArray();
        if($result){
            if(count($result) > 1){
                // Duplicate Records
                throw new \Exception('Duplicate Records');
            }
            $case = current($result);
            return $case['case_id'];
        }
         return $result;
    }
 
     public function findByMobile($phone, $company_id = null){
        // Formatting
        $phone = preg_replace('/[^0-9]/','', $phone);
        // $phone = \Formatter\Format::digits10($phone);
        $query = CasesContact::from('case_contact as cc')
                ->select('cc.*')
                ->where('cc.mobile_phone', $phone);
        if($company_id){
            $query->leftJoin('cases', 'cases.id', 'cc.case_id')
                    ->where('cases.company_id', $company_id);
        }
        $result =  current($query->get()->toArray());
        return $result;
    }
}
