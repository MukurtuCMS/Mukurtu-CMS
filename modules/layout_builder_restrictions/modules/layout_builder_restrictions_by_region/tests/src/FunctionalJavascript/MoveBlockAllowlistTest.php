<?php

declare(strict_types=1);

namespace Drupal\Tests\layout_builder_restrictions_by_region\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\layout_builder_restrictions\Traits\MoveBlockHelperTrait;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;

/**
 * Tests moving blocks via the form.
 *
 * @group layout_builder_restrictions_by_region
 */
class MoveBlockAllowlistTest extends WebDriverTestBase {

  use ContentTypeCreationTrait;
  use MoveBlockHelperTrait;

  /**
   * Path prefix for the field UI for the test bundle.
   *
   * @var string
   */
  const FIELD_UI_PREFIX = 'admin/structure/types/manage/bundle_with_section_field';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'contextual',
    'field_ui',
    'node',
    'layout_builder',
    'layout_builder_restrictions',
    'layout_builder_restrictions_by_region',
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

    $this->createContentType(['type' => 'bundle_with_section_field']);

    $this->drupalLogin($this->drupalCreateUser([
      'access administration pages',
      'configure any layout',
      'administer blocks',
      'administer node display',
      'administer node fields',
      'access contextual links',
    ]));

    // Enable Layout Builder.
    $this->drupalGet(static::FIELD_UI_PREFIX . '/display/default');
    $this->submitForm(['layout[enabled]' => TRUE], 'Save');

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
   * Tests moving a plugin block.
   */
  public function testMovePluginBlock() {
    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();
    $page->clickLink('Manage layout');
    $assert_session->addressEquals(static::FIELD_UI_PREFIX . '/display/default/layout');
    $expected_block_order = [
      '.block-extra-field-blocknodebundle-with-section-fieldlinks',
      '.block-field-blocknodebundle-with-section-fieldbody',
    ];
    $this->assertRegionBlocksOrder(0, 'content', $expected_block_order);

    // Add a top section using the Two column layout.
    $page->clickLink('Add section');
    $assert_session->waitForElementVisible('css', '#drupal-off-canvas');
    $this->assertNotEmpty($assert_session->waitForText('Two column'));
    $page->clickLink('Two column');
    $this->assertNotEmpty($assert_session->waitForElementVisible('css', 'input[value="Add section"]'));
    $page->pressButton('Add section');
    $this->assertRegionBlocksOrder(1, 'content', $expected_block_order);
    // Add a 'Powered by Drupal' block in the 'first' region of the new section.
    $first_region_block_locator = '[data-layout-delta="0"].layout--twocol-section [data-region="first"] [data-layout-block-uuid]';
    $assert_session->elementNotExists('css', $first_region_block_locator);
    $assert_session->elementExists('css', '[data-layout-delta="0"].layout--twocol-section [data-region="first"] .layout-builder__add-block')->click();
    $this->assertNotEmpty($assert_session->waitForElementVisible('css', '#drupal-off-canvas a:contains("Powered by Drupal")'));
    $this->assertNotEmpty($assert_session->waitForText('Powered by Drupal'));
    $page->clickLink('Powered by Drupal');
    $this->assertNotEmpty($assert_session->waitForElementVisible('css', 'input[value="Add block"]'));
    $this->assertNotEmpty($assert_session->waitForText('Add block'));
    $page->pressButton('Add block');
    $this->assertNotEmpty($assert_session->waitForElementVisible('css', $first_region_block_locator));

    // Ensure the request has completed before the test starts.
    $this->assertNotEmpty($assert_session->waitForElementRemoved('css', '.ui-dialog-off-canvas'));

    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();

    // From the manage display page, go to manage the layout.
    $this->drupalGet(static::FIELD_UI_PREFIX . "/display/default");
    $assert_session->linkExists('Manage layout');

    // Only allow one-column and two-column layouts.
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-layouts"]/summary');
    $element->click();
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-layouts-layout-restriction-restricted"]');
    $element->click();
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-layouts-layouts-layout-onecol"]');
    $element->click();
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-layouts-layouts-layout-twocol-section"]');
    $element->click();

    // Add a block restriction after the fact to test basic restriction.
    // Restrict all 'Content' fields from options.
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-blocks-by-layout-layout-onecol"]/summary');
    $element->click();

    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-blocks-by-layout-layout-onecol-table"]/tbody/tr[@data-region="all_regions"]//a');
    $element->click();
    $this->assertNotEmpty($assert_session->waitForText('Allow all existing & new Content fields blocks.'));

    $assert_session->checkboxChecked('Allow all existing & new Content fields blocks.');
    $assert_session->checkboxNotChecked('Allow specific Content fields blocks:');
    $element = $page->find('xpath', '//*[starts-with(@id,"edit-allowed-blocks-content-fields-restriction-allowlisted--")]');
    $element->click();
    $element = $page->find('xpath', '//*[starts-with(@id,"edit-submit--")]');
    $element->click();
    $this->assertNotEmpty($assert_session->waitForText('Save'));
    $page->pressButton('Save');

    $page->clickLink('Manage layout');
    $expected_block_order_1 = [
      '.block-extra-field-blocknodebundle-with-section-fieldlinks',
      '.block-field-blocknodebundle-with-section-fieldbody',
    ];
    $this->assertRegionBlocksOrder(1, 'content', $expected_block_order_1);
    $assert_session->addressEquals(static::FIELD_UI_PREFIX . '/display/default/layout');

    // Attempt to reorder body field in current region.
    $this->openMoveForm(
      1,
      'content',
      'block-field-blocknodebundle-with-section-fieldbody',
      ['Links', 'Body (current)']
    );
    $this->moveBlockWithKeyboard(
      'up',
      'Body (current)',
      ['Body (current) *', 'Links']
    );
    $page->pressButton('Move');
    $this->assertNotEmpty($assert_session->waitForText('Content cannot be placed'));
    // Verify that a validation error is provided.
    $modal = $page->find('css', '#drupal-off-canvas p');
    $this->assertSame("There is a restriction on Body placement in the layout_onecol all_regions region for bundle_with_section_field content.", trim($modal->getText()));

    $dialog_div = $this->assertSession()->waitForElementVisible('css', 'div.ui-dialog');
    $close_button = $dialog_div->findButton('Close');
    $this->assertNotNull($close_button);
    $close_button->press();

    $page->pressButton('Save layout');
    $page->clickLink('Manage layout');
    // The order should not have changed after save.
    $this->assertRegionBlocksOrder(1, 'content', $expected_block_order_1);

    $this->drupalGet(static::FIELD_UI_PREFIX . "/display/default");
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-layouts"]/summary');
    $element->click();
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-layouts-layout-restriction-restricted"]');
    $element->click();
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-layouts-layouts-layout-twocol-section"]');
    $element->click();
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-layouts-layouts-layout-onecol"]');
    $element->click();
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-blocks-by-layout-layout-onecol"]/summary');
    $element->click();

    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-blocks-by-layout-layout-onecol-table"]/tbody/tr[@data-region="all_regions"]//a');
    $element->click();
    $this->assertNotEmpty($assert_session->waitForText('Allow all existing & new Content fields blocks.'));

    $assert_session->checkboxChecked('Allow all existing & new Content fields blocks.');
    $assert_session->checkboxNotChecked('Allow specific Content fields blocks:');
    $element = $page->find('xpath', '//*[starts-with(@id, "edit-allowed-blocks-content-fields-restriction-allowlisted--")]');
    $element->click();
    $element = $page->find('xpath', '//*[starts-with(@id,"edit-submit--")]');
    $element->click();
    $this->assertNotEmpty($assert_session->waitForText('Save'));
    $page->pressButton('Save');

    // Try an allowed move to another section.
    // Move the body block to the First region of a two-column layout.
    $this->drupalGet(static::FIELD_UI_PREFIX . "/display/default/layout");
    $this->openMoveForm(
      1,
      'content',
      'block-field-blocknodebundle-with-section-fieldbody',
      ['Links', 'Body (current)']
    );
    $page->selectFieldOption('Region', '0:first');
    $this->assertBlockTable(['Powered by Drupal', 'Body (current)']);
    $this->moveBlockWithKeyboard(
      'up',
      'Body',
      ['Body (current) *', 'Powered by Drupal']
    );
    $page->pressButton('Move');
    $this->assertNotEmpty($assert_session->waitForElementRemoved('css', '.ui-dialog'));
    $expected_block_order_2 = [
      '.block-field-blocknodebundle-with-section-fieldbody',
      '.block-system-powered-by-block',
    ];
    $this->assertRegionBlocksOrder(
      0,
      'first',
      $expected_block_order_2
    );

    // Try an allowed move within a section.
    // Move the body block to the Second region of a two-column layout.
    $this->openMoveForm(
      0,
      'first',
      'block-field-blocknodebundle-with-section-fieldbody',
      ['Body (current)', 'Powered by Drupal']
    );
    $page->selectFieldOption('Region', '0:second');
    $this->assertBlockTable(['Body (current)']);
    $page->pressButton('Move');
    $this->assertNotEmpty($assert_session->waitForElementRemoved('css', '.ui-dialog'));
    $expected_block_order_3 = [
      '.block-field-blocknodebundle-with-section-fieldbody',
    ];
    $this->assertRegionBlocksOrder(0, 'second', $expected_block_order_3);

    // Try a disallowed move to another section.
    // Move the body block to the Content region of a one-column layout.
    $this->openMoveForm(0, 'second', 'block-field-blocknodebundle-with-section-fieldbody', ['Body (current)']);
    $page->selectFieldOption('Region', '1:content');
    $this->assertBlockTable(['Links', 'Body (current)']);
    $page->pressButton('Move');
    $this->assertNotEmpty($assert_session->waitForText('Content cannot be placed'));
    $modal = $page->find('css', '#drupal-off-canvas p');
    // Content cannot be moved between sections if a restriction exists.
    $this->assertSame("There is a restriction on Body placement in the layout_onecol all_regions region for bundle_with_section_field content.", trim($modal->getText()));
  }

}
