<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\Core\Render\Element;
use Drupal\KernelTests\KernelTestBase;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\mukurtu_core\Plugin\Field\FieldFormatter\MukurtuLeafletFormatter;
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

  /**
   * Popups and marker names are set when the parent bubbles cache metadata.
   *
   * Since leaflet 10.4.13, LeafletDefaultFormatter::viewElements() merges
   * token bubbleable metadata into its return value whenever popups are on,
   * so the array holds '#cache' and '#attached' keys next to the map
   * elements. The shipped full view displays turn popups on, so this mirrors
   * those settings rather than the formatter defaults.
   */
  public function testPopupSettingsWithBubbledMetadata(): void {
    $descriptions = ['Pullman', 'Seattle', 'New York'];
    $coordinates = [[-117.16, 46.73], [-122.33, 47.60], [-73.99, 40.73]];
    $features = [];
    foreach ($descriptions as $i => $description) {
      $features[] = [
        'type' => 'Feature',
        'properties' => ['location_description' => $description],
        'geometry' => ['type' => 'Point', 'coordinates' => $coordinates[$i]],
      ];
    }
    $entity = EntityTest::create([
      'name' => 'Mapped item',
      'field_coverage' => [
        'value' => json_encode(['type' => 'FeatureCollection', 'features' => $features]),
      ],
    ]);
    $entity->save();

    $leaflet_popup = MukurtuLeafletFormatter::defaultSettings()['leaflet_popup'];
    $leaflet_popup['control'] = '1';
    $leaflet_popup['options'] = '{"maxWidth":"300","minWidth":"50","autoPan":true}';
    $items = $entity->get('field_coverage');
    $formatter = \Drupal::service('plugin.manager.field.formatter')->getInstance([
      'field_definition' => $items->getFieldDefinition(),
      'view_mode' => 'default',
      'configuration' => [
        'type' => 'mukurtu_leaflet_formatter',
        'settings' => ['leaflet_popup' => $leaflet_popup],
      ],
    ]);

    $elements = $formatter->viewElements($items, $entity->language()->getId());

    $this->assertArrayHasKey('#cache', $elements, 'Cache metadata bubbled up by the parent formatter must be kept.');
    $this->assertSame([0], Element::children($elements));
    $leaflet_settings = $elements[0]['#attached']['drupalSettings']['leaflet'];
    $map_features = reset($leaflet_settings)['features'];
    $this->assertCount(3, $map_features);
    foreach ($descriptions as $i => $description) {
      $this->assertSame($description, $map_features[$i]['popup']['value']);
      $this->assertNotEmpty($map_features[$i]['title'], 'Each marker needs an accessible name.');
    }
  }

}
