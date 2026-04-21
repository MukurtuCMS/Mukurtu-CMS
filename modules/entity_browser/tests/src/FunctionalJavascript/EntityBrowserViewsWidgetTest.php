<?php

namespace Drupal\Tests\entity_browser\FunctionalJavascript;

use Drupal\file\Entity\File;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\entity_browser\Element\EntityBrowserElement;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Entity Browser views widget tests.
 *
 * @group entity_browser
 * @see \Drupal\entity_browser\Plugin\EntityBrowser\Widget\View
 */
class EntityBrowserViewsWidgetTest extends EntityBrowserWebDriverTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'node',
    'views',
    'entity_browser_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $user = $this->drupalCreateUser([
      'access test_entity_browser_file entity browser pages',
      'access test_double_underscore entity browser pages',
      'bypass node access',
    ]);
    $this->drupalLogin($user);
  }

  /**
   * Tests Entity Browser views widget.
   */
  public function testViewsWidget() {
    // Create a file so that our test View isn't empty.
    \Drupal::service('file_system')->copy(\Drupal::root() . '/core/misc/druplicon.png', 'public://example.jpg');
    /** @var \Drupal\file\FileInterface $file */
    $file = File::create([
      'uri' => 'public://example.jpg',
    ]);
    $file->save();

    // Visit a test entity browser page that defaults to using a View widget.
    $this->drupalGet('/entity-browser/iframe/test_entity_browser_file');
    $field = 'entity_browser_select[file:' . $file->id() . ']';

    // Test exposed filters.
    $this->assertSession()->pageTextContains('example.jpg');
    $this->assertSession()->fieldExists($field);
    $this->getSession()->getPage()->fillField('filename', 'llama');
    $this->getSession()->getPage()->pressButton('Apply');
    $this->waitForAjaxToFinish();
    $this->assertSession()->fieldNotExists($field);
    $this->assertSession()->pageTextNotContains('example.jpg');
    $this->getSession()->getPage()->fillField('filename', 'example');
    $this->getSession()->getPage()->pressButton('Apply');
    $this->waitForAjaxToFinish();
    $this->assertSession()->pageTextContains('example.jpg');
    $this->assertSession()->fieldExists($field)->check();
    $this->assertSession()->buttonExists('Select entities')->press();
    $this->assertSession()->responseNotContains('HTTP/1.0 200 OK');
    $this->assertSession()->responseNotContains('Cache-Control: no-cache, private');
    // Test that the response contains the selected entity.
    $script = "return drupalSettings.entity_browser.iframe.entities[0];";
    $result = $this->getSession()
      ->getDriver()
      ->getWebDriverSession()
      ->execute([
        'script' => $script,
        'args' => [],
      ]);
    $this->assertEquals($file->id(), $result[0]);
    $this->assertEquals('file', $result[2]);

    // Create another file to test bulk select form.
    \Drupal::service('file_system')->copy(\Drupal::root() . '/core/misc/druplicon.png', 'public://example_1.jpg');
    /** @var \Drupal\file\FileInterface $file */
    $new_file = File::create([
      'uri' => 'public://example_1.jpg',
    ]);
    $new_file->save();
    // Visit entity browser test page again.
    $this->drupalGet('/entity-browser/iframe/test_entity_browser_file');
    $new_field = 'entity_browser_select[file:' . $new_file->id() . ']';
    // Assert both checkbox fields are there.
    $check_old = $this->assertSession()->fieldExists($field);
    $check_new = $this->assertSession()->fieldExists($new_field);
    // Compare value attributes of checkboxes and assert they not equal.
    $this->assertNotEquals($check_old->getAttribute('value'), $check_new->getAttribute('value'));

    $uuid = \Drupal::service('uuid')->generate();
    \Drupal::service('entity_browser.selection_storage')->setWithExpire(
      $uuid,
      ['validators' => ['cardinality' => ['cardinality' => 1]]],
      21600
    );
    $this->drupalGet('/entity-browser/iframe/test_entity_browser_file', ['query' => ['uuid' => $uuid]]);
    $this->getSession()->getPage()->fillField('entity_browser_select[file:1]', TRUE);
    $this->getSession()->getPage()->fillField('entity_browser_select[file:2]', TRUE);
    $this->getSession()->getPage()->pressButton('Select entities');

    $this->assertSession()->pageTextContains('You can only select one item.');
    $this->assertSession()->checkboxNotChecked('entity_browser_select[file:1]');
    $this->assertSession()->checkboxNotChecked('entity_browser_select[file:2]');

    // Test entity_browser.view.js adding AJAX to exposed forms.
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'field_alderaan',
      'type' => 'entity_reference',
      'entity_type' => 'node',
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
      'settings' => [
        'target_type' => 'node',
      ],
    ]);
    $field_storage->save();

    $field = FieldConfig::create([
      'field_name' => 'field_alderaan',
      'entity_type' => 'node',
      'bundle' => 'article',
      'label' => 'Referenced articles',
      'settings' => [],
    ]);
    $field->save();

    /** @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface $form_display */
    $form_display = $this->container->get('entity_type.manager')
      ->getStorage('entity_form_display')
      ->load('node.article.default');

    $form_display->setComponent('field_alderaan', [
      'type' => 'entity_browser_entity_reference',
      'settings' => [
        'entity_browser' => 'test_double_underscore',
        'open' => TRUE,
        'field_widget_edit' => TRUE,
        'field_widget_remove' => TRUE,
        'field_widget_replace' => FALSE,
        'selection_mode' => EntityBrowserElement::SELECTION_MODE_APPEND,
        'field_widget_display' => 'label',
        'field_widget_display_settings' => [],
      ],
    ])->save();

    $nodes = [
      'Happy families are all alike',
      'Call me Ishmael',
    ];

    foreach ($nodes as $title) {
      $this->createNode([
        'title' => $title,
        'type' => 'article',
      ]);
    }

    $this->drupalGet('/node/add/article');
    $this->assertSession()->waitForElementVisible('css', '#entity-browser-test-double-underscore-form');

    $this->getSession()->switchToIFrame('entity_browser_iframe_test_double_underscore');
    foreach ($nodes as $title) {
      $this->assertSession()->pageTextContains($title);
    }

    $this->assertSession()->fieldExists('title')->setValue('Ishmael');
    $this->assertSession()->buttonExists('Apply')->press();
    $this->waitForAjaxToFinish();
    $this->assertSession()->pageTextContains('Call me Ishmael');
    $this->assertSession()->pageTextNotContains('Happy families are all alike');
    $this->assertSession()->fieldExists('title')->setValue('families');
    $this->assertSession()->buttonExists('Apply')->press();
    $this->waitForAjaxToFinish();
    $this->assertSession()->pageTextNotContains('Call me Ishmael');
    $this->assertSession()->pageTextContains('Happy families are all alike');

  }

}
