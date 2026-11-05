<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\Core\Database\Database;
use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\mukurtu_core\Plugin\views\filter\MediaTagFilter;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\taxonomy\TermInterface;
use Drupal\views\Plugin\views\query\Sql;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests how the media tag list and filter handle nested terms (#2524).
 *
 * @see \Drupal\mukurtu_core\Service\TermPathOptions
 * @see \Drupal\mukurtu_core\Plugin\views\filter\MediaTagFilter
 */
#[Group('mukurtu_core')]
class NestedMediaTagFilterTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_reference_revisions',
    'field',
    'file',
    'filter',
    'geofield',
    'image',
    'language',
    'leaflet',
    'media',
    'mukurtu_core',
    'mukurtu_protocol',
    'node',
    'og',
    'options',
    'paragraphs',
    'system',
    'taxonomy',
    'text',
    'user',
    'views',
  ];

  /**
   * Media tags: Washington > Spokane > Hillyard, and Alaska.
   *
   * @var \Drupal\taxonomy\TermInterface[]
   */
  protected array $terms;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('taxonomy_term');
    $this->installConfig(['filter', 'language']);
    ConfigurableLanguage::createFromLangcode('es')->save();
    Vocabulary::create(['vid' => 'media_tag', 'name' => 'Media Tag'])->save();

    $washington = $this->createTerm('Washington', 'Washington ES');
    $spokane = $this->createTerm('Spokane', 'Spokane ES', $washington);
    $hillyard = $this->createTerm('Hillyard', 'Hillyard ES', $spokane);
    $alaska = $this->createTerm('Alaska', 'Alaska ES');
    $this->terms = compact('washington', 'spokane', 'hillyard', 'alaska');
  }

  /**
   * Creates a media tag with a Spanish translation.
   */
  protected function createTerm(string $name, string $spanish, ?TermInterface $parent = NULL): TermInterface {
    $term = Term::create(['vid' => 'media_tag', 'name' => $name, 'parent' => $parent?->id() ?? 0]);
    $term->save();
    $term->addTranslation('es', ['name' => $spanish])->save();
    return $term;
  }

  /**
   * Terms are listed in tree order, each with its full path.
   */
  public function testOptionsAreInTreeOrderWithPaths(): void {
    $ids = array_map(fn ($term) => (int) $term->id(), $this->terms);
    $this->assertSame([
      $ids['alaska'] => 'Alaska',
      $ids['washington'] => 'Washington',
      $ids['spokane'] => 'Washington › Spokane',
      $ids['hillyard'] => 'Washington › Spokane › Hillyard',
    ], $this->container->get('mukurtu_core.term_path_options')->getOptions('media_tag'));
  }

  /**
   * Weight decides the order before the name does.
   */
  public function testWeightOrdersTerms(): void {
    $this->terms['washington']->setWeight(-1)->save();
    $options = $this->container->get('mukurtu_core.term_path_options')->getOptions('media_tag');
    $this->assertSame('Washington', reset($options));
  }

  /**
   * Paths use the current language.
   */
  public function testOptionsAreTranslated(): void {
    $this->config('system.site')->set('default_langcode', 'es')->save();
    $this->container->get('language.default')->set(ConfigurableLanguage::load('es'));
    $this->container->get('language_manager')->reset();

    $options = $this->container->get('mukurtu_core.term_path_options')->getOptions('media_tag');
    $this->assertSame('Washington ES › Spokane ES › Hillyard ES', $options[$this->terms['hillyard']->id()]);
  }

  /**
   * Choosing a term finds media tagged with it or a term nested under it.
   */
  public function testFilterIncludesNestedTerms(): void {
    Database::getConnection()->schema()->createTable('media__field_media_tags', [
      'fields' => [
        'entity_id' => ['type' => 'int', 'not null' => TRUE],
        'field_media_tags_target_id' => ['type' => 'int', 'not null' => TRUE],
      ],
    ]);
    $rows = [
      1 => 'washington',
      2 => 'spokane',
      3 => 'hillyard',
      4 => 'alaska',
    ];
    foreach ($rows as $media_id => $key) {
      Database::getConnection()->insert('media__field_media_tags')
        ->fields(['entity_id' => $media_id, 'field_media_tags_target_id' => $this->terms[$key]->id()])
        ->execute();
    }

    $this->assertSame([2, 3], $this->filteredMediaIds('spokane'));
    $this->assertSame([1, 2, 3], $this->filteredMediaIds('washington'));
    $this->assertSame([3], $this->filteredMediaIds('hillyard'));
    $this->assertSame([4], $this->filteredMediaIds('alaska'));
  }

  /**
   * Gets the media IDs the filter limits a view to for a term.
   */
  protected function filteredMediaIds(string $key): array {
    $filter = new class([], 'mukurtu_media_tag_filter', [], $this->container->get('entity_type.manager'), $this->container->get('database'), $this->container->get('mukurtu_core.term_path_options')) extends MediaTagFilter {

      /**
       * {@inheritdoc}
       */
      public function ensureMyTable() {
        return 'media_field_data';
      }

    };
    $filter->options = ['group' => 1];
    $filter->tableAlias = 'media_field_data';
    $filter->value = [(string) $this->terms[$key]->id()];

    $media_ids = [];
    $query = $this->createMock(Sql::class);
    $query->expects($this->once())->method('addWhere')->willReturnCallback(function ($group, $field, $value) use (&$media_ids, $query) {
      $media_ids = array_map('intval', $value);
      sort($media_ids);
      return $query;
    });
    $filter->query = $query;
    $filter->query();
    return $media_ids;
  }

}
