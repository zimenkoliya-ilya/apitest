<?php

namespace App\Models\cases;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CasesView extends Model
{
    use HasFactory;
    protected $table = "case_views";
    protected $fillable = [
        'user_id',
        'case_id',
        'timestamp',
    ];
    public function upsert($case_id, $user_id){
        $profile = CasesView::where('user_id', $user_id)->where('case_id', $case_id)->get()->toArray();
        
        $data = array(
            'user_id' =>$user_id,
            'case_id' =>$case_id,
            'timestamp' => date('Y-m-d H:i:s')
        );
        if($profile){
            $result = self::where('user_id', $user_id)->where('case_id', $case_id)->first();
            $result->update($data);
        }else{
            $result = self::create($data);
        }
        return $result;
    }

    public function add($data){
        $result = CasesView::create($data);
        return $result;
    }

    public function delete_($user_id){
        $result = CasesView::where('user_id', $user_id)->delete();
        return $result;
    }

    public function update_($user_id, $case_id, $data){
        $result = CasesView::where('user_id', $user_id)->where('case_id', $case_id)->first();
        $result->update($data);
        return $result;
    }

    public function find_($uid){
        $result = CasesView::where('user_id', $uid)->get()->toArray();
        return current($result);
    }
}
