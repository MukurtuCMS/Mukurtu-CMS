<?php

namespace Drupal\mukurtu_export;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Plugin\ContextAwarePluginInterface;

interface MukurtuExporterInterface extends ConfigurableInterface, PluginInspectionInterface, ContextAwarePluginInterface
{
  /**
   * Generates an exporter's settings form.
   *
   * @param array $form
   *   A minimally prepopulated form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The state of the (entire) configuration form.
   *
   * @return array
   *   The $form array with additional form elements for the settings of this
   *   exporter. The submitted form values should match $this->settings.
   */
  public function settingsForm(array $form, FormStateInterface $form_state);
  public function getSettings(array &$form, FormStateInterface $form_state);


  /**
   * Run at the start of the export process.
   */
  public static function exportSetup($entities, $options, &$context);

  /**
   * Run at the end of the export process.
   */
  public static function exportCompleted(&$context);

  /**
   * Run at the start of a single batch operation during the export process.
   */
  public static function batchSetup(&$context);

  /**
   * Export a single batch.
   *
   * @param mixed $context
   *   The batch context.
   *
   * @return void
   */
  public static function batchExport(&$context);

  /**
   * Run at the end of a single batch operation during the export process.
   */
  public static function batchCompleted(&$context);
}
