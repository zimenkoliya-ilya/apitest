<?php

namespace App\Models\student;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentLoans extends Model
{
    use HasFactory;
    protected $table = "student_loans";
    public function delete_($case_id){
        return StudentLoans::where('case_id', $case_id)->delete();
    }

    public function findByCaseID($case_id){
        return current(StudentLoans::where('case_id', $case_id)->get()->toArray());
    }
}
