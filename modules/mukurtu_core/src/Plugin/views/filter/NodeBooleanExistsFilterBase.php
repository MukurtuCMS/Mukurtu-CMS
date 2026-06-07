<?php

namespace Drupal\mukurtu_core\Plugin\views\filter;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\filter\FilterPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base for Yes/No existence filters on node content.
 *
 * Subclasses implement getSubquery() to return a SELECT of node IDs (nid or
 * entity_id) that match the "Yes" condition. The filter then shows a
 * three-option select (Any / Yes / No) in the exposed form.
 */
abstract class NodeBooleanExistsFilterBase extends FilterPluginBase {

  const VALUE_ANY = 'All';
  const VALUE_YES = '1';
  const VALUE_NO  = '0';

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected readonly Connection $database,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('database'),
    );
  }

  /**
   * Returns a SELECT query whose result set is the node IDs that qualify.
   */
  abstract protected function getSubquery(): SelectInterface;

  /**
   * {@inheritdoc}
   */
  protected function valueForm(&$form, FormStateInterface $form_state) {
    $form['value'] = [
      '#type' => 'select',
      '#title' => $this->t('Value'),
      '#options' => [
        self::VALUE_YES => $this->t('Yes'),
        self::VALUE_NO  => $this->t('No'),
      ],
      '#empty_option' => $this->t('- Any -'),
      '#empty_value' => self::VALUE_ANY,
      '#default_value' => $this->value ?? self::VALUE_ANY,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function adminSummary() {
    if (!isset($this->value) || $this->value === self::VALUE_ANY || $this->value === '') {
      return $this->t('unrestricted');
    }
    return $this->value === self::VALUE_YES ? $this->t('Yes') : $this->t('No');
  }

  /**
   * {@inheritdoc}
   */
  public function query(): void {
    if (!isset($this->value) || $this->value === self::VALUE_ANY || $this->value === '') {
      return;
    }
    $this->ensureMyTable();
    $subquery = $this->getSubquery();
    $op = ($this->value === self::VALUE_YES) ? 'IN' : 'NOT IN';
    $this->query->addWhere($this->options['group'], "$this->tableAlias.nid", $subquery, $op);
  }

}
