<?php

namespace Drupal\Tests\entity_browser\Functional;

use Drupal\entity_browser\Element\EntityBrowserElement;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests the entity browser form element.
 *
 * @group entity_browser
 */
class FormElementTest extends BrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['entity_browser_test', 'node', 'views'];

  /**
   * Test nodes.
   *
   * @var \Drupal\node\NodeInterface[]
   */
  protected $nodes;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->container
      ->get('entity_type.manager')
      ->getStorage('node_type')
      ->create([
        'type' => 'page',
        'name' => 'page',
      ])->save();

    $this->nodes[] = $this->drupalCreateNode();
    $this->nodes[] = $this->drupalCreateNode();
  }

  /**
   * Tests the Entity browser form element.
   */
  public function testFormElement() {
    // See \Drupal\entity_browser_test\Form\FormElementTest.
    $this->drupalGet('/test-element');
    $this->assertSession()->linkExists('Select entities', 0, 'Trigger link found.');

    $ids = [
      $this->nodes[0]->getEntityTypeId() . ':' . $this->nodes[0]->id(),
      $this->nodes[1]->getEntityTypeId() . ':' . $this->nodes[1]->id(),
    ];

    $ids = implode(' ', $ids);

    $this->assertSession()->hiddenFieldExists("fancy_entity_browser[entity_ids]")->setValue($ids);

    $this->assertSession()->buttonExists('Submit')->press();
    $expected = 'Selected entities: ' . $this->nodes[0]->label() . ', ' . $this->nodes[1]->label();
    $this->assertSession()->responseContains($expected);

    $default_entity = $this->nodes[0]->getEntityTypeId() . ':' . $this->nodes[0]->id();
    $this->drupalGet('/test-element', ['query' => ['default_entity' => $default_entity, 'selection_mode' => EntityBrowserElement::SELECTION_MODE_EDIT]]);
    $this->assertSession()->linkExists('Select entities', 0, 'Trigger link found.');

    $this->assertSession()->hiddenFieldValueEquals("fancy_entity_browser[entity_ids]", $default_entity);
    $hidden_field = $this->assertSession()->hiddenFieldExists("fancy_entity_browser[entity_ids]");
    $new_value = 'node:' . $this->nodes[1]->id() . ' node:' . $this->nodes[0]->id();
    $hidden_field->setValue($new_value);

    $this->submitForm([], 'Submit');
    $expected = 'Selected entities: ' . $this->nodes[1]->label() . ', ' . $this->nodes[0]->label();
    $this->assertSession()->responseContains($expected);
  }

}
