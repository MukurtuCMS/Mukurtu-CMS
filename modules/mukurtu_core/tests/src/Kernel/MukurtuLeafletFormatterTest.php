<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that rendering a mukurtu_leaflet_formatter field never mutates the
 * entity's own live field data.
 *
 * MukurtuLeafletFormatter::viewElements() splits a single multi-feature
 * GeoJSON FeatureCollection value into one array entry per feature, purely
 * so each point can get its own popup. It must do this on a detached copy -
 * $items (a FieldItemListInterface) is the actual object attached to the
 * entity, not a copy, so mutating it directly would corrupt the entity's
 * real field value in memory for the life of that PHP object. Since
 * geofield cardinality is 1 on real content, if that same (now
 * multi-delta) entity object were later reloaded from Drupal's per-request
 * entity cache and saved, everything past delta 0 would be silently
 * truncated on save.
 */
#[Group('mukurtu_core')]
class MukurtuLeafletFormatterTest extends KernelTestBase {

  /**
   * Contrib leaflet's formatter settings schema (leaflet_popup.value,
   * geocoder.settings.set_marker, etc.) is incomplete - unrelated to the
   * behavior under test here.
   *
   * {@inheritdoc}
   */
  protected $strictConfigSchema = FALSE;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_test',
    'field',
    'file',
    'geofield',
    'image',
    'leaflet',
    'media',
    'mukurtu_core',
    'node',
    'system',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('entity_test');
    $this->installEntitySchema('user');

    FieldStorageConfig::create([
      'field_name' => 'field_coverage',
      'entity_type' => 'entity_test',
      'type' => 'geofield',
      'cardinality' => 1,
    ])->save();

    FieldConfig::create([
      'field_name' => 'field_coverage',
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
    ])->save();

    \Drupal::service('entity_display.repository')
      ->getViewDisplay('entity_test', 'entity_test', 'default')
      ->setComponent('field_coverage', ['type' => 'mukurtu_leaflet_formatter'])
      ->save();
  }

  /**
   * Rendering a multi-feature value must not mutate the entity's own field.
   */
  public function testRenderingDoesNotMutateEntityFieldData(): void {
    $feature_collection = json_encode([
      'type' => 'FeatureCollection',
      'features' => [
        ['type' => 'Feature', 'properties' => [], 'geometry' => ['type' => 'Point', 'coordinates' => [-117.16, 46.73]]],
        ['type' => 'Feature', 'properties' => [], 'geometry' => ['type' => 'Point', 'coordinates' => [-122.33, 47.60]]],
        ['type' => 'Feature', 'properties' => [], 'geometry' => ['type' => 'Point', 'coordinates' => [-73.99, 40.73]]],
      ],
    ]);

    $entity = EntityTest::create([
      'name' => $this->randomString(),
      'field_coverage' => ['value' => $feature_collection],
    ]);
    $entity->save();

    $this->assertCount(1, $entity->get('field_coverage')->getValue(), 'Starts as a single delta holding the whole FeatureCollection.');

    $view_builder = \Drupal::entityTypeManager()->getViewBuilder('entity_test');
    $build = $view_builder->view($entity, 'default');
    \Drupal::service('renderer')->renderRoot($build);

    // The formatter is expected to split the value into 3 deltas for
    // rendering (one per feature, so each can carry its own popup) - but
    // only on a render-time copy. The entity's own live field data must be
    // completely unaffected by having been rendered.
    $this->assertCount(
      1,
      $entity->get('field_coverage')->getValue(),
      'Rendering must not mutate the entity\'s own field_coverage - it should still be exactly the single delta it started as.',
    );
    $this->assertStringContainsString(
      '"Feature"',
      $entity->get('field_coverage')->getValue()[0]['value'],
    );
    $this->assertEquals(
      3,
      substr_count($entity->get('field_coverage')->getValue()[0]['value'], '"Feature"'),
      'All 3 features must still be present in the single delta.',
    );

    // Reloading and re-saving the same object after rendering (mirroring
    // the real bug: the moderation "quick publish" review-panel form runs
    // in the same request as the page's own view builder, then reloads the
    // node from Drupal's per-request entity cache and saves it) must not
    // lose any data either.
    $reloaded = \Drupal::entityTypeManager()->getStorage('entity_test')->load($entity->id());
    $reloaded->save();
    $after_save = \Drupal::entityTypeManager()->getStorage('entity_test')->loadUnchanged($entity->id());
    $this->assertCount(1, $after_save->get('field_coverage')->getValue());
    $this->assertEquals(
      3,
      substr_count($after_save->get('field_coverage')->getValue()[0]['value'], '"Feature"'),
      'All 3 features must survive a render-then-save cycle within the same request.',
    );
  }

}
