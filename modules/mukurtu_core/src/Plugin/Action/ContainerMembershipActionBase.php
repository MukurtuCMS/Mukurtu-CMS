<?php

namespace Drupal\mukurtu_core\Plugin\Action;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Utility\Error;
use Drupal\node\NodeInterface;
use Drupal\views\ViewExecutable;
use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base for bulk actions that add items to, or remove them from, a container.
 *
 * A container is a node that lists other nodes in a multi-value reference
 * field, such as a collection or a word list. The editor picks one container
 * they can update. Items that are already in it (when adding) or not in it
 * (when removing) are skipped, and the container is saved once per batch, as
 * a new revision, rather than once per item.
 */
abstract class ContainerMembershipActionBase extends ViewsBulkOperationsActionBase implements ContainerFactoryPluginInterface, PluginFormInterface {

  use StringTranslationTrait;

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly EntityRepositoryInterface $entityRepository,
    protected readonly AccountProxyInterface $currentUser,
    protected readonly TimeInterface $time,
    protected readonly LoggerInterface $logger,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('entity.repository'),
      $container->get('current_user'),
      $container->get('datetime.time'),
      $container->get('logger.factory')->get('mukurtu_core'),
    );
  }

  /**
   * The node bundle of the container, such as 'collection'.
   */
  abstract protected function containerBundle(): string;

  /**
   * The container field that references its items.
   */
  abstract protected function itemsField(): string;

  /**
   * Whether this action removes items rather than adding them.
   */
  protected function removes(): bool {
    return FALSE;
  }

  /**
   * Whether the node can be added to this kind of container at all.
   */
  abstract protected function isEligibleItem(NodeInterface $node): bool;

  /**
   * Why an ineligible item was skipped.
   */
  abstract protected function ineligibleReason(): TranslatableMarkup;

  /**
   * The label of the container picker.
   */
  abstract protected function selectLabel(): TranslatableMarkup;

  /**
   * Shown instead of the picker when the user can't update any container.
   */
  abstract protected function noContainersMessage(): TranslatableMarkup;

  /**
   * Result for an item that was added or removed, linking to the container.
   */
  abstract protected function doneMessage(string $label, string $url): TranslatableMarkup;

  /**
   * Result for an item that was already in, or not in, the container.
   *
   * Links to the container, like the done message.
   */
  abstract protected function skippedMessage(string $label, string $url): TranslatableMarkup;

  /**
   * Result for an item that couldn't be added or removed.
   */
  abstract protected function failedMessage(string $label): TranslatableMarkup;

  /**
   * The container's revision log message for a batch.
   */
  protected function revisionLogMessage(int $count): string {
    return $this->removes()
      ? (string) $this->formatPlural($count, 'Removed 1 item in a bulk operation.', 'Removed @count items in a bulk operation.')
      : (string) $this->formatPlural($count, 'Added 1 item in a bulk operation.', 'Added @count items in a bulk operation.');
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return ['container' => NULL];
  }

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    return $entity ? $this->executeMultiple([$entity])[0] : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function executeMultiple(array $objects): array {
    $id = $this->configuration['container'] ?? NULL;
    $container = $id ? $this->entityTypeManager->getStorage('node')->load($id) : NULL;

    if (!$container || $container->bundle() !== $this->containerBundle() || !$container->access('update', $this->currentUser)) {
      $label = $container ? $this->label($container) : (string) $id;
      return array_fill(0, count($objects), $this->result($this->failedMessage($label), 'error'));
    }

    $label = $this->label($container);
    $url = $this->entityRepository->getTranslationFromContext($container)->toUrl()->toString();
    $present = array_flip(array_column($container->get($this->itemsField())->getValue(), 'target_id'));

    // /admin/content lists one row per translation, so the same node can
    // arrive more than once in a batch. Track membership by ID as it changes.
    $results = [];
    $changed = [];
    foreach ($objects as $delta => $entity) {
      $id = $entity->id();
      $skip = $this->removes() ? !isset($present[$id]) : isset($present[$id]);
      if ($skip) {
        $results[$delta] = $this->result($this->skippedMessage($label, $url), 'warning');
        continue;
      }
      if ($this->removes()) {
        unset($present[$id]);
        $container->remove($entity);
      }
      else {
        $present[$id] = TRUE;
        $container->add($entity);
      }
      $changed[$delta] = $entity;
      $results[$delta] = $this->result($this->doneMessage($label, $url));
    }

    if ($changed) {
      $container->setNewRevision(TRUE);
      $container->setRevisionUserId($this->currentUser->id());
      $container->setRevisionCreationTime($this->time->getRequestTime());
      $container->setRevisionLogMessage($this->revisionLogMessage(count($changed)));

      // Validate only the items field. Whole-entity validation also rechecks
      // unrelated references, such as a blocked owner, that the editor can't
      // fix and the bulk action didn't change.
      $saved = FALSE;
      $violations = $container->get($this->itemsField())->validate();
      if ($violations->count() > 0) {
        $this->logger->warning('Bulk change to @container failed validation: @property: @message', [
          '@container' => $container->id(),
          '@property' => $violations[0]->getPropertyPath(),
          '@message' => $violations[0]->getMessage(),
        ]);
      }
      else {
        try {
          $container->save();
          $saved = TRUE;
        }
        catch (\Exception $e) {
          Error::logException($this->logger, $e);
        }
      }
      if (!$saved) {
        foreach (array_keys($changed) as $delta) {
          $results[$delta] = $this->result($this->failedMessage($label), 'error');
        }
      }
    }

    ksort($results);
    return $results;
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    if (!$object instanceof NodeInterface) {
      $result = AccessResult::forbidden();
    }
    elseif (!$this->isEligibleItem($object)) {
      $result = AccessResult::forbidden((string) $this->ineligibleReason());
    }
    else {
      $result = $object->access('view', $account, TRUE);
    }

    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public static function customAccess(AccountInterface $account, ViewExecutable $view): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $options = [];
    foreach ($this->getContainers() as $container) {
      $options[$container->id()] = $this->label($container);
    }

    if (!$options) {
      $form['container'] = [
        '#type' => 'item',
        '#markup' => $this->noContainersMessage(),
      ];
      // Nothing to apply; leave only Cancel.
      $form['actions']['submit']['#access'] = FALSE;
      return $form;
    }

    $form['container'] = [
      '#type' => 'select',
      '#title' => $this->selectLabel(),
      '#options' => $options,
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['container'] = $form_state->getValue('container');
  }

  /**
   * Returns the containers the current user can update, newest first.
   *
   * @return \Drupal\node\NodeInterface[]
   *   The containers, keyed by ID.
   */
  protected function getContainers(): array {
    $storage = $this->entityTypeManager->getStorage('node');
    $ids = $storage->getQuery()
      ->condition('type', $this->containerBundle())
      ->accessCheck(TRUE)
      ->sort('changed', 'DESC')
      ->execute();

    return array_filter(
      $ids ? $storage->loadMultiple($ids) : [],
      fn(NodeInterface $container) => $container->access('update', $this->currentUser),
    );
  }

  /**
   * Returns the entity's label in the current content language.
   */
  protected function label(EntityInterface $entity): string {
    return (string) $this->entityRepository->getTranslationFromContext($entity)->label();
  }

  /**
   * Builds a VBO result row.
   */
  protected function result(TranslatableMarkup $message, string $type = 'status'): array {
    return ['message' => (string) $message, 'type' => $type];
  }

}
