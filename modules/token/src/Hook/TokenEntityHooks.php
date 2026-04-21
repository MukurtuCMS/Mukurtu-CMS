<?php

namespace Drupal\token\Hook;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\menu_link_content\MenuLinkContentInterface;
use Drupal\node\NodeInterface;

/**
 * Entity hooks for token module.
 */
final class TokenEntityHooks {

  use StringTranslationTrait;

  public function __construct(
    protected readonly ModuleHandlerInterface $moduleHandler,
  ) {
  }

  /**
   * Implements various entity CRUD hooks that require a cache clear.
   */
  #[Hook('date_format_insert')]
  #[Hook('date_format_delete')]
  #[Hook('field_config_presave')]
  #[Hook('field_config_delete')]
  public function entitySaveClearCache() {
    token_clear_cache();
  }

  /**
   * Implements hook_entity_type_alter().
   *
   * Because some token types to do not match their entity type names, we have to
   * map them to the proper type. This is purely for other modules' benefit.
   *
   * @see \Drupal\token\TokenEntityMapperInterface::getEntityTypeMappings()
   * @see http://drupal.org/node/737726
   */
  #[Hook('entity_type_alter')]
  public function entityTypeAlter(array &$entity_types) {
    $devel_exists = $this->moduleHandler->moduleExists('devel');
    /** @var \Drupal\Core\Entity\EntityTypeInterface[] $entity_types */
    foreach ($entity_types as $entity_type_id => $entity_type) {
      if (!$entity_type->get('token_type')) {
        // Fill in default token types for entities.
        switch ($entity_type_id) {
          case 'taxonomy_term':
          case 'taxonomy_vocabulary':
            // Stupid taxonomy token types...
            $entity_type->set('token_type', str_replace('taxonomy_', '', $entity_type_id));
            break;

          default:
            // By default the token type is the same as the entity type.
            $entity_type->set('token_type', $entity_type_id);
            break;
        }
      }
      if ($devel_exists && $entity_type->hasViewBuilderClass() && !$entity_type->hasLinkTemplate('token-devel')) {
        $entity_type->setLinkTemplate('token-devel', "/devel/token/{$entity_type_id}/{{$entity_type_id}}");
      }
    }
  }

  /**
   * Implements hook_ENTITY_TYPE_insert for node entities.
   */
  #[Hook('node_insert')]
  public function nodeInsert(NodeInterface $node): void {
    if ($node->hasField('menu_link') && $menu_link = $node->menu_link->entity) {
      if ($menu_link->link->uri == 'internal:/node/' . $node->uuid()) {
        // Update the menu-link to point to the now saved node.
        // @todo The menu link doesn't need to be changed in a workspace context.
        //   Fix this in https://www.drupal.org/project/drupal/issues/3511204.
        if (!$node->isDefaultRevision() && $node->hasLinkTemplate('latest-version')) {
          // If a new menu link is created while saving the node as a pending draft
          // (non-default revision), store it as a link to the latest version.
          // That ensures that there is a regular, valid link target that is
          // only visible to users with permission to view the latest version.
          $menu_link->get('link')->uri = 'internal:/node/' . $node->id() . '/latest';
        }
        else {
          $menu_link->link = 'entity:node/' . $node->id();
        }
        $menu_link->save();
      }
    }
  }

  /**
   * Implements hook_ENTITY_TYPE_presave() for menu_link_content.
   */
  #[Hook('menu_link_content_presave')]
  public function menuLinkContentPresave(MenuLinkContentInterface $menu_link_content): void {
    drupal_static_reset('token_menu_link_load_all_parents');
  }

  /**
   * Implements hook_field_info_alter().
   */
  #[Hook('field_info_alter')]
  public function fieldInfoAlter(&$info) {
    $defaults = [
      'taxonomy_term_reference' => 'taxonomy_term_reference_plain',
      'number_integer' => 'number_unformatted',
      'number_decimal' => 'number_unformatted',
      'number_float' => 'number_unformatted',
      'file' => 'file_url_plain',
      'image' => 'file_url_plain',
      'text' => 'text_default',
      'text_long' => 'text_default',
      'text_with_summary' => 'text_default',
      'list_integer' => 'list_default',
      'list_float' => 'list_default',
      'list_string' => 'list_default',
      'list_boolean' => 'list_default',
    ];
    foreach ($defaults as $field_type => $default_token_formatter) {
      if (isset($info[$field_type])) {
        $info[$field_type] += [
          'default_token_formatter' => $default_token_formatter,
        ];
      }
    }
  }

  /**
   * Implements hook_entity_base_field_info().
   */
  #[Hook('entity_base_field_info')]
  public function entityBaseFieldInfo(EntityTypeInterface $entity_type) {
    // We add a pseudo entity-reference field to track the menu entry created
    // from the node add/edit form so that tokens generated at that time that
    // reference the menu link can access the yet to be saved menu link.
    // @todo Revisit when https://www.drupal.org/node/2315773 is resolved.
    if ($entity_type->id() === 'node' && $this->moduleHandler->moduleExists('menu_ui')) {
      $fields['menu_link'] = BaseFieldDefinition::create('entity_reference')->setLabel($this->t('Menu link'))->setDescription($this->t('Computed menu link for the node (only available during node saving).'))->setRevisionable(TRUE)->setSetting('target_type', 'menu_link_content')->setClass('\Drupal\token\MenuLinkFieldItemList')->setTranslatable(TRUE)->setInternal(TRUE)->setDisplayOptions('view', [
        'label' => 'hidden',
        'region' => 'hidden',
      ])->setComputed(TRUE)->setDisplayOptions('form', [
        'region' => 'hidden',
      ]);
      return $fields;
    }
    return [];
  }

}
