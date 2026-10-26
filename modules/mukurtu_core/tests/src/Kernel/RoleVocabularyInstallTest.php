<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\mukurtu_core\EntityReferenceRoleConverter;
use Drupal\taxonomy\Entity\Vocabulary;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests EntityReferenceRoleConverter::installRoleVocabulary().
 *
 * Run by mukurtu_core_update_40214(). With mukurtu_multilingual on, it also
 * installs the Role vocabulary's language settings, which that module ships
 * from config/optional.
 *
 * @see mukurtu_core_update_40214()
 */
#[Group('mukurtu_core')]
class RoleVocabularyInstallTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   *
   * Listing mukurtu_multilingual makes moduleExists() report it without
   * running its install, which would create the settings this test checks.
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'filter',
    'text',
    'taxonomy',
    'flat_taxonomy',
    'language',
    'content_translation',
    'path_alias',
    'geofield',
    'leaflet',
    'mukurtu_core',
    'mukurtu_multilingual',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Turning translation on for a vocabulary adds columns to the term tables.
    $this->installEntitySchema('taxonomy_term');
  }

  /**
   * Installs the vocabulary and its language settings on a multilingual site.
   */
  public function testInstallsVocabularyAndLanguageSettings(): void {
    $this->assertNull(Vocabulary::load('role'));
    $this->assertNull(ContentLanguageSettings::load('taxonomy_term.role'));

    EntityReferenceRoleConverter::installRoleVocabulary();

    $this->assertNotNull(Vocabulary::load('role'));
    $settings = ContentLanguageSettings::load('taxonomy_term.role');
    $this->assertNotNull($settings);
    $this->assertTrue($settings->getThirdPartySetting('content_translation', 'enabled'));
  }

  /**
   * Running it again leaves existing settings alone.
   */
  public function testKeepsExistingLanguageSettings(): void {
    EntityReferenceRoleConverter::installRoleVocabulary();
    ContentLanguageSettings::load('taxonomy_term.role')
      ->setThirdPartySetting('content_translation', 'enabled', FALSE)
      ->save();

    EntityReferenceRoleConverter::installRoleVocabulary();

    $this->assertFalse(ContentLanguageSettings::load('taxonomy_term.role')->getThirdPartySetting('content_translation', 'enabled'));
  }

}
