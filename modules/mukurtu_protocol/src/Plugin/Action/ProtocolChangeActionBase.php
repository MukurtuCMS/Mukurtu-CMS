<?php

namespace Drupal\mukurtu_protocol\Plugin\Action;

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
use Drupal\mukurtu_protocol\CulturalProtocolControlledInterface;
use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base for bulk actions that change an item's cultural protocols or sharing.
 *
 * Each action changes only what it names: protocols the editor can't see and
 * the current sharing setting are kept unless that is the change. Items are
 * saved one by one as new revisions. The actions always use VBO's
 * confirmation step, where lossNotices() warns who would lose access.
 *
 * @see \Drupal\mukurtu_protocol\Hook\BulkProtocolConfirmHooks
 */
abstract class ProtocolChangeActionBase extends ViewsBulkOperationsActionBase implements ContainerFactoryPluginInterface, PluginFormInterface {

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
      $container->get('logger.factory')->get('mukurtu_protocol'),
    );
  }

  /**
   * Applies the change to the item in memory.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|null
   *   NULL if the item was changed, otherwise why it was skipped.
   */
  abstract protected function change(CulturalProtocolControlledInterface $item): ?TranslatableMarkup;

  /**
   * Result for an item that was changed.
   */
  abstract protected function doneMessage(): TranslatableMarkup;

  /**
   * The item's revision log message.
   */
  abstract protected function revisionLogMessage(): string;

  /**
   * Notices about members who lose access, beyond "everyone".
   *
   * @param \Drupal\mukurtu_protocol\CulturalProtocolControlledInterface $before
   *   The item as it is.
   * @param \Drupal\mukurtu_protocol\CulturalProtocolControlledInterface $after
   *   The item with the change applied, unsaved.
   * @param string $label
   *   The item's label.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup[]
   *   The notices.
   */
  abstract protected function memberLossNotices(CulturalProtocolControlledInterface $before, CulturalProtocolControlledInterface $after, string $label): array;

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    $skip = $this->change($entity);
    if ($skip) {
      return $this->result($skip, 'warning');
    }

    $expected_protocols = $this->sortedProtocolIds($entity);
    $expected_sharing = $entity->getSharingSetting();

    $entity->setNewRevision(TRUE);
    $entity->setRevisionUserId($this->currentUser->id());
    $entity->setRevisionCreationTime($this->time->getRequestTime());
    $entity->setRevisionLogMessage($this->revisionLogMessage());

    try {
      $entity->save();
    }
    catch (\Exception $e) {
      Error::logException($this->logger, $e);
      return $this->result($this->t("Couldn't change this item"), 'error');
    }

    // CulturalProtocolItem::preSave() quietly restores protocols the editor
    // may not remove and drops ones they may not add, so check the outcome.
    if ($this->sortedProtocolIds($entity) !== $expected_protocols || $entity->getSharingSetting() !== $expected_sharing) {
      return $this->result($this->t("Couldn't change this item"), 'error');
    }

    return $this->result($this->doneMessage());
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $account ??= $this->currentUser;
    // VBO 4.4 has no action-level access check, so the "Use bulk editing"
    // permission is checked here, for each item.
    if (!$account->hasPermission('use mukurtu bulk edit')) {
      $result = AccessResult::forbidden()->cachePerPermissions();
    }
    elseif (!$object instanceof CulturalProtocolControlledInterface) {
      $result = AccessResult::forbidden((string) $this->t("This content doesn't use cultural protocols."));
    }
    else {
      $result = $object->access('update', $account, TRUE);
    }

    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
  }

  /**
   * Returns warnings about who would lose access to the item.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup[]
   *   The notices, empty when nobody loses access or the change would be
   *   skipped.
   */
  public function lossNotices(CulturalProtocolControlledInterface $item, AccountInterface $account): array {
    $after = clone $item;
    if ($this->change($after)) {
      return [];
    }

    $label = $this->label($item);
    $notices = [];

    if ($this->canView($item, $account) && !$this->canView($after, $account)) {
      $notices[] = $this->t('@item: you will no longer be able to see it.', ['@item' => $label]);
    }
    elseif ($this->canUpdate($item, $account) && !$this->canUpdate($after, $account)) {
      $notices[] = $this->t('@item: you will no longer be able to edit it.', ['@item' => $label]);
    }

    if ($this->isVisibleToEveryone($item) && !$this->isVisibleToEveryone($after)) {
      $notices[] = $this->t('@item: will no longer be visible to everyone.', ['@item' => $label]);
    }
    else {
      $notices = array_merge($notices, $this->memberLossNotices($item, $after, $label));
    }

    return $notices;
  }

  /**
   * Whether every user is a member of the item's protocol set.
   *
   * Everyone is a member of an open protocol, so "any" sharing with one open
   * protocol, or "all" sharing with only open protocols, reaches everyone.
   */
  protected function isVisibleToEveryone(CulturalProtocolControlledInterface $item): bool {
    $protocols = $item->getProtocolEntities();
    if (!$protocols) {
      return FALSE;
    }
    $open = array_filter($protocols, fn($protocol) => $protocol->isOpen());
    return $item->getSharingSetting() === 'all' ? count($open) === count($protocols) : (bool) $open;
  }

  /**
   * Whether the account could view the item as it stands in memory.
   *
   * Published items are viewed through stored node grants, which only change
   * on save, so protocol set membership stands in for them here.
   */
  protected function canView(CulturalProtocolControlledInterface $item, AccountInterface $account): bool {
    return $account->hasPermission('bypass node access') || $item->isProtocolSetMember($account);
  }

  /**
   * Whether the account could edit the item as it stands in memory.
   */
  protected function canUpdate(CulturalProtocolControlledInterface $item, AccountInterface $account): bool {
    $handler = $this->entityTypeManager->getAccessControlHandler($item->getEntityTypeId());
    $handler->resetCache();
    $allowed = $handler->access($item, 'update', $account);
    $handler->resetCache();
    return $allowed;
  }

  /**
   * Returns the item's protocol IDs, sorted.
   */
  protected function sortedProtocolIds(CulturalProtocolControlledInterface $item): array {
    $ids = array_map('intval', $item->getProtocols());
    sort($ids);
    return $ids;
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
