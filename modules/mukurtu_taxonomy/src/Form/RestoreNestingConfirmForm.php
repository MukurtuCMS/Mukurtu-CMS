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
 * Offers to restore the nesting a vocabulary had before it was made flat.
 *
 * The vocabulary form sends administrators here after they allow nested
 * terms again, if a saved structure exists. Declining keeps the saved
 * structure, so the offer comes back the next time.
 */
class RestoreNestingConfirmForm extends ConfirmFormBase {

  /**
   * The vocabulary to restore.
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
    return 'mukurtu_taxonomy_restore_nesting_confirm';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?VocabularyInterface $taxonomy_vocabulary = NULL) {
    $this->vocabulary = $taxonomy_vocabulary;

    // Still flat, or nothing saved (already restored in another tab).
    if ($this->nestedTerms->isFlat($this->vocabulary) || !$this->nestedTerms->hasSnapshot($this->vocabulary->id())) {
      throw new EnforcedResponseException(new TrustedRedirectResponse($this->getCancelUrl()->setAbsolute()->toString()));
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Restore the nesting in %vocabulary?', ['%vocabulary' => $this->vocabulary->label()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->formatPlural(
      count($this->nestedTerms->getSnapshot($this->vocabulary->id())),
      "%vocabulary had nested terms before it was made flat. Restoring puts 1 term back under its previous parent, in its previous order. Terms whose previous parent no longer exists stay at the top level. If you don't restore now, you can do it the next time you allow nested terms.",
      "%vocabulary had nested terms before it was made flat. Restoring puts @count terms back under their previous parents, in their previous order. Terms whose previous parent no longer exists stay at the top level. If you don't restore now, you can do it the next time you allow nested terms.",
      ['%vocabulary' => $this->vocabulary->label()]
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Restore nesting');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelText() {
    return $this->t('Not now');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return $this->vocabulary->toUrl('overview-form');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $vid = $this->vocabulary->id();

    $batch = (new BatchBuilder())
      ->setTitle($this->t('Restoring nested terms'))
      ->setProgressMessage('')
      ->addOperation([NestedTermsBatch::class, 'restore'], [$vid, $this->nestedTerms->getSnapshot($vid)])
      ->setFinishCallback([NestedTermsBatch::class, 'restoreFinished']);
    batch_set($batch->toArray());

    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
