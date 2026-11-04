<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_taxonomy\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\taxonomy\TermInterface;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that deleting or merging a parent term keeps its children (#2520).
 *
 * @see mukurtu_taxonomy_taxonomy_term_predelete()
 * @see mukurtu_taxonomy_taxonomy_vocabulary_delete()
 * @see mukurtu_taxonomy_term_delete_form_description()
 * @see \Drupal\mukurtu_taxonomy\EventSubscriber\TermMergeChildrenSubscriber
 */
#[Group('mukurtu_taxonomy')]
class KeepChildTermsTest extends EntityKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'taxonomy',
    'field',
    'text',
    'filter',
    'node',
    'flat_taxonomy',
    'term_reference_change',
    'term_merge',
    'mukurtu_taxonomy',
  ];

  /**
   * {@inheritdoc}
   *
   * The flat_taxonomy module's own schema file is never discovered.
   * mukurtu_core supplies the schema for its 'flat' setting, and isn't
   * enabled here.
   */
  protected $strictConfigSchema = FALSE;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['taxonomy', 'node']);
    $this->setUpCurrentUser([], ['administer taxonomy']);

    Vocabulary::create(['vid' => 'location', 'name' => 'Location'])->save();
  }

  /**
   * Creates a term.
   */
  protected function createTerm(string $name, int $parent = 0, string $vid = 'location'): TermInterface {
    $term = Term::create(['vid' => $vid, 'name' => $name, 'parent' => $parent]);
    $term->save();
    return $term;
  }

  /**
   * Reloads a term's parent ID.
   */
  protected function parentOf(TermInterface $term): int {
    $this->container->get('entity_type.manager')->getStorage('taxonomy_term')->resetCache();
    return (int) Term::load($term->id())->get('parent')->target_id;
  }

  /**
   * Builds a small tree: Washington > Spokane > Hillyard and Browne's Addition.
   *
   * @return \Drupal\taxonomy\TermInterface[]
   *   The terms, keyed by name.
   */
  protected function createTree(): array {
    $washington = $this->createTerm('Washington');
    $spokane = $this->createTerm('Spokane', (int) $washington->id());
    $hillyard = $this->createTerm('Hillyard', (int) $spokane->id());
    $browne = $this->createTerm("Browne's Addition", (int) $spokane->id());
    return compact('washington', 'spokane', 'hillyard', 'browne');
  }

  /**
   * Deleting a nested term moves its children under its parent.
   *
   * Their own children stay with them.
   */
  public function testDeleteMovesChildrenUp(): void {
    $terms = $this->createTree();
    $grandchild = $this->createTerm('Market Street', (int) $terms['hillyard']->id());

    $terms['spokane']->delete();

    $this->assertSame((int) $terms['washington']->id(), $this->parentOf($terms['hillyard']));
    $this->assertSame((int) $terms['washington']->id(), $this->parentOf($terms['browne']));
    $this->assertSame((int) $terms['hillyard']->id(), $this->parentOf($grandchild));
  }

  /**
   * Deleting a top-level term moves its children to the top level.
   */
  public function testDeleteTopLevelMovesChildrenToTop(): void {
    $terms = $this->createTree();

    $terms['washington']->delete();

    $this->assertSame(0, $this->parentOf($terms['spokane']));
    $this->assertSame((int) $terms['spokane']->id(), $this->parentOf($terms['hillyard']));
  }

  /**
   * Deleting a vocabulary still deletes all of its terms.
   */
  public function testVocabularyDeleteDeletesAllTerms(): void {
    $this->createTree();
    Vocabulary::create(['vid' => 'other', 'name' => 'Other'])->save();
    $other = $this->createTerm('Other', 0, 'other');

    Vocabulary::load('location')->delete();

    $storage = $this->container->get('entity_type.manager')->getStorage('taxonomy_term');
    $storage->resetCache();
    $this->assertSame([], $storage->loadByProperties(['vid' => 'location']));
    $this->assertNotNull(Term::load($other->id()));
  }

  /**
   * Builds a term's delete form.
   */
  protected function buildDeleteForm(TermInterface $term): array {
    return $this->container->get('entity.form_builder')->getForm($term, 'delete');
  }

  /**
   * The delete form says where the term's children will move.
   */
  public function testDeleteFormDescription(): void {
    $terms = $this->createTree();

    $form = $this->buildDeleteForm($terms['spokane']);
    $this->assertSame(
      '2 terms nested under <em class="placeholder">Spokane</em> will move under <em class="placeholder">Washington</em>. This action cannot be undone.',
      (string) $form['description']['#markup']
    );

    $form = $this->buildDeleteForm($terms['washington']);
    $this->assertSame(
      '1 term nested under <em class="placeholder">Washington</em> will move to the top level. This action cannot be undone.',
      (string) $form['description']['#markup']
    );

    $form = $this->buildDeleteForm($terms['hillyard']);
    $this->assertSame('This action cannot be undone.', (string) $form['description']['#markup']);
  }

  /**
   * A category in use can't be deleted, so its children don't move.
   */
  public function testCategoryGuardMovesNothing(): void {
    Vocabulary::create(['vid' => 'category', 'name' => 'Category'])->save();
    NodeType::create(['type' => 'digital_heritage', 'name' => 'Digital heritage'])->save();
    FieldStorageConfig::create([
      'field_name' => 'field_category',
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'settings' => ['target_type' => 'taxonomy_term'],
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_category',
      'entity_type' => 'node',
      'bundle' => 'digital_heritage',
    ])->save();

    $parent = $this->createTerm('Arts', 0, 'category');
    $child = $this->createTerm('Weaving', (int) $parent->id(), 'category');
    Node::create(['type' => 'digital_heritage', 'title' => 'Basket', 'field_category' => $parent->id()])->save();

    $form_object = $this->container->get('entity_type.manager')->getFormObject('taxonomy_term', 'delete')->setEntity($parent);
    $form_state = (new FormState())->setValues(['confirm' => 1, 'op' => 'Delete']);
    $this->container->get('form_builder')->submitForm($form_object, $form_state);

    $this->assertNotEmpty($form_state->getErrors());
    $this->assertNotNull(Term::load($parent->id()));
    $this->assertSame((int) $parent->id(), $this->parentOf($child));
  }

  /**
   * Merging terms moves their children under the target term.
   */
  public function testMergeMovesChildrenToTarget(): void {
    $terms = $this->createTree();
    $oregon = $this->createTerm('Oregon');

    $this->container->get('term_merge.term_merger')->mergeIntoTerm([$terms['spokane']], $oregon);

    $this->assertNull(Term::load($terms['spokane']->id()));
    $this->assertSame((int) $oregon->id(), $this->parentOf($terms['hillyard']));
    $this->assertSame((int) $oregon->id(), $this->parentOf($terms['browne']));
  }

  /**
   * Merging a term into one of its own children never makes a loop.
   *
   * The target moves up to the merged term's parent, and its siblings move
   * under it.
   */
  public function testMergeIntoChild(): void {
    $terms = $this->createTree();

    $this->container->get('term_merge.term_merger')->mergeIntoTerm([$terms['spokane']], $terms['hillyard']);

    $this->assertSame((int) $terms['washington']->id(), $this->parentOf($terms['hillyard']));
    $this->assertSame((int) $terms['hillyard']->id(), $this->parentOf($terms['browne']));
  }

}
