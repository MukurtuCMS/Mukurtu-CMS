<?php

declare(strict_types=1);

namespace Drupal\mukurtu_import\Plugin\migrate\process;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\Attribute\MigrateProcess;
use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Drupal\og\Entity\OgRole;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Resolves a "Name>role1|role2" cell into a community/protocol membership.
 *
 * Community/protocol membership isn't a real field on the user entity, so
 * this plugin does its own compound-string parsing and entity resolution
 * rather than being composed from the generic uuid_lookup/mukurtu_entity_lookup
 * plugins, which assume a real destination field and migration context
 * (see MukurtuImportStrategy::getProcess()).
 *
 * Configuration:
 * - entity_type: 'community' or 'protocol'.
 * - delimiter: the character separating the group name from its role list.
 *   Defaults to '>' (matching LocalContextsLabelLookup's default) rather
 *   than ':', since community/protocol names commonly contain colons.
 *
 * An unresolvable or ambiguous group name is a hard failure: membership
 * grants are Mukurtu's core access-control primitive, so a typo'd name
 * silently producing no membership is a much higher-consequence silent
 * failure than a blank reference field.
 */
#[MigrateProcess('mukurtu_group_membership_lookup')]
class GroupMembershipLookup extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a GroupMembershipLookup object.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, protected EntityTypeManagerInterface $entityTypeManager) {
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
    );
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\migrate\MigrateException
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $group_entity_type = $this->configuration['entity_type'] ?? NULL;
    if (!in_array($group_entity_type, ['community', 'protocol'], TRUE)) {
      throw new MigrateException('The mukurtu_group_membership_lookup plugin requires entity_type to be "community" or "protocol".');
    }

    $value = trim((string) $value);
    if ($value === '') {
      return NULL;
    }

    // Split on the first unescaped delimiter separating the group name from
    // its pipe-delimited role list (mirrors LocalContextsLabelLookup's
    // escaped-delimiter compound-string parsing).
    $delimiter = $this->configuration['delimiter'] ?? '>';
    $escaped_delimiter = preg_quote($delimiter, '/');
    $parts = preg_split('/(?<!\\\\)' . $escaped_delimiter . '/', $value, 2);
    $name = trim(str_replace('\\' . $delimiter, $delimiter, $parts[0]));
    $roles = [];
    if (isset($parts[1])) {
      $roles = array_values(array_filter(
        array_map('trim', explode('|', $parts[1])),
        fn(string $role) => $role !== '',
      ));
    }

    if ($name === '') {
      throw new MigrateException(sprintf('"%s" does not contain a %s name.', $value, $group_entity_type));
    }

    $resolved_roles = [];
    foreach ($roles as $role) {
      $resolved_role = $this->resolveRole($role, $group_entity_type);
      if ($resolved_role === NULL) {
        // Community::addMember()/Protocol::addMember() silently drop any
        // role that doesn't resolve to a real OgRole rather than erroring,
        // which would otherwise let a typo'd role name produce a
        // successfully-created but silently role-less membership. Fail the
        // row instead, consistent with treating an unresolvable name as a
        // hard failure above.
        throw new MigrateException(sprintf('"%s" is not a valid role for a %s.', $role, $group_entity_type));
      }
      $resolved_roles[] = $resolved_role;
    }

    return [
      'target_id' => $this->resolveGroup($name, $group_entity_type, $value),
      'roles' => $resolved_roles,
    ];
  }

  /**
   * Resolves a role machine name or label to its machine name.
   *
   * @param string $role
   *   The role as given in the CSV cell -- either its machine name (e.g.
   *   "community_manager") or its label (e.g. "Community Manager").
   * @param string $group_entity_type
   *   Either 'community' or 'protocol'.
   *
   * @return string|null
   *   The role's machine name, or NULL if it doesn't resolve to a role on
   *   this group type.
   *
   * @throws \Drupal\migrate\MigrateException
   *   If the role label is ambiguous.
   */
  protected function resolveRole(string $role, string $group_entity_type): ?string {
    // Try the machine name first -- OgRole IDs are deterministic
    // ({entity_type}-{bundle}-{name}), so this is a direct load.
    if (OgRole::getRole($group_entity_type, $group_entity_type, $role)) {
      return $role;
    }

    // Fall back to a case-insensitive label match among this group type's
    // roles (mirrors resolveGroup()'s label-resolution/ambiguity handling).
    $matches = array_values(array_filter(
      OgRole::loadByGroupType($group_entity_type, $group_entity_type),
      fn($og_role) => mb_strtolower($og_role->label()) === mb_strtolower($role),
    ));

    if (count($matches) > 1) {
      throw new MigrateException(sprintf('"%s" is ambiguous, multiple roles for a %s share this label.', $role, $group_entity_type));
    }

    return $matches ? reset($matches)->getName() : NULL;
  }

  /**
   * Resolves a community/protocol name or UUID to an entity ID.
   *
   * @param string $name
   *   The name or UUID as given in the CSV cell.
   * @param string $group_entity_type
   *   Either 'community' or 'protocol'.
   * @param string $original_value
   *   The original, unsplit cell value, used in error messages.
   *
   * @return int|string
   *   The resolved entity ID.
   *
   * @throws \Drupal\migrate\MigrateException
   *   If the name cannot be resolved, or resolves to more than one entity.
   */
  protected function resolveGroup(string $name, string $group_entity_type, string $original_value) {
    $storage = $this->entityTypeManager->getStorage($group_entity_type);
    $entity_type = $storage->getEntityType();

    // Resolve UUIDs first (mirrors UuidLookup).
    if (preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i', $name)) {
      $entities = $storage->loadByProperties(['uuid' => $name]);
      if (!empty($entities)) {
        return reset($entities)->id();
      }
    }

    // Resolve by name/label. Entity queries are case-insensitive by default
    // for a strict '=' comparison, which is the desired behavior here
    // (mirrors the ignore_case option other lookup plugins expose).
    $label_key = $entity_type->getKey('label');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition($label_key, $name, '=')
      ->execute();

    if (count($ids) > 1) {
      throw new MigrateException(sprintf('"%s" is ambiguous, multiple %s entities are named "%s".', $original_value, $group_entity_type, $name));
    }

    if (empty($ids)) {
      throw new MigrateException(sprintf('"%s" could not be resolved to an existing %s.', $original_value, $group_entity_type));
    }

    return reset($ids);
  }

}
