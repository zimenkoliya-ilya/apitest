<?php

namespace App\Models\system;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Account;
class SystemAccess extends Model
{
    use HasFactory;
    static function queryAccess($query){

        // Case File Access (Search, Open, Contacts Filter)
        if(SystemAccess::getUserId() == 1 || Account::getCompanyId() == 1 || !isset($_SESSION['user'])){
            // SYSTEM
            return $query;
        }
        switch(\Account::getType()){
            case 'Master':
                $query->where('c.company_id','IN', \Account::getNetworkIds());
                break;
            case 'Network':
                $query->where('c.company_id','IN', \Account::getNetworkIds());
                break;
            case 'Company':
                $query->where('c.company_id','IN', \Account::getNetworkIds());
                 break;
            case 'Region':
                $query->join(array('case_assignments','ca_region'),'left')->on('ca_region.case_id','=','c.id');
                $query->where('ca.user_id','=', \Account::getUserId());
                break;
            case 'Campaign':
                //$query->where('rg.region_id','=', Model_System_User::getSessionMeta('region_id'));
                $campaigns = Model_System_Campaign::findByGroup(Model_System_User::getSessionMeta('campaign_group_id'));

                if(is_array($campaigns)){
                    foreach($campaigns as $c){
                        $ids[] = $c['id'];
                    }
                    $query->where('cs.campaign_id','IN', $ids);
                }
                break;
            case 'User':
               /* $query->join(array('case_assignments','ca_usr'),'left')->on('ca_usr.case_id','=','c.id')->on('ca_user.user_id','=', \Account::getUserId());
                $query->where_open();*/
                //$query->where('ca_usr.user_id','=', \Account::getUserId());
                $query->where('c.company_id', 'IN', \Account::getNetworkIds());
                break;
        }

        return $query;
    }
}
