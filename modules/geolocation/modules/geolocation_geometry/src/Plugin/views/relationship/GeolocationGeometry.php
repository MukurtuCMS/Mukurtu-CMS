<?php

namespace Drupal\geolocation_geometry\Plugin\views\relationship;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\relationship\RelationshipPluginBase;
use Drupal\views\Plugin\ViewsHandlerManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Geometry joins.
 *
 * @ingroup views_relationship_handlers
 *
 * @ViewsRelationship("geolocation_geometry")
 */
class GeolocationGeometry extends RelationshipPluginBase {

  /**
   * Join Manager.
   *
   * @var \Drupal\Component\Plugin\PluginManagerInterface
   */
  public $joinManager;

  /**
   * Query.
   *
   * @var \Drupal\views\Plugin\views\query\Sql
   */
  public $query;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ViewsHandlerManager $join_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->joinManager = $join_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.views.join')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defineOptions() {
    $options = parent::defineOptions();

    $options['geometry_join_type'] = ['default' => 'geolocation_geometry_within'];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $form['geometry_join_type'] = [
      '#type' => 'select',
      '#options' => [
        'geolocation_geometry_within' => $this->t('This field ST_WITHIN that geometry'),
        'geolocation_geometry_contains' => $this->t('This field ST_CONTAINS that geometry'),
        'geolocation_geometry_intersects' => $this->t('This field ST_INTERSECT that geometry'),
      ],
      '#title' => $this->t('Relationship type'),
      '#description' => $this->t('Spatial join type on DB level'),
      '#default_value' => $this->options['geometry_join_type'],
    ];
  }

  /**
   * Called to implement a relationship in a query.
   */
  public function query() {
    $this->ensureMyTable();

    $first = [
      'left_table' => $this->definition['table'],
      'left_field' => $this->definition['field'],
      'table' => $this->definition['relationship table'],
      'field' => $this->definition['relationship field'],
      'adjusted' => TRUE,
    ];
    if (!empty($this->options['required'])) {
      $first['type'] = 'INNER';
    }

    $geometry_join_id = $this->options['geometry_join_type'];
    if ($this->definition['field_type'] == 'geolocation') {
      switch ($this->options['geometry_join_type']) {
        case 'geolocation_geometry_within':
          $geometry_join_id = 'geolocation_within';
          break;

        case 'geolocation_geometry_contains':
          $geometry_join_id = 'geolocation_contains';
          break;

        case 'geolocation_geometry_intersects':
          $geometry_join_id = 'geolocation_intersects';
          break;
      }
    }

    /** @var \Drupal\views\Plugin\views\join\JoinPluginBase $first_join */
    $first_join = $this->joinManager->createInstance($geometry_join_id, $first);

    $first_alias = $this->query->addTable($this->definition['relationship table'], $this->relationship, $first_join);

    $second = [
      'left_table' => $first_alias,
      'left_field' => 'entity_id',
      'table' => $this->definition['base'],
      'field' => $this->definition['base field'],
      'adjusted' => TRUE,
    ];

    if (!empty($this->options['required'])) {
      $second['type'] = 'INNER';
    }

    if (!empty($this->definition['join_id'])) {
      $id = $this->definition['join_id'];
    }
    else {
      $id = 'standard';
    }

    if (!empty($this->definition['join_extra'])) {
      $second['extra'] = $this->definition['join_extra'];
    }

    /** @var \Drupal\views\Plugin\views\join\JoinPluginBase $second_join */
    $second_join = $this->joinManager->createInstance($id, $second);
    $second_join->adjusted = TRUE;

    $alias = $this->definition['base'] . '__' . $this->definition['base field'];

    $this->alias = $this->query->addRelationship($alias, $second_join, $this->definition['base'], $this->relationship);
  }

}
