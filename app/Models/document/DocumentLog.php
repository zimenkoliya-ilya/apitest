<?php

namespace App\Models\document;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Account;
class DocumentLog extends Model
{
    use HasFactory;
    protected $table = "document_log";
    protected $fillable = [
        'document_id',
        'viewed',
        'viewed_by'
    ];
    public function findAll($document_id)
    {
        $result = DocumentLog::
            from('document_log as dl')
            ->where('dl.id', '=', $document_id)
            ->get()
            ->toArray();
        return current($result);
    }
    public function add($document_id){
        $data = array(
            'document_id' => $document_id,
            'viewed' => date("Y-m-d H:i:s"),
            'viewed_by' => Account::getUserId()
        );
        $result = DocumentLog::create($data);
        return current($result);
    }
}
