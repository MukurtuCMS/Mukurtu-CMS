<?php

namespace Drupal\Tests\entity_browser\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\SortableTestTrait;

/**
 * Test for integration of entity browser and inline entity form.
 *
 * @group entity_browser
 *
 * @package Drupal\Tests\entity_browser\FunctionalJavascript
 */
class InlineEntityFormTest extends EntityBrowserWebDriverTestBase {

  use SortableTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'views',
    'block',
    'node',
    'file',
    'image',
    'field_ui',
    'views_ui',
    'system',
    'node',
    'inline_entity_form',
    'entity_browser_test',
    'entity_browser_ief_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected static $userPermissions = [
    'create media',
    'update media',
    'access ief_entity_browser_file entity browser pages',
    'access ief_entity_browser_file_modal entity browser pages',
    'access widget_context_default_value entity browser pages',
    'access bundle_filter entity browser pages',
    'access content',
    'create ief_content content',
    'create shark content',
    'create jet content',
    'edit any ief_content content',
  ];

  /**
   * Check that selection state in entity browser Inline Entity Form.
   */
  public function testEntityBrowserInsideInlineEntityForm() {

    $this->createFile('test_file1');
    $this->createFile('test_file2');
    $this->createFile('test_file3');

    $this->drupalGet('node/add/ief_content');
    $page = $this->getSession()->getPage();

    $page->fillField('Title', 'Test IEF Title');
    $page->pressButton('Add new Test File Media');

    $this->assertSession()->assertWaitOnAjaxRequest();

    $page->fillField('Name', 'Test Bundle Media');
    $page->clickLink('Select entities');

    $this->getSession()
      ->switchToIFrame('entity_browser_iframe_ief_entity_browser_file');
    $page->checkField('entity_browser_select[file:1]');
    $page->checkField('entity_browser_select[file:2]');

    $page->pressButton('Select entities');

    $page->pressButton('Use selected');
    $this->getSession()->switchToIFrame();

    $page->pressButton('Create Test File Media');
    $this->assertSession()->assertWaitOnAjaxRequest();

    $page->pressButton('Save');

    $this->drupalGet('node/1/edit');
    $page = $this->getSession()->getPage();

    $page->pressButton('Edit');
    $this->assertSession()->assertWaitOnAjaxRequest();

    // Test reorder of elements.
    $list_selector = '[data-drupal-selector="edit-ief-media-field-form-inline-entity-form-entities-0-form-ief-media-type-file-field-current"]';
    $item_selector = "$list_selector .item-container";
    $this->sortableAfter("$item_selector:first-child", "$item_selector:last-child", $list_selector);

    $page->pressButton('Update Test File Media');
    $this->assertSession()->assertWaitOnAjaxRequest();

    $page->pressButton('Edit');
    $this->assertSession()->assertWaitOnAjaxRequest();

    // Check that element on second position is test_file1 (file:1).
    $secondElement = $page->find('xpath', '//div[@data-drupal-selector="edit-ief-media-field-form-inline-entity-form-entities-0-form-ief-media-type-file-field-current"]/div[2]');
    if (empty($secondElement)) {
      throw new \Exception('Element is not found.');
    }
    $this->assertSame('file:1', $secondElement->getAttribute('data-entity-id'));

    // Test remove of element.
    $this->click('input[name*="ief_media_type_file_field_remove_1_1"]');
    $this->assertSession()->assertWaitOnAjaxRequest();

    $page->pressButton('Update Test File Media');
    $this->assertSession()->assertWaitOnAjaxRequest();

    $page->pressButton('Edit');
    $this->assertSession()->assertWaitOnAjaxRequest();

    // Check that remote button does not exist for already removed entity.
    $this->assertSession()
      ->elementNotExists('css', '[name*="ief_media_type_file_field_remove_1_1"]');

    // Test add inside Entity Browser.
    $page->clickLink('Select entities');

    $this->getSession()
      ->switchToIFrame('entity_browser_iframe_ief_entity_browser_file');
    $page->checkField('entity_browser_select[file:3]');

    $page->pressButton('Select entities');
    $page->pressButton('Use selected');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->switchToIFrame();

    $page->pressButton('Update Test File Media');
    $this->assertSession()->assertWaitOnAjaxRequest();

    $page->pressButton('Edit');
    $this->assertSession()->assertWaitOnAjaxRequest();

    // Check that element on second position is test_file3 (file:3).
    $secondElement = $page->find('xpath', '//div[@data-drupal-selector="edit-ief-media-field-form-inline-entity-form-entities-0-form-ief-media-type-file-field-current"]/div[2]');
    if (empty($secondElement)) {
      throw new \Exception('Element is not found.');
    }
    $this->assertSame('file:3', $secondElement->getAttribute('data-entity-id'));

    // Test reorder inside Entity Browser.
    $page->clickLink('Select entities');

    $this->getSession()
      ->switchToIFrame('entity_browser_iframe_ief_entity_browser_file');

    $list_selector = '[data-drupal-selector="edit-selected"]';
    $item_selector = "$list_selector .item-container";
    $this->sortableAfter("$item_selector:first-child", "$item_selector:last-child", $list_selector);

    $page->pressButton('Use selected');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->switchToIFrame();

    $page->pressButton('Update Test File Media');
    $this->assertSession()->assertWaitOnAjaxRequest();

    $page->pressButton('Edit');
    $this->assertSession()->assertWaitOnAjaxRequest();

    // Check that element on second position is test_file2 (file:2).
    $secondElement = $page->find('xpath', '//div[@data-drupal-selector="edit-ief-media-field-form-inline-entity-form-entities-0-form-ief-media-type-file-field-current"]/div[2]');
    if (empty($secondElement)) {
      throw new \Exception('Element is not found.');
    }
    $this->assertSame('file:2', $secondElement->getAttribute('data-entity-id'));

    // Test remove inside entity browser.
    $page->clickLink('Select entities');

    $this->getSession()
      ->switchToIFrame('entity_browser_iframe_ief_entity_browser_file');

    $page->pressButton('remove_3_0');

    $page->pressButton('Use selected');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->switchToIFrame();

    $page->pressButton('Update Test File Media');
    $this->assertSession()->assertWaitOnAjaxRequest();

    $page->pressButton('Edit');
    $this->assertSession()->assertWaitOnAjaxRequest();

    // Check that element on first position is test_file2 (file:2).
    $secondElement = $page->find('xpath', '//div[@data-drupal-selector="edit-ief-media-field-form-inline-entity-form-entities-0-form-ief-media-type-file-field-current"]/div[1]');
    if (empty($secondElement)) {
      throw new \Exception('Element is not found.');
    }
    $this->assertSame('file:2', $secondElement->getAttribute('data-entity-id'));
  }

  /**
   * Checks auto_open functionality for modals.
   */
  public function testModalAutoOpenInsideInlineEntityForm() {

    $this->config('core.entity_form_display.node.ief_content.default')
      ->set('content.ief_media_field.third_party_settings.entity_browser_entity_form.entity_browser_id', 'ief_entity_browser_file_modal')
      ->save();

    $this->drupalGet('node/add/ief_content');
    $page = $this->getSession()->getPage();

    $page->fillField('Title', 'Test IEF Title');
    $page->pressButton('Add existing Test File Media');

    $this->assertSession()->assertWaitOnAjaxRequest();

    $this->getSession()
      ->switchToIFrame('entity_browser_iframe_ief_entity_browser_file_modal');

    $this->assertSession()->waitForElementVisible('css', 'ief-entity-browser-file-modal-form');

    $this->assertSession()->responseContains('Test entity browser file modal');
  }

  /**
   * Tests the EntityBrowserWidgetContext argument_default views plugin.
   */
  public function testContextualFilter() {
    $this->createNode(['type' => 'shark', 'title' => 'Luke']);
    $this->createNode(['type' => 'jet', 'title' => 'Leia']);
    $this->createNode(['type' => 'ief_content', 'title' => 'Darth']);

    $this->drupalGet('node/add/ief_content');
    $page = $this->getSession()->getPage();

    $page->fillField('Title', 'Test IEF Title');
    $page->pressButton('Add existing node');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->switchToIFrame('entity_browser_iframe_widget_context_default_value');

    // Check that only nodes of an allowed type are listed.
    $this->assertSession()->pageTextContains('Luke');
    $this->assertSession()->pageTextContains('Leia');
    $this->assertSession()->pageTextNotContains('Darth');

    /** @var \Drupal\Core\Field\FieldConfigInterface $field_config */
    $field_config = $this->container->get('entity_type.manager')
      ->getStorage('field_config')
      ->load('node.ief_content.field_nodes');
    $handler_settings = $field_config->getSetting('handler_settings');
    $handler_settings['target_bundles'] = [
      'ief_content' => 'ief_content',
    ];
    $field_config->setSetting('handler_settings', $handler_settings);
    $field_config->save();

    $this->drupalGet('node/add/ief_content');
    $page = $this->getSession()->getPage();

    $page->fillField('Title', 'Test IEF Title');
    $page->pressButton('Add existing node');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->switchToIFrame('entity_browser_iframe_widget_context_default_value');

    // Check that only nodes of an allowed type are listed.
    $this->assertSession()->pageTextNotContains('Luke');
    $this->assertSession()->pageTextNotContains('Leia');
    $this->assertSession()->pageTextContains('Darth');
  }

  /**
   * Tests the ContextualBundle filter plugin with exposed option.
   */
  public function testContextualBundleExposed() {

    $this->config('core.entity_form_display.node.ief_content.default')
      ->set('content.field_nodes.third_party_settings.entity_browser_entity_form.entity_browser_id', 'bundle_filter')
      ->save();

    $this->config('entity_browser.browser.bundle_filter')
      ->set('widgets.b882a89d-9ce4-4dfe-9802-62df93af232a.settings.view', 'bundle_filter_exposed')
      ->save();

    $this->createNode(['type' => 'shark', 'title' => 'Luke']);
    $this->createNode(['type' => 'jet', 'title' => 'Leia']);
    $this->createNode(['type' => 'ief_content', 'title' => 'Darth']);

    $this->drupalGet('node/add/ief_content');
    $page = $this->getSession()->getPage();

    $page->fillField('Title', 'Test IEF Title');
    $page->pressButton('Add existing node');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->switchToIFrame('entity_browser_iframe_bundle_filter');

    // Check that only nodes of an allowed type are listed.
    $this->assertSession()->pageTextContains('Luke');
    $this->assertSession()->pageTextContains('Leia');
    $this->assertSession()->pageTextNotContains('Darth');

    // Test exposed form type filter.
    $this->assertSession()->selectExists('Type')->selectOption('jet');
    $this->assertSession()->buttonExists('Apply')->press();
    $this->assertSession()->assertWaitOnAjaxRequest();

    // Check that only nodes of the type selected in the exposed filter display.
    $this->assertSession()->pageTextNotContains('Luke');
    $this->assertSession()->pageTextContains('Leia');
    $this->assertSession()->pageTextNotContains('Darth');

    $this->assertSession()->selectExists('Type')->selectOption('shark');
    $this->assertSession()->buttonExists('Apply')->press();
    $this->assertSession()->assertWaitOnAjaxRequest();

    // Check that only nodes of the type selected in the exposed filter display.
    $this->assertSession()->pageTextContains('Luke');
    $this->assertSession()->pageTextNotContains('Leia');
    $this->assertSession()->pageTextNotContains('Darth');

    $this->assertSession()->selectExists('Type')->selectOption('All');
    $this->assertSession()->buttonExists('Apply')->press();
    $this->assertSession()->assertWaitOnAjaxRequest();

    // Check that only nodes of the type selected in the exposed filter display.
    $this->assertSession()->pageTextContains('Luke');
    $this->assertSession()->pageTextContains('Leia');
    $this->assertSession()->pageTextNotContains('Darth');

    /** @var \Drupal\Core\Field\FieldConfigInterface $field_config */
    $field_config = $this->container->get('entity_type.manager')
      ->getStorage('field_config')
      ->load('node.ief_content.field_nodes');
    $handler_settings = $field_config->getSetting('handler_settings');
    $handler_settings['target_bundles'] = [
      'ief_content' => 'ief_content',
    ];
    $field_config->setSetting('handler_settings', $handler_settings);
    $field_config->save();

    $this->drupalGet('node/add/ief_content');
    $page = $this->getSession()->getPage();

    $page->fillField('Title', 'Test IEF Title');
    $page->pressButton('Add existing node');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->switchToIFrame('entity_browser_iframe_bundle_filter');

    // Check that only nodes of an allowed type are listed.
    $this->assertSession()->pageTextNotContains('Luke');
    $this->assertSession()->pageTextNotContains('Leia');
    $this->assertSession()->pageTextContains('Darth');

    // If there is just one target_bundle, the contextual filter
    // should not be visible.
    $this->assertSession()->fieldNotExists('Type');

  }

  /**
   * Tests entity_browser_entity_form_reference_form_validate.
   */
  public function testEntityFormReferenceFormValidate() {
    $boxer = $this->createNode(['type' => 'shark', 'title' => 'Boxer']);
    $napoleon = $this->createNode(['type' => 'jet', 'title' => 'Napoleon']);

    $this->drupalGet('node/add/ief_content');
    $page = $this->getSession()->getPage();

    $page->fillField('Title', 'Test IEF Title');

    // Select the same node twice.
    for ($i = 0; $i < 2; $i++) {
      $this->assertSession()->buttonExists('Add existing node')->press();
      $this->assertSession()->assertWaitOnAjaxRequest();
      $this->getSession()->switchToIFrame('entity_browser_iframe_widget_context_default_value');
      $this->assertSession()->fieldExists('entity_browser_select[node:' . $boxer->id() . ']')->check();
      $this->assertSession()->buttonExists('Select entities')->press();
      $this->assertSession()->buttonExists('Use selected')->press();
      $this->assertSession()->assertWaitOnAjaxRequest();
      $this->getSession()->switchToIFrame();

      if (!$this->coreVersion('10.2')) {
        $this->assertSession()->assertWaitOnAjaxRequest();
      }
    }

    $this->assertSession()->pageTextContains('The selected node has already been added.');

    // Select a different node.
    $this->getSession()->switchToIFrame('entity_browser_iframe_widget_context_default_value');
    $this->assertSession()->fieldExists('entity_browser_select[node:' . $napoleon->id() . ']')->check();
    $this->assertSession()->buttonExists('Select entities')->press();
    $this->assertSession()->buttonExists('Use selected')->press();
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->switchToIFrame();

    if (!$this->coreVersion('10.2')) {
      $this->assertSession()->assertWaitOnAjaxRequest();
    }

    $this->assertSession()->pageTextNotContains('The selected node has already been added.');

    $ief_table = $this->assertSession()->elementExists('xpath', '//table[contains(@id, "ief-entity-table-edit-field-nodes-entities")]');
    $table_text = $ief_table->getText();
    $this->assertStringContainsString('Boxer', $table_text);
    $this->assertStringContainsString('Napoleon', $table_text);
  }

  /**
   * {@inheritdoc}
   */
  protected function sortableUpdate($item, $from, $to = NULL) {
    [$container] = explode(' ', $item, 2);

    $js = <<<END
(Drupal.entityBrowserEntityReference || Drupal.entityBrowserMultiStepDisplay).entitiesReordered(document.querySelector("$container"));
END;
    $this->getSession()->executeScript($js);
  }

}
