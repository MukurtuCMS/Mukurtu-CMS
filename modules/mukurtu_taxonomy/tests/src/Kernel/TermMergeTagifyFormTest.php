<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_taxonomy\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\taxonomy\VocabularyInterface;
use Drupal\term_merge\Form\MergeTerms;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the Tagify 'Terms to merge' element on the term merge form.
 *
 * term_merge 2.0.1 replaced its checkbox list with a comma-separated
 * autocomplete, so terms could no longer be browsed (#2438).
 * mukurtu_taxonomy_form_taxonomy_merge_terms_alter() swaps in a Tagify
 * element that lists every term on click. These tests run the real term_merge
 * form, with the alter applied, through the real form pipeline, so upstream's
 * own validateForm()/submitForm() consume the converted value.
 *
 * mukurtu_taxonomy's full dependency chain isn't needed, so its .module file
 * is required here rather than the module being enabled, mirroring
 * FlatTaxonomyCheckboxRenderTest.
 *
 * @see mukurtu_taxonomy_form_taxonomy_merge_terms_alter()
 * @see mukurtu_taxonomy_term_merge_terms_validate()
 */
#[Group('mukurtu_taxonomy')]
class TermMergeTagifyFormTest extends EntityKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'taxonomy',
    'field',
    'text',
    'filter',
    'term_reference_change',
    'term_merge',
    'tagify',
  ];

  /**
   * The vocabulary whose terms are merged.
   */
  protected VocabularyInterface $vocabulary;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('taxonomy_term');
    $this->installConfig(['taxonomy']);

    $this->vocabulary = Vocabulary::create(['vid' => 'category', 'name' => 'Category']);
    $this->vocabulary->save();
    Vocabulary::create(['vid' => 'other', 'name' => 'Other'])->save();

    require_once __DIR__ . '/../../../mukurtu_taxonomy.module';

    $this->setUpCurrentUser([], ['merge taxonomy terms']);
  }

  /**
   * Creates a term in the given vocabulary.
   */
  protected function createTerm(string $name, string $vid = 'category'): Term {
    $term = Term::create(['vid' => $vid, 'name' => $name]);
    $term->save();
    return $term;
  }

  /**
   * Encodes terms the way the Tagify widget submits them.
   *
   * @param \Drupal\taxonomy\Entity\Term[] $terms
   *   The selected terms.
   */
  protected function tagifyValue(array $terms): string {
    return json_encode(array_map(fn(Term $term) => [
      'value' => $term->label(),
      'entity_id' => $term->id(),
    ], $terms));
  }

  /**
   * Submits the merge form and returns the term IDs stored for the next step.
   */
  protected function submitTerms(string $value, ?FormStateInterface &$form_state = NULL): mixed {
    $form_state = (new FormState())
      ->addBuildInfo('args', [$this->vocabulary])
      ->setValues(['terms' => $value, 'op' => 'Next']);
    \Drupal::formBuilder()->submitForm(TermMergeTagifyTestForm::class, $form_state);

    return $this->container->get('tempstore.private')->get('term_merge')->get('terms');
  }

  /**
   * The terms element is a Tagify picker listing every term A to Z on click.
   */
  public function testElementIsBrowsableTagify(): void {
    $form = \Drupal::formBuilder()->getForm(TermMergeTagifyTestForm::class, $this->vocabulary);
    $element = $form['terms'];

    $this->assertSame('entity_autocomplete_tagify', $element['#type']);
    $this->assertSame(0, $element['#suggestions_dropdown']);
    $this->assertSame(0, $element['#selection_settings']['match_limit']);
    $this->assertSame(['category' => 'category'], $element['#selection_settings']['target_bundles']);
    $this->assertSame(['field' => 'name', 'direction' => 'ASC'], $element['#selection_settings']['sort']);
    $this->assertTrue($element['#required']);
    $this->assertArrayNotHasKey('data-max-items', $element['#attributes']);
    $this->assertSame('0', (string) $element['#attributes']['data-suggestions-dropdown']);
  }

  /**
   * Selected terms reach term_merge's tempstore for the target step.
   */
  public function testSelectedTermsAreStored(): void {
    $apple = $this->createTerm('Apple');
    $banana = $this->createTerm('Banana');

    $stored = $this->submitTerms($this->tagifyValue([$apple, $banana]), $form_state);

    $this->assertSame([], $form_state->getErrors());
    $this->assertSame([(int) $apple->id(), (int) $banana->id()], $stored);
  }

  /**
   * Terms from another vocabulary are dropped.
   */
  public function testTermsFromOtherVocabularyAreDropped(): void {
    $apple = $this->createTerm('Apple');
    $foreign = $this->createTerm('Foreign', 'other');

    $stored = $this->submitTerms($this->tagifyValue([$apple, $foreign]), $form_state);

    $this->assertSame([], $form_state->getErrors());
    $this->assertSame([(int) $apple->id()], $stored);
  }

  /**
   * Only foreign terms leaves nothing selected, which term_merge rejects.
   */
  public function testOnlyForeignTermsIsRejected(): void {
    $foreign = $this->createTerm('Foreign', 'other');

    $stored = $this->submitTerms($this->tagifyValue([$foreign]), $form_state);

    $this->assertArrayHasKey('terms', $form_state->getErrors());
    $this->assertNull($stored);
  }

}

/**
 * The real term_merge form with mukurtu_taxonomy's alter applied.
 *
 * mukurtu_taxonomy isn't enabled in this test, so its form alter hook doesn't
 * fire on its own.
 */
class TermMergeTagifyTestForm extends MergeTerms {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?VocabularyInterface $taxonomy_vocabulary = NULL) {
    $form = parent::buildForm($form, $form_state, $taxonomy_vocabulary);
    mukurtu_taxonomy_form_taxonomy_merge_terms_alter($form, $form_state, $this->getFormId());
    return $form;
  }

}
