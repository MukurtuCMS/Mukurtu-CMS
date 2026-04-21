<?php

namespace Drupal\geolocation;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Defines an interface for geolocation DataProvider plugins.
 */
interface DataProviderInterface extends PluginInspectionInterface {

  /**
   * Determine valid views option.
   *
   * @param \Drupal\views\Plugin\views\field\FieldPluginBase $views_field
   *   Views field definition.
   *
   * @return bool
   *   Yes or no.
   */
  public function isViewsGeoOption(FieldPluginBase $views_field);

  /**
   * Determine valid field geo option.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition
   *   Field definition.
   *
   * @return bool
   *   Yes or no.
   */
  public function isFieldGeoOption(FieldDefinitionInterface $fieldDefinition);

  /**
   * Get positions from views row.
   *
   * @param \Drupal\views\ResultRow $row
   *   Row.
   * @param \Drupal\views\Plugin\views\field\FieldPluginBase|null $views_field
   *   Views field definition.
   *
   * @return array
   *   Retrieved locations.
   */
  public function getPositionsFromViewsRow(ResultRow $row, FieldPluginBase $views_field = NULL);

  /**
   * Get locations from views row.
   *
   * @param \Drupal\views\ResultRow $row
   *   Row.
   * @param \Drupal\views\Plugin\views\field\FieldPluginBase|null $views_field
   *   Views field definition.
   *
   * @return array
   *   Renderable locations.
   */
  public function getLocationsFromViewsRow(ResultRow $row, FieldPluginBase $views_field = NULL);

  /**
   * Get shapes from views row.
   *
   * @param \Drupal\views\ResultRow $row
   *   Row.
   * @param \Drupal\views\Plugin\views\field\FieldPluginBase|null $views_field
   *   Views field definition.
   *
   * @return array
   *   Renderable shapes.
   */
  public function getShapesFromViewsRow(ResultRow $row, FieldPluginBase $views_field = NULL);

  /**
   * Get positions from field item list.
   *
   * @param \Drupal\Core\Field\FieldItemInterface $fieldItem
   *   Views field definition.
   *
   * @return array
   *   Retrieved coordinates.
   */
  public function getPositionsFromItem(FieldItemInterface $fieldItem);

  /**
   * Get locations from field item list.
   *
   * @param \Drupal\Core\Field\FieldItemInterface $fieldItem
   *   Views field definition.
   *
   * @return array
   *   Renderable locations.
   */
  public function getLocationsFromItem(FieldItemInterface $fieldItem);

  /**
   * Get shapes from field item list.
   *
   * @param \Drupal\Core\Field\FieldItemInterface $fieldItem
   *   Views field definition.
   *
   * @return array
   *   Renderable shapes.
   */
  public function getShapesFromItem(FieldItemInterface $fieldItem);

  /**
   * Replace field item tokens.
   *
   * @param string $text
   *   Text.
   * @param \Drupal\Core\Field\FieldItemInterface $fieldItem
   *   Field item.
   *
   * @return array
   *   Retrieved locations.
   */
  public function replaceFieldItemTokens($text, FieldItemInterface $fieldItem);

  /**
   * Return field item tokens.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface|null $fieldDefinitionInterface
   *   Field definition interface.
   *
   * @return array
   *   Token help element.
   */
  public function getTokenHelp(FieldDefinitionInterface $fieldDefinitionInterface = NULL);

  /**
   * Provide data provider settings form array.
   *
   * @param array $settings
   *   The current data provider settings.
   * @param array $parents
   *   Form parents.
   *
   * @return array
   *   A form array to be integrated in whatever.
   */
  public function getSettingsForm(array $settings, array $parents = []);

  /**
   * Set views field.
   *
   * @param \Drupal\views\Plugin\views\field\FieldPluginBase $viewsField
   *   Views field.
   */
  public function setViewsField(FieldPluginBase $viewsField);

  /**
   * Set field definition.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition
   *   Field definition.
   */
  public function setFieldDefinition(FieldDefinitionInterface $fieldDefinition);

}
