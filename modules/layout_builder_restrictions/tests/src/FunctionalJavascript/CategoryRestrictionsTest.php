<?php

declare(strict_types=1);

namespace Drupal\Tests\layout_builder_restrictions\FunctionalJavascript;

/**
 * Demonstrate that blocks can be restricted by category.
 *
 * @group layout_builder_restrictions
 */
class CategoryRestrictionsTest extends LayoutBuilderRestrictionsTestBase {

  /**
   * Verify that the UI can restrict layouts in Layout Builder settings tray.
   */
  public function testLayoutRestriction() {
    $this->getSession()->resizeWindow(1200, 4000);
    // Create 2 custom block types, with 3 block instances.
    $this->generateTestBlocks();
    $node_id = $this->generateTestNode();
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    // Baseline: Inline blocks are allowed.
    // Establish baseline behavior prior to any restrictions.
    $this->navigateToNodeSettingsTray($node_id);
    // Initially, the body field is available.
    $assert_session->linkExists('Body');
    // Initially, custom blocks instances are available.
    $assert_session->linkExists('Basic Block 1');
    $assert_session->linkExists('Basic Block 2');
    $assert_session->linkExists('Alternate Block 1');
    // Initially, all inline block types are allowed.
    $this->clickLink('Create content block');
    $this->assertNotEmpty($assert_session->waitForText('Add a new content block'));
    $assert_session->linkExists('Basic');
    $assert_session->linkExists('Alternate');

    $this->navigateToManageDisplay();
    $element = $page->find('xpath', '//*[@id="edit-layout-layout-builder-restrictions-allowed-blocks"]/summary');
    $element->click();
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-layouts-layout-restriction-all"]');
    $assert_session->checkboxChecked('edit-layout-builder-restrictions-allowed-layouts-layout-restriction-all');
    $assert_session->checkboxNotChecked('edit-layout-builder-restrictions-allowed-layouts-layout-restriction-restricted');

    // Restrict all Inline blocks as a category.
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-blocks-inline-blocks-restriction-restrict-all"]');
    $element->click();
    $page->pressButton('Save');

    // Verify Inline blocks, as a category, are restricted.
    $this->navigateToNodeSettingsTray($node_id);
    // Existing custom block instances are still present
    // because we did not restrict 'Custom block types'.
    $assert_session->linkExists('Body');
    $assert_session->linkExists('Basic Block 1');
    $assert_session->linkExists('Basic Block 2');
    $assert_session->linkExists('Alternate Block 1');
    // New inline blocks of any type cannot be created.
    $assert_session->linkNotExists('Create content block');

    $this->navigateToManageDisplay();
    $element = $page->find('xpath', '//*[@id="edit-layout-layout-builder-restrictions-allowed-blocks"]/summary');
    $element->click();
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-layouts-layout-restriction-all"]');

    // Restrict existing Custom blocks as a category.
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-blocks-custom-block-types-restriction-restrict-all"]');
    $element->click();
    $page->pressButton('Save');
    // Existing Custom blocks are restricted categorically.
    $this->navigateToNodeSettingsTray($node_id);
    $assert_session->linkNotExists('Basic Block 1');
    $assert_session->linkNotExists('Basic Block 2');
    $assert_session->linkNotExists('Alternate Block 1');
    // Content fields are still allowed.
    $assert_session->linkExists('Body');
  }

}
