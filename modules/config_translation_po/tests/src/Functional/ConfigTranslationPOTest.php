<?php

declare(strict_types = 1);

namespace Drupal\Tests\config_translation_po\Functional;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;

/**
 * Test description.
 *
 * @group config_translation_po
 */
final class ConfigTranslationPOTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['config_translation_po'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->adminUser = $this->drupalCreateUser([
      'administer languages',
      'translate interface',
      'access administration pages',
    ]);
    $this->drupalLogin($this->adminUser);

    // Copy test po files to the translations directory.
    \Drupal::service('file_system')->copy($this->root . '/core/modules/locale/tests/test.de.po', 'translations://', FileSystemInterface::EXISTS_REPLACE);
    \Drupal::service('file_system')->copy($this->root . '/core/modules/locale/tests/test.xx.po', 'translations://', FileSystemInterface::EXISTS_REPLACE);
  }

  /**
   * Test callback.
   */
  public function testImportExport(): void {
    $assert = $this->assertSession();
    // Add a language.
    $edit = [
      'predefined_langcode' => 'fr',
    ];
    $this->drupalGet(Url::fromRoute('language.add'));
    $this->submitForm($edit, 'Add language');

    $file_system = \Drupal::service('file_system');
    // First import some known translations.
    // This will also automatically add the 'fr' language.
    $name = $file_system->tempnam('temporary://', "po_") . '.po';
    file_put_contents($name, $this->getPoFile());
    $this->drupalGet(Url::fromRoute('config_translation_po.import_config_form'));
    $assert->elementExists('xpath', '//h1[text() = "Import Configuration Translations"]');
    $this->submitForm([
      'langcode' => 'fr',
      'files[file]' => $name,
    ], 'Import');
    $file_system->unlink($name);
    $assert->pageTextNotContains('The language French is not enabled.');

    $this->drupalGet(Url::fromRoute('config_translation_po.export_config_form'));
    $assert->elementExists('xpath', '//h1[text() = "Export Configuration Translations"]');
    $this->submitForm([
      'langcode' => 'fr',
    ], 'Export');
    $assert->pageTextContains('# fr translation of Drupal');
  }

  /**
   * Helper function that returns a proper .po file.
   */
  private function getPoFile() {
    return <<< EOF
msgid ""
msgstr ""
"Project-Id-Version: Drupal\\n"
"MIME-Version: 1.0\\n"
"Content-Type: text/plain; charset=UTF-8\\n"
"Content-Transfer-Encoding: 8bit\\n"
"Plural-Forms: nplurals=2; plural=(n > 1);\\n"

msgid "Monday"
msgstr "lundi"
EOF;
  }

}
