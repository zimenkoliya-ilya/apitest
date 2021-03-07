<?php

namespace App\Models\document;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentActivity extends Model
{
    use HasFactory;
    protected $table = "document_activity";
    protected $fillable = [
        'name',
        'message',
        'created',
        'created_by',
        'case_id',
        'created_at',
        'updated_at'
    ];
    public function findAllByCase($case_id)
    {
        $result = \DB::select()
            ->from(array('document_activity', 'dl'))
            ->where('dl.case_id', '=', $case_id)
            ->execute();
        return $result->as_array();
    }


    public function add($name, $message, $case_id){
        $data = array(
            'message' => $message,
            'name' => $name,
            'created' => date("Y-m-d H:i:s"),
            'case_id' => $case_id,
            'created_by' => \Model_Account::getUserId()
        );
        $result = \DB::insert('document_activity')->set($data)->execute();
        return current($result);
    }
}
