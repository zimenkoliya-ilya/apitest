<?php

namespace App\Models\emailmarketing;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailmarketingTemplates extends Model
{
    use HasFactory;
    protected $table = "template_emails";
    static function exists($id)
  {
    $result = self::get($id);
    if (count($result) > 0) return true;
    return false;
  }

  /* Template exists by Name */
  static function existsByName($name)
  {
    $template = self::getByName($name);
    if (count($template)>0) return true;
    return false;
  }

  /* Get template by Id */
  static function get($id)
  {
    $result = EmailmarketingTemplates::where('id', $id)->get()->toArray();
    if (count($result) > 0) return $result[0];
    return array();
  }

  /* Get Template by Name */
  static function getByName($name)
  {
    $result = EmailmarketingTemplates::where('name', 'like', $name)->get()->toArray();
    if (count($result) > 0) return $result[0];
    return array();
  }

  /* Get Template Name */
  static function getName($id)
  {
    $temp = self::find($id);
    if (count($temp)>0) return $temp->name;
    return '';
  }

  /* Get Template from */
  static function getFrom($id)
  {
    $temp = self::find($id);
    if (count($temp)>0) return $temp->from;
    return '';
  }

  /* Get Template Subject */
  static function getSubject($id)
  {
    $temp = self::find($id);
    if (count($temp)>0) return $temp->subject;
    return '';
  }

  /* Get Template CC */
  static function getCc($id)
  {
    $temp = self::find($id);
    if (count($temp)>0) return $temp->cc;
    return '';
  }

  /* Get Template Bcc */
  static function getBcc($id)
  {
    $temp = self::find($id);
    if (count($temp)>0) return $temp->bcc;
    return '';
  }

  /* Get Template To */
  static function getTo($id)
  {
    $temp = self::find($id);
    if (count($temp)>0) return $temp->to;
    return '';
  }

  /* Get Template Message */
  static function getMessage($id)
  {
    $temp = self::find($id);
    if (count($temp)>0) return $temp->message;
    return '';
  }

  /* List Templates for System Settings */
  static function listTemplates() {
    $query = EmailmarketingTemplates::select('et.id as id', 'et.name as name', 'et.from as emailfrom', 'et.to as emailto')
    ->from('template_emails as et');
    $result = $query->get()->toArray();
    if (count($result) > 0) return $result;
    return array();
  }
  
  /* Save New/Update if there's an id - returns id */
  static function save($data)
  {
    $id = 0;
    if (isset($data['id']) && $data['id'] != '') { // It's update
      if (self::exists($data['id'])) {
        $result = EmailmarketingTemplates::find($data['id'])->fill($data);
        $result->update();
        $result = self::updateTemplatecaseCampaign($data);
        $id = $data['id'];
      }
    } else { // It's insert
      if (self::existsByName($data['name'])==0) {
        list($id, $rows_affected) = EmailmarketingTemplates::create($data);
      }
    }
    return $id;
  }

  /* Remove Template */
  static function remove($id)
  {
    $remove =EmailmarketingTemplates::find($id)->delete();
    $result = ($remove>0);
    // Also remove from case Campaigns
    $remove = self::removeFromcases($id);
    // Also remove from all default Campaigns
    $remove = self::removeFromCampaigns($id);
    return $result;

  }


  /* For email_marketing_campaign_templates - on campaigns */

  /* Get Templates by Campaign Id */
  static function getByCampaignId($campaign_id)
  {
    $query = EmailmarketingCampaignTemplate::
    join('template_emails', 'template_emails.id', 'email_marketing_campaign_templates.template_id');
    $query->where('campaign_id', $campaign_id);
    $result = $query->get()->toArray();
    if (count($result) > 0) return $result;
    return array();
  }

  /* Get Templates by Campaign Name */
  static function getByCampaignName($campaign_name)
  {
    $query = EmailmarketingCampaignTemplate::
    join('email_marketing_campaign', 'email_marketing_campaign.id', 'email_marketing_campaign_templates.campaign_id');
    $query->join('template_emails', 'template_emails.id', 'email_marketing_campaign_templates.template_id');
    $query->where('email_marketing_campaign.name', $campaign_name);
    $result = $query->get()->toArray();
    if (count($result) > 0) return $result;
    return array();
  }

  /* Remove Template From All Campaigns*/
  static function removeFromCampaigns($id)
  {
    $result = EmailmarketingCampaignTemplate::delete('email_marketing_campaign_templates')->where('template_id', $id)->get();
    return ($result>0);
  }

  /* Remove Template From Campaign*/
  static function removeFromCampaign($id)
  {
    $result = EmailmarketingCampaignTemplate::find($id)->delete();
    return ($result>0);

  }


  /* Removes collection of templates belongs to a campaign */
  static function removesTemplatesFromCampaign($campaign_id) {
    $result = EmailMarketingCampaignTemplate::where('campaign_id', $campaign_id)->delete();
    return ($result>0);
  }


  /* Return collection of templates included in a campaign */
  static function includedInCampaign($campaign_id) {
    $query = EmailMarketingCampaignTemplate::select('email_marketing_campaign_templates.id','email_marketing_campaign_templates.template_id','template_emails.name','template_emails.from','template_emails.to', 'email_marketing_campaign_templates.timeframe');
    $query->join('template_emails', 'template_emails.id','email_marketing_campaign_templates.template_id');
    $query->orderBy('email_marketing_campaign_templates.order','asc');
    $result = $query->get()->toArray();
    if (count($result) > 0) return $result;
    return array();
  }



  /* Add Template to Campaign */
  static function addToCampaign($template_id, $campaign_id, $order=0, $timeframe = 10) {
    $result = true;
    if (EmailMarketingCampaign::exists($campaign_id) && EmailMarketingCampaign::exists($template_id)) {
      $template_data['id'] = null;
      $template_data['template_id'] = $template_id;
      $template_data['campaign_id'] = $campaign_id;
      $template_data['order'] = $order;
      $template_data['timeframe'] = $timeframe;
      list($insert_id, $rows_affected) = EmailMarketingCampaignTemplate::create($template_data);
      $result &= ($insert_id>0);
    }
    return $result;
  }

  /* Get campaign template by Id */
  static function getCampaignTemplate($id)
  {
    $result = EmailMarketingCampaignTemplate::find($id)->toArray();
    if (count($result) > 0) return $result[0];
    return array();
  }

  /* Save Campaign Template Field */
  static function saveCampaignTemplateField($id, $field, $value) {
    $result = EmailMarketingCampaignTemplate::find($id)->fill($field,$value);
    $result->update();
    return ($result>0);
  }


  /* For email_marketing_campaign_cases - on cases */

  /* Get template fields by campaign case templates */
  static function getTemplateFieldByTemplateCaseId($template_case_id,$field) {
    $query = EmailMarketingTemplate::select('template_emails.'.$field);
    $query->join('email_marketing_campaign_cases', 'template_emails.id', 'email_marketing_campaign_cases.template_id');
    $query->where('email_marketing_campaign_cases.id', $template_case_id);
    $result = $query->get()->toArray();
    if (count($result) > 0) return $result[0];
    return array();
  }

  /* Get all campaign emails from a case campaign */
  static function findByCaseId( $case_id )
  {
    $query = EmailMarketingTemplate::select(
                'email_marketing_campaign_cases.id as id',
                'template_emails.id as template_id',
                'template_emails.name as template_name',
                'email_marketing_campaign.id as campaign_id',
                'email_marketing_campaign.name as campaign_name',
                'email_marketing_campaign_cases.send_date as schedule_send',
                'email_marketing_campaign_cases.sent_date as date_sent')
                ->from('email_marketing_campaign_cases');
    $query->join('template_emails')->on('template_emails.id','=','email_marketing_campaign_cases.template_id');
    $query->join('email_marketing_campaign')->on('email_marketing_campaign.id','=','email_marketing_campaign_cases.campaign_id');
    $query->where('case_id', '=', $case_id);
    $result = $query->execute()->as_array();
    if (count($result) > 0) return $result;
    return array();
  }


  /* Check if a given case campaign exists */
  static function existsCaseCampaign( $campaign_id, $case_id )
  {
    $query = EmailmarketingCampaigncases::where('case_id', $case_id);
    $query->where('campaign_id', $campaign_id);
    $result = $query->get()->toArray();
    if (count($result) > 0) return $result;
    return array();
  }

  /* Check if a id of a template case campaign exists */
  static function existsTemplateCaseCampaign( $id, $case_id, $campaign_id, $template_id )
  {
    $query = EmailmarketingCampaigncases::where('id', $id);
    $query->where('case_id', $case_id);
    $query->where('template_id', $template_id);
    $query->where('campaign_id', $campaign_id);
    $result = $query->get()->toArray();
    if (count($result) > 0) return $result;
    return array();
  }

  /* Collect all campaign templates */
  static function getCampaignTemplatesByCampaignId($campaign_id)
  {
    $query = EmailmarketingCampaigntemplate::where('campaign_id', $campaign_id);
    $result = $query->get()->toArray();
    if (count($result) > 0) return $result;
    return array();
  }

    /* Add a new template to a case campaign */
  static function addCampaignToCase($campaign_id, $case_id)
  {
    $result = false;
    if (ModelEmailMarketingCampaign::exists($campaign_id)) {
      $result = true;
      $templates = self::getCampaignTemplatesByCampaignId($campaign_id);
        /* Check if campaign is already assigned to case file */
        if(self::existsCaseCampaign($campaign_id, $case_id)){
            return false;
        }

      foreach($templates as $template) {
        unset($template['id']);
        $template['case_id'] = $case_id;
        $template['send_date'] = date('Y-m-d H:i:s', strtotime("+".$template['timeframe']." days",strtotime(date('Y-m-d', time()))));
        unset($template['timeframe']);
       $template['active'] = 1;
        unset($template['order']);
        list($insert_id, $rows_affected) = EmailmarketingCampaigncases::create($template);
        if ($insert_id>0) {
          $result &= true;
        } else {
          $result &= false;
        }
      }
    }
    return $result;
  }


  /* Date */

  static private function _dateAdd($datets, $days) {
    $date = new DateTime();
    $wdate = $date->setTimestamp($datets);
    $wdate->modify("+ ".$days." days");
    return $wdate->getTimestamp();
  }


  /* Update a template to a case campaign */
  static function updateTemplateCaseCampaign($data)
  {
    $result = 0;
    if (self::existsTemplatecaseCampaign( $data['id'], $data['case_id'], $data['campaign_id'], $data['template_id'] )) {
      $result = EmailmarketingCampaigncases::find($data['id'])->fill($data);
      $result->update();
    }
    return $result>0;
  }

  /* Remove Template From cases*/
  static function removeFromCases($id)
  {
    $result = EmailmarketingCampaigncases::where('template_id', $id)->delete();
    return ($result>0);
  }

  /* Remove Case Template From cases*/
  static function removeCaseTemplateFromCases($id)
  {
    $result = EmailmarketingCampaigncases::find($id)->delete();
    return ($result>0);
  }


  /* Remove Template From case Campaign*/
  static function removeFromCaseCampaigns($id, $case_id)
  {
     $result =  EmailmarketingCampaigncases::
      where('template_id',  $id)
      ->where('case_id',  $case_id)->delete();
    return ($result>0);
  }

  /* Return collection of templates belongs to a case */
  static function belongsToCase($case_id)
  {
    $query = EmailmarketingCampaigncases::
      where('case_id', '=', $case_id);
    $result = $query->get()->toArray();
    if (count($result) > 0) return $result;
    return array();
  }

  /* Removes collection of templates belongs to a case */
  static function removesTemplatesFromCase($case_id) {
    $result = EmailmarketingCampaigncases::where('case_id', $case_id)->delete();
    return ($result>0);
  }

  /* Removes collection of templates belongs to a case campaign */
  static function removesTemplatesFromCaseCampaign($campaign_id, $case_id = null) {
    $query = EmailMarketingCampaignTemplate::where('campaign_id', $campaign_id);
    if ($case_id != null) $query->where('case_id', $case_id);
    $result = $query->get();
    return ($result>0);
  }




  static function validate($factory)
  {
    $val = \Validation::forge($factory);
    $val->add_field('name', 'Name', 'required|trim');
    $val->add_field('subject', 'Subject', 'required|trim');
    $val->add_field('message', 'Message', 'required|trim');
    $val->add_field('from', 'Email From', 'required|trim');
    $val->add_field('to', 'Email To', 'required|trim');

    return $val;
  }

}
