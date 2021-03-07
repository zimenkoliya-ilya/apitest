<?php

namespace App\Models\emailmarketing;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailmarketingCampaign extends Model
{
    use HasFactory;
    protected $table = "email_marketing_campaign";
    private static $dataTables = array(
        'email_marketing_campaign' => 'CREATE TABLE `email_marketing_campaign` (
                                                    `id` int(11) NOT NULL AUTO_INCREMENT,
                                                    `name` varchar(128),
                                                    `description` text,
                                                    `active` tinyint(1) NOT NULL,
                                                    PRIMARY KEY (`id`)
                                                  )',
        'email_marketing_campaign_templates' => 'CREATE TABLE `email_marketing_campaign_templates` (
                                                    `id` int(11) NOT NULL AUTO_INCREMENT,
                                                    `campaign_id` int(11) NOT NULL,
                                                    `template_id` int(11) NOT NULL,
                                                    `order` int(11) NOT NULL,
                                                    `timeframe` int(11) NOT NULL,
                                                    PRIMARY KEY (`id`)
                                                  )',
        'email_marketing_campaign_cases' => 'CREATE TABLE `email_marketing_campaign_cases` (
                                                    `id` int(11) NOT NULL AUTO_INCREMENT,
                                                    `campaign_id` int(11) NOT NULL,
                                                    `case_id` int(11) NOT NULL,
                                                    `template_id` int(11) NOT NULL,
                                                    `send_date` datetime NOT NULL,
                                                    `active` tinyint(1) NOT NULL,
                                                    `sent_date` datetime,
                                                    PRIMARY KEY (`id`)
                                                  )',
        );
      
        /** Initializing method that will insert the new tables if they don't exist
         *  and collect all starting permissions and roles */
        static function bootstrap()
        {
          /* Flow:
              1. Checks if the tables exist - if not creates them
              2. Adds the current methods to current starter roles
          */
          $_SESSION['log'] = "";
          $rs_buildModel = self::_buildDataModel(self::$dataTables);
          $rs_updatePermissions = true; //Model_System_Access::updatePermissionsFrom('emailmarketing');
          return ($rs_buildModel && $rs_updatePermissions);
        }
      
        // Initiate the data model using the queries set on top as private property
        // TODO: This should probably be converted into a public generic method
        private static function _buildDataModel($table_definition)
        {
          $result = true;
          foreach (array_keys($table_definition) as $table) {
            $rs = (DB::query("SHOW TABLES LIKE '" . $table . "'")->execute()) > 0;
            if (!$rs) {
              $result &= \DB::query("DROP TABLE IF EXISTS `" . $table . "`;")->execute();
              $result &= \DB::query($table_definition[$table])->execute();
              $result &= (DB::query("SHOW TABLES LIKE '" . $table . "'")->execute()) > 0;
            } else {
              $result &=true;
            }
          }
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
          $query = \DB::select()->from('email_marketing_campaign');
          $query->where('id', '=', $id);
          $result = $query->execute()->as_array();
          if (count($result) > 0) return $result[0];
          return array();
        }
      
      
        /* Get All campaigns */
        static function getAll($active=null) {
          $query = \DB::select()->from('email_marketing_campaign');
          if ($active) $query->where('active', '=', 1);
          $result = $query->execute()->as_array();
          if (count($result) > 0) return $result;
          return array();
        }
      
      
        /* Get campaign by Active */
        static function findByActive($active) {
          $query = \DB::select()->from('email_marketing_campaign');
          $query->where('active', '=', ($active?1:0));
          $result = $query->execute()->as_array();
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
          $query = \DB::select()->from('email_marketing_campaign');
          $query->where('name', 'like', $name);
          $result = $query->execute()->as_array();
          if (count($result) > 0) return $result;
          return array();
        }
      
        /* Get list of campaign Names */
        static function listCampaignNames() {
          $campaigns = \DB::select('id','name')->from('email_marketing_campaign');
          $campaigns->where('active', '=', '1');
          $result = $campaigns->execute()->as_array();
          if (count($result)>0) return $result;
          return array();
        }
      
        /* Add Template to Campaign */
        static function saveTemplate($id, $temp_id, $addsUsers) {
          return Model_Emailmarketing_Templates::addToCampaign($temp_id,$id, $addsUsers);
        }
      
        /* Get Templates from Campaign */
        static function getTemplates($id) {
          return Model_Emailmarketing_Templates::includedInCampaign($id);
        }
      
        /* List Campaigns for System Settings */
        static function listCampaigns() {
          $query = \DB::query('select ec.id as id, ec.name as campaign, count(ect.id) as templates, count(ectu.id) as cases, ec.active as active from email_marketing_campaign as ec left join email_marketing_campaign_templates as ect on ect.campaign_id=ec.id left join email_marketing_campaign_cases as ectu on ectu.campaign_id = ec.id group by ec.id, ec.name');
          $result = $query->execute()->as_array();
          if (count($result) > 0) return $result;
          return array();
        }
      
      
        /* Set campaign active */
        static function active($id) {
          $result = false;
          if (self::exists($id)) {
            $result = \DB::update('email_marketing_campaign')
              ->value("active", 1)
              ->where('id', '=', $id)
              ->execute();
          }
          return $result;
        }
        /* Set campaign inactive */
        static function inactive($id) {
          $result = false;
          if (self::exists($id)) {
            $result = \DB::update('email_marketing_campaign')
              ->value("active", 0)
              ->where('id', '=', $id)
              ->execute();
          }
          return $result;
        }
      
        /* Set campaign inactive */
        static function softDelete($id) {
          return self::inactive($id);
        }
      
        /* Save New/Update if there's an id - returns id */
        static function save($data)
        {
          $id = 0;
          if (isset($data['id']) && $data['id'] != '') { // It's update
            if (self::exists($data['id'])) {
              $result = \DB::update('email_marketing_campaign')->set($data)->execute();
              $id = $data['id'];
            }
          } else { // It's insert
            if (self::existsByName($data['name'])==0) {
              list($id, $rows_affected) = \DB::insert('email_marketing_campaign')->set($data)->execute();
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
          $result &= Model_Emailmarketing_Templates::removesTemplatesFromCampaign($id);
          // Also remove from all users campaigns
          $result &= Model_Emailmarketing_Templates::removesTemplatesFromCaseCampaign($id);
          return $result;
        }
      
      
        static function validate($factory)
        {
          $val = \Validation::forge($factory);
          $val->add('name', 'name')
            ->add_rule('required');
          //  ->add_rule('match_pattern', '^[a-zA-Z ]+$');
          return $val;
        }
}
