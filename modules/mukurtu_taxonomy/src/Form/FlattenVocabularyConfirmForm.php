<?php

declare(strict_types=1);

namespace Drupal\mukurtu_taxonomy\Form;

use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\EnforcedResponseException;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\mukurtu_taxonomy\Batch\NestedTermsBatch;
use Drupal\mukurtu_taxonomy\NestedTerms;
use Drupal\taxonomy\VocabularyInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Confirms making a vocabulary that has nested terms flat.
 *
 * The vocabulary form sends administrators here instead of flattening
 * straight away. Confirming saves the current nesting, then moves every
 * nested term to the top level in a batch.
 */
class FlattenVocabularyConfirmForm extends ConfirmFormBase {

  /**
   * The vocabulary to make flat.
   */
  protected VocabularyInterface $vocabulary;

  public function __construct(protected NestedTerms $nestedTerms) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('mukurtu_taxonomy.nested_terms'));
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mukurtu_taxonomy_flatten_vocabulary_confirm';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?VocabularyInterface $taxonomy_vocabulary = NULL) {
    $this->vocabulary = $taxonomy_vocabulary;

    // Already flat, or nothing to move (a second tab, or the back button).
    if ($this->nestedTerms->isFlat($this->vocabulary) || !$this->nestedTerms->countNestedTerms($this->vocabulary->id())) {
      throw new EnforcedResponseException(new TrustedRedirectResponse($this->getCancelUrl()->setAbsolute()->toString()));
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Make %vocabulary flat?', ['%vocabulary' => $this->vocabulary->label()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->formatPlural(
      $this->nestedTerms->countNestedTerms($this->vocabulary->id()),
      '1 term in %vocabulary is nested under another term. Making the vocabulary flat moves it to the top level. The current nesting is saved, and you can restore it if you allow nested terms again.',
      '@count terms in %vocabulary are nested under other terms. Making the vocabulary flat moves them to the top level. The current nesting is saved, and you can restore it if you allow nested terms again.',
      ['%vocabulary' => $this->vocabulary->label()]
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Make flat');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return $this->vocabulary->toUrl('edit-form');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $vid = $this->vocabulary->id();
    $this->nestedTerms->snapshot($vid);

    $batch = (new BatchBuilder())
      ->setTitle($this->t('Moving terms to the top level'))
      ->setProgressMessage('')
      ->addOperation([NestedTermsBatch::class, 'flatten'], [$vid, $this->nestedTerms->getNestedTermIds($vid)])
      ->setFinishCallback([NestedTermsBatch::class, 'flattenFinished']);
    batch_set($batch->toArray());

    $form_state->setRedirectUrl($this->vocabulary->toUrl('overview-form'));
  }

}
