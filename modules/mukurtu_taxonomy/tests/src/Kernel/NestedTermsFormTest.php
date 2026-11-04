<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_taxonomy\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\taxonomy\VocabularyInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the vocabulary and term form changes for nested terms (#2519).
 *
 * @see mukurtu_taxonomy_flat_taxonomy_process()
 * @see mukurtu_taxonomy_vocabulary_flat_builder()
 * @see mukurtu_taxonomy_vocabulary_flat_submit()
 * @see mukurtu_taxonomy_form_taxonomy_term_form_alter()
 */
#[Group('mukurtu_taxonomy')]
class NestedTermsFormTest extends EntityKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'taxonomy',
    'field',
    'text',
    'filter',
    'flat_taxonomy',
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
    $this->installConfig(['taxonomy']);
    $this->setUpCurrentUser([], ['administer taxonomy']);
  }

  /**
   * Creates a vocabulary, flat or nested.
   */
  protected function createVocabulary(string $vid, bool $flat): VocabularyInterface {
    $vocabulary = Vocabulary::create(['vid' => $vid, 'name' => ucfirst($vid)]);
    if ($flat) {
      $vocabulary->setThirdPartySetting('flat_taxonomy', 'flat', 1);
    }
    $vocabulary->save();
    return $vocabulary;
  }

  /**
   * Builds a vocabulary's edit or add form.
   */
  protected function buildVocabularyForm(VocabularyInterface $vocabulary): array {
    return $this->container->get('entity.form_builder')->getForm($vocabulary);
  }

  /**
   * The Flat checkbox is enabled, explained, and uses Mukurtu's builder.
   *
   * It's checked by default on a new vocabulary.
   */
  public function testVocabularyFormCheckbox(): void {
    $form = $this->buildVocabularyForm($this->createVocabulary('location', FALSE));

    $this->assertArrayNotHasKey('disabled', $form['flat']['#attributes'] ?? []);
    $this->assertEmpty($form['flat']['#value']);
    $this->assertStringContainsString("can't be nested", (string) $form['flat']['#description']);
    $this->assertContains('mukurtu_taxonomy_vocabulary_flat_builder', $form['#entity_builders']);
    $this->assertNotContains('flat_taxonomy_form_taxonomy_vocabulary_form_builder', $form['#entity_builders']);
    $this->assertContains('mukurtu_taxonomy_vocabulary_flat_submit', $form['actions']['submit']['#submit']);

    $form = $this->buildVocabularyForm(Vocabulary::create(['vid' => 'new_one', 'name' => 'New one']));
    $this->assertEquals(1, $form['flat']['#value']);
  }

  /**
   * The entity builder sets the flat setting and the redirect flag.
   *
   * @param bool $was_flat
   *   Whether the saved vocabulary is flat.
   * @param bool $has_nested
   *   Whether the vocabulary has a nested term.
   * @param bool $has_snapshot
   *   Whether a saved structure exists.
   * @param bool $submitted
   *   The submitted Flat value.
   * @param bool $expect_flat
   *   Whether the vocabulary should end up flat.
   * @param string|null $expect_redirect
   *   The expected redirect route, if any.
   */
  #[DataProvider('builderProvider')]
  public function testBuilder(bool $was_flat, bool $has_nested, bool $has_snapshot, bool $submitted, bool $expect_flat, ?string $expect_redirect): void {
    $vocabulary = $this->createVocabulary('location', FALSE);
    if ($has_nested) {
      $parent = Term::create(['vid' => 'location', 'name' => 'Washington']);
      $parent->save();
      Term::create(['vid' => 'location', 'name' => 'Spokane', 'parent' => $parent->id()])->save();
    }
    if ($was_flat) {
      $vocabulary->setThirdPartySetting('flat_taxonomy', 'flat', 1)->save();
    }
    if ($has_snapshot) {
      $this->container->get('keyvalue')
        ->get('mukurtu_taxonomy.nested_terms_snapshot')
        ->set('location', [5 => ['parent' => 4, 'weight' => 0]]);
    }

    $form = [];
    $form_state = (new FormState())->setValue('flat', (int) $submitted);
    $entity = Vocabulary::load('location');
    // Builders run during validation and again on submit.
    mukurtu_taxonomy_vocabulary_flat_builder('taxonomy_vocabulary', $entity, $form, $form_state);
    mukurtu_taxonomy_vocabulary_flat_builder('taxonomy_vocabulary', $entity, $form, $form_state);

    $this->assertSame($expect_flat, (bool) $entity->getThirdPartySetting('flat_taxonomy', 'flat'));
    $this->assertSame($expect_redirect, $form_state->get('mukurtu_taxonomy_flat_redirect'));
  }

  /**
   * Data provider for testBuilder().
   */
  public static function builderProvider(): array {
    return [
      'make flat, nested terms: confirm first' => [
        FALSE, TRUE, FALSE, TRUE, FALSE, 'mukurtu_taxonomy.flatten_vocabulary',
      ],
      'make flat, no nested terms' => [FALSE, FALSE, FALSE, TRUE, TRUE, NULL],
      'stay flat' => [TRUE, FALSE, FALSE, TRUE, TRUE, NULL],
      'allow nesting, snapshot: offer restore' => [
        TRUE, FALSE, TRUE, FALSE, FALSE, 'mukurtu_taxonomy.restore_nesting',
      ],
      'allow nesting, no snapshot' => [TRUE, FALSE, FALSE, FALSE, FALSE, NULL],
      'stay nested, snapshot kept from a declined restore' => [FALSE, TRUE, TRUE, FALSE, FALSE, NULL],
    ];
  }

  /**
   * Saving a flat vocabulary without changing Flat doesn't re-save its terms.
   */
  public function testUnchangedFlatSaveLeavesTermsAlone(): void {
    // A nested term in a flat vocabulary can only exist if something went
    // around the forms, which makes it a marker: flat_taxonomy's builder
    // re-saved every term on every save, which would move it to the top.
    $vocabulary = $this->createVocabulary('location', FALSE);
    $parent = Term::create(['vid' => 'location', 'name' => 'Washington']);
    $parent->save();
    $child = Term::create(['vid' => 'location', 'name' => 'Spokane', 'parent' => $parent->id()]);
    $child->save();
    $vocabulary->setThirdPartySetting('flat_taxonomy', 'flat', 1)->save();

    $form = [];
    $form_state = (new FormState())->setValue('flat', 1);
    mukurtu_taxonomy_vocabulary_flat_builder('taxonomy_vocabulary', $vocabulary, $form, $form_state);
    $vocabulary->save();

    $this->assertSame((int) $parent->id(), (int) Term::load($child->id())->get('parent')->target_id);
  }

  /**
   * The submit handler redirects to the flagged confirm page.
   */
  public function testSubmitRedirect(): void {
    $vocabulary = $this->createVocabulary('location', FALSE);
    $form_object = $this->container->get('entity_type.manager')->getFormObject('taxonomy_vocabulary', 'default');
    $form_object->setEntity($vocabulary);
    $form = [];

    $form_state = (new FormState())->setFormObject($form_object);
    mukurtu_taxonomy_vocabulary_flat_submit($form, $form_state);
    $this->assertNull($form_state->getRedirect());

    $form_state->set('mukurtu_taxonomy_flat_redirect', 'mukurtu_taxonomy.flatten_vocabulary');
    mukurtu_taxonomy_vocabulary_flat_submit($form, $form_state);
    $redirect = $form_state->getRedirect();
    $this->assertSame('mukurtu_taxonomy.flatten_vocabulary', $redirect->getRouteName());
    $this->assertSame(['taxonomy_vocabulary' => 'location'], $redirect->getRouteParameters());
  }

  /**
   * Builds the term form for a new term.
   */
  protected function buildTermForm(string $vid): array {
    return $this->container->get('entity.form_builder')->getForm(Term::create(['vid' => $vid]));
  }

  /**
   * Relations stay hidden on a flat stock vocabulary.
   */
  public function testTermFormFlatStockVocabulary(): void {
    $this->createVocabulary('location', TRUE);
    $form = $this->buildTermForm('location');
    $this->assertFalse($form['relations']['#access']);
  }

  /**
   * A vocabulary that allows nesting offers a single parent.
   *
   * This applies to vocabularies that aren't stock ones too, and the choice
   * is saved as one parent.
   */
  #[DataProvider('nestedVocabularyProvider')]
  public function testTermFormNestedVocabulary(string $vid): void {
    $this->createVocabulary($vid, FALSE);
    $parent = Term::create(['vid' => $vid, 'name' => 'Washington']);
    $parent->save();

    $form = $this->buildTermForm($vid);

    $this->assertNotFalse($form['relations']['#access'] ?? TRUE);
    $this->assertFalse($form['relations']['parent']['#multiple']);
    $this->assertSame('Parent term', (string) $form['relations']['parent']['#title']);
    $this->assertArrayHasKey($parent->id(), $form['relations']['parent']['#options']);

    $form_state = (new FormState())->setValues([
      'name' => [['value' => 'Spokane']],
      'parent' => (string) $parent->id(),
      'weight' => 0,
    ]);
    $form_state->setTriggeringElement($form['actions']['submit']);
    $form_state->setValue('op', $form['actions']['submit']['#value']);
    $this->container->get('form_builder')->submitForm($this->container->get('entity_type.manager')->getFormObject('taxonomy_term', 'default')->setEntity(Term::create(['vid' => $vid])), $form_state);

    $this->assertSame([], array_map('strval', $form_state->getErrors()));
    $saved = $this->container->get('entity_type.manager')->getStorage('taxonomy_term')->loadByProperties(['name' => 'Spokane']);
    $this->assertCount(1, $saved);
    $this->assertSame([(int) $parent->id()], array_map('intval', array_column(reset($saved)->get('parent')->getValue(), 'target_id')));
  }

  /**
   * Data provider for testTermFormNestedVocabulary().
   */
  public static function nestedVocabularyProvider(): array {
    return [
      'stock vocabulary' => ['location'],
      'administrator-made vocabulary' => ['regions'],
    ];
  }

}
