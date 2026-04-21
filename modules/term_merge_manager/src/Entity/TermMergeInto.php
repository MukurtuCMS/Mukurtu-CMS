<?php

namespace Drupal\term_merge_manager\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\user\UserInterface;

/**
 * Defines the Term merge into entity.
 *
 * @ingroup term_merge_manager
 *
 * @ContentEntityType(
 *   id = "term_merge_into",
 *   label = @Translation("Term merge into"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\term_merge_manager\TermMergeIntoListBuilder",
 *     "views_data" = "Drupal\term_merge_manager\Entity\TermMergeIntoViewsData",
 *
 *     "form" = {
 *       "default" = "Drupal\term_merge_manager\Form\TermMergeIntoForm",
 *       "delete" = "Drupal\term_merge_manager\Form\TermMergeIntoDeleteForm",
 *     },
 *     "access" = "Drupal\term_merge_manager\TermMergeIntoAccessControlHandler",
 *     "route_provider" = {
 *       "html" = "Drupal\term_merge_manager\TermMergeIntoHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "term_merge_into",
 *   admin_permission = "administer term merge into entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "uid" = "user_id",
 *     "vid" = "vid",
 *     "tid" = "tid",
 *     "langcode" = "langcode",
 *     "status" = "status",
 *   },
 *   links = {
 *     "canonical" = "/admin/structure/term_merge_into/{term_merge_into}",
 *     "delete-form" = "/admin/structure/term_merge_into/{term_merge_into}/delete",
 *     "collection" = "/admin/structure/term_merge_into",
 *   },
 *   field_ui_base_route = "term_merge_into.settings"
 * )
 */
class TermMergeInto extends ContentEntityBase implements TermMergeIntoInterface {

  use EntityChangedTrait;

  /**
   * {@inheritdoc}
   */
  public static function preCreate(EntityStorageInterface $storage_controller, array &$values) {
    parent::preCreate($storage_controller, $values);
    $values += [
      'user_id' => \Drupal::currentUser()->id(),
    ];
  }

  /**
   * The loadIdByTid function.
   */
  public static function loadIdByTid($tid) {

    $query = \Drupal::entityQuery('term_merge_into');
    $query->condition('status', 1);
    $query->condition('tid', $tid);
    $query->accessCheck(TRUE);
    $entity_id = $query->execute();

    if (empty($entity_id)) {
      return FALSE;
    }

    return key($entity_id);
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
  public function getVid() {
    return $this->get('vid')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setVid($vid) {
    $this->set('vid', $vid);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getTid() {
    return $this->get('tid')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setTid($tid) {
    $this->set('tid', $tid);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    $tid = $this->getTid();
    if (empty($tid)) {
      return FALSE;
    }
    $term = Term::load($tid);
    if (!is_object($term)) {
      return FALSE;
    }
    return $term->getName();
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
   * {@inheritdoc}
   */
  public function isPublished() {
    return (bool) $this->getEntityKey('status');
  }

  /**
   * {@inheritdoc}
   */
  public function setPublished($published) {
    $this->set('status', $published ? TRUE : FALSE);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['user_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Authored by'))
      ->setDescription(t('The user ID of author of the Term merge into entity.'))
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

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Publishing status'))
      ->setDescription(t('A boolean indicating whether the Term merge into is published.'))
      ->setDefaultValue(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'weight' => -3,
      ]);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the entity was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the entity was last edited.'));

    $fields['vid'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Vocabulary ID'))
      ->setDescription(t('The {taxonomy_vocabulary}.vid of the vocabulary to which the action is assigned.'));

    $fields['tid'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Term ID'))
      ->setDescription(t('The term ID the items get merged into.'));

    return $fields;
  }

}
