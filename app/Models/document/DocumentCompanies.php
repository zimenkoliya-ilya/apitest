<?php

namespace App\Models\document;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentCompanies extends Model
{
    use HasFactory;
    protected $table = "documents_companies";
    protected $fillable = [
        'company_id',
        'form_id'
    ];
    public function findAllByCompany($company_id, $format='form'){
        $result = DocumentCompanies::select('documents.*')
            ->leftJoin('documents', 'documents.id', 'documents_companies.form_id')
            ->where('documents_companies.company_id', $company_id)
            ->get()->toArray();
        if($result){
            if($format == 'form') {
                foreach ($result as $item) {
                    $ids[] = $item['id'];
                }
                return $ids;
            }else{
                return $result;
            }
        }
        return false;
    }
    public function upsert($company_id, $form_ids)
    {
        DocumentCompanies::where('company_id',$company_id)->delete();

        // Exlude Dupes
        $ids = array_unique($form_ids);

        foreach ($ids as $id) {
            $query = array('form_id'=>$id, 'company_id'=>$company_id);
            $result = DocumentCompanies::create($query);
        }
        return $result;
    }

    public function upsertFormtoCompanies($form_id, $company_ids)
    {
        DocumentCompanies::where('form_id', $form_id)->delete();
        foreach ($company_ids as $id) {
            $query = array("form_id"=>$form_id, 'company_id'=>$id);
            $result = DocumentCompanies::create($query);
        }
        return $result;
    }
}
