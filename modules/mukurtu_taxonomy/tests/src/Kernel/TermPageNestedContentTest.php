<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_taxonomy\Kernel;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Config\FileStorage;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\mukurtu_protocol\Entity\Protocol;
use Drupal\mukurtu_taxonomy\Controller\TaxonomyRecordViewController;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\TermInterface;
use Drupal\Tests\mukurtu_digital_heritage\Kernel\DigitalHeritageTestBase;
use Drupal\user\Entity\User;
use Drupal\views\Entity\View;
use Drupal\views\Views;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that term pages include nested terms and their content (#2521).
 *
 * @see \Drupal\mukurtu_taxonomy\Controller\TaxonomyRecordViewController
 */
#[Group('mukurtu_taxonomy')]
class TermPageNestedContentTest extends DigitalHeritageTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'language',
    'flat_taxonomy',
    'mukurtu_taxonomy',
  ];

  /**
   * {@inheritdoc}
   *
   * The flat_taxonomy module's own schema file is never discovered.
   * mukurtu_core supplies the schema for its 'flat' setting, and isn't
   * installed here.
   */
  protected $strictConfigSchema = FALSE;

  /**
   * Category terms: Arts > Weaving > Baskets, and Music.
   *
   * @var \Drupal\taxonomy\TermInterface[]
   */
  protected array $terms;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // The term index is only kept when taxonomy's own settings are installed.
    $this->installConfig(['taxonomy', 'language']);
    $this->installEntitySchema('path_alias');
    $path = \Drupal::service('extension.list.module')->getPath('mukurtu_taxonomy');
    View::create(Yaml::decode(file_get_contents("$path/config/install/views.view.taxonomy_term.yml")))->save();

    $arts = $this->createCategory('Arts');
    $weaving = $this->createTerm('Weaving', $arts);
    $baskets = $this->createTerm('Baskets', $weaving);
    $music = $this->createCategory('Music');
    $this->terms = compact('arts', 'weaving', 'baskets', 'music');
  }

  /**
   * Creates a category nested under another.
   */
  protected function createTerm(string $name, TermInterface $parent, int $weight = 0): TermInterface {
    $term = Term::create(['name' => $name, 'vid' => 'category', 'parent' => $parent->id(), 'weight' => $weight]);
    $term->save();
    return $term;
  }

  /**
   * Creates a published item in the given categories.
   */
  protected function createItem(string $title, array $categories): int {
    $item = $this->buildDigitalHeritage($title, $categories);
    $item->save();
    return (int) $item->id();
  }

  /**
   * Gets the titles the term page lists for a term, in order.
   */
  protected function listedTitles(TermInterface $term): array {
    $view = Views::getView('taxonomy_term');
    $view->setDisplay('default');
    $view->setArguments([$term->id()]);
    $view->execute();
    $titles = array_map(fn ($row) => $row->_entity->label(), $view->result);
    sort($titles);
    return $titles;
  }

  /**
   * A parent's page lists content tagged with any term nested under it.
   *
   * Content tagged with two terms in the branch is listed once.
   */
  public function testParentListsNestedContent(): void {
    $this->createItem('Arts item', [$this->terms['arts']]);
    $this->createItem('Weaving item', [$this->terms['weaving']]);
    $this->createItem('Baskets item', [$this->terms['baskets']]);
    $this->createItem('Weaving and baskets item', [$this->terms['weaving'], $this->terms['baskets']]);
    $this->createItem('Music item', [$this->terms['music']]);

    $this->assertSame(
      ['Arts item', 'Baskets item', 'Weaving and baskets item', 'Weaving item'],
      $this->listedTitles($this->terms['arts'])
    );
    $this->assertSame(
      ['Baskets item', 'Weaving and baskets item', 'Weaving item'],
      $this->listedTitles($this->terms['weaving'])
    );
    $this->assertSame(['Baskets item', 'Weaving and baskets item'], $this->listedTitles($this->terms['baskets']));
  }

  /**
   * Moving a term refreshes the content on its old parent's page.
   *
   * The listing is cached separately from the page, under the view's own
   * cache tags, so it needs the vocabulary's term list tag too.
   */
  public function testMovingTermRefreshesListing(): void {
    $this->createItem('Baskets item', [$this->terms['baskets']]);
    $renderer = $this->container->get('renderer');
    $render = function () use ($renderer): string {
      $build = TaxonomyRecordViewController::create($this->container)->build(Term::load($this->terms['arts']->id()));
      return (string) $renderer->renderInIsolation($build);
    };

    $this->assertStringContainsString('Baskets item', $render());

    Term::load($this->terms['weaving']->id())->set('parent', 0)->save();

    $this->assertStringNotContainsString('Baskets item', $render());
  }

  /**
   * Content the viewer can't see under its protocols stays hidden.
   */
  public function testProtocolAccessStillApplies(): void {
    $strict = Protocol::create([
      'name' => 'Strict Protocol',
      'field_communities' => [$this->community->id()],
      'field_access_mode' => 'strict',
    ]);
    $strict->save();
    $strict->addMember($this->currentUser, ['protocol_steward']);

    $this->createItem('Open item', [$this->terms['baskets']]);
    $hidden = $this->buildDigitalHeritage('Strict item', [$this->terms['baskets']]);
    $hidden->setProtocols([$strict]);
    $hidden->save();

    $this->assertSame(['Open item', 'Strict item'], $this->listedTitles($this->terms['arts']));

    $outsider = User::create(['name' => 'outsider', 'status' => 1]);
    $outsider->save();
    $this->container->get('current_user')->setAccount($outsider);

    $this->assertSame(['Open item'], $this->listedTitles($this->terms['arts']));
  }

  /**
   * The term page lists the terms nested under it, and the ones beside it.
   *
   * Terms are in the vocabulary's order. Names are in the current content
   * language, and moving any term in the vocabulary refreshes the page.
   */
  public function testNestedTermsList(): void {
    $carving = $this->createTerm('Carving', $this->terms['arts'], -1);
    ConfigurableLanguage::createFromLangcode('es')->save();
    $this->container->get('entity_type.manager')->getStorage('taxonomy_term')->resetCache();
    Term::load($this->terms['arts']->id())->addTranslation('es', ['name' => 'Artes'])->save();
    Term::load($this->terms['weaving']->id())->addTranslation('es', ['name' => 'Tejido'])->save();

    $controller = TaxonomyRecordViewController::create($this->container);
    $build = $controller->build($this->terms['arts']);
    $this->assertSame(['Carving', 'Weaving'], array_column($build['records']['#nested_terms'], 'name'));
    $this->assertContains('taxonomy_term_list:category', $build['#cache']['tags']);
    $this->assertContains('languages:language_content', $build['#cache']['contexts']);

    // A top-level term has nothing beside it.
    $this->assertNull($build['records']['#sibling_terms']);

    // An only child has no siblings either.
    $baskets = $controller->build($this->terms['baskets']);
    $this->assertSame([], $baskets['records']['#nested_terms']);
    $this->assertNull($baskets['records']['#sibling_terms']);

    // The term itself is left out of the list beside it.
    $siblings = $controller->build($this->terms['weaving'])['records']['#sibling_terms'];
    $this->assertSame('Also in <em class="placeholder">Arts</em>', (string) $siblings['heading']);
    $this->assertSame(['Carving'], array_column($siblings['terms'], 'name'));

    $this->config('system.site')->set('default_langcode', 'es')->save();
    $this->container->get('language.default')->set(ConfigurableLanguage::load('es'));
    $this->container->get('language_manager')->reset();

    $this->assertSame(
      'Also in <em class="placeholder">Artes</em>',
      (string) TaxonomyRecordViewController::create($this->container)->build($carving)['records']['#sibling_terms']['heading']
    );
    $nested = TaxonomyRecordViewController::create($this->container)->build($this->terms['arts'])['records']['#nested_terms'];
    $this->assertSame(['Carving', 'Tejido'], array_column($nested, 'name'));
  }

  /**
   * The update switches a stored view that still has the shipped argument.
   *
   * Afterwards the page lists nested content. A view whose argument a site
   * changed is left alone.
   */
  public function testUpdateHook(): void {
    require_once \Drupal::root() . '/' . \Drupal::service('extension.list.module')->getPath('mukurtu_taxonomy') . '/mukurtu_taxonomy.install';
    $shipped = $this->config('views.view.taxonomy_term')->get('display.default.display_options');

    // As shipped before this update.
    $options = 'display.default.display_options';
    $this->config('views.view.taxonomy_term')
      ->set("$options.arguments", [
        'tid' => [
          'id' => 'tid',
          'table' => 'taxonomy_index',
          'field' => 'tid',
          'plugin_id' => 'taxonomy_index_tid',
          'default_action' => 'not found',
        ],
      ])
      ->set("$options.sorts.sticky.table", 'taxonomy_index')
      ->set("$options.sorts.created.table", 'taxonomy_index')
      ->set("$options.filters.status.table", 'taxonomy_index')
      ->set("$options.header.entity_taxonomy_term.target", '{{ raw_arguments.tid }}')
      ->save();

    $this->assertStringContainsString('nested under them', (string) mukurtu_taxonomy_update_40022());
    $updated = $this->config('views.view.taxonomy_term')->get($options);
    foreach (['arguments', 'sorts', 'filters', 'header'] as $key) {
      $this->assertEquals($shipped[$key], $updated[$key], $key);
    }
    $this->assertNull(mukurtu_taxonomy_update_40022());

    $this->createItem('Baskets item', [$this->terms['baskets']]);
    $this->assertSame(['Baskets item'], $this->listedTitles($this->terms['arts']));

    $this->config('views.view.taxonomy_term')->set("$options.arguments", ['custom' => ['plugin_id' => 'taxonomy_index_tid']])->save();
    $this->assertNull(mukurtu_taxonomy_update_40022());
  }

  /**
   * The shipped Category filter on Manage content includes nested terms.
   */
  public function testManageContentCategoryFilter(): void {
    $profile_path = \Drupal::service('extension.list.profile')->getPath('mukurtu');
    $shipped = (new FileStorage($profile_path . '/config/install'))->read('views.view.mukurtu_manage_all_content');
    $filter = $shipped['display']['default']['display_options']['filters']['field_category_target_id'];
    View::create([
      'id' => 'nested_category_filter_test',
      'label' => 'Nested category filter test',
      'base_table' => 'node_field_data',
      'base_field' => 'nid',
      'display' => [
        'default' => [
          'id' => 'default',
          'display_plugin' => 'default',
          'display_title' => 'Default',
          'position' => 0,
          'display_options' => ['filters' => ['field_category_target_id' => $filter]],
        ],
      ],
    ])->save();

    $this->createItem('Weaving item', [$this->terms['weaving']]);
    $this->createItem('Baskets item', [$this->terms['baskets']]);
    $this->createItem('Music item', [$this->terms['music']]);

    $view = Views::getView('nested_category_filter_test');
    $view->setDisplay('default');
    $view->setExposedInput(['field_category_target_id' => $this->terms['weaving']->id()]);
    $view->execute();
    $titles = array_map(fn ($row) => $row->_entity->label(), $view->result);
    sort($titles);

    $this->assertSame(['Baskets item', 'Weaving item'], $titles);
  }

}
