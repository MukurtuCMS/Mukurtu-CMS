<?php

namespace Drupal\config_pages;

use Drupal\Component\Plugin\PluginInspectionInterface;

/**
 * Provides an interface defining a context config page.
 */
interface ConfigPagesContextInterface extends PluginInspectionInterface {

  /**
   * Return the label of the context.
   *
   * @return string
   *   Return label.
   */
  public function getLabel();

  /**
   * Get the value of the context.
   *
   * @return mixed
   *   Return value of the context.
   */
  public function getValue();

  /**
   * Get available links to switch on given context.
   *
   * @return array
   *   Return array of available links to switch on given context.
   */
  public function getLinks();

}
