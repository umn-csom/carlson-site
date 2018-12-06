<?php

namespace Drupal\csom_datalayer\EventSubscriber;


class UpdateAnalyticsTable {

    private $slate_session_data;
    private $piwik_session_data;
    private $piwik_update_finished = FALSE;
    private $slate_update_finished = FALSE;
    private $database;


    //Constructor..
    public function __construct() {}

    function AsyncPHPRequest($user, $password, $url) {

        $curl = curl_init();
		
		//A given cURL operation should only take
		//720 seconds max. 12 Minutes
		curl_setopt($curl, CURLOPT_CONNECTTIMEOUT ,0);
		curl_setopt($curl, CURLOPT_TIMEOUT, 720);
		set_time_limit(0);

		//curl_setopt ($curl, CURLOPT_SSLVERSION, 5);
        // Optional Authentication:
        curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);

        curl_setopt($curl, CURLOPT_USERPWD, $user . ':' . $password);

        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

        //create the multiple cURL handle
        $mh = curl_multi_init();

        //add the handle
        curl_multi_add_handle($mh, $curl);

        $active = null;
		//ksm($url);
		
		
        //execute the handle
        do {
            $mrc = curl_multi_exec($mh, $active);
        } while ($mrc == CURLM_CALL_MULTI_PERFORM);

        while ($active && $mrc == CURLM_OK) {
            //if (curl_multi_select($mh) != -1) {
				if (curl_multi_select($mh) == -1) {
					usleep(1);
				}
				
                do {
                    $mrc = curl_multi_exec($mh, $active);
					// Check for errors
					//ksm($mrc);
					if($mrc > 0) {
						// Display error message
						// \Drupal::logger('csom_datalayer')->error( "ERROR!\n " . $url . " " . curl_multi_strerror($mrc));
					}
                } while ($mrc == CURLM_CALL_MULTI_PERFORM);
            //}
        }
		

        // Retrieve the JSON data
        $data = curl_multi_getcontent($curl);

        //close the handles
        curl_multi_remove_handle($mh, $curl);
        curl_multi_close($mh);
		
		// \Drupal::logger('csom_datalayer')->notice('datalayer content pulled from: ' . $url);

        return $data;

    }

    public function PullSlateData($user, $password, $url) {

        if ($this->slate_update_finished) {
            return;
        }
		
		//$curl = curl_init();
		
        $this->slate_session_data = $this->AsyncPHPRequest($user, $password, $url);

		//curl_close($curl);

        $this->slate_update_finished = TRUE;

    }


    public function PullPiwikData($user, $password, $url) {

        if ($this->piwik_update_finished) {
            return;
        }

        //$curl = curl_init();

        $this->piwik_session_data = $this->AsyncPHPRequest($user, $password, $url);

        //curl_close($curl);

        $this->piwik_update_finished = TRUE;

    }

    public function PerformSlateTableUpdate() {
        // TODO:: replace database string variable that indicates when the last update was run
        //variable_set("LastSlateTableUpdate", date('l jS \of F Y h:i:s A'));

        $someObject = json_decode($this->slate_session_data);
        $program_status_entries = $someObject->statuses[0]->people_program_statuses;

        foreach ($program_status_entries as $key => $value) {
            //initailize defaults
            $slate_id = '';
            $program = '';
            $current_status = '0';
            $email = '';
            $GA_ClientID = '';
            $Piwik_Visitor_ID = '';
            $Inquiry = '';
            $Inquiry_Date = NULL;
            $Inquiry_Activities = '';
            $Applicant = '0';
            $App_Date = NULL;
            $App_Term = '';
            $App_Status = '';
            $Inactive = 0;
            $Inactive_Date = NULL;

            //we need a unique 'slate_id' and 'program'
            if (!isset($value->slate_id) || !isset($value->program)) {
                continue;
            }

            //check for NULL values
            if (isset($value->slate_id)) {
                $slate_id = $value->slate_id;
            }
            if (isset($value->program)) {
                $program = $value->program;
            }
            if (isset($value->current_status)) {
                $current_status = $value->current_status;
            }
            if (isset($value->email)) {
                $email = $value->email;
            }
            if (isset($value->GA_ClientID)) {
                $GA_ClientID = $value->GA_ClientID;
            }
            if (isset($value->Piwik_Visitor_ID)) {
                $Piwik_Visitor_ID = $value->Piwik_Visitor_ID;
            }
            if (isset($value->Inquiry)) {
                $Inquiry = $value->Inquiry;
            }
            if (isset($value->Inquiry_Date)) {
                $Inquiry_Date = $value->Inquiry_Date;
            }
            if (isset($value->Inquiry_Activities)) {
                $Inquiry_Activities = $value->Inquiry_Activities;
            }
            if (isset($value->Applicant)) {
                $Applicant = $value->Applicant;
            }
            if (isset($value->App_Date)) {
                $App_Date = $value->App_Date;
            }
            if (isset($value->App_Term)) {
                $App_Term = $value->App_Term;
            }
            if (isset($value->App_Status)) {
                $App_Status = $value->App_Status;
            }
            if (isset($value->Inactive)) {
                $Inactive = $value->Inactive;
            }
            if (isset($value->InactiveDate)) {
                $Inactive_Date = $value->InactiveDate;
            }
            
			try {
				$connection = \Drupal::database();
				//Here we want to check every slate_id and program pair currently in the database
				//If a particular pair exists, then update the remaining values
				//else insert a new entry
				$connection->merge('csom_slate_status')
					->key(array(
						'SlateID' => $slate_id,
						'Program' => $program,

					))
					->fields(array(
						'CurrentStatus' => $current_status,
						'Email' => $email,
						'GAClientID' => $GA_ClientID,
						'PiwikVisitorID' => $Piwik_Visitor_ID,
						'Inquiry' => $Inquiry,
						'InquiryDate' => $Inquiry_Date,
						'InquiryActivities' => $Inquiry_Activities,
						'Applicant' => $Applicant,
						'AppDate' => $App_Date,
						'AppTerm' => $App_Term,
						'AppStatus' => $App_Status,
						'Inactive' => $Inactive,
						'InactiveDate' => $Inactive_Date,
					))
					->execute();
			}  catch (Exception $e) {
				// \Drupal::logger('csom_datalayer')->error('Slate load issue - Caught exception: ' .   $e->getMessage());
			}
        }

        //Log('csom_slate_status', time());
    }

    //TODO: Add proper error handling here
    public function PerformPiwikTableUpdate() {
        // TODO:: replace database string variable that indicates when the last update was run
        //variable_set("LastPiwikTableUpdate", date('l jS \of F Y h:i:s A'));
        $PiwikEntries = json_decode($this->piwik_session_data);

        foreach ($PiwikEntries as $key => $value) {

            //if (!isset($key)) throw new Exception('data not there');
            //Initailize defaults
            $SubscriberID = '';
            $PiwikVisitorID = '';
            $VisitorType = '';
            $Browser = '';
            $DeviceType = '';
            $Resolution = '';
            $TotalVisits = 0;
            $AvgActionsPerVisit = 0;
            $AvgVisitDuration = NULL;
            $DaysSinceLastVisit = 0;
            $FirstActionDate = NULL;
            $LastActionDate = NULL;
            $LastLocation = '';
            $LastReferrerUrl = '';
            $LastCampaignSource = '';
            $LastCampaignName = '';
            $LastCampaignMedium = '';
            $LastCampaignContent = '';

            //check for NULL values
            if (isset($value->subscriberid)) {
                $SubscriberID = $value->subscriberid;
            }
            //we need a unique 'Piwik ID'
            if (!isset($value->visitorid)) {
                continue;
            } else {
                $PiwikVisitorID = $value->visitorid;
            }
            if (isset($value->visitor_type)) {
                $VisitorType = $value->visitor_type;
            }
            if (isset($value->browser)) {
                $Browser = $value->browser;
            }
            if (isset($value->devicetype)) {
                $DeviceType = $value->devicetype;
            }
            if (isset($value->resolution)) {
                $Resolution = $value->resolution;
            }
            if (isset($value->total_visits)) {
                $TotalVisits = $value->total_visits;
            }
            if (isset($value->avg_actions_per_visit)) {
                $AvgActionsPerVisit = $value->avg_actions_per_visit;
            }
            if (isset($value->avg_visit_duration)) {
                $AvgVisitDuration = $value->avg_visit_duration;
            }
            if (isset($value->days_since_first_visit)) {
                $DaysSinceLastVisit = $value->days_since_first_visit;
            }
            if (isset($value->firstactiondate)) {
                $FirstActionDate = date('Y-m-d H:i:s', strtotime($value->firstactiondate));
            }
            if (isset($value->lastactiondate)) {
                $LastActionDate = date('Y-m-d H:i:s', strtotime($value->lastactiondate));
            }
            if (isset($value->last_location)) {
                $LastLocation = $value->last_location;
            }
            if (isset($value->last_referrerurl)) {
                $LastReferrerUrl = substr($value->last_referrerurl, 0, 2000);
            }
            if (isset($value->last_campaign_source)) {
                $LastCampaignSource = $value->last_campaign_source;
            }
            if (isset($value->last_campaign_name)) {
                $LastCampaignName = $value->last_campaign_name;
            }
            if (isset($value->last_campaign_medium)) {
                $LastCampaignMedium = $value->last_campaign_medium;
            }
            if (isset($value->last_campaign_content)) {
                $LastCampaignContent = $value->last_campaign_content;
            }

            try {
                $connection = \Drupal::database();

                $connection->merge('csom_piwik_status')
                    ->key([
                        'PiwikVisitorID' => $PiwikVisitorID,

                    ])
                    ->fields([
                        'SubscriberID' => $SubscriberID,
                        'VisitorType' => $VisitorType,
                        'Browser' => $Browser,
                        'DeviceType' => $DeviceType,
                        'Resolution' => $Resolution,
                        'TotalVisits' => $TotalVisits,
                        'AvgActionsPerVisit' => $AvgActionsPerVisit,
                        'AvgVisitDuration' => $AvgVisitDuration,
                        'DaysSinceLastVisit' => $DaysSinceLastVisit,
                        'FirstActionDate' => $FirstActionDate,
                        'LastActionDate' => $LastActionDate,
                        'LastLocation' => $LastLocation,
                        'LastReferrerUrl' => $LastReferrerUrl,
                        'LastCampaignSource' => $LastCampaignSource,
                        'LastCampaignName' => $LastCampaignName,
                        'LastCampaignMedium' => $LastCampaignMedium,
                        'LastCampaignContent' => $LastCampaignContent,
                    ])
                    ->execute();
            }  catch (Exception $e) {
                // \Drupal::logger('csom_datalayer')->error('Piwik load issue - Caught exception: ' .   $e->getMessage());
            }
        }


    }


}
?>
