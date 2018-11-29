<?php

function csom_datalayer_admin_search_form($form, &$form_state) {


  $form['components'] = array(
    '#type' => 'fieldset',
    '#title' => t('Search For Records'),
    '#collapsible' => TRUE,
    '#collapsed' => FALSE,
    '#description' => t('Search databases for analytics records.'),
  );

  $active = array(0 => t('Visitor ID'), 1 => t('Subscriber ID'), 2 => t('Email'));

  $form['components']['option'] = array(
    '#type' => 'radios',
    '#title' => t('Key to Search By:'),
    '#default_value' => 0,
    '#options' => $active,
  );

  $form['components']['search'] = array(
    '#type' => 'textfield',

    '#title' => t('Value to Look Up:'),

    '#default_value' => 'Enter Search query.... ',

    '#size' => 60,

    '#required' => TRUE,
  );

  if (!empty($form_state['piwik_results_table'])) {
    $form['components']['piwik_results_table'] = array('#markup' => "<div style= overflow:scroll; max-width: 100%;>".$form_state['piwik_results_table']."<div>");
  }

  if (!empty($form_state['slate_results_table'])) {
    $form['components']['slate_results_table'] = array('#markup' => "<div style= overflow:scroll; max-width: 100%;>".$form_state['slate_results_table']."<div>");
  }

  $form['submit'] = array(
    '#type' => 'submit',
    '#value' => 'Search',
  );

  return $form;

}

function csom_datalayer_admin_search_form_submit($form, &$form_state) {
  $option = $form_state['values']['option'];
  $query = $form_state['values']['search'];
  $form_state['piwik_results_table'] = print_piwik_table($query, $option);
  $form_state['slate_results_table'] = print_slate_table($query, $option);
  $form_state['rebuild'] = TRUE;
}

//Create a table for piwik data
function print_piwik_table($query, $option) {
  $result = NULL;
  $header = NULL;
  $rows = array();

  if ($option == 0) {
    $visitorID = $query;
    $sql = "SELECT SubscriberID, PiwikVisitorID, VisitorType, Browser, DeviceType, Resolution, TotalVisits, AvgActionsPerVisit, AvgVisitDuration, DaysSinceLastVisit, FirstActionDate, LastActionDate, LastLocation, LastReferrerUrl, LastCampaignSource, LastCampaignName, LastCampaignMedium
          FROM {csom_piwik_status} where PiwikVisitorID =:visitorID";
    $result = db_query($sql, [':visitorID' => $visitorID])->fetchAll();
    if (empty($result)) {
      return;
    }
  } else if ($option == 1) {
    $SubscriberID = $query;
    $sql = "SELECT SubscriberID, PiwikVisitorID, VisitorType, Browser, DeviceType, Resolution, TotalVisits, AvgActionsPerVisit, AvgVisitDuration, DaysSinceLastVisit, FirstActionDate, LastActionDate, LastLocation, LastReferrerUrl, LastCampaignSource, LastCampaignName, LastCampaignMedium
          FROM {csom_piwik_status} where SubscriberID  =:subscriberid";
    $result = db_query($sql, [':subscriberid' => $SubscriberID])->fetchAll();
    if (empty($result)) {
      return;
    }
  } else {

    return;
  }



    //Find Piwik data rows
    foreach ($result as $record) {

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

      $rows[] = [
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
    }

    // Table header
    $header = array(
      'SubscriberID' => t("SubscriberID"),
      "PiwikVisitorID" => t("PiwikVisitorID"),
      "VisitorType" => t("VisitorType"),
      "Browser" => t('Browser'),
      'DeviceType' => t('DeviceType'),
      'Resolution' => t('Resolution'),
      'TotalVisits' => t('TotalVisits'),
      "AvgActionsPerVisit" => t("AvgActionsPerVisit"),
      "DaysSinceLastVisit" => t("DaysSinceLastVisit"),
      "FirstActionDate" => t("FirstActionDate"),
      "LastActionDate" => t("LastActionDat"),
      "LastLocation" => t("LastLocation"),
      "LastReferrerUrl" => t("LastReferrerUrl"),
      "LastCampaignSource" => t("LastCampaignSourc"),
      "LastCampaignName" => t("LastCampaignName"),
      "LastCampaignMedium" => t("LastCampaignMedium"),
    );

  // Format and print out the table.
  return theme('table', array(
      'header'  => $header,
      'rows'    => $rows,
      'id'      => 'table-articles',
      'class'   => 'articles',
    ));

}

function print_slate_table($query, $option) {
  $result = NULL;
  $header = NULL;
  $rows = array();

  //Search by visitor ID
  if ($option == 0) {
    $visitorID = $query;
    $sql = "SELECT Program, CurrentStatus, Email, GAClientID, PiwikVisitorID, Inquiry, InquiryDate, InquiryActivities, Applicant, AppDate, AppTerm, AppStatus, Inactive, InactiveDate
        FROM {csom_slate_status} where PiwikVisitorID =:visitorId";
    $result = db_query($sql, array(':visitorId' => $visitorID))->fetchAll();
    if (empty($result)) {
      return;
    }



  }
  //Search by email
  else if ($option == 2) {
    $email = $query;
    $sql = "SELECT Program, CurrentStatus, Email, GAClientID, PiwikVisitorID, Inquiry, InquiryDate, InquiryActivities, Applicant, AppDate, AppTerm, AppStatus, Inactive, InactiveDate
        FROM {csom_slate_status} where Email =:email";
    $result = db_query($sql, array(':email' => $email))->fetchAll();
    if (empty($result)) {
      return;
    }
  } else {

    return;
  }

  //Find slate data rows
  foreach ($result as $record) {

    $CurrentStatus = $record->CurrentStatus;
    $Email = $record->Email;
    $GAClientID = $record->GAClientID;
    $PiwikVisitorID = $record->PiwikVisitorID;
    $Inquiry = $record->Inquiry;
    $InquiryDate = $record->InquiryDate;
    $InquiryActivities = $record->InquiryActivities ;
    $Applicant = $record->Applicant;
    $AppDate = $record->AppDate;
    $AppTerm = $record->AppTerm;
    $AppStatus = $record->AppStatus;
    $Inactive = $record->Inactive;
    $InactiveDate = $record->InactiveDate;


    $rows[] = array(
      'CurrentStatus' => $CurrentStatus,
      'Email' => $Email,
      'GAClientID' => $GAClientID,
      'PiwikVisitorID' => $PiwikVisitorID,
      'Inquiry' => $Inquiry,
      'InquiryDate' => $InquiryDate,
      'InquiryActivities' => $InquiryActivities,
      'Applicant' => $Applicant,
      'AppDate' => $AppDate,
      'AppTerm' => $AppTerm,
      'AppStatus' => $AppStatus,
      'Inactive' => $Inactive,
      'InactiveDate' => $InactiveDate,
    );

  }

  // Table header
  $header = array(
    'CurrentStatus' => t('CurrentStatus'),
    'Email' => t('Email'),
    'GAClientID' => t('GAClientID'),
    'PiwikVisitorID' => t('PiwikVisitorID'),
    'Inquiry' => t('Inquiry'),
    'InquiryDate' => t('InquiryDate'),
    'InquiryActivities' => t('InquiryActivities'),
    'Applicant' => t('Applicant'),
    'AppDate' => t('AppDate'),
    'AppTerm' => t('AppTerm'),
    'AppStatus' => t('AppStatus'),
    'Inactive' => t('Inactive'),
    'InactiveDate' => t('InactiveDate'),
  );

  // Format and print out the table.
  return theme('table', array(
    'header'  => $header,
    'rows'    => $rows,
    'id'      => 'table-articles',
    'class'   => 'articles',
  ));
}

