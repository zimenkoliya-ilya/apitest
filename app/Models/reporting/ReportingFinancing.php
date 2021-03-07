<?php

namespace App\Models\reporting;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReportingFinancing extends Model
{
    use HasFactory;
    protected $table = "financing";
    function setQuery($option){
        switch($option){
            case 'default':
                $this->filters->setFilter('Search By','date_field',array(array('id'=>'f.created','name'=>'Financed Date')),'select', true);
                $this->query = \DB::select('f.case_id','cc.first_name','cc.last_name','f.application_id','f.score','f.track','comp.name',\DB::expr('COALESCE(f.status, 0) as ea_status'))
                ->from(array('financing', 'f'))
                ->join(array('cases','c'), 'LEFT')->on('c.id','=','f.case_id')
                ->join(array('case_contact','cc'), 'LEFT')->on('cc.case_id','=','f.case_id')
                ->join(array('case_statuses','cs'), 'LEFT')->on('cs.case_id','=','f.case_id')
                ->join(array('companies','comp'), 'LEFT')->on('comp.id','=','c.company_id')
                ->group_by('f.case_id');

                $this->query->where('f.created','between',
                    array(
                        date('Y-m-d', strtotime($this->dates['start_date'])).' 00:00:00',
                        date('Y-m-d', strtotime($this->dates['end_date'])).' 23:59:59')
                );

                $this->dateIsSet = true;

                //if(!isset($this->date_field)){
                //    $this->setDateField('f.created');
               // }

                //$this->setDateRange();

                $this->useACL();

                break;
        }
    }
}
