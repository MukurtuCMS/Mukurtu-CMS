<?php

declare(strict_types=1);

namespace Drupal\mukurtu_collection\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Render\Element;
use Drupal\mukurtu_collection\CollectionHierarchyServiceInterface;
use Drupal\mukurtu_collection\CollectionOrder;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Sets the order of top-level collections on the Collections page.
 */
class CollectionOrderForm extends ConfigFormBase {

  /**
   * Constructs a CollectionOrderForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Config\TypedConfigManagerInterface $typedConfigManager
   *   The typed config manager.
   * @param \Drupal\mukurtu_collection\CollectionOrder $collectionOrder
   *   The collection order service.
   * @param \Drupal\mukurtu_collection\CollectionHierarchyServiceInterface $hierarchyService
   *   The collection hierarchy service.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entityRepository
   *   The entity repository.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    TypedConfigManagerInterface $typedConfigManager,
    protected CollectionOrder $collectionOrder,
    protected CollectionHierarchyServiceInterface $hierarchyService,
    protected EntityRepositoryInterface $entityRepository,
    protected LanguageManagerInterface $languageManager,
  ) {
    parent::__construct($config_factory, $typedConfigManager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('mukurtu_collection.collection_order'),
      $container->get('mukurtu_collection.hierarchy_service'),
      $container->get('entity.repository'),
      $container->get('language_manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'mukurtu_collection_order_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return [CollectionOrder::CONFIG_NAME];
  }

  /**
   * Splits top-level collections by whether the current user can view them.
   *
   * @return array
   *   A two-item list: the viewable collections, keyed by node ID, and a map
   *   of the other collections' node IDs.
   */
  protected function getRootCollectionsByAccess(): array {
    $viewable = [];
    $hidden = [];
    foreach ($this->hierarchyService->getRootCollections() as $nid => $collection) {
      if ($collection->access('view')) {
        $viewable[(int) $nid] = $collection;
      }
      else {
        $hidden[(int) $nid] = TRUE;
      }
    }
    return [$viewable, $hidden];
  }

  /**
   * Gets viewable top-level collection labels in their current order.
   *
   * Collections the current user can't view are left out, so the form never
   * shows their titles. submitForm() keeps their saved positions.
   *
   * @param bool $alphabetical
   *   TRUE to ignore the saved order and sort every collection by label.
   *
   * @return string[]
   *   Labels in the current language, keyed by node ID. Collections in the
   *   saved order come first, in that order. The rest follow by label.
   */
  protected function getOrderedCollections(bool $alphabetical = FALSE): array {
    [$viewable] = $this->getRootCollectionsByAccess();
    $labels = [];
    foreach ($viewable as $nid => $collection) {
      $labels[$nid] = (string) $this->entityRepository->getTranslationFromContext($collection)->label();
    }

    $placed = [];
    foreach ($alphabetical ? [] : $this->collectionOrder->getOrder() as $nid) {
      if (isset($labels[$nid])) {
        $placed[$nid] = $labels[$nid];
      }
    }
    $unplaced = array_diff_key($labels, $placed);
    $this->sortByLocale($unplaced);

    return $placed + $unplaced;
  }

  /**
   * Merges the submitted order into the saved one.
   *
   * Collections the user couldn't see keep their saved positions. The
   * submitted collections fill the other positions in their new order, and
   * any left over go at the end. IDs that are no longer top-level
   * collections are dropped.
   *
   * @param int[] $submitted
   *   The node IDs of the rows on the form, in their new order.
   *
   * @return int[]
   *   The order to save.
   */
  protected function mergeOrder(array $submitted): array {
    [$viewable, $hidden] = $this->getRootCollectionsByAccess();
    $order = [];
    foreach ($this->collectionOrder->getOrder() as $nid) {
      if (isset($hidden[$nid])) {
        $order[] = $nid;
      }
      elseif (isset($viewable[$nid]) && $submitted) {
        $order[] = array_shift($submitted);
      }
    }
    return array_merge($order, $submitted);
  }

  /**
   * Sorts labels with locale-aware collation, keeping keys.
   *
   * Falls back to a case-insensitive natural sort without the intl extension.
   *
   * @param string[] $labels
   *   The labels to sort.
   */
  protected function sortByLocale(array &$labels): void {
    if (!class_exists('\Collator')) {
      uasort($labels, 'strnatcasecmp');
      return;
    }
    $collator = new \Collator($this->languageManager->getCurrentLanguage()->getId());
    uasort($labels, fn (string $a, string $b): int => (int) $collator->compare($a, $b));
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['sort_mode'] = [
      '#type' => 'radios',
      '#title' => $this->t('Order'),
      '#description' => $this->t('Choose how top-level collections are ordered on the Collections page. Collections that are not in your custom order appear after it, sorted alphabetically.'),
      '#options' => [
        CollectionOrder::MODE_ALPHABETICAL => $this->t('Alphabetical'),
        CollectionOrder::MODE_CUSTOM => $this->t('Custom order'),
      ],
      '#default_value' => $this->collectionOrder->getSortMode(),
      '#required' => TRUE,
    ];

    $table = [
      '#type' => 'table',
      '#header' => [
        $this->t('Collection'),
        $this->t('Weight'),
      ],
      '#empty' => $this->t('There are no collections.'),
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'collection-order-weight',
        ],
      ],
    ];

    $collections = $this->getOrderedCollections((bool) $form_state->get('reset_alphabetical'));
    $delta = max(10, count($collections));
    $weight = 0;
    foreach ($collections as $nid => $label) {
      $table[$nid]['#attributes']['class'][] = 'draggable';
      $table[$nid]['#weight'] = $weight;
      $table[$nid]['label'] = [
        '#plain_text' => $label,
      ];
      $table[$nid]['weight'] = [
        '#type' => 'weight',
        '#title' => $this->t('Weight for @title', ['@title' => $label]),
        '#title_display' => 'invisible',
        '#default_value' => $weight,
        '#delta' => $delta,
        '#attributes' => ['class' => ['collection-order-weight']],
      ];
      $weight++;
    }

    // #states can't hide a bare table, so it goes in a container. The
    // container also holds the "Show row weights" toggle tabledrag adds.
    $form['custom_order'] = [
      '#type' => 'container',
      '#states' => [
        'visible' => [
          ':input[name="sort_mode"]' => ['value' => CollectionOrder::MODE_CUSTOM],
        ],
      ],
      'collections' => $table,
      'reset' => [
        '#type' => 'submit',
        '#value' => $this->t('Reset to alphabetical'),
        '#submit' => ['::resetToAlphabetical'],
        '#limit_validation_errors' => [],
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * Rebuilds the table in alphabetical order without saving it.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function resetToAlphabetical(array &$form, FormStateInterface $form_state): void {
    // The posted weights would otherwise override the rebuilt rows' defaults.
    $input = $form_state->getUserInput();
    unset($input['collections']);
    $form_state->setUserInput($input);
    $form_state->set('reset_alphabetical', TRUE)->setRebuild();
    $this->messenger()->addStatus($this->t('The table is now in alphabetical order. Save the configuration to keep it.'));
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Sort by the submitted weight. The row's position breaks ties, so rows
    // with equal weights keep the order they were shown in.
    $values = $form_state->getValue('collections') ?: [];
    $rows = [];
    $table = $form['custom_order']['collections'];
    foreach (Element::children($table) as $nid) {
      if (isset($values[$nid]['weight'])) {
        $rows[] = [(int) $values[$nid]['weight'], (int) $table[$nid]['#weight'], (int) $nid];
      }
    }
    sort($rows);

    $this->collectionOrder->save(
      $form_state->getValue('sort_mode'),
      $this->mergeOrder(array_column($rows, 2)),
    );

    parent::submitForm($form, $form_state);
  }

}
