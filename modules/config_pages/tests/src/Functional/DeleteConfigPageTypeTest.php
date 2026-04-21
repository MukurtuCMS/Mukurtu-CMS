<?php

namespace Drupal\Tests\config_pages\Functional;

use Drupal\config_pages\Entity\ConfigPagesType;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that the ConfigPages Type can be created.
 *
 * @group config_pages
 */
#[RunTestsInSeparateProcesses]
class DeleteConfigPageTypeTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['config_pages'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $configPageType = ConfigPagesType::create([
      'id' => 'config_pages_test_type',
      'label' => 'ConfigPages Test Type Label',
      'context' => [
        'show_warning' => '',
        'group' => [],
      ],
      'menu' => [
        'path' => '/admin/config_pages_test/test_page',
        'weight' => 0,
        'description' => 'Test page for ConfigPages module.',
      ],
      'token' => FALSE,
    ]);

    $configPageType->save();
    $this->container->get('router.builder')->rebuild();
  }

  /**
   * Check if Config page type was created successfully.
   */
  public function testConfigPagesTypeCreate() {
    $account = $this->drupalCreateUser(['administer config_pages types']);
    $this->drupalLogin($account);

    $p = $this->drupalGet('admin/structure/config_pages/types');
    $this->assertSession()->statusCodeEquals(200);
    $this->clickLink('Delete');
    $this->assertSession()->statusCodeEquals(200);
    $this->click('#edit-submit');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('The config page type ConfigPages Test Type Label has been deleted');
  }

}
