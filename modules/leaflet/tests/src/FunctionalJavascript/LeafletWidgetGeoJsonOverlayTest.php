<?php

namespace Drupal\Tests\leaflet\FunctionalJavascript;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\NodeType;

/**
 * Tests the leaflet widget with GeoJSON overlays functionality.
 *
 * @group leaflet
 */
class LeafletWidgetGeoJsonOverlayTest extends WebDriverTestBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'field',
    'field_ui',
    'user',
    'views',
    'system',
    'geofield',
    'leaflet',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * A test user with administrative privileges.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * The name of the created content type.
   *
   * @var string
   */
  protected $contentTypeName = 'geolocation_test';

  /**
   * The machine name of the created geofield.
   *
   * @var string
   */
  protected $geoFieldName = 'field_geofield';

  /**
   * The machine name of the created geojson field.
   *
   * @var string
   */
  protected $geoJsonFieldName = 'field_geojson';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create content type.
    $this->createContentType();

    // Create admin user.
    $this->adminUser = $this->drupalCreateUser([
      'access administration pages',
      'administer site configuration',
      'administer content types',
      'administer node fields',
      'administer node form display',
      'administer nodes',
      'bypass node access',
    ]);

    $this->drupalLogin($this->adminUser);
  }

  /**
   * Creates a content type for testing.
   */
  protected function createContentType(array $values = []) {
    NodeType::create([
      'type' => $this->contentTypeName,
      'name' => 'GeoLocation Test Content Type',
    ])->save();

    // Create a geofield.
    FieldStorageConfig::create([
      'field_name' => $this->geoFieldName,
      'entity_type' => 'node',
      'type' => 'geofield',
    ])->save();

    FieldConfig::create([
      'field_name' => $this->geoFieldName,
      'entity_type' => 'node',
      'bundle' => $this->contentTypeName,
      'label' => 'GeoField',
    ])->save();

    // Create a string_long field to store geojson data.
    FieldStorageConfig::create([
      'field_name' => $this->geoJsonFieldName,
      'entity_type' => 'node',
      'type' => 'string_long',
    ])->save();

    FieldConfig::create([
      'field_name' => $this->geoJsonFieldName,
      'entity_type' => 'node',
      'bundle' => $this->contentTypeName,
      'label' => 'GeoJSON Data',
    ])->save();

    // Set form display.
    \Drupal::service('entity_display.repository')
      ->getFormDisplay('node', $this->contentTypeName, 'default')
      ->setComponent($this->geoFieldName, [
        'type' => 'leaflet_widget_default',
        'settings' => [],
      ])
      ->setComponent($this->geoJsonFieldName, [
        'type' => 'string_textarea',
        'settings' => [
          'rows' => '5',
          'placeholder' => '',
        ],
      ])
      ->save();
  }

  /**
   * Tests the geojson overlay settings form is correctly displayed.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   * @throws \Behat\Mink\Exception\ResponseTextException
   */
  public function testGeoJsonOverlaySettingsForm() {
    $this->drupalLogin($this->adminUser);
    // Go to the field settings form.
    $this->drupalGet("admin/structure/types/manage/{$this->contentTypeName}/form-display");

    // Click the settings edit button for the geofield.
    $this->getSession()->getPage()->find('css', '[name="' . $this->geoFieldName . '_settings_edit"]')->click();
    $this->assertSession()->waitForElementVisible('css', '.js-form-item-fields-' . $this->geoFieldName . '-settings-edit-form-settings-geojson-overlays-sources-fields');

    // Check that geojson overlay settings are present.
    $this->assertSession()->pageTextContains('Map (GeoJSON) Overlays');
    $this->assertSession()->pageTextContains('Sources');
    $this->assertSession()->pageTextContains('Fields');

    // Check available field options.
    $this->assertSession()->fieldExists('fields[' . $this->geoFieldName . '][settings_edit_form][settings][geojson_overlays][sources][fields][]');
    $this->assertSession()->optionExists('fields[' . $this->geoFieldName . '][settings_edit_form][settings][geojson_overlays][sources][fields][]', $this->geoJsonFieldName);

    // Check other overlay settings.
    $this->assertSession()->fieldExists('fields[' . $this->geoFieldName . '][settings_edit_form][settings][geojson_overlays][path]');
    $this->assertSession()->fieldExists('fields[' . $this->geoFieldName . '][settings_edit_form][settings][geojson_overlays][zoom_to_geojson]');
    $this->assertSession()->fieldExists('fields[' . $this->geoFieldName . '][settings_edit_form][settings][geojson_overlays][snapping]');
  }

  /**
   * Tests the geojson overlay settings are properly saved.
   */
  public function testGeoJsonOverlaySettingsSaving() {
    // Go to the field settings form.
    $this->drupalGet("admin/structure/types/manage/{$this->contentTypeName}/form-display");
    $this->getSession()->getPage()->find('css', '[name="' . $this->geoFieldName . '_settings_edit"]')->click();
    $this->assertSession()->waitForElementVisible('css', '.js-form-item-fields-' . $this->geoFieldName . '-settings-edit-form-settings-geojson-overlays-sources-fields');

    // Configure the geojson overlay settings.
    $edit = [
      'fields[' . $this->geoFieldName . '][settings_edit_form][settings][geojson_overlays][sources][fields][]' => [$this->geoJsonFieldName],
      'fields[' . $this->geoFieldName . '][settings_edit_form][settings][geojson_overlays][path]' => '{"color":"#ff0000","opacity":"0.8","weight":2,"fillColor":"#ffff00","fillOpacity":"0.5"}',
      'fields[' . $this->geoFieldName . '][settings_edit_form][settings][geojson_overlays][zoom_to_geojson]' => TRUE,
      'fields[' . $this->geoFieldName . '][settings_edit_form][settings][geojson_overlays][snapping]' => TRUE,
    ];

    // Submit the settings form.
    $this->submitForm($edit, 'Update');
    $this->assertSession()->waitForElementVisible('css', '[name="' . $this->geoFieldName . '_settings_edit"]');
    $this->submitForm([], 'Save');

    // Check that settings were saved.
    $this->drupalGet("admin/structure/types/manage/{$this->contentTypeName}/form-display");
    $this->getSession()->getPage()->find('css', '[name="' . $this->geoFieldName . '_settings_edit"]')->click();
    $this->assertSession()->waitForElementVisible('css', '.js-form-item-fields-' . $this->geoFieldName . '-settings-edit-form-settings-geojson-overlays-sources-fields');

    // Verify selected field.
    $this->assertSession()->elementExists('css', 'select[name="fields[' . $this->geoFieldName . '][settings_edit_form][settings][geojson_overlays][sources][fields][]"] option[value="' . $this->geoJsonFieldName . '"][selected="selected"]');

    // Verify custom path settings.
    $this->assertSession()->fieldValueEquals(
      'fields[' . $this->geoFieldName . '][settings_edit_form][settings][geojson_overlays][path]',
      '{"color":"#ff0000","opacity":"0.8","weight":2,"fillColor":"#ffff00","fillOpacity":"0.5"}'
    );

    // Verify checkbox states.
    $this->assertSession()->checkboxChecked('fields[' . $this->geoFieldName . '][settings_edit_form][settings][geojson_overlays][zoom_to_geojson]');
    $this->assertSession()->checkboxChecked('fields[' . $this->geoFieldName . '][settings_edit_form][settings][geojson_overlays][snapping]');
  }

  /**
   * Tests the geojson overlay functionality on node form.
   */
  public function testGeoJsonOverlayNodeForm() {
    // Configure the geojson overlay settings first.
    $this->drupalGet("admin/structure/types/manage/{$this->contentTypeName}/form-display");
    $this->getSession()->getPage()->find('css', '[name="' . $this->geoFieldName . '_settings_edit"]')->click();
    $this->assertSession()->waitForElementVisible('css', '.js-form-item-fields-' . $this->geoFieldName . '-settings-edit-form-settings-geojson-overlays-sources-fields');

    $edit = [
      'fields[' . $this->geoFieldName . '][settings_edit_form][settings][geojson_overlays][sources][fields][]' => [$this->geoJsonFieldName],
      'fields[' . $this->geoFieldName . '][settings_edit_form][settings][geojson_overlays][path]' => '{"color":"#ff0000","opacity":"0.8","weight":2,"fillColor":"#ffff00","fillOpacity":"0.5"}',
      'fields[' . $this->geoFieldName . '][settings_edit_form][settings][geojson_overlays][zoom_to_geojson]' => TRUE,
      'fields[' . $this->geoFieldName . '][settings_edit_form][settings][geojson_overlays][snapping]' => TRUE,
    ];

    $this->submitForm($edit, 'Update');
    $this->assertSession()->waitForElementVisible('css', '[name="' . $this->geoFieldName . '_settings_edit"]');
    $this->submitForm([], 'Save');

    // Create a sample GeoJSON data.
    $sample_geojson = '{"type":"FeatureCollection","features":[{"type":"Feature","properties":{},"geometry":{"type":"Polygon","coordinates":[[[10.0,10.0],[10.0,20.0],[20.0,20.0],[20.0,10.0],[10.0,10.0]]]}}]}';

    // Create a node with geojson data.
    $this->drupalGet("node/add/{$this->contentTypeName}");

    $this->submitForm([
      'title[0][value]' => 'Test GeoJSON Overlay',
      $this->geoJsonFieldName . '[0][value]' => $sample_geojson,
    ], 'Save');

    // Check that node was created.
    $this->assertSession()->pageTextContains('GeoLocation Test Content Type Test GeoJSON Overlay has been created.');

    // Edit the node to see if the geojson overlay is loaded.
    $node_id = \Drupal::entityQuery('node')
      ->condition('title', 'Test GeoJSON Overlay')
      ->accessCheck(FALSE)
      ->execute();
    $node_id = reset($node_id);

    $this->drupalGet("node/{$node_id}/edit");

    // Check if the leaflet map container is present.
    $this->assertSession()->elementExists('css', '.leaflet-container');

    // The actual overlay is loaded via JavaScript, so we can only verify the
    // configuration is passed to drupalSettings.
    $this->assertSession()->responseContains('"leaflet_widget":{"map_id":"');
    $this->assertSession()->responseContains('"geojsonFieldOverlay":{"sources":{"fields":["' . $this->geoJsonFieldName . '"]');
    $this->assertSession()->responseContains('"path":"{\u0022color\u0022:\u0022#ff0000\u0022,\u0022opacity\u0022:\u00220.8\u0022,\u0022weight\u0022:2,\u0022fillColor\u0022:\u0022#ffff00\u0022,\u0022fillOpacity\u0022:\u00220.5\u0022}"');
    $this->assertSession()->responseContains('"zoom_to_geojson":true');
    $this->assertSession()->responseContains('"snapping":true');
  }

}
