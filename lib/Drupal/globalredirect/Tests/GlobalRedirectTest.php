<?php

namespace Drupal\globalredirect\Tests;

use Drupal\simpletest\WebTestBase;

define('ERROR_MESSAGE',   'ERROR<br />Expected Path: !expected_path<br />Expected Status Code: !expected_status<br />Location: !location<br />Status: !status');
define('SUCCESS_MESSAGE', 'SUCCESS<br />Expected Path: !expected_path<br />Expected Status Code: !expected_status<br />Location: !location<br />Status: !status');
define('INFO_MESSAGE',    'Running Test: !id<br />Requesting URL: !url<br />Path Info:<br />!path');

/**
 * @file
 * Global Redirect functionality tests
 */

class GlobalRedirectTest extends WebTestBase {

  private $gr_forum_term;
  private $gr_term;


  function setUp(array $modules = array()) {
    $modules[] = 'path';
    $modules[] = 'globalredirect';
    $modules[] = 'taxonomy';
    $modules[] = 'forum';
    parent::setUp($modules);

    // Clean URLs should be enabled for testing.
    variable_set('clean_url', 1);

    // Create a user
    $this->normal_user = $this->drupalCreateUser(array(
      'access content',
      'create page content',
      'create url aliases',
      'access administration pages', // For the 'ignore admin path' test
    ));
    $this->drupalLogin($this->normal_user);

    // Create a dummy node
    $node = array(
      'type' => 'page',
      'title' => 'Test Page Node',
      'path' => array('alias' => 'test-node'),
      'language' => LANGUAGE_NONE,
    );

    // Save the node
    $node = $this->drupalCreateNode($node);

    // Create an alias for the create story path - this is used in the "redirect with permissions testing" test.
    $path = array('source' => 'node/add/article', 'alias' => 'node-add-article');
    path_save($path);

    // Create an alias for the admin URL - this is used in the "ignore admin path" test.
    $path = array('source' => 'admin', 'alias' => 'administration');
    path_save($path);


    // The forum vocab should already be created - should be term 1?
    $forum_term = (object)array(
      'vid' => variable_get('forum_nav_vocabulary', 0),
      'name' => 'Test Forum Term',
    );
    taxonomy_term_save($forum_term);
    $this->gr_forum_term = taxonomy_term_load($forum_term->tid);

    // Create another test vocab (with a default module) - should be vocab 2?
    $vocab = (object)array(
      'name' => 'test vocab',
      'machine_name' => 'test-vocab',
      'hierarchy' => 0,
      'module' => 'taxonomy',
    );
    taxonomy_vocabulary_save($vocab);
    $vocab = taxonomy_vocabulary_load($vocab->vid, TRUE);

    // Create a test term - Should be term 2?
    $term = (object)array(
      'vid' => $vocab->vid,
      'name' => 'Test Term',
      'path' => array('alias' => 'test-term'),
    );
    taxonomy_term_save($term);
    $this->gr_term = taxonomy_term_load($term->tid);
  }



  protected function _globalredirect_test_paths() {
    // Get the settings
    $settings = _globalredirect_get_settings();
    $this->assert('pass', '<pre>' . print_r($settings, TRUE) . '</pre>');

    // Array of request => "array of expected data" pairs.
    // The array must have a return-code key (with a numeric HTTP code as a value (eg 301 or 200).
    // The array may also have an expected-path key with a value representing the expected path. If this is ommitted, the request is passed through url().
    return array(
      // "" is the frontpage. Should NOT redirect. -- Test for normal requests
      array(
        'request' => '',
        'return-code' => 200,
        'expected-path' => '',
      ),

      // "node" is the default frontpage. Should redirect to base path. --- Test for frontpage redirect
      // The result depends on settings
      array(
        'request' => 'node',
        'return-code' => $settings['frontpage_redirect'] ? 301 : 200,
        'expected-path' => $settings['frontpage_redirect'] ? '' : 'node',
      ),

      // "node/1" has been defined above as having an alias ("test-node"). Should 301 redirect to the alias. --- Test for source path request on aliased path
      array(
        'request' => 'node/1',
        'return-code' => 301,
      ),

      // "node/add/article" has an alias, however the redirect depends on the menu_check setting --- Test for access request to secured url
      array(
        'request' => 'node/add/article',
        'return-code' => $settings['menu_check'] ? 403 : 301,
      ),

      // "user/[uid]/" has no alias, but the redirect depends on the $deslash_check setting --- Test for deslashing redirect
      array(
        'request' => 'user/' . $this->loggedInUser->uid . '/',
        'return-code' => $settings['deslash'] ? 301 : 200,
        'expected-path' => 'user/' . $this->loggedInUser->uid,
      ),

      // NonClean to Clean check 1 --- This should always redirect as we're requesting an aliased path in unaliased form (albeit also unclean)
      array(
        'request' => url('<front>', array('absolute' => TRUE)),
        'options' => array('query' => array('q' => 'node/1'), 'external' => TRUE),
        'return-code' => 301,
        'expected-path' => 'test-node',
      ),

      // NonClean to Clean check 2 --- This may or may not redirect, depending on the state of nonclean_to_clean as we're requesting an unaliased path
      array(
        'request' => url('<front>', array('absolute' => TRUE)),
        'options' => array('query' => array('q' => 'node/add/page'), 'external' => TRUE),
        'return-code' => $settings['nonclean_to_clean'] ? 301 : 200,
        'expected-path' => $settings['nonclean_to_clean'] ? 'node/add/page' : '?q=node/add/page',
      ),

      // Ensure that the NonClean to Clean with an external URL does not redirect.
      array(
        'request' => url('<front>', array('absolute' => TRUE)),
        'options' => array('query' => array('q' => 'http://www.example.com'), 'external' => TRUE),
        'return-code' => 404,
      ),
      array(
        'request' => url('<front>', array('absolute' => TRUE)),
        'options' => array('query' => array('q' => 'http://www.example.com/'), 'external' => TRUE),
        'return-code' => 404,
      ),
      // Also test un-escaped query strings with external URLs.
      array(
        'request' => url('<front>', array('absolute' => TRUE)) . '?q=http://www.example.com',
        'options' => array('external' => TRUE),
        'return-code' => 404,
      ),
      array(
        'request' => url('<front>', array('absolute' => TRUE)) . '?q=http://www.example.com/',
        'options' => array('external' => TRUE),
        'return-code' => 404,
      ),
      // Test with external URLs in the destination query string.
      array(
        'request' => 'node/1',
        'options' => array('query' => array('destination' => 'http://www.example.com/')),
        'return-code' => 301,
        'expected-path' => 'test-node',
      ),

      // Case Sensitive URL Check --- This may or may not redirect, depending on the state of case_sensitive_urls as we're requesting an aliased path in the wrong case
      array(
        'request' => 'Test-Node',
        'options' => array('external' => TRUE),
        'return-code' => $settings['case_sensitive_urls'] ? (db_driver() == 'sqlite' ? 404 : 301) : (db_driver() == 'sqlite' ? 404 : 200), // SQLite cannot return case-insensitive rows.
        'expected-path' => $settings['case_sensitive_urls'] ? 'test-node' : 'Test-Node',
      ),


      // Term Path Handler Check --- This may or may not redirect, depending on the state of term_path_handler as we're requesting an aliased path with the wrong source
      array(
        'request' => 'taxonomy/term/' . $this->gr_forum_term->tid, // TODO: WE're assumeing term 1 is the forum tid created in setUp() - bad?
        'return-code' => $settings['term_path_handler'] ? 301 : 200,
        'expected-path' => $settings['term_path_handler'] ? 'forum/' . $this->gr_forum_term->tid : 'taxonomy/term/' . $this->gr_forum_term->tid,
      ),


      // Trailing Zero Check --- This may or may not redirect, depending on the state of trailing_zero, as we're requesting an aliased path with a trailing zero source
      // If 1, we trim ALL trailing /0... If 0 (disabled) or 2 (taxonomy term only), then a 200 response should be issued.
      array(
        'request' => 'node/1/0',
        'return-code' => $settings['trailing_zero'] == 1 ? 301 : 200,
        'expected-path' => $settings['trailing_zero'] == 1 ? 'test-node' : 'node/1/0',
      ),

      // Trailing Zero Check --- This may or may not redirect, depending on the state of trailing_zero, as we're requesting an aliased path with a trailing zero source
      // If not disabled, then we should trim from taxonomy/term/1/0
      array(
        'request' => 'taxonomy/term/' . $this->gr_term->tid . '/0',
        'return-code' => $settings['trailing_zero'] > 0 ? 301 : 200,
        'expected-path' => $settings['trailing_zero'] > 0 ? 'test-term' : 'taxonomy/term/' .  $this->gr_term->tid . '/0',
      ),

      // Regression test for http://drupal.org/node/867654 - term 10 does not exist
      array(
        'request' => 'taxonomy/term/10/0',
        'return-code' => $settings['trailing_zero'] > 0 ? 301 : 404, // Term 10 does not exist in testing.
        'expected-path' => $settings['trailing_zero'] > 0 ? 'taxonomy/term/10' : 'taxonomy/term/10/0',
      ),

      // Regression test for http://drupal.org/node/867654 - term 10 does not exist
      array(
        'request' => 'admin',
        'return-code' => $settings['ignore_admin_path'] > 0 ? 200 : 301, // Ignoring the admin path means no src=>alias redirecting.
        'expected-path' => $settings['ignore_admin_path'] > 0 ? 'admin' : 'administration',
      ),
    );
  }


  protected function _globalredirect_batch_test() {
    // Define the default path options
    $path_defaults = array(
      'options' => array()
    );
    $path_options_defaults = array(
      'base_url' => $GLOBALS['base_url'] . base_path(),
      'absolute' => TRUE,
      'alias' => TRUE,
      'external' => TRUE,
    );

    // Get the test paths
    $test_paths = $this->_globalredirect_test_paths();
    $this->assert('pass', '<pre>' . print_r($test_paths, TRUE) . '</pre>');

    // Foreach of the above, lets check they redirect correctly
    foreach ($test_paths as $id => $path) {
      // Overlay some defaults onto the path. This ensures 'options' is set as an array.
      $path += $path_defaults;

      // If the path doesn't have an absolute or alias setting, set them to TRUE.
      $path['options'] += $path_options_defaults;

      // Build a URL from the path
      $request_path = $path['options']['external'] ? ($path['options']['base_url'] . $path['request']) : $path['request'];
      $request_path = url($request_path, $path['options']);


      // Display a message telling the user what we're testing
      $info = array(
        '!id' => $id,
        '!path' => '<pre>'. print_r($path, TRUE) .'</pre>',
        '!url' => $request_path,
      );
      $this->pass(t(INFO_MESSAGE, $info), 'GlobalRedirect');


      // Do a HEAD request (don't care about the body). The alias=>TRUE is to tell Drupal not to lookup the alias - this is a raw request.
      $this->drupalHead($request_path, array('alias' => TRUE));


      // Grab the headers from the request
      $headers = $this->drupalGetHeaders(TRUE);


      // Build a nice array of results
      $result = array(
        '!expected_status' => $path['return-code'],
        '!location' => isset($headers[0]['location']) ? $headers[0]['location'] : 'N/A',
        '!status' => $headers[0][':status'],
      );


      // If the expected result is not a redirect, then there is no expected path in the location header.
      if ($path['return-code'] != 301) {
        $result['!expected_path'] = 'N/A';
      }
      else {
        $url_options = array('absolute' => TRUE);

        if (isset($path['expected-path'])) {
          // If we have an expected path provided, use this and tell url() not to do an alias lookup
          $expected = $path['expected-path'];
          $url_options['alias'] = TRUE;
        }
        else {
          // Otherwise set the path to the request and let url() do an alias lookup.
          $expected = $path['request'];
        }
        $result['!expected_path'] = url($expected, $url_options);
      }


      // First test - is the status as expected? (Note: The expected status must be cast to string for strpos to work)
      if (strpos($result['!status'], (string)$result['!expected_status']) !== FALSE) {
        // Ok, we have a status and the status contains the appropriate response code (eg, 200, 301, 403 or 404).

        // Next test (if expected return code is 301) - is the location set, and is it as expected?
        if ($result['!expected_status'] == 301 && $result['!location'] == $result['!expected_path']) {
          // We have redirect and ended up in the right place - a PASS!!!
          $this->pass(t(SUCCESS_MESSAGE, $result), 'GlobalRedirect');
        }
        elseif ($result['!expected_status'] != 301) {
          // We weren't supposed to redirect - this is good!
          $this->pass(t(SUCCESS_MESSAGE, $result), 'GlobalRedirect');
        }
        else {
          // In this case either the return-code or the returned location is unexpected
          $this->fail(t(ERROR_MESSAGE, $result), 'GlobalRedirect');
          $this->fail('<pre>' . print_r($headers, TRUE) . '</pre>');
        }
      }
      else {
        // The status either wasn't present or was not as expected
        $this->fail(t(ERROR_MESSAGE, $result), 'GlobalRedirect');
        $this->fail('<pre>' . print_r($headers, TRUE) . '</pre>');
      }
    }
  }
}
