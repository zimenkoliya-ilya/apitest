<?php

namespace App\Models\queue;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Action;
class QueueTimers extends Model
{
    use HasFactory;
    protected $table = "queue_timers";
    protected $fillable = [
        'case_id',
        'action_id',
        'task',
        'target_id',
        'created',
        'processed',
        'at',
        'error',
    ];
    function isAfterHours(){
        if(date("H:i:s") > "18:00:00" || date("H:i:s") < "08:00:00"){
            return true;
        }

        return false;
    }

    function addToQueue($task, $case_id, $target_id){

         $payload = array(
           'case_id' => $case_id,
           'task' => $task,
           'target_id' => $target_id,
           'created' => date("Y-m-d H:i:s")
         );

         QueueTimers::create($payload);
    }

    function processQueue($type=null){

        if(!$this->isAfterHours()){

            if($type){
                $queue_items = QueueTimers::where("processed", null)->where('task', $type)->get()->toArray();
            }else {
                $queue_items = QueueTimers::where("processed", null)->get()->toArray();
            }

           if($queue_items){
               foreach($queue_items as $item){
                    $result = QueueTimers::find($id)->fill(['processed' => date("Y-m-d H:i:s")]);
                    $result->update();
                   Action::performTask($item['task'],$item['case_id'],$item['target_id']);
               }
           }
        }

    }
}
