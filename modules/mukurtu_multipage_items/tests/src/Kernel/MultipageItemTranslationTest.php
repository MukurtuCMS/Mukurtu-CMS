<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_multipage_items\Kernel;

use Drupal\Core\Language\Language;
use Drupal\Core\Language\LanguageInterface;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\mukurtu_multipage_items\Controller\MultipageItemPageController;

/**
 * Tests that the multipage item view uses translated titles (#2557).
 *
 * @group mukurtu_multipage_items
 */
#[\PHPUnit\Framework\Attributes\Group('mukurtu_multipage_items')]
class MultipageItemTranslationTest extends MultipageItemTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block_content',
    'content_moderation',
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
    'mukurtu_collection',
    'mukurtu_core',
    'mukurtu_drafts',
    'mukurtu_local_contexts',
    'mukurtu_multipage_items',
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
   * The page title and "Jump to page" options follow the content language.
   */
  public function testTitlesAreTranslated(): void {
    $first = $this->buildItem('First page');
    $first->save();
    $first->addTranslation('es', ['title' => 'Primera página'])->save();
    // No Spanish translation, so the original language is used.
    $second = $this->buildItem('Second page');
    $second->save();

    $mpi = $this->buildMultipageItem('Notebook');
    $mpi->addPage($first);
    $mpi->addPage($second);
    $mpi->save();
    $mpi->addTranslation('es', ['title' => 'Cuaderno'])->save();

    $this->setActiveContentLanguage('es');
    $controller = MultipageItemPageController::create(\Drupal::getContainer());

    $this->assertSame('Cuaderno', (string) $controller->title($first));
    $this->assertSame([
      $first->id() => 'Primera página',
      $second->id() => 'Second page',
    ], $controller->view($first)['#table_of_contents']['#options']);
  }

}
