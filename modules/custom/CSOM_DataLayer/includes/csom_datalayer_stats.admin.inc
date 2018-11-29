<?php


function csom_datalayer_admin_stats_form($form, &$form_state) {

    $form_state['stats_table'] = print_tables();
    $form['components'] = array(
        '#type' => 'fieldset',
        '#title' => t('Table States'),
        '#collapsible' => TRUE,
        '#collapsed' => FALSE,
        '#description' => t('Statistics on Analytics Data Tables.'),
    );

    $form['components']['results_table'] = array('#markup' => "<div style= overflow:scroll; max-width: 100%;>".$form_state['stats_table']."<div>");

    $form['Refresh'] = array(
        '#type' => 'submit',
        '#value' => 'Refresh',
    );

    return $form;

}

function csom_datalayer_admin_search_form_submit($form, &$form_state) {
    $form_state['stats_table'] = print_slate_table();
    $form_state['rebuild'] = TRUE;
}

function print_tables() {
    $result = NULL;
    $rows = array();
    //Count the number of rows in the piwik table
    $sql = "SELECT COUNT(*) FROM {csom_slate_status}";

    $numRecords = (array) db_query($sql)->fetchAll()[0];
    $n1 = $numRecords['COUNT(*)'];

    //Count the number of rows in the slate table
    $sql = "SELECT COUNT(*) FROM {csom_piwik_status}";

    $numRecords = (array) db_query($sql)->fetchAll()[0];
    $n2 = $numRecords['COUNT(*)'];

    //rows
    $rows[] = array(
        'TableName' => 'Slate Table',
        'NumberOfRecords' => $n1,
        'LastUpdate' => variable_get("LastSlateTableUpdate"),
    );
    $rows[] = array(
        'TableName' => 'Piwik Table',
        'NumberOfRecords' => $n2,
        'LastUpdate' => variable_get("LastPiwikTableUpdate"),
    );

    // Table header
    $header = array(
        'TableName' =>  t('Table Name'),
        'NumberOfRecords' => t('Number of Records'),
        'LastUpdate' => t('Last Update Run'),
    );

    // Format and print out the table.
    return theme('table', array(
        'header'  => $header,
        'rows'    => $rows,
        'id'      => 'table-articles',
        'class'   => 'articles',
    ));
}


