<?php

namespace Drupal\mukurtu_protocol\Entity;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EditorialContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityPublishedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Session\AccountInterface;
use Drupal\media\MediaInterface;
use Drupal\og\Entity\OgRole;
use Drupal\og\Og;
use Drupal\og\OgMembershipInterface;
use Drupal\user\UserInterface;
use Exception;

/**
 * Defines the Community entity.
 *
 * @ingroup mukurtu_protocol
 *
 * @ContentEntityType(
 *   id = "community",
 *   label = @Translation("Community"),
 *   label_collection = @Translation("Communities"),
 *   label_singular = @Translation("Community"),
 *   label_plural = @Translation("Communities"),
 *   label_count = @PluralTranslation(
 *     singular = "@count community",
 *     plural = "@count communities",
 *   ),
 *   handlers = {
 *     "storage" = "Drupal\mukurtu_protocol\CommunityStorage",
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\mukurtu_protocol\CommunityListBuilder",
 *     "views_data" = "Drupal\mukurtu_protocol\Entity\CommunityViewsData",
 *     "translation" = "Drupal\mukurtu_protocol\CommunityTranslationHandler",
 *
 *     "form" = {
 *       "default" = "Drupal\mukurtu_protocol\Form\CommunityForm",
 *       "add" = "Drupal\mukurtu_protocol\Form\CommunityAddForm",
 *       "edit" = "Drupal\mukurtu_protocol\Form\CommunityForm",
 *       "delete" = "Drupal\mukurtu_protocol\Form\CommunityDeleteForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\mukurtu_protocol\CommunityHtmlRouteProvider",
 *     },
 *     "access" = "Drupal\mukurtu_protocol\CommunityAccessControlHandler",
 *   },
 *   base_table = "community",
 *   data_table = "community_field_data",
 *   revision_table = "community_revision",
 *   revision_data_table = "community_field_revision",
 *   translatable = TRUE,
 *   admin_permission = "administer community entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "revision" = "vid",
 *     "label" = "name",
 *     "uuid" = "uuid",
 *     "uid" = "user_id",
 *     "langcode" = "langcode",
 *     "published" = "status",
 *   },
 *   revision_metadata_keys = {
 *     "revision_user" = "revision_user",
 *     "revision_created" = "revision_created",
 *     "revision_log_message" = "revision_log"
 *   },
 *   links = {
 *     "canonical" = "/communities/community/{community}",
 *     "add-form" = "/communities/community/add",
 *     "edit-form" = "/communities/community/{community}/edit",
 *     "delete-form" = "/communities/community/{community}/delete",
 *     "version-history" = "/communities/community/{community}/revisions",
 *     "revision" = "/communities/community/{community}/revisions/{community_revision}/view",
 *     "revision_revert" = "/communities/community/{community}/revisions/{community_revision}/revert",
 *     "revision_delete" = "/communities/community/{community}/revisions/{community_revision}/delete",
 *     "translation_revert" = "/communities/community/{community}/revisions/{community_revision}/revert/{langcode}",
 *     "collection" = "/dashboard/communities",
 *   },
 *   field_ui_base_route = "community.settings"
 * )
 */
class Community extends EditorialContentEntityBase implements CommunityInterface {

  use EntityChangedTrait;
  use EntityPublishedTrait;

  /**
   * {@inheritdoc}
   */
  public static function preCreate(EntityStorageInterface $storage_controller, array &$values) {
    parent::preCreate($storage_controller, $values);

    // Don't add the current user as the community owner if we are currently
    // migrating. This would create the OG membership before we migrate
    // the memberships which will create a sync issue.
    $mukurtuMigrate = $values['mukurtu_migrate'] ?? FALSE;
    if (!$mukurtuMigrate) {
      $values += [
        'user_id' => \Drupal::currentUser()->id(),
      ];
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function urlRouteParameters($rel) {
    $uri_route_parameters = parent::urlRouteParameters($rel);

    if ($rel === 'revision_revert' && $this instanceof RevisionableInterface) {
      $uri_route_parameters[$this->getEntityTypeId() . '_revision'] = $this->getRevisionId();
    }
    elseif ($rel === 'revision_delete' && $this instanceof RevisionableInterface) {
      $uri_route_parameters[$this->getEntityTypeId() . '_revision'] = $this->getRevisionId();
    }

    return $uri_route_parameters;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);

    foreach (array_keys($this->getTranslationLanguages()) as $langcode) {
      $translation = $this->getTranslation($langcode);

      // If no owner has been set explicitly, make the anonymous user the owner.
      if (!$translation->getOwner()) {
        $translation->setOwnerId(0);
      }
    }

    // If no revision author has been set explicitly,
    // make the community owner the revision author.
    if (!$this->getRevisionUser()) {
      $this->setRevisionUserId($this->getOwnerId());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return $this->get('name')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setName($name) {
    $this->set('name', $name);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->get('field_description')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setDescription($description) {
    return $this->set('field_description', $description);
  }

  /**
   * {@inheritdoc}
   */
  public function getCommunityType() {
    return $this->get('field_community_type')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCommunityType($community_type) {
    return $this->set('field_community_type', $community_type);
  }

  /**
   * {@inheritdoc}
   */
  public function getSharingSetting() {
    return $this->get('field_access_mode')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setSharingSetting($sharing) {
    $this->set('field_access_mode', $sharing);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime() {
    return $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedTime($timestamp) {
    $this->set('created', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwner() {
    return $this->get('user_id')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwnerId() {
    return $this->get('user_id')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwnerId($uid) {
    $this->set('user_id', $uid);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwner(UserInterface $account) {
    $this->set('user_id', $account->id());
    return $this;
  }

  /**
   * {@inheritDoc}
   */
  public function getParentCommunity(): ?CommunityInterface {
    $entities = $this->get('field_parent_community')->referencedEntities();
    return $entities[0] ?? NULL;
  }

  /**
   * {@inheritDoc}
   */
  public function getThumbnailImage(): ?MediaInterface {
    return $this->get('field_thumbnail_image')->referencedEntities()[0] ?? NULL;
  }

  /**
   * {@inheritDoc}
   */
  public function setThumbnailImage(MediaInterface $image): CommunityInterface {
    return $this->set('field_thumbnail_image', $image->id());
  }

  /**
   * {@inheritDoc}
   */
  public function getBannerImage(): ?MediaInterface {
    return $this->get('field_banner_image')->referencedEntities()[0] ?? NULL;
  }

  /**
   * {@inheritDoc}
   */
  public function setBannerImage(MediaInterface $image): CommunityInterface {
    return $this->set('field_banner_image', $image->id());
  }

  /**
   * {@inheritDoc}
   */
  public function getChildCommunities() {
    return $this->get('field_child_communities')->referencedEntities() ?? [];
  }

  /**
   * {@inheritDoc}
   */
  public function isParentCommunity(): bool {
    $value = $this->get('field_child_communities')->getValue();
    if (!empty($value)) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * {@inheritDoc}
   */
  public function isChildCommunity(): bool {
    $query = \Drupal::entityQuery('community')
      ->condition('field_child_communities', $this->id(), '=')
      ->accessCheck(FALSE);
    $results = $query->execute();

    // Not in use at all.
    if (count($results) > 0) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function addMember(AccountInterface $account, $roles = []): MukurtuGroupInterface {
    $membership = Og::getMembership($this, $account, OgMembershipInterface::ALL_STATES);
    if (!$membership) {
      // Load OgRoles from role ids.
      $ogRoles = [];
      foreach ($roles as $role) {
        $ogRole = OgRole::getRole('community', 'community', $role);
        if ($ogRole) {
          $ogRoles[] = $ogRole;
        }
      }

      // Create the membership and add the roles.
      $membership = Og::createMembership($this, $account);
      $membership->setRoles($ogRoles);
      $membership->save();

      // @todo Do better, repeated too much, factor out.
      Cache::invalidateTags(["user:{$account->id()}", "user_roles"]);
      \Drupal::service('cache.render')->invalidateAll();
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function removeMember(AccountInterface $account): MukurtuGroupInterface {
    $membership = Og::getMembership($this, $account, OgMembershipInterface::ALL_STATES);
    if ($membership) {
      $membership->delete();
    }

    // @todo Do better, repeated too much, factor out.
    Cache::invalidateTags(["user:{$account->id()}", "user_roles"]);
    \Drupal::service('cache.render')->invalidateAll();

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setRoles(AccountInterface $account, $roles = []): MukurtuGroupInterface {
    $membership = Og::getMembership($this, $account, OgMembershipInterface::ALL_STATES);
    if ($membership) {
      // Load OgRoles from role ids.
      $ogRoles = [];
      foreach ($roles as $role) {
        $ogRole = OgRole::getRole('community', 'community', $role);
        if ($ogRole) {
          $ogRoles[] = $ogRole;
        }
      }

      // Add the roles.
      $membership->setRoles($ogRoles);
      $membership->save();

      // @todo Do better, repeated too much, factor out.
      Cache::invalidateTags(["user:{$account->id()}", "user_roles"]);
      \Drupal::service('cache.render')->invalidateAll();
    }

    return $this;
  }

  /**
   * {@inheritDoc}
   */
  public function getMembership(AccountInterface $account, array $states = [OgMembershipInterface::STATE_ACTIVE]): ?OgMembershipInterface {
    return Og::getMembership($this, $account, $states);
  }

  /**
   * {@inheritDoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage, $update);
    if (!$update) {
      // Update org list for brand new communities.
      $config = \Drupal::service('config.factory')->getEditable('mukurtu_protocol.community_organization');
      $org = $config->get('organization') ?? [];

      // Put the new community at the end of the list (highest weight).
      $weight = 0;
      foreach ($org as $id => $settings) {
        if ($settings['parent'] == 0 && $settings['weight'] > $weight) {
          $weight = $settings['weight'];
        }
      }
      $weight += 1;

      // Save the updated list.
      $org[$this->id()] = ['parent' => 0, 'weight' => $weight];
      $config->set('organization', $org)->save();
    }
  }

  /**
   * {@inheritDoc}
   */
  public static function postDelete(EntityStorageInterface $storage, array $entities) {
    parent::postDelete($storage, $entities);

    // Find communities that were deleted.
    $communities = [];
    foreach ($entities as $entity) {
      if ($entity instanceof CommunityInterface) {
        $communities[] = $entity;
      }
    }

    // Remove them from the org list.
    if (!empty($communities)) {
      $communityIds = array_map(fn($c) => $c->id(), $communities);
      $config = \Drupal::service('config.factory')->getEditable('mukurtu_protocol.community_organization');
      $org = $config->get('organization') ?? [];
      foreach ($org as $id => $settings) {
        // Remove delete community.
        if (in_array($id, $communityIds)) {
          unset($org[$id]);
          continue;
        }

        // Change any deleted parent reference to 0 which puts them back at
        // root level. We have protection against deleting parent communities
        // in the community access control handler so this should never trigger
        // but we'll have this just in case.
        if (in_array($settings['parent'], $communityIds)) {
          $org[$id]['parent'] = 0;
        }
      }

      // Save the updated list.
      $config->set('organization', $org)->save();
    }

  }

  /**
   * {@inheritdoc}
   */
  public function getProtocols() {
    try {
      $storage = $this->entityTypeManager()->getStorage('protocol');
    } catch (Exception $e) {
      return [];
    }
    $query = $storage->getQuery();
    $result = $query->condition('field_communities', $this->id(), '=')
      ->accessCheck(FALSE)
      ->execute();

    $protocols = [];
    if (!empty($result)) {
      $protocols = $this->entityTypeManager()->getStorage('protocol')->loadMultiple($result);
    }
    return $protocols;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    // Add the published field.
    $fields += static::publishedBaseFieldDefinitions($entity_type);

    $fields['user_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Authored by'))
      ->setDescription(t('The user ID of author of the Community entity.'))
      ->setRevisionable(TRUE)
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setTranslatable(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'author',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 5,
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => '60',
          'autocomplete_type' => 'tags',
          'placeholder' => '',
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Community name'))
      ->setDescription(t('The name of the Community.'))
      ->setRevisionable(TRUE)
      ->setSettings([
        'max_length' => 255,
        'text_processing' => 0,
      ])
      ->setDefaultValue('')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => -4,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -4,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setRequired(TRUE);

    $fields['field_description'] = BaseFieldDefinition::create('text_with_summary')
      ->setLabel(t('Description'))
      ->setDisplayOptions('view', [
        'label' => 'visible',
        'type' => 'text_default',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'text_textarea_with_summary',
        'weight' => 0,
        'rows' => 6,
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);


    $fields['field_parent_community'] = BaseFieldDefinition::create('entity_reference')
      ->setName('field_parent_community')
      ->setLabel(t('Parent Community'))
      ->setDescription('')
      ->setComputed(TRUE)
      ->setClass('Drupal\mukurtu_protocol\Plugin\Field\CommunityParentCommunityItemList')
      ->setSetting('target_type', 'community')
      ->setSetting('handler', 'default:community')
      ->setRequired(FALSE)
      ->setCardinality(1)
      ->setTranslatable(FALSE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', ['label' => 'hidden']);

    $fields['field_child_communities'] = BaseFieldDefinition::create('entity_reference')
      ->setName('field_child_communities')
      ->setLabel(t('Sub-communities'))
      ->setComputed(TRUE)
      ->setClass('Drupal\mukurtu_protocol\Plugin\Field\CommunityChildCommunitiesItemList')
      ->setSetting('target_type', 'community')
      ->setSetting('handler', 'default:community')
      ->setRequired(FALSE)
      ->setCardinality(-1)
      ->setTranslatable(FALSE)
      ->setDisplayOptions('view', [
        'label' => 'visible',
        'type' => 'string',
        'weight' => 4,
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['status']->setDescription(t('A boolean indicating whether the Community is published.'))
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'weight' => 30,
      ]);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the entity was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the entity was last edited.'));

    $fields['revision_translation_affected'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Revision translation affected'))
      ->setDescription(t('Indicates if the last edit of a translation belongs to current revision.'))
      ->setReadOnly(TRUE)
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE);

    $fields['field_access_mode'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Sharing Protocol'))
      ->setDescription(t('Open - Your community page is visible to all visitors of your site. Any items under open protocols are also accessible.<br>
Strict - Your community page is invisible to all site users who are not members or your community. All protocols created within this community are inaccessible to users outside of this community.'))
      ->setSettings([
        'allowed_values' => [
          'strict' => 'Strict',
          'open' => 'Open',
        ],
      ])
      ->setDisplayOptions('view', [
        'label' => 'visible',
        'type' => 'list_default',
        'weight' => 10,
      ])
      ->setDisplayOptions('form', [
        'type' => 'options_buttons',
        'weight' => 10,
      ])
      ->setDefaultValue('strict')
      ->setCardinality(1)
      ->setRequired(TRUE)
      ->setRevisionable(TRUE)
      ->setTranslatable(FALSE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['field_community_type'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Community Type'))
      ->setDescription(t('Indicates the type of community.'))
      ->setSetting('target_type', 'taxonomy_term')
      ->setSetting('handler', 'default:taxonomy_term')
      ->setSetting('handler_settings',
        [
          'target_bundles' => [
            'community_type' => 'community_type',
          ],
        ],
      )
      ->setRequired(FALSE)
      ->setCardinality(1)
      ->setDisplayOptions('view', [
        'label' => 'visible',
        'type' => 'string',
        'weight' => 4,
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['field_featured_content'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Featured Content'))
      ->setSetting('target_type', 'node')
      ->setSetting('handler', 'default:node')
      ->setSetting('handler_settings', [
        'auto_create' => FALSE,
      ])
      ->setRequired(FALSE)
      ->setCardinality(-1)
      ->setTranslatable(FALSE)
      ->setDisplayOptions('view', [
        'label' => 'visible',
        'type' => 'string',
        'weight' => 20,
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['field_banner_image'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Banner Image'))
      ->setSetting('target_type', 'media')
      ->setSetting('handler', 'default:media')
      ->setSetting('handler_settings', [
        'auto_create' => FALSE,
        'target_bundles' => ['image' => 'image'],
      ])
      ->setRequired(FALSE)
      ->setCardinality(-1)
      ->setTranslatable(FALSE)
      ->setDisplayOptions('view', [
        'label' => 'visible',
        'type' => 'string',
        'weight' => 20,
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['field_thumbnail_image'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Thumbnail Image'))
      ->setSetting('target_type', 'media')
      ->setSetting('handler', 'default:media')
      ->setSetting('handler_settings', [
        'auto_create' => FALSE,
        'target_bundles' => ['image' => 'image'],
      ])
      ->setRequired(FALSE)
      ->setCardinality(-1)
      ->setTranslatable(FALSE)
      ->setDisplayOptions('view', [
        'label' => 'visible',
        'type' => 'string',
        'weight' => 20,
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    return $fields;
  }

}
