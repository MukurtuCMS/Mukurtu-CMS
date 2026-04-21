<?php

namespace Drupal\search_api\Plugin\views\field;

use Drupal\views\Attribute\ViewsField;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TypedData\ComplexDataInterface;
use Drupal\search_api\Plugin\views\query\SearchApiQuery;
use Drupal\search_api\SearchApiException;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Drupal\views\ViewExecutable;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Handles rendering an entity in a certain view mode in Search API Views.
 *
 * @ingroup views_field_handlers
 */
#[ViewsField('search_api_rendered_item')]
class SearchApiRenderedItem extends FieldPluginBase {

  use SearchApiFieldTrait;

  /**
   * The search index.
   *
   * @var \Drupal\search_api\IndexInterface
   */
  protected $index;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /** @var static $plugin */
    $plugin = parent::create($container, $configuration, $plugin_id, $plugin_definition);

    $plugin->setEntityTypeManager($container->get('entity_type.manager'));
    $plugin->setLogger($container->get('logger.channel.search_api'));

    return $plugin;
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

    $no_view_mode_option = [
      '' => $this->t("Don't include the rendered item."),
    ];

    foreach ($this->index->getDatasources() as $datasource_id => $datasource) {
      $datasource_label = $datasource->label();
      $bundles = $datasource->getBundles();
      if (!$datasource->getViewModes()) {
        $form['view_modes'][$datasource_id] = [
          '#type' => 'item',
          '#title' => $this->t('View mode for datasource %name', ['%name' => $datasource_label]),
          '#description' => $this->t("This datasource doesn't have any view modes available. It is therefore not possible to display results of this datasource in this field."),
        ];
        continue;
      }

      foreach ($bundles as $bundle_id => $bundle_label) {
        $title = $this->t('View mode for datasource %datasource, bundle %bundle', ['%datasource' => $datasource_label, '%bundle' => $bundle_label]);
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
          '#options' => $no_view_mode_option + $view_modes,
          '#title' => $title,
          '#default_value' => key($view_modes),
        ];
        if (isset($this->options['view_modes'][$datasource_id][$bundle_id])) {
          $form['view_modes'][$datasource_id][$bundle_id]['#default_value'] = $this->options['view_modes'][$datasource_id][$bundle_id];
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function query($use_groupby = FALSE) {
    $this->addRetrievedProperty('_object');
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $row) {
    if (!(($row->_object ?? NULL) instanceof ComplexDataInterface)) {
      $context = [
        '%item_id' => $row->search_api_id,
        '%view' => $this->view->storage->label() ?? $this->view->storage->id(),
      ];
      $this->getLogger()->warning('Failed to load item %item_id in view %view.', $context);
      return '';
    }

    $datasource_id = $row->search_api_datasource;
    if (!$this->index->isValidDatasource($datasource_id)) {
      $context = [
        '%datasource' => $datasource_id ?? '(null)',
        '%view' => $this->view->storage->label() ?? $this->view->storage->id(),
      ];
      $this->getLogger()->warning('Item of unknown datasource %datasource returned in view %view.', $context);
      return '';
    }
    // Always use the default view mode if it was not set explicitly in the
    // options.
    $bundle = $this->index->getDatasourceIfAvailable($datasource_id)->getItemBundle($row->_object);
    $view_mode = $this->options['view_modes'][$datasource_id][$bundle] ?? 'default';
    if ($view_mode === '') {
      return '';
    }

    try {
      $build = $this->index->getDatasource($datasource_id)
        ->viewItem($row->_object, $view_mode);
      if ($build) {
        // Add the excerpt to the render array to allow adding it to view modes.
        $build['#search_api_excerpt'] = $row->_item->getExcerpt();
      }
      return $build;
    }
    catch (SearchApiException $e) {
      $this->logException($e);
      return '';
    }
  }

}
