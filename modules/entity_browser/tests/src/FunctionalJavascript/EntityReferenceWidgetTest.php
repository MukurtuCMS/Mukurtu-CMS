<?php

namespace Drupal\Tests\entity_browser\FunctionalJavascript;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\entity_browser\Element\EntityBrowserElement;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\FunctionalJavascriptTests\SortableTestTrait;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\Role;

/**
 * Tests the Entity Reference Widget.
 *
 * @group entity_browser
 */
class EntityReferenceWidgetTest extends EntityBrowserWebDriverTestBase {

  use SortableTestTrait;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    /** @var \Drupal\user\RoleInterface $role */
    $role = Role::load('authenticated');
    $this->grantPermissions($role, [
      'access test_entity_browser_iframe_node_view entity browser pages',
      'bypass node access',
      'administer node form display',
      'access contextual links',
    ]);

  }

  /**
   * Tests Entity Reference widget.
   */
  public function testEntityReferenceWidget() {

    // Create an entity_reference field to test the widget.
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'field_entity_reference1',
      'type' => 'entity_reference',
      'entity_type' => 'node',
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
      'settings' => [
        'target_type' => 'node',
      ],
    ]);
    $field_storage->save();

    $field = FieldConfig::create([
      'field_name' => 'field_entity_reference1',
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

    $form_display->setComponent('field_entity_reference1', [
      'type' => 'entity_browser_entity_reference',
      'settings' => [
        'entity_browser' => 'test_entity_browser_iframe_node_view',
        'open' => TRUE,
        'field_widget_edit' => TRUE,
        'field_widget_remove' => TRUE,
        'field_widget_replace' => FALSE,
        'selection_mode' => EntityBrowserElement::SELECTION_MODE_APPEND,
        'field_widget_display' => 'rendered_entity',
        'field_widget_display_settings' => [
          'view_mode' => 'teaser',
        ],
      ],
    ])->save();

    // Create a dummy node that will be used as target.
    $target_node = Node::create([
      'title' => 'Walrus',
      'type' => 'article',
    ]);
    $target_node->save();

    $target_node_translation = $target_node->addTranslation('fr', $target_node->toArray());
    $target_node_translation->setTitle('le Morse');
    $target_node_translation->save();

    $this->drupalGet('/node/add/article');
    $this->assertSession()->fieldExists('title[0][value]')->setValue('Referencing node 1');
    $this->getSession()->switchToIFrame('entity_browser_iframe_test_entity_browser_iframe_node_view');
    $this->assertSession()->fieldExists('entity_browser_select[node:1]')->check();
    $this->assertSession()->buttonExists('Select entities')->press();
    $this->waitForAjaxToFinish();
    $this->getSession()->switchToIFrame();
    $this->assertTrue($this->assertSession()->waitForText('Walrus'));
    $this->assertSession()->buttonExists('Save')->press();

    $this->assertSession()->pageTextContains('Article Referencing node 1 has been created.');
    $nid = \Drupal::entityQuery('node')->accessCheck(TRUE)->condition('title', 'Referencing node 1')->execute();
    $nid = reset($nid);

    // Assert correct translation appears.
    // @see Drupal\entity_browser\Plugin\EntityBrowser\FieldWidgetDisplay\EntityLabel
    $this->drupalGet('fr/node/' . $nid . '/edit');
    $this->assertSession()->pageTextContains('le Morse');
    $this->drupalGet('node/' . $nid . '/edit');
    $this->assertSession()->pageTextContains('Walrus');

    // Make sure both "Edit" and "Remove" buttons are visible.
    $this->assertSession()->buttonExists('edit-field-entity-reference1-current-items-0-remove-button');
    $this->assertSession()->buttonExists('edit-field-entity-reference1-current-items-0-edit-button')->press();
    // Make sure the contextual links are not present.
    $this->assertSession()->elementNotExists('css', '.contextual-links');

    // Test edit dialog by changing title of referenced entity.
    $edit_dialog = $this->assertSession()->waitForElement('xpath', '//div[contains(@id, "node-' . $target_node->id() . '-edit-dialog")]');
    $title_field = $edit_dialog->findField('title[0][value]');
    $title = $title_field->getValue();
    $this->assertEquals('Walrus', $title);
    $title_field->setValue('Alpaca');
    $this->assertSession()
      ->elementExists('css', '.ui-dialog-buttonset.form-actions .form-submit')
      ->press();
    $this->waitForAjaxToFinish();
    // Check that new title is displayed.
    $this->assertSession()->pageTextNotContains('Walrus');
    $this->assertSession()->pageTextContains('Alpaca');

    // Test whether changing these definitions on the browser config effectively
    // change the visibility of the buttons.
    $form_display->setComponent('field_entity_reference1', [
      'type' => 'entity_browser_entity_reference',
      'settings' => [
        'entity_browser' => 'test_entity_browser_iframe_node_view',
        'open' => TRUE,
        'field_widget_edit' => FALSE,
        'field_widget_remove' => FALSE,
        'field_widget_replace' => FALSE,
        'selection_mode' => EntityBrowserElement::SELECTION_MODE_APPEND,
        'field_widget_display' => 'label',
        'field_widget_display_settings' => [],
      ],
    ])->save();
    $this->drupalGet('node/' . $nid . '/edit');
    $this->assertSession()->buttonNotExists('edit-field-entity-reference1-current-items-0-remove-button');
    $this->assertSession()->buttonNotExists('edit-field-entity-reference1-current-items-0-edit-button');

    // Set them to visible again.
    $form_display->setComponent('field_entity_reference1', [
      'type' => 'entity_browser_entity_reference',
      'settings' => [
        'entity_browser' => 'test_entity_browser_iframe_node_view',
        'open' => TRUE,
        'field_widget_edit' => TRUE,
        'field_widget_remove' => TRUE,
        'field_widget_replace' => FALSE,
        'selection_mode' => EntityBrowserElement::SELECTION_MODE_APPEND,
        'field_widget_display' => 'label',
        'field_widget_display_settings' => [],
      ],
    ])->save();
    $this->drupalGet('node/' . $nid . '/edit');
    $remove_button = $this->assertSession()->buttonExists('edit-field-entity-reference1-current-items-0-remove-button');
    $this->assertEquals('Remove', $remove_button->getValue());
    $this->assertTrue($remove_button->hasClass('remove-button'));
    $edit_button = $this->assertSession()->buttonExists('edit-field-entity-reference1-current-items-0-edit-button');
    $this->assertEquals('Edit', $edit_button->getValue());
    $this->assertTrue($edit_button->hasClass('edit-button'));
    // Make sure the "Replace" button is not there.
    $this->assertSession()->buttonNotExists('edit-field-entity-reference1-current-items-0-replace-button');

    // Test the "Remove" button on the widget works.
    $this->assertSession()->buttonExists('Remove')->press();
    $this->waitForAjaxToFinish();
    $this->assertSession()->pageTextNotContains('Alpaca');

    // Test the "Replace" button functionality.
    $form_display->setComponent('field_entity_reference1', [
      'type' => 'entity_browser_entity_reference',
      'settings' => [
        'entity_browser' => 'test_entity_browser_iframe_node_view',
        'open' => TRUE,
        'field_widget_edit' => TRUE,
        'field_widget_remove' => TRUE,
        'field_widget_replace' => TRUE,
        'selection_mode' => EntityBrowserElement::SELECTION_MODE_APPEND,
        'field_widget_display' => 'label',
        'field_widget_display_settings' => [],
      ],
    ])->save();
    // In order to ensure the replace button opens the browser, it needs to be
    // closed.
    /** @var \Drupal\entity_browser\EntityBrowserInterface $browser */
    $browser = $this->container->get('entity_type.manager')
      ->getStorage('entity_browser')
      ->load('test_entity_browser_iframe_node_view');
    $browser->getDisplay()
      ->setConfiguration([
        'width' => 650,
        'height' => 500,
        'link_text' => 'Select entities',
        'auto_open' => FALSE,
      ]);
    $browser->save();

    // We'll need a third node to be able to make a new selection.
    $target_node2 = Node::create([
      'title' => 'Target example node 2',
      'type' => 'article',
    ]);
    $target_node2->save();
    $this->drupalGet('node/' . $nid . '/edit');
    // If there is only one entity in the current selection the button should
    // show up.
    $replace_button = $this->assertSession()->buttonExists('edit-field-entity-reference1-current-items-0-replace-button');
    $this->assertEquals('Replace', $replace_button->getValue());
    $this->assertTrue($replace_button->hasClass('replace-button'));
    // Clicking on the button should empty the selection and automatically
    // open the browser again.
    $replace_button->click();
    $this->waitForAjaxToFinish();
    $this->getSession()->switchToIFrame('entity_browser_iframe_test_entity_browser_iframe_node_view');
    $this->assertSession()->fieldExists('entity_browser_select[node:3]')->check();
    $this->assertSession()->buttonExists('Select entities')->press();
    $this->waitForAjaxToFinish();
    $this->getSession()->switchToIFrame();

    if (!$this->coreVersion('10.2')) {
      $this->assertSession()->assertWaitOnAjaxRequest();
    }

    // Even in the AJAX-built markup for the newly selected element, the replace
    // button should be there.
    $this->assertSession()->elementExists('css', 'input[data-drupal-selector="edit-field-entity-reference1-current-items-0-replace-button"]');
    // Adding a new node to the selection, however, should make it disappear.
    $open_iframe_link = $this->assertSession()->elementExists('css', 'a[data-drupal-selector="edit-field-entity-reference1-entity-browser-entity-browser-link"]');
    $open_iframe_link->click();
    $this->getSession()->switchToIFrame('entity_browser_iframe_test_entity_browser_iframe_node_view');
    $this->assertSession()->fieldExists('entity_browser_select[node:1]')->check();
    $this->assertSession()->buttonExists('Select entities')->press();
    $this->waitForAjaxToFinish();
    $this->getSession()->switchToIFrame();

    if (!$this->coreVersion('10.2')) {
      $this->assertSession()->assertWaitOnAjaxRequest();
    }
    $this->assertSession()->elementNotExists('css', 'input[data-drupal-selector="edit-field-entity-reference1-current-items-0-replace-button"]');
    $this->assertSession()->buttonExists('Save')->press();
    $this->assertSession()->pageTextContains('Article Referencing node 1 has been updated.');

    // Test the replace button again with different field cardinalities.
    FieldStorageConfig::load('node.field_entity_reference1')->setCardinality(1)->save();
    $this->drupalGet('/node/add/article');
    $this->assertSession()->fieldExists('title[0][value]')->setValue('Referencing node 2');
    $open_iframe_link = $this->assertSession()->elementExists('css', 'a[data-drupal-selector="edit-field-entity-reference1-entity-browser-entity-browser-link"]');
    $open_iframe_link->click();
    $this->getSession()->switchToIFrame('entity_browser_iframe_test_entity_browser_iframe_node_view');
    $this->assertSession()->fieldExists('entity_browser_select[node:1]')->check();
    $this->assertSession()->buttonExists('Select entities')->press();
    $this->waitForAjaxToFinish();
    $this->getSession()->switchToIFrame();

    if (!$this->coreVersion('10.2')) {
      $this->assertSession()->assertWaitOnAjaxRequest();
    }

    $this->assertSession()->elementContains('css', '#edit-field-entity-reference1-wrapper', 'Alpaca');
    // All three buttons should be visible.
    $this->assertSession()->elementExists('css', 'input[data-drupal-selector="edit-field-entity-reference1-current-items-0-remove-button"]');
    $this->assertSession()->elementExists('css', 'input[data-drupal-selector="edit-field-entity-reference1-current-items-0-edit-button"]');
    $replace_button = $this->assertSession()->elementExists('css', 'input[data-drupal-selector="edit-field-entity-reference1-current-items-0-replace-button"]');
    // Clicking on the button should empty the selection and automatically
    // open the browser again.
    $replace_button->click();
    $this->waitForAjaxToFinish();
    $this->getSession()->switchToIFrame('entity_browser_iframe_test_entity_browser_iframe_node_view');
    $this->assertSession()->fieldExists('entity_browser_select[node:2]')->check();
    $this->assertSession()->buttonExists('Select entities')->press();
    $this->waitForAjaxToFinish();
    $this->getSession()->switchToIFrame();
    $this->assertSession()->elementContains('css', '#edit-field-entity-reference1-wrapper', 'Referencing node 1');

    // Do the same as above but now with cardinality 2.
    FieldStorageConfig::load('node.field_entity_reference1')
      ->setCardinality(2)
      ->save();
    $this->drupalGet('/node/add/article');
    $this->assertSession()->fieldExists('title[0][value]')->setValue('Referencing node 3');
    $open_iframe_link = $this->assertSession()->elementExists('css', 'a[data-drupal-selector="edit-field-entity-reference1-entity-browser-entity-browser-link"]');
    $open_iframe_link->click();
    $this->getSession()->switchToIFrame('entity_browser_iframe_test_entity_browser_iframe_node_view');
    $this->assertSession()->fieldExists('entity_browser_select[node:1]')->check();
    $this->assertSession()->buttonExists('Select entities')->press();
    $this->waitForAjaxToFinish();
    $this->getSession()->switchToIFrame();

    if (!$this->coreVersion('10.2')) {
      $this->assertSession()->assertWaitOnAjaxRequest();
    }

    $this->assertSession()->elementContains('css', '#edit-field-entity-reference1-wrapper', 'Alpaca');
    // All three buttons should be visible.
    $this->assertSession()->elementExists('css', 'input[data-drupal-selector="edit-field-entity-reference1-current-items-0-remove-button"]');
    $this->assertSession()->elementExists('css', 'input[data-drupal-selector="edit-field-entity-reference1-current-items-0-edit-button"]');
    $replace_button = $this->assertSession()->elementExists('css', 'input[data-drupal-selector="edit-field-entity-reference1-current-items-0-replace-button"]');
    // Clicking on the button should empty the selection and automatically
    // open the browser again.
    $replace_button->click();
    $this->waitForAjaxToFinish();
    $this->getSession()->switchToIFrame('entity_browser_iframe_test_entity_browser_iframe_node_view');
    $this->assertSession()->fieldExists('entity_browser_select[node:2]')->check();
    $this->assertSession()->buttonExists('Select entities')->press();
    $this->waitForAjaxToFinish();
    $this->getSession()->switchToIFrame();

    if (!$this->coreVersion('10.2')) {
      $this->assertSession()->assertWaitOnAjaxRequest();
    }

    $this->assertSession()->elementContains('css', '#edit-field-entity-reference1-wrapper', 'Referencing node 1');

    // Verify that if the user cannot edit the entity, the "Edit" button does
    // not show up, even if configured to.
    /** @var \Drupal\user\RoleInterface $role */
    $role = Role::load('authenticated');
    $role->revokePermission('bypass node access')->trustData()->save();
    $this->drupalGet('node/add/article');
    $open_iframe_link = $this->assertSession()->elementExists('css', 'a[data-drupal-selector="edit-field-entity-reference1-entity-browser-entity-browser-link"]');
    $open_iframe_link->click();
    $this->getSession()->switchToIFrame('entity_browser_iframe_test_entity_browser_iframe_node_view');
    $this->assertSession()->fieldExists('entity_browser_select[node:1]')->check();
    $this->assertSession()->buttonExists('Select entities')->press();
    $this->waitForAjaxToFinish();
    $this->getSession()->switchToIFrame();

    if (!$this->coreVersion('10.2')) {
      $this->assertSession()->assertWaitOnAjaxRequest();
    }

    $this->assertSession()->buttonNotExists('edit-field-entity-reference1-current-items-0-edit-button');
  }

  /**
   * Tests that drag and drop functions properly.
   */
  public function testDragAndDrop() {
    $assert_session = $this->assertSession();

    $time = time();

    $gatsby = $this->createNode([
      'type' => 'shark',
      'title' => 'Gatsby',
      'created' => $time--,
    ]);
    $daisy = $this->createNode([
      'type' => 'jet',
      'title' => 'Daisy',
      'created' => $time--,
    ]);
    $nick = $this->createNode([
      'type' => 'article',
      'title' => 'Nick',
      'created' => $time--,
    ]);

    $santa = $this->createNode([
      'type' => 'shark',
      'title' => 'Santa Claus',
      'created' => $time--,
    ]);
    $easter_bunny = $this->createNode([
      'type' => 'jet',
      'title' => 'Easter Bunny',
      'created' => $time--,
    ]);
    $pumpkin_king = $this->createNode([
      'type' => 'article',
      'title' => 'Pumpkin King',
      'created' => $time--,
    ]);

    $field1_storage_config = [
      'field_name' => 'field_east_egg',
      'type' => 'entity_reference',
      'entity_type' => 'node',
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
      'settings' => [
        'target_type' => 'node',
      ],
    ];

    $field2_storage_config = [
      'field_name' => 'field_east_egg2',
    ] + $field1_storage_config;

    $field_storage = FieldStorageConfig::create($field1_storage_config);
    $field_storage->save();

    $field_storage2 = FieldStorageConfig::create($field2_storage_config);
    $field_storage2->save();

    $field1_config = [
      'field_name' => 'field_east_egg',
      'entity_type' => 'node',
      'bundle' => 'article',
      'label' => 'East Eggers',
      'settings' => [
        'handler_settings' => [
          'target_bundles' => [
            'shark' => 'shark',
            'jet' => 'jet',
            'article' => 'article',
          ],
        ],
      ],
    ];

    $field2_config = [
      'field_name' => 'field_east_egg2',
      'label' => 'Easter Eggs',
    ] + $field1_config;

    $field = FieldConfig::create($field1_config);
    $field->save();

    $field2 = FieldConfig::create($field2_config);
    $field2->save();

    /** @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface $form_display */
    $form_display = $this->container->get('entity_type.manager')
      ->getStorage('entity_form_display')
      ->load('node.article.default');

    $form_display->removeComponent('field_reference');

    $field_widget_config = [
      'type' => 'entity_browser_entity_reference',
      'settings' => [
        'entity_browser' => 'widget_context_default_value',
        'table_settings' => [
          'status_column' => TRUE,
          'bundle_column' => TRUE,
          'label_column' => FALSE,
        ],
        'open' => FALSE,
        'field_widget_edit' => TRUE,
        'field_widget_remove' => TRUE,
        'field_widget_replace' => FALSE,
        'selection_mode' => EntityBrowserElement::SELECTION_MODE_APPEND,
        'field_widget_display' => 'label',
        'field_widget_display_settings' => [],
      ],
    ];

    $form_display->setComponent('field_east_egg', $field_widget_config)->save();
    $form_display->setComponent('field_east_egg2', $field_widget_config)->save();

    // Set auto open to false on the entity browser.
    $entity_browser = $this->container->get('entity_type.manager')
      ->getStorage('entity_browser')
      ->load('widget_context_default_value');

    $display_configuration = $entity_browser->get('display_configuration');
    $display_configuration['auto_open'] = FALSE;
    $entity_browser->set('display_configuration', $display_configuration);
    $entity_browser->save();

    $account = $this->drupalCreateUser([
      'access widget_context_default_value entity browser pages',
      'create article content',
      'access content',
    ]);
    $this->drupalLogin($account);

    $this->drupalGet('node/add/article');

    $this->assertSession()->elementExists('xpath', '(//summary)[1]')->click();

    // Open the entity browser widget form.
    $this->getSession()->getPage()->clickLink('Select entities');
    $this->getSession()->switchToIFrame('entity_browser_iframe_widget_context_default_value');
    $this->assertSession()->fieldExists('entity_browser_select[node:' . $gatsby->id() . ']')->check();
    $this->assertSession()->fieldExists('entity_browser_select[node:' . $daisy->id() . ']')->check();
    $this->assertSession()->fieldExists('entity_browser_select[node:' . $nick->id() . ']')->check();
    $this->assertSession()->buttonExists('Select entities')->press();
    $this->assertSession()->buttonExists('Use selected')->press();
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->switchToIFrame();

    if (!$this->coreVersion('10.2')) {
      $this->assertSession()->assertWaitOnAjaxRequest();
    }

    $correct_order = [
      1 => 'Gatsby',
      2 => 'Daisy',
      3 => 'Nick',
    ];
    foreach ($correct_order as $key => $value) {
      $this->assertSession()
        ->elementContains('xpath', "(//div[contains(@class, 'item-container')])[" . $key . "]", $value);
    }

    // Close details 1.
    $this->assertSession()->elementExists('xpath', '(//summary)[1]')->click();
    // Open details 2.
    $this->assertSession()->elementExists('xpath', '(//summary)[2]')->click();

    // Open the entity browser widget form.
    $this->assertSession()->elementExists('xpath', "(//a[contains(text(), 'Select entities')])[2]")->click();
    $this->getSession()->switchToIFrame('entity_browser_iframe_widget_context_default_value');

    $this->assertSession()->fieldExists('entity_browser_select[node:' . $santa->id() . ']')->check();
    $this->assertSession()->fieldExists('entity_browser_select[node:' . $easter_bunny->id() . ']')->check();
    $this->assertSession()->fieldExists('entity_browser_select[node:' . $pumpkin_king->id() . ']')->check();
    $this->assertSession()->buttonExists('Select entities')->press();
    $this->assertSession()->buttonExists('Use selected')->press();
    $this->waitForAjaxToFinish();
    $this->getSession()->switchToIFrame();

    if (!$this->coreVersion('10.2')) {
      $this->assertSession()->assertWaitOnAjaxRequest();
    }

    // Close details 2.
    $this->assertSession()->elementExists('xpath', '(//summary)[2]')->click();
    // Open details 1.
    $this->assertSession()->elementExists('xpath', '(//summary)[1]')->click();

    // In the first set of selections, drag the first item into the second
    // position.
    $list_selector = '[data-drupal-selector="edit-field-east-egg-current"]';
    $item_selector = "$list_selector .item-container";
    $assert_session->elementsCount('css', $item_selector, 3);
    $this->sortableAfter("$item_selector:first-child", "$item_selector:nth-child(2)", $list_selector);

    $this->assertSession()->fieldExists('title[0][value]')->setValue('Hello World');

    $this->assertSession()->buttonExists('Save')->press();

    $this->drupalGet('node/7/edit');

    $correct_order = [
      1 => 'Daisy',
      2 => 'Gatsby',
      3 => 'Nick',
      4 => 'Santa Claus',
      5 => 'Easter Bunny',
      6 => 'Pumpkin King',
    ];
    foreach ($correct_order as $key => $value) {
      $this->assertSession()
        ->elementContains('xpath', "(//div[contains(@class, 'item-container')])[" . $key . "]", $value);
    }

    // In the second set of selections, drag the first item into the second
    // position.
    $list_selector = '[data-drupal-selector="edit-field-east-egg2-current"]';
    $item_selector = "$list_selector .item-container";
    $assert_session->elementsCount('css', $item_selector, 3);
    $this->sortableAfter("$item_selector:first-child", "$item_selector:nth-child(2)", $list_selector);

    $correct_order = [
      4 => 'Easter Bunny',
      5 => 'Santa Claus',
      6 => 'Pumpkin King',
    ];
    foreach ($correct_order as $key => $value) {
      $this->assertSession()
        ->elementContains('xpath', "(//div[contains(@class, 'item-container')])[" . $key . "]", $value);
    }

    // Test that order is preserved after removing item.
    $this->assertSession()
      ->elementExists('xpath', '(//input[contains(@class, "remove-button")])[5]')
      ->press();

    $this->waitForAjaxToFinish();

    $correct_order = [
      4 => 'Easter Bunny',
      5 => 'Pumpkin King',
    ];

    foreach ($correct_order as $key => $value) {
      $this->assertSession()
        ->elementContains('xpath', "(//div[contains(@class, 'item-container')])[" . $key . "]", $value);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function sortableUpdate($item, $from, $to = NULL) {
    [$container] = explode(' ', $item, 2);

    $js = <<<END
Drupal.entityBrowserEntityReference.entitiesReordered(document.querySelector("$container"));
END;
    $this->getSession()->executeScript($js);
  }

}
