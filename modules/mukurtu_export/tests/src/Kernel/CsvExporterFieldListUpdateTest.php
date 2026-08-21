<?php

declare(strict_types = 1);

namespace Drupal\Tests\mukurtu_export\Kernel;

use Drupal\mukurtu_export\Entity\CsvExporter;
use Drupal\Tests\mukurtu_protocol\Kernel\ProtocolAwareEntityTestBase;

/**
 * Tests mukurtu_export_update_40017()/_40018(), which add the target_id/alt
 * sub-columns for a handful of single-value media reference fields that
 * never had them, then remove the now-redundant bare column now that the
 * sub-columns carry the same data.
 *
 * Extends ProtocolAwareEntityTestBase (with mukurtu_export/mukurtu_
 * multipage_items added) rather than plain KernelTestBase because
 * mukurtu_export's service dependencies (csv_field_export_event_
 * subscriber -> mukurtu_core.paragraph_emptiness_checker, etc.) only
 * resolve once that base's full module list - the same combination
 * CsvExportFieldTestBase already relies on - is enabled.
 *
 * @see mukurtu_export_update_40017()
 * @see mukurtu_export_update_40018()
 */
class CsvExporterFieldListUpdateTest extends ProtocolAwareEntityTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['mukurtu_export', 'mukurtu_multipage_items'];

  /**
   * The hooks operate on hand-seeded fixture config, not the real schema.
   *
   * {@inheritdoc}
   */
  protected $strictConfigSchema = FALSE;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_export');
    require_once $module_path . '/mukurtu_export.install';
  }

  /**
   * Seeds a csv_exporter config with a given entity_fields_export_list.
   */
  private function seedConfig(string $id, array $list): void {
    CsvExporter::create([
      'id' => $id,
      'label' => $id,
      'entity_fields_export_list' => $list,
    ])->save();
  }

  /**
   * Reads back a seeded config's entity_fields_export_list.
   */
  private function getList(string $id): array {
    return \Drupal::config("mukurtu_export.csv_exporter.$id")->get('entity_fields_export_list');
  }

  /**
   * The missing target_id/alt entries are appended when absent.
   */
  public function testSubcolumnsAddedWhenMissing(): void {
    $this->seedConfig('test_exporter', [
      'node__word_list' => ['field_word_list_image' => 'Image'],
    ]);

    mukurtu_export_update_40017();

    $list = $this->getList('test_exporter');
    $this->assertSame('Image > File ID', $list['node__word_list']['field_word_list_image/target_id']);
    $this->assertSame('Image > Alternative text', $list['node__word_list']['field_word_list_image/alt']);
  }

  /**
   * An existing entry for one of the sub-columns is not duplicated.
   */
  public function testSubcolumnsNotDuplicatedWhenPresent(): void {
    $this->seedConfig('test_exporter', [
      'node__word_list' => [
        'field_word_list_image' => 'Image',
        'field_word_list_image/target_id' => 'Custom Label',
      ],
    ]);

    mukurtu_export_update_40017();

    $list = $this->getList('test_exporter');
    $this->assertSame('Custom Label', $list['node__word_list']['field_word_list_image/target_id']);
  }

  /**
   * The redundant bare column is removed when it still matches the known
   * shipped default.
   */
  public function testBareColumnRemovedWhenMatchesDefault(): void {
    $this->seedConfig('test_exporter', [
      'node__word_list' => [
        'field_word_list_image' => 'Image',
        'field_word_list_image/target_id' => 'Image > File ID',
        'field_word_list_image/alt' => 'Image > Alternative text',
      ],
    ]);

    mukurtu_export_update_40018();

    $list = $this->getList('test_exporter');
    $this->assertArrayNotHasKey('field_word_list_image', $list['node__word_list']);
    $this->assertSame('Image > File ID', $list['node__word_list']['field_word_list_image/target_id']);
  }

  /**
   * A site-customized bare column label is left untouched.
   */
  public function testBareColumnLeftAloneWhenCustomized(): void {
    $this->seedConfig('test_exporter', [
      'node__word_list' => ['field_word_list_image' => 'My Custom Image Label'],
    ]);

    mukurtu_export_update_40018();

    $list = $this->getList('test_exporter');
    $this->assertSame('My Custom Image Label', $list['node__word_list']['field_word_list_image']);
  }

  /**
   * A config with no entry for the affected bundle is skipped without
   * error by both hooks.
   */
  public function testHooksSkipUnrelatedConfig(): void {
    $this->seedConfig('test_exporter', [
      'node__collection' => ['title' => 'Title'],
    ]);

    mukurtu_export_update_40017();
    mukurtu_export_update_40018();

    $list = $this->getList('test_exporter');
    $this->assertSame(['title' => 'Title'], $list['node__collection']);
  }

}
