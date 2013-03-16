<?php
namespace Drupal\globalredirect\Tests;

use Drupal\globalredirect\Tests\GlobalRedirectTest;

class GlobalRedirectTestConfigBeta extends GlobalRedirectTest {
  public static function getInfo() {
    return array(
      'name' => '3. Global Redirect - Config Beta',
      'description' => 'Ensure that Global Redirect functions correctly. Only enable Menu Checking',
      'group' => 'Global Redirect',
    );
  }

  function testGlobalRedirect() {
    variable_set('globalredirect_settings', array(
      'deslash' => 0,
      'menu_check' => 1,
      'nonclean_to_clean' => 0,
      'case_sensitive_urls' => 0,
      'term_path_handler' => 0,
      'frontpage_redirect' => 0,
      'trailing_zero' => 0,
    ));

    $this->_globalredirect_batch_test();
  }
}
