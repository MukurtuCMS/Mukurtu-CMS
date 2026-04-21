<?php

namespace Drupal\Tests\field_group\FunctionalJavascript;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\field_group\Functional\FieldGroupTestTrait;

/**
 * Tests for form display.
 *
 * @group field_group
 */
class EntityFormTest extends WebDriverTestBase {

  use FieldGroupTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'field_test',
    'field_ui',
    'field_group',
    'field_group_test',
  ];

  /**
   * The node type id.
   *
   * @var string
   */
  protected $type;

  /**
   * A node to use for testing.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    // Create test user.
    $admin_user = $this->drupalCreateUser([
      'access content',
      'administer content types',
      'administer node fields',
      'administer node form display',
      'administer node display',
      'bypass node access',
    ]);
    $this->drupalLogin($admin_user);

    // Create content type, with underscores.
    $type_name = strtolower($this->randomMachineName(8)) . '_test';
    $type = $this->drupalCreateContentType([
      'name' => $type_name,
      'type' => $type_name,
    ]);
    $this->type = $type->id();

    // Create required test field.
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'field_test',
      'entity_type' => 'node',
      'type' => 'test_field',
    ]);
    $field_storage->save();

    $instance = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => $type_name,
      'label' => 'field_test',
      'required' => TRUE,
    ]);
    $instance->save();

    /** @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface $form_display */
    $form_display = EntityFormDisplay::load('node.' . $this->type . '.default');

    // Set the field visible on the form display object.
    $display_options = [
      'type' => 'string_textfield',
      'region' => 'content',
      'settings' => [
        'size' => 60,
      ],
    ];
    $form_display->setComponent('field_test', $display_options);

    // Save the form display.
    $form_display->save();
  }

  /**
   * Tests required field validation visibility.
   */
  public function testRequiredFieldValidationVisibility() {
    $data = [
      'label' => 'Tab 1',
      'weight' => '1',
      'children' => [
        0 => 'title',
        1 => 'body',
      ],
      'format_type' => 'tab',
      'format_settings' => [
        'label' => 'Tab 1',
        'classes' => 'test-class',
        'description' => '',
        'formatter' => 'open',
      ],
    ];
    $first_tab = $this->createGroup('node', $this->type, 'form', 'default', $data);

    $data = [
      'label' => 'Field group details',
      'weight' => '1',
      'children' => [
        0 => 'field_test',
      ],
      'format_type' => 'details',
      'format_settings' => [
        'open' => FALSE,
        'required_fields' => TRUE,
      ],
    ];
    $field_group_details = $this->createGroup('node', $this->type, 'form', 'default', $data);

    $data = [
      'label' => 'Tab 2',
      'weight' => '1',
      'children' => [
        0 => $field_group_details->group_name,
      ],
      'format_type' => 'tab',
      'format_settings' => [
        'label' => 'Tab 1',
        'classes' => 'test-class-2',
        'description' => 'description of second tab',
        'formatter' => 'closed',
      ],
    ];
    $second_tab = $this->createGroup('node', $this->type, 'form', 'default', $data);

    $data = [
      'label' => 'Tabs',
      'weight' => '1',
      'children' => [
        0 => $first_tab->group_name,
        1 => $second_tab->group_name,
      ],
      'format_type' => 'tabs',
      'format_settings' => [
        'direction' => 'vertical',
        'label' => 'Tab 1',
        'classes' => 'test-class-wrapper',
      ],
    ];
    $tabs_group = $this->createGroup('node', $this->type, 'form', 'default', $data);

    // Load the node creation page.
    $this->drupalGet('node/add/' . $this->type);

    // Test if it's a vertical tab.
    $this->assertSession()->elementExists('xpath', $this->assertSession()
      ->buildXPathQuery('//div[@data-vertical-tabs-panes=""]'));
    $this->requiredFieldVisibilityAssertions();

    // Switch to horizontal.
    $tabs_group->format_settings['direction'] = 'horizontal';
    field_group_group_save($tabs_group);

    // Reload the node creation page.
    $this->drupalGet('node/add/' . $this->type);

    // Test if it's a horizontal tab.
    $this->assertSession()->elementExists('xpath', $this->assertSession()
      ->buildXPathQuery('//div[@data-horizontal-tabs-panes=""]'));
    $this->requiredFieldVisibilityAssertions();
  }

  /**
   * Tests the required field_test to assert its visibility.
   */
  private function requiredFieldVisibilityAssertions(): void {
    // Assert that the required field, field_test is present but not visible.
    $this->assertSession()->fieldExists('field_test');
    $this->assertFalse($this->getSession()
      ->getDriver()
      ->isVisible($this->cssSelectToXpath('input[name="field_test[0][value]"]')));

    // Submit the form without filling any required field.
    $this->getSession()->getPage()->pressButton('Save');

    // Assert that the field_test is not visible because it's in the first tab.
    $this->assertFalse($this->getSession()
      ->getDriver()
      ->isVisible($this->cssSelectToXpath('input[name="field_test[0][value]"]')));

    // Fill in the title field and leave the required field_test empty.
    $this->getSession()->getPage()->fillField('Title', 'Node title');
    $this->getSession()->getPage()->pressButton('Save');

    // Assert that the field_test is visible because the second tab is in focus
    // and the collapsible field group is open.
    $this->assertTrue($this->getSession()
      ->getDriver()
      ->isVisible($this->cssSelectToXpath('input[name="field_test[0][value]"]')));
  }

}
