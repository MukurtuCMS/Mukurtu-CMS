<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_taxonomy\Kernel;

use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\mukurtu_taxonomy\Batch\NestedTermsBatch;
use Drupal\mukurtu_taxonomy\NestedTerms;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\taxonomy\TermInterface;
use Drupal\taxonomy\VocabularyInterface;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests saving, flattening, and restoring nested terms (#2519).
 *
 * The mukurtu_taxonomy module is listed in $modules so its hooks and services
 * load, but KernelTestBase doesn't install its dependency chain.
 *
 * @see \Drupal\mukurtu_taxonomy\NestedTerms
 * @see \Drupal\mukurtu_taxonomy\Batch\NestedTermsBatch
 */
#[Group('mukurtu_taxonomy')]
class NestedTermsTest extends EntityKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'taxonomy',
    'field',
    'text',
    'filter',
    'flat_taxonomy',
    'language',
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
   * The service under test.
   */
  protected NestedTerms $nestedTerms;

  /**
   * A vocabulary that allows nested terms.
   */
  protected VocabularyInterface $vocabulary;

  /**
   * The progress message from the last batch pass run by runBatch().
   */
  protected string $lastBatchMessage = '';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('taxonomy_term');
    $this->installConfig(['taxonomy', 'language']);

    $this->vocabulary = Vocabulary::create(['vid' => 'location', 'name' => 'Location']);
    $this->vocabulary->save();

    $this->nestedTerms = $this->container->get('mukurtu_taxonomy.nested_terms');
  }

  /**
   * Creates a term.
   */
  protected function createTerm(string $name, int $parent = 0, int $weight = 0, string $vid = 'location'): TermInterface {
    $term = Term::create(['vid' => $vid, 'name' => $name, 'parent' => $parent, 'weight' => $weight]);
    $term->save();
    return $term;
  }

  /**
   * Reloads a term's parent ID.
   */
  protected function parentOf(TermInterface $term): int {
    return (int) Term::load($term->id())->get('parent')->target_id;
  }

  /**
   * Runs a batch operation until it finishes, then its finished callback.
   */
  protected function runBatch(string $operation, array $arguments, string $finished): array {
    $context = ['sandbox' => [], 'results' => [], 'finished' => 0];
    do {
      NestedTermsBatch::$operation(...[...$arguments, &$context]);
    } while ($context['finished'] < 1);
    NestedTermsBatch::$finished(TRUE, $context['results']);
    $this->lastBatchMessage = (string) ($context['message'] ?? '');
    return $context['results'];
  }

  /**
   * Builds a small tree: Washington > Spokane > Hillyard, and Oregon.
   *
   * @return \Drupal\taxonomy\TermInterface[]
   *   The terms, keyed by name.
   */
  protected function createTree(): array {
    $washington = $this->createTerm('Washington');
    $spokane = $this->createTerm('Spokane', (int) $washington->id(), 3);
    $hillyard = $this->createTerm('Hillyard', (int) $spokane->id(), 7);
    $oregon = $this->createTerm('Oregon');
    return compact('washington', 'spokane', 'hillyard', 'oregon');
  }

  /**
   * Nested terms are counted per vocabulary, ignoring top-level terms.
   */
  public function testCountNestedTerms(): void {
    Vocabulary::create(['vid' => 'other', 'name' => 'Other'])->save();
    $other = $this->createTerm('Other parent', 0, 0, 'other');
    $this->createTerm('Other child', (int) $other->id(), 0, 'other');

    $this->assertSame(0, $this->nestedTerms->countNestedTerms('location'));
    $terms = $this->createTree();

    $this->assertSame(2, $this->nestedTerms->countNestedTerms('location'));
    $this->assertEqualsCanonicalizing(
      [(int) $terms['spokane']->id(), (int) $terms['hillyard']->id()],
      $this->nestedTerms->getNestedTermIds('location')
    );
  }

  /**
   * The snapshot stores the parent and weight of nested terms only.
   */
  public function testSnapshot(): void {
    $terms = $this->createTree();
    $this->assertFalse($this->nestedTerms->hasSnapshot('location'));

    $this->nestedTerms->snapshot('location');

    $this->assertSame([
      (int) $terms['spokane']->id() => ['parent' => (int) $terms['washington']->id(), 'weight' => 3],
      (int) $terms['hillyard']->id() => ['parent' => (int) $terms['spokane']->id(), 'weight' => 7],
    ], $this->nestedTerms->getSnapshot('location'));
  }

  /**
   * Flattening moves nested terms up, then makes the vocabulary flat.
   *
   * Weights are kept, and the snapshot stays.
   */
  public function testFlatten(): void {
    $terms = $this->createTree();
    $this->nestedTerms->snapshot('location');

    $results = $this->runBatch('flatten', ['location', $this->nestedTerms->getNestedTermIds('location')], 'flattenFinished');

    $this->assertSame(2, $results['moved']);
    $this->assertSame('Moved 2 of 2 terms to the top level.', $this->lastBatchMessage);
    $this->assertSame(0, $this->parentOf($terms['spokane']));
    $this->assertSame(0, $this->parentOf($terms['hillyard']));
    $this->assertSame(7, (int) Term::load($terms['hillyard']->id())->getWeight());
    $this->assertSame(0, $this->nestedTerms->countNestedTerms('location'));
    $this->assertTrue($this->nestedTerms->isFlat(Vocabulary::load('location')));
    $this->assertTrue($this->nestedTerms->hasSnapshot('location'));
  }

  /**
   * A failed flatten leaves the vocabulary nested.
   */
  public function testFailedFlattenLeavesVocabularyNested(): void {
    NestedTermsBatch::flattenFinished(TRUE, ['vid' => 'location', 'moved' => 1, 'errors' => 1]);
    $this->assertFalse($this->nestedTerms->isFlat(Vocabulary::load('location')));

    NestedTermsBatch::flattenFinished(FALSE, ['vid' => 'location', 'moved' => 2, 'errors' => 0]);
    $this->assertFalse($this->nestedTerms->isFlat(Vocabulary::load('location')));
  }

  /**
   * Restoring puts terms back under their saved parents.
   *
   * Deleted terms are skipped, terms whose parent was deleted stay at the top
   * level, terms added while flat aren't touched, and the snapshot is deleted.
   */
  public function testRestore(): void {
    $terms = $this->createTree();
    $alaska = $this->createTerm('Alaska');
    $juneau = $this->createTerm('Juneau', (int) $alaska->id(), 2);
    $sitka = $this->createTerm('Sitka', (int) $alaska->id(), 4);
    $this->nestedTerms->snapshot('location');
    $this->runBatch('flatten', ['location', $this->nestedTerms->getNestedTermIds('location')], 'flattenFinished');

    // While flat: a parent and a child are deleted, a term is added, and
    // weights change.
    $alaska->delete();
    $sitka->delete();
    $added = $this->createTerm('Idaho');
    Term::load($terms['spokane']->id())->setWeight(50)->save();

    // Allow nesting again, as the vocabulary form would.
    $vocabulary = Vocabulary::load('location');
    $vocabulary->unsetThirdPartySetting('flat_taxonomy', 'flat')->save();

    $results = $this->runBatch('restore', ['location', $this->nestedTerms->getSnapshot('location')], 'restoreFinished');

    $this->assertSame(2, $results['restored']);
    $this->assertSame('Restored 4 of 4 terms.', $this->lastBatchMessage);
    $this->assertSame(1, $results['orphaned']);
    $this->assertSame((int) $terms['washington']->id(), $this->parentOf($terms['spokane']));
    $this->assertSame(3, (int) Term::load($terms['spokane']->id())->getWeight());
    $this->assertSame((int) $terms['spokane']->id(), $this->parentOf($terms['hillyard']));
    $this->assertSame(0, $this->parentOf($juneau));
    $this->assertSame(0, $this->parentOf($added));
    $this->assertFalse($this->nestedTerms->hasSnapshot('location'));
  }

  /**
   * A failed restore keeps the snapshot so it can be tried again.
   */
  public function testFailedRestoreKeepsSnapshot(): void {
    $this->createTree();
    $this->nestedTerms->snapshot('location');

    NestedTermsBatch::restoreFinished(TRUE, ['vid' => 'location', 'restored' => 1, 'orphaned' => 0, 'errors' => 1]);

    $this->assertTrue($this->nestedTerms->hasSnapshot('location'));
  }

  /**
   * Deleting a vocabulary deletes its snapshot.
   */
  public function testVocabularyDeleteDeletesSnapshot(): void {
    $this->createTree();
    $this->nestedTerms->snapshot('location');

    Vocabulary::load('location')->delete();

    $this->assertFalse($this->nestedTerms->hasSnapshot('location'));
  }

  /**
   * Messages use the translated name, which is never saved as the original.
   *
   * Flattening saves the vocabulary when it finishes. Saving a copy loaded
   * with translations would replace the original name with the translation.
   */
  public function testTranslatedVocabularyName(): void {
    ConfigurableLanguage::createFromLangcode('es')->save();
    $language_manager = $this->container->get('language_manager');
    $language_manager->getLanguageConfigOverride('es', 'taxonomy.vocabulary.location')->set('name', 'Ubicación')->save();
    $language_manager->setConfigOverrideLanguage($language_manager->getLanguage('es'));

    $this->assertSame('Ubicación', $this->nestedTerms->label('location'));

    $this->createTree();
    $this->nestedTerms->snapshot('location');
    $this->runBatch('flatten', ['location', $this->nestedTerms->getNestedTermIds('location')], 'flattenFinished');

    $this->assertSame('Location', $this->config('taxonomy.vocabulary.location')->getOriginal('name', FALSE));
    $this->assertTrue($this->nestedTerms->isFlat($this->container->get('entity_type.manager')->getStorage('taxonomy_vocabulary')->loadOverrideFree('location')));
    $messages = array_map('strval', $this->container->get('messenger')->messagesByType('status'));
    $this->assertStringContainsString('Ubicación', implode(' ', $messages));
  }

  /**
   * A term can have only one parent.
   */
  public function testSingleParentConstraint(): void {
    $washington = $this->createTerm('Washington');
    $oregon = $this->createTerm('Oregon');

    $term = Term::create(['vid' => 'location', 'name' => 'Columbia River']);
    $this->assertCount(0, $term->validate());

    $term->set('parent', [(int) $washington->id()]);
    $this->assertCount(0, $term->validate());

    $term->set('parent', [(int) $washington->id(), (int) $oregon->id()]);
    $violations = $term->validate();
    $this->assertCount(1, $violations);
    $this->assertSame('parent', $violations[0]->getPropertyPath());
    $this->assertSame('A term can have only one parent.', (string) $violations[0]->getMessage());
  }

}
