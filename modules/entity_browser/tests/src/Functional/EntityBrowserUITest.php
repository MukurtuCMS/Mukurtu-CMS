<?php

namespace Drupal\Tests\entity_browser\Functional;

use Drupal\file\Entity\File;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\TestFileCreationTrait;

/**
 * Tests the entity browser UI.
 *
 * @group entity_browser
 */
class EntityBrowserUITest extends BrowserTestBase {

  use TestFileCreationTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'entity_browser_test',
    'views',
    'block',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests entity browser UI.
   */
  public function testEntityBrowserUI() {
    $account = $this->drupalCreateUser([
      'administer entity browsers',
      'access test_entity_browser_iframe entity browser pages',
    ]);
    $this->drupalLogin($account);
    // Go to the entity browser iframe link.
    $this->drupalGet('/entity-browser/iframe/test_entity_browser_iframe');
    $this->assertSession()->responseContains('Select');
    $this->drupalGet('/admin/config/content/entity_browser/test_entity_browser_iframe/widgets');
    $edit = [
      'table[871dbf77-012e-41cb-b32a-ada353d2de35][form][submit_text]' => 'Different',
    ];
    $this->submitForm($edit, 'Save');
    $this->drupalGet('/entity-browser/iframe/test_entity_browser_iframe');
    $this->assertSession()->responseContains('Different');
  }

  /**
   * Tests entity browser token support for upload widget.
   */
  public function testEntityBrowserToken() {
    $this->container->get('module_installer')->install(['token', 'file']);
    $account = $this->drupalCreateUser([
      'access test_entity_browser_token entity browser pages',
    ]);
    $this->drupalLogin($account);
    // Go to the entity browser iframe link.
    $this->drupalGet('/entity-browser/iframe/test_entity_browser_token');
    $image = current($this->getTestFiles('image'));
    $edit = [
      'files[upload][]' => $this->container->get('file_system')->realpath($image->uri),
    ];
    $this->submitForm($edit, 'Select files');

    $file = File::load(1);
    // Test entity browser token that has upload location configured to
    // public://[current-user:account-name]/.
    $this->assertEquals($file->getFileUri(), 'public://' . $account->getAccountName() . '/' . $file->getFilename(), 'Image has the correct uri.');
  }

}
