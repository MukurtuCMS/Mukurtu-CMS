<?php

declare(strict_types=1);

namespace Drupal\mukurtu_protocol\Plugin\Field\FieldFormatter;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\og\OgAccessInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Custom Mukurtu plugin implementation for the OG subscribe formatter.
 * This plugin copies OgGroupSubscribeFormatter, but removes the subscribe links.
 *
 * @FieldFormatter(
 *   id = "mukurtu_og_group_subscribe",
 *   label = @Translation("Mukurtu OG Group subscribe"),
 *   description = @Translation("Display OG Group subscribe and un-subscribe links."),
 *   field_types = {
 *     "og_group"
 *   }
 * )
 * @todo remove this formatter once we have group subscription working.
 */
class MukurtuOgGroupSubscribeFormatter extends FormatterBase implements ContainerFactoryPluginInterface
{
  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The OG access service.
   *
   * @var \Drupal\og\OgAccessInterface
   */
  protected $ogAccess;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new MukurtuGroupSubscribeFormatter object.
   *
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Any third party settings settings.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\og\OgAccessInterface $og_access
   *   The OG access service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, AccountInterface $current_user, OgAccessInterface $og_access, EntityTypeManagerInterface $entity_type_manager)
  {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);

    $this->currentUser = $current_user;
    $this->ogAccess = $og_access;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition)
  {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('current_user'),
      $container->get('og.access'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode)
  {
    $elements = [];

    // Cache by the OG membership state. Anonymous users are handled below.
    $elements['#cache']['contexts'] = [
      'og_membership_state',
      'user.roles:authenticated',
    ];
    $cache_meta = CacheableMetadata::createFromRenderArray($elements);

    $group = $items->getEntity();
    $cache_meta->merge(CacheableMetadata::createFromObject($group));
    $cache_meta->applyTo($elements);

    $user = $this->entityTypeManager->getStorage('user')->load(($this->currentUser->id()));

    $storage = $this->entityTypeManager->getStorage('og_membership');
    $props = [
      'uid' => $user ? $user->id() : 0,
      'entity_type' => $group->getEntityTypeId(),
      'entity_bundle' => $group->bundle(),
      'entity_id' => $group->id(),
    ];
    $memberships = $storage->loadByProperties($props);
    /** @var \Drupal\og\OgMembershipInterface $membership */
    $membership = reset($memberships);

    if ($membership) {
      $cache_meta->merge(CacheableMetadata::createFromObject($membership));
      $cache_meta->applyTo($elements);
      if ($membership->isBlocked()) {
        // If user is blocked, they should not be able to apply for
        // membership.
        return $elements;
      }

      $roles = $membership->getRoles();
      $unwantedRoleIndex = $this->getUnwantedRoleIndex($roles);

      if ($unwantedRoleIndex != -1) {
        unset($roles[$unwantedRoleIndex]);
        // Reindex the array after unset.
        $roles = array_values($roles);
      }
      $rolesMessage = $this->createRolesMessage($roles);
      // Member is pending or active.
      $elements[0] = [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#attributes' => [
          'title' => $this->t($rolesMessage),
          'class' => ['group'],
        ],
        '#value' => $this->t($rolesMessage),
      ];

      return $elements;
    }

    return $elements;
  }


  /**
   * If the "Member" role is in the roles list, find the index it's located at.
   * We don't want to show this role in the roles message, so we need to remove
   * it before we can process the roles list.
   */
  protected function getUnwantedRoleIndex(array $rolesList) {
    $unwantedRoleIndex = -1;
    $i = 0;
    foreach ($rolesList as $role) {
      if ($role->label() == "Member") {
        $unwantedRoleIndex = $i;
        break;
      }
      $i++;
    }
    return $unwantedRoleIndex;
  }

  /**
   * Generate a message which lists the current user's roles.
   */
  protected function createRolesMessage($rolesList) {
    $rolesMessage = "You are a ";
    $total = count($rolesList);
    if ($total == 1) {
      // User has single role, do not add "and" to the message.
      $rolesMessage .= $rolesList[0]->label();
    }
    // User has two roles, no commas please.
    else if ($total == 2) {
      $rolesMessage .= $rolesList[0]->label() . ' and a ' . $rolesList[1]->label();
    }
    else {
      // User has three or more roles, so the message needs list formatting.
      $count = 1;
      foreach ($rolesList as $role) {
        if ($count < $total) {
          $rolesMessage .= $role->label() . ', ';
        }
        else if ($count == $total) {
          $rolesMessage .= "and a " . $role->label();
        }
        $count++;
      }
    }

    return $rolesMessage;
  }
}
