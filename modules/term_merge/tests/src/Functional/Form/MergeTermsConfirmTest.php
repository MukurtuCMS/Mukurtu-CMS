<?php

namespace Drupal\Tests\term_merge\Functional\Form;

use Drupal\Core\Form\FormState;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\term_merge\Form\MergeTermsConfirm;
use Drupal\Tests\term_merge\Functional\MergeTermsTestBase;
use Drupal\Tests\term_merge\Functional\TestDoubles\TermMergerDummy;
use Drupal\Tests\term_merge\Functional\TestDoubles\TermMergerSpy;

/**
 * Tests the Merge terms confirm form.
 *
 * @group term_merge
 */
class MergeTermsConfirmTest extends MergeTermsTestBase {

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    \Drupal::getContainer()->set('term_merge.term_merger', new TermMergerDummy());
  }

  /**
   * Returns possible merge options that can be selected in the interface.
   *
   * @return array
   *   An array of options. Each option has contains the following values:
   *   - terms: an array of source taxonomy term ids.
   *   - target: a string representing the target taxonomy term.
   */
  public static function selectedTermsProvider(): array {

    $test_data['no terms new target'] = [
      'terms' => [],
      'target' => 'New term',
    ];

    $test_data['no terms existing target'] = [
      'terms' => [],
      'target' => '',
    ];

    $test_data['one term new target'] = [
      'terms' => [1],
      'target' => 'New term',
    ];

    $test_data['one term existing target'] = [
      'terms' => [1],
      'target' => '',
    ];

    $test_data['two terms new target'] = [
      'terms' => [1, 2],
      'target' => 'New term',
    ];

    $test_data['two terms existing target'] = [
      'terms' => [1, 2],
      'target' => '',
    ];

    $test_data['three terms new target'] = [
      'terms' => [1, 2, 3],
      'target' => 'New term',
    ];

    $test_data['three terms existing target'] = [
      'terms' => [1, 2, 3],
      'target' => '',
    ];

    $test_data['four terms new target'] = [
      'terms' => [1, 2, 3, 4],
      'target' => 'New term',
    ];

    $test_data['four terms existing target'] = [
      'terms' => [1, 2, 3, 4],
      'target' => '',
    ];

    return $test_data;
  }

  /**
   * Tests the title callback for the confirm form.
   *
   * @test
   * @dataProvider selectedTermsProvider
   */
  public function titleCallback(array $selected_terms): void {
    $sut = $this->createSubjectUnderTest();
    $this->privateTempStoreFactory->get('term_merge')->set('terms', $selected_terms);

    $expected = new TranslatableMarkup('Are you sure you wish to merge %termCount terms?', ['%termCount' => count($selected_terms)]);
    self::assertEquals($expected, $sut->titleCallback());
  }

  /**
   * Tests the form build for the confirm form.
   *
   * @test
   * @dataProvider selectedTermsProvider
   */
  public function buildForm(array $selected_terms, $target) {
    $target = $this->prepareTarget($target);
    $sut = $this->createSubjectUnderTest();
    $this->privateTempStoreFactory->get('term_merge')->set('terms', $selected_terms);
    $this->privateTempStoreFactory->get('term_merge')->set('target', $target);

    $actual = $sut->buildForm([], new FormState(), $this->vocabulary);

    if (empty($selected_terms)) {
      self::assertEquals([], $actual);
      $this->assertSingleErrorMessage(new TranslatableMarkup("You must submit at least one term."));
    }
    else {
      $this->assertConfirmationForm($selected_terms, $actual, $target);
    }
  }

  /**
   * Tests the confirm form build structure for a given set of taxonomy terms.
   *
   * @param int[] $selected_terms
   *   An array of selected taxonomy term IDs.
   * @param array $actual
   *   The form structure.
   * @param \Drupal\taxonomy\Entity\Term|string $target
   *   A newly created term if the target was an empty string, the original
   *   string otherwise.
   */
  protected function assertConfirmationForm(array $selected_terms, array $actual, $target): void {
    $items = [];
    foreach ($selected_terms as $term_index) {
      $items[] = $this->terms[$term_index]->label();
    }

    $arguments = [
      '%termCount' => count($selected_terms),
      '%termName' => is_string($target) ? $target : $target->label(),
    ];
    if (is_string($target)) {
      $message = new TranslatableMarkup("You are about to merge %termCount terms into new term %termName. This action can't be undone. Are you sure you wish to continue with merging the terms below?", $arguments);
    }
    else {
      $message = new TranslatableMarkup("You are about to merge %termCount terms into existing term %termName. This action can't be undone. Are you sure you wish to continue with merging the terms below?", $arguments);
    }

    $expected = [
      'message' => [
        '#markup' => $message,
      ],
      'terms' => [
        '#title' => new TranslatableMarkup("Terms to be merged"),
        '#theme' => 'item_list',
        '#items' => $items,
      ],
      'actions' => [
        '#type' => 'actions',
        'submit' => [
          '#button_type' => 'primary',
          '#type' => 'submit',
          '#value' => new TranslatableMarkup('Confirm merge'),
        ],
      ],
    ];

    self::assertEquals($expected, $actual);
  }

  /**
   * Tests a status message is available.
   *
   * @param string $expected_message
   *   The status message text.
   */
  protected function assertSingleErrorMessage(string $expected_message): void {
    $messages = $this->messenger()->all();
    $error_messages = $this->messenger()->messagesByType('error');

    self::assertCount(1, $messages);
    self::assertEquals($expected_message, array_pop($error_messages));
  }

  /**
   * Tests an exception is thrown for invalid target types.
   */
  public function incorrectTargetThrowsException(): void {
    $this->expectException('\LogicException', 'Invalid target type. Should be string or implement TermInterface');
    $sut = $this->createSubjectUnderTest();

    $this->privateTempStoreFactory->get('term_merge')->set('terms', [1, 2]);
    $this->privateTempStoreFactory->get('term_merge')->set('target', new \stdClass());

    $form_state = new FormState();
    $build = $sut->buildForm([], $form_state, $this->vocabulary);
    $sut->submitForm($build, $form_state);
  }

  /**
   * Returns possible merge options that can be selected in the interface.
   *
   * @return array
   *   An array of options. Each option has contains the following values:
   *   - methodName: the method name associated with the selected merge option.
   *   - target: a string representing the target taxonomy term.
   */
  public static function termMergerMethodProvider(): array {
    $methods['new term'] = [
      'methodName' => 'mergeIntoNewTerm',
      'target' => 'New term',
    ];

    $methods['existing term'] = [
      'methodName' => 'mergeIntoTerm',
      'target' => '',
    ];

    return $methods;
  }

  /**
   * Tests the correct method is invoked on the term merger after confirmation.
   *
   * @test
   * @dataProvider termMergerMethodProvider
   */
  public function submitFormInvokesCorrectTermMergerMethod($method_name, $target): void {
    $term_merger_spy = new TermMergerSpy();
    \Drupal::getContainer()->set('term_merge.term_merger', $term_merger_spy);
    $sut = $this->createSubjectUnderTest();
    $terms = [reset($this->terms)->id(), end($this->terms)->id()];
    $this->privateTempStoreFactory->get('term_merge')->set('terms', $terms);
    $this->privateTempStoreFactory->get('term_merge')->set('target', $this->prepareTarget($target));

    $form_state = new FormState();
    $build = $sut->buildForm([], $form_state, $this->vocabulary);

    $sut->submitForm($build, $form_state);

    self::assertEquals([$method_name], $term_merger_spy->calledFunctions());
  }

  /**
   * Tests the redirect after merging terms.
   *
   * @test
   * @dataProvider termMergerMethodProvider
   */
  public function submitRedirectsToMergeRoute($method_name, $target): void {
    $sut = $this->createSubjectUnderTest();
    $terms = [reset($this->terms)->id(), end($this->terms)->id()];
    $this->privateTempStoreFactory->get('term_merge')->set('terms', $terms);
    $this->privateTempStoreFactory->get('term_merge')->set('target', $this->prepareTarget($target));

    $form_state = new FormState();
    $build = $sut->buildForm([], $form_state, $this->vocabulary);

    $sut->submitForm($build, $form_state);

    $route_name = 'entity.taxonomy_vocabulary.merge_form';
    self::assertRedirect($form_state, $route_name, $this->vocabulary->id());
  }

  /**
   * Tests a status message is displayed after merging terms.
   *
   * @test
   */
  public function submitSetsSuccessMessage(): void {
    $sut = $this->createSubjectUnderTest();
    $terms = [reset($this->terms)->id(), end($this->terms)->id()];
    $this->privateTempStoreFactory->get('term_merge')->set('terms', $terms);
    $this->privateTempStoreFactory->get('term_merge')->set('target', 'Target');

    $form_state = new FormState();
    $build = $sut->buildForm([], $form_state, $this->vocabulary);

    $sut->submitForm($build, $form_state);

    $arguments = [
      '%count' => 2,
      '%target' => 'Target',
    ];
    $expected = [
      new TranslatableMarkup('Successfully merged %count terms into %target', $arguments),
    ];

    self::assertEquals($expected, $this->messenger()->messagesByType('status'));
  }

  /**
   * Creates the form class used for rendering the confirm form.
   *
   * @return \Drupal\term_merge\Form\MergeTermsConfirm
   *   The form class used for rendering the confirm form.
   */
  protected function createSubjectUnderTest(): MergeTermsConfirm {
    return new MergeTermsConfirm($this->entityTypeManager, $this->privateTempStoreFactory, \Drupal::service('term_merge.term_merger'));
  }

  /**
   * {@inheritdoc}
   */
  protected function numberOfTermsToSetUp(): int {
    return 4;
  }

}
