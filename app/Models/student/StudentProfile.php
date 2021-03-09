<?php

namespace App\Models\student;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentProfile extends Model
{
    use HasFactory;
    protected $table = "student_profiles";
    public function delete_($case_id){
        return StudentProfile::where('case_id','=', $case_id)->delete();
    }

    public function findByCaseID($case_id){
        return current(StudentProfile::where('case_id','=', $case_id)->get()->toArray());
    }

}
