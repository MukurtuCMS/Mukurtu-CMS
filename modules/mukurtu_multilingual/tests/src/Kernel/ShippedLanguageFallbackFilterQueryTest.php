<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_multilingual\Kernel;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Language\Language;
use Drupal\Core\Language\LanguageInterface;
use Drupal\entity_test\Entity\EntityTestMul;
use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;
use Drupal\views\Entity\View;
use Drupal\views\Views;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Runs each shipped language_with_fallback filter through a real query.
 *
 * The filter shipped in #2074 was written as a search_api_string filter
 * ('=' plus a min/max/value array). Search API declares the property's
 * views_type as 'language', so Views builds it with SearchApiLanguage (an
 * in_operator) whatever plugin_id the YAML names. That handler has no '='
 * operator and silently added no condition, so every browse page listed
 * every translation of every item. The YAML-only checks in
 * ShippedViewsLanguageFallbackTest and ViewLanguageFallbackCoverageTest
 * passed throughout, because the filter was present, just inert.
 *
 * This copies each shipped filter definition onto a minimal view over a
 * real database-backed index and checks what it actually returns.
 */
#[Group('mukurtu_multilingual')]
class ShippedLanguageFallbackFilterQueryTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'language',
    'entity_test',
    'views',
    'search_api',
    'search_api_db',
  ];

  /**
   * The views that ship a language_with_fallback filter.
   *
   * Kept explicit, and checked against the files on disk by
   * testEveryShippedFallbackFilterIsCovered(), so a new view can't slip past
   * this test unnoticed.
   */
  private const SHIPPED_VIEWS = [
    'modules/mukurtu_browse/config/install/views.view.mukurtu_browse.yml',
    'modules/mukurtu_browse/config/install/views.view.mukurtu_browse_by_map.yml',
    'modules/mukurtu_browse/config/install/views.view.mukurtu_browse_collections.yml',
    'modules/mukurtu_browse/config/install/views.view.mukurtu_browse_map.yml',
    'modules/mukurtu_browse/config/install/views.view.mukurtu_digital_heritage_browse.yml',
    'modules/mukurtu_dictionary/config/install/views.view.mukurtu_dictionary.yml',
    'modules/mukurtu_solr/config/install/views.view.dictionary_browse_solr_new_index.yml',
    'modules/mukurtu_solr/config/install/views.view.mukurtu_browse_by_map_solr.yml',
    'modules/mukurtu_solr/config/install/views.view.mukurtu_browse_collections_solr.yml',
    'modules/mukurtu_solr/config/install/views.view.mukurtu_browse_solr.yml',
    'modules/mukurtu_solr/config/install/views.view.mukurtu_dictionary_solr.yml',
    'modules/mukurtu_solr/config/install/views.view.mukurtu_digital_heritage_browse_solr.yml',
    'modules/mukurtu_solr/config/install/views.view.mukurtu_taxonomy_references_solr.yml',
    'modules/mukurtu_taxonomy/config/install/views.view.mukurtu_taxonomy_references.yml',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installSchema('search_api', ['search_api_item']);
    $this->installEntitySchema('search_api_task');
    $this->installEntitySchema('entity_test_mul');
    $this->installEntitySchema('user');
    $this->installConfig(['search_api', 'language']);

    ConfigurableLanguage::createFromLangcode('fr')->save();

    Server::create([
      'id' => 'fallback_test_server',
      'name' => 'Fallback test server',
      'backend' => 'search_api_db',
      'backend_config' => ['database' => 'default:default'],
      'status' => TRUE,
    ])->save();

    Index::create([
      'id' => 'fallback_test_index',
      'name' => 'Fallback test index',
      'status' => TRUE,
      'server' => 'fallback_test_server',
      'datasource_settings' => ['entity:entity_test_mul' => []],
      'tracker_settings' => ['default' => []],
      'processor_settings' => ['language_with_fallback' => []],
      'field_settings' => [
        'language_with_fallback' => [
          'label' => 'Language (with fallback)',
          'property_path' => 'language_with_fallback',
          'type' => 'string',
        ],
      ],
      'options' => ['index_directly' => FALSE],
    ])->save();
  }

  /**
   * Lists every shipped view file as a data set.
   */
  public static function shippedViewProvider(): array {
    $cases = [];
    foreach (self::SHIPPED_VIEWS as $file) {
      $cases[basename($file, '.yml')] = [$file];
    }
    return $cases;
  }

  /**
   * Every view on disk with this filter is listed in SHIPPED_VIEWS.
   */
  public function testEveryShippedFallbackFilterIsCovered(): void {
    $root = dirname(__DIR__, 5);
    $this->assertFileExists("$root/mukurtu.info.yml", 'Sanity check: resolved profile root is wrong.');

    // Prune dependency trees while walking rather than after, so a theme's
    // node_modules or a vendor directory doesn't make this crawl slow.
    $skip = ['vendor', 'node_modules', '.git'];
    $found = [];
    foreach (['modules', 'config'] as $top) {
      $directory = new \RecursiveDirectoryIterator("$root/$top", \FilesystemIterator::SKIP_DOTS);
      $filter = new \RecursiveCallbackFilterIterator($directory, fn (\SplFileInfo $file): bool => !($file->isDir() && in_array($file->getFilename(), $skip, TRUE)));
      foreach (new \RecursiveIteratorIterator($filter) as $file) {
        $path = $file->getPathname();
        if (str_contains($path, '/config/install/views.view.') && str_contains((string) file_get_contents($path), 'field: language_with_fallback')) {
          $found[] = substr($path, strlen($root) + 1);
        }
      }
    }
    sort($found);
    $expected = self::SHIPPED_VIEWS;
    sort($expected);

    $this->assertSame($expected, $found);
  }

  /**
   * A shipped filter returns each entity once, translated when possible.
   */
  #[DataProvider('shippedViewProvider')]
  public function testShippedFilterReturnsOneItemPerEntity(string $file): void {
    $filter = $this->shippedFilter($file);

    $untranslated = EntityTestMul::create(['name' => 'Only English', 'langcode' => 'en']);
    $untranslated->save();
    $translated = EntityTestMul::create(['name' => 'Crow and fox', 'langcode' => 'en']);
    $translated->save();
    $translated->addTranslation('fr', ['name' => 'Corbeau et renard'])->save();

    $index = Index::load('fallback_test_index');
    $index->indexItems();

    $filter['table'] = 'search_api_index_fallback_test_index';
    View::create([
      'id' => 'fallback_filter_test',
      'base_table' => 'search_api_index_fallback_test_index',
      'display' => [
        'default' => [
          'display_plugin' => 'default',
          'id' => 'default',
          'display_options' => [
            'filters' => ['language_with_fallback' => $filter],
            'pager' => ['type' => 'none'],
            // The test runs as anonymous, who can't view entity_test_mul,
            // and Search API's Views query drops rows the user can't see.
            'query' => ['type' => 'search_api_query', 'options' => ['skip_access' => TRUE]],
          ],
        ],
      ],
    ])->save();

    $this->setActiveContentLanguage('fr');
    $view = Views::getView('fallback_filter_test');
    $view->execute();
    $ids = array_map(fn ($row) => $row->search_api_id, $view->result);
    sort($ids);

    $this->assertSame([
      'entity:entity_test_mul/' . $untranslated->id() . ':en',
      'entity:entity_test_mul/' . $translated->id() . ':fr',
    ], $ids, "$file: the language_with_fallback filter should return the French translation where one exists and the English original otherwise, once each.");
  }

  /**
   * Reads the language_with_fallback filter out of a shipped view file.
   */
  private function shippedFilter(string $file): array {
    $view = Yaml::decode(file_get_contents(dirname(__DIR__, 5) . '/' . $file));
    foreach ($view['display'] as $display) {
      if (isset($display['display_options']['filters']['language_with_fallback'])) {
        return $display['display_options']['filters']['language_with_fallback'];
      }
    }
    $this->fail("$file has no language_with_fallback filter.");
  }

  /**
   * Sets the negotiated content language, which kernel tests can't negotiate.
   */
  private function setActiveContentLanguage(string $langcode): void {
    $language_manager = $this->container->get('language_manager');
    $property = new \ReflectionProperty($language_manager, 'negotiatedLanguages');
    $property->setValue($language_manager, [LanguageInterface::TYPE_CONTENT => new Language(['id' => $langcode])]);
  }

}
