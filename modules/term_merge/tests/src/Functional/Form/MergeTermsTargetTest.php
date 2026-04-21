<?php

namespace Drupal\Tests\term_merge\Functional\Form;

use Drupal\Core\Form\FormState;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\taxonomy\Entity\Term;
use Drupal\term_merge\Form\MergeTermsTarget;
use Drupal\Tests\term_merge\Functional\MergeTermsTestBase;

/**
 * Tests the merge terms target terms form.
 *
 * @group term_merge
 */
class MergeTermsTargetTest extends MergeTermsTestBase {

  /**
   * Tests the title for the target taxonomy term field.
   *
   * @test
   */
  public function hasTitle(): void {
    $sut = new MergeTermsTarget($this->entityTypeManager, $this->privateTempStoreFactory);

    $expected = new TranslatableMarkup('Please select a target term');

    self::assertEquals($expected, $sut->titleCallback());
  }

  /**
   * Tests the form structure of the merge terms target terms form.
   *
   * @test
   */
  public function buildsForm(): void {
    $sut = new MergeTermsTarget($this->entityTypeManager, $this->privateTempStoreFactory);

    $known_term_ids = array_keys($this->terms);
    $selected_term_ids = array_slice($known_term_ids, 0, 2);
    $this->privateTempStoreFactory->get('term_merge')->set('terms', $selected_term_ids);

    $options = [];
    foreach ($known_term_ids as $term_id) {
      if (in_array($term_id, $selected_term_ids)) {
        continue;
      }
      $options[$term_id] = $this->terms[$term_id]->label();
    }

    $expected = [
      'description' => [
        '#markup' => new TranslatableMarkup('Please enter a new term or select an existing term to merge into.'),
      ],
      'new' => [
        '#type' => 'textfield',
        '#title' => new TranslatableMarkup('New term'),
      ],
      'existing' => [
        '#type' => 'select',
        '#title' => new TranslatableMarkup('Existing term'),
        '#empty_option' => new TranslatableMarkup('Select an existing term'),
        '#options' => $options,
      ],
      'actions' => [
        '#type' => 'actions',
        'submit' => [
          '#button_type' => 'primary',
          '#type' => 'submit',
          '#value' => new TranslatableMarkup('Submit'),
        ],
      ],
    ];

    $actual = $sut->buildForm([], new FormState(), $this->vocabulary);
    self::assertEquals($expected, $actual);
  }

  /**
   * Returns options for the merge term target.
   *
   * @return string[]
   *   Options that allow the invoking test to know which targets to select.
   */
  public static function selectedTargetsProvider(): array {
    $test_data['no target selected'] = ['none'];
    $test_data['both targets selected'] = ['both'];

    return $test_data;
  }

  /**
   * Tests validation of the target term.
   *
   * @test
   * @dataProvider selectedTargetsProvider
   */
  public function newOrExistingTermMustBeSelected($selected_terms): void {
    $sut = new MergeTermsTarget($this->entityTypeManager, $this->privateTempStoreFactory);

    $known_term_ids = array_keys($this->terms);
    $selected_term_ids = array_slice($known_term_ids, 0, 2);
    $this->privateTempStoreFactory->get('term_merge')->set('terms', $selected_term_ids);

    $form_state = new FormState();
    $build = $sut->buildForm([], $form_state, $this->vocabulary);
    self::assertEmpty($form_state->getErrors());

    if ($selected_terms == 'both') {
      $form_state->setValue('new', 'New term');
      $form_state->setValue('existing', end($known_term_ids));
    }

    $sut->validateForm($build, $form_state);
    $expected_error = new TranslatableMarkup('You must either select an existing term or enter a new term.');
    self::assertEquals(['new' => $expected_error], $form_state->getErrors());
  }

  /**
   * Tests term merging to a new term.
   *
   * @test
   */
  public function newTermFormSubmission(): void {
    $sut = new MergeTermsTarget($this->entityTypeManager, $this->privateTempStoreFactory);

    $known_term_ids = array_keys($this->terms);
    $selected_term_ids = array_slice($known_term_ids, 0, 2);
    $term_merge_collection = $this->privateTempStoreFactory->get('term_merge');
    $term_merge_collection->set('terms', $selected_term_ids);

    $form_state = new FormState();
    $build = $sut->buildForm([], $form_state, $this->vocabulary);

    $target = 'newTarget';
    $form_state->setValue('new', $target);
    $sut->validateForm($build, $form_state);
    $sut->submitForm($build, $form_state);

    self::assertSame($target, $term_merge_collection->get('target'));
    $this->assertRedirect($form_state, 'entity.taxonomy_vocabulary.merge_confirm', $this->vocabulary->id());
  }

  /**
   * Tests term merging to an existing term.
   *
   * @test
   */
  public function existingTermSubmission() {
    $sut = new MergeTermsTarget($this->entityTypeManager, $this->privateTempStoreFactory);

    $known_term_ids = array_keys($this->terms);
    $selected_term_ids = array_slice($known_term_ids, 0, 2);
    $term_merge_collection = $this->privateTempStoreFactory->get('term_merge');
    $term_merge_collection->set('terms', $selected_term_ids);

    $form_state = new FormState();
    $build = $sut->buildForm([], $form_state, $this->vocabulary);

    $target = end($known_term_ids);
    $form_state->setValue('existing', $target);
    $sut->validateForm($build, $form_state);
    $sut->submitForm($build, $form_state);

    $target_term = Term::load($target);
    self::assertEquals($target_term, $term_merge_collection->get('target'));
    $this->assertRedirect($form_state, 'entity.taxonomy_vocabulary.merge_confirm', $this->vocabulary->id());
  }

  /**
   * {@inheritdoc}
   */
  protected function numberOfTermsToSetUp(): int {
    return 4;
  }

}
