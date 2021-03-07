<?php

namespace App\Models\document;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Account;
class DocumentDownloads extends Model
{
    use HasFactory;
    protected $table = "document_downloads";
    protected $fillable = [
        'case_id',
        'token',
        'created',
        'type_id',
        'case_document_id',
        'document_id',
        'ip_address'
    ];
    public function findByToken($token)
    {
        $result = DocumentDownloads::
            join('documents', 'documents.id','document_downloads.document_id')
            ->join('case_documents', 'case_documents.id', 'document_downloads.case_document_id')
            ->where('token', $token)
            ->get()
            ->toArray();
        return $result;
    }
    public function add($name, $message, $case_id){
        $data = array(
            'message' => $message,
            'name' => $name,
            'created' => date("Y-m-d H:i:s"),
            'case_id' => $case_id,
            'created_by' => Account::getUserId()
        );
        $result = DocumentActivity::create($data);
        return current($result);
    }
}
