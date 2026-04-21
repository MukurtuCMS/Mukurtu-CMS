<?php

namespace Drupal\Tests\entity_browser\FunctionalJavascript;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\entity_browser\Element\EntityBrowserElement;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\ckeditor5\Traits\CKEditor5TestTrait;
use Drupal\user\Entity\Role;

/**
 * Tests the Cardinality handling.
 *
 * @group entity_browser
 */
class CardinalityTest extends EntityBrowserWebDriverTestBase {

  use CKEditor5TestTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'entity_browser_test',
    'embed',
    'entity_embed',
    'entity_browser_entity_embed_test',
    'inline_entity_form',
    'entity_browser_ief_test',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    /** @var \Drupal\user\RoleInterface $role */
    $role = Role::load('authenticated');
    $this->grantPermissions($role, [
      'access cardinality entity browser pages',
      'bypass node access',
      'administer node form display',
      'access content',
    ]);
  }

  /**
   * Tests Entity Reference widget.
   */
  public function testEntityReferenceWidget() {

    // Create an entity_reference field to test the widget.
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'field_fellowship',
      'type' => 'entity_reference',
      'entity_type' => 'node',
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
      'settings' => [
        'target_type' => 'node',
      ],
    ]);
    $field_storage->save();

    $field = FieldConfig::create([
      'field_name' => 'field_fellowship',
      'entity_type' => 'node',
      'bundle' => 'article',
      'label' => 'Referenced articles',
      'settings' => [
        'handler' => 'default:node',
        'handler_settings' => [
          'target_bundles' => [
            'article' => 'article',
            'shark' => 'shark',
            'jet' => 'jet',
          ],
        ],
      ],
    ]);
    $field->save();

    /** @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface $form_display */
    $form_display = $this->container->get('entity_type.manager')
      ->getStorage('entity_form_display')
      ->load('node.article.default');

    $form_display->setComponent('field_fellowship', [
      'type' => 'entity_browser_entity_reference',
      'settings' => [
        'entity_browser' => 'cardinality',
        'open' => TRUE,
        'field_widget_edit' => TRUE,
        'field_widget_remove' => TRUE,
        'field_widget_replace' => TRUE,
        'selection_mode' => EntityBrowserElement::SELECTION_MODE_APPEND,
        'field_widget_display' => 'label',
        'field_widget_display_settings' => [],
      ],
    ])->save();

    $gollum = $this->createNode(['type' => 'shark', 'title' => 'Gollum']);
    $aragorn = $this->createNode(['type' => 'jet', 'title' => 'Aragorn']);
    $gandolf = $this->createNode(['type' => 'article', 'title' => 'Gandolf']);
    $legolas = $this->createNode(['type' => 'article', 'title' => 'Legolas']);
    $boromir = $this->createNode(['type' => 'article', 'title' => 'Boromir']);

    // Test the cardinality handling.
    $role = Role::load('authenticated');
    $this->grantPermissions($role, [
      'access cardinality entity browser pages',
      'bypass node access',
      'administer node form display',
    ]);
    FieldStorageConfig::load('node.field_fellowship')
      ->setCardinality(2)
      ->save();
    // Without using field cardinality, the view should contain checkboxes.
    // Set view to use field cardinality.
    $this->config('views.view.bundle_filter_exposed')
      ->set('display.default.display_options.fields.entity_browser_select.use_field_cardinality', FALSE)
      ->save();
    $this->drupalGet('/node/add/article');
    $this->assertSession()->fieldExists('title[0][value]')->setValue('Le Seigneur des anneaux');

    $this->openIframe();
    $gollum_checkbox = $this->assertCheckboxExistsByValue('node:' . $gollum->id());
    $gollum_checkbox->check();
    $aragorn_checkbox = $this->assertCheckboxExistsByValue('node:' . $aragorn->id());
    $aragorn_checkbox->check();
    $this->assertTrue($gollum_checkbox->isChecked());
    $this->assertTrue($aragorn_checkbox->isChecked());
    // If using field cardinality and field cardinality is greater than 1 then
    // there should be still checkboxes.
    $this->config('views.view.bundle_filter_exposed')
      ->set('display.default.display_options.fields.entity_browser_select.use_field_cardinality', TRUE)
      ->save();
    $this->drupalGet('/node/add/article');
    $this->openIframe();
    $gollum_checkbox = $this->assertCheckboxExistsByValue('node:' . $gollum->id());
    $aragorn_checkbox = $this->assertCheckboxExistsByValue('node:' . $aragorn->id());
    $gandolf_checkbox = $this->assertCheckboxExistsByValue('node:' . $gandolf->id());
    $this->assertCheckboxExistsByValue('node:' . $legolas->id());
    $this->assertCheckboxExistsByValue('node:' . $boromir->id());
    // If we attempt to select 3 nodes, Entity Browser should prevent it and
    // return an error message.
    $gollum_checkbox->check();
    $aragorn_checkbox->check();
    $gandolf_checkbox->check();
    $this->assertSession()->buttonExists('Select entities')->press();
    $this->assertSession()->pageTextContains('You can only select up to 2 items');
    // If we change the cardinality to 1, we should have radios.
    FieldStorageConfig::load('node.field_fellowship')
      ->setCardinality(1)
      ->save();
    $this->drupalGet('/node/add/article');
    $this->openIframe();
    $gollum_radio = $this->assertRadioExistsByValue('node:' . $gollum->id());
    $gollum_radio->click();
    $this->assertSession()->buttonExists('Select entities')->press();
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->switchToIFrame();

    if (!$this->coreVersion('10.2')) {
      $this->assertSession()->assertWaitOnAjaxRequest();
    }

    // Assert the selected entity.
    $this->assertSession()->pageTextContains('Gollum');
    // Attempt to select more than one element.
    $this->assertSession()->buttonExists('Replace')->press();
    $this->waitForAjaxToFinish();
    $this->getSession()->switchToIFrame('entity_browser_iframe_cardinality');
    $gollum_radio = $this->assertRadioExistsByValue('node:' . $gollum->id());
    $gollum_radio->click();
    $gandolf_radio = $this->assertRadioExistsByValue('node:' . $gandolf->id());
    $gandolf_radio->click();
    $this->assertFalse($gollum_radio->isSelected());
    $this->assertTrue($gandolf_radio->isSelected());
    $this->assertSession()->buttonExists('Select entities')->press();
    $this->getSession()->switchToIFrame();
    $this->waitForAjaxToFinish();
    // Assert the selected entity.
    $this->assertSession()->pageTextContains('Gandolf');
    $this->assertSession()->pageTextNotContains('Gollum');

    $this->assertSession()->buttonExists('Replace')->press();
    $this->waitForAjaxToFinish();
    $this->getSession()->switchToIFrame('entity_browser_iframe_cardinality');

    // Test that cardinality setting persists when using exposed filters form,
    // When applying the exposed filters, the radios should persist.
    $this->assertSession()->selectExists('Type')->selectOption('jet');
    $this->assertSession()->buttonExists('Apply')->press();
    $this->assertSession()->assertWaitOnAjaxRequest();

    $this->assertRadioExistsByValue('node:' . $aragorn->id());
    $this->assertRadioNotExistsByValue('node:' . $legolas->id());

    $this->assertSession()->selectExists('Type')->selectOption('shark');
    $this->assertSession()->buttonExists('Apply')->press();
    $this->assertSession()->assertWaitOnAjaxRequest();

    $this->assertRadioExistsByValue('node:' . $gollum->id());
    $this->assertRadioNotExistsByValue('node:' . $aragorn->id());

  }

  /**
   * Tests cardinality functionality using Entity Embed button.
   */
  public function testEntityEmbed() {
    $this->config('entity_browser.browser.bundle_filter')
      ->set('widgets.b882a89d-9ce4-4dfe-9802-62df93af232a.settings.view', 'bundle_filter_exposed')
      ->save();

    $role = Role::load('authenticated');
    $this->grantPermissions($role, [
      'access content',
      'use text format full_html',
      'create test_entity_embed content',
      'access bundle_filter entity browser pages',
    ]);

    FieldStorageConfig::load('node.field_nodes')
      ->setCardinality(1)
      ->save();

    $westley = $this->createNode(['type' => 'shark', 'title' => 'Westley']);
    $buttercup = $this->createNode(['type' => 'jet', 'title' => 'Buttercup']);
    $humperdinck = $this->createNode(['type' => 'article', 'title' => 'Humperdinck']);

    $this->drupalGet('/node/add/test_entity_embed');

    $this->waitForEditor();
    $this->pressEditorButton('Bundle Filter Test Embed');
    $this->assertSession()->waitForElementVisible('xpath', "//iframe[contains(@name, 'entity_browser_iframe_bundle_filter')]", 3000);
    $this->getSession()->switchToIFrame('entity_browser_iframe_bundle_filter');
    $this->assertSession()->waitForElementVisible('xpath', "//div[contains(@class, 'views-exposed-form')]");

    // Without use_field_cardinality set, there should be checkboxes, the default.
    $this->assertCheckBoxExistsByValue('node:' . $westley->id());
    $this->assertCheckBoxExistsByValue('node:' . $buttercup->id());
    $this->assertCheckBoxNotExistsByValue('node:' . $humperdinck->id());

    // Set view to use field cardinality.
    $this->config('views.view.bundle_filter_exposed')
      ->set('display.default.display_options.fields.entity_browser_select.use_field_cardinality', TRUE)
      ->save();

    $this->drupalGet('/node/add/test_entity_embed');
    $this->waitForEditor();
    $this->pressEditorButton('Bundle Filter Test Embed');
    $this->assertSession()->waitForElementVisible('xpath', "//iframe[contains(@name, 'entity_browser_iframe_bundle_filter')]", 3000);
    $this->getSession()->switchToIFrame('entity_browser_iframe_bundle_filter');
    $this->assertSession()->waitForElementVisible('xpath', "//div[contains(@class, 'views-exposed-form')]");

    // With use_field_cardinality set to true, there should be radios, since
    // cardinality on entity embed is always 1.
    $this->assertRadioExistsByValue('node:' . $westley->id());
    $this->assertRadioExistsByValue('node:' . $buttercup->id());
    $this->assertRadioNotExistsByValue('node:' . $humperdinck->id());

    // Test that cardinality setting persists when using exposed filters form.
    // When applying the exposed filters, the radios should persist.
    $this->assertSession()->selectExists('Type')->selectOption('jet');
    $this->assertSession()->buttonExists('Apply')->press();
    $this->assertSession()->assertWaitOnAjaxRequest();

    $this->assertRadioNotExistsByValue('node:' . $westley->id());
    $this->assertRadioExistsByValue('node:' . $buttercup->id());
    $this->assertRadioNotExistsByValue('node:' . $humperdinck->id());

    $this->assertSession()->selectExists('Type')->selectOption('shark');
    $this->assertSession()->buttonExists('Apply')->press();
    $this->assertSession()->assertWaitOnAjaxRequest();

    $this->assertRadioExistsByValue('node:' . $westley->id());
    $this->assertRadioNotExistsByValue('node:' . $buttercup->id());
    $this->assertRadioNotExistsByValue('node:' . $humperdinck->id());

  }

  /**
   * Tests cardinality functionality using Inline Entity Form.
   */
  public function testInlineEntityForm() {

    /** @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface $form_display */
    $form_display = $this->container->get('entity_type.manager')
      ->getStorage('entity_form_display')
      ->load('node.ief_content.default');

    $field_nodes = $form_display->getComponent('field_nodes');
    $field_nodes['third_party_settings']['entity_browser_entity_form']['entity_browser_id'] = 'cardinality';
    $form_display->setComponent('field_nodes', $field_nodes);
    $form_display->save();

    // Set auto open to TRUE on the entity browser.
    $entity_browser = $this->container->get('entity_type.manager')
      ->getStorage('entity_browser')
      ->load('cardinality');
    $display_configuration = $entity_browser->get('display_configuration');
    $display_configuration['auto_open'] = TRUE;
    $entity_browser->set('display_configuration', $display_configuration);
    $entity_browser->save();

    $vizzini = $this->createNode(['type' => 'shark', 'title' => 'Vizzini']);
    $inigo = $this->createNode(['type' => 'jet', 'title' => 'Inigo']);
    $miracle_max = $this->createNode(['type' => 'article', 'title' => 'Miracle Max']);

    $this->drupalGet('node/add/ief_content');
    $page = $this->getSession()->getPage();

    $page->pressButton('Add existing node');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->switchToIFrame('entity_browser_iframe_cardinality');

    // Without use_field_cardinality set, there should be checkboxes, the default.
    $this->assertCheckBoxExistsByValue('node:' . $vizzini->id());
    $this->assertCheckBoxExistsByValue('node:' . $inigo->id());
    $this->assertCheckBoxNotExistsByValue('node:' . $miracle_max->id());

    $view = $this->config('views.view.bundle_filter_exposed');
    $field = $view->get('display.default.display_options.fields.entity_browser_select', TRUE);
    $field['use_field_cardinality'] = TRUE;
    $view->set('display.default.display_options.fields.entity_browser_select', $field);
    $view->save();

    FieldStorageConfig::load('node.field_nodes')
      ->setCardinality(1)
      ->save();

    $this->drupalGet('node/add/ief_content');
    $page = $this->getSession()->getPage();

    $page->pressButton('Add existing node');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->switchToIFrame('entity_browser_iframe_cardinality');

    // With use_field_cardinality set to true, and cardinality set to 1,
    // there should be radios.
    $this->assertRadioExistsByValue('node:' . $vizzini->id());
    $this->assertRadioExistsByValue('node:' . $inigo->id());
    $this->assertRadioNotExistsByValue('node:' . $miracle_max->id());

    // Test that cardinality setting persists when using exposed filters form.
    // When applying the exposed filters, the radios should persist.
    $this->assertSession()->selectExists('Type')->selectOption('jet');
    $this->assertSession()->buttonExists('Apply')->press();
    $this->assertSession()->assertWaitOnAjaxRequest();

    $this->assertRadioNotExistsByValue('node:' . $vizzini->id());
    $this->assertRadioExistsByValue('node:' . $inigo->id());
    $this->assertRadioNotExistsByValue('node:' . $miracle_max->id());

    $this->assertSession()->selectExists('Type')->selectOption('shark');
    $this->assertSession()->buttonExists('Apply')->press();
    $this->assertSession()->assertWaitOnAjaxRequest();

    $this->assertRadioExistsByValue('node:' . $vizzini->id());
    $this->assertRadioNotExistsByValue('node:' . $inigo->id());
    $this->assertRadioNotExistsByValue('node:' . $miracle_max->id());

  }

  /**
   * Helper function for repetitive task.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   */
  protected function openIframe() {
    $open_iframe_link = $this->assertSession()
      ->elementExists('css', 'a[data-drupal-selector="edit-field-fellowship-entity-browser-entity-browser-link"]');
    $open_iframe_link->click();
    $this->getSession()->switchToIFrame('entity_browser_iframe_cardinality');
  }

}
