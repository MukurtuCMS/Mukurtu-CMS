<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_community_records\Kernel;

use Drupal\Core\Language\Language;
use Drupal\Core\Language\LanguageInterface;
use Drupal\language\Entity\ConfigurableLanguage;

/**
 * Tests the record titles passed to the community records tabs (#598).
 *
 * The tab script swaps the page heading to the active record's title, so
 * each record must carry its title in the current content language.
 *
 * @group mukurtu_community_records
 */
#[\PHPUnit\Framework\Attributes\Group('mukurtu_community_records')]
class CommunityRecordTitleTest extends CommunityRecordTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'file',
    'filter',
    'geofield',
    'image',
    'language',
    'leaflet',
    'media',
    'node',
    'node_access_test',
    'og',
    'options',
    'path',
    'path_alias',
    'system',
    'taxonomy',
    'text',
    'user',
    'views',
    'workflows',
    'mukurtu_community_records',
    'mukurtu_core',
    'mukurtu_local_contexts',
    'mukurtu_protocol',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    ConfigurableLanguage::createFromLangcode('es')->save();
  }

  /**
   * Sets the active content language.
   *
   * Matches NotYetTranslatedIndicatorTest: a kernel test has no request to
   * negotiate from, and the language manager has no public setter.
   */
  protected function setActiveContentLanguage(string $langcode): void {
    $language_manager = \Drupal::languageManager();
    $property = new \ReflectionProperty($language_manager, 'negotiatedLanguages');
    $property->setValue($language_manager, [LanguageInterface::TYPE_CONTENT => new Language(['id' => $langcode])]);
  }

  /**
   * Returns record titles from the full view of a node, keyed by node ID.
   */
  protected function recordTitles($node): array {
    $build = \Drupal::entityTypeManager()->getViewBuilder('node')->view($node, 'full');
    return array_column($build['template']['#records'], 'title', 'id');
  }

  /**
   * Each record tab carries its own title.
   */
  public function testRecordsCarryTitles(): void {
    $original = $this->buildRecord('Original title');
    $original->save();
    $record = $this->buildRecord('Community record title', $original);
    $record->save();

    $titles = $this->recordTitles($original);

    $this->assertSame([
      $original->id() => 'Original title',
      $record->id() => 'Community record title',
    ], $titles);
  }

  /**
   * Record titles follow the current content language.
   */
  public function testRecordTitlesAreTranslated(): void {
    $original = $this->buildRecord('Original title');
    $original->save();
    $record = $this->buildRecord('Community record title', $original);
    $record->save();
    $record->addTranslation('es', ['title' => 'Título del registro'])->save();

    $this->setActiveContentLanguage('es');
    $titles = $this->recordTitles($original);

    $this->assertSame('Título del registro', $titles[$record->id()]);
    // No Spanish translation, so the original language is used.
    $this->assertSame('Original title', $titles[$original->id()]);
  }

}
