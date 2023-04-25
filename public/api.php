<?php

// Current version
define('API_VERSION', 0.1);

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

      if (API_SEARCH_ENABLED) {

        // Connect database
        $db = new MySQL(DB_HOST, DB_PORT, DB_NAME, DB_USERNAME, DB_PASSWORD);

        // Connect Sphinx search server
        $sphinx = new SphinxQL(SPHINX_HOST, SPHINX_PORT);


        // Filter request data
        $query = !empty($_GET['query']) ? Filter::url($_GET['query']) : '';
        $page  = !empty($_GET['page']) ? Filter::url($_GET['page']) : 1;

        // Make search request
        $sphinxResultsTotal = $sphinx->searchHostPagesTotal('"' . $query . '"');
        $sphinxResults      = $sphinx->searchHostPages('"' . $query . '"', $page * API_SEARCH_PAGINATION_RESULTS_LIMIT - API_SEARCH_PAGINATION_RESULTS_LIMIT, API_SEARCH_PAGINATION_RESULTS_LIMIT, $sphinxResultsTotal);

        // Generate results
        $dbResults = [];

        foreach ($sphinxResults as $i => $sphinxResult) {

          if ($hostPage = $db->getFoundHostPage($sphinxResult->id)) {

            $dbResults[$i] = $hostPage;

            $dbResults[$i]->weight = $sphinxResult->weight;
          }
        }

        // Make response
        $response = [
          'status'  => true,
          'totals'  => $sphinxResultsTotal,
          'result'  => $dbResults,
        ];

      } else {

        $response = [
          'status' => false,
          'result' => [],
        ];
      }

    break;

    // Host API
    case 'hosts';

      if (API_HOSTS_ENABLED) {

        // Connect database
        $db = new MySQL(DB_HOST, DB_PORT, DB_NAME, DB_USERNAME, DB_PASSWORD);

        $response = [
          'status' => true,
          'totals' => $db->getTotalHosts(),
          'result' => $db->getAPIHosts(API_HOSTS_FIELDS),
        ];

      } else {

        $response = [
          'status' => false,
          'result' => [],
        ];
      }

    break;

    // Manifest API
    case 'manifest';

      if (API_MANIFEST_ENABLED) {

        $response = [
          'status' => true,
          'result' => [
            'applicationName'            => APPLICATION_NAME,
            'applicationMode'            => APPLICATION_MODE,
            'applicationUrl'             => WEBSITE_DOMAIN,

            'crawlUrlRegexp'             => CRAWL_URL_REGEXP,

            'crawlHostDefaultPagesLimit' => CRAWL_HOST_DEFAULT_PAGES_LIMIT,
            'crawlHostDefaultStatus'     => CRAWL_HOST_DEFAULT_STATUS,
            'crawlHostDefaultMetaOnly'   => CRAWL_HOST_DEFAULT_META_ONLY,

            'crawlHostPageSecondsOffset' => CRAWL_PAGE_SECONDS_OFFSET,
            'cleanHostSecondsOffset'     => CLEAN_HOST_SECONDS_OFFSET,

            'crawlRobotsDefaultRules'    => CRAWL_ROBOTS_DEFAULT_RULES,
            'crawlRobotsPostfixRules'    => CRAWL_ROBOTS_POSTFIX_RULES,

            'apiVersion'                 => API_VERSION,

            'apiEnabled'                 => API_ENABLED,
            'apiSearchEnabled'           => API_SEARCH_ENABLED,
            'apiHostsEnabled'            => API_HOSTS_ENABLED,
          ],
        ];

      } else {

        $response = [
          'status' => false,
          'result' => [],
        ];
      }

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