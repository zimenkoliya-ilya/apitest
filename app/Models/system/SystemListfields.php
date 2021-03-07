<?php

namespace App\Models\system;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemListfields extends Model
{
    use HasFactory;
    protected $table = "list_fields";
    static function find_($id){
            
        $result = \DB::select()
                ->from(array('list_fields','l'))
                ->where('l.id', '=', $id)
                ->execute();
        return current($result->as_array());
    }
    
    static function findAll(){
        
        $result = \DB::select()->from('list_fields')
            ->execute();
        return $result->as_array();
        
    }

    static function findAllBySystemAndCompany($company_id){

        $result = \DB::select()->from('list_fields')
            ->execute();
        return $result->as_array();

    }


    static function findByListId($list_id, $sorted = false){
        $result = \DB::select()
                ->from('list_fields')
                ->where('list_id', '=', $list_id);

        if($sorted){
            $result->order_by('sort');
        }

        $result = $result->execute();

        return $result->as_array();
    }

    static function deleteByListId($list_id){
        $result = \DB::delete('list_fields')->where('list_id', '=', $list_id)->execute();
    }

    static function findByFilter($filter, $columns){
        $query = self::select($columns)
            ->from('list_fields as lf')
            ->join('lists as l', 'l.id', 'lf.list_id')
            ->join('form_fields as of', 'of.id', 'lf.field_id');
        if(isset($filter['list_id'])){
            $query->where('list_id', $filter['list_id']);
        }
        $query->orderBy('lf.sort','Asc');
        $result = $query->get()->toArray();
        return $result;
    }
    
    static function add($data){
        $result = \DB::insert('list_fields')->set($data)->execute();
        return current($result);
    }
    
    static function update_($id, $data){
        $result = \DB::update('list_fields')->set($data)->where('id', '=', $id)->execute();
    }
    
    static function delete_($id){
        $result = \DB::delete('list_fields')->where('id','=',$id)->execute();
    }
    
    static function validate($factory){
        
        $val = \Validation::forge($factory);


        return $val;
    }
}
