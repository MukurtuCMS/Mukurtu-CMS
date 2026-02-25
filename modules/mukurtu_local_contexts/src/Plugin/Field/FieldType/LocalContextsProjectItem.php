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
 * Defines the 'local_contexts_project' field type.
 *
 * @FieldType(
 *   id = "local_contexts_project",
 *   label = @Translation("Local Contexts Project"),
 *   default_widget = "local_contexts_project",
 *   default_formatter = "local_contexts_project"
 * )
 */
class LocalContextsProjectItem extends StringItem implements OptionsProviderInterface {
  protected $localContextsProjectManager;

  public function __construct(ComplexDataDefinitionInterface $definition, $name = NULL, ?TypedDataInterface $parent = NULL) {
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

  protected function flattenProjectOptions(array $projects) {
    $options = [];
    foreach ($projects as $id => $project) {
      $options[$id] = $project['title'] ?? $this->t('Unknown Project');
    }
    return $options;
  }

  /**
   * {@inheritDoc}
   */
  public function getPossibleValues(?AccountInterface $account = NULL) {
    $options = $this->flattenProjectOptions($this->localContextsProjectManager->getAllProjects());
    return array_keys($options);
  }

  /**
   * {@inheritDoc}
   */
  public function getPossibleOptions(?AccountInterface $account = NULL){
    return $this->flattenProjectOptions($this->localContextsProjectManager->getAllProjects());
  }

  /**
   * {@inheritDoc}
   */
  public function getSettableValues(?AccountInterface $account = NULL) {
    $options = $account ? $this->localContextsProjectManager->getUserProjects($account) : $this->localContextsProjectManager->getSiteSupportedProjects();
    return array_keys($this->flattenProjectOptions($options));
  }

  /**
   * {@inheritDoc}
   */
  public function getSettableOptions(?AccountInterface $account = NULL) {
    $options = $account ? $this->localContextsProjectManager->getUserProjects($account) : $this->localContextsProjectManager->getSiteSupportedProjects();
    return $this->flattenProjectOptions($options);
  }

}
