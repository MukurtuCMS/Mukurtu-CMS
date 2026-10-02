<?php

declare(strict_types=1);

namespace Drupal\mukurtu_core\Plugin\Field\FieldFormatter;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceLabelFormatter;
use Drupal\Core\Link;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mukurtu_core\Plugin\Field\FieldType\EntityReferenceRoleItem;

/**
 * Displays each referenced label followed by its role, if it has one.
 */
#[FieldFormatter(
  id: 'mukurtu_entity_reference_role_label',
  label: new TranslatableMarkup('Label with role'),
  field_types: ['mukurtu_entity_reference_role'],
)]
class EntityReferenceRoleLabelFormatter extends EntityReferenceLabelFormatter {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = parent::viewElements($items, $langcode);

    // Tag every name, on or off, so changing the setting refreshes pages.
    $settings_cache = (new CacheableMetadata())->addCacheTags(['config:' . EntityReferenceRoleItem::SETTINGS]);
    foreach ($elements as &$element) {
      CacheableMetadata::createFromRenderArray($element)->merge($settings_cache)->applyTo($element);
    }
    unset($element);
    if (!EntityReferenceRoleItem::rolesEnabled($this->fieldDefinition->getName())) {
      return $elements;
    }

    $role_ids = [];
    foreach ($elements as $delta => $element) {
      if (!empty($items[$delta]->role_target_id)) {
        $role_ids[$delta] = $items[$delta]->role_target_id;
      }
    }
    if (!$role_ids) {
      return $elements;
    }

    $entity_repository = \Drupal::service('entity.repository');
    $roles = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadMultiple(array_unique($role_ids));

    foreach ($role_ids as $delta => $role_id) {
      if (!isset($roles[$role_id])) {
        continue;
      }
      $role = $entity_repository->getTranslationFromContext($roles[$role_id], $langcode);
      $access = $role->access('view label', NULL, TRUE);
      $cacheability = CacheableMetadata::createFromRenderArray($elements[$delta])
        ->addCacheableDependency($role)
        ->addCacheableDependency($access);

      if ($access->isAllowed()) {
        $element = $elements[$delta];
        // A GeneratedLink is MarkupInterface, so the placeholder keeps it as a
        // link while the role label is escaped.
        if (($element['#type'] ?? NULL) === 'link') {
          $name = Link::fromTextAndUrl($element['#title'], $element['#url'])->toString();
          $cacheability = $cacheability->merge(BubbleableMetadata::createFromObject($name));
        }
        else {
          $name = $element['#plain_text'];
        }
        $elements[$delta] = [
          '#entity' => $element['#entity'],
          '#markup' => $this->t('@name (@role)', ['@name' => $name, '@role' => $role->label()]),
        ];
      }
      $cacheability->applyTo($elements[$delta]);
    }

    return $elements;
  }

}
