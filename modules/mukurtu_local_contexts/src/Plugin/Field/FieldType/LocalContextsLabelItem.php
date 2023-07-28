<?php

namespace Drupal\mukurtu_local_contexts\Plugin\Field\FieldType;

Use Drupal\Core\Field\Plugin\Field\FieldType\StringItem;
use Drupal\mukurtu_local_contexts\Event\LocalContextsProjectReferenceUpdatedEvent;
use Drupal\Core\TypedData\OptionsProviderInterface;
use Drupal\Core\TypedData\ComplexDataDefinitionInterface;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\mukurtu_local_contexts\LocalContextsSupportedProjectManager;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines the 'local_contexts_label' field type.
 *
 * @FieldType(
 *   id = "local_contexts_label",
 *   label = @Translation("Local Contexts Label"),
 *   default_widget = "local_contexts_label",
 *   default_formatter = "local_contexts_label"
 * )
 */
class LocalContextsLabelItem extends StringItem implements OptionsProviderInterface {
  protected $localContextsProjectManager;

  public function __construct(ComplexDataDefinitionInterface $definition, $name = NULL, TypedDataInterface $parent = NULL) {
    parent::__construct($definition, $name, $parent);
    $this->localContextsProjectManager = new LocalContextsSupportedProjectManager();
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultStorageSettings()
  {
    return [
      'max_length' => 128,
      'is_ascii' => TRUE,
    ] + parent::defaultStorageSettings();
  }

  /**
   * {@inheritDoc}
   */
  public function postSave($update) {
    $event_dispatcher = \Drupal::service('event_dispatcher');
    if ($id = $this->getValue()['value'] ?? NULL) {
      $event = new LocalContextsProjectReferenceUpdatedEvent($id);
      $event_dispatcher->dispatch($event, LocalContextsProjectReferenceUpdatedEvent::EVENT_NAME);
    }
  }

  protected function flattenOptions(array $labels) {
    $options = [];
    foreach ($labels as $id => $label) {
      $options[$label['title']][$label['p_id'] . ':' . $label['id']] = $label['name'] ?? $this->t('Unknown Label');
    }

    return $options;
  }

  /**
   * {@inheritDoc}
   */
  public function getPossibleValues(AccountInterface $account = NULL) {
    $labels = $this->localContextsProjectManager->getAllLabels();
    $values = [];
    foreach ($labels as $label) {
      $values[] = $label['p_id'] . ':' . $label['id'];
    }
    return $values;
  }

  /**
   * {@inheritDoc}
   */
  public function getPossibleOptions(AccountInterface $account = NULL){
    return $this->flattenOptions($this->localContextsProjectManager->getAllLabels());
  }

  /**
   * {@inheritDoc}
   */
  public function getSettableValues(AccountInterface $account = NULL) {
    $labels = $account ? $this->localContextsProjectManager->getUserLabels($account) : $this->localContextsProjectManager->getSiteLabels();
    $values = [];
    foreach ($labels as $label) {
      $values[] = $label['p_id'] . ':' . $label['id'];
    }
    return $values;
  }

  /**
   * {@inheritDoc}
   */
  public function getSettableOptions(AccountInterface $account = NULL) {
    $labels = $account ? $this->localContextsProjectManager->getUserLabels($account) : $this->localContextsProjectManager->getSiteLabels();
    return $this->flattenOptions($labels);
  }

}
