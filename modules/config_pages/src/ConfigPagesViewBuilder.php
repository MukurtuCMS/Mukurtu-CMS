<?php

namespace Drupal\config_pages;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityViewBuilder;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Render\Element;

/**
 * View builder for config_pages entities.
 *
 * Overrides the default EntityViewBuilder to inject the correct #langcode
 * on field render arrays when a config page uses a language context.
 * This ensures that text filters (e.g. Linkit, Media embed) resolve links
 * in the correct language on multilingual sites.
 */
class ConfigPagesViewBuilder extends EntityViewBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildComponents(array &$build, array $entities, array $displays, $view_mode) {
    parent::buildComponents($build, $entities, $displays, $view_mode);

    foreach ($entities as $id => $entity) {
      $langcode = $this->extractLanguageFromContext($entity);
      if ($langcode === NULL) {
        continue;
      }

      foreach (Element::children($build[$id]) as $field_name) {
        $this->applyLangcodeToFieldItems($build[$id][$field_name], $langcode);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function viewField(FieldItemListInterface $items, $display_options = []) {
    $output = parent::viewField($items, $display_options);

    $entity = $items->getEntity();
    if ($entity instanceof ConfigPagesInterface) {
      $langcode = $this->extractLanguageFromContext($entity);
      if ($langcode !== NULL) {
        $this->applyLangcodeToFieldItems($output, $langcode);
      }
    }

    return $output;
  }

  /**
   * Extracts language code from a config page entity's context.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The config page entity.
   *
   * @return string|null
   *   The language code if found, or NULL.
   */
  protected function extractLanguageFromContext(EntityInterface $entity): ?string {
    if (!$entity instanceof ConfigPagesInterface) {
      return NULL;
    }

    if ($entity->get('context')->isEmpty()) {
      return NULL;
    }

    $context = $entity->get('context')->first()->get('value')->getString();
    $context = unserialize($context, ['allowed_classes' => FALSE]);
    if (!is_array($context)) {
      return NULL;
    }

    foreach ($context as $context_item) {
      if (isset($context_item['language'])) {
        return $context_item['language'];
      }
    }

    return NULL;
  }

  /**
   * Applies a langcode to all items in a field render array.
   *
   * @param array $field_build
   *   The field render array, passed by reference.
   * @param string $langcode
   *   The language code to set.
   */
  protected function applyLangcodeToFieldItems(array &$field_build, string $langcode): void {
    foreach (Element::children($field_build) as $delta) {
      $field_build[$delta]['#langcode'] = $langcode;
    }
  }

}
