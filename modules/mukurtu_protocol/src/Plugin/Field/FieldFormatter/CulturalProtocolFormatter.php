<?php

namespace Drupal\mukurtu_protocol\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Entity\Exception\UndefinedLinkTemplateException;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\TypedData\TranslatableInterface;

/**
 * Plugin implementation of the 'cultural_protocol' formatter.
 *
 * @FieldFormatter(
 *   id = "cultural_protocol_formatter",
 *   label = @Translation("Cultural Protocol Formatter"),
 *   field_types = {
 *     "cultural_protocol"
 *   }
 * )
 */
class CulturalProtocolFormatter extends FormatterBase {
  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    // Heavily based on EntityReferenceLabelFormatter's viewElements().
    $elements = [];
    $output_as_link = TRUE;

    foreach ($items as $item) {
      $protocolIds = $item->getProtocolIds();
      foreach ($this->getEntitiesToView($protocolIds, $langcode) as $delta => $entity) {
        $label = $entity->label();

        if ($output_as_link && !$entity->isNew()) {
          try {
            $uri = $entity->toUrl();
          } catch (UndefinedLinkTemplateException $e) {
            // This exception is thrown by \Drupal\Core\Entity\Entity::urlInfo()
            // and it means that the entity type doesn't have a link template nor
            // a valid "uri_callback", so don't bother trying to output a link for
            // the rest of the referenced entities.
            $output_as_link = FALSE;
          }
        }
        if ($output_as_link && isset($uri) && !$entity->isNew()) {
          $elements[$delta] = [
            '#type' => 'link',
            '#title' => $label,
            '#url' => $uri,
            '#options' => $uri->getOptions(),
          ];

          if (!empty($items[$delta]->_attributes)) {
            $elements[$delta]['#options'] += ['attributes' => []];
            $elements[$delta]['#options']['attributes'] += $items[$delta]->_attributes;
            // Unset field item attributes since they have been included in the
            // formatter output and shouldn't be rendered in the field template.
            unset($items[$delta]->_attributes);
          }
        } else {
          $elements[$delta] = ['#plain_text' => $label];
        }
        $elements[$delta]['#cache']['tags'] = $entity->getCacheTags();
      }
    }
    return $elements;
  }

  /**
   * Returns the referenced protocol entities for display.
   * Heavily based on EntityReferenceFormatterBase's getEntitiesToView().
   *
   * The method takes care of:
   * - checking entity access,
   * - placing the entities in the language expected for display.
   *
   * @param $protocolIds
   *   The list of protocol ids.
   * @param string $langcode
   *   The language code of the referenced entities to display.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   The array of referenced entities to display, keyed by delta.
   */
  protected function getEntitiesToView($protocolIds, $langcode) {
    $entities = [];

    foreach ($protocolIds as $delta => $id) {
      $entity = \Drupal::entityTypeManager()->getStorage('protocol')->load($id);

      // Set the entity in the correct language for display.
      if ($entity instanceof TranslatableInterface) {
        $entity = \Drupal::service('entity.repository')->getTranslationFromContext($entity, $langcode);
      }

      $access = $this->checkAccess($entity);
      // Okay so I don't know if caching is important here, but the formatter
      // seems to work without it, so I'll leave it commented out for now.
      // TODO test caching
      // // Add the access result's cacheability, ::view() needs it.
      // $item->_accessCacheability = CacheableMetadata::createFromObject($access);
      if ($access->isAllowed()) {
        $entities[$delta] = $entity;
      }
    }

    return $entities;
  }

  /**
   * Checks access to the given entity.
   * Copied from EntityReferenceFormatterBase's checkAccess().
   *
   * By default, entity 'view' access is checked. However, a subclass can choose
   * to exclude certain items from entity access checking by immediately
   * granting access.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to check.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   A cacheable access result.
   */
  protected function checkAccess(EntityInterface $entity) {
    return $entity->access('view', NULL, TRUE);
  }
}
