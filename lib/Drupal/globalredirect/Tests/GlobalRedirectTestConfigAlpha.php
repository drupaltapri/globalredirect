<?php
namespace Drupal\globalredirect\Tests;

use Drupal\globalredirect\Tests\GlobalRedirectTest;

class GlobalRedirectTestConfigAlpha extends GlobalRedirectTest {
  public static function getInfo() {
    return array(
      'name' => '2. Global Redirect - Config Alpha',
      'description' => 'Ensure that Global Redirect functions correctly. Only enable Trailing Zero',
      'group' => 'Global Redirect',
    );
  }

  function testGlobalRedirect() {
    variable_set('globalredirect_settings', array(
      'deslash' => 0,
      'menu_check' => 0,
      'nonclean_to_clean' => 0,
      'case_sensitive_urls' => 0,
      'term_path_handler' => 0,
      'frontpage_redirect' => 0,
      'trailing_zero' => 1,
      'ignore_admin_path' => 0,
    ));

    $this->_globalredirect_batch_test();
  }
}
