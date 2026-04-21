<?php

namespace Drupal\term_merge\Form;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\Checkboxes;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\taxonomy\VocabularyInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Term merge form.
 */
class MergeTerms extends FormBase {

  /**
   * The term storage handler.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected EntityStorageInterface $termStorage;

  /**
   * The vocabulary.
   *
   * @var \Drupal\taxonomy\VocabularyInterface
   */
  protected VocabularyInterface $vocabulary;

  protected bool $hasSynonyms;

  /**
   * Constructs a MergeTerms object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity manager service.
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $tempStoreFactory
   *   The private temporary storage factory service.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected PrivateTempStoreFactory $tempStoreFactory,
    ModuleHandlerInterface $module_handler,
  ) {
    $this->termStorage = $entityTypeManager->getStorage('taxonomy_term');
    $this->hasSynonyms = $module_handler->moduleExists('synonyms');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('tempstore.private'),
      $container->get('module_handler'),
    );
  }

  /**
   * Returns a unique string identifying the form.
   *
   * @return string
   *   The unique string identifying the form.
   */
  public function getFormId() {
    return 'taxonomy_merge_terms';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?VocabularyInterface $taxonomy_vocabulary = NULL) {
    $this->vocabulary = $taxonomy_vocabulary;

    if ($this->hasSynonyms) {
      $form['terms_to_synonym'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Add source term as synonym'),
        '#description' => $this->t('Selected terms titles will get moved to the target term.'),
      ];
    }


    $form['terms'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Terms to merge'),
      '#options' => $this->getTermOptions($taxonomy_vocabulary),
      '#description' => $this->t('Select two or more terms to merge together'),
      '#required' => TRUE,
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#button_type' => 'primary',
      '#type' => 'submit',
      '#value' => $this->t('Merge'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    $selected_terms = $form_state->getValue('terms');

    if (empty($selected_terms)) {
      $form_state->setErrorByName('terms', 'At least one term must be selected.');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $selected_terms = Checkboxes::getCheckedCheckboxes($form_state->getValue('terms'));

    $term_store = $this->tempStoreFactory->get('term_merge');
    $term_store->set('terms', $selected_terms);

    if (!empty($form_state->getValue('terms_to_synonym')) || $form_state->hasValue('terms_to_synonym')) {
      $term_store->set('terms_to_synonym', $form_state->getValue('terms_to_synonym'));
    }

    $route_name = 'entity.taxonomy_vocabulary.merge_target';
    $route_parameters['taxonomy_vocabulary'] = $this->vocabulary->id();
    $form_state->setRedirect($route_name, $route_parameters);
  }

  /**
   * Callback for the form title.
   *
   * @param \Drupal\taxonomy\VocabularyInterface $taxonomy_vocabulary
   *   The vocabulary.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The title.
   */
  public function titleCallback(VocabularyInterface $taxonomy_vocabulary) {
    return $this->t('Merge %vocabulary terms', ['%vocabulary' => $taxonomy_vocabulary->label()]);
  }

  /**
   * Builds a list of all terms in this vocabulary.
   *
   * @param \Drupal\taxonomy\VocabularyInterface $vocabulary
   *   The vocabulary.
   *
   * @return string[]
   *   An array of taxonomy term labels keyed by their id.
   */
  protected function getTermOptions(VocabularyInterface $vocabulary) {
    $options = [];

    $terms = $this->termStorage->loadByProperties(['vid' => $vocabulary->id()]);
    foreach ($terms as $term) {
      $options[$term->id()] = $term->label();
    }

    asort($options);

    return $options;
  }
}
