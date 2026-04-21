<?php

declare(strict_types=1);

namespace Drupal\Tests\layout_builder_restrictions_by_region\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Block restrictions can be removed or preserved based on config setting.
 *
 * @group layout_builder_restrictions_by_region
 */
class RetainLayoutRestrictionsTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'layout_builder',
    'layout_builder_restrictions',
    'layout_builder_restrictions_by_region',
    'node',
    'field_ui',
    'block_content',
  ];

  /**
   * Specify the theme to be used in testing.
   *
   * @var string
   */
  protected $defaultTheme = 'olivero';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create a node bundle.
    $this->createContentType(['type' => 'bundle_with_section_field']);

    $this->drupalLogin($this->drupalCreateUser([
      'access administration pages',
      'administer blocks',
      'administer node display',
      'administer node fields',
      'configure any layout',
      'configure layout builder restrictions',
      'create and edit custom blocks',
    ]));
  }

  /**
   * Demonstrate that layout restrictions are removed after layout removal.
   */
  public function testRemovedRestrictions() {
    $this->getSession()->resizeWindow(1200, 4000);
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    // Add and restrict, then remove a layout.
    $this->addThenRemoveLayout($page, $assert_session);

    // The block restriction configuration for the onecol layout is removed.
    $config = \Drupal::service('config.factory')->get('core.entity_view_display.node.bundle_with_section_field.default');
    $settings = $config->get('third_party_settings');
    $this->assertEquals([], $settings['layout_builder_restrictions']['entity_view_mode_restriction_by_region']['allowed_layouts']);
    $this->assertEquals([], $settings['layout_builder_restrictions']['entity_view_mode_restriction_by_region']['restricted_categories']);
  }

  /**
   * Demonstrate that layout restrictions can be retained after removal.
   */
  public function testRetainedRestrictions() {
    $this->getSession()->resizeWindow(1200, 4000);
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    // Set global configuration to retain layout restrictions after removal.
    $config = \Drupal::service('config.factory')->getEditable('layout_builder_restrictions_by_region.settings');
    $config->set('retain_restrictions_after_layout_removal', 1)->save();

    // Add and restrict, then remove a layout.
    $this->addThenRemoveLayout($page, $assert_session);

    // The block restriction configuration for the onecol layout is retained.
    $config = \Drupal::service('config.factory')->get('core.entity_view_display.node.bundle_with_section_field.default');
    $settings = $config->get('third_party_settings');
    $this->assertEquals([], $settings['layout_builder_restrictions']['entity_view_mode_restriction_by_region']['allowed_layouts']);
    $this->assertEquals(['Content fields'], $settings['layout_builder_restrictions']['entity_view_mode_restriction_by_region']['restricted_categories']['layout_onecol']['all_regions']);
  }

  /**
   * UI manipulation that adds a layout with restrictions then removes it.
   */
  protected function addThenRemoveLayout($page, $assert_session) {
    $field_ui_prefix = 'admin/structure/types/manage/bundle_with_section_field';

    // From the manage display page, go to manage the layout.
    $this->drupalGet("$field_ui_prefix/display/default");
    // Checking is_enable will show allow_custom.
    $page->checkField('layout[enabled]');
    $page->checkField('layout[allow_custom]');
    $page->pressButton('Save');
    $assert_session->linkExists('Manage layout');

    // Allow One Column Layout, with all-region restriction.
    $this->drupalGet("$field_ui_prefix/display/default");
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-layouts"]/summary');
    $element->click();
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-layouts-layout-restriction-restricted"]');
    $element->click();
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-layouts-layouts-layout-onecol"]');
    $element->click();
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-blocks-by-layout-layout-onecol"]/summary');
    $element->click();
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-blocks-by-layout-layout-onecol-table"]/tbody/tr[@data-region="all_regions"]//a');
    $element->click();
    $this->assertNotEmpty($assert_session->waitForText('Allowed blocks'));
    // Set 'Content' fields category to be restricted.
    $element = $page->find('xpath', '//*[contains(@class, "form-item-allowed-blocks-content-fields-restriction")]/input[@value="restrict_all"]');
    $element->click();
    $element = $page->find('xpath', '//*[starts-with(@id,"edit-submit--")]');
    $element->click();
    $this->assertNotEmpty($assert_session->waitForText('Save'));
    $page->pressButton('Save');
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-blocks-by-layout-layout-onecol"]/summary');
    $element->click();
    $assert_session->elementNotContains('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-blocks-by-layout-layout-onecol-table"]/tbody/tr[@data-region="all_regions"]', 'Unrestricted');

    // Remove one column layout from allowed list.
    $this->drupalGet("$field_ui_prefix/display/default");
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-layouts"]/summary');
    $element->click();
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-layouts-layout-restriction-restricted"]');
    $element->click();
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-layouts-layouts-layout-onecol"]');
    $element->click();
    $page->pressButton('Save');
    $this->assertTrue($assert_session->waitForText('Your settings have been saved.'));
  }

}
