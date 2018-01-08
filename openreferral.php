<?php
/**
 * @package Open Referral
 * @version 0.9
 */
/*
Plugin Name: Open Referral
Plugin URI: http://github.com/openadvocate/openreferral-wordpress
Description: OpenReferral for Wordpress
Author: Ki Kim (kkim@urbaninsight.com)
Version: 0.9
Author URI: http://www.openadvocate.org
*/

/**
 * Admin page.
 */
function openref_activate() {
  add_option('openref_api_base_url', '');
  add_option('openref_api_key', '');
  add_option('openref_cache', '');
}

function openref_deactivate() {
  delete_option('openref_api_base_url');
  delete_option('openref_api_key');
  delete_option('openref_cache');
}

function openref_admin_init() {
  register_setting('openreferral', 'openref_api_base_url');
  register_setting('openreferral', 'openref_api_key');
}

function openref_admin_menu() {
  add_options_page(
    'Open Referral Options',
    'Open Referral',
    'manage_options',
    'openreferral',
    'openref_options_form'
  );
}

function openref_admin_action_handler() {
  if (isset($_POST['option_page']) and $_POST['option_page'] == 'openreferral') {
    if ($_POST['formaction'] == 'openref_test_connection') {
      $api_key = get_option('openref_api_key');
      $or_base_url = get_option('openref_api_base_url');

      if (!$api_key) {
        add_settings_error('error', 'settings_updated', __('API key missing.'), 'error');
      }

      if (!$or_base_url) {
        add_settings_error('error', 'settings_error', __('Open Referral API Base URL missing.'), 'updated');
      }

      if ($api_key and $or_base_url) {
        $airtable = wp_remote_request($or_base_url . "/organizations", array('headers' => array('Authorization' => 'Bearer ' . $api_key)));

        if (isset($airtable['response']) and
            $airtable['response']['code'] == 200 and
            $airtable['response']['message'] == 'OK')
        {
          add_settings_error('general', 'settings_updated', __('Test run successfully.'), 'updated');
        }
        else {
          add_settings_error('error', 'settings_updated', __('Connection failed.'), 'updated');
        }
      }
    }
    elseif ($_POST['formaction'] == 'openref_purge_cache') {
      update_option('openref_cache', '');

      add_settings_error('general', 'settings_updated', __('Purged cache.'), 'updated');
    }
  }
}

function openref_options_form() {
  include(WP_PLUGIN_DIR.'/openreferral/options.php');
}

register_activation_hook(__FILE__, 'openref_activate');
register_deactivation_hook(__FILE__, 'openref_deactivate');

add_action('admin_init', 'openref_admin_init');
add_action('admin_init', 'openref_admin_action_handler');
add_action('admin_menu', 'openref_admin_menu');



/**
 * Front end pages.
 */

// Alter path prefix from /wp-json to /openreferral
add_filter('rest_url_prefix', 'openref_api_slug');

function openref_api_slug($slug) {
  return 'openreferral';
}

// Register custom path to /openreferral/v1/datapackage.json
add_action('rest_api_init', function ($server) {
  register_rest_route('v1', 'datapackage.json', array(
    'methods' => 'GET',
    'callback' => 'openref_callback_json',
  ));
});

// Callback for path datapackage.json
function openref_callback_json( $data ) {
  $base_url = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'];
  $name = $_SERVER['HTTP_HOST'] . '-open-referral-dataset';

  $json = array(
    'name' => $name,
    'resources' => array(
      array(
        'path' => $base_url . '/openreferral/v1/organizations.csv',
        'schema' => array(
          'fields' => openref_fields('schema', 'organizations')
        )
      ),
      array(
        'path' => $base_url . '/openreferral/v1/phones.csv',
        'schema' => array(
          'fields' => openref_fields('schema', 'phones')
        )
      ),
      array(
        'path' => $base_url . '/openreferral/v1/postal_addresses.csv',
        'schema' => array(
          'fields' => openref_fields('schema', 'address')
        )
      ),
    )
  );

  return $json;
}

// Hook into pre-serve in otder to return csv instead of json.
add_filter('rest_pre_serve_request', 'openref_serve_csv', 10, 4);

function openref_serve_csv( $served, $result, $request, $server ) {
  $route = explode('/', $request->get_route());

  switch (array_pop($route)) {
    case 'organizations.csv':
      $table = 'organizations';
      break;
    case 'phones.csv':
      $table = 'phones';
      break;
    case 'postal_addresses.csv':
      $table = 'address';
      break;
  }

  if (!empty($table)) {
    if (!$csv = openref_get_openreferral_data($table)) {
      echo 'Open Referral data not available.';
      return;
    }

    header('Content-type', 'text/csv');
    $out = fopen('php://output', 'w');
    foreach ($csv as $line) {
      fputcsv($out, $line);
    }
    fclose($out);

    $served = true;
  }

  return $served;
}

function openref_get_openreferral_data($table) {
  if (!$cache = get_option('openref_cache')) {
    openref_build_openreferral_data();

    $cache = get_option('openref_cache');
  }

  return isset($cache[$table]) ? $cache[$table] : NULL;
}


function openref_build_openreferral_data() {
  $api_key = get_option('openref_api_key');
  $or_base_url = get_option('openref_api_base_url');

  if (!$api_key or !$or_base_url) return;

  $tables = array('services', 'locations', 'organizations', 'phones', 'address', 'contact', 'program');
  $fields = openref_fields('field', 'all');

  $data = array();

  foreach ($tables as $table) {
    $airtable = wp_remote_request($or_base_url . "/$table", array('headers' => array('Authorization' => 'Bearer ' . $api_key)));

    if (isset($airtable['response']) and
        $airtable['response']['code'] == 200 and
        $airtable['response']['message'] == 'OK')
    {
      $records = json_decode($airtable['body'])->records;

      foreach ($records as $row) {
        $data[$table][$row->id] = $row->fields;
      }
    }
  }

  // Replace record id with label (mostly name field except phone number) and
  // build csv data to cache.
  foreach (array_keys($fields) as $table) {
    foreach ($data[$table] as $row) {
      $item = array();

      foreach ($row as $field => $value) {
        if (is_array($value)) {
          foreach ($value as $key => $rec_id) {
            $val = NULL;
            if (strpos($rec_id, 'rec') !== FALSE) {
              if (isset($data[$field][$rec_id]->name)) {
                $val = $data[$field][$rec_id]->name;
              }
              elseif (isset($data[$field][$rec_id]->number)) {
                $val = $data[$field][$rec_id]->number;
              }
            }

            if ($val) {
              $value[$key] = $val;
            }
          }
          $value = join(', ', $value);
        }
        $item[$field] = $value;
      }
      $openref[$table][] = $item;
    }

    foreach ($openref[$table] as $row) {
      if (empty($csv_data[$table])) {
        $csv_data[$table][] = array_values($fields[$table]);
      }

      $item = array();

      foreach ($fields[$table] as $field) {
        $item[] = isset($row[$field]) ? $row[$field] : '';
      }

      $csv_data[$table][] = $item;
    }
  }

  update_option('openref_cache', $csv_data);
}

add_action( 'rest_api_init', function () {
  register_rest_route('v1', 'organizations.csv', array(
    'methods' => 'GET',
    'callback' => 'openref_callback_csv',
  ) );
  register_rest_route('v1', 'phones.csv', array(
    'methods' => 'GET',
    'callback' => 'openref_callback_csv',
  ) );
  register_rest_route('v1', 'postal_addresses.csv', array(
    'methods' => 'GET',
    'callback' => 'openref_callback_csv',
  ) );
} );

function openref_callback_csv() {
  $response = new WP_REST_Response();

  $response->header('Content-type', 'text/csv');

  return $response;
}

function openref_fields($mode, $table) {
  $fields = array(
    'organizations' => array(
      'name', 'alternate_name', 'description', 'email', 'url', 'legal_status', 'tax_status', 'tax_id', 'year_incorporated', 'services', 'phones', 'locations', 'contact', 'details', 'program'
    ),
    'phones' => array(
      'number', 'locations', 'services', 'organizations', 'contacts', 'service_at_location_id', 'extension', 'type', 'language', 'description'
    ),
    'address' => array(
      'address_1', 'city', 'state_province', 'postal_code', 'region', 'country', 'attention', 'address_type', 'locations'
    ),
  );

  if ($mode == 'field') {
    return $table == 'all' ? $fields : $fields[$table];
  }
  elseif ($mode == 'schema') {
    return array_map(function ($val) {
      return array('name' => $val, 'type' => 'string');
    }, $fields[$table]);
  }
}
