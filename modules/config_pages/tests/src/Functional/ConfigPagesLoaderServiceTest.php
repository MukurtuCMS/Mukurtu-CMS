<?php

namespace Drupal\Tests\config_pages\Functional;

use Drupal\config_pages\ConfigPagesLoaderServiceInterface;
use Drupal\config_pages\Entity\ConfigPages;
use Drupal\config_pages\Entity\ConfigPagesType;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests for ConfigPagesLoaderService.
 *
 * @group config_pages
 */
#[RunTestsInSeparateProcesses]
class ConfigPagesLoaderServiceTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['config_pages', 'field', 'text'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The config_pages loader service.
   */
  protected ConfigPagesLoaderServiceInterface $loaderService;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->loaderService = \Drupal::service('config_pages.loader');

    // Create a config page type.
    $configPageType = ConfigPagesType::create([
      'id' => 'test_config_page',
      'label' => 'Test Config Page',
      'context' => [
        'show_warning' => '',
        'group' => [],
      ],
      'menu' => [
        'path' => '/admin/config/test_config_page',
        'weight' => 0,
        'description' => 'Test config page for loader service test.',
      ],
      'token' => FALSE,
    ]);
    $configPageType->save();

    // Create a text field for the config page.
    $field_storage = FieldStorageConfig::create([
      'entity_type' => 'config_pages',
      'field_name' => 'field_text',
      'type' => 'text',
    ]);
    $field_storage->save();

    $field = FieldConfig::create([
      'field_name' => 'field_text',
      'entity_type' => 'config_pages',
      'bundle' => 'test_config_page',
      'label' => 'Test Text Field',
      'required' => FALSE,
      'translatable' => FALSE,
    ]);
    $field->save();

    $display_repository = \Drupal::service('entity_display.repository');
    $display_repository->getViewDisplay('config_pages', $configPageType->id())
      ->setComponent('field_text', [
        'type' => 'text_default',
        'weight' => 0,
      ])
      ->save();
  }

  /**
   * @covers \Drupal\config_pages\ConfigPagesLoaderService::getFieldView
   */
  public function testGetFieldView() {
    // Create a config page with test data.
    $config_page = ConfigPages::create([
      'type' => 'test_config_page',
      'field_text' => 'Test field content',
      'context' => serialize([]),
    ]);
    $config_page->save();

    // Valid field.
    $result = $this->loaderService->getFieldView($config_page, 'field_text');
    $this->assertIsArray($result);
    $this->assertContains('config_pages:' . $config_page->id(), $result['#cache']['tags']);

    // Non-existing field.
    $result = $this->loaderService->getFieldView('test_config_page', 'field_text_non_existing');
    $this->assertIsArray($result);
    $this->assertContains('config_pages_list:' . $config_page->bundle(), $result['#cache']['tags']);

    // Empty field.
    $config_page->set('field_text', NULL)->save();
    $result = $this->loaderService->getFieldView('test_config_page', 'field_text');
    $this->assertIsArray($result);
    $this->assertContains('config_pages:' . $config_page->id(), $result['#cache']['tags']);
  }

}
