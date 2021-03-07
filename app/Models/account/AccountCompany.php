<?php

namespace App\Models\account;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Validator;
class AccountCompany extends Model
{
    use HasFactory;
    protected $table = "accounting_types_companies";
    protected $fillable = [
        'accounting_type_id', 'company_id'
      ];
    public function find_($id){
        $result = AccountCompany::find($id)->toArray();
        return $result;
    }

    public function findAll(){
        $result = AccountCompany::all()->toArray();
        return $result;
    }

    public function findAllByCompany($company_id, $format='form'){
        $result = AccountCompany::where('company_id', $company_id)->get()->toArray();
        if($result){
            if($format == 'form') {
                foreach ($result as $item) {
                    $ids[] = $item['account_type_id'];
                }
                return $ids;
            }else{
                return $result;
            }
        }
        return false;
    }
    public function add($data){
        $result = AccountCompany::create($data);
        return current($result);
    }
    public function update_($id, $data){
        $result = AccountCompany::find($id)->fill($data);
        $result->update();
    }

    public function delete_($id){
        $db = AccountCompany::find($id)->delete();
    }
    // problem
    public function validate($factory){
        $val = Validator::make($factory,[]);
        $val = \Validation::forge($factory);
        return $val;
    }

    public function upsert($company_id, $account_ids){
        $db = AccountCompany::where('company_id', $company_id)->delete();
        $ids = array_unique($account_ids);
        foreach($ids as $id){
            $query[] = array('company_id'=>$company_id, 'accounting_type_id'=>$id);
        }
        $result = AccountCompany::insert($query);
        return $result;
    }
}
