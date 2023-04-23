<?php

// Load system dependencies
require_once('../config/app.php');
require_once('../library/curl.php');
require_once('../library/robots.php');
require_once('../library/filter.php');
require_once('../library/parser.php');
require_once('../library/mysql.php');
require_once('../library/sphinxql.php');

if (API_ENABLED) {

  // Action
  switch (!empty($_GET['action']) ? $_GET['action'] : false) {

    // Search API
    case 'search';

      // Connect database
      $db = new MySQL(DB_HOST, DB_PORT, DB_NAME, DB_USERNAME, DB_PASSWORD);

      // Connect Sphinx search server
      $sphinx = new SphinxQL(SPHINX_HOST, SPHINX_PORT);


      // Filter request data
      $query = !empty($_GET['query']) ? Filter::url($_GET['query']) : '';
      $page  = !empty($_GET['page']) ? Filter::url($_GET['page']) : 1;

      // Make search request
      $sphinxResults      = $sphinx->searchHostPages('"' . $query . '"', $page * API_SEARCH_PAGINATION_RESULTS_LIMIT - API_SEARCH_PAGINATION_RESULTS_LIMIT, API_SEARCH_PAGINATION_RESULTS_LIMIT);
      $sphinxResultsTotal = $sphinx->searchHostPagesTotal('"' . $query . '"');

      // Generate results
      $dbResults = [];

      foreach ($sphinxResults as $sphinxResult) {

        if ($hostPage = $db->getFoundHostPage($sphinxResult->id)) {

          $dbResults[] = $hostPage;
        }
      }

      // Make response
      $response = [
        'status'  => true,
        'totals'  => $sphinxResultsTotal,
        'result'  => $dbResults,
      ];

    break;

    // Host API
    case 'hosts';

      // Connect database
      $db = new MySQL(DB_HOST, DB_PORT, DB_NAME, DB_USERNAME, DB_PASSWORD);

      $response = [
        'status' => true,
        'totals' => $db->getTotalHosts(),
        'result' => $db->getAPIHosts(API_HOSTS_FIELDS),
      ];

    break;

    default:

      $response = [
        'status'  => false,
        'message' => _('Undefined API action request.'),
      ];
  }

} else {

  $response = [
    'status'  => false,
    'message' => _('API requests disabled by the node owner.'),
  ];
}

// Output
header('Content-Type: application/json; charset=utf-8');

echo json_encode($response);