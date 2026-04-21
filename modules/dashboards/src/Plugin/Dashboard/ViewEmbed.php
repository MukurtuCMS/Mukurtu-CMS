<?php

namespace Drupal\dashboards\Plugin\Dashboard;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\dashboards\Plugin\DashboardBase;
use Drupal\views\Entity\View;
use Drupal\views\Views;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Show account info.
 *
 * @Dashboard(
 *   id = "view_embed",
 *   label = @Translation("Embed a view"),
 *   category = @Translation("Dashboards: Views")
 * )
 */
class ViewEmbed extends DashboardBase {

  /**
   * Entity query.
   *
   * @var \Drupal\Core\Entity\Query\QueryInterface
   */
  protected $entityQuery;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, CacheBackendInterface $cache_backend, QueryInterface $query) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $cache_backend);
    $this->entityQuery = $query;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('dashboards.cache'),
      $container->get('entity_type.manager')->getStorage('view')->getQuery()
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildRenderArray($configuration): array {
    $viewId = explode(':', $configuration['view']);
    $view = Views::getView($viewId[0]);
    if (!$view) {
      return [
        '#markup' => 'View do not exist anymore',
      ];
    }
    return views_embed_view($viewId[0], $viewId[1]) ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array $form, FormStateInterface $form_state, array $configuration): void {
    if (!$form_state->getValue('view')) {
      $form_state->setErrorByName('view', $this->t('Requires a view to be provided.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildSettingsForm(array $form, FormStateInterface $form_state, array $configuration): array {

    $views = $this->entityQuery
      ->condition('status', TRUE)
      ->condition("display.*.display_plugin", ['embed'], 'IN')
      ->accessCheck(FALSE)
      ->execute();

    $views = View::loadMultiple($views);

    $options = [];

    foreach ($views as $view) {
      foreach ($view->get('display') as $display) {
        if ($display['display_plugin'] != 'embed') {
          continue;
        }
        $options[$view->id() . ':' . $display['id']] = $view->label() . ': ' . $display['display_title'];
      }
    }

    if (empty($options)) {
      $form['view'] = [
        '#markup' => $this->t('Requires an embed view to be added for this plugin to be used.'),
      ];
      return $form;
    }

    $form['view'] = [
      '#type' => 'select',
      '#required' => TRUE,
      '#options' => $options,
      '#default_value' => (isset($configuration['view'])) ? $configuration['view'] : FALSE,
    ];
    return $form;
  }

}
