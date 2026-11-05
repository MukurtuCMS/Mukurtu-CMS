<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_taxonomy\Kernel;

use Drupal\Core\Form\EnforcedResponseException;
use Drupal\Core\Form\FormState;
use Drupal\Core\Render\RenderContext;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\mukurtu_taxonomy\Form\FlattenVocabularyConfirmForm;
use Drupal\mukurtu_taxonomy\Form\RestoreNestingConfirmForm;
use Drupal\mukurtu_taxonomy\NestedTerms;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\taxonomy\TermInterface;
use Drupal\taxonomy\VocabularyInterface;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the Make flat and Restore nesting confirm forms (#2519).
 *
 * @see \Drupal\mukurtu_taxonomy\Form\FlattenVocabularyConfirmForm
 * @see \Drupal\mukurtu_taxonomy\Form\RestoreNestingConfirmForm
 */
#[Group('mukurtu_taxonomy')]
class NestedTermsConfirmFormTest extends EntityKernelTestBase {

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
   * The nested terms service.
   */
  protected NestedTerms $nestedTerms;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('taxonomy_term');
    $this->installConfig(['taxonomy']);
    $this->container->get('router.builder')->rebuild();
    $this->setUpCurrentUser([], ['administer taxonomy']);

    Vocabulary::create(['vid' => 'location', 'name' => 'Location'])->save();
    $this->nestedTerms = $this->container->get('mukurtu_taxonomy.nested_terms');
  }

  /**
   * Creates Spokane nested under Washington.
   *
   * @return \Drupal\taxonomy\TermInterface
   *   The nested term.
   */
  protected function createNestedTerm(): TermInterface {
    $washington = Term::create(['vid' => 'location', 'name' => 'Washington']);
    $washington->save();
    $spokane = Term::create(['vid' => 'location', 'name' => 'Spokane', 'parent' => $washington->id()]);
    $spokane->save();
    return $spokane;
  }

  /**
   * Makes the vocabulary flat or lets it nest.
   */
  protected function setFlat(bool $flat): VocabularyInterface {
    $vocabulary = Vocabulary::load('location');
    $flat ? $vocabulary->setThirdPartySetting('flat_taxonomy', 'flat', 1) : $vocabulary->unsetThirdPartySetting('flat_taxonomy', 'flat');
    $vocabulary->save();
    return $vocabulary;
  }

  /**
   * Builds a confirm form, returning the redirect path if it sends the user on.
   */
  protected function redirectFrom(string $form_class): ?string {
    try {
      $this->container->get('form_builder')->getForm($form_class, Vocabulary::load('location'));
    }
    catch (EnforcedResponseException $e) {
      return parse_url($e->getResponse()->getTargetUrl(), PHP_URL_PATH);
    }
    return NULL;
  }

  /**
   * Submits a confirm form, which runs its batch straight away.
   */
  protected function submit(string $form_class, string $op): FormState {
    $form_state = (new FormState())
      ->addBuildInfo('args', [Vocabulary::load('location')])
      ->setValues(['confirm' => 1, 'op' => $op]);
    $this->container->get('renderer')->executeInRenderContext(
      new RenderContext(),
      fn () => $this->container->get('form_builder')->submitForm($form_class, $form_state),
    );
    return $form_state;
  }

  /**
   * The Make flat page only opens when there's nesting to move.
   */
  public function testFlattenFormRedirectsWhenNothingToMove(): void {
    $this->assertSame('/admin/structure/taxonomy/manage/location', $this->redirectFrom(FlattenVocabularyConfirmForm::class));

    $this->createNestedTerm();
    $this->assertNull($this->redirectFrom(FlattenVocabularyConfirmForm::class));

    // Flat already, for example confirmed in another tab.
    $this->setFlat(TRUE);
    $this->assertSame('/admin/structure/taxonomy/manage/location', $this->redirectFrom(FlattenVocabularyConfirmForm::class));
  }

  /**
   * Confirming saves the nesting, then moves the terms and makes it flat.
   */
  public function testFlattenFormSubmit(): void {
    $spokane = $this->createNestedTerm();
    $parent = (int) $spokane->get('parent')->target_id;

    $form_state = $this->submit(FlattenVocabularyConfirmForm::class, 'Make flat');

    $this->assertSame([], array_map('strval', $form_state->getErrors()));
    $this->assertSame([(int) $spokane->id() => ['parent' => $parent, 'weight' => 0]], $this->nestedTerms->getSnapshot('location'));
    $this->assertSame(0, $this->nestedTerms->countNestedTerms('location'));
    $this->assertTrue($this->nestedTerms->isFlat(Vocabulary::load('location')));
  }

  /**
   * The Restore nesting page only opens when nesting is allowed and saved.
   *
   * It still opens on a vocabulary with nested terms, so a failed or
   * declined restore can be tried again.
   */
  public function testRestoreFormRedirectsWhenNothingToRestore(): void {
    $overview = '/admin/structure/taxonomy/manage/location/overview';
    $this->assertSame($overview, $this->redirectFrom(RestoreNestingConfirmForm::class));

    $this->createNestedTerm();
    $this->nestedTerms->snapshot('location');
    $this->assertNull($this->redirectFrom(RestoreNestingConfirmForm::class));

    $this->setFlat(TRUE);
    $this->assertSame($overview, $this->redirectFrom(RestoreNestingConfirmForm::class));
  }

  /**
   * Confirming puts the terms back, then deletes the saved nesting.
   */
  public function testRestoreFormSubmit(): void {
    $spokane = $this->createNestedTerm();
    $parent = (int) $spokane->get('parent')->target_id;
    $this->nestedTerms->snapshot('location');
    Term::load($spokane->id())->set('parent', 0)->save();

    $form_state = $this->submit(RestoreNestingConfirmForm::class, 'Restore nesting');

    $this->assertSame([], array_map('strval', $form_state->getErrors()));
    $this->assertSame($parent, (int) Term::load($spokane->id())->get('parent')->target_id);
    $this->assertFalse($this->nestedTerms->hasSnapshot('location'));
  }

}
