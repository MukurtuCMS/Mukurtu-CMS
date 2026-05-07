<?php

namespace Drupal\mukurtu_protocol\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\Url;
use Drupal\og\OgRoleInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base form for the "Manage roles" bulk action on OG members pages.
 *
 * Subclasses provide the tempstore collection name, the group entity type/
 * bundle, and the redirect route so this form can serve both community and
 * protocol members pages.
 */
abstract class ManageBulkRolesFormBase extends FormBase {

  /**
   * The private tempstore factory.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected $tempStoreFactory;

  /**
   * Constructs a ManageBulkRolesFormBase object.
   */
  public function __construct(PrivateTempStoreFactory $temp_store_factory) {
    $this->tempStoreFactory = $temp_store_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('tempstore.private'));
  }

  /**
   * Returns the tempstore collection name for this group type.
   *
   * @return string
   */
  abstract protected function getTempStoreCollection(): string;

  /**
   * Returns the members-list route name to redirect back to after save.
   *
   * @return string
   */
  abstract protected function getMembersRouteId(): string;

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $group = NULL) {
    $membership_ids = $this->tempStoreFactory
      ->get($this->getTempStoreCollection())
      ->get('membership_ids');

    if (empty($membership_ids)) {
      $this->messenger()->addWarning($this->t('No members were selected.'));
      return $form;
    }

    /** @var \Drupal\og\Entity\OgMembership[] $memberships */
    $memberships = \Drupal::entityTypeManager()
      ->getStorage('og_membership')
      ->loadMultiple($membership_ids);

    if (empty($memberships)) {
      $this->messenger()->addWarning($this->t('The selected members could not be loaded.'));
      return $form;
    }

    // Use the first membership's group to derive the role set.
    $first = reset($memberships);
    $group_entity = $first->getGroup();

    $role_manager = \Drupal::service('og.role_manager');
    $all_roles = $role_manager->getRolesByBundle(
      $group_entity->getEntityTypeId(),
      $group_entity->bundle()
    );

    // Keep only assignable (non-required) roles, sorted by weight.
    $roles = array_filter($all_roles, fn($r) => $r->getRoleType() === OgRoleInterface::ROLE_TYPE_STANDARD);
    $role_options = array_map(fn($r) => $r->getLabel(), $roles);
    $role_options = _mukurtu_protocol_sort_role_options($role_options);

    // Build the table header: User + one column per role.
    $header = [$this->t('User')];
    foreach ($role_options as $role_id => $role_label) {
      $header[] = $role_label;
    }

    $form['roles_table'] = [
      '#type' => 'table',
      '#caption' => $this->t('Members and roles'),
      '#header' => $header,
      '#empty' => $this->t('No members found.'),
    ];

    foreach ($memberships as $membership) {
      $user = $membership->getOwner();
      if (!$user) {
        continue;
      }

      $current_role_ids = array_keys($membership->getRoles());
      $row = [];

      $row['username'] = [
        '#markup' => $this->t('<strong>@name</strong>', ['@name' => $user->getDisplayName()]),
      ];

      foreach ($role_options as $role_id => $role_label) {
        $row[$role_id] = [
          '#type' => 'checkbox',
          '#title' => $role_label,
          '#title_display' => 'invisible',
          '#default_value' => in_array($role_id, $current_role_ids),
          '#attributes' => ['aria-label' => $this->t('@role for @name', ['@role' => $role_label, '@name' => $user->getDisplayName()])],
        ];
      }

      // The row key IS the membership ID; submit reads it from the table values.
      $form['roles_table'][$membership->id()] = $row;
    }

    // Store role IDs so submit knows which columns to read.
    $form['role_ids'] = [
      '#type' => 'value',
      '#value' => array_keys($role_options),
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#button_type' => 'primary',
    ];
    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#url' => $group
        ? Url::fromRoute($this->getMembersRouteId(), ['group' => $group->id()])
        : Url::fromRoute('<front>'),
      '#attributes' => ['class' => ['button']],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $table_values = $form_state->getValue('roles_table', []);
    $role_ids = $form_state->getValue('role_ids', []);

    $storage = \Drupal::entityTypeManager()->getStorage('og_membership');
    $role_storage = \Drupal::entityTypeManager()->getStorage('og_role');

    $all_roles = $role_storage->loadMultiple($role_ids);
    $updated = 0;
    $group = NULL;

    foreach ($table_values as $membership_id => $row) {
      /** @var \Drupal\og\Entity\OgMembership $membership */
      $membership = $storage->load($membership_id);
      if (!$membership) {
        continue;
      }

      // Capture the group for the post-save redirect.
      if (!$group) {
        $group = $membership->getGroup();
      }

      // Preserve required/base roles; replace only the standard assignable ones.
      $current_roles = $membership->getRoles();
      $new_roles = array_filter($current_roles, fn($r) => $r->getRoleType() !== OgRoleInterface::ROLE_TYPE_STANDARD);

      foreach ($role_ids as $role_id) {
        if (!empty($row[$role_id]) && isset($all_roles[$role_id])) {
          $new_roles[$role_id] = $all_roles[$role_id];
        }
      }

      $membership->setRoles(array_values($new_roles));
      $membership->save();
      $updated++;
    }

    // Clear the tempstore now that we're done.
    $this->tempStoreFactory
      ->get($this->getTempStoreCollection())
      ->delete('membership_ids');

    $this->messenger()->addStatus($this->formatPlural(
      $updated,
      'Updated roles for 1 member.',
      'Updated roles for @count members.'
    ));

    if ($group) {
      $form_state->setRedirect($this->getMembersRouteId(), ['group' => $group->id()]);
    }
  }

}
