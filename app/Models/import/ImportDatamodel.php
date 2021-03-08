<?php

namespace App\Models\import;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImportDatamodel extends Model
{
    use HasFactory;
    function getModel($model_name, $payload){
        try{
            if(method_exists($this, $model_name)) {
                return $this->{$model_name}($payload);
            }
        }catch(\Exception $e){
            throw new \Exception($e);
        }
    }


    function skywave_voice($payload){
        $data=array();
        // Validate Basics
        if(isset($payload['remove']) && $payload['remove'] == 'yes'){
            throw new \Exception('Remove flag set');
        }
        // Determine Inbound
        if(isset($payload['orig_domain']) && $payload['orig_domain'] != ''){
            // outbound, return false
            throw new \Exception('Outbound call; Skywave Voice; not set for lead');
        }
        // Check for Called Number
        $payload['orig_req_user'] = preg_replace('/^1|\D/', '', $payload['orig_req_user']);// strip 1
        $payload['ani'] = preg_replace('/^1|\D/', '', $payload['ani']);// strip 1
        $data['did'] = $payload['orig_req_user'];
        // Check for Caller ID Number
        $data['primary_phone'] = $data['mobile_phone'] = $payload['ani'];
        // Check for Caller ID Name
        $data['first_name'] = $payload['orig_from_name'];
        // Parse and return import format
        return $data;
        // Is Display Call Popup Active?
    }
}
