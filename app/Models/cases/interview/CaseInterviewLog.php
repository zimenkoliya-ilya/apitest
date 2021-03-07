<?php

namespace App\Models\cases\interview;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Account;
class CaseInterviewLog extends Model
{
    use HasFactory;
    protected $table = "case_interview_logs";
    public function addMany($events, $case_id)
    {
        $date = date('Y-m-d H:i:s');
        $user = Account::getUserId();
        foreach($events as $event){
            $field_id = (isset($event[3]) && !empty($event[3]) ? $event[3] : null);
            $array[] =  ['case_id'=>$case_id,'created'=>$date, 
                        'created_by'=>$user,'field'=>$event[0],
                        'event'=>$event[1], 'value'=>$event[2],
                        'field_id'=>$field_id];
        }
        $result = CaseInterviewLog::insert($array);
        return $result;
    }
}
