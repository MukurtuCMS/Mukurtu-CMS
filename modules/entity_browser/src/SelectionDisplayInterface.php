<?php

namespace Drupal\entity_browser;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\DependentPluginInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormInterface;

/**
 * Defines the interface for entity browser selection displays.
 *
 * This plugin type is responsible for displaying the currently selected
 * entities in an entity browser and delivering them upstream. The selections
 * are displayed in a form which delivers the selected entities on submit.
 */
interface SelectionDisplayInterface extends PluginInspectionInterface, ConfigurableInterface, PluginFormInterface, DependentPluginInterface {

  /**
   * Returns the selection display label.
   *
   * @return string
   *   The selection display label.
   */
  public function label();

  /**
   * Returns selection display form.
   *
   * @param array $original_form
   *   Entire form built up to this point. Form elements for selection display
   *   should generally not be added directly to it but returned from function
   *   as a separated unit.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state object.
   *
   * @return array
   *   Form structure.
   */
  public function getForm(array &$original_form, FormStateInterface $form_state);

  /**
   * Validates form.
   *
   * @param array $form
   *   Form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state object.
   */
  public function validate(array &$form, FormStateInterface $form_state);

  /**
   * Submits form.
   *
   * @param array $form
   *   Form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state object.
   */
  public function submit(array &$form, FormStateInterface $form_state);

  /**
   * Check does selection display support preselection.
   *
   * If preselection is not allowed by entity browser selection display, then
   * exception will be thrown.
   *
   * @deprecated Use ::supportsPreselection instead.
   *
   * @throws \Drupal\Core\Config\ConfigException
   */
  public function checkPreselectionSupport();

  /**
   * Check if the plugin supports preselection.
   *
   * @returns bool
   *   Returns TRUE if preselection is supported.
   */
  public function supportsPreselection();

  /**
   * Returns true if selection display supports selection over javascript.
   *
   * @return bool
   *   True if javascript add/remove events are supported.
   */
  public function supportsJsCommands();

}
