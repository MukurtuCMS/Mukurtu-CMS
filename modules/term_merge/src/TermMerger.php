<?php

namespace Drupal\term_merge;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\taxonomy\TermInterface;
use Drupal\term_reference_change\ReferenceMigrator;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Implements TermMergerInterface to provide a term merger service.
 */
class TermMerger implements TermMergerInterface {

  /**
   * The taxonomy term storage.
   *
   * @var \Drupal\taxonomy\TermStorageInterface
   */
  protected EntityStorageInterface $termStorage;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The term reference migration service.
   *
   * @var \Drupal\term_reference_change\ReferenceMigrator
   */
  protected ReferenceMigrator $migrator;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected EventDispatcherInterface $dispatcher;

  /**
   * TermMerger constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   * @param \Drupal\term_reference_change\ReferenceMigrator $migrator
   *   The reference migration service.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher
   *   The event dispatcher.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    ReferenceMigrator $migrator,
    EventDispatcherInterface $dispatcher
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->termStorage = $this->entityTypeManager->getStorage('taxonomy_term');
    $this->migrator = $migrator;
    $this->dispatcher = $dispatcher;
  }

  /**
   * {@inheritdoc}
   */
  public function mergeIntoNewTerm(array $terms_to_merge, string $new_term_label): TermInterface {
    $this->validateTerms($terms_to_merge);

    $first_term = reset($terms_to_merge);
    $values = [
      'name' => $new_term_label,
      'vid' => $first_term->bundle(),
    ];

    /** @var \Drupal\taxonomy\TermInterface $new_term */
    $new_term = $this->termStorage->create($values);

    $this->mergeIntoTerm($terms_to_merge, $new_term);

    return $new_term;
  }

  /**
   * {@inheritdoc}
   */
  public function mergeIntoTerm(array $terms_to_merge, TermInterface $target_term): void {
    $this->validateTerms($terms_to_merge);

    // We have to save the term to make sure we've got an id to reference.
    if ($target_term->isNew()) {
      $target_term->save();
    }

    $firstTerm = reset($terms_to_merge);
    if ($firstTerm->bundle() !== $target_term->bundle()) {
      throw new \RuntimeException('The target term must be in the same vocabulary as the terms being merged');
    }

    $this->migrateReferences($terms_to_merge, $target_term);

    $event = new TermsMergedEvent($terms_to_merge, $target_term);
    $this->dispatcher->dispatch($event, TermMergeEventNames::TERMS_MERGED);

    $this->termStorage->delete($terms_to_merge);
  }

  /**
   * Asserts that all passed in terms are valid.
   *
   * @param \Drupal\taxonomy\TermInterface[] $terms_to_assert
   *   The array to assert.
   */
  protected function validateTerms(array $terms_to_assert): void {
    $this->assertTermsNotEmpty($terms_to_assert);
    $this->assertAllTermsHaveSameVocabulary($terms_to_assert);
  }

  /**
   * Asserts that all terms have the same vocabulary.
   *
   * @param \Drupal\taxonomy\TermInterface[] $terms_to_assert
   *   The array to assert.
   */
  protected function assertAllTermsHaveSameVocabulary(array $terms_to_assert): void {
    $vocabulary = '';

    foreach ($terms_to_assert as $term) {
      if (empty($vocabulary)) {
        $vocabulary = $term->bundle();
      }

      if ($vocabulary !== $term->bundle()) {
        throw new \RuntimeException('Only merges within the same vocabulary are supported');
      }
    }
  }

  /**
   * Asserts that the termsToAssert variable is not empty.
   *
   * @param \Drupal\taxonomy\TermInterface[] $terms_to_assert
   *   The array to assert.
   */
  protected function assertTermsNotEmpty(array $terms_to_assert) {
    if (empty($terms_to_assert)) {
      throw new \RuntimeException('You must provide at least 1 term');
    }
  }

  /**
   * Updates the term references on all entities referencing multiple terms.
   *
   * @param \Drupal\taxonomy\TermInterface[] $from_terms
   *   The terms to migrate away from.
   * @param \Drupal\taxonomy\TermInterface $to_term
   *   The term to migrate to.
   */
  protected function migrateReferences(array $from_terms, TermInterface $to_term): void {
    foreach ($from_terms as $from_term) {
      $this->migrator->migrateReference($from_term, $to_term);
    }
  }

}
