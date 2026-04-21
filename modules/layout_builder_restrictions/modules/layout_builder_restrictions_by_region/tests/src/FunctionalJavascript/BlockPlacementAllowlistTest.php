<?php

declare(strict_types=1);

namespace Drupal\Tests\layout_builder_restrictions_by_region\FunctionalJavascript;

use Drupal\Tests\layout_builder_restrictions\FunctionalJavascript\LayoutBuilderRestrictionsTestBase;

/**
 * Demonstrate that blocks can be individually restricted.
 *
 * @group layout_builder_restrictions_by_region
 */
class BlockPlacementAllowlistTest extends LayoutBuilderRestrictionsTestBase {

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
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->drupalLogin($this->drupalCreateUser([
      'access administration pages',
      'administer blocks',
      'administer node display',
      'administer node fields',
      'configure any layout',
      'configure layout builder restrictions',
      'administer block content',
      'create and edit custom blocks',
    ]));

    // Enable entity_view_mode_restriction_by_region plugin.
    // Disable entity_view_mode_restriction plugin.
    $layout_builder_restrictions_plugins = [
      'entity_view_mode_restriction' => [
        'weight' => 1,
        'enabled' => FALSE,
      ],
      'entity_view_mode_restriction_by_region' => [
        'weight' => 0,
        'enabled' => TRUE,
      ],
    ];
    $config = \Drupal::service('config.factory')->getEditable('layout_builder_restrictions.plugins');
    $config->set('plugin_config', $layout_builder_restrictions_plugins)->save();
    $this->getSession()->resizeWindow(900, 2000);
  }

  /**
   * Verify that both tempstore and config storage function correctly.
   */
  public function testBlockRestrictionStorage() {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    // Only allow two-column layout.
    $this->navigateToManageDisplay();
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-layouts"]/summary');
    $element->click();
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-layouts-layout-restriction-restricted"]');
    $element->click();
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-layouts-layouts-layout-twocol-section"]');
    $element->click();

    // Verify form behavior when restriction is applied to all regions.
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-blocks-by-layout-layout-twocol-section"]/summary');
    $element->click();
    $assert_session->checkboxChecked('edit-layout-builder-restrictions-allowed-blocks-by-layout-layout-twocol-section-restriction-behavior-all');
    $assert_session->elementContains('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-blocks-by-layout-layout-twocol-section-table"]/tbody/tr[@data-region="all_regions"]', 'All regions');
    $assert_session->elementContains('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-blocks-by-layout-layout-twocol-section-table"]/tbody/tr[@data-region="all_regions"]', 'Unrestricted');

    // Verify form behavior when restriction is applied on a per-region basis.
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-blocks-by-layout-layout-twocol-section-restriction-behavior-per-region"]');
    $element->click();
    $assert_session->checkboxChecked('edit-layout-builder-restrictions-allowed-blocks-by-layout-layout-twocol-section-restriction-behavior-per-region');
    $assert_session->elementContains('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-blocks-by-layout-layout-twocol-section-table"]/tbody/tr[@data-region="first"]', 'First');
    $assert_session->elementContains('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-blocks-by-layout-layout-twocol-section-table"]/tbody/tr[@data-region="first"]', 'Unrestricted');
    $assert_session->elementContains('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-blocks-by-layout-layout-twocol-section-table"]/tbody/tr[@data-region="second"]', 'Second');
    $assert_session->elementContains('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-blocks-by-layout-layout-twocol-section-table"]/tbody/tr[@data-region="second"]', 'Unrestricted');

    // Test temporary storage.
    // Add restriction to First region.
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-blocks-by-layout-layout-twocol-section-table"]/tbody/tr[@data-region="first"]//a');
    $element->click();
    $this->assertNotEmpty($assert_session->waitForText('Allow all existing & new Content fields blocks.'));
    $assert_session->checkboxChecked('Allow all existing & new Content fields blocks.');
    $assert_session->checkboxNotChecked('Allow specific Content fields blocks:');

    // Restrict all 'Content' fields from options.
    $element = $page->find('xpath', '//*[starts-with(@id,"edit-allowed-blocks-content-fields-restriction-allowlisted--")]');
    $element->click();
    $element = $page->find('xpath', '//*[starts-with(@id,"edit-submit--")]');
    $element->click();

    // Verify First region is 'Restricted' and Second region
    // remains 'Unrestricted'.
    $assert_session->elementContains('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-blocks-by-layout-layout-twocol-section-table"]/tbody/tr[@data-region="first"]', 'Restricted');
    $assert_session->elementContains('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-blocks-by-layout-layout-twocol-section-table"]/tbody/tr[@data-region="second"]', 'Unrestricted');

    // Reload First region allowed block form to verify temp storage.
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-blocks-by-layout-layout-twocol-section-table"]/tbody/tr[@data-region="first"]//a');
    $element->click();
    $this->assertNotEmpty($assert_session->waitForText('Allow all existing & new Content fields blocks.'));
    $assert_session->checkboxNotChecked('Allow all existing & new Content fields blocks.');
    $assert_session->checkboxChecked('Allow specific Content fields blocks:');
    $page->pressButton('Close');

    // Load Second region allowed block form to verify temp storage.
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-blocks-by-layout-layout-twocol-section-table"]/tbody/tr[@data-region="second"]//a');
    $element->click();
    $this->assertNotEmpty($assert_session->waitForText('Allow all existing & new Content fields blocks.'));
    $assert_session->checkboxChecked('Allow all existing & new Content fields blocks.');
    $assert_session->checkboxNotChecked('Allow specific Content fields blocks:');
    $page->pressButton('Close');

    // Verify All Regions unaffected.
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-blocks-by-layout-layout-twocol-section-restriction-behavior-all"]');
    $element->click();
    $assert_session->elementContains('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-blocks-by-layout-layout-twocol-section-table"]/tbody/tr[@data-region="all_regions"]', 'Unrestricted');
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-blocks-by-layout-layout-twocol-section-table"]/tbody/tr[@data-region="all_regions"]//a');
    $element->click();
    $this->assertNotEmpty($assert_session->waitForText('Allow all existing & new Content fields blocks.'));
    $assert_session->checkboxChecked('Allow all existing & new Content fields blocks.');
    $assert_session->checkboxNotChecked('Allow specific Content fields blocks:');
    $page->pressButton('Close');

    // Switch back to Per-region restrictions prior to saving.
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-blocks-by-layout-layout-twocol-section-restriction-behavior-per-region"]');
    $element->click();

    // Allow one-column layout.
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-layouts-layouts-layout-onecol"]');
    $element->click();
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-blocks-by-layout-layout-onecol"]/summary');
    $element->click();
    $assert_session->elementContains('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-blocks-by-layout-layout-onecol-table"]/tbody/tr[@data-region="all_regions"]', 'Unrestricted');
    // Save to config.
    $page->pressButton('Save');

    // Verify no block restrictions bleed to other layouts/regions upon save
    // to database.
    $this->navigateToManageDisplay();
    // Check two-column layout.
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-blocks-by-layout-layout-twocol-section"]/summary');
    $element->click();
    $assert_session->elementContains('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-blocks-by-layout-layout-twocol-section-table"]/tbody/tr[@data-region="first"]', 'Restricted');
    $assert_session->elementContains('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-blocks-by-layout-layout-twocol-section-table"]/tbody/tr[@data-region="second"]', 'Unrestricted');

    // Verify All Regions unaffected.
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-blocks-by-layout-layout-twocol-section-restriction-behavior-all"]');
    $element->click();
    $assert_session->elementContains('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-blocks-by-layout-layout-twocol-section-table"]/tbody/tr[@data-region="all_regions"]', 'Unrestricted');

    // Check one-column layout.
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-blocks-by-layout-layout-onecol"]/summary');
    $element->click();
    $assert_session->elementContains('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-blocks-by-layout-layout-onecol-table"]/tbody/tr[@data-region="all_regions"]', 'Unrestricted');
  }

  /**
   * Verify that the UI can restrict blocks in Layout Builder settings tray.
   */
  public function testBlockRestriction() {
    $blocks = $this->generateTestBlocks();
    $node_id = $this->generateTestNode();
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    // Only allow two-column layout.
    $this->navigateToManageDisplay();
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-layouts"]/summary');
    $element->click();
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-layouts-layout-restriction-restricted"]');
    $element->click();
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-layouts-layouts-layout-twocol-section"]');
    $element->click();

    // Switch to per-region restriction.
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-blocks-by-layout-layout-twocol-section"]/summary');
    $element->click();
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-blocks-by-layout-layout-twocol-section-restriction-behavior-per-region"]');
    $element->click();
    $assert_session->elementContains('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-blocks-by-layout-layout-twocol-section-table"]/tbody/tr[@data-region="first"]', 'Restricted');
    $assert_session->elementContains('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-blocks-by-layout-layout-twocol-section-table"]/tbody/tr[@data-region="second"]', 'Unrestricted');
    $page->pressButton('Save');

    $this->navigateToNodeLayout($node_id);
    // Remove default one-column layout and replace with two-column layout.
    $this->clickLink('Remove Section 1');
    $this->assertNotEmpty($assert_session->waitForText('Are you sure'));
    $page->pressButton('Remove');
    $this->assertNotEmpty($assert_session->waitForText('Add section'));
    $this->clickLink('Add section');
    $this->assertNotEmpty($assert_session->waitForText('Two column'));
    $this->clickLink('Two column');
    $this->assertNotEmpty($assert_session->waitForText('Configure section'));
    $element = $page->find('xpath', '//*[contains(@class, "ui-dialog-off-canvas")]//*[starts-with(@id,"edit-actions-submit--")]');
    $element->click();
    $this->assertNotEmpty($assert_session->waitForElementRemoved('css', '.ui-dialog-off-canvas'));
    // Select 'Add block' link in First region.
    $element = $page->find('xpath', '//*[contains(@class, "layout__region--first")]//a');
    $element->click();
    $this->assertNotEmpty($assert_session->waitForLink('Body'));
    // Initially, the body field is available.
    $assert_session->linkExists('Body');
    // Initially, custom blocks instances are available.
    $assert_session->linkExists('Basic Block 1');
    $assert_session->linkExists('Basic Block 2');
    $assert_session->linkExists('Alternate Block 1');
    // Initially, all inline block types are allowed.
    $this->clickLink('Create content block');
    $this->assertNotEmpty($assert_session->waitForLink('Basic'));
    $assert_session->linkExists('Basic');
    $assert_session->linkExists('Alternate');
    $page->pressButton('Close');
    $page->pressButton('Save');

    // Load Allowed Blocks form for First region.
    $this->navigateToManageDisplay();
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-blocks-by-layout-layout-twocol-section"]/summary');
    $element->click();
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-blocks-by-layout-layout-twocol-section-restriction-behavior-per-region"]');
    $element->click();
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-blocks-by-layout-layout-twocol-section-table"]/tbody/tr[@data-region="first"]//a');
    $element->click();
    $this->assertNotEmpty($assert_session->waitForText('Allow all existing & new Content fields blocks.'));

    // Impose Custom Block type restrictions.
    $assert_session->checkboxChecked('Allow all existing & new Content fields blocks.');
    $assert_session->checkboxNotChecked('Allow specific Content fields blocks:');
    $assert_session->checkboxChecked('Allow all existing & new Custom block types blocks.');
    $assert_session->checkboxNotChecked('Allow specific Custom block types blocks:');

    // Restrict all 'Content' fields from options.
    $element = $page->find('xpath', '//*[contains(@class, "form-item-allowed-blocks-content-fields-restriction")]/input[@value="allowlisted"]');
    $element->click();
    // Restrict all Custom block types from options.
    $element = $page->find('xpath', '//*[contains(@class, "form-item-allowed-blocks-custom-block-types-restriction")]/input[@value="allowlisted"]');
    $element->click();
    $element = $page->find('xpath', '//*[starts-with(@id,"edit-submit--")]');
    $element->click();
    $this->assertNotEmpty($assert_session->waitForText('Save'));
    $page->pressButton('Save');

    $this->navigateToNodeLayout($node_id);

    // Select 'Add block' link in First region.
    $element = $page->find('xpath', '//*[contains(@class, "layout__region--first")]//a');
    $element->click();
    $this->assertNotEmpty($assert_session->waitForText('Choose a block'));
    $assert_session->linkNotExists('Body');
    $assert_session->linkNotExists('Basic Block 1');
    $assert_session->linkNotExists('Basic Block 2');
    $assert_session->linkNotExists('Alternate Block 1');
    // Inline block types are still allowed.
    $this->clickLink('Create content block');
    $this->assertNotEmpty($assert_session->waitForLink('Basic'));
    $assert_session->linkExists('Basic');
    $assert_session->linkExists('Alternate');

    // Impose Inline Block type restrictions.
    // Load Allowed Blocks form for First region.
    $this->navigateToManageDisplay();
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-blocks-by-layout-layout-twocol-section"]/summary');
    $element->click();
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-blocks-by-layout-layout-twocol-section-table"]/tbody/tr[@data-region="first"]//a');
    $element->click();
    $this->assertNotEmpty($assert_session->waitForText('Allow specific Content fields blocks:'));

    $assert_session->checkboxChecked('Allow specific Content fields blocks:');
    $assert_session->checkboxNotChecked('Allow all existing & new Content fields blocks.');
    $assert_session->checkboxChecked('Allow all existing & new Inline blocks blocks.');
    $assert_session->checkboxNotChecked('Allow specific Inline blocks blocks:');

    // Restrict all Inline blocks from options.
    $element = $page->find('xpath', '//*[starts-with(@id, "edit-allowed-blocks-inline-blocks-restriction-allowlisted--")]');
    $element->click();
    $element = $page->find('xpath', '//*[starts-with(@id,"edit-submit--")]');
    $element->click();
    $this->assertNotEmpty($assert_session->waitForText('Save'));
    $page->pressButton('Save');

    // Check independent restrictions on Custom block and Inline blocks.
    $this->navigateToNodeLayout($node_id);

    $element = $page->find('xpath', '//*[contains(@class, "layout__region--first")]//a');
    $element->click();
    $this->assertNotEmpty($assert_session->waitForText('Choose a block'));

    $assert_session->linkNotExists('Body');
    $assert_session->linkNotExists('Basic Block 1');
    $assert_session->linkNotExists('Basic Block 2');
    $assert_session->linkNotExists('Alternate Block 1');
    // Inline block types are not longer allowed.
    $assert_session->linkNotExists('Create content block');

    // Allowlist some blocks / block types.
    $this->navigateToManageDisplay();
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-blocks-by-layout-layout-twocol-section"]/summary');
    $element->click();
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-blocks-by-layout-layout-twocol-section-table"]/tbody/tr[@data-region="first"]//a');
    $element->click();
    $this->assertNotEmpty($assert_session->waitForText('Allow specific Content fields blocks:'));
    $assert_session->checkboxChecked('Allow specific Content fields blocks:');

    // Allow only 'body' field as an option.
    $page->checkField('allowed_blocks[Content fields][allowed_blocks][field_block:node:bundle_with_section_field:body]');
    // Allowlist all "basic" Custom block types.
    $page->checkField('allowed_blocks[Custom block types][allowed_blocks][basic]');
    // Allowlist "alternate" Inline block type.
    $page->checkField('allowed_blocks[Inline blocks][allowed_blocks][inline_block:alternate]');

    $element = $page->find('xpath', '//*[starts-with(@id,"edit-submit--")]');
    $element->click();
    $this->assertNotEmpty($assert_session->waitForText('Save'));
    $page->pressButton('Save');

    $this->navigateToNodeLayout($node_id);
    $this->clickLink('Add block');
    $this->assertNotEmpty($assert_session->waitForLink('Body'));
    $assert_session->linkExists('Body');
    // ... but other 'content' fields aren't.
    $assert_session->linkNotExists('Promoted to front page');
    $assert_session->linkNotExists('Sticky at top of lists');
    // "Basic" Content blocks are allowed.
    $assert_session->linkExists('Basic Block 1');
    $assert_session->linkExists('Basic Block 2');
    // ... but "alternate" Content block are disallowed.
    $assert_session->linkNotExists('Alternate Block 1');
    // Only the basic inline block type is allowed.
    $this->clickLink('Create content block');
    $this->assertNotEmpty($assert_session->waitForLink('Alternate'));
    $assert_session->linkNotExists('Basic');
    $assert_session->linkExists('Alternate');

    // Custom block instances take precedence over custom block type setting.
    $this->navigateToManageDisplay();
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-blocks-by-layout-layout-twocol-section"]/summary');
    $element->click();
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-blocks-by-layout-layout-twocol-section-table"]/tbody/tr[@data-region="first"]//a');
    $element->click();
    $this->assertNotEmpty($assert_session->waitForText('Allowed blocks'));

    $element = $page->find('xpath', '//*[starts-with(@id, "edit-allowed-blocks-custom-blocks-restriction-allowlisted--")]');
    $element->click();
    // Allow Alternate Block 1.
    $page->checkField('allowed_blocks[Custom blocks][allowed_blocks][block_content:' . $blocks['Alternate Block 1'] . ']');
    // Allow Basic Block 1.
    $page->checkField('allowed_blocks[Custom blocks][allowed_blocks][block_content:' . $blocks['Basic Block 1'] . ']');
    $element = $page->find('xpath', '//*[starts-with(@id,"edit-submit--")]');
    $element->click();
    $this->assertNotEmpty($assert_session->waitForText('Save'));
    $page->pressButton('Save');

    $this->navigateToNodeLayout($node_id);
    $element = $page->find('xpath', '//*[contains(@class, "layout__region--first")]//a');
    $element->click();
    $this->assertNotEmpty($assert_session->waitForLink('Basic Block 1'));
    $assert_session->linkExists('Basic Block 1');
    $assert_session->linkNotExists('Basic Block 2');
    $assert_session->linkExists('Alternate Block 1');

    // Next, add restrictions to another region and verify no contamination
    // between regions.
    // Add restriction to Second region.
    $this->navigateToManageDisplay();
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-blocks-by-layout-layout-twocol-section"]/summary');
    $element->click();
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-blocks-by-layout-layout-twocol-section-table"]/tbody/tr[@data-region="second"]//a');
    $element->click();
    $this->assertNotEmpty($assert_session->waitForText('Allowed blocks'));

    // System blocks are disallowed.
    $element = $page->find('xpath', '//*[starts-with(@id, "edit-allowed-blocks-system-restriction-allowlisted--")]');
    $element->click();
    $element = $page->find('xpath', '//*[starts-with(@id,"edit-submit--")]');
    $element->click();
    $this->assertNotEmpty($assert_session->waitForText('Save'));
    $page->pressButton('Save');

    $this->navigateToNodeLayout($node_id);

    $element = $page->find('xpath', '//*[contains(@class, "layout__region--first")]//a');
    $element->click();
    $this->assertNotEmpty($assert_session->waitForLink('Powered by Drupal'));
    $assert_session->linkExists('Powered by Drupal');
    $page->pressButton('Close');

    $element = $page->find('xpath', '//*[contains(@class, "layout__region--second")]//a');
    $element->click();

    $this->assertNotEmpty($assert_session->waitForText('Choose a block'));
    $assert_session->linkNotExists('Powered by Drupal');
    $page->pressButton('Close');

    // Next, allow a three-column layout and verify no contamination.
    $this->navigateToManageDisplay();
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-layouts"]/summary');
    $element->click();
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-layouts-layouts-layout-threecol-section"]');
    $element->click();
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-blocks-by-layout-layout-threecol-section"]/summary');
    $element->click();
    // Restrict on a per-region basis.
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-blocks-by-layout-layout-threecol-section-restriction-behavior-per-region"]');
    $element->click();

    $assert_session->elementContains('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-blocks-by-layout-layout-threecol-section-table"]/tbody/tr[@data-region="first"]', 'First');
    $assert_session->elementContains('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-blocks-by-layout-layout-threecol-section-table"]/tbody/tr[@data-region="first"]', 'Unrestricted');
    $assert_session->elementContains('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-blocks-by-layout-layout-threecol-section-table"]/tbody/tr[@data-region="second"]', 'Second');
    $assert_session->elementContains('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-blocks-by-layout-layout-threecol-section-table"]/tbody/tr[@data-region="second"]', 'Unrestricted');
    $assert_session->elementContains('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-blocks-by-layout-layout-threecol-section-table"]/tbody/tr[@data-region="third"]', 'Third');
    $assert_session->elementContains('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-blocks-by-layout-layout-threecol-section-table"]/tbody/tr[@data-region="third"]', 'Unrestricted');

    // Manage restrictions for First region.
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-blocks-by-layout-layout-threecol-section-table"]/tbody/tr[@data-region="first"]//a');
    $element->click();
    $this->assertNotEmpty($assert_session->waitForText('Allow all existing & new Content fields blocks.'));

    $assert_session->checkboxChecked('Allow all existing & new Content fields blocks.');
    $assert_session->checkboxNotChecked('Allow specific Content fields blocks:');
    $assert_session->checkboxChecked('Allow all existing & new Custom blocks blocks.');
    $assert_session->checkboxNotChecked('Allow specific Custom blocks blocks:');
    $assert_session->checkboxChecked('Allow all existing & new Inline blocks blocks.');
    $assert_session->checkboxNotChecked('Allow specific Inline blocks blocks:');
    $assert_session->checkboxChecked('Allow all existing & new System blocks.');
    $assert_session->checkboxNotChecked('Allow specific System blocks:');
    $assert_session->checkboxChecked('Allow all existing & new Forms blocks.');
    $assert_session->checkboxNotChecked('Allow specific Forms blocks:');

    // Disallow Forms blocks.
    $element = $page->find('xpath', '//*[starts-with(@id, "edit-allowed-blocks-forms-restriction-allowlisted--")]');
    $element->click();
    $element = $page->find('xpath', '//*[starts-with(@id,"edit-submit--")]');
    $element->click();
    $this->assertNotEmpty($assert_session->waitForText('Save'));

    $assert_session->elementContains('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-blocks-by-layout-layout-threecol-section-table"]/tbody/tr[@data-region="third"]', 'Third');
    $assert_session->elementContains('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-blocks-by-layout-layout-threecol-section-table"]/tbody/tr[@data-region="third"]', 'Restricted');
    $page->pressButton('Save');

    $this->navigateToNodeLayout($node_id);

    // Add three-column layout below existing section.
    $element = $page->find('xpath', '//*[@data-layout-builder-highlight-id="section-1"]//a');
    $element->click();
    $this->assertNotEmpty($assert_session->waitForLink('Three column'));
    $this->clickLink('Three column');
    $this->assertNotEmpty($assert_session->waitForText('Configure section'));
    $element = $page->find('xpath', '//*[contains(@class, "ui-dialog-off-canvas")]//*[starts-with(@id,"edit-actions-submit--")]');
    $element->click();
    $this->assertNotEmpty($assert_session->waitForText('Save'));
    $page->pressButton('Save');

    $this->navigateToNodeLayout($node_id);
    // Verify Forms blocks are unavailable to First region in
    // three-column layout.
    $element = $page->find('xpath', '//*[contains(@class, "layout--threecol-section")]/*[contains(@class, "layout__region--first")]//a');
    $element->click();
    $assert_session->linkNotExists('Search form');

    // Finally, test all_regions functionality.
    $this->navigateToManageDisplay();

    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-blocks-by-layout-layout-twocol-section"]/summary');
    $element->click();
    // Switch Two-column layout restrictions to all regions.
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-blocks-by-layout-layout-twocol-section-restriction-behavior-all"]');
    $element->click();
    $assert_session->elementContains('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-blocks-by-layout-layout-twocol-section-table"]/tbody/tr[@data-region="all_regions"]', 'Unrestricted');
    $page->pressButton('Save');

    // Verify no restrictions.
    $this->navigateToNodeLayout($node_id);
    $element = $page->find('xpath', '//*[contains(@class, "layout--twocol-section")]/*[contains(@class, "layout__region--first")]//a');
    $element->click();
    $this->assertNotEmpty($assert_session->waitForLink('Promoted to front page'));
    $assert_session->linkExists('Promoted to front page');
    $page->pressButton('Close');
    $this->assertNotEmpty($assert_session->waitForElementRemoved('css', '.ui-dialog-off-canvas'));

    $element = $page->find('xpath', '//*[contains(@class, "layout--twocol-section")]/*[contains(@class, "layout__region--second")]//a');
    $element->click();
    $this->assertNotEmpty($assert_session->waitForLink('Promoted to front page'));
    $assert_session->linkExists('Promoted to front page');
    $page->pressButton('Close');
    $this->assertNotEmpty($assert_session->waitForText('Save'));
    $page->pressButton('Save');

    // Add a restriction for all_regions.
    $this->navigateToManageDisplay();
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-blocks-by-layout-layout-twocol-section"]/summary');
    $element->click();
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-blocks-by-layout-layout-twocol-section-table"]/tbody/tr[@data-region="all_regions"]//a');
    $element->click();
    $this->assertNotEmpty($assert_session->waitForText('Allow all existing & new Content fields blocks.'));

    $assert_session->checkboxChecked('Allow all existing & new Content fields blocks.');
    $assert_session->checkboxNotChecked('Allow specific Content fields blocks:');

    $element = $page->find('xpath', '//*[contains(@class, "form-item-allowed-blocks-content-fields-restriction")]/input[@value="allowlisted"]');
    $element->click();
    $element = $page->find('xpath', '//*[starts-with(@id,"edit-submit--")]');
    $element->click();
    $this->assertNotEmpty($assert_session->waitForText('Save'));
    $page->pressButton('Save');

    // Verify restrictions applied to both regions.
    $this->navigateToNodeLayout($node_id);
    $element = $page->find('xpath', '//*[contains(@class, "layout--twocol-section")]/*[contains(@class, "layout__region--first")]//a');
    $element->click();
    $this->assertNotEmpty($assert_session->waitForText('Choose a block'));
    $assert_session->linkNotExists('Promoted to front page');
    $page->pressButton('Close');
    $this->assertNotEmpty($assert_session->waitForElementRemoved('css', '.ui-dialog-off-canvas'));

    $element = $page->find('xpath', '//*[contains(@class, "layout--twocol-section")]/*[contains(@class, "layout__region--second")]//a');
    $element->click();
    $this->assertNotEmpty($assert_session->waitForText('Choose a block'));
    $assert_session->linkNotExists('Promoted to front page');
  }

}
