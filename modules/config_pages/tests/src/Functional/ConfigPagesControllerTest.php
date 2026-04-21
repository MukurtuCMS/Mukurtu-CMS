<?php

namespace Drupal\Tests\config_pages\Functional;

use Drupal\config_pages\Entity\ConfigPages;
use Drupal\config_pages\Entity\ConfigPagesType;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Functional tests for ConfigPagesController.
 *
 * @group config_pages
 * @coversDefaultClass \Drupal\config_pages\Controller\ConfigPagesController
 */
#[RunTestsInSeparateProcesses]
class ConfigPagesControllerTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['config_pages', 'field', 'text'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The config page type.
   *
   * @var \Drupal\config_pages\Entity\ConfigPagesType
   */
  protected $configPageType;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->configPageType = ConfigPagesType::create([
      'id' => 'test_controller',
      'label' => 'Test Controller Type',
      'context' => [
        'show_warning' => FALSE,
        'group' => [],
      ],
      'menu' => [
        'path' => '/admin/config/test-controller',
        'weight' => 0,
        'description' => '',
      ],
      'token' => FALSE,
    ]);
    $this->configPageType->save();

    $this->container->get('router.builder')->rebuild();
  }

  /**
   * Tests classInit renders the config page form.
   *
   * @covers ::classInit
   */
  public function testClassInitRendersForm(): void {
    $user = $this->drupalCreateUser([
      'edit config_pages entity',
      'edit test_controller config page entity',
    ]);
    $this->drupalLogin($user);

    $this->drupalGet('/admin/config/test-controller');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->buttonExists('Save');
  }

  /**
   * Tests getPageTitle returns correct page title.
   *
   * @covers ::getPageTitle
   */
  public function testPageTitle(): void {
    $user = $this->drupalCreateUser([
      'edit config_pages entity',
      'edit test_controller config page entity',
    ]);
    $this->drupalLogin($user);

    $this->drupalGet('/admin/config/test-controller');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementTextContains('css', 'title', 'Test Controller Type');
  }

  /**
   * Tests classInit loads existing entity instead of creating new.
   *
   * @covers ::classInit
   */
  public function testClassInitLoadsExistingEntity(): void {
    $configPage = ConfigPages::create([
      'type' => 'test_controller',
      'label' => 'Existing Page',
      'context' => serialize([]),
    ]);
    $configPage->save();

    $user = $this->drupalCreateUser([
      'edit config_pages entity',
      'edit test_controller config page entity',
    ]);
    $this->drupalLogin($user);

    $this->drupalGet('/admin/config/test-controller');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->buttonExists('Save');
  }

  /**
   * Tests that default path works when no custom menu path is set.
   *
   * @covers ::classInit
   */
  public function testDefaultPathWithoutMenuPath(): void {
    $type = ConfigPagesType::create([
      'id' => 'no_menu_path',
      'label' => 'No Menu Path Type',
      'context' => [
        'show_warning' => FALSE,
        'group' => [],
      ],
      'menu' => [
        'path' => '',
        'weight' => 0,
        'description' => '',
      ],
      'token' => FALSE,
    ]);
    $type->save();

    $this->container->get('router.builder')->rebuild();

    $user = $this->drupalCreateUser([
      'edit config_pages entity',
      'edit no_menu_path config page entity',
    ]);
    $this->drupalLogin($user);

    $this->drupalGet('/admin/structure/config_pages/no_menu_path');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->buttonExists('Save');
  }

  /**
   * Tests clear confirmation page is accessible.
   *
   * @covers ::clearConfirmation
   */
  public function testClearConfirmationPage(): void {
    $configPage = ConfigPages::create([
      'type' => 'test_controller',
      'label' => 'Test Controller Type',
      'context' => serialize([]),
    ]);
    $configPage->save();

    $user = $this->drupalCreateUser([
      'access config_pages clear values option',
    ]);
    $this->drupalLogin($user);

    $this->drupalGet('admin/structure/config_pages/' . $configPage->id() . '/confirmPurge');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('clear');
  }

  /**
   * Tests import confirmation page is accessible.
   *
   * @covers ::importConfirmation
   */
  public function testImportConfirmationPage(): void {
    $targetPage = ConfigPages::create([
      'type' => 'test_controller',
      'label' => 'Test Controller Type',
      'context' => serialize([]),
    ]);
    $targetPage->save();

    $sourcePage = ConfigPages::create([
      'type' => 'test_controller',
      'label' => 'Source Page',
      'context' => serialize([['ctx' => 'source']]),
    ]);
    $sourcePage->save();

    $user = $this->drupalCreateUser([
      'context import config_pages entity',
    ]);
    $this->drupalLogin($user);

    $this->drupalGet('admin/structure/config_pages/' . $targetPage->id() . '/confirmImport/' . $sourcePage->id());
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('import');
  }

}
