<?php

declare(strict_types=1);

namespace Drupal\mukurtu_core\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Config\FileStorage;
use Drupal\Core\Config\InstallStorage;
use Drupal\Core\Config\Schema\SchemaCheckTrait;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ProfileExtensionList;
use Drupal\Core\State\StateInterface;

/**
 * Keeps a site's Klaro configuration across an uninstall and re-install.
 *
 * Uninstalling Klaro deletes all of its configuration, including the settings
 * Mukurtu ships in the profile and anything the site customized since. When
 * Klaro is installed again, restore the site's previous configuration if it
 * was saved and still matches Klaro's config schema. Otherwise apply the
 * profile's defaults, as a fresh Mukurtu install would.
 */
class KlaroConfigKeeper {

  use SchemaCheckTrait;

  /**
   * The state key holding the saved configuration.
   */
  const STATE_KEY = 'mukurtu_core.klaro_config_snapshot';

  /**
   * The roles granted "use klaro" by a fresh Mukurtu install.
   */
  const DEFAULT_ROLES = ['anonymous', 'authenticated'];

  /**
   * Klaro's config entity types, in the order they must be created.
   */
  const ENTITY_TYPES = ['klaro_purpose', 'klaro_app'];

  public function __construct(
    protected StorageInterface $configStorage,
    protected ConfigFactoryInterface $configFactory,
    protected ConfigManagerInterface $configManager,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected TypedConfigManagerInterface $typedConfigManager,
    protected StateInterface $state,
    protected ProfileExtensionList $profileList,
  ) {}

  /**
   * Saves the site's current Klaro configuration.
   */
  public function snapshot(): void {
    $snapshot = [
      'config' => $this->configStorage->readMultiple($this->configStorage->listAll('klaro.')),
      'translations' => [],
      'roles' => [],
    ];

    foreach ($this->configStorage->getAllCollectionNames() as $collection) {
      if (!str_starts_with($collection, 'language.')) {
        continue;
      }
      $storage = $this->configStorage->createCollection($collection);
      $translations = $storage->readMultiple($storage->listAll('klaro.'));
      if ($translations) {
        $snapshot['translations'][$collection] = $translations;
      }
    }

    /** @var \Drupal\user\RoleInterface $role */
    foreach ($this->entityTypeManager->getStorage('user_role')->loadMultiple() as $role) {
      if (!$role->isAdmin() && $role->hasPermission('use klaro')) {
        $snapshot['roles'][] = $role->id();
      }
    }

    $this->state->set(self::STATE_KEY, $snapshot);
  }

  /**
   * Restores the saved configuration, or applies the profile's defaults.
   *
   * @return bool
   *   TRUE if the saved configuration was restored, FALSE if the defaults were
   *   applied instead.
   */
  public function restore(): bool {
    $snapshot = $this->state->get(self::STATE_KEY);
    $this->state->delete(self::STATE_KEY);

    if (is_array($snapshot) && !empty($snapshot['config']) && $this->isValid($snapshot)) {
      $this->applyConfig($snapshot['config'], TRUE);
      foreach ($snapshot['translations'] ?? [] as $collection => $translations) {
        $storage = $this->configStorage->createCollection($collection);
        foreach ($translations as $name => $data) {
          $storage->write($name, $data);
        }
      }
      $this->configFactory->reset();
      $this->grantUseKlaro($snapshot['roles'] ?? []);
      return TRUE;
    }

    $this->applyConfig($this->getDefaultConfig(), FALSE);
    $this->grantUseKlaro(self::DEFAULT_ROLES);
    return FALSE;
  }

  /**
   * Returns the Klaro configuration the Mukurtu profile ships.
   */
  protected function getDefaultConfig(): array {
    $path = $this->profileList->getPath('mukurtu') . '/' . InstallStorage::CONFIG_INSTALL_DIRECTORY;
    $storage = new FileStorage($path);
    return $storage->readMultiple($storage->listAll('klaro.'));
  }

  /**
   * Checks every saved item still matches Klaro's current config schema.
   *
   * Klaro may have been updated while it was uninstalled, so the saved
   * configuration could describe settings that no longer exist.
   */
  protected function isValid(array $snapshot): bool {
    // Translations hold a subset of the keys, which the schema check allows.
    $sets = array_merge([$snapshot['config']], array_values($snapshot['translations'] ?? []));
    foreach ($sets as $items) {
      foreach ($items as $name => $data) {
        if ($this->checkConfigSchema($this->typedConfigManager, $name, $data) !== TRUE) {
          return FALSE;
        }
      }
    }
    return TRUE;
  }

  /**
   * Writes Klaro configuration, through the entity API for config entities.
   *
   * @param array $configs
   *   Configuration data keyed by config name.
   * @param bool $replace
   *   TRUE to also delete Klaro config entities that are not in $configs.
   */
  protected function applyConfig(array $configs, bool $replace): void {
    $entities = array_fill_keys(self::ENTITY_TYPES, []);
    foreach ($configs as $name => $data) {
      $entity_type_id = $this->configManager->getEntityTypeIdByName($name);
      if (isset($entities[$entity_type_id])) {
        $entities[$entity_type_id][$data['id']] = $data;
      }
      elseif ($entity_type_id === NULL) {
        $this->configFactory->getEditable($name)->setData($data)->save();
      }
    }

    foreach ($entities as $entity_type_id => $records) {
      /** @var \Drupal\Core\Config\Entity\ConfigEntityStorageInterface $storage */
      $storage = $this->entityTypeManager->getStorage($entity_type_id);
      $existing = $storage->loadMultiple();
      foreach ($records as $id => $record) {
        if (isset($existing[$id])) {
          // Keep the UUID the fresh install gave it; saving a different one
          // over an existing config entity throws.
          $record['uuid'] = $existing[$id]->uuid();
          $entity = $storage->updateFromStorageRecord($existing[$id], $record);
        }
        else {
          $entity = $storage->createFromStorageRecord($record);
        }
        $entity->save();
      }
      if ($replace) {
        $storage->delete(array_diff_key($existing, $records));
      }
    }
  }

  /**
   * Grants the "use klaro" permission to the given roles, where they exist.
   */
  protected function grantUseKlaro(array $role_ids): void {
    if (!$role_ids) {
      return;
    }
    /** @var \Drupal\user\RoleInterface $role */
    foreach ($this->entityTypeManager->getStorage('user_role')->loadMultiple($role_ids) as $role) {
      if (!$role->isAdmin() && !$role->hasPermission('use klaro')) {
        $role->grantPermission('use klaro')->save();
      }
    }
  }

}
