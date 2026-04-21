<?php

declare(strict_types=1);

namespace Drupal\Tests\layout_builder_restrictions\FunctionalJavascript;

/**
 * Demonstrate that blocks can be individually restricted.
 *
 * @group layout_builder_restrictions
 */
class LayoutRestrictionsTest extends LayoutBuilderRestrictionsTestBase {

  /**
   * Verify that the UI can restrict layouts in Layout Builder settings tray.
   */
  public function testLayoutRestriction() {
    // Create 2 custom block types, with 3 block instances.
    $this->generateTestBlocks();
    $node_id = $this->generateTestNode();
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    // Baseline: 'One column' & 'Two column' layouts are available.
    $this->navigateToNodeSettingsTray($node_id);
    $this->clickLink('Add section');
    $this->assertNotEmpty($assert_session->waitForText('Choose a layout for this section'));
    $assert_session->linkExists('One column');
    $assert_session->linkExists('Two column');

    // Allow only 'Two column' layout.
    $this->navigateToManageDisplay();
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-layouts"]/summary');
    $element->click();
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-layouts-layout-restriction-all"]');
    $assert_session->checkboxChecked('edit-layout-builder-restrictions-allowed-layouts-layout-restriction-all');
    $assert_session->checkboxNotChecked('edit-layout-builder-restrictions-allowed-layouts-layout-restriction-restricted');
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-layouts-layout-restriction-restricted"]');
    $element->click();
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-layouts-layouts-layout-twocol-section"]');
    $element->click();
    $page->pressButton('Save');

    // Verify 'Two column' is allowed, 'One column' restricted.
    $this->navigateToNodeSettingsTray($node_id);
    $this->clickLink('Add section');
    $this->assertNotEmpty($assert_session->waitForText('Choose a layout for this section'));
    $assert_session->linkNotExists('One column');
    $assert_session->linkExists('Two column');
  }

}
