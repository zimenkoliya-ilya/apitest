<?php

namespace App\Models\emailmarketing;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
class EmailmarketingCampaign extends Model
{
    use HasFactory;
    protected $table = "email_marketing_campaign";

    private static $dataTables = array(
        'email_marketing_campaign' => '
          "email_marketing_campaign", function($table)
          {
              $table->id();
              $table->string("name")->nullable();
              $table->text("description")->nullable();
              $table->integer("active")->nullable();
              $table->timestamps();
          }',
        'email_marketing_campaign_templates' =>'
          `email_marketing_campaign_templates`, function($table)
          {
              $table->id();
              $table->integer(`campaign_id`)->nullable();
              $table->integer(`template_id`)->nullable();
              $table->integer(`order`)->nullable();
              $table->integer(`timeframe`)->nullable();
              $table->timestamps();
          }
        ',
        'email_marketing_campaign_cases' => '
          `email_marketing_campaign_cases`, function($table)
          {
              $table->id();
              $table->integer(`campaign_id`)->nullable();
              $table->integer(`case_id`)->nullable();
              $table->integer(`template_id`)->nullable();
              $table->datetime(`send_date`)->nullable();
              $table->tinyInteger(`active`)->nullable();
              $table->timestamps();
          }
        ',
      );
      
        static function bootstrap()
        {
          $_SESSION['log'] = "";
          $rs_buildModel = self::_buildDataModel(self::$dataTables);
          $rs_updatePermissions = true; //Model_System_Access::updatePermissionsFrom('emailmarketing');
          return ($rs_buildModel && $rs_updatePermissions);
        }
      
        private static function _buildDataModel($table_definition)
        {
          $result = true;
          $fr = Schema::hasTable('email_marketing_campaign');
          if(!$fr){
            $result &= Schema::dropIfExists('email_marketing_campaign');
            $result &= Schema::connection('mysql')->create('email_marketing_campaign', function($table)
              {
                  $table->id();
                  $table->string('name')->nullable();
                  $table->text('description')->nullable();
                  $table->tinyInteger('active')->default(1);
                  $table->timestamps();
              });
            $result &= (DB::select("SHOW TABLES LIKE 'email_marketing_campaign'"));  
          }else{
            $result &=true;
          }
          $fs = Schema::hasTable('email_marketing_campaign_templates');
          if(!$fs){
            $result &= Schema::dropIfExists('email_marketing_campaign_templates');
            $result &= Schema::connection('mysql')->create('email_marketing_campaign_templates', function($table)
              {
                $table->id();
                $table->integer('campaign_id')->nullable();
                $table->integer('template_id')->nullable();
                $table->integer('order')->nullable();
                $table->integer('timeframe')->nullable();
                $table->timestamps();
              });
            $result &= (DB::select("SHOW TABLES LIKE 'email_marketing_campaign_templates'"));  
          }else{
            $result &=true;
          }
          $ft = Schema::hasTable('email_marketing_campaign_cases');
          if(!$ft){
            $result &= Schema::dropIfExists('email_marketing_campaign_cases');
            $result &= Schema::connection('mysql')->create('email_marketing_campaign_cases', function($table)
              {
                $table->id();
                $table->integer('campaign_id')->nullable();
                $table->integer('case_id')->nullable();
                $table->integer('template_id')->nullable();
                $table->datetime('send_date')->nullable();
                $table->tinyInteger('active')->default(1);
                $table->timestamps();
              });
            $result &= (DB::select("SHOW TABLES LIKE 'email_marketing_campaign_cases'"));  
          }else{
            $result &=true;
          }
          dd($result);
          return $result;
        }
      
        /* Getters and setters */
      
        /* Campaign exists by Id */
        static function exists($id) {
          $result = self::get($id);
          if (count($result) > 0) return true;
          return false;
        }
      
        /* Campaign exists by Name */
        static function existsByName($name) {
          $campaign = self::findByName($name);
          if (count($campaign)>0) return true;
          return false;
        }
      
        /* Get campaign by Id */
        static function get($id) {
          $query = EmailmarketingCampaign::where('id', $id);
          $result = $query->get()->toArray();
          if (count($result) > 0) return $result[0];
          return array();
        }
      
      
        /* Get All campaigns */
        static function getAll($active=null) {
          if ($active){
            $result = EmailmarketingCampaign::where('active', 1)->get()->toArray();
          } else{
            $result = array();
          }
          if (count($result) > 0) return $result;
          return array();
        }
      
      
        /* Get campaign by Active */
        static function findByActive($active) {
          $query = EmailmarketingCampaign::where('active', '=', ($active?1:0));
          $result = $query->get()->toArray();
          if (count($result) > 0) return $result;
          return array();
        }
      
        /* Get campaign Id by Name */
        static function findIdByName($name) {
          $campaign = self::findByName($name);
          if (count($campaign)>0) return $campaign['id'];
          return 0;
        }
      
        /* Get campaign Name */
        static function getName($id) {
          $campaign = self::get($id);
          if (count($campaign)>0) return $campaign['name'];
          return '';
        }
      
        /* Get campaign Description */
        static function getDescription($id) {
          $campaign = self::get($id);
          if (count($campaign)>0) return $campaign['description'];
          return '';
        }
      
        /* Get campaign by Name */
        static function findByName($name) {
          $query = EmailmarketingCampaign::where('name', 'like', $name);
          $result = $query->get()->toArray();
          if (count($result) > 0) return $result;
          return array();
        }
      
        /* Get list of campaign Names */
        static function listCampaignNames() {
          $campaigns = EmailmarketingCampaign::where('active', '1');
          $result = $campaigns->get()->toArray();
          if (count($result)>0) return $result;
          return array();
        }
      
        /* Add Template to Campaign */
        static function saveTemplate($id, $temp_id, $addsUsers) {
          return EmailmarketingTemplates::addToCampaign($temp_id,$id, $addsUsers);
        }
      
        /* Get Templates from Campaign */
        static function getTemplates($id) {
          return EmailmarketingTemplates::includedInCampaign($id);
        }
      
        /* List Campaigns for System Settings */
        static function listCampaigns() {
          $query = EmailmarketingCampaign::
          selectRaw('ec.id as id, ec.name as campaign, count(ect.id) as templates, count(ectu.id) as cases, ec.active as active')
          ->from('email_marketing_campaign as ec')
          ->leftJoin('email_marketing_campaign_templates as ect', 'ect.campaign_id', 'ec.id')
          ->leftJoin('email_marketing_campaign_cases as ectu', 'ectu.campaign_id', 'ec.id')
          ->groupBy('ec.id, ec.name');
          $result = $query->get()->toArray();
          if (count($result) > 0) return $result;
          return array();
        }
      
      
        /* Set campaign active */
        static function active($id) {
          $result = false;
          if (self::exists($id)) {
            $result = EmailmarketingCampaign::find($id)->fill(['active'=>1]);
            $result->update();
          }
          return $result;
        }
        /* Set campaign inactive */
        static function inactive($id) {
          $result = false;
          if (self::exists($id)) {
            $result = EmailmarketingCampaign::find($id)->fill(['active'=>0]);
            $result->update();
          }
          return $result;
        }
      
        /* Set campaign inactive */
        static function softDelete($id) {
          return self::inactive($id);
        }
      
        /* Save New/Update if there's an id - returns id */
        static function save_($data)
        {
          $id = 0;
          if (isset($data['id']) && $data['id'] != '') { // It's update
            if (self::exists($data['id'])) {
              $result = EmailmarketingCampaign::find($data['id'])->fill($data);
              $result->update();
              $id = $data['id'];
            }
          } else { // It's insert
            if (self::existsByName($data['name'])==0) {
              list($id, $rows_affected) = EmailmarketingCampaign::create($data);
            }
          }
          return $id;
        }
      
      
      
        /* Remove Campaign, Email Templates associated and users associated with it */
        static function remove($id)
        {
          $result = true;
          DB::delete('email_marketing_campaign')->where('id', '=', $id)->execute();
          // Also remove all the templates
          $result &= EmailmarketingTemplates::removesTemplatesFromCampaign($id);
          // Also remove from all users campaigns
          $result &= EmailmarketingTemplates::removesTemplatesFromCaseCampaign($id);
          return $result;
        }
      
      
        static function validate($factory)
        {
          $val = validator::make($factory,[
            'name'=>'required'
          ]);
          return $val;
        }
}
