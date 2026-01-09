<?php

declare(strict_types = 1);

namespace Drupal\mukurtu_dictionary\Plugin\views\sort;

use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Cache\UncacheableDependencyTrait;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Attribute\ViewsSort;
use Drupal\views\Plugin\views\sort\SortPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Sort handler for custom glossary entry order.
 *
 * This sort plugin works with Search API views to sort results based on
 * user-defined glossary entry weights configured in the
 * mukurtu_dictionary_glossary_order.settings config object.
 *
 * @ViewsSort("glossary_custom_order")
 */
#[ViewsSort("glossary_custom_order")]
class GlossaryCustomOrder extends SortPluginBase implements CacheableDependencyInterface {

  use UncacheableDependencyTrait;

  /**
   * The associated views query object.
   *
   * @var \Drupal\search_api\Plugin\views\query\SearchApiQuery
   */
  public $query;

  /**
   * Constructs a GlossaryCustomOrder object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected ConfigFactoryInterface $configFactory,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    // Load the glossary order configuration.
    $config = $this->configFactory->get('mukurtu_dictionary_glossary_order.settings');
    $sort_mode = $config->get('sort_mode') ?? 'default';

    // If default mode, fall back to standard alphabetical sort.
    if ($sort_mode === 'default') {
      $this->query->sort($this->realField, $this->options['order']);
      return;
    }

    // Build weights map from config.
    $weights_config = $config->get('weights') ?? [];
    $weights = [];
    foreach ($weights_config as $item) {
      if (isset($item['glossary_entry']) && isset($item['weight'])) {
        $weights[$item['glossary_entry']] = (int) $item['weight'];
      }
    }

    // If no weights are configured, fall back to standard sort.
    if (empty($weights)) {
      $this->query->sort($this->realField, $this->options['order']);
      return;
    }

    // Store the weights in the view for use in post_execute.
    $this->view->glossary_weights = $weights;
    $this->view->glossary_field = $this->realField;
    $this->view->glossary_order = $this->options['order'];

    // We'll handle sorting in post_execute by reordering results.
    // Don't add a sort to the query itself.
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $form['order']['#description'] = $this->t('The sort order will be applied to the custom glossary order weights. Note: Items without a configured weight will sort to the end alphabetically regardless of this setting.');
  }

}
