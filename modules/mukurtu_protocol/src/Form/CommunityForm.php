<?php

namespace Drupal\mukurtu_protocol\Form;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form controller for Community edit forms.
 *
 * @ingroup mukurtu_protocol
 */
class CommunityForm extends ContentEntityForm {

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
    /* @var \Drupal\mukurtu_protocol\Entity\Community $entity */
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
   * Explains why the community can't be deleted, when that applies.
   *
   * Shown only to users who can reach the delete form, which lists what is
   * blocking the delete.
   *
   * @param array $form
   *   The form.
   */
  protected function addDeleteBlockedNotice(array &$form): void {
    $entity = $this->entity;
    $cacheability = (new CacheableMetadata())->addCacheTags([
      'protocol_list',
      'config:mukurtu_protocol.community_organization',
    ]);
    /** @var \Drupal\mukurtu_protocol\CommunityAccessControlHandler $handler */
    $handler = $this->entityTypeManager->getAccessControlHandler('community');
    $access = $handler->deleteFormAccess($entity, $this->currentUser());
    $cacheability->addCacheableDependency($access);

    if ($access->isAllowed() && ($entity->isParentCommunity() || !empty($entity->getProtocols()))) {
      $form['delete_blocked_notice'] = [
        '#theme' => 'status_messages',
        '#message_list' => [
          'warning' => [
            [
              '#type' => 'inline_template',
              '#template' => '{{ reason }} {{ link }}',
              '#context' => [
                'reason' => $this->t("This community can't be deleted because it still has cultural protocols or child communities."),
                'link' => $entity->toLink($this->t('See why'), 'delete-form')->toRenderable(),
              ],
            ],
          ],
        ],
        '#status_headings' => ['warning' => $this->t('Warning message')],
        '#weight' => -1000,
      ];
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
        $this->messenger()->addMessage($this->t('Created the %label Community.', [
          '%label' => $entity->label(),
        ]));
        break;

      default:
        $this->messenger()->addMessage($this->t('Saved the %label Community.', [
          '%label' => $entity->label(),
        ]));
    }
    $form_state->setRedirect('entity.community.canonical', ['community' => $entity->id()]);
  }

}
