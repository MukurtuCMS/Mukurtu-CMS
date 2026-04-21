<?php

namespace Drupal\search_api\Plugin\views\row;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\ComplexDataInterface;
use Drupal\search_api\LoggerTrait;
use Drupal\search_api\Plugin\views\query\SearchApiQuery;
use Drupal\search_api\SearchApiException;
use Drupal\views\Attribute\ViewsRow;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\Plugin\views\row\RowPluginBase;
use Drupal\views\ViewExecutable;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a row plugin for displaying a result as a rendered item.
 *
 * @see search_api_views_plugins_row_alter()
 */
#[ViewsRow(
  id: 'search_api',
  title: new TranslatableMarkup('Rendered entity'),
  help: new TranslatableMarkup('Displays entity of the matching search API item'),
  display_types: ['normal'],
)]
class SearchApiRow extends RowPluginBase {

  use LoggerTrait;

  /**
   * The search index.
   *
   * @var \Drupal\search_api\IndexInterface
   */
  protected $index;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /** @var static $row */
    $row = parent::create($container, $configuration, $plugin_id, $plugin_definition);

    $row->setEntityTypeManager($container->get('entity_type.manager'));
    $row->setLogger($container->get('logger.channel.search_api'));

    return $row;
  }

  /**
   * Retrieves the entity type manager.
   *
   * @return \Drupal\Core\Entity\EntityTypeManagerInterface
   *   The entity type manager.
   */
  public function getEntityTypeManager() {
    return $this->entityTypeManager ?: \Drupal::entityTypeManager();
  }

  /**
   * Sets the entity type manager.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The new entity type manager.
   *
   * @return $this
   */
  public function setEntityTypeManager(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, ?array &$options = NULL) {
    parent::init($view, $display, $options);
    $base_table = $view->storage->get('base_table');
    $this->index = SearchApiQuery::getIndexFromTable($base_table, $this->getEntityTypeManager());
    if (!$this->index) {
      $view_label = $view->storage->label();
      throw new \InvalidArgumentException("View '$view_label' is not based on Search API but tries to use its row plugin.");
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['view_modes'] = ['default' => []];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $bundle_options = [
      ':default' => $this->t('Use the default setting.'),
    ];
    foreach ($this->index->getDatasources() as $datasource_id => $datasource) {
      $datasource_label = $datasource->label();
      $bundles = $datasource->getBundles();
      $datasource_view_modes = $datasource->getViewModes();
      if (!$datasource_view_modes) {
        $form['view_modes'][$datasource_id] = [
          '#type' => 'item',
          '#title' => $this->t('View mode for datasource %name', ['%name' => $datasource_label]),
          '#description' => $this->t("This datasource doesn't have any view modes available. It is therefore not possible to display results of this datasource using this row plugin."),
        ];
        continue;
      }
      $datasource_config = $this->options['view_modes'][$datasource_id] ?? [];
      $form['view_modes'][$datasource_id][':default'] = [
        '#type' => 'select',
        '#title' => $this->t('Default view mode for %datasource', ['%datasource' => $datasource->label()]),
        '#options' => $datasource_view_modes,
        '#default_value' => $datasource_config[':default'] ?? 'default',
        '#description' => $this->t('You can override this setting per bundle by choosing different view modes below.'),
      ];
      foreach ($bundles as $bundle_id => $bundle_label) {
        $title = $this->t('View mode for datasource %datasource, bundle %bundle', [
          '%datasource' => $datasource_label,
          '%bundle' => $bundle_label,
        ]);
        $view_modes = $datasource->getViewModes($bundle_id);
        if (!$view_modes) {
          $form['view_modes'][$datasource_id][$bundle_id] = [
            '#type' => 'item',
            '#title' => $title,
            '#description' => $this->t("This bundle doesn't have any view modes available. It is therefore not possible to display results of this bundle using this row plugin."),
          ];
          continue;
        }
        $form['view_modes'][$datasource_id][$bundle_id] = [
          '#type' => 'select',
          '#options' => $bundle_options + $view_modes,
          '#title' => $title,
          '#default_value' => $datasource_config[$bundle_id] ?? ':default',
        ];
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function validateOptionsForm(&$form, FormStateInterface $form_state): void {
    parent::validateOptionsForm($form, $form_state);

    // The "item" form element used for datasources without view modes results
    // in an empty string form value, which would mess up our configuration.
    // Remove those values manually.
    $key = $form['view_modes']['#parents'];
    $view_modes = $form_state->getValue($key, []);
    $view_modes = array_filter($view_modes, fn ($element) => $element !== '');
    $form_state->setValue($key, $view_modes);
  }

  /**
   * {@inheritdoc}
   */
  public function preRender($result) {
    // Load all result objects at once, before rendering.
    // Set $entity->view property to be accessible in preprocess functions.
    $items_to_load = [];
    foreach ($result as $i => $row) {
      if (empty($row->_object)) {
        $items_to_load[$i] = $row->search_api_id;
      }
      else {
        $entity = $row->_object->getValue();
        if ($entity instanceof EntityInterface && !isset($entity->view)) {
          $entity->view = $this->view;
        }
      }
    }

    $items = $this->index->loadItemsMultiple($items_to_load);
    foreach ($items_to_load as $i => $item_id) {
      if (isset($items[$item_id])) {
        $result[$i]->_object = $items[$item_id];
        $result[$i]->_item->setOriginalObject($items[$item_id]);

        $entity = $items[$item_id]->getValue();
        if ($entity instanceof EntityInterface && !isset($entity->view)) {
          $entity->view = $this->view;
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function render($row) {
    $datasource_id = $row->search_api_datasource;

    if (!($row->_object instanceof ComplexDataInterface)) {
      $context = [
        '%item_id' => $row->search_api_id,
        '%view' => $this->view->storage->label() ?? $this->view->storage->id(),
      ];
      $this->getLogger()->warning('Failed to load item %item_id in view %view.', $context);
      return '';
    }

    $datasource = $this->index->getDatasourceIfAvailable($datasource_id);
    if (!$datasource) {
      $context = [
        '%datasource' => $datasource_id,
        '%view' => $this->view->storage->label() ?? $this->view->storage->id(),
      ];
      $this->getLogger()->warning('Item of unknown datasource %datasource returned in view %view.', $context);
      return '';
    }

    $bundle = $datasource->getItemBundle($row->_object);
    // Do not attempt to view the item if the datasource has no view modes.
    if (!$datasource->getViewModes($bundle)) {
      return '';
    }
    // If there is no view mode set for the given bundle, or the option is
    // explicitly set to ":default", use the global default setting.
    $datasource_config = $this->options['view_modes'][$datasource_id] ?? [];
    if (($datasource_config[$bundle] ?? ':default') === ':default') {
      $datasource_config[$bundle] = $datasource_config[':default'] ?? 'default';
    }
    // Always use the default view mode if it was not set explicitly in the
    // options.
    $view_mode = $datasource_config[$bundle] ?? 'default';

    try {
      $build = $datasource->viewItem($row->_object, $view_mode);
      // Add the excerpt to the render array to allow adding it to view modes.
      if (isset($row->search_api_excerpt)) {
        $build['#search_api_excerpt'] = $row->search_api_excerpt;
      }

      return $build;
    }
    catch (SearchApiException $e) {
      $this->logException($e);
      return '';
    }
  }

  /**
   * {@inheritdoc}
   */
  public function query() {}

}
