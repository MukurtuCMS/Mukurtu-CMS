<?php

declare(strict_types=1);

namespace Drupal\Tests\layout_builder_restrictions\FunctionalJavascript;

/**
 * Demonstrate that blocks can be individually restricted.
 *
 * @group layout_builder_restrictions
 */
class DefaultRestrictionsTest extends LayoutBuilderRestrictionsTestBase {

  /**
   * When new categories are restricted, a newly available block is restricted.
   */
  public function testNewCategoriesRestricted() {
    // Create 2 custom block types, with 3 block instances.
    $this->generateTestBlocks();
    $node_id = $this->generateTestNode();
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    // Impose Custom Block type restrictions.
    $this->navigateToManageDisplay();
    // Restrict all new block categories.
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-block-categories-restricted"]');
    $element->click();
    $page->pressButton('Save');

    // Enable a test module, which provides a plugin block.
    $this->container->get('module_installer')->install(['test_layout_builder_restrictions']);

    $this->navigateToNodeSettingsTray($node_id);
    // The 'Test' block is not allowed, even though there isn't a specific
    // restriction.
    $assert_session->linkNotExists('Test Block');
    // Other blocks are allowed because they were captured in the configuration
    // save, above.
    $this->clickLink('Create content block');
    $this->assertNotEmpty($assert_session->waitForText('Add a new content block'));
    $assert_session->linkExists('Basic');
    $assert_session->linkExists('Alternate');
  }

  /**
   * When new categories are allowed, a newly available block is allowed.
   */
  public function testNewCategoriesAllowed() {
    // Create 2 custom block types, with 3 block instances.
    $this->generateTestBlocks();
    $node_id = $this->generateTestNode();
    $this->getSession()->resizeWindow(1200, 2000);
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    // From the manage display page, go to manage the layout.
    $this->navigateToManageDisplay();
    // Checking is_enable will show allow_custom.
    $page->checkField('layout[enabled]');
    $page->checkField('layout[allow_custom]');
    $page->pressButton('Save');

    // Allow all new block categories.
    $this->navigateToManageDisplay();
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-block-categories-allowed"]');
    $element->click();
    $page->pressButton('Save');

    // Enable a test module, which provides a plugin block.
    $this->container->get('module_installer')->install(['test_layout_builder_restrictions']);

    $this->navigateToNodeSettingsTray($node_id);
    // The new test block is allowed.
    $assert_session->linkExists('Test Block');
    // Other blocks are allowed because they were captured in the configuration
    // save, above.
    $this->clickLink('Create content block');
    $this->assertNotEmpty($assert_session->waitForText('Add a new content block'));
    $assert_session->linkExists('Basic');
    $assert_session->linkExists('Alternate');
  }

}
