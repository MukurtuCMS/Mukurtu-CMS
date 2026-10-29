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
   * Gets top-level collection labels in their current display order.
   *
   * @return string[]
   *   Labels in the current language, keyed by node ID. Collections in the
   *   saved order come first, in that order. The rest follow by label.
   */
  protected function getOrderedCollections(): array {
    $labels = [];
    foreach ($this->hierarchyService->getRootCollections() as $nid => $collection) {
      $labels[(int) $nid] = (string) $this->entityRepository->getTranslationFromContext($collection)->label();
    }

    $placed = [];
    foreach ($this->collectionOrder->getOrder() as $nid) {
      if (isset($labels[$nid])) {
        $placed[$nid] = $labels[$nid];
      }
    }
    $unplaced = array_diff_key($labels, $placed);
    $this->sortByLocale($unplaced);

    return $placed + $unplaced;
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
    $form['helper'] = [
      '#type' => 'item',
      '#markup' => $this->t('Choose how top-level collections are ordered on the Collections page. Collections that are not in your custom order appear after it, sorted alphabetically.'),
    ];

    $form['sort_mode'] = [
      '#type' => 'radios',
      '#title' => $this->t('Order'),
      '#options' => [
        CollectionOrder::MODE_ALPHABETICAL => $this->t('Alphabetical'),
        CollectionOrder::MODE_CUSTOM => $this->t('Custom order (drag to reorder below)'),
      ],
      '#default_value' => $this->collectionOrder->getSortMode(),
      '#required' => TRUE,
    ];

    $form['collections'] = [
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
      '#states' => [
        'visible' => [
          ':input[name="sort_mode"]' => ['value' => CollectionOrder::MODE_CUSTOM],
        ],
      ],
    ];

    $collections = $this->getOrderedCollections();
    $delta = max(10, count($collections));
    $weight = 0;
    foreach ($collections as $nid => $label) {
      $form['collections'][$nid]['#attributes']['class'][] = 'draggable';
      $form['collections'][$nid]['#weight'] = $weight;
      $form['collections'][$nid]['label'] = [
        '#plain_text' => $label,
      ];
      $form['collections'][$nid]['weight'] = [
        '#type' => 'weight',
        '#title' => $this->t('Weight for @title', ['@title' => $label]),
        '#title_display' => 'invisible',
        '#default_value' => $weight,
        '#delta' => $delta,
        '#attributes' => ['class' => ['collection-order-weight']],
      ];
      $weight++;
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Sort by the submitted weight. The row's position breaks ties, so rows
    // with equal weights keep the order they were shown in.
    $values = $form_state->getValue('collections') ?: [];
    $rows = [];
    foreach (Element::children($form['collections']) as $nid) {
      if (isset($values[$nid]['weight'])) {
        $rows[] = [(int) $values[$nid]['weight'], (int) $form['collections'][$nid]['#weight'], (int) $nid];
      }
    }
    sort($rows);

    $this->collectionOrder->save(
      $form_state->getValue('sort_mode'),
      array_column($rows, 2),
    );

    parent::submitForm($form, $form_state);
  }

}
