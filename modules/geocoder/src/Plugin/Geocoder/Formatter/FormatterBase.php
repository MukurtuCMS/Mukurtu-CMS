<?php

namespace Drupal\geocoder\Plugin\Geocoder\Formatter;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Geocoder\Formatter\StringFormatter;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a base class for geocoder formatter plugins.
 */
abstract class FormatterBase extends PluginBase implements FormatterInterface, ContainerFactoryPluginInterface {

  /**
   * The geocoder formatter handler.
   *
   * @var \Geocoder\Formatter\StringFormatter
   */
  protected $formatter;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->formatter = new StringFormatter();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * Clean the formatted address.
   *
   * @param string $formatted_address
   *   The address to clean.
   */
  protected function cleanFormattedAddress(&$formatted_address) {
    // Remove double white spaces.
    $formatted_address = preg_replace('/\s+/', ' ', $formatted_address);
    // Remove space before comma.
    $formatted_address = str_replace(' ,', ',', $formatted_address);
    // Trim empty spaces and commas.
    $formatted_address = trim(trim(trim($formatted_address), ','));
  }

}
