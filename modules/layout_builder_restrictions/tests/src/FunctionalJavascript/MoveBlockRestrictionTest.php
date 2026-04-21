<?php

declare(strict_types=1);

namespace Drupal\Tests\layout_builder_restrictions\FunctionalJavascript;

use Drupal\Tests\layout_builder_restrictions\Traits\MoveBlockHelperTrait;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;

/**
 * Tests moving blocks via the form.
 *
 * @group layout_builder_restrictions
 */
class MoveBlockRestrictionTest extends LayoutBuilderRestrictionsTestBase {

  use ContentTypeCreationTrait;
  use MoveBlockHelperTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'block_content',
    'contextual',
    'node',
    'layout_builder',
    'layout_builder_restrictions',
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

    $this->drupalLogin($this->drupalCreateUser([
      'access administration pages',
      'configure any layout',
      'administer blocks',
      'administer node display',
      'administer node fields',
      'access contextual links',
      'create and edit custom blocks',
    ]));
  }

  /**
   * Tests moving a content block.
   */
  public function testMoveContentBlock() {
    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();
    $blocks = $this->generateTestBlocks();
    $this->generateTestNode();

    $this->navigateToManageDisplay();
    $page->clickLink('Manage layout');
    // Add a top section using the Two column layout.
    $page->clickLink('Add section');
    $assert_session->waitForElementVisible('css', '#drupal-off-canvas');
    $page->clickLink('Two column');
    $this->assertNotEmpty($assert_session->waitForElementVisible('css', 'input[value="Add section"]'));
    $page->pressButton('Add section');
    $this->assertNotEmpty($assert_session->waitForElementRemoved('css', '.ui-dialog-off-canvas'));
    // Add Basic Block 1 to the 'first' region.
    $assert_session->elementExists('css', '[data-layout-delta="0"].layout--twocol-section [data-region="first"] .layout-builder__add-block .layout-builder__link--add')->click();
    $this->assertNotEmpty($assert_session->waitForText('Basic Block 1'));
    $page->clickLink('Basic Block 1');
    $this->assertNotEmpty($assert_session->waitForText("Display title"));
    $page->pressButton('Add block');
    $this->assertNotEmpty($assert_session->waitForElementRemoved('css', '.ui-dialog-off-canvas'));

    // Add Alternate Block 1 to the 'first' region.
    $assert_session->elementExists('css', '[data-layout-delta="0"].layout--twocol-section [data-region="first"] .layout-builder__add-block .layout-builder__link--add')->click();
    $this->assertNotEmpty($assert_session->waitForText("Alternate Block 1"));
    $page->clickLink('Alternate Block 1');
    $this->assertNotEmpty($assert_session->waitForText('Add block'));
    $page->pressButton('Add block');
    $this->assertNotEmpty($assert_session->waitForElementRemoved('css', '.ui-dialog-off-canvas'));
    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();

    // Restrict all Content blocks.
    $this->navigateToManageDisplay();
    $element = $page->find('xpath', '//*[@id="edit-layout-layout-builder-restrictions-allowed-blocks"]/summary');
    $element->click();
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-blocks-custom-blocks-restriction-all"]');
    $assert_session->checkboxChecked('edit-layout-builder-restrictions-allowed-blocks-custom-blocks-restriction-all');
    $assert_session->checkboxNotChecked('edit-layout-builder-restrictions-allowed-blocks-custom-blocks-restriction-allowlisted');
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-blocks-custom-blocks-restriction-allowlisted"]');
    $element->click();
    $page->pressButton('Save');

    $page->clickLink('Manage layout');
    $expected_block_order = [
      '.block-block-content' . $blocks['Basic Block 1'],
      '.block-block-content' . $blocks['Alternate Block 1'],
    ];
    $this->assertRegionBlocksOrder(0, 'first', $expected_block_order);
    $this->navigateToManageDisplay();
    $page->clickLink('Manage layout');
    $this->assertNotEmpty($assert_session->waitForText('Basic Block 1'));
    // Attempt to reorder Alternate Block 1.
    $this->openMoveForm(
      0,
      'first',
      'block-block-content' . $blocks['Alternate Block 1'],
      ['Basic Block 1', 'Alternate Block 1 (current)']
    );
    $this->moveBlockWithKeyboard(
      'up',
      'Alternate Block 1',
      ['Alternate Block 1 (current) *', 'Basic Block 1']
    );
    $page->pressButton('Move');
    $this->assertNotEmpty($assert_session->waitForText('Content cannot be placed'));
    // Verify that a validation error is provided.
    $modal = $page->find('css', '#drupal-off-canvas p');
    $this->assertSame("There is a restriction on Alternate Block 1 placement in the layout_twocol_section first region for bundle_with_section_field content.", trim($modal->getText()));

    $dialog_div = $this->assertSession()->waitForElementVisible('css', 'div.ui-dialog');
    $close_button = $dialog_div->findButton('Close');
    $this->assertNotNull($close_button);
    $close_button->press();

    $this->assertNotEmpty($assert_session->waitForElementRemoved('css', '.ui-dialog'));
    $page->pressButton('Save layout');
    $page->clickLink('Manage layout');
    // The order should not have changed after save.
    $this->assertRegionBlocksOrder(0, 'first', $expected_block_order);

    // Allow Alternate Block, but not Basic block.
    $this->navigateToManageDisplay();
    $element = $page->find('xpath', '//*[@id="edit-layout-layout-builder-restrictions-allowed-blocks"]/summary');
    $element->click();
    // Do not apply individual block level restrictions.
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-blocks-custom-blocks-restriction-all"]');
    $element->click();
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-blocks-custom-block-types-restriction-allowlisted"]');
    $element->click();
    // Allowlist all "Alternate" block types.
    $page->checkField('layout_builder_restrictions[allowed_blocks][Custom block types][available_blocks][alternate]');
    $page->pressButton('Save');

    // Reorder Alternate block.
    $page->clickLink('Manage layout');
    $expected_block_order_moved = [
      '.block-block-content' . $blocks['Alternate Block 1'],
      '.block-block-content' . $blocks['Basic Block 1'],
    ];
    $this->assertRegionBlocksOrder(0, 'first', $expected_block_order);
    $this->openMoveForm(
      0,
      'first',
      'block-block-content' . $blocks['Alternate Block 1'],
      ['Basic Block 1', 'Alternate Block 1 (current)']
    );
    $this->moveBlockWithKeyboard(
      'up',
      'Alternate Block 1',
      ['Alternate Block 1 (current) *', 'Basic Block 1']
    );
    $page->pressButton('Move');
    $this->assertNotEmpty($assert_session->waitForElementRemoved('css', '.ui-dialog'));
    $this->assertRegionBlocksOrder(0, 'first', $expected_block_order_moved);

    // Demonstrate that Basic block types are still restricted.
    $this->openMoveForm(
      0,
      'first',
      'block-block-content' . $blocks['Basic Block 1'],
      ['Alternate Block 1', 'Basic Block 1 (current)']
    );
    $this->moveBlockWithKeyboard(
      'up',
      'Basic Block 1',
      ['Basic Block 1 (current) *', 'Alternate Block 1']
    );
    $page->pressButton('Move');
    $this->assertNotEmpty($assert_session->waitForText('Content cannot be placed'));
    // Verify that a validation error is provided.
    $modal = $page->find('css', '#drupal-off-canvas p');
    $this->assertSame("There is a restriction on Basic Block 1 placement in the layout_twocol_section first region for bundle_with_section_field content.", trim($modal->getText()));
    $dialog_div = $this->assertSession()->waitForElementVisible('css', 'div.ui-dialog');
    $close_button = $dialog_div->findButton('Close');
    $this->assertNotNull($close_button);
    $close_button->press();
    $this->assertNotEmpty($assert_session->waitForElementRemoved('css', '.ui-dialog'));
    $page->pressButton('Save layout');
    $page->clickLink('Manage layout');

    // Allow all Custom block types.
    $this->navigateToManageDisplay();
    $element = $page->find('xpath', '//*[@id="edit-layout-layout-builder-restrictions-allowed-blocks"]/summary');
    $element->click();
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-blocks-custom-block-types-restriction-all"]');
    $element->click();
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-blocks-custom-blocks-restriction-all"]');
    $element->click();
    $page->pressButton('Save');

    // Reorder both Alternate & Basic block block.
    $page->clickLink('Manage layout');
    $this->assertRegionBlocksOrder(0, 'first', $expected_block_order_moved);
    $this->openMoveForm(
      0,
      'first',
      'block-block-content' . $blocks['Basic Block 1'],
      ['Alternate Block 1', 'Basic Block 1 (current)']
    );
    $this->moveBlockWithKeyboard(
      'up',
      'Basic Block 1',
      ['Basic Block 1 (current) *', 'Alternate Block 1']
    );
    $page->pressButton('Move');
    $this->assertNotEmpty($assert_session->waitForElementRemoved('css', '.ui-dialog'));
    $modal = $page->find('css', '#drupal-off-canvas p');
    $this->assertNull($modal);
    $page->pressButton('Save layout');
    // Reorder Alternate block.
    $page->clickLink('Manage layout');
    $this->assertRegionBlocksOrder(0, 'first', $expected_block_order);
    $this->openMoveForm(
      0,
      'first',
      'block-block-content' . $blocks['Alternate Block 1'],
      ['Basic Block 1', 'Alternate Block 1 (current)']
    );
    $this->moveBlockWithKeyboard(
      'up',
      'Alternate Block 1',
      ['Alternate Block 1 (current) *', 'Basic Block 1']
    );
    $page->pressButton('Move');
    $this->assertNotEmpty($assert_session->waitForElementRemoved('css', '.ui-dialog'));
    $page->pressButton('Save layout');
    $page->clickLink('Manage layout');
    $this->assertRegionBlocksOrder(0, 'first', $expected_block_order_moved);
  }

}
