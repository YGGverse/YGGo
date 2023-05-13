<?php

// Current version
define('API_VERSION', 0.9);

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
        $type  = !empty($_GET['type']) ? Filter::url($_GET['type']) : 'text';
        $mode  = !empty($_GET['mode']) ? Filter::url($_GET['mode']) : 'default';
        $query = !empty($_GET['query']) ? Filter::url($_GET['query']) : '';
        $page  = !empty($_GET['page']) ? (int) $_GET['page'] : 1;

        // Make search request
        $sphinxResultsTotal = $sphinx->searchHostPagesTotal(Filter::searchQuery($query, $mode), $type);
        $sphinxResults      = $sphinx->searchHostPages(Filter::searchQuery($query, $mode), $type, $page * API_SEARCH_PAGINATION_RESULTS_LIMIT - API_SEARCH_PAGINATION_RESULTS_LIMIT, API_SEARCH_PAGINATION_RESULTS_LIMIT, $sphinxResultsTotal);

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
            'config' => [
              'websiteDomain'               => WEBSITE_DOMAIN,
              'crawlUrlRegexp'              => CRAWL_URL_REGEXP,
              'crawlHostDefaultNsfw'        => CRAWL_HOST_DEFAULT_NSFW,
              'crawlHostDefaultPagesLimit'  => CRAWL_HOST_DEFAULT_PAGES_LIMIT,
              'crawlHostDefaultStatus'      => CRAWL_HOST_DEFAULT_STATUS,
              'crawlHostDefaultMetaOnly'    => CRAWL_HOST_DEFAULT_META_ONLY,
              'crawlHostPageSecondsOffset'  => CRAWL_PAGE_SECONDS_OFFSET,
              'crawlHostPageMimeIndex'      => CRAWL_PAGE_MIME_INDEX,
              'crawlHostPageMimeSnapLocal'  => CRAWL_PAGE_MIME_SNAP_LOCAL,
              'cleanHostSecondsOffset'      => CLEAN_HOST_SECONDS_OFFSET,
              'crawlRobotsDefaultRules'     => CRAWL_ROBOTS_DEFAULT_RULES,
              'crawlRobotsPostfixRules'     => CRAWL_ROBOTS_POSTFIX_RULES,
            ],
            'api' => [
              'version'  => API_VERSION,
              'manifest' => API_ENABLED && API_MANIFEST_ENABLED ? WEBSITE_DOMAIN . '/api.php?action=manifest' : false,
              'search'   => API_ENABLED && API_SEARCH_ENABLED ? WEBSITE_DOMAIN . '/api.php?action=search' : false,
              'hosts'    => API_ENABLED && API_HOSTS_ENABLED ? WEBSITE_DOMAIN . '/api.php?action=hosts' : false,
            ]
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