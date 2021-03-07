<?php

namespace App\Models\account;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Account_types_company;
use Validator;
class Company extends Model
{
    use HasFactory;

    public function find_($id){
        $result = Account_types_company::find($id)->toArray();
        return $result;
    }

    public function findAll(){
        $result = Account_types_company::all()->toArray();
        return $result;
    }

    public function findAllByCompany($company_id, $format='form'){
        $result = Account_types_company::where('company_id', $company_id)->get()->toArray();
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
        $result = Account_types_company::create($data);
        return current($result);
    }
    public function update_($id, $data){
        $result = Account_types_company::find($id)->fill($data);
        $result->update();
    }

    public function delete_($id){
        $db = Account_types_company::find($id)->delete();
    }
    // problem
    public function validate($factory){
        $val = Validator::make($factory,[]);
        $val = \Validation::forge($factory);
        return $val;
    }

    public function upsert($company_id, $account_ids){
        $db = Account_types_company::where('company_id', $company_id)->delete();
        $ids = array_unique($account_ids);
        foreach($ids as $id){
            $query[] = array('company_id'=>$company_id, 'accounting_type_id'=>$id);
        }
        $result = Account_types_company::insert($query);
        return $result;
    }
}
