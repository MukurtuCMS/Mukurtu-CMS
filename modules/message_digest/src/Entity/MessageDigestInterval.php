<?php

namespace Drupal\message_digest\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;

/**
 * Simple configuration for message digest intervals.
 *
 * @ConfigEntityType(
 *   id = "message_digest_interval",
 *   label = @Translation("Message digest interval"),
 *   config_prefix = "interval",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "langcode" = "langcode"
 *   },
 *   admin_permission = "administer message digest",
 *   config_export = {
 *     "id",
 *     "label",
 *     "interval",
 *     "langcode",
 *     "description"
 *   },
 *   handlers = {
 *     "form" = {
 *       "add" = "\Drupal\message_digest\Form\MessageDigestIntervalForm",
 *       "edit" = "\Drupal\message_digest\Form\MessageDigestIntervalForm",
 *       "delete" = "\Drupal\message_digest\Form\MessageDigestIntervalDeleteForm"
 *     },
 *     "list_builder" = "\Drupal\message_digest\MessageDigestIntervalListBuilder"
 *   },
 *   links = {
 *     "add-form": "/admin/config/message/message-digest/interval/add",
 *     "edit-form": "/admin/config/message/message-digest/manage/{message_digest_interval}",
 *     "delete-form": "/admin/config/message/message-digest/manage/{message_digest_interval}/delete",
 *     "collection": "/admin/config/message/message-digest"
 *   }
 * )
 */
class MessageDigestInterval extends ConfigEntityBase implements MessageDigestIntervalInterface {

  /**
   * The interval description.
   *
   * @var string
   */
  public $description;

  /**
   * The interval.
   *
   * @var string
   */
  public $interval;

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->description;
  }

  /**
   * {@inheritdoc}
   */
  public function getInterval() {
    return $this->interval;
  }

}
