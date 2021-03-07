<?php

namespace App\Models\cases;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CasesStage extends Model
{
    use HasFactory;
    public function update_($case_id, $is_client){
        $case = CasesStatus::find_($case_id);
        if($is_client == $case['is_client']){
            return false; // same status
        }
        $payload = array('is_client' => $is_client,'updated'=> date("Y-m-d H:i:s"));
        if($is_client == 1 && isset($case) && empty($case['activation_date'])){
            $payload['activation_date'] = date("Y-m-d H:i:s");
        }
        if($is_client == 2){
            $payload['termination_date'] = date("Y-m-d H:i:s");
        }
        $result = CasesStatus::where('case_id', $case_id)->first();
        $result->update($payload);
        return $result;
    }


    public function getStageName($is_client){
        if($is_client == 0){
            return 'Lead';
        }
        if($is_client == 1){
            return 'Client';
        }
        if($is_client == 2){
            return 'Terminated';
        }
    }
}
