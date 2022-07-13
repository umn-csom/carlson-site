<?php

/**
 * Class CSOM_DataLayerInit.
 */

namespace Drupal\csom_datalayer\EventSubscriber;

//Include needed php classes
//use Drupal\csom_datalayer\PIWIKID;
use \UpdateAnalyticsTable;

//Service classes
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

//Defining code to be run every page load
class CSOM_DataLayerInit implements EventSubscriberInterface
{
    
    //Member variables
    private $clientId = '';
    private $update_databases = null;
    private $visitorId = '';
    private $SubscriberID = '';
    private $email = '';

    private $visitor_status = array();
    private $program_status = array();

    public function __construct()
    {

    }

    public static function getSubscribedEvents()
    {
        $events[KernelEvents::REQUEST][] = array('initializeMyModule');
        return $events;
    }


    /**
     * csom_datalayer
     *
     * @param \Symfony\Component\HttpKernel\Event\GetResponseEvent $event
     *   The event to process.
     */

    public function initializeMyModule(GetResponseEvent $event)
    {

        $route_name = \Drupal::routeMatch()->getRouteName();
        if ($route_name != 'view.frontpage.page_1') {
            // \Drupal::logger('csom_datalayer')->notice($route_name);
            
            // pull identifying information
            self::update_database_param();
            self::extract_GA_id();
            self::extract_PIWIK_id();
            self::get_subscriberID();
            self::get_email();

            // Query the tables
            $this->program_status = self::get_program_status();
            $this->visitor_status = self::get_visitor_status();

            // \Drupal::logger('csom_datalayer')->notice(count($this->visitor_status));

            // load datalayer with client data
            self::load_program_status_into_datalayer();
            self::load_visitor_status_into_datalayer();

            




            // Update site analytics tables based on param
            if  (isset($_GET['super_secret_update_param'])) {
                $config = \Drupal::config('csom_datalayer.settings');
                $status_url = $config->get('status_url');
                $status_user = $config->get('status_user');
                $status_pass = $config->get('status_pass');
                $analytics_url = $config->get('analytics_url');
                $analytics_user = $config->get('analytics_user');
                $analytics_pass = $config->get('analytics_pass');
                $UpdateObject = \Drupal::service('csom_datalayer.update_analytics_tables');

                //make the database connections
                // \Drupal::logger('csom_datalayer')->notice('datalayer start content pull for: Slate' );
                $UpdateObject->PullSlateData($status_user, $status_pass, $status_url);
                // \Drupal::logger('csom_datalayer')->notice('datalayer start content pull for: Piwik/Matomo' );
                $UpdateObject->PullPiwikData($analytics_user, $analytics_pass, $analytics_url);

                //perform the updates
                $UpdateObject->PerformSlateTableUpdate();
                $UpdateObject->PerformPiwikTableUpdate();

                //Set this object for garbage collection as it takes up a lot of space
                unset($UpdateObject);
            }

            // Update site Status analytics table based on param
            if  (isset($_GET['update_slate_status_data'])) {
                $config = \Drupal::config('csom_datalayer.settings');
                $status_url = $config->get('status_url');
                $status_user = $config->get('status_user');
                $status_pass = $config->get('status_pass');
                $UpdateObject = \Drupal::service('csom_datalayer.update_analytics_tables');

                //make the database connections
                // \Drupal::logger('csom_datalayer')->notice('datalayer start content pull for: Slate' );
                $UpdateObject->PullSlateData($status_user, $status_pass, $status_url);

                //perform the updates
                $UpdateObject->PerformSlateTableUpdate();

                //Set this object for garbage collection as it takes up a lot of space
                unset($UpdateObject);
            }

            // Update site Visitor analytics table based on param
            if  (isset($_GET['update_visitor_status_data'])) {
                $config = \Drupal::config('csom_datalayer.settings');
                $analytics_url = $config->get('analytics_url');
                $analytics_user = $config->get('analytics_user');
                $analytics_pass = $config->get('analytics_pass');
                $UpdateObject = \Drupal::service('csom_datalayer.update_analytics_tables');

                //make the database connections
                // \Drupal::logger('csom_datalayer')->notice('datalayer start content pull for: Piwik/Matomo' );
                $UpdateObject->PullPiwikData($analytics_user, $analytics_pass, $analytics_url);

                //perform the updates
                $UpdateObject->PerformPiwikTableUpdate();

                //Set this object for garbage collection as it takes up a lot of space
                unset($UpdateObject);
            }
        }

    }


    //Extract ga_id from cookie
    function extract_GA_id()
    {
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
        
        // Parse cookie _ga='<something>.<something>.<clientid>.<timestamp>';
        $this->clientId = explode(".", $_COOKIE["_ga"])[2];
    }


    //Extract VisitorID from cookie
    function extract_PIWIK_id()
    {
        foreach ($_COOKIE as $key=>$val) {

            //Grab piwik analytics visitor id
            if (strpos($key, "pk_id")) {

                //$current_user = new PIWIKID($_COOKIE, $key);

                //$this->visitorId = $current_user->GetVisitorID();
                $this->visitorId = explode('.', $_COOKIE["$key"])[0];
            }

        }
    }

    //Get the current path
    function c_path()
    {
        return $_GET['q'];
    }

    // Extract the subscriberID from the URL
    function get_subscriberID()
    {
        if (isset($_GET['subscriberid'])) {
            $this->SubscriberID = $_GET['subscriberid'];
        } 
    }

    //Extract email from the URL
    function get_email()
    {
        if (isset($_GET['email'])) {
            $this->email = $_GET['email'];
        } 
    }

    function update_database_param()
    {
        if (isset($_GET['super_secret_update_param'])) {
            $this->update_database = $_GET['super_secret_update_param'];
        } 
    }


    function get_alias()
    {

        $url = drupal_lookup_path('alias', c_path());

        return $url;
    }



    function load_program_status_into_datalayer()
    {
        
        if (empty($this->program_status)) { return array(); 
        }

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
        // \Drupal::logger('csom_datalayer')->notice('load_visitor_status_into_datalayer');
        if (empty($this->visitor_status)) { return array(); 
        }
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
                FROM {csom_slate_status} where (GAClientID != '' AND	GAClientID = :clientID) OR (PiwikVisitorID != '' AND	PiwikVisitorID = :visitorId)";
            return $connection->query($sql, [':clientID' => $this->clientId, ':visitorId' => $this->visitorId])->fetchAll();
        } else {

            $sql = "SELECT Program, CurrentStatus, Email, GAClientID, PiwikVisitorID, Inquiry, InquiryDate, InquiryActivities, Applicant, AppDate, AppTerm, AppStatus, Inactive, InactiveDate
                FROM {csom_slate_status} where Email =:email";
            return $connection->query($sql, [':email' => $this->email])->fetchAll();
        }
    }

    function get_visitor_status()
    {
        $connection = \Drupal::database();
        // $vM = "visitorID : " . $this->visitorId . "  : Not is empty " . !empty($this->visitorId) . "  : " . ($this->visitorId != '') ." : " . (!empty($this->SubscriberID) || !empty($this->visitorId));
        // $sM = "SubscriberID : " . $this->SubscriberID . "  : Not is empty " . !empty($this->SubscriberID) . " : " ;
        // \Drupal::logger('csom_datalayer')->notice($vM);
        // \Drupal::logger('csom_datalayer')->notice($sM);
        if (!empty($this->SubscriberID) || !empty($this->visitorId)) {
            // \Drupal::logger('csom_datalayer')->notice("grabbing visitor on s or v");
            $sql = "SELECT SubscriberID, PiwikVisitorID, VisitorType, Browser, DeviceType, Resolution, TotalVisits, AvgActionsPerVisit, AvgVisitDuration, DaysSinceLastVisit, FirstActionDate, LastActionDate, LastLocation, LastReferrerUrl, LastCampaignSource, LastCampaignName, LastCampaignMedium
                    FROM {csom_piwik_status} where (SubscriberID = :subscriberid AND SubscriberID != '') OR (PiwikVisitorID = :visitorId AND PiwikVisitorID != '')";
            return $connection->query($sql, [':subscriberid' => $this->SubscriberID, ':visitorId' => $this->visitorId])->fetchAll();

        } elseif ($this->visitorId != '') {

            if (!empty($this->SubscriberID)) {
                $sql = "SELECT SubscriberID, PiwikVisitorID, VisitorType, Browser, DeviceType, Resolution, TotalVisits, AvgActionsPerVisit, AvgVisitDuration, DaysSinceLastVisit, FirstActionDate, LastActionDate, LastLocation, LastReferrerUrl, LastCampaignSource, LastCampaignName, LastCampaignMedium
                    FROM {csom_piwik_status} where (PiwikVisitorID = :visitorId AND PiwikVisitorID != '') OR (SubscriberID = :subscriberid AND SubscriberID != '')";
                return $connection->query($sql, [':visitorId' => $this->visitorId, ':subscriberid' => $this->SubscriberID])->fetchAll();
            } else {
                $sql = "SELECT SubscriberID, PiwikVisitorID, VisitorType, Browser, DeviceType, Resolution, TotalVisits, AvgActionsPerVisit, AvgVisitDuration, DaysSinceLastVisit, FirstActionDate, LastActionDate, LastLocation, LastReferrerUrl, LastCampaignSource, LastCampaignName, LastCampaignMedium
                    FROM {csom_piwik_status} where (PiwikVisitorID = :visitorId AND PiwikVisitorID != '')";
                return $connection->query($sql, [':visitorId' => $this->visitorId])->fetchAll();
            }

        } else {
            return null;
        }


    }





}
?>
