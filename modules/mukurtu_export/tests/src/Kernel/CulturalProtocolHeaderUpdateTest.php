<?php

declare(strict_types = 1);

namespace Drupal\Tests\mukurtu_export\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\mukurtu_export\Entity\CsvExporter;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_export_update_40017(), which fixes the Cultural Protocols
 * CSV export headers to match what the importer's auto-mapper expects.
 *
 * @see mukurtu_export_update_40017()
 */
#[Group('mukurtu_export')]
class CulturalProtocolHeaderUpdateTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'mukurtu_export',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_export');
    require_once $module_path . '/mukurtu_export.install';
  }

  /**
   * The update hook rewrites headers still holding the old default label.
   */
  public function testUpdateRewritesDefaultLabels(): void {
    $exporter = CsvExporter::create([
      'id' => 'test_exporter',
      'label' => 'Test Exporter',
      'entity_fields_export_list' => [
        'node__protocol_aware_content' => [
          'field_cultural_protocols/protocols' => 'Protocols',
          'field_cultural_protocols/sharing_setting' => 'Sharing Setting',
        ],
      ],
    ]);
    $exporter->save();

    mukurtu_export_update_40017();

    $exporter = \Drupal::entityTypeManager()->getStorage('csv_exporter')->load('test_exporter');
    $list = $exporter->get('entity_fields_export_list');
    $this->assertEquals('Cultural Protocols > Protocols', $list['node__protocol_aware_content']['field_cultural_protocols/protocols']);
    $this->assertEquals('Cultural Protocols > Sharing Setting', $list['node__protocol_aware_content']['field_cultural_protocols/sharing_setting']);
  }

  /**
   * The update hook leaves a user-customized label untouched.
   */
  public function testUpdateLeavesCustomLabelsUntouched(): void {
    $exporter = CsvExporter::create([
      'id' => 'test_exporter',
      'label' => 'Test Exporter',
      'entity_fields_export_list' => [
        'node__protocol_aware_content' => [
          'field_cultural_protocols/protocols' => 'My Custom Protocols Header',
          'field_cultural_protocols/sharing_setting' => 'Sharing Setting',
        ],
      ],
    ]);
    $exporter->save();

    mukurtu_export_update_40017();

    $exporter = \Drupal::entityTypeManager()->getStorage('csv_exporter')->load('test_exporter');
    $list = $exporter->get('entity_fields_export_list');
    $this->assertEquals('My Custom Protocols Header', $list['node__protocol_aware_content']['field_cultural_protocols/protocols']);
    $this->assertEquals('Cultural Protocols > Sharing Setting', $list['node__protocol_aware_content']['field_cultural_protocols/sharing_setting']);
  }

  /**
   * The update hook is a no-op when there's nothing to rewrite.
   */
  public function testUpdateIsNoOpWithoutMatchingFields(): void {
    $exporter = CsvExporter::create([
      'id' => 'test_exporter',
      'label' => 'Test Exporter',
      'entity_fields_export_list' => [
        'node__protocol_aware_content' => [
          'title' => 'Title',
        ],
      ],
    ]);
    $exporter->save();

    mukurtu_export_update_40017();

    $exporter = \Drupal::entityTypeManager()->getStorage('csv_exporter')->load('test_exporter');
    $list = $exporter->get('entity_fields_export_list');
    $this->assertEquals(['title' => 'Title'], $list['node__protocol_aware_content']);
  }

}
