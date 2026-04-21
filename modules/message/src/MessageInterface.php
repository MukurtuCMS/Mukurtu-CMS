<?php

namespace Drupal\message;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Language\Language;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface defining a Message entity.
 */
interface MessageInterface extends ContentEntityInterface, EntityChangedInterface, EntityOwnerInterface {

  /**
   * Set the message template.
   *
   * @param MessageTemplateInterface $template
   *   Message template.
   *
   * @return \Drupal\message\MessageInterface
   *   Returns the message object.
   */
  public function setTemplate(MessageTemplateInterface $template);

  /**
   * Get the message template.
   *
   * @return \Drupal\message\MessageTemplateInterface
   *   Returns the message object.
   */
  public function getTemplate();

  /**
   * Retrieve the time stamp of the message.
   *
   * @return int
   *   The Unix timestamp.
   */
  public function getCreatedTime();

  /**
   * Setting the timestamp.
   *
   * @param int $timestamp
   *   The Unix timestamp.
   *
   * @return \Drupal\message\MessageInterface
   *   Returns the message object.
   */
  public function setCreatedTime($timestamp);

  /**
   * Return the UUID.
   *
   * @return string
   *   Return the UUID.
   */
  public function getUuid();

  /**
   * Retrieve the message arguments.
   *
   * @return array
   *   The arguments of the message.
   */
  public function getArguments();

  /**
   * Set the arguments of the message.
   *
   * @param array $values
   *   Array of arguments.
   *
   * @code
   *   $values = [
   *     '@name_without_callback' => 'John doe',
   *     '@name_with_callback' => [
   *       'callback' => 'User::load',
   *       'arguments' => [1],
   *     ],
   *   ];
   * @endcode
   *
   * @return \Drupal\message\MessageInterface
   *   Returns the message object.
   */
  public function setArguments(array $values);

  /**
   * Set the language that should be used.
   *
   * @param string $language
   *   The language to load from the message template when fetching the text.
   */
  public function setLanguage($language);

  /**
   * Get the language of the Message.
   *
   * @return string
   *   The language of the message.
   */
  public function getLanguage();

  /**
   * Replace arguments with their placeholders.
   *
   * @param string $langcode
   *   The language code.
   * @param null|int $delta
   *   The delta of the message to return. If NULL all the message text will be
   *   returned.
   *
   * @return array
   *   The message text.
   */
  public function getText($langcode = Language::LANGCODE_NOT_SPECIFIED, $delta = NULL);

  /**
   * Delete multiple message.
   *
   * @param array $ids
   *   The messages IDs to delete.
   *
   * @deprecated in message:1.2.0 and is removed from message:2.0.0. Instead, each
   *   entity should call the ::delete() method explicitly.
   * @see https://www.drupal.org/project/message/issues/3091343
   */
  public static function deleteMultiple(array $ids);

  /**
   * Run a EFQ over messages from a given template.
   *
   * @param string $template
   *   The message template.
   *
   * @return array
   *   Array of message IDs.
   */
  public static function queryByTemplate($template);

  /**
   * Convert message contents to a string.
   *
   * @return string
   *   The message contents.
   */
  public function __toString();

}
