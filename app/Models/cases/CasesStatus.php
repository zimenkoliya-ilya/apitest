<?php

namespace App\Models\cases;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CasesStatus extends Model
{
    use HasFactory;
    protected $table = "case_statuses";
    protected $primaryKey = 'case_id';
    protected $fillable = [
        'case_id',
        'is_company',
        'status_id',
        'compaign_id',
        'docs_status',
        'doc_signed',
        'is_client',
        'is_deleted',
        'accounting_status_id',
        'dialer_id',
        'renewal_date',
        'paused',
        'updated',
        'created',
        'status_updated',
        'accounting_updated',
        'financed',
        'activation_date',
        'termination_date',
        'accounting_type',
        'submission_ready',
        'account_type_id',
        'poi_id',
        'processor_id',
        'shark_tank_date',
        'shark_tank',
        'direct_mail_id',
    ];

    static function find_($case_id){
        $result = current(CasesStatus::where('case_id', $case_id)->get()->toArray());
        return $result;
    }
}
