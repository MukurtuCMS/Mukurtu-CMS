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

  protected function flattenOptions(array $values) {
    $options = [];
    foreach ($values as $id => $value) {
      if ($value['display'] == 'notice') {
        $options[$value['title']][$value['p_id'] . ':' . $value['type'] . ':' . $value['display']] = $value['name'] ?? $this->t('Unknown Notice');
      }
      else if ($value['display'] == 'label') {
        $options[$value['title']][$value['p_id'] . ':' . $value['id'] . ':' . $value['display']] = $value['name'] ?? $this->t('Unknown Label');
      }
    }
    return $options;
  }

  /**
   * {@inheritDoc}
   */
  public function getPossibleValues(AccountInterface $account = NULL) {
    $values = [];
    $labels = $this->localContextsProjectManager->getAllLabels();
    $notices = $this->localContextsProjectManager->getAllNotices();

    foreach ($labels as $label) {
      $values[] = $label['p_id'] . ':' . $label['id'] . ':' . $label['display'];
    }
    foreach ($notices as $notice) {
      $values[] = $notice['p_id'] . ':' . $notice['type'] . ':' . $notice['display'];
    }
    return $values;
  }

  /**
   * {@inheritDoc}
   */
  public function getPossibleOptions(AccountInterface $account = NULL) {
    $labelOptions = $this->flattenOptions($this->localContextsProjectManager->getAllLabels());
    $noticeOptions = $this->flattenOptions($this->localContextsProjectManager->getAllNotices());
    return array_merge($labelOptions, $noticeOptions);
  }

  /**
   * {@inheritDoc}
   */
  public function getSettableValues(AccountInterface $account = NULL) {
    $labels = $account ? $this->localContextsProjectManager->getUserLabels($account) : $this->localContextsProjectManager->getSiteLabels();
    $notices = $account ? $this->localContextsProjectManager->getUserNotices($account) : $this->localContextsProjectManager->getSiteNotices();
    $values = [];

    foreach ($labels as $label) {
      $values[] = $label['p_id'] . ':' . $label['id'] . ':' . $label['display'];
    }
    foreach ($notices as $notice) {
      $values[] = $notice['p_id'] . ':' . $notice['type'] . ':' . $notice['display'];
    }
    return $values;
  }

  /**
   * {@inheritDoc}
   */
  public function getSettableOptions(AccountInterface $account = NULL) {
    $labels = $account ? $this->localContextsProjectManager->getUserLabels($account) : $this->localContextsProjectManager->getSiteLabels();
    $notices = $account ? $this->localContextsProjectManager->getUserNotices($account) : $this->localContextsProjectManager->getSiteNotices();
    $labelOptions = $this->flattenOptions($labels);
    $noticeOptions = $this->flattenOptions($notices);
    $options = array_merge($labelOptions, $noticeOptions);
    return $options;
  }
}

