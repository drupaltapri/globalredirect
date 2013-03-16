<?php
namespace Drupal\globalredirect\Tests;

use Drupal\globalredirect\Tests\GlobalRedirectTest;

class GlobalRedirectTestDefault extends GlobalRedirectTest {
  public static function getInfo() {
    return array(
      'name' => '1. Global Redirect - Default Settings',
      'description' => 'Ensure that Global Redirect functions correctly',
      'group' => 'Global Redirect',
    );
  }

  function testGlobalRedirect() {
    variable_set('globalredirect_settings', array(
      'deslash' => 1,
      'menu_check' => 0,
      'nonclean_to_clean' => 1,
      'case_sensitive_urls' => 1,
      'term_path_handler' => 1,
      'frontpage_redirect' => 1,
      'trailing_zero' => 0,
      'ignore_admin_path' => 1,
    ));
    $this->_globalredirect_batch_test();
  }
}
