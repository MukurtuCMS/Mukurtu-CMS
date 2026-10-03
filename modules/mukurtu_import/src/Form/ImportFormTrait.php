<?php

declare(strict_types=1);

namespace Drupal\mukurtu_import\Form;

use Drupal\Core\Session\AccountInterface;

/**
 * Provides shared helper methods for import forms.
 *
 * Classes using this trait must provide:
 * @property \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
 * @property \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
 * @property \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entityBundleInfo
 * @property \Drupal\mukurtu_import\MukurtuImportFieldProcessPluginManager $fieldProcessPluginManager
 */
trait ImportFormTrait {

  /**
   * Memoized field definitions.
   *
   * @var array
   */
  protected array $fieldDefinitions = [];

  /**
   * Get the options array for available text formats.
   *
   * @return array
   *   An array of text format labels indexed by format ID.
   */
  protected function getTextFormatOptions(): array {
    $formats = filter_formats();
    return array_map(fn($format) => $format->label(), $formats);
  }

  /**
   * Get the options array for available target entity types.
   *
   * @return array
   *   An associative array of entity type IDs to labels, filtered by
   *   the current user's create access.
   */
  protected function getEntityTypeIdOptions(): array {
    $definitions = $this->entityTypeManager->getDefinitions();
    $options = [];
    $custom_entity_type_ids = \Drupal::service('mukurtu_core.roundtrip_entity_types')->getCustomEntityTypeIds();
    $entity_type_ids = array_merge(['node', 'media'], $custom_entity_type_ids, ['paragraph', 'taxonomy_term', 'user']);
    foreach ($entity_type_ids as $entity_type_id) {
      // User accounts are more sensitive than other import targets (they can
      // create accounts, assign roles, etc.), so this option additionally
      // requires the dedicated 'import mukurtu users' permission on top of
      // the entity type's own create access.
      if ($entity_type_id === 'user' && !$this->currentUser()->hasPermission('import mukurtu users')) {
        continue;
      }

      if (isset($definitions[$entity_type_id]) && $this->userCanCreateAnyBundleForEntityType($entity_type_id)) {
        $options[$entity_type_id] = $definitions[$entity_type_id]->getLabel();

        if ($entity_type_id === 'paragraph') {
          $options[$entity_type_id] = $this->t('Compound Types (paragraphs)');
        }
      }
    }

    return $options;
  }

  /**
   * Gets the available bundle options for a given entity type.
   *
   * @param string|null $entity_type_id
   *   The entity type ID to get bundles for.
   *
   * @return array
   *   An associative array of bundle options filtered by user access.
   */
  protected function getBundleOptions(?string $entity_type_id): array {
    $bundle_info = $this->entityBundleInfo->getAllBundleInfo();

    if (!isset($bundle_info[$entity_type_id])) {
      return [-1 => $this->t('No sub-types available')];
    }

    $options = [];
    if (count($bundle_info[$entity_type_id]) > 1) {
      $options = [-1 => $this->t('None: Base Fields Only')];
    }

    foreach ($bundle_info[$entity_type_id] as $bundle => $info) {
      if ($this->userCanCreateEntity($entity_type_id, $bundle)) {
        $options[$bundle] = $info['label'] ?? $bundle;
      }
    }
    return $options;
  }

  /**
   * Build the target field options for the mapping select elements.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string|null $bundle
   *   The bundle.
   *
   * @return array
   *   An associative array of field names/subfields to labels.
   */
  protected function buildTargetOptions(string $entity_type_id, ?string $bundle = NULL): array {
    $entity_definition = $this->entityTypeManager->getDefinition($entity_type_id);
    $entity_keys = $entity_definition->getKeys();
    $export_overrides = $this->getExportLabelOverrides($entity_type_id, $bundle);

    $options = [-1 => $this->t('Ignore - Do not import')];
    foreach ($this->getFieldDefinitions($entity_type_id, $bundle) as $field_name => $field_definition) {
      $plugin = $this->fieldProcessPluginManager->getInstance(['field_definition' => $field_definition]);
      $supported_properties = $plugin->getSupportedProperties($field_definition);

      if (!empty($supported_properties)) {
        foreach ($supported_properties as $property_name => $property_info) {
          $target_key = "{$field_name}/{$property_name}";
          $options[$target_key] = $export_overrides[$target_key] ?? $property_info['label'];
        }
      }
      else {
        $options[$field_name] = $export_overrides[$field_name] ?? $field_definition->getLabel();
      }
    }

    // Community/protocol membership isn't a real, writable field on user
    // accounts (the only related field, field_communities, is computed and
    // read-only, and there is no field_protocols equivalent), so these
    // targets can't be discovered through the field-definition loop above.
    // Account Status is a real pair of fields (status, field_pending) but is
    // exposed as a single virtual target instead, since mapping them
    // separately requires knowing field_pending's non-obvious default and
    // the Status-overrides-Pending interaction. All three are handled as
    // virtual destination properties by ProtocolAwareUserContent and
    // MukurtuImportStrategy::getProcess().
    if ($entity_type_id === 'user') {
      $options['communities'] = $this->t('Communities');
      $options['protocols'] = $this->t('Protocols');
      $options['account_status'] = $this->t('Account Status');
    }

    // Some base fields' own definition labels are more generic than what
    // the site's actual admin forms call them; use the more familiar term
    // here instead.
    foreach ($this->getFieldLabelOverrides($entity_type_id) as $field_name => $override_label) {
      if (isset($options[$field_name])) {
        $options[$field_name] = $override_label;
      }
    }

    // Disambiguate the Language field from the langcode base field, unless
    // mukurtu_export's own header for it already disambiguates it (e.g.
    // "Locale") - see getExportLabelOverrides().
    if (isset($options[$entity_keys['langcode']]) && !isset($export_overrides[$entity_keys['langcode']])) {
      $options[$entity_keys['langcode']] .= $this->t(' (langcode)');
    }

    // Keep the "Ignore" option at the top, then sort the rest alphabetically.
    $ignore = [-1 => $options[-1]];
    unset($options[-1]);
    natcasesort($options);
    return $ignore + $options;
  }

  /**
   * Gets the real column headers mukurtu_export writes for a bundle.
   *
   * Several columns get a deliberately different header than the field's
   * own Drupal label when actually exported (e.g. community's "name" field
   * is labeled "Community name" in its field settings but written as bare
   * "Name" in the CSV; langcode's real header varies per bundle between
   * "Locale", "Language", and "Language code"). Since none of that is
   * derivable generically, this looks it up from whichever installed
   * csv_exporter config has an entry for this bundle, so the Customize
   * Settings dropdown, its auto-mapper, and the "Download CSV Template"
   * feature all show labels consistent with what a real export/reimport
   * round trip actually uses.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string|null $bundle
   *   The bundle.
   *
   * @return array
   *   An array of target key (field name, or "field_name/property") to real
   *   export header label. Empty if mukurtu_export isn't installed or has
   *   no matching entry for this bundle (e.g. a custom bundle not covered
   *   by any shipped or site-configured exporter).
   */
  protected function getExportLabelOverrides(string $entity_type_id, ?string $bundle): array {
    if (!\Drupal::moduleHandler()->moduleExists('mukurtu_export')) {
      return [];
    }

    $lookup_key = "{$entity_type_id}__{$bundle}";
    $storage = $this->entityTypeManager->getStorage('csv_exporter');
    foreach ($storage->loadMultiple() as $exporter) {
      $list = $exporter->get('entity_fields_export_list')[$lookup_key] ?? NULL;
      if ($list) {
        return $list;
      }
    }

    return [];
  }

  /**
   * Checks if a user has permission to create an entity of a specific type and bundle.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string|null $bundle
   *   The bundle.
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   The user account. Defaults to the current user.
   *
   * @return bool
   *   TRUE if the user has access.
   */
  protected function userCanCreateEntity(string $entity_type_id, ?string $bundle = NULL, ?AccountInterface $account = NULL): bool {
    if (!$account) {
      $account = $this->currentUser();
    }

    // User accounts don't have a separate "create" permission of their own
    // in this module; the dedicated 'import mukurtu users' permission
    // (already required by getEntityTypeIdOptions()) is the full
    // authorization gate. Without this override, core's default create
    // access check for the user entity type would additionally require the
    // broad 'administer users' permission, defeating the point of a more
    // narrowly scoped import permission.
    if ($entity_type_id === 'user') {
      return $account->hasPermission('import mukurtu users');
    }

    return $this->entityTypeManager->getAccessControlHandler($entity_type_id)->createAccess($bundle, $account);
  }

  /**
   * Checks if a user can create any bundle of a specific entity type.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   The user account. Defaults to the current user.
   *
   * @return bool
   *   TRUE if the user has access to create at least one bundle.
   */
  protected function userCanCreateAnyBundleForEntityType(string $entity_type_id, ?AccountInterface $account = NULL): bool {
    if (!$account) {
      $account = $this->currentUser();
    }

    $bundle_info = $this->entityBundleInfo->getAllBundleInfo();
    if (!empty($bundle_info[$entity_type_id])) {
      foreach ($bundle_info[$entity_type_id] as $bundle_id => $info) {
        if ($this->userCanCreateEntity($entity_type_id, $bundle_id, $account)) {
          return TRUE;
        }
      }
    }
    return FALSE;
  }

  /**
   * Get the field definitions for an entity type/bundle.
   *
   * @param string $entity_type_id
   *   The entity type id.
   * @param string|null $bundle
   *   The bundle.
   *
   * @return \Drupal\Core\Field\FieldDefinitionInterface[]
   *   The field definitions.
   */
  protected function getFieldDefinitions(string $entity_type_id, ?string $bundle = NULL): array {
    if (empty($this->fieldDefinitions[$entity_type_id][$bundle])) {
      $entity_definition = $this->entityTypeManager->getDefinition($entity_type_id);
      $entity_keys = $entity_definition->getKeys();
      $revision_metadata_keys = $entity_definition->getRevisionMetadataKeys();
      $field_defs = $this->entityFieldManager->getFieldDefinitions($entity_type_id, $bundle);

      foreach ($field_defs as $field_name => $field_def) {
        if ($field_name === $entity_keys['id'] || $field_name === $entity_keys['uuid']) {
          continue;
        }

        // Remove revision metadata fields (revision log, user, and timestamp)
        // as valid targets. These are system-managed but not formally marked
        // as internal or read-only in core.
        if (in_array($field_name, $revision_metadata_keys, TRUE)) {
          unset($field_defs[$field_name]);
        }

        // The 'changed' timestamp and 'comment' fields are not relevant to
        // the import use case.
        if ($field_name === 'changed' || $field_name === 'comment') {
          unset($field_defs[$field_name]);
        }

        // Remove unwanted 'behavior_settings' paragraph base field.
        if ($entity_type_id === 'paragraph' && $field_name === 'behavior_settings') {
          unset($field_defs[$field_name]);
        }

        // Passwords must never be settable via import (plaintext passwords
        // are never mapped from a CSV; new accounts get a standard account
        // setup email instead). 'access', 'login', and 'init' are internal
        // bookkeeping fields not meaningful for an admin-authored import.
        if ($entity_type_id === 'user' && in_array($field_name, ['pass', 'access', 'login', 'init'], TRUE)) {
          unset($field_defs[$field_name]);
        }

        // These fields aren't exposed on the interactive account
        // registration or admin "add user" forms either, so offering them
        // here is more confusing than useful for an import audience
        // mirroring those same forms.
        if ($entity_type_id === 'user' && in_array($field_name, ['created', 'message_subscribe_email', 'user_picture', 'preferred_admin_langcode', 'preferred_langcode', 'langcode', 'timezone'], TRUE)) {
          unset($field_defs[$field_name]);
        }

        // Superseded by the unified 'account_status' virtual target
        // (Active/Blocked/Pending), which sets both of these under the hood
        // -- see ProtocolAwareUserContent::applyAccountStatus(). Mapping
        // them directly requires knowing field_pending's non-obvious
        // storage default (1) and the Status-overrides-Pending interaction
        // the interactive account form already hides from the user.
        if ($entity_type_id === 'user' && in_array($field_name, ['status', 'field_pending'], TRUE)) {
          unset($field_defs[$field_name]);
        }

        // Landing pages inherit a required Cultural Protocols field from the
        // shared MukurtuNode bundle class (kept there only for its "allow
        // public view" access override). The field is hidden on the landing
        // page form and isn't part of the editing workflow, so exclude it
        // from import field lists.
        if ($entity_type_id === 'node' && $bundle === 'landing_page' && $field_name === 'field_cultural_protocols') {
          unset($field_defs[$field_name]);
        }

        if ($field_def->isComputed() || $field_def->isReadOnly() || $field_def->isInternal()) {
          unset($field_defs[$field_name]);
        }

        // The default_langcode field is managed internally by Drupal's
        // translation system and throws a LogicException if modified directly,
        // but core does not mark it as internal or read-only.
        if (isset($entity_keys['default_langcode']) && $field_name === $entity_keys['default_langcode']) {
          unset($field_defs[$field_name]);
        }
      }
      $this->fieldDefinitions[$entity_type_id][$bundle] = $field_defs;
    }

    return $this->fieldDefinitions[$entity_type_id][$bundle];
  }

  /**
   * Compare field labels against a search string.
   *
   * @param string $needle
   *   The search term.
   * @param string $entity_type_id
   *   The entity type id.
   * @param string|null $bundle
   *   The bundle.
   * @return string|null
   *   The field name of the match or NULL if no matches found.
   */
  protected function searchFieldLabels(string $needle, string $entity_type_id, ?string $bundle = NULL): ?string {
    $field_defs = $this->getFieldDefinitions($entity_type_id, $bundle);
    $matching_fields = array_filter($field_defs, function($field) use ($needle) {
      return $needle == mb_strtolower((string) $field->getLabel());
    });

    // If there are multiple matches, return the first bundle specific match.
    if (count($matching_fields) > 1) {
      foreach ($matching_fields as $matched_field_name => $matched_field) {
        if ($matched_field->getTargetBundle()) {
          return $matched_field_name;
        }
      }
    }

    // If all are base fields, return the first.
    if (count($matching_fields) >= 1) {
      $field_names = array_keys($matching_fields);
      return reset($field_names);
    }

    // Also match against this entity type's field label overrides (see
    // getFieldLabelOverrides()), so a header using the more familiar term
    // resolves too, alongside the field's own raw definition label.
    foreach ($this->getFieldLabelOverrides($entity_type_id) as $field_name => $override_label) {
      if ($needle == mb_strtolower((string) $override_label) && isset($field_defs[$field_name])) {
        return $field_name;
      }
    }

    return NULL;
  }

  /**
   * Per-entity-type overrides for how a field is labeled/matched in the
   * import UI.
   *
   * Some base fields' own definition labels are more generic, or less
   * familiar, than what the site's actual admin forms call them. Used by
   * buildTargetOptions() to display the more familiar term, and by
   * searchFieldLabels() so a header using either term resolves.
   *
   * @param string $entity_type_id
   *   The entity type id.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup[]
   *   Field name => override label.
   */
  protected function getFieldLabelOverrides(string $entity_type_id): array {
    if ($entity_type_id === 'user') {
      // The 'name' base field's own definition label is 'Name', but the
      // actual "Add user" admin form displays it as 'Username'.
      return ['name' => $this->t('Username')];
    }
    return [];
  }

  /**
   * Some basic logic to try and auto-map source to target.
   *
   * 1. Check for full field label matches (case insensitive).
   * 2. Check for field name matches (case insensitive).
   *
   * @param string $source
   *   The CSV column header to resolve a target for.
   * @param string $entity_type_id
   *   The entity type id.
   * @param string|null $bundle
   *   The bundle.
   * @param array $config_mapping
   *   An existing mapping (as from MukurtuImportStrategyInterface::getMapping())
   *   to check first, so an already-configured source/target pairing takes
   *   precedence over the label/name guessing below.
   */
  protected function getAutoMappedTarget($source, $entity_type_id, $bundle = NULL, array $config_mapping = []) {
    $field_defs = $this->getFieldDefinitions($entity_type_id, $bundle);

    // If the selected config has an existing valid mapping for this field,
    // it has precedence.
    foreach ($config_mapping as $mapping) {
      // Break up any subfields.
      $subfields = explode('/', $mapping['target'], 2);
      $target = reset($subfields);

      // Checking if we have a mapping and the root of the target field exists.
      if ($mapping['source'] == $source && in_array($target, array_keys($field_defs))) {
        return $mapping['target'];
      }
    }

    $needle = mb_strtolower($source);

    // Match against the real mukurtu_export header for this bundle when
    // known - see getExportLabelOverrides(). This takes precedence over the
    // generic property-label matching below since it's authoritative for
    // exactly what a real export/reimport round trip uses (e.g. langcode's
    // per-bundle "Locale"/"Language"/"Language code" header, or a field
    // whose real header differs from its own configured Drupal label).
    foreach ($this->getExportLabelOverrides($entity_type_id, $bundle) as $target_key => $label) {
      if ($needle === mb_strtolower($label)) {
        return $target_key;
      }
    }

    // Check if any field has a property, which our import field process plugins
    // support, matching the source label.
    foreach ($field_defs as $field_name => $field_definition) {
      $plugin = $this->fieldProcessPluginManager->getInstance(['field_definition' => $field_definition]);
      $supported_properties = $plugin->getSupportedProperties($field_definition);

      foreach ($supported_properties as $property_name => $property_info) {
        if ($needle == mb_strtolower($property_info['label'])) {
          return "{$field_name}/{$property_name}";
        }
      }
    }

    // Disambiguate the langcode base field from other Language-labeled
    // fields when no export-header override applies.
    $entity_definition = $this->entityTypeManager->getDefinition($entity_type_id);
    $entity_keys = $entity_definition->getKeys();
    if (!empty($entity_keys['langcode']) && isset($field_defs[$entity_keys['langcode']])) {
      $langcode_label = mb_strtolower($field_defs[$entity_keys['langcode']]->getLabel() . ' (langcode)');
      if ($needle === $langcode_label) {
        return $entity_keys['langcode'];
      }
    }

    // Check for field label matches.
    if ($field_label_match = $this->searchFieldLabels($needle, $entity_type_id, $bundle)) {
      return $field_label_match;
    }

    // Check if we have a (case insensitive) field name match.
    if (isset($field_defs[$needle])) {
      return $needle;
    }

    return -1;
  }

}
