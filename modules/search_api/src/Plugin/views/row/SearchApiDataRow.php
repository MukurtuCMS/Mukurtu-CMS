<?php

namespace Drupal\search_api\Plugin\views\row;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\ComplexDataInterface;
use Drupal\rest\Plugin\views\row\DataEntityRow;
use Drupal\search_api\LoggerTrait;
use Drupal\search_api\Plugin\views\query\SearchApiQuery;
use Drupal\search_api\Plugin\views\SearchApiHandlerTrait;
use Drupal\search_api\SearchApiException;
use Drupal\views\Attribute\ViewsRow;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\ViewExecutable;

/**
 * Displays entities as raw data.
 *
 * @ingroup views_row_plugins
 *
 * @see search_api_views_plugins_row_alter()
 */
#[ViewsRow(
  id: 'search_api_data',
  title: new TranslatableMarkup('Entity (Search API)'),
  help: new TranslatableMarkup('Retrieves entities as row data.'),
  display_types: ['data'],
)]
class SearchApiDataRow extends DataEntityRow {

  use LoggerTrait;
  use SearchApiHandlerTrait;

  /**
   * The search index associated with the current view.
   *
   * @var \Drupal\search_api\IndexInterface|null
   */
  protected $index;

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
  public function preRender($result) {
    parent::preRender($result);

    $this->getQuery()->getSearchApiResults()->preLoadResultItems();
    /** @var \Drupal\search_api\Plugin\views\ResultRow $row */
    foreach ($result as $row) {
      if (!$row->_object) {
        try {
          $row->_object = $row->_item->getOriginalObject(FALSE);
        }
        catch (SearchApiException) {
          // Can never happen for getOriginalObject() with $load = FALSE. Catch
          // for sake of static analysis.
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function render($row) {
    /** @var \Drupal\search_api\Plugin\views\ResultRow $row */
    if (!($row->_object instanceof ComplexDataInterface)) {
      $context = [
        '%item_id' => $row->search_api_id,
        '%view' => $this->view->storage->label() ?? $this->view->storage->id(),
      ];
      $this->getLogger()->warning('Failed to load item %item_id in view %view.', $context);
      return NULL;
    }

    return $row->_object;
  }

  /**
   * {@inheritdoc}
   */
  public function query() {}

}
