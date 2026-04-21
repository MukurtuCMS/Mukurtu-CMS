<?php

namespace Drupal\Tests\term_merge\Functional;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxy;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\Url;
use Drupal\taxonomy\VocabularyInterface;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\taxonomy\Traits\TaxonomyTestTrait;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Base class for Term merge kernel tests.
 */
abstract class MergeTermsTestBase extends BrowserTestBase {

  use TaxonomyTestTrait {
    TaxonomyTestTrait::createVocabulary as traitCreateVocabulary;
  }
  use MessengerTrait;
  use ProphecyTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'filter',
    'term_merge',
    'term_reference_change',
    'taxonomy',
    'text',
    'user',
    'system',
  ];

  /**
   * Name of the theme to use in tests.
   *
   * @var string
   */
  protected $defaultTheme = 'stark';

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The tempstore factory.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected PrivateTempStoreFactory $privateTempStoreFactory;

  /**
   * A vocabulary for testing purposes.
   *
   * @var \Drupal\taxonomy\VocabularyInterface
   */
  protected VocabularyInterface $vocabulary;

  /**
   * An array of taxonomy terms.
   *
   * @var \Drupal\taxonomy\TermInterface[]
   */
  protected array $terms;

  /**
   * Create a new vocabulary with random properties.
   *
   * @return \Drupal\taxonomy\VocabularyInterface
   *   The created vocabulary.
   */
  public function createVocabulary(): VocabularyInterface {
    return $this->traitCreateVocabulary();
  }

  /**
   * Returns the number of terms that should be set up by the setUp function.
   *
   * @return int
   *   The number of terms that should be set up by the setUp function.
   */
  abstract protected function numberOfTermsToSetUp(): int;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->entityTypeManager = \Drupal::entityTypeManager();
    $this->privateTempStoreFactory = \Drupal::service('tempstore.private');
    $this->vocabulary = $this->createVocabulary();

    $dispatcher = $this->prophesize(EventDispatcherInterface::class);
    $account_proxy = new AccountProxy($dispatcher->reveal());

    $account = $this->createMock(AccountInterface::class);
    $account->method('id')->willReturn(24);
    /** @var \Drupal\Core\Session\AccountInterface $account */
    $account_proxy->setAccount($account);
    \Drupal::getContainer()->set('current_user', $account_proxy);

    $this->createTerms($this->numberOfTermsToSetUp());
  }

  /**
   * Prepares the target provided by mergeTermFunctionsProvider for use.
   *
   * Dataproviders run before the tests are set up and are therefore unable to
   * create proper taxonomy terms. Which means we'll have to do so in the test.
   *
   * @param string $target
   *   The label for the taxonomy term target.
   *
   * @return \Drupal\taxonomy\Entity\Term|string
   *   A newly created term if the target was an empty string, the original
   *   string otherwise.
   */
  protected function prepareTarget(string $target) {
    if (!empty($target)) {
      return $target;
    }

    return $this->createTerm($this->vocabulary);
  }

  /**
   * Asserts whether a given formState has its redirect set to a given route.
   *
   * @param \Drupal\Core\Form\FormState $form_state
   *   The current form state.
   * @param string $route_name
   *   The name of the route.
   * @param string $vocabulary_id
   *   The target vocabulary machine name.
   */
  protected function assertRedirect(FormState $form_state, string $route_name, string $vocabulary_id): void {
    $routeParameters['taxonomy_vocabulary'] = $vocabulary_id;
    $expected = new Url($route_name, $routeParameters);
    $this->assertEquals($expected, $form_state->getRedirect());
  }

  /**
   * Create a given amount of taxonomy terms.
   *
   * @param int $count
   *   The amount of taxonomy terms to create.
   */
  protected function createTerms(int $count): void {
    for ($i = 0; $i < $count; $i++) {
      $term = $this->createTerm($this->vocabulary);
      $this->terms[$term->id()] = $term;
    }
  }

}
