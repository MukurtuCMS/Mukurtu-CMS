<?php

namespace Drupal\Tests\term_merge\Functional\Form;

use Drupal\Core\Form\FormState;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\term_merge\Form\MergeTerms;
use Drupal\Tests\term_merge\Functional\MergeTermsTestBase;

/**
 * Tests the term merge form.
 *
 * @group term_merge
 */
class MergeTermsTest extends MergeTermsTestBase {

  /**
   * Tests the title callback for the term merge form.
   *
   * @test
   */
  public function hasTitleCallback(): void {
    $sut = $this->createSubjectUnderTest();
    $vocabulary = $this->createVocabulary();

    $expected = new TranslatableMarkup('Merge %vocabulary terms', ['%vocabulary' => $vocabulary->label()]);
    self::assertEquals($expected, $sut->titleCallback($vocabulary));
  }

  /**
   * Tests a term merge form for a vocabulary without terms.
   *
   * @test
   */
  public function vocabularyWithoutTermsReturnsEmptyForm(): void {
    $vocabulary = $this->createVocabulary();
    $sut = $this->createSubjectUnderTest();

    $actual = $sut->buildForm([], new FormState(), $vocabulary);
    self::assertEquals($this->getEmptyFormExpectation(), $actual);
  }

  /**
   * Tests a term merge form for a vocabulary with terms.
   *
   * @test
   */
  public function vocabularyWithTerms(): void {
    $vocabulary = $this->createVocabulary();
    $term1 = $this->createTerm($vocabulary);
    $term2 = $this->createTerm($vocabulary);
    $sut = $this->createSubjectUnderTest();

    $actual = $sut->buildForm([], new FormState(), $vocabulary);

    $expected = $this->getEmptyFormExpectation();
    $expected['terms']['#options'][$term1->id()] = $term1->label();
    $expected['terms']['#options'][$term2->id()] = $term2->label();
    self::assertEquals($expected, $actual);
  }

  /**
   * Test data provider for validatesSelectedTerms.
   *
   * @return array
   *   An array of selections. Each selection has contains the following values:
   *   - selectedTerms: an array of selected source taxonomy term ids.
   *   - expectingErrors: a boolean indicating the form is expected to generate
   *     an error.
   */
  public static function validatesSelectedTermsTestDataProvider(): array {
    $test_data['No terms selected'] = [
      'selectedTerms' => [],
      'expectingErrors' => TRUE,
    ];

    $test_data['One term selected'] = [
      'selectedTerms' => [1],
      'expectingErrors' => FALSE,
    ];

    $test_data['Two terms selected'] = [
      'selectedTerms' => [1, 2],
      'expectingErrors' => FALSE,
    ];

    $test_data['three terms selected'] = [
      'selectedTerms' => [1, 2, 3],
      'expectingErrors' => FALSE,
    ];

    return $test_data;
  }

  /**
   * Checks the form validation for the merge terms form.
   *
   * @param array $selected_terms
   *   The selected term ids.
   * @param bool $expecting_errors
   *   If a validation error is expected.
   *
   * @test
   *
   * @dataProvider validatesSelectedTermsTestDataProvider
   */
  public function validatesSelectedTerms(array $selected_terms, bool $expecting_errors) {
    $vocabulary = $this->createVocabulary();
    $this->createTerm($vocabulary);
    $this->createTerm($vocabulary);
    $this->createTerm($vocabulary);
    $sut = $this->createSubjectUnderTest();

    $form_state = new FormState();
    $form_state->setValue('terms', $selected_terms);
    $form = $sut->buildForm([], $form_state, $vocabulary);

    $sut->validateForm($form, $form_state);

    self::assertSame($expecting_errors, !empty($form_state->getErrors()));
  }

  /**
   * Tests the form redirects to the confirm form.
   *
   * @test
   */
  public function redirectsToConfirmationForm(): void {
    $vocabulary = $this->createVocabulary();
    $sut = $this->createSubjectUnderTest();

    $form_state = new FormState();
    $form_state->setValue('terms', [1, 2]);
    $form = $sut->buildForm([], $form_state, $vocabulary);

    $sut->submitForm($form, $form_state);

    $route_name = 'entity.taxonomy_vocabulary.merge_target';
    $route_parameters['taxonomy_vocabulary'] = $vocabulary->id();
    $expected = new Url($route_name, $route_parameters);
    self::assertEquals($expected, $form_state->getRedirect());
  }

  /**
   * Tests merge terms are saved to the temp store.
   *
   * @test
   */
  public function setsLocalStorage(): void {
    $vocabulary = $this->createVocabulary();
    $sut = $this->createSubjectUnderTest();
    $form_state = new FormState();
    $expected_term_ids = [1, 2];
    $form_state->setValue('terms', $expected_term_ids);
    $form = $sut->buildForm([], $form_state, $vocabulary);

    self::assertEmpty($this->privateTempStoreFactory->get('term_merge')->get('terms'));
    $sut->submitForm($form, $form_state);

    self::assertEquals($expected_term_ids, $this->privateTempStoreFactory->get('term_merge')->get('terms'));
  }

  /**
   * Returns the expected form structure when the form is empty.
   *
   * @return array
   *   A renderable array.
   */
  private function getEmptyFormExpectation(): array {
    return [
      'terms' => [
        '#type' => 'select',
        '#title' => new TranslatableMarkup("Terms to merge"),
        '#options' => [],
        '#empty_option' => new TranslatableMarkup('Select two or more terms to merge together'),
        '#multiple' => TRUE,
        '#required' => TRUE,
      ],
      'actions' => [
        '#type' => 'actions',
        'submit' => [
          '#button_type' => 'primary',
          '#type' => 'submit',
          '#value' => new TranslatableMarkup('Merge'),
        ],
      ],
    ];
  }

  /**
   * Creates the form class used for rendering the merge terms form.
   *
   * @return \Drupal\term_merge\Form\MergeTerms
   *   The form class used for rendering the merge terms form.
   */
  private function createSubjectUnderTest(): MergeTerms {
    return new MergeTerms($this->entityTypeManager, $this->privateTempStoreFactory);
  }

  /**
   * {@inheritdoc}
   */
  protected function numberOfTermsToSetUp(): int {
    return 0;
  }

}
