<?php

declare(strict_types=1);

namespace Drupal\Tests\layout_builder_restrictions\FunctionalJavascript;

/**
 * Demonstrate that blocks can be individually restricted.
 *
 * @group layout_builder_restrictions
 */
class LanguageCategoriesTest extends LayoutBuilderRestrictionsTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'language',
    'locale',
    'block',
    'announcements_feed',
    'layout_builder',
    'layout_builder_restrictions',
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
      'administer languages',
      'access administration pages',
      'administer blocks',
      'administer node display',
      'administer node fields',
      'configure any layout',
      'create and edit custom blocks',
    ]));
    // Install Norwegian language.
    $edit = [];
    $edit['predefined_langcode'] = 'nb';
    $this->drupalGet('admin/config/regional/language/add');
    $this->submitForm($edit, 'Add language');

    $edit = [
      'site_default_language' => 'nb',
    ];
    $this->drupalGet('admin/config/regional/language');
    $this->submitForm($edit, 'Save configuration');

    // Enable URL language detection and selection.
    $edit = ['language_interface[enabled][language-url]' => 1];
    $this->drupalGet('admin/config/regional/language/detection');
    $this->submitForm($edit, 'Save settings');

    // Make sure the Norwegian language is prefix free.
    $edit = [
      'prefix[en]' => 'en',
      'prefix[nb]' => '',
    ];
    $this->drupalGet('admin/config/regional/language/detection/url');
    $this->submitForm($edit, 'Save configuration');

    // Make sure one of the strings we know will be translated.
    $locale_storage = $this->container->get('locale.storage');
    $string = NULL;
    $strings = $locale_storage->getStrings([
      'source' => '@entity fields',
    ]);
    if (!empty($strings)) {
      $string = reset($strings);
    }
    else {
      $string = $locale_storage->createString([
        'source' => '@entity fields',
      ])->save();
    }
    $locale_storage->createTranslation([
      'lid' => $string->getId(),
      'language' => 'nb',
      'translation' => '@entity felter',
    ])->save();
    // Add translation for 'Announcements Feed'.
    $strings = $locale_storage->getStrings([
      'source' => 'Announcements Feed',
    ]);
    if (!empty($strings)) {
      $string = reset($strings);
    }
    else {
      $string = $locale_storage->createString([
        'source' => 'Announcements Feed',
      ])->save();
    }
    $locale_storage->createTranslation([
      'lid' => $string->getId(),
      'language' => 'nb',
      'translation' => 'Kunngjøringsfeed',
    ])->save();
  }

  /**
   * Verify that the UI can restrict blocks in Layout Builder settings tray.
   */
  public function testBlockRestriction() {
    // Create 2 custom block types, with 3 block instances.
    $this->generateTestBlocks();
    $node_id = $this->generateTestNode();
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    $this->navigateToNodeSettingsTray($node_id);
    // Initially, the body field is available.
    $assert_session->linkExists('Body');
    // Initially, the Kunngjøringsfeed block is available.
    $assert_session->linkExists('Kunngjøringsfeed');

    // Impose Custom Block type restrictions.
    $this->navigateToManageDisplay();
    $element = $page->find('xpath', '//*[@id="edit-layout-layout-builder-restrictions-allowed-blocks"]/summary');
    $element->click();
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-blocks-content-fields-restriction-all"]');
    $assert_session->checkboxChecked('edit-layout-builder-restrictions-allowed-blocks-content-fields-restriction-all');
    $assert_session->checkboxNotChecked('edit-layout-builder-restrictions-allowed-blocks-content-fields-restriction-allowlisted');
    $assert_session->checkboxChecked('edit-layout-builder-restrictions-allowed-blocks-inline-blocks-restriction-all');
    $assert_session->checkboxNotChecked('edit-layout-builder-restrictions-allowed-blocks-inline-blocks-restriction-allowlisted');
    // Restrict all 'Content' fields from options.
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-blocks-content-fields-restriction-allowlisted"]');
    $element->click();
    // Restrict the Hjelp block.
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-blocks-announcements-restriction-allowlisted"]');
    $element->click();
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-blocks-announcements-available-blocks-announce-block"]');
    $element->click();

    $page->pressButton('Save');

    $this->navigateToNodeSettingsTray($node_id);
    // Establish that the 'body' field is no longer present.
    $assert_session->linkNotExists('Body');
    // Establish that the Hjelp block is still present.
    $assert_session->linkExists('Kunngjøringsfeed');
  }

}
