<?php

namespace Drupal\mukurtu_protocol\Form;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form controller for Protocol edit forms.
 *
 * @ingroup mukurtu_protocol
 */
class ProtocolForm extends ContentEntityForm {

  use DeleteBlockerListTrait;
  use MukurtuAdvancedSidebarFormTrait;

  /**
   * The current user account.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $account;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    // Instantiates this form class.
    $instance = parent::create($container);
    $instance->account = $container->get('current_user');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $this->injectAdvancedSidebarContainer($form);
    $form = parent::form($form, $form_state);
    $this->buildAdvancedSidebarAuthorGroup($form);
    $this->groupAdvancedSidebarRevisionLog($form);
    $this->suppressAdvancedSidebarStatusDescription($form);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    /* @var \Drupal\mukurtu_protocol\Entity\Protocol $entity */
    $form = parent::buildForm($form, $form_state);

    if (!$this->entity->isNew()) {
      $form['new_revision'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Create new revision'),
        '#default_value' => FALSE,
      ];
      $this->groupAdvancedSidebarRevisionCheckbox($form);
      $this->addDeleteBlockedNotice($form);
    }

    return $form;
  }

  /**
   * Explains why the protocol can't be deleted, when that applies.
   *
   * Shown only to users who can reach the delete form, which lists what is
   * blocking the delete.
   *
   * @param array $form
   *   The form.
   */
  protected function addDeleteBlockedNotice(array &$form): void {
    $entity = $this->entity;
    $cacheability = (new CacheableMetadata())->addCacheTags(['node_list', 'media_list']);
    /** @var \Drupal\mukurtu_protocol\ProtocolAccessControlHandler $handler */
    $handler = $this->entityTypeManager->getAccessControlHandler('protocol');
    $access = $handler->deleteFormAccess($entity, $this->currentUser());
    $cacheability->addCacheableDependency($access);

    $total = $access->isAllowed() ? array_sum(array_map('count', $entity->getReferencingEntityIds())) : 0;
    if ($total > 0) {
      $form['delete_blocked_notice'] = $this->buildDeleteBlockedWarning([
        '#type' => 'inline_template',
        '#template' => '{{ reason }} {{ link }}',
        '#context' => [
          'reason' => $this->formatPlural($total,
            "This cultural protocol can't be deleted because 1 item still uses it.",
            "This cultural protocol can't be deleted because @count items still use it."
          ),
          'link' => $entity->toLink($this->t('See which items'), 'delete-form')->toRenderable(),
        ],
      ]);
      $form['delete_blocked_notice']['#weight'] = -1000;
    }

    $cacheability->merge(CacheableMetadata::createFromRenderArray($form))->applyTo($form);
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $entity = $this->entity;

    // Save as a new revision if requested to do so.
    if (!$form_state->isValueEmpty('new_revision') && $form_state->getValue('new_revision') != FALSE) {
      $entity->setNewRevision();

      // If a new revision is created, save the current user as revision author.
      $entity->setRevisionCreationTime($this->time->getRequestTime());
      $entity->setRevisionUserId($this->account->id());
    }
    else {
      $entity->setNewRevision(FALSE);
    }

    $status = parent::save($form, $form_state);

    switch ($status) {
      case SAVED_NEW:
        $this->messenger()->addMessage($this->t('Created the %label Protocol.', [
          '%label' => $entity->label(),
        ]));
        break;

      default:
        $this->messenger()->addMessage($this->t('Saved the %label Protocol.', [
          '%label' => $entity->label(),
        ]));
    }

    // Add the current user as a member of this protocol and grant them the
    // protocol steward role.
    /** @var \Drupal\mukurtu_protocol\Entity\Protocol $entity */
    $entity->addMember($this->account)->setRoles($this->account, ['protocol_steward']);

    $form_state->setRedirect('entity.protocol.canonical', ['protocol' => $entity->id()]);
  }

}
