<?php

namespace Drupal\Tests\term_merge\Functional;

use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\TermInterface;
use Drupal\term_merge\TermMerger;

/**
 * Tests term merging for taxonomy terms.
 *
 * @group term_merge
 */
class TermMergerTermCrudTest extends MergeTermsTestBase {

  /**
   * Returns possible merge options that can be selected in the interface.
   *
   * @return array
   *   An array of options. Each option has contains the following values:
   *   - methodName: the selected method for merging to the target term.
   *   - target: a string representing the target taxonomy term.
   */
  public static function mergeTermFunctionsProvider(): array {

    $functions['::mergeIntoNewTerm'] = [
      'methodName' => 'mergeIntoNewTerm',
      'target' => 'new term',
    ];

    $functions['::mergeIntoTerm'] = [
      'methodName' => 'mergeIntoTerm',
      'target' => '',
    ];

    return $functions;
  }

  /**
   * Tests only taxonomy terms in the same vocabulary can be merged.
   *
   * @param string $method_name
   *   The merge method being tested.
   * @param string $target
   *   The label for the taxonomy term target.
   *
   * @test
   * @dataProvider mergeTermFunctionsProvider
   */
  public function canOnlyMergeTermsInTheSameVocabulary(string $method_name, string $target): void {
    $this->expectException('\RuntimeException', 'Only merges within the same vocabulary are supported');
    $vocab2 = $this->createVocabulary();
    $term3 = $this->createTerm($vocab2);

    $terms = [reset($this->terms), $term3];

    $sut = $this->createSubjectUnderTest();

    $sut->{$method_name}($terms, $this->prepareTarget($target));
  }

  /**
   * Tests the form validation for the minimum required input.
   *
   * @param string $method_name
   *   The merge method being tested.
   * @param string $target
   *   The label for the taxonomy term target.
   *
   * @test
   * @dataProvider mergeTermFunctionsProvider
   */
  public function minimumTermsValidation(string $method_name, string $target) {
    $this->expectException('\RuntimeException', 'You must provide at least 1 term');
    $sut = $this->createSubjectUnderTest();

    $sut->{$method_name}([], $this->prepareTarget($target));
  }

  /**
   * Tests a newly created term is available when merging to a new term.
   *
   * @test
   */
  public function mergeIntoNewTermCreatesNewTerm(): void {
    $sut = $this->createSubjectUnderTest();

    $term_label = 'newTerm';
    $term = $sut->mergeIntoNewTerm($this->terms, $term_label);

    self::assertTrue($term instanceof TermInterface);
    self::assertSame($term_label, $term->label());
    // Id is only set if the term has been saved.
    self::assertNotNull($term->id());
  }

  /**
   * Tests the validation for the target term being in the same vocabulary.
   */
  public function existingTermMustBeInSameVocabularyAsMergedTerms(): void {
    $this->expectException('\RuntimeException', 'The target term must be in the same vocabulary as the terms being merged');
    $sut = $this->createSubjectUnderTest();

    $term = $this->createTerm($this->createVocabulary());

    $sut->mergeIntoTerm($this->terms, $term);
  }

  /**
   * Tests a taxonomy term that is passed to the migration is saved correctly.
   *
   * @test
   */
  public function mergeIntoTermSavesTermIfNewTermIsPassedIn(): void {
    $sut = $this->createSubjectUnderTest();
    $values = [
      'name' => 'Unsaved term',
      'vid' => $this->vocabulary->id(),
    ];
    /** @var \Drupal\taxonomy\TermInterface $term */
    $term = Term::create($values);
    self::assertEmpty($term->id());

    $sut->mergeIntoTerm($this->terms, $term);

    self::assertNotEmpty($term->id());
  }

  /**
   * Tests the merged terms are deleted after the migration.
   *
   * @param string $method_name
   *   The merge method being tested.
   * @param string $target
   *   The label for the taxonomy term target.
   *
   * @test
   * @dataProvider mergeTermFunctionsProvider
   */
  public function mergedTermsAreDeleted(string $method_name, string $target) {
    $sut = $this->createSubjectUnderTest();

    $sut->{$method_name}($this->terms, $this->prepareTarget($target));

    $term_ids = array_keys($this->terms);
    self::assertEquals([], Term::loadMultiple($term_ids));
  }

  /**
   * Creates the class used for merging terms.
   *
   * @return \Drupal\term_merge\TermMerger
   *   The class used for merging terms
   */
  private function createSubjectUnderTest(): TermMerger {
    $migrator = \Drupal::service('term_reference_change.migrator');
    $dispatcher = \Drupal::service('event_dispatcher');
    $event_type_manager = \Drupal::service('entity_type.manager');
    return new TermMerger($event_type_manager, $migrator, $dispatcher);
  }

  /**
   * {@inheritdoc}
   */
  protected function numberOfTermsToSetUp(): int {
    return 2;
  }

}
