<?php

namespace App\Models\flyer;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FlyerDisclaimers extends Model
{
    use HasFactory;
    protected $table = "flyer_disclaimers";
    static function find_($id){
        $result = FlyerDisclaimers::find($id)->toArray();

        return $result;
    }

    static function findAll(){
        $result = FlyerDisclaimers::from('flyer_disclaimers as fd')
            ->orderBy('fd.name', 'asc')
            ->groupBy('fd.id')
            ->get()
            ->toArray();
        return $result;
    }
}
