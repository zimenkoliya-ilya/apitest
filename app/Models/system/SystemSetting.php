<?php

namespace App\Models\system;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    use HasFactory;
    protected $table = 'settings';
    // used
    static function get_($type, $company_id = null, $name = null, $active=null){
        $result = array();

        $query = Systemsetting::where('type', $type);

        if($company_id){
            $query->where('company_id', $company_id);
        }
        if($name){
            $query->where('name', $name);
        }

        if($active){
            $query->where('active',  1);
        }

        $result = $query->get()->toArray();
        /*
        if(!isset($result) || empty($result)){
            $default = self::getByType($type);
            if($default){
                $result = &$default;
            }else {
                return false;
            }
        }*/

        //$setting = array();
        //foreach($result as $item){
        //    $setting[$item['name']] = $item['value'];
        //}
        return current($result);

    }

    static function find_($id){

        $result = \DB::select()
            ->from('settings')
            ->where('id', '=', $id)
            ->execute();

        return current($result->as_array());
    }

    static function getAll($type, $company_id = null, $name = null){

        $query = \DB::select()->from('settings')->where('type','=',$type);

        if($company_id){
            $query->where('company_id','=', $company_id);
        }
        if($name){
            $query->where('name','=', $name);
        }

        $result = $query->execute()->as_array();

        return $result;

    }

    static function getAllByCompanyIDS($type, $company_ids){

        $query = \DB::select()->from('settings')->where('type','=',$type);
        $query->where('company_id','IN', $company_ids);
        $result = $query->execute()->as_array();

        return $result;

    }


    static function getByType($type){
        $result = \DB::select()->from('settings')->where('type','=',$type)->execute();
        return $result->as_array();
    }

    static function getById($setting_id){
        $result = \DB::select()->from('settings')->where('id','=',$setting_id)->execute();
        return current($result->as_array());
    }

    static function getByName($name){
        $result = \DB::select()->from('settings')->where('name','=',$name)->execute();
        return current($result->as_array());
    }
}
