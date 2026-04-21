<?php

namespace Drupal\Tests\geolocation\FunctionalJavascript;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Tests the Token Formatter functionality.
 *
 * @group geolocation
 */
class GeolocationTokenFormatterTest extends GeolocationJavascriptTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'field',
    'filter',
    'geolocation',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->drupalCreateContentType(['type' => 'article', 'name' => 'Article']);

    // Add the geolocation field to the article content type.
    FieldStorageConfig::create([
      'field_name' => 'field_geolocation',
      'entity_type' => 'node',
      'type' => 'geolocation',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_geolocation',
      'label' => 'Geolocation',
      'entity_type' => 'node',
      'bundle' => 'article',
    ])->save();

    EntityFormDisplay::load('node.article.default')
      ->setComponent('field_geolocation', [
        'type' => 'geolocation_latlng',
      ])
      ->save();

    EntityViewDisplay::load('node.article.default')
      ->setComponent('field_geolocation', [
        'type' => 'geolocation_latlng',
        'weight' => 1,
      ])
      ->save();

    $entity_test_storage = \Drupal::entityTypeManager()->getStorage('node');
    $entity_test_storage->create([
      'id' => 1,
      'title' => 'Test node 1',
      'body' => 'test test',
      'type' => 'article',
      'field_geolocation' => [
        'lat' => 52,
        'lng' => 47,
        'data' => [
          'title' => 'My home',
          // Not used, just to check interference with other values.
          'extraconfig' => 'myvalue',
        ],
      ],
    ])->save();
  }

  /**
   * Tests the token formatter.
   */
  public function testGeocoderTokenizedTestReplacement() {
    $this->drupalGet('node/1');
    $this->assertSession()->responseContains('<span class="geolocation-latlng">52, 47</span>');

    EntityViewDisplay::load('node.article.default')
      ->setComponent('field_geolocation', [
        'type' => 'geolocation_token',
        'settings' => [
          'tokenized_text' => [
            'value' => 'Title: [geolocation_current_item:data:title] Lat/Lng: [geolocation_current_item:lat]/[geolocation_current_item:lng]',
            'format' => filter_default_format(),
          ],
        ],
        'weight' => 1,
      ])
      ->save();

    $this->drupalGet('node/1');
    $this->assertSession()->responseContains('Lat/Lng: 52/47');
    $this->assertSession()->responseContains('Title: My home');
  }

}
