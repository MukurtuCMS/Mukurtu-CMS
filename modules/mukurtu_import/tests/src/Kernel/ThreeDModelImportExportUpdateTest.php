<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_import\Kernel;

use Drupal\Core\Config\FileStorage;
use Drupal\media\Entity\MediaType;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_import_update_40407(), 3D models in import and export (#2810).
 *
 * @see mukurtu_import_update_40407()
 */
#[Group('mukurtu_import')]
class ThreeDModelImportExportUpdateTest extends MukurtuImportTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'mukurtu_export',
    'mukurtu_multipage_items',
  ];

  /**
   * The shipped CSV export settings.
   */
  private const EXPORTERS = [
    'default_local_metadata_only',
    'default_local_with_media',
    'default_external_metadata_only',
    'default_external_with_media',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_import');
    require_once \Drupal::root() . '/' . $module_path . '/mukurtu_import.install';

    // The site as it was before the update: the media type exists (from
    // mukurtu_core_update_40233()), the template and export maps don't.
    MediaType::create([
      'id' => '3d_model',
      'label' => '3D Model',
      'source' => 'file',
      'source_configuration' => ['source_field' => 'field_media_3d_model'],
    ])->save();

    $export_path = \Drupal::service('extension.list.module')->getPath('mukurtu_export');
    $shipped = new FileStorage(\Drupal::root() . '/' . $export_path . '/config/install');
    $storage = \Drupal::entityTypeManager()->getStorage('csv_exporter');
    foreach (self::EXPORTERS as $id) {
      $values = $shipped->read("mukurtu_export.csv_exporter.$id");
      $this->assertArrayHasKey('media__3d_model', $values['entity_fields_export_list'], "Precondition: $id ships the 3D Model map.");
      unset($values['entity_fields_export_list']['media__3d_model'], $values['langcode'], $values['status'], $values['dependencies']);
      $storage->create($values)->save();
    }
  }

  /**
   * Loads an export preset's column map.
   */
  private function map(string $id): array {
    return \Drupal::entityTypeManager()->getStorage('csv_exporter')->loadUnchanged($id)->get('entity_fields_export_list');
  }

  /**
   * The hook adds the template and every preset's map, once.
   */
  public function testAdds(): void {
    $this->assertSame('Added 3D models to bulk import and export.', mukurtu_import_update_40407());

    $strategy = \Drupal::entityTypeManager()->getStorage('mukurtu_import_strategy')->loadUnchanged('3d_model_all_fields');
    $this->assertNotNull($strategy);
    $this->assertSame('3d_model', $strategy->get('target_bundle'));
    $targets = array_column($strategy->getMapping(), 'target', 'source');
    $this->assertSame('field_media_3d_model', $targets['3D Model']);
    $this->assertSame('field_media_3d_model_usdz', $targets['3D Model for Apple devices']);

    foreach (self::EXPORTERS as $id) {
      $map = $this->map($id);
      $this->assertSame('3D Model', $map['media__3d_model']['field_media_3d_model'] ?? NULL, $id);
      $this->assertSame('3D Model for Apple devices', $map['media__3d_model']['field_media_3d_model_usdz'] ?? NULL, $id);
    }

    $this->assertNull(mukurtu_import_update_40407(), 'A second run changed something.');
  }

  /**
   * A preset without documents, and a site's own template, are left alone.
   */
  public function testKeepsSiteChoices(): void {
    $exporter = \Drupal::entityTypeManager()->getStorage('csv_exporter')->load('default_local_with_media');
    $map = $exporter->get('entity_fields_export_list');
    unset($map['media__document']);
    $exporter->set('entity_fields_export_list', $map)->save();

    $import_path = \Drupal::service('extension.list.module')->getPath('mukurtu_import');
    $values = (new FileStorage(\Drupal::root() . '/' . $import_path . '/config/install'))->read('mukurtu_import.mukurtu_import_strategy.3d_model_all_fields');
    unset($values['langcode'], $values['status'], $values['dependencies']);
    $values['label'] = 'Our 3D models';
    \Drupal::entityTypeManager()->getStorage('mukurtu_import_strategy')->create($values)->save();

    mukurtu_import_update_40407();

    $this->assertArrayNotHasKey('media__3d_model', $this->map('default_local_with_media'));
    $this->assertArrayHasKey('media__3d_model', $this->map('default_local_metadata_only'));
    $this->assertSame('Our 3D models', \Drupal::entityTypeManager()->getStorage('mukurtu_import_strategy')->loadUnchanged('3d_model_all_fields')->label());
  }

  /**
   * Without the 3D Model media type there is nothing to add.
   */
  public function testNoMediaType(): void {
    MediaType::load('3d_model')->delete();

    $this->assertNull(mukurtu_import_update_40407());
    $this->assertNull(\Drupal::entityTypeManager()->getStorage('mukurtu_import_strategy')->loadUnchanged('3d_model_all_fields'));
  }

  /**
   * The update runs after the one that creates the 3D Model media type.
   *
   * Without the declared dependency the two modules' updates aren't linked,
   * and only the modules' names put them in the right order.
   */
  public function testRunsAfterMediaTypeUpdate(): void {
    require_once $this->root . '/core/includes/update.inc';
    // A site from before both updates. Drupal reads hook_update_dependencies()
    // only from modules with a schema version, which kernel tests don't set.
    $core_path = \Drupal::service('extension.list.module')->getPath('mukurtu_core');
    require_once $this->root . '/' . $core_path . '/mukurtu_core.install';
    $registry = \Drupal::service('update.update_hook_registry');
    $registry->setInstalledVersion('mukurtu_core', 40232);
    $registry->setInstalledVersion('mukurtu_import', 40406);

    $graph = update_build_dependency_graph(update_get_update_function_list([
      'mukurtu_core' => 40233,
      'mukurtu_import' => 40407,
    ]));
    $this->assertArrayHasKey('mukurtu_import_update_40407', $graph['mukurtu_core_update_40233']['edges'] ?? []);
  }

}
