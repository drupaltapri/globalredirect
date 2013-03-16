<?php
// TODO - If Menu Check AND Trailing Zero enabled, there is no 301 to get rid of /0 off a non-existant term (as the menu check fails).
// This why they are separated out into to test suites.
namespace Drupal\globalredirect\Tests;

use Drupal\globalredirect\Tests\GlobalRedirectTest;

class GlobalRedirectTestConfigLanguages extends GlobalRedirectTest {
  public static function getInfo() {
    return array(
      'name' => '4. Global Redirect - Languages',
      'description' => 'Ensure that Global Redirect functions correctly when locales are used',
      'group' => 'Global Redirect',
    );
  }

  protected function _globalredirect_test_paths() {
    $settings = _globalredirect_get_settings();

    $paths = parent::_globalredirect_test_paths();

    // "node/1" has been defined as having an alias ("test-node") and Language NONE. Should 301 redirect to the alias. --- Test for source path request on aliased path
    $paths[] = array(
      'request' => 'fr/node/1',
      'return-code' => 301,
      'expected-path' => 'fr/test-node',
    );

    // "node/2" is english - should plainly redirect to an alias
    $paths[] = array(
      'request' => 'node/2',
      'return-code' => 301,
      'expected-path' => 'test-english-node',
    );

    // Node 3 is french. As no language prefix is provided, should redirect to the english version
    $paths[] = array(
      'request' => 'node/3',
      'return-code' => 301,
      'expected-path' => 'test-english-node',
    );

    // Now we're requesting a french node using the french language - redirect to the nodes alias with french prefix
    $paths[] = array(
      'request' => 'fr/node/3',
      'return-code' => 301,
      'expected-path' => 'fr/test-french-node',
    );

    // Node 4 is german - requesting under french prefix. Redirect to french node
    $paths[] = array(
      'request' => 'fr/node/4',
      'return-code' => 301,
      'expected-path' => 'fr/test-french-node',
    );

    // Node 3 is french, requesting with german prefix. Should redirect to the german node with german prefix.
    $paths[] = array(
      'request' => 'de/node/3',
      'return-code' => 301,
      'expected-path' => 'de/test-german-node',
    );

    // Requesting to edit the french node on the english site. Should redirect to the english node edit
    $paths[] = array(
      'request' => 'node/3/edit',
      'return-code' => 301,
      'expected-path' => 'node/2/edit',
    );

    // Requesting to edit the english node on the french site - should redirect to edit the french node
    $paths[] = array(
      'request' => 'fr/node/2/edit',
      'return-code' => 301,
      'expected-path' => 'fr/node/3/edit',
    );

    // Requesting to edit the german node on the german site. Should return a 200...
    $paths[] = array(
      'request' => 'de/node/4/edit',
      'return-code' => 200,
    );

    return $paths;
  }


  function setUp(array $modules = array()) {
    parent::setUp(array('locale', 'translation'));

    $this->admin_user = $this->drupalCreateUser(array('bypass node access', 'administer nodes', 'administer languages', 'administer content types', 'administer blocks', 'access administration pages'));
    $this->drupalLogin($this->admin_user);

    // Force each lang code to have a prefix.
    foreach (array('en', 'fr', 'de') as $langcode) {
      $prefix = '';
      if ($langcode != 'en') {
        $this->addLanguage($langcode);
        $prefix = $langcode;
      }

      $edit = array('prefix' => $prefix);
      $this->drupalPost('admin/config/regional/language/edit/'. $langcode, $edit, t('Save language'));
      // TODO: There doesn't seem to be a message to confirm the save was successful!
      //$this->assertRaw(t('The configuration options have been saved.'), t('URL language part set to prefix.'));
    }


    $this->drupalGet('admin/structure/types/manage/page');
    $edit = array();
    $edit['language_content_type'] = 2;
    $this->drupalPost('admin/structure/types/manage/page', $edit, t('Save content type'));
    $this->assertRaw(t('The content type %type has been updated.', array('%type' => 'Basic page')), t('Basic page content type has been updated.'));

    // Enable URL language detection and selection
    $edit = array('language[enabled][locale-url]' => TRUE);
    $this->drupalPost('admin/config/regional/language/configure', $edit, t('Save settings'));
    $this->assertRaw(t('Language negotiation configuration saved.'), t('URL language detection enabled.'));
    drupal_static_reset('locale_url_outbound_alter');

    // Ensure the URL part is set to prefix
    $edit = array('locale_language_negotiation_url_part' => 0);
    $this->drupalPost('admin/config/regional/language/configure/url', $edit, t('Save configuration'));
    $this->assertRaw(t('The configuration options have been saved.'), t('URL language part set to prefix.'));


    // Create a dummy english node
    // This is Node 2 (node 1 is in the parent::setUp())
    $node = array(
      'type' => 'page',
      'title' => 'Test English Page Node',
      'path' => array('alias' => 'test-english-node'),
      'language' => 'en',
      'tnid' => 2,
      'body' => array('en' => array(array())),
    );

    // Save the node
    $node = $this->drupalCreateNode($node);


    // Create a translation of the english node, tp French
    // This is Node 3
    $node = array(
      'type' => 'page',
      'title' => 'Test French Page Node',
      'path' => array('alias' => 'test-french-node'),
      'language' => 'fr',
      'tnid' => 2,
      'body' => array('fr' => array(array())),
    );

    // Save the node
    $node = $this->drupalCreateNode($node);



    // Create another translation of the english node, to German
    // This is Node 4
    $node = array(
      'type' => 'page',
      'title' => 'Test German Page Node',
      'path' => array('alias' => 'test-german-node'),
      'language' => 'de',
      'tnid' => 2,
      'body' => array('de' => array(array())),
    );

    // Save the node
    $node = $this->drupalCreateNode($node);
  }



  function testGlobalRedirect() {
    variable_set('globalredirect_settings', array(
      'language_redirect' => 1,
    ));


    $this->_globalredirect_batch_test();
  }


  /**
   * NOTE: Borrowed from translation.test
   * Install a the specified language if it has not been already. Otherwise make sure that
   * the language is enabled.
   *
   * @param $language_code
   *   The language code the check.
   */
  function addLanguage($language_code) {
    // Check to make sure that language has not already been installed.
    $this->drupalGet('admin/config/regional/language');

    if (strpos($this->drupalGetContent(), 'enabled[' . $language_code . ']') === FALSE) {
      // Doesn't have language installed so add it.
      $edit = array();
      $edit['langcode'] = $language_code;
      $this->drupalPost('admin/config/regional/language/add', $edit, t('Add language'));

      // Make sure we are not using a stale list.
      drupal_static_reset('language_list');
      $languages = language_list('language');
      $this->assertTrue(array_key_exists($language_code, $languages), t('Language was installed successfully.'));

      if (array_key_exists($language_code, $languages)) {
        $this->assertRaw(t('The language %language has been created and can now be used. More information is available on the <a href="@locale-help">help screen</a>.', array('%language' => $languages[$language_code]->name, '@locale-help' => url('admin/help/locale'))), t('Language has been created.'));
      }
    }
    elseif ($this->xpath('//input[@type="checkbox" and @name=:name and @checked="checked"]', array(':name' => 'enabled[' . $language_code . ']'))) {
      // It's installed and enabled. No need to do anything.
      $this->assertTrue(true, 'Language [' . $language_code . '] already installed and enabled.');
    }
    else {
      // It's installed but not enabled. Enable it.
      $this->assertTrue(true, 'Language [' . $language_code . '] already installed.');
      $this->drupalPost(NULL, array('enabled[' . $language_code . ']' => TRUE), t('Save configuration'));
      $this->assertRaw(t('Configuration saved.'), t('Language successfully enabled.'));
    }
  }
}
