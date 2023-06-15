<?php

namespace Drupal\mukurtu_export\Plugin;

use Drupal\Core\Plugin\PluginBase;
use Drupal\mukurtu_export\MukurtuExporterInterface;
use Drupal\Core\Plugin\ContextAwarePluginTrait;
use Drupal\Core\Plugin\ContextAwarePluginAssignmentTrait;

abstract class ExporterBase extends PluginBase implements MukurtuExporterInterface
{
  use ContextAwarePluginTrait;
  use ContextAwarePluginAssignmentTrait;

  /**
   * An associative array containing the configured settings of this exporter.
   *
   * @var array
   */
  public $settings = [];

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration)
  {
    if (isset($configuration['settings'])) {
      $this->settings = (array) $configuration['settings'];
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration()
  {
    return [
      'id' => $this->getPluginId(),
      'settings' => $this->settings,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration()
  {
    return [
      'settings' => $this->pluginDefinition['settings'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function exportSetup($entities, $options, &$context)
  {

  }

  /**
   * {@inheritdoc}
   */
  public static function exportCompleted(&$context)
  {

  }

  /**
   * {@inheritdoc}
   */
  public static function batchSetup(&$context)
  {

  }

  /**
   * {@inheritdoc}
   */
  public static function batchCompleted(&$context)
  {

  }
}
