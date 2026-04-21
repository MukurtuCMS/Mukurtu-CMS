<?php

namespace Drupal\search_api\ParseMode;

use Drupal\search_api\Plugin\HideablePluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a base class from which other parse mode classes may extend.
 *
 * Plugins extending this class need to provide the plugin definition using the
 * \Drupal\search_api\Attribute\SearchApiParseMode attribute. These definitions
 * may be altered using the "search_api.gathering_parse_modes" event.
 *
 * A complete plugin definition should be written as in this example:
 *
 * @code
 * #[SearchApiParseMode(
 *   id: 'my_parse_mode',
 *   label: new TranslatableMarkup('My parse mode'),
 *   description: new TranslatableMarkup('Some info about my parse mode'),
 * )]
 * @endcode
 *
 * @see \Drupal\search_api\Attribute\SearchApiParseMode
 * @see \Drupal\search_api\ParseMode\ParseModePluginManager
 * @see \Drupal\search_api\ParseMode\ParseModeInterface
 * @see \Drupal\search_api\Event\SearchApiEvents::GATHERING_PARSE_MODES
 * @see plugin_api
 */
abstract class ParseModePluginBase extends HideablePluginBase implements ParseModeInterface {

  /**
   * The default conjunction to use when parsing keywords.
   *
   * @var string
   */
  protected $conjunction = 'AND';

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function label() {
    $plugin_definition = $this->getPluginDefinition();
    return $plugin_definition['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    $plugin_definition = $this->getPluginDefinition();
    return $plugin_definition['description'];
  }

  /**
   * {@inheritdoc}
   */
  public function getConjunction() {
    return $this->conjunction;
  }

  /**
   * {@inheritdoc}
   */
  public function setConjunction($conjunction) {
    $this->conjunction = $conjunction;
    return $this;
  }

}
