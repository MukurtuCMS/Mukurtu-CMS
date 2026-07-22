<?php

namespace Drupal\mukurtu_local_contexts\Plugin\Field\FieldType;

Use Drupal\Core\Field\Plugin\Field\FieldType\StringItem;
use Drupal\mukurtu_local_contexts\Event\LocalContextsProjectReferenceUpdatedEvent;
use Drupal\Core\TypedData\OptionsProviderInterface;
use Drupal\Core\TypedData\ComplexDataDefinitionInterface;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\mukurtu_local_contexts\LocalContextsSupportedProjectManager;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Field\FieldItemListInterface;

/**
 * Defines the 'local_contexts_label_and_notice' field type.
 *
 * @FieldType(
 *   id = "local_contexts_label_and_notice",
 *   label = @Translation("Local Contexts Label and Notice"),
 *   default_widget = "local_contexts_label_and_notice",
 *   default_formatter = "local_contexts_label_and_notice"
 * )
 */
class LocalContextsLabelItem extends StringItem implements OptionsProviderInterface {

  protected $localContextsProjectManager;

  public function __construct(ComplexDataDefinitionInterface $definition, $name = NULL, ?TypedDataInterface $parent = NULL) {
    parent::__construct($definition, $name, $parent);
    $this->localContextsProjectManager = \Drupal::service('mukurtu_local_contexts.supported_project_manager');
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

  protected function flattenOptions(array $values) {
    $options = [];
    foreach ($values as $id => $value) {
      if ($value['display'] == 'notice') {
        $options[$value['title']][$value['project_id'] . ':' . $value['type'] . ':' . $value['display']] = $value['name'] ?? $this->t('Unknown Notice');
      }
      else if ($value['display'] == 'label') {
        $options[$value['title']][$value['project_id'] . ':' . $value['id'] . ':' . $value['display']] = $value['name'] ?? $this->t('Unknown Label');
      }
    }
    return $options;
  }

  /**
   * {@inheritDoc}
   */
  public function getPossibleValues(?AccountInterface $account = NULL) {
    $values = [];
    $labels = $this->localContextsProjectManager->getAllLabels();
    $notices = $this->localContextsProjectManager->getAllNotices();

    foreach ($labels as $label) {
      $values[] = $label['project_id'] . ':' . $label['id'] . ':' . $label['display'];
    }
    foreach ($notices as $notice) {
      $values[] = $notice['project_id'] . ':' . $notice['type'] . ':' . $notice['display'];
    }
    return $values;
  }

  /**
   * {@inheritDoc}
   */
  public function getPossibleOptions(?AccountInterface $account = NULL) {
    $labelOptions = $this->flattenOptions($this->localContextsProjectManager->getAllLabels());
    $noticeOptions = $this->flattenOptions($this->localContextsProjectManager->getAllNotices());
    return array_merge($labelOptions, $noticeOptions);
  }

  /**
   * Get the project IDs already referenced by this specific field/entity.
   *
   * @return string[]
   */
  protected function getCurrentlyReferencedProjectIds(): array {
    $parent = $this->getParent();
    if (!$parent instanceof FieldItemListInterface) {
      return [];
    }
    $ids = [];
    foreach ($parent as $item) {
      if (!empty($item->value)) {
        // Value format is "{project_id}:{label_id|type}:{display}".
        [$project_id] = explode(':', $item->value, 2);
        $ids[] = $project_id;
      }
    }
    return array_unique($ids);
  }

  /**
   * Remove legacy-project labels/notices unless already referenced.
   *
   * @param array $values
   *   Label or notice rows keyed by ID, each with a 'project_id' key.
   *
   * @return array
   *   The filtered label/notice list.
   */
  protected function excludeUnreferencedLegacyValues(array $values): array {
    $referenced = $this->getCurrentlyReferencedProjectIds();
    foreach ($values as $key => $value) {
      if ($this->localContextsProjectManager->isLegacyProjectId((string) $value['project_id']) && !in_array($value['project_id'], $referenced, TRUE)) {
        unset($values[$key]);
      }
    }
    return $values;
  }

  /**
   * {@inheritDoc}
   */
  public function getSettableValues(?AccountInterface $account = NULL) {
    $labels = $account ? $this->localContextsProjectManager->getUserLabels($account) : $this->localContextsProjectManager->getSiteLabels();
    $notices = $account ? $this->localContextsProjectManager->getUserNotices($account) : $this->localContextsProjectManager->getSiteNotices();
    $labels = $this->excludeUnreferencedLegacyValues($labels);
    $notices = $this->excludeUnreferencedLegacyValues($notices);
    $values = [];

    foreach ($labels as $label) {
      $values[] = $label['project_id'] . ':' . $label['id'] . ':' . $label['display'];
    }
    foreach ($notices as $notice) {
      $values[] = $notice['project_id'] . ':' . $notice['type'] . ':' . $notice['display'];
    }
    return $values;
  }

  /**
   * {@inheritDoc}
   */
  public function getSettableOptions(?AccountInterface $account = NULL) {
    $labels = $account ? $this->localContextsProjectManager->getUserLabels($account) : $this->localContextsProjectManager->getSiteLabels();
    $notices = $account ? $this->localContextsProjectManager->getUserNotices($account) : $this->localContextsProjectManager->getSiteNotices();
    $labels = $this->excludeUnreferencedLegacyValues($labels);
    $notices = $this->excludeUnreferencedLegacyValues($notices);
    $labelOptions = $this->flattenOptions($labels);
    $noticeOptions = $this->flattenOptions($notices);
    $options = array_merge($labelOptions, $noticeOptions);
    return $options;
  }
}

