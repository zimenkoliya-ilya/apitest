<?php

namespace App\Models\company;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompanyProfile extends Model
{
    use HasFactory;
    protected $table = "company_profile";
    protected $fillable =[
        'company_id',
        'processor_id',
        'user_fee',
        'processing_fee',
        'renewal_fee'
    ];
    public function find_($id)
    {
        $result = CompanyProfile::where('company_id', $id)->get()->toArray();
        return current($result);
    }

    public function findAll()
    {
        $result = CompanyProfile::all()->toArray();
        return $result;
    }
    
    public function add($data)
    {
        $result = CompanyProfile::create($data);
        return $result;
    }
    // problem not exist note_id column in company_profile table
    public function update_($id, $data)
    {
        $result = CompanyProfile::where('note_id', $id)->fill($data);
        $result->update();
        return $result;
    }
    // problem company_profile table has not id column.
    public function upsert($id, $data)
    {
        $r = self::find($id);
        if($r){
            $result = self::find($id)->fill($data);
            $result->update();
        }else{
            $result = self::create($data);
        }
        return $result;
    }
}
