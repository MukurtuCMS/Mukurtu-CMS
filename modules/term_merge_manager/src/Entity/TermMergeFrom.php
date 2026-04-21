<?php

namespace Drupal\term_merge_manager\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\user\UserInterface;

/**
 * Defines the Term merge from entity.
 *
 * @ingroup term_merge_manager
 *
 * @ContentEntityType(
 *   id = "term_merge_from",
 *   label = @Translation("Term merge from"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\term_merge_manager\TermMergeFromListBuilder",
 *     "views_data" = "Drupal\term_merge_manager\Entity\TermMergeFromViewsData",
 *
 *     "form" = {
 *       "default" = "Drupal\term_merge_manager\Form\TermMergeFromForm",
 *       "delete" = "Drupal\term_merge_manager\Form\TermMergeFromDeleteForm",
 *     },
 *     "access" = "Drupal\term_merge_manager\TermMergeFromAccessControlHandler",
 *     "route_provider" = {
 *       "html" = "Drupal\term_merge_manager\TermMergeFromHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "term_merge_from",
 *   admin_permission = "administer term merge from entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "name",
 *     "uuid" = "uuid",
 *     "uid" = "user_id",
 *     "langcode" = "langcode",
 *     "status" = "status",
 *     "tmiid" = "tmiid",
 *     "vid" = "vid",
 *     "name" = "name",
 *   },
 *   links = {
 *     "canonical" = "/admin/structure/term_merge_from/{term_merge_from}",
 *     "delete-form" = "/admin/structure/term_merge_from/{term_merge_from}/delete",
 *     "collection" = "/admin/structure/term_merge_from",
 *   },
 *   field_ui_base_route = "term_merge_from.settings"
 * )
 */
class TermMergeFrom extends ContentEntityBase implements TermMergeFromInterface {

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
   * Load Term with name and vocabulary.
   *
   * @param string $vid
   *   The term vid.
   * @param string $name
   *   The term name.
   *
   * @return bool|\Drupal\Core\Entity\EntityInterface|\Drupal\term_merge_manager\Entity\TermMergeFrom|null
   *   Returns TermMergeFrom entity or False.
   */
  public static function loadByVidName($vid, $name) {

    $query = \Drupal::entityQuery('term_merge_from');
    $query->condition('status', 1);
    $query->condition('vid', $vid);
    $query->condition('name', $name);
    $query->accessCheck(TRUE);

    $entity_id = $query->execute();

    if (empty($entity_id)) {
      return FALSE;
    }

    return TermMergeFrom::load(key($entity_id));
  }

  /**
   * Load Term with name and vocabulary.
   *
   * @param int $id
   *   The term id.
   *
   * @return bool|\Drupal\Core\Entity\EntityInterface|\Drupal\term_merge_manager\Entity\TermMergeFrom|null
   *   Returns TermMergeFrom entity or False.
   */
  public static function loadByMergeId($id) {

    $query = \Drupal::entityQuery('term_merge_from');
    $query->condition('status', 1);
    $query->condition('tmiid', $id);
    $query->accessCheck(TRUE);
    $entity_id = $query->execute();

    if (empty($entity_id)) {
      return FALSE;
    }

    return $entity_id;
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
   * The getIntoId function.
   */
  public function getIntoId() {
    $intoid = $this->get('tmiid')->value;
    if (empty($intoid)) {
      return FALSE;
    }
    $term = TermMergeInto::load($intoid);
    if (!is_object($term)) {
      return FALSE;
    }
    return $term->getTid();
  }

  /**
   * The getIntoName function.
   */
  public function getIntoName() {
    $intoid = $this->get('tmiid')->value;
    if (empty($intoid)) {
      return FALSE;
    }
    $term = TermMergeInto::load($intoid);
    if (!is_object($term)) {
      return FALSE;
    }
    return $term->getName();
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
      ->setDescription(t('The user ID of author of the Term merge from entity.'))
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
      ->setLabel(t('Name'))
      ->setDescription(t('The name of the Term merge from entity.'))
      ->setSettings([
        'max_length' => 50,
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

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Publishing status'))
      ->setDescription(t('A boolean indicating whether the Term merge from is published.'))
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

    $fields['tmiid'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Term Merge Into ID'))
      ->setDescription(t('The TermMergeManager ID to which the action is assigned.'));
    // @todo add constraint
    $fields['vid'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Vocabulary ID'))
      ->setDescription(t('The {taxonomy_vocabulary}.vid of the vocabulary to which the action is assigned. Additional for simpler queries'));

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Term Name'))
      ->setDescription(t('The term name.'))
      ->setSettings([
        'max_length' => 255,
        'text_processing' => 0,
      ]);

    return $fields;
  }

}
