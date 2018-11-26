<?php

/**
* Class CSOMDataLayerInit.
*/

namespace Drupal\CSOM_DataLayer\EventSubscriber;

//Include needed php classes
use Drupal\CSOM_DataLayer\PIWIKID;
use \UpdateAnalyticsTable;

//Service classes
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

//Defining code to be run every page load
class CSOM_DataLayerInit implements EventSubscriberInterface {
    
    //Member variables
    private $clientId = '';
    private $update_databases = NULL;
    private $visitorId = '';
    private $SubscriberID = '';
    private $email = '';

    private $visitor_status = array();
    private $program_status = array();

    public function __construct() {

    }

    public static function getSubscribedEvents() {
        $events[KernelEvents::REQUEST][] = array('initializeMyModule');
        return $events;
    }


    /**
     * CSOM_Datalayer
     *
     * @param \Symfony\Component\HttpKernel\Event\GetResponseEvent $event
     *   The event to process.
     */

    public function initializeMyModule(GetResponseEvent $event)
    {
		
        // pull identifying information
        self::update_database_param();
        self::extract_GA_id();
		self::extract_PIWIK_id();
        self::get_subscriberID();
        self::get_email();
		
		
		
        // Query the tables
        $this->program_status = self::get_program_status();
        $this->visitor_status = self::get_visitor_status();

        // load datalayer with client data
        self::load_program_status_into_datalayer();
        self::load_visitor_status_into_datalayer();

        // Update site analytics tables based on param
        if  (isset($_GET['super_secret_update_param'])) {

            $UpdateObject = \Drupal::service('CSOM_DataLayer.update_analytics_tables');

            //make the database connections
	    \Drupal::logger('csom_datalayer')->notice('datalayer start content pull for: Slate' );
            $UpdateObject->PullSlateData('E4Pyigr-q&Cx','-~7FfCeB/4m$','https://choose.umn.edu/manage/query/run?id=bbd83abb-a2bb-4d6d-91b4-180778989d3e&h=85908d7e-b7c6-79d0-db0c-a79f7d83bf7e&cmd=service&output=json');
            \Drupal::logger('csom_datalayer')->notice('datalayer start content pull for: Piwik/Matomo' );
	    $UpdateObject->PullPiwikData('username@carlsonschoolofmanagement-CTL215', 'c109d736-bf4e-4e4a-83f0-d877f3f4ca00', 'http://134.84.122.217:9090/ws/simple/getPiwikDWRecords');
			
            //perform the updates
            $UpdateObject->PerformSlateTableUpdate();
            $UpdateObject->PerformPiwikTableUpdate();

            //Set this object for garbage collection as it takes up a lot of space
            unset($UpdateObject);
        }

    }


    //Extract ga_id from cookie
    function extract_GA_id() {
        $ga_cookie = "";
        
        if (isset($_COOKIE["_ga"])) {
            $ga_cookie = $_COOKIE["_ga"];
        } else {
            $ga_cookie = "";
        }

        //Finds and returns the google analytics client id from the _ga cookie
        if ($ga_cookie === "") {
            return;
        } 
        //ksm($ga_cookie);
        // Parse cookie _ga='<something>.<something>.<clientid>.<timestamp>';
        $this->clientId = explode(".", $_COOKIE["_ga"])[2];
    }


    //Extract VisitorID from cookie
    function extract_PIWIK_id() {
        foreach ($_COOKIE as $key=>$val) {
			//ksm($key);
            //Grab piwik analytics visitor id
            if (strpos($key, "pk_id")) {

                //$current_user = new PIWIKID($_COOKIE, $key);

                //$this->visitorId = $current_user->GetVisitorID();
	    	$this->visitorId = explode('.', $_COOKIE["$key"])[0];
				//ksm("visitorId : " . $this->visitorId);
            }

        }
    }

    //Get the current path
    function c_path() {
        return $_GET['q'];
    }

    // Extract the subscriberID from the URL
    function get_subscriberID() {
        if (isset($_GET['subscriberid'])) {
            $this->SubscriberID = $_GET['subscriberid'];
        }
		//ksm("SubscriberID : " . $this->SubscriberID);		
    }

    //Extract email from the URL
    function get_email() {
        if (isset($_GET['email'])) {
            $this->email = $_GET['email'];
        } 
    }

    function update_database_param() {
        if (isset($_GET['super_secret_update_param'])) {
            $this->update_database = $_GET['super_secret_update_param'];
        } 
    }


    function get_alias() {

        $url = drupal_lookup_path('alias', c_path());

        return $url;
    }



    function load_program_status_into_datalayer()
    {
        
        if (empty($this->program_status)) { return array(); }

        $peoplePrograms = array();
        $JSONDATA = array();

        foreach ($this->program_status as $record) {

            $Program = $record->Program;
            $CurrentStatus = $record->CurrentStatus;
            $Inquiry = $record->Inquiry;
            $InquiryDate = $record->InquiryDate;
            $InquiryActivities = $record->InquiryActivities;
            $Applicant = $record->Applicant;
            $AppDate = $record->AppDate;
            $AppTerm = $record->AppTerm;
            $AppStatus = $record->AppStatus;
            $Inactive = $record->Inactive;
            $Inactive_Date = $record->InactiveDate;

            $peoplePrograms[] = [
                'program' => $Program,
                'CurrentStatus' => $CurrentStatus,
                'Inquiry' => $Inquiry,
                'InquiryDate' => $InquiryDate,
                'InquiryActivities' => $InquiryActivities,
                'Applicant' => $Applicant,
                'AppDate' => $AppDate,
                'AppTerm' => $AppTerm,
                'AppStatus' => $AppStatus,
                'Inactive' => $Inactive,
                'Inactive_Date' => $Inactive_Date,
            ];

            $JSONDATA = json_encode($peoplePrograms);
        }

        datalayer_add(array("People_Program_Status" => $JSONDATA));
    }


    function load_visitor_status_into_datalayer()
    {
        if (empty($this->visitor_status)) { return array(); }
        $visitorStatus = array();

        $JSONDATA = array();

        foreach ($this->visitor_status as $record) {

            $SubscriberID = $record->SubscriberID;
            $PiwikVisitorID = $record->PiwikVisitorID;
            $VisitorType = $record->VisitorType;
            $Browser = $record->Browser;
            $DeviceType = $record->DeviceType;
            $Resolution = $record->Resolution;
            $TotalVisits = $record->TotalVisits;
            $AvgActionsPerVisit = $record->AvgActionsPerVisit;
            $AvgVisitDuration = $record->AvgVisitDuration;
            $DaysSinceLastVisit = $record->DaysSinceLastVisit;
            $FirstActionDate = $record->FirstActionDate;
            $LastActionDate = $record->LastActionDate;
            $LastLocation = $record->LastLocation;
            $LastReferrerUrl = $record->LastReferrerUrl;
            $LastCampaignSource = $record->LastCampaignSource;
            $LastCampaignName = $record->LastCampaignName;
            $LastCampaignMedium = $record->LastCampaignMedium;


            $visitorStatus[] = [
                'SubscriberID' => $SubscriberID,
                'PiwikVisitorID' => $PiwikVisitorID,
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
            ];

            $JSONDATA = json_encode($visitorStatus);
        }

        datalayer_add(array("Visitor_Status" => $JSONDATA));
    }

    function get_program_status()
    {

        $connection = \Drupal::database();

        if (empty($this->email)) {
            
            $sql = "SELECT Program, CurrentStatus, Email, GAClientID, PiwikVisitorID, Inquiry, InquiryDate, InquiryActivities, Applicant, AppDate, AppTerm, AppStatus, Inactive, InactiveDate
                FROM {csom_slate_status} where GAClientID =:clientID or PiwikVisitorID =:visitorId";
				//ksm("clientID : " . $this->clientId);
				//ksm("pps : " . count($connection->query($sql, [':clientID' => $this->clientId, ':visitorId' => $this->visitorId])->fetchAll()));
            return $connection->query($sql, [':clientID' => $this->clientId, ':visitorId' => $this->visitorId])->fetchAll();
        } else {
			
            $sql = "SELECT Program, CurrentStatus, Email, GAClientID, PiwikVisitorID, Inquiry, InquiryDate, InquiryActivities, Applicant, AppDate, AppTerm, AppStatus, Inactive, InactiveDate
                FROM {csom_slate_status} where Email =:email";
				//ksm("pps : " . count($connection->query($sql, [':email' => $this->email])->fetchAll()));
            return $connection->query($sql, [':email' => $this->email])->fetchAll();
        }
    }

    function get_visitor_status()
    {
        $connection = \Drupal::database();
        if (!empty($this->SubscriberID) || !empty($this->visitorId)) {
			//ksm( ":subscriberid " . $this->SubscriberID .  " :visitorId " . $this->visitorId );
            $sql = "SELECT SubscriberID, PiwikVisitorID, VisitorType, Browser, DeviceType, Resolution, TotalVisits, AvgActionsPerVisit, AvgVisitDuration, DaysSinceLastVisit, FirstActionDate, LastActionDate, LastLocation, LastReferrerUrl, LastCampaignSource, LastCampaignName, LastCampaignMedium
                    FROM {csom_piwik_status} where (SubscriberID != '' AND SubscriberID =:subscriberid ) OR (PiwikVisitorID != '' AND	PiwikVisitorID = :visitorId)";
					//ksm("visits : " . count($connection->query($sql, [':subscriberid' => $this->SubscriberID, ':visitorId' => $this->visitorId])->fetchAll()));
            return $connection->query($sql, [':subscriberid' => $this->SubscriberID, ':visitorId' => $this->visitorId])->fetchAll();

        } elseif ($this->visitorId != '') {

            if (!empty($this->SubscriberID)) {
                $sql = "SELECT SubscriberID, PiwikVisitorID, VisitorType, Browser, DeviceType, Resolution, TotalVisits, AvgActionsPerVisit, AvgVisitDuration, DaysSinceLastVisit, FirstActionDate, LastActionDate, LastLocation, LastReferrerUrl, LastCampaignSource, LastCampaignName, LastCampaignMedium
                    FROM {csom_piwik_status} where PiwikVisitorID =:visitorId or SubscriberID  =:subscriberid";
                return $connection->query($sql, [':visitorId' => $this->visitorId, ':subscriberid' => $this->SubscriberID])->fetchAll();
            } else {
                $sql = "SELECT SubscriberID, PiwikVisitorID, VisitorType, Browser, DeviceType, Resolution, TotalVisits, AvgActionsPerVisit, AvgVisitDuration, DaysSinceLastVisit, FirstActionDate, LastActionDate, LastLocation, LastReferrerUrl, LastCampaignSource, LastCampaignName, LastCampaignMedium
                    FROM {csom_piwik_status} where PiwikVisitorID =:visitorId";
                return $connection->query($sql, [':visitorId' => $this->visitorId])->fetchAll();
            }

        } else {
            return null;
        }


    }





}
?>
