<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_taxonomy\Unit;

use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\taxonomy\VocabularyInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests how Mukurtu takes over the flat_taxonomy checkbox (#2519).
 *
 * @see mukurtu_taxonomy_form_taxonomy_vocabulary_form_alter()
 * @see mukurtu_taxonomy_flat_taxonomy_process()
 */
#[Group('mukurtu_taxonomy')]
class FlatTaxonomyGuardTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  public static function setUpBeforeClass(): void {
    parent::setUpBeforeClass();
    require_once __DIR__ . '/../../../mukurtu_taxonomy.module';
  }

  /**
   * Builds a form state whose form object edits a new or saved vocabulary.
   */
  protected function formState(bool $isNew): FormStateInterface {
    $vocabulary = $this->createMock(VocabularyInterface::class);
    $vocabulary->method('isNew')->willReturn($isNew);
    $formObject = $this->createMock(EntityFormInterface::class);
    $formObject->method('getEntity')->willReturn($vocabulary);
    $formState = $this->createMock(FormStateInterface::class);
    $formState->method('getFormObject')->willReturn($formObject);
    return $formState;
  }

  /**
   * The alter attaches the #process callback without touching the form.
   *
   * It must not modify $form['flat'] directly, since flat_taxonomy's own
   * hook_form_taxonomy_vocabulary_form_alter() may not have run yet.
   */
  public function testAlterAttachesProcessCallback(): void {
    $form = [];
    $formState = $this->createMock(FormStateInterface::class);

    mukurtu_taxonomy_form_taxonomy_vocabulary_form_alter($form, $formState);

    $this->assertSame(['mukurtu_taxonomy_flat_taxonomy_process'], $form['#process']);
    $this->assertArrayNotHasKey('flat', $form);
  }

  /**
   * The checkbox stays enabled and is on by default for new vocabularies.
   */
  #[DataProvider('newVocabularyProvider')]
  public function testCheckbox(bool $isNew, bool $initialDefaultValue, bool $expectedDefaultValue): void {
    $form = [
      'flat' => ['#type' => 'checkbox', '#default_value' => $initialDefaultValue],
      '#entity_builders' => [],
      'actions' => ['submit' => ['#submit' => ['::submitForm', '::save']]],
    ];

    $result = mukurtu_taxonomy_flat_taxonomy_process($form, $this->formState($isNew), $form);

    $this->assertSame($expectedDefaultValue, $result['flat']['#default_value']);
    $this->assertArrayNotHasKey('#disabled', $result['flat']);
    $this->assertNotEmpty($result['flat']['#description']->getUntranslatedString());
  }

  /**
   * Data provider: is the vocabulary new, the default, the expected default.
   */
  public static function newVocabularyProvider(): array {
    return [
      'new vocabulary is flat by default' => [TRUE, FALSE, TRUE],
      'saved nested vocabulary stays nested' => [FALSE, FALSE, FALSE],
      'saved flat vocabulary stays flat' => [FALSE, TRUE, TRUE],
    ];
  }

  /**
   * The flat_taxonomy builder is replaced, and the redirect runs after save.
   */
  public function testReplacesBuilderAndAddsSubmit(): void {
    $otherBuilder = [new \stdClass(), 'build'];
    $form = [
      'flat' => ['#type' => 'checkbox', '#default_value' => TRUE],
      '#entity_builders' => [$otherBuilder, 'flat_taxonomy_form_taxonomy_vocabulary_form_builder'],
      'actions' => ['submit' => ['#submit' => ['::submitForm', '::save']]],
    ];

    $result = mukurtu_taxonomy_flat_taxonomy_process($form, $this->formState(FALSE), $form);

    $this->assertSame([$otherBuilder, 'mukurtu_taxonomy_vocabulary_flat_builder'], $result['#entity_builders']);
    $this->assertSame(['::submitForm', '::save', 'mukurtu_taxonomy_vocabulary_flat_submit'], $result['actions']['submit']['#submit']);
  }

  /**
   * Forms without a 'flat' element are left untouched.
   */
  public function testFormWithoutFlatElementIsLeftAlone(): void {
    $form = [];

    $result = mukurtu_taxonomy_flat_taxonomy_process($form, $this->formState(FALSE), $form);

    $this->assertSame([], $result);
  }

}
