<?php

namespace Drupal\geofield\Plugin\views\filter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\geofield\Plugin\views\GeofieldBoundaryHandlerTrait;
use Drupal\views\Plugin\views\filter\FilterPluginBase;
use Drupal\views\Plugin\views\query\Sql;

/**
 * Filter handler for search keywords.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("geofield_rectangular_boundary_filter")
 */
class GeofieldRectBoundaryFilter extends FilterPluginBase implements ContainerFactoryPluginInterface {

  use GeofieldBoundaryHandlerTrait;

  /**
   * {@inheritdoc}
   */
  protected $alwaysMultiple = TRUE;

  /**
   * {@inheritdoc}
   */
  public function adminSummary() {
    return $this->t("Rectangular Boundary filter");
  }

  /**
   * {@inheritdoc}
   */
  protected function valueForm(&$form, FormStateInterface $form_state) {

    parent::valueForm($form, $form_state);

    $form['value']['#tree'] = TRUE;
    $form['value']['#prefix'] = '<div id="geofield-boundary-filter">';
    $form['value']['#suffix'] = '</div>';
    $form['value']['group'] = [
      '#type' => 'details',
      '#title' => $this->t('Rectangle Boundaries'),
      '#open' => TRUE,
    ];
    $value_element = &$form['value'];

    // Add the Latitude and Longitude elements.
    $value_element['group']['lat_north_east'] = [
      '#type' => 'textfield',
      '#title' => $this->t('NE Latitude'),
      '#default_value' => !empty($this->value['group']['lat_north_east']) ? $this->value['group']['lat_north_east'] : '',
      '#weight' => 10,
      '#size' => 12,
    ];
    $value_element['group']['lng_north_east'] = [
      '#type' => 'textfield',
      '#title' => $this->t('NE Longitude'),
      '#default_value' => !empty($this->value['group']['lng_north_east']) ? $this->value['group']['lng_north_east'] : '',
      '#weight' => 20,
      '#size' => 12,
    ];
    $value_element['group']['lat_south_west'] = [
      '#type' => 'textfield',
      '#title' => $this->t('SW Latitude'),
      '#default_value' => !empty($this->value['group']['lat_south_west']) ? $this->value['group']['lat_south_west'] : '',
      '#weight' => 30,
      '#size' => 12,
    ];
    $value_element['group']['lng_south_west'] = [
      '#type' => 'textfield',
      '#title' => $this->t('SW Longitude'),
      '#default_value' => !empty($this->value['group']['lng_south_west']) ? $this->value['group']['lng_south_west'] : '',
      '#weight' => 40,
      '#size' => 12,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    if (!($this->query instanceof Sql)) {
      return;
    }

    if (empty($this->value)) {
      return;
    }

    // Get the field alias.
    $lat_north_east = $this->value['group']['lat_north_east'];
    $lng_north_east = $this->value['group']['lng_north_east'];
    $lat_south_west = $this->value['group']['lat_south_west'];
    $lng_south_west = $this->value['group']['lng_south_west'];

    if (
      !is_numeric($lat_north_east)
      || !is_numeric($lng_north_east)
      || !is_numeric($lat_south_west)
      || !is_numeric($lng_south_west)
    ) {
      return;
    }

    $this->query->addWhereExpression(
      $this->options['group'],
      self::getBoundaryQueryFragment($this->ensureMyTable(), $this->realField, $lat_north_east, $lng_north_east, $lat_south_west, $lng_south_west)
    );
  }

}
