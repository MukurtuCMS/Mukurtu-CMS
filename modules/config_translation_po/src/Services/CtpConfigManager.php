<?php

namespace Drupal\config_translation_po\Services;

use Drupal\Component\Gettext\PoItem;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\TraversableTypedDataInterface;
use Drupal\locale\LocaleConfigManager;

/**
 * Manages translatable configuration items for exporting translations.
 *
 * @package Drupal\config_translation_po\Services
 */
class CtpConfigManager extends LocaleConfigManager {

  /**
   * The translatable ConfigPoItems for export.
   *
   * @var \Drupal\config_translation_po\Component\ConfigPoItem[]
   */
  protected $translatableElements = [];

  /**
   * The needed language id.
   *
   * @var string
   */
  protected $langcode;

  /**
   * Gets array of translated strings for Locale translatable configuration.
   *
   * @param string $name
   *   Configuration object name.
   *
   * @return array
   *   Array of Locale translatable elements of the default configuration in
   *   $name.
   */
  public function getTranslatableConfig($name) {
    // Create typed configuration wrapper based on install storage data.
    $data = $this->configStorage->read($name);
    $typed_config = $this->typedConfigManager->createFromNameAndData($name, $data);
    if ($typed_config instanceof TraversableTypedDataInterface) {
      return $this->getTranslatableData($typed_config);
    }
    return [];
  }

  /**
   * Gets configuration names associated with components.
   *
   * @param array $components
   *   (optional) Array of component lists indexed by type. If not present or it
   *   is an empty array, it will update all components.
   *
   * @return array
   *   Array of configuration object names.
   */
  public function getComponentNames(array $components = []) {
    $components = array_filter($components);
    if ($components) {
      $names = [];
      foreach ($components as $type => $list) {
        // InstallStorage::getComponentNames returns a list of folders keyed by
        // config name.
        $names = array_merge($names, $this->configStorage->getComponentNames($type, $list));
      }
      return $names;
    }
    else {
      return $this->configStorage->listAll();
    }
  }

  /**
   * Updates all configuration translations for the names / languages provided.
   *
   * To be used when interface translation changes result in the need to update
   * configuration translations to keep them in sync.
   *
   * @param array $names
   *   Array of names of configuration objects to update.
   * @param array $langcodes
   *   (optional) Array of language codes to update. Defaults to all
   *   configurable languages.
   *
   * @return int
   *   Total number of configuration override and active configuration objects
   *   updated (saved or removed).
   */
  public function updateConfigTranslations(array $names, array $langcodes = []) {
    $langcodes = $langcodes ? $langcodes : array_keys($this->languageManager->getLanguages());
    $count = 0;
    foreach ($names as $name) {
      $translatable = $this->getTranslatableConfig($name);
      if (empty($translatable)) {
        // If there is nothing translatable in this configuration or not
        // supported, skip it.
        continue;
      }

      $this->setContext($translatable, [$name]);
      $active_langcode = $this->getActiveConfigLangcode($name);
      $active = $this->configStorage->read($name);

      foreach ($langcodes as $langcode) {
        $processed = $this->processTranslatableData($name, $active, $translatable, $langcode);
        // If the language code is not the same as the active storage
        // language, we should update the configuration override.
        if ($langcode != $active_langcode) {
          $override = $this->languageManager->getLanguageConfigOverride($langcode, $name);
          // Filter out locale managed configuration keys so that translations
          // removed from Locale will be reflected in the config override.
          $data = $this->filterOverride($override->get(), $translatable);
          if (!empty($processed)) {
            // Merge in the Locale managed translations with existing data.
            $data = NestedArray::mergeDeepArray([$data, $processed], TRUE);
          }
          if (empty($data) && !$override->isNew()) {
            // The configuration override contains Locale overrides that no
            // longer exist.
            $this->deleteTranslationOverride($name, $langcode);
            $count++;
          }
          elseif (!empty($data)) {
            // Update translation data in configuration override.
            $this->saveTranslationOverride($name, $langcode, $data);
            $count++;
          }
        }
        elseif (locale_is_translatable($langcode)) {
          // If the language code is the active storage language, we should
          // update. If it is English, we should only update if English is also
          // translatable.
          $active = NestedArray::mergeDeepArray([$active, $processed], TRUE);
          $this->saveTranslationActive($name, $active);
          $count++;
        }
      }
    }
    return $count;
  }

  /**
   * Set context for translatable element.
   *
   * @param array $translatable
   *   The translatable elements.
   * @param array $context
   *   The context array.
   */
  protected function setContext(array &$translatable, array $context = []) {
    foreach ($translatable as $key => &$value) {
      $merge_context = $context;
      $merge_context[] = $key;
      if ($value instanceof TranslatableMarkup) {
        if (empty($value->getOption('context'))) {
          $context_string = implode(':', $merge_context);
          /** @var \Drupal\Core\StringTranslation\TranslatableMarkup $value */
          $untranslatable = $value->getUntranslatedString();
          $arguments = $value->getArguments();
          $options = $value->getOptions();
          $options['context'] = $context_string;
          $translatable[$key] = new TranslatableMarkup($untranslatable, $arguments, $options);
        }
      }
      else {
        $this->setContext($value, $merge_context);
      }
    }
  }

  /**
   * Export all configuration translations for the names / languages provided.
   *
   * To be used when interface translation changes result in the need to update
   * configuration translations to keep them in sync.
   *
   * @param array $names
   *   Array of names of configuration objects to update.
   * @param array $langcodes
   *   (optional) Array of language codes to update. Defaults to all
   *   configurable languages.
   *
   * @return array
   *   The ConfigPoItem list.
   */
  public function exportConfigTranslations(array $names, array $langcodes = []) {
    foreach ($names as $name) {
      $translatable = $this->getTranslatableConfig($name);
      if (empty($translatable)) {
        // If there is nothing translatable in this configuration or not
        // supported, skip it.
        continue;
      }
      // Add context to translatable elements from configuration.
      $this->setContext($translatable, [$name]);
      $active_langcode = $this->getActiveConfigLangcode($name);
      $active = $this->configStorage->read($name);

      foreach ($langcodes as $this->langcode) {
        $processed = $this
          ->processTranslatableData($name, $active, $translatable, $this->langcode);
        // If the language code is not the same as the active storage
        // language, we should update the configuration override.
        if ($this->langcode != $active_langcode) {
          $override = $this->languageManager->getLanguageConfigOverride($this->langcode, $name);
          // Filter out locale managed configuration keys so that translations
          // removed from Locale will be reflected in the config override.
          $data = $this->filterOverride($override->get(), $translatable);
          if (!empty($processed)) {
            // Merge in the Locale managed translations with existing data.
            $data = NestedArray::mergeDeepArray([$data, $processed], TRUE);
            if (empty($data)) {
              continue;
            }
            $this->setTranslatableElements($translatable, $data, [$name]);
          }
          if ($this->langcode === 'system') {
            $data = $translatable;
            $this->setTranslatableElements($translatable, $data, [$name]);
          }
        }
        else {
          $data = $translatable;
          $this->setTranslatableElements($translatable, $data, [$name]);
        }
      }
    }

    return $this->translatableElements;
  }

  /**
   * Set the ConfigPoItems from translatable element.
   *
   * @param array $translatable
   *   The translatable element.
   * @param array $data
   *   The translated element.
   * @param array $context
   *   The context array.
   */
  protected function setTranslatableElements(array $translatable, array $data, array $context = []) {
    foreach ($data as $key => $value) {
      if (isset($translatable[$key])) {
        $merge_context = $context;
        $merge_context[] = $key;
        if (is_array($value)) {
          $this->setTranslatableElements($translatable[$key], $value, $merge_context);
        }
        else {
          if ($value instanceof TranslatableMarkup) {
            $value = $value->getUntranslatedString();
          }
          $this->preparePoItem($translatable[$key], $value, $merge_context);
        }
      }
    }
  }

  /**
   * Prepare ConfigPoItem.
   *
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|string $source
   *   The source element.
   * @param string $data
   *   The translated text.
   * @param array $comment
   *   The comment array.
   */
  protected function preparePoItem($source, string $data, array $comment) {
    if ($source instanceof TranslatableMarkup) {
      $source = $source->getUntranslatedString();
    }
    $excludes = $this->getExludes();
    if (empty($source) || in_array($source, $excludes)) {
      return;
    }
    $context = implode(':', $comment);
    $po_item = new PoItem();
    $po_item->setLangcode($this->langcode);
    $po_item->setContext($context);
    $po_item->setSource($source);
    $po_item->setTranslation($this->langcode !== 'system' ? $data : '');
    $this->translatableElements[$context] = $po_item;
  }

  /**
   * Return exclude strings.
   *
   * @return string[]
   *   The exluded string array.
   */
  protected function getExludes() {
    return [
      '(Empty)',
      '‹‹',
      '››',
      ', ',
    ];
  }

  /**
   * Process the translatable data array with a given language.
   *
   * If the given language is translatable, will return the translated copy
   * which will only contain strings that had translations. If the given
   * language is English and is not translatable, will return a simplified
   * array of the English source strings only.
   *
   * @param string $name
   *   The configuration name.
   * @param array $active
   *   The active configuration data.
   * @param array|\Drupal\Core\StringTranslation\TranslatableMarkup[] $translatable
   *   The translatable array structure. A nested array matching the exact
   *   structure under of the default configuration for $name with only the
   *   elements that are translatable wrapped into a TranslatableMarkup.
   * @param string $langcode
   *   The language code to process the array with.
   *
   * @return array
   *   Processed translatable data array. Will only contain translations
   *   different from source strings or in case of untranslatable English, the
   *   source strings themselves.
   *
   * @see self::getTranslatableData()
   */
  protected function processTranslatableData($name, array $active, array $translatable, $langcode) {
    $translated = [];
    foreach ($translatable as $key => $item) {
      if (!isset($active[$key])) {
        continue;
      }
      if (is_array($item)) {
        // Only add this key if there was a translated value underneath.
        $value = $this->processTranslatableData($name, $active[$key], $item, $langcode);
        if (!empty($value)) {
          $translated[$key] = $value;
        }
      }
      else {
        if (locale_is_translatable($langcode)) {
          $value = $this->translateString($name, $langcode, $item->getUntranslatedString(), $item->getOption('context'));
        }
        if (empty($value)) {
          $value = $item->getUntranslatedString();
        }
        if (!empty($value)) {
          $translated[$key] = $value;
        }
      }
    }
    return $translated;
  }

}
