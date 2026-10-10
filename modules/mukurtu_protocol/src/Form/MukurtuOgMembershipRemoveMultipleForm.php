<?php

namespace Drupal\mukurtu_protocol\Form;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultReasonInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\TempStore\PrivateTempStore;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\Url;
use Drupal\mukurtu_protocol\Plugin\Action\MukurtuDeleteOgMembershipAction;
use Drupal\og\OgAccessInterface;
use Drupal\og\OgMembershipInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Confirms the bulk removal of members from a community or protocol.
 *
 * Reached from the members overview bulk form: the delete action stages the
 * selected memberships in the tempstore and redirects here, so that a bulk
 * removal is confirmed before it happens rather than applied on the first
 * click (#2370, WCAG 2.1 3.3.4 Error Prevention).
 *
 * The staged selection can include memberships that cannot be removed yet,
 * because the person still belongs to a protocol within the community. Those
 * are listed separately with the reason, instead of being dropped from the
 * selection with a generic error the way the bulk form would do it: an
 * og_membership has no label, so that error cannot even name the person.
 *
 * @see \Drupal\mukurtu_protocol\Plugin\Action\MukurtuDeleteOgMembershipAction
 */
class MukurtuOgMembershipRemoveMultipleForm extends ConfirmFormBase {

  /**
   * The memberships that will be removed.
   *
   * @var \Drupal\og\OgMembershipInterface[]
   */
  protected array $removable = [];

  /**
   * The staged memberships that cannot be removed, keyed by membership ID.
   *
   * Each value is the reason, from the access result.
   *
   * @var array
   */
  protected array $blocked = [];

  /**
   * The group the staged memberships belong to.
   *
   * @var \Drupal\Core\Entity\EntityInterface|null
   */
  protected ?EntityInterface $group = NULL;

  /**
   * The tempstore holding the staged selection.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStore
   */
  protected PrivateTempStore $tempStore;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityRepositoryInterface $entityRepository,
    protected OgAccessInterface $ogAccess,
    PrivateTempStoreFactory $temp_store_factory,
    protected AccountInterface $account,
  ) {
    $this->tempStore = $temp_store_factory->get(MukurtuDeleteOgMembershipAction::TEMPSTORE_COLLECTION);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity.repository'),
      $container->get('og.access'),
      $container->get('tempstore.private'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mukurtu_og_membership_remove_multiple_form';
  }

  /**
   * Access callback for the confirm route.
   *
   * Allowed when the user may manage members of a group that has memberships
   * staged in their own tempstore. Whether each individual membership can go
   * is decided per membership, in buildForm() and again in submitForm().
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account to check.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result.
   */
  public function access(AccountInterface $account) {
    foreach ($this->loadStagedMemberships() as $membership) {
      $group = $membership->getGroup();
      if ($group && $this->ogAccess->userAccess($group, 'manage members', $account)->isAllowed()) {
        // The tempstore is per user and per session, so this result cannot be
        // shared with anybody else.
        return AccessResult::allowed()->setCacheMaxAge(0);
      }
    }
    return AccessResult::forbidden()->setCacheMaxAge(0);
  }

  /**
   * Loads the memberships staged for removal by the current user.
   *
   * @return \Drupal\og\OgMembershipInterface[]
   *   The staged memberships, in the order they were staged. Memberships that
   *   no longer exist are skipped.
   */
  protected function loadStagedMemberships(): array {
    $selection = $this->tempStore->get($this->tempStoreKey());
    if (empty($selection) || !is_array($selection)) {
      return [];
    }
    $memberships = $this->entityTypeManager
      ->getStorage('og_membership')
      ->loadMultiple(array_keys($selection));

    return array_filter($memberships, fn ($membership) => $membership instanceof OgMembershipInterface);
  }

  /**
   * The tempstore key the delete action staged this user's selection under.
   *
   * @return string
   *   The tempstore key.
   */
  protected function tempStoreKey(): string {
    return $this->account->id() . ':og_membership';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    foreach ($this->loadStagedMemberships() as $membership) {
      // The bulk form stages one group's members at a time, so the first
      // membership's group is the group this removal is about.
      $this->group = $this->group ?? $membership->getGroup();

      $access = $membership->access('delete', $this->account, TRUE);
      if ($access->isAllowed()) {
        $this->removable[$membership->id()] = $membership;
      }
      else {
        $this->blocked[$membership->id()] = [
          'name' => $this->memberName($membership),
          'reason' => $access instanceof AccessResultReasonInterface ? $access->getReason() : NULL,
        ];
      }
    }

    $form = parent::buildForm($form, $form_state);
    $form['description']['#weight'] = -10;

    if ($this->removable) {
      // The list heading is an h2 rather than the h3 item_list would render,
      // so that it follows the confirm form's h1 without skipping a level.
      $form['members'] = [
        '#type' => 'container',
        '#weight' => -5,
        'heading' => [
          '#type' => 'html_tag',
          '#tag' => 'h2',
          '#value' => $this->formatPlural(
            count($this->removable),
            'Member to remove',
            '@count members to remove',
          ),
        ],
        'list' => [
          '#theme' => 'item_list',
          '#items' => array_values(array_map([$this, 'memberName'], $this->removable)),
        ],
      ];
    }

    if ($this->blocked) {
      $items = [];
      foreach ($this->blocked as $blocked) {
        $items[] = $blocked['reason']
          ? $this->t('@name: @reason', [
            '@name' => $blocked['name'],
            '@reason' => $blocked['reason'],
          ])
          : $blocked['name'];
      }
      $form['blocked'] = [
        '#type' => 'container',
        '#weight' => -4,
        'heading' => [
          '#type' => 'html_tag',
          '#tag' => 'h2',
          '#value' => $this->formatPlural(
            count($this->blocked),
            'Member that cannot be removed yet, and will be left as they are',
            '@count members that cannot be removed yet, and will be left as they are',
          ),
        ],
        'list' => [
          '#theme' => 'item_list',
          '#items' => $items,
        ],
      ];
    }

    $form['actions']['#weight'] = 10;

    // Nothing can be removed, so there is nothing to confirm.
    if (!$this->removable) {
      $form['actions']['submit']['#access'] = FALSE;
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $removed = 0;
    foreach ($this->removable as $membership) {
      // Access is checked again here, in case it changed while the
      // confirmation was on screen.
      if (!$membership->access('delete', $this->account)) {
        continue;
      }
      $owner = $membership->getOwner();
      $membership->delete();
      if ($owner) {
        Cache::invalidateTags(["user:{$owner->id()}"]);
      }
      $removed++;
    }

    $this->tempStore->delete($this->tempStoreKey());

    if ($removed) {
      $this->messenger()->addStatus($this->formatPlural(
        $removed,
        'Removed 1 member from %group.',
        'Removed @count members from %group.',
        ['%group' => $this->groupLabel()],
      ));
    }

    $form_state->setRedirectUrl($this->getCancelUrl());
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    if (!$this->removable) {
      return $this->t('No members can be removed from %group', ['%group' => $this->groupLabel()]);
    }
    return $this->formatPlural(
      count($this->removable),
      'Remove 1 member from %group?',
      'Remove @count members from %group?',
      ['%group' => $this->groupLabel()],
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    if ($this->group && $this->group->getEntityTypeId() === 'protocol') {
      return $this->t('They will lose their roles in this protocol and will no longer see content shared with it. Their accounts are not affected, and you can add them back from the members list, but their roles will need to be set again.');
    }
    return $this->t('They will lose their roles in this community and will no longer see content shared with it. Their accounts are not affected, and you can add them back from the members list, but their roles will need to be set again.');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Remove');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    if ($this->group && $this->group->getEntityTypeId() === 'protocol') {
      return Url::fromRoute('mukurtu_protocol.protocol_members_list', ['group' => $this->group->id()]);
    }
    if ($this->group) {
      return Url::fromRoute('mukurtu_protocol.community_members_list', ['group' => $this->group->id()]);
    }
    return Url::fromRoute('<front>');
  }

  /**
   * The group's label, in the language the page is being built in.
   *
   * @return string
   *   The group label, or an empty string if the group has gone away.
   */
  protected function groupLabel(): string {
    if (!$this->group) {
      return '';
    }
    return (string) $this->entityRepository->getTranslationFromContext($this->group)->label();
  }

  /**
   * The display name of the member a membership belongs to.
   *
   * @param \Drupal\og\OgMembershipInterface $membership
   *   The membership.
   *
   * @return string
   *   The member's display name, or an empty string if the user has gone away.
   */
  public function memberName(OgMembershipInterface $membership): string {
    $owner = $membership->getOwner();
    return $owner ? (string) $owner->getDisplayName() : '';
  }

}
