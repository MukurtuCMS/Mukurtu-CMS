<?php

namespace Drupal\term_merge\Form;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\synonyms\SynonymsService\ProviderService;
use Drupal\taxonomy\TermInterface;
use Drupal\taxonomy\VocabularyInterface;
use Drupal\term_merge\TermMergerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Term merge confirm form.
 */
class MergeTermsConfirm extends FormBase {

  /**
   * The term storage handler.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected EntityStorageInterface $termStorage;

  /**
   * The current vocabulary.
   *
   * @var \Drupal\taxonomy\VocabularyInterface
   */
  protected VocabularyInterface $vocabulary;

  /**
   * Constructs a MergeTermsConfirm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity manager service.
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $tempStoreFactory
   *   The private temporary storage factory.
   * @param \Drupal\term_merge\TermMergerInterface $termMerger
   *   The term merger service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   * @param \Drupal\synonyms\SynonymsService\ProviderService|null $synonymProvider
   *   The synonym provider service, or NULL if Synonyms module is not present.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected PrivateTempStoreFactory $tempStoreFactory,
    protected TermMergerInterface $termMerger,
    protected ?ProviderService  $synonymProvider,
  ) {
    $this->termStorage = $entityTypeManager->getStorage('taxonomy_term');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $synonym_provider = $container->has('synonyms.provider_service') ?  $container->get('synonyms.provider_service') : NULL;
    return new static(
      $container->get('entity_type.manager'),
      $container->get('tempstore.private'),
      $container->get('term_merge.term_merger'),
      $synonym_provider,
    );
  }

  /**
   * Returns a unique string identifying the form.
   *
   * @return string
   *   The unique string identifying the form.
   */
  public function getFormId() {
    return 'taxonomy_merge_terms_confirm';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?VocabularyInterface $taxonomy_vocabulary = NULL) {
    $this->vocabulary = $taxonomy_vocabulary;
    $selected_term_ids = $this->getSelectedTermIds();

    if (empty($selected_term_ids)) {
      $this->messenger()->addError($this->t("You must submit at least one term."), 'error');
      return $form;
    }

    $target = $this->tempStoreFactory->get('term_merge')->get('target');

    if (!is_string($target) && !$target instanceof TermInterface) {
      throw new \LogicException("Invalid target type. Should be string or implement TermInterface");
    }

    $arguments = [
      '%termCount' => count($selected_term_ids),
      '%termName' => is_string($target) ? $target : $target->label(),
    ];

    if (is_string($target)) {
      $form['message']['#markup'] = $this->t("You are about to merge %termCount terms into new term %termName. This action can't be undone. Are you sure you wish to continue with merging the terms below?", $arguments);
    }
    else {
      $form['message']['#markup'] = $this->t("You are about to merge %termCount terms into existing term %termName. This action can't be undone. Are you sure you wish to continue with merging the terms below?", $arguments);
    }

    $form['terms'] = [
      '#title' => $this->t("Terms to be merged"),
      '#theme' => 'item_list',
      '#items' => $this->getSelectedTermLabels(),
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#button_type' => 'primary',
      '#type' => 'submit',
      '#value' => $this->t('Confirm merge'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $selected_terms = $this->loadSelectedTerms();

    /** @var \Drupal\taxonomy\TermInterface|string $target */
    $target = $this->tempStoreFactory->get('term_merge')->get('target');
    // Stashing the destination tid in the form_state allows implementations of
    // hook_form_alter() to access it.
    if (is_string($target)) {
      $target_label = $target;
      $term_destination = $this->termMerger->mergeIntoNewTerm($selected_terms, $target);
      $form_state->set('destination_tid', $term_destination->id());
    }
    else {
      $target_label = $target->label();
      $this->termMerger->mergeIntoTerm($selected_terms, $target);
      $term_destination = $target;
      $form_state->set('destination_tid', $target->id());
    }
    if (isset($this->synonymProvider) && $this->tempStoreFactory->get('term_merge')->get('terms_to_synonym')) {
      // Merge synonyms to target term.
      $vocabulary_synonym_provider_plugin = NULL;
      // Get enabled field providers for this vocabulary.
      foreach ($this->synonymProvider->getSynonymConfigEntities('taxonomy_term', 'keywords') as $synonym_config) {
        $synonym_provider_plugin = $synonym_config->getProviderPluginInstance();
        if ($synonym_provider_plugin->getBaseId() == 'field') {
          $vocabulary_synonym_provider_plugin = $synonym_provider_plugin;
        }
      }

      if ($vocabulary_synonym_provider_plugin) {
        $synonyms_to_merge = [];
        foreach ($selected_terms as $selected_term) {
          $synonyms_to_merge[] = $selected_term->label();
          $synonyms_to_merge = array_merge($synonyms_to_merge, $vocabulary_synonym_provider_plugin->getSynonyms($selected_term));
        }

        $field_name = $vocabulary_synonym_provider_plugin->getPluginDefinition()['field'];
        if ($term_destination->hasField($field_name)) {
          $synonym_values = array_column($term_destination->get($field_name)->getValue(), 'value');
          $synonym_values = array_merge($synonym_values, $synonyms_to_merge);
          $synonym_values = array_unique($synonym_values);

          $term_destination->get($field_name)->setValue(array_map(fn($v) => ['value' => $v], $synonym_values));
          $term_destination->save();
        }
      }
    }

    $this->setSuccessfullyMergedMessage(count($selected_terms), $target_label);
    $this->redirectToTermMergeForm($form_state);
  }

  /**
   * Callback for the form title.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The title.
   */
  public function titleCallback() {
    $term_count = count($this->getSelectedTermIds());
    return $this->formatPlural($term_count, 'Are you sure you wish to merge one term?', 'Are you sure you wish to merge @count terms?');
  }

  /**
   * Gets a list of selected term ids from the temp store.
   *
   * @return int[]
   *   The selected term ids.
   */
  protected function getSelectedTermIds(): array {
    return $this->tempStoreFactory->get('term_merge')->get('terms') ?? [];
  }

  /**
   * Gets a list of selected term labels from the temp store.
   *
   * @return string[]
   *   The labels of the selected terms.
   */
  protected function getSelectedTermLabels(): array {
    $selected_terms = $this->loadSelectedTerms();

    $items = [];
    foreach ($selected_terms as $term) {
      $items[] = $term->label();
    }

    return $items;
  }

  /**
   * Loads the selected terms.
   *
   * @return \Drupal\taxonomy\TermInterface[]
   *   The selected terms.
   */
  protected function loadSelectedTerms(): array {
    return $this->termStorage->loadMultiple($this->getSelectedTermIds());
  }

  /**
   * Sets a redirect to the term merge form.
   *
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The form state object to set the redirect on.
   */
  protected function redirectToTermMergeForm(FormStateInterface $formState): void {
    $parameters['taxonomy_vocabulary'] = $this->vocabulary->id();
    $formState->setRedirect('entity.taxonomy_vocabulary.merge_form', $parameters);
  }

  /**
   * Sets the successfully merged terms message.
   *
   * @param int $count
   *   The number of terms merged.
   * @param string $target_name
   *   The name of the target term.
   */
  protected function setSuccessfullyMergedMessage(int $count, string $target_name): void {
    $arguments = [
      '%count' => $count,
      '%target' => $target_name,
    ];
    $this->messenger()->addStatus($this->t('Successfully merged %count terms into %target', $arguments));
  }

}
