<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_taxonomy\Kernel;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Http\Exception\CacheableNotFoundHttpException;
use Drupal\Core\Session\AccountInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\mukurtu_protocol\Entity\Community;
use Drupal\mukurtu_protocol\Entity\Protocol;
use Drupal\mukurtu_taxonomy\Controller\TaxonomyRecordViewController;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeAccessRebuild;
use Drupal\og\Entity\OgRole;
use Drupal\og\Og;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\taxonomy\TermInterface;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\views\Entity\View;
use Drupal\views\Views;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests who can see a term's name (#2643).
 *
 * @see \Drupal\mukurtu_core\Service\TermVisibility
 * @see \Drupal\mukurtu_taxonomy\Controller\TaxonomyRecordViewController::build()
 * @see mukurtu_core_update_40227()
 */
#[Group('mukurtu_taxonomy')]
class TermVisibilityTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block_content',
    'content_moderation',
    'entity_reference_revisions',
    'field',
    'file',
    'filter',
    'flat_taxonomy',
    'geofield',
    'image',
    'leaflet',
    'media',
    'mukurtu_core',
    'mukurtu_protocol',
    'mukurtu_taxonomy',
    'node',
    'og',
    'options',
    'paragraphs',
    'system',
    'taxonomy',
    'text',
    'user',
    'views',
    'workflows',
  ];

  /**
   * {@inheritdoc}
   *
   * mukurtu_taxonomy's optional dependencies aren't all enabled here.
   */
  protected $strictConfigSchema = FALSE;

  /**
   * The strict protocol.
   */
  protected Protocol $strict;

  /**
   * The open protocol.
   */
  protected Protocol $open;

  /**
   * A protocol member.
   */
  protected AccountInterface $member;

  /**
   * Someone with no memberships.
   */
  protected AccountInterface $visitor;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['og', 'taxonomy']);
    foreach (['block_content', 'node', 'workflow', 'taxonomy_term', 'og_membership', 'user', 'community', 'protocol'] as $type) {
      $this->installEntitySchema($type);
    }
    $this->installSchema('node', ['node_access']);
    $this->installSchema('mukurtu_protocol', ['mukurtu_protocol_map', 'mukurtu_protocol_access']);
    $this->container->get(NodeAccessRebuild::class)->rebuild();
    Og::addGroup('community', 'community');
    Og::addGroup('protocol', 'protocol');

    NodeType::create(['type' => 'thing', 'name' => 'Thing'])->save();
    Vocabulary::create(['vid' => 'keywords', 'name' => 'Keywords'])->save();
    FieldStorageConfig::create([
      'field_name' => 'field_keywords',
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'cardinality' => -1,
      'settings' => ['target_type' => 'taxonomy_term'],
    ])->save();
    FieldConfig::create(['field_name' => 'field_keywords', 'entity_type' => 'node', 'bundle' => 'thing', 'label' => 'Keywords'])->save();

    foreach (['anonymous', 'authenticated'] as $rid) {
      Role::create(['id' => $rid, 'label' => $rid])->grantPermission('access content')->save();
    }
    $role = OgRole::create(['name' => 'contributor', 'label' => 'Contributor', 'permissions' => []]);
    $role->setGroupType('protocol');
    $role->setGroupBundle('protocol');
    $role->save();

    // The first user is user 1, who bypasses access checks.
    $owner = User::create(['name' => 'owner']);
    $owner->save();
    $this->container->get('current_user')->setAccount($owner);
    $this->member = User::create(['name' => 'member', 'status' => 1]);
    $this->member->save();
    $this->visitor = User::create(['name' => 'visitor', 'status' => 1]);
    $this->visitor->save();

    $community = Community::create(['name' => 'Community']);
    $community->save();
    $community->addMember($owner);
    $community->addMember($this->member);
    foreach (['strict', 'open'] as $mode) {
      $this->$mode = Protocol::create(['name' => $mode, 'field_communities' => [$community->id()], 'field_access_mode' => $mode]);
      $this->$mode->save();
    }
    $this->strict->addMember($this->member, ['contributor']);
  }

  /**
   * Creates a term.
   */
  protected function createTerm(string $name, ?TermInterface $parent = NULL): TermInterface {
    $term = Term::create(['vid' => 'keywords', 'name' => $name, 'parent' => $parent?->id() ?? 0]);
    $term->save();
    return $term;
  }

  /**
   * Creates published content in a protocol, tagged with a term.
   */
  protected function tag(TermInterface $term, Protocol $protocol, bool $published = TRUE): void {
    $node = Node::create(['type' => 'thing', 'title' => $term->label(), 'status' => $published, 'field_keywords' => [$term->id()]]);
    $node->setProtocols([$protocol->id()]);
    $node->setSharingSetting('any');
    $node->save();
  }

  /**
   * Whether someone can see a term, with a fresh service.
   */
  protected function canSee(TermInterface $term, AccountInterface $account): bool {
    $this->container->set('mukurtu_core.term_visibility', NULL);
    return $this->container->get('mukurtu_core.term_visibility')->isVisible($term, $account);
  }

  /**
   * A term on restricted content shows only to people who can see that content.
   */
  public function testRestrictedTerm(): void {
    $term = $this->createTerm('Sensitive');
    $this->tag($term, $this->strict);

    $this->assertFalse($this->canSee($term, User::getAnonymousUser()));
    $this->assertFalse($this->canSee($term, $this->visitor));
    $this->assertTrue($this->canSee($term, $this->member));
  }

  /**
   * A term on open content shows to everyone.
   */
  public function testPublicTerm(): void {
    $term = $this->createTerm('Public');
    $this->tag($term, $this->open);

    $this->assertTrue($this->canSee($term, User::getAnonymousUser()));
    $this->assertTrue($this->canSee($term, $this->visitor));
  }

  /**
   * A term tagged on both open and strict content shows to everyone.
   */
  public function testTermOnBoth(): void {
    $term = $this->createTerm('Both');
    $this->tag($term, $this->strict);
    $this->tag($term, $this->open);

    $this->assertTrue($this->canSee($term, User::getAnonymousUser()));
  }

  /**
   * Content that isn't published doesn't count.
   */
  public function testUnpublishedContent(): void {
    $term = $this->createTerm('Draft');
    $this->tag($term, $this->open, FALSE);

    $this->assertFalse($this->canSee($term, User::getAnonymousUser()));
  }

  /**
   * Parents show when a nested term does, and nested terms don't follow them.
   */
  public function testParents(): void {
    $grandparent = $this->createTerm('Washington');
    $parent = $this->createTerm('Spokane', $grandparent);
    $child = $this->createTerm('Hillyard', $parent);
    $sibling = $this->createTerm('Seattle', $grandparent);
    $this->tag($child, $this->open);

    $anonymous = User::getAnonymousUser();
    $this->assertTrue($this->canSee($grandparent, $anonymous));
    $this->assertTrue($this->canSee($parent, $anonymous));
    $this->assertTrue($this->canSee($child, $anonymous));
    $this->assertFalse($this->canSee($sibling, $anonymous));
  }

  /**
   * The setting shows unused terms, but never terms on restricted content.
   */
  public function testShowUnusedSetting(): void {
    $unused = $this->createTerm('Unused');
    $sensitive = $this->createTerm('Sensitive');
    $this->tag($sensitive, $this->strict);
    $sensitive_parent = $this->createTerm('Sensitive parent');
    $this->tag($this->createTerm('Sensitive child', $sensitive_parent), $this->strict);

    $anonymous = User::getAnonymousUser();
    $this->assertFalse($this->canSee($unused, $anonymous));

    Vocabulary::load('keywords')->setThirdPartySetting('mukurtu_taxonomy', 'show_unused_terms', TRUE)->save();
    $this->assertTrue($this->canSee($unused, $anonymous));
    $this->assertFalse($this->canSee($sensitive, $anonymous));
    $this->assertFalse($this->canSee($sensitive_parent, $anonymous));
  }

  /**
   * Vocabularies not used on content items have no public terms.
   */
  public function testNonContentVocabularies(): void {
    $service = $this->container->get('mukurtu_core.term_visibility');
    $this->assertFalse($service->isPublicVocabulary('media_tag'));
    $this->assertFalse($service->isPublicVocabulary('community_type'));
    $this->assertFalse($service->isPublicVocabulary('interpersonal_relationship'));
    $this->assertTrue($service->isPublicVocabulary('keywords'));
    $this->assertSame([], $service->getVisibleTermIds('media_tag'));
  }

  /**
   * People who can edit a term can always see it.
   */
  public function testEditorsSeeTerms(): void {
    $term = $this->createTerm('Sensitive');
    $this->tag($term, $this->strict);
    Role::load('authenticated')->grantPermission('edit terms in keywords')->save();

    $this->assertTrue($this->canSee($term, User::load($this->visitor->id())));
    $this->assertFalse($this->canSee($term, User::getAnonymousUser()));
  }

  /**
   * A hidden term's page is a 404 that varies by visitor.
   */
  public function testTermPageIsNotFound(): void {
    $term = $this->createTerm('Sensitive');
    $this->tag($term, $this->strict);
    $this->container->get('current_user')->setAccount(User::getAnonymousUser());
    $this->container->set('mukurtu_core.term_visibility', NULL);

    $controller = TaxonomyRecordViewController::create($this->container);
    try {
      $controller->build($term);
      $this->fail('The term page should be a 404.');
    }
    catch (CacheableNotFoundHttpException $e) {
      $this->assertContains('user.node_grants:view', $e->getCacheContexts());
      $this->assertContains('node_list', $e->getCacheTags());
    }
  }

  /**
   * A term's ID doesn't redirect to its alias, which contains its name.
   */
  public function testTermPathDoesNotRedirectToItsAlias(): void {
    $route = $this->container->get('router.route_provider')->getRouteByName('entity.taxonomy_term.canonical');
    $this->assertTrue($route->getDefault('_disable_route_normalizer'));
  }

  /**
   * The shipped Categories view only lists terms the visitor can see.
   */
  public function testCategoriesView(): void {
    Vocabulary::create(['vid' => 'category', 'name' => 'Category'])->save();
    $category = function (string $name, Protocol $protocol): int {
      $term = Term::create(['vid' => 'category', 'name' => $name, 'parent' => 0]);
      $term->save();
      $this->tag($term, $protocol);
      return (int) $term->id();
    };
    $public = $category('Public', $this->open);
    $sensitive = $category('Sensitive', $this->strict);

    $path = $this->container->get('extension.list.module')->getPath('mukurtu_core');
    $values = Yaml::decode(file_get_contents("$path/config/install/views.view.mukurtu_categories.yml"));
    unset($values['dependencies']);
    View::create($values)->save();

    $tids = function (AccountInterface $account): array {
      $this->container->get('current_user')->setAccount($account);
      $this->container->set('mukurtu_core.term_visibility', NULL);
      $view = Views::getView('mukurtu_categories');
      $view->setDisplay('default');
      $view->display_handler->setOption('pager', ['type' => 'none']);
      $view->preExecute();
      $view->execute();
      return array_map(fn ($row) => (int) $row->tid, $view->result);
    };
    $this->assertSame([$public], $tids(User::getAnonymousUser()));
    $this->assertEqualsCanonicalizing([$public, $sensitive], $tids($this->member));

    $filter = Views::getView('mukurtu_categories');
    $filter->setDisplay('default');
    $filter->initHandlers();
    $this->assertContains('user.node_grants:view', $filter->filter['mukurtu_visible_terms']->getCacheContexts());
  }

  /**
   * The update unlinks terms, and leaves customized displays alone.
   */
  public function testUpdateHook(): void {
    require_once $this->container->get('extension.list.module')->getPath('mukurtu_core') . '/mukurtu_core.install';
    $factory = $this->container->get('config.factory');
    $shipped = 'core.entity_view_display.community.community.full';
    $custom = 'core.entity_view_display.community.community.default';
    $factory->getEditable($shipped)->set('content.field_community_type', ['type' => 'entity_reference_label', 'settings' => ['link' => TRUE]])->save();
    $factory->getEditable($custom)->set('content.field_community_type', ['type' => 'list_default', 'settings' => []])->save();

    $this->assertIsString(mukurtu_core_update_40227());

    $this->assertFalse($factory->get($shipped)->get('content.field_community_type.settings.link'));
    $this->assertSame(['type' => 'list_default', 'settings' => []], $factory->get($custom)->get('content.field_community_type'));
  }

}
