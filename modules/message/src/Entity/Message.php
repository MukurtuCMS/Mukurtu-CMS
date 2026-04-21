<?php

namespace Drupal\message\Entity;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Language\Language;
use Drupal\Core\Render\Markup;
use Drupal\message\MessageException;
use Drupal\message\MessageInterface;
use Drupal\message\MessageTemplateInterface;
use Drupal\user\UserInterface;

/**
 * Defines the Message entity class.
 *
 * @ContentEntityType(
 *   id = "message",
 *   label = @Translation("Message"),
 *   label_singular = @Translation("message"),
 *   label_plural = @Translation("messages"),
 *   label_count = @PluralTranslation(
 *     singular="@count message",
 *     plural="@count messages"
 *   ),
 *   bundle_label = @Translation("Message template"),
 *   module = "message",
 *   base_table = "message",
 *   data_table = "message_field_data",
 *   translatable = TRUE,
 *   bundle_entity_type = "message_template",
 *   entity_keys = {
 *     "id" = "mid",
 *     "bundle" = "template",
 *     "uuid" = "uuid",
 *     "langcode" = "langcode",
 *     "uid" = "uid"
 *   },
 *   bundle_keys = {
 *     "bundle" = "template"
 *   },
 *   handlers = {
 *     "view_builder" = "Drupal\message\MessageViewBuilder",
 *     "list_builder" = "Drupal\message\MessageListBuilder",
 *     "views_data" = "Drupal\message\MessageViewsData",
 *     "form" = {
 *       "default" = "Drupal\Core\Entity\ContentEntityForm",
 *       "delete-multiple-confirm" = "Drupal\Core\Entity\Form\DeleteMultipleForm",
 *     },
 *     "route_provider" = {
 *       "html" = "\Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider",
 *     },
 *   },
 *   field_ui_base_route = "entity.message_template.edit_form",
 *   admin_permission = "administer messages",
 *   links = {
 *     "delete-multiple-form" = "/admin/content/message/delete",
 *   }
 * )
 */
class Message extends ContentEntityBase implements MessageInterface {

  use EntityChangedTrait;

  /**
   * Holds the arguments of the message instance.
   *
   * @var array
   */
  protected $arguments;

  /**
   * The language to use when fetching text from the message template.
   *
   * @var string
   */
  protected $language = Language::LANGCODE_NOT_SPECIFIED;

  /**
   * {@inheritdoc}
   */
  public function setTemplate(MessageTemplateInterface $template) {
    $this->set('template', $template);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getTemplate() {
    // Normally config entities are automatically translated into the active
    // config override language, but since we might be sending messages in other
    // languages, we need to make sure we get the untranslated template.
    /** @var \Drupal\Core\Config\Entity\ConfigEntityStorageInterface $storage */
    $storage = \Drupal::entityTypeManager()->getStorage('message_template');
    return $storage->loadOverrideFree($this->bundle());
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
    return $this->get('uid')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwnerId() {
    return $this->getEntityKey('uid');
  }

  /**
   * {@inheritdoc}
   */
  public function setOwnerId($uid) {
    $this->set('uid', $uid);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwner(UserInterface $account) {
    $this->set('uid', $account->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getUuid() {
    return $this->get('uuid')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getArguments() {
    $arguments = $this->get('arguments')->first();
    return $arguments ? $arguments->getValue() : [];
  }

  /**
   * {@inheritdoc}
   */
  public function setArguments(array $values) {
    $this->set('arguments', $values);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields['mid'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Message ID'))
      ->setDescription(t('The message ID.'))
      ->setReadOnly(TRUE)
      ->setSetting('unsigned', TRUE);

    $fields['uuid'] = BaseFieldDefinition::create('uuid')
      ->setLabel(t('UUID'))
      ->setDescription(t('The message UUID'))
      ->setReadOnly(TRUE);

    $fields['template'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Template'))
      ->setDescription(t('The message template.'))
      ->setSetting('target_type', 'message_template')
      ->setReadOnly(TRUE);

    $fields['langcode'] = BaseFieldDefinition::create('language')
      ->setLabel(t('Language code'))
      ->setDescription(t('The message language code.'));

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Created by'))
      ->setDescription(t('The user that created the message.'))
      ->setSettings([
        'target_type' => 'user',
        'default_value' => 0,
      ])
      ->setDefaultValueCallback('Drupal\message\Entity\Message::getCurrentUserId')
      ->setTranslatable(TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created on'))
      ->setDescription(t('The time that the message was created.'))
      ->setTranslatable(TRUE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the message was last edited.'))
      ->setTranslatable(TRUE);

    $fields['arguments'] = BaseFieldDefinition::create('map')
      ->setLabel(t('Arguments'))
      ->setDescription(t('Holds the arguments of the message in serialize format.'));

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getText($langcode = NULL, $delta = NULL) {
    if (!$message_template = $this->getTemplate()) {
      // Message template does not exist any more.
      // We don't throw an exception, to make sure we don't break sites that
      // removed the message template, so we silently ignore.
      return [];
    }
    if (!$langcode) {
      $langcode = $this->language;
    }

    $message_arguments = $this->getArguments();
    $message_template_text = $message_template->getText($langcode, $delta);

    $output = $this->processArguments($message_arguments, $message_template_text);

    $token_options = $message_template->getSetting('token options', []);
    if (!empty($token_options['token replace'])) {
      // Token should be processed.
      $output = $this->processTokens($output, !empty($token_options['clear']), $langcode);
    }

    return $output;
  }

  /**
   * Process the message given the arguments saved with it.
   *
   * @param array $arguments
   *   Array with the arguments.
   * @param array $output
   *   Array with the templated text saved in the message template.
   *
   * @return array
   *   The templated text, with the placeholders replaced with the actual value,
   *   if there are indeed arguments.
   */
  protected function processArguments(array $arguments, array $output) {
    // Check if we have arguments saved along with the message.
    if (empty($arguments)) {
      return $output;
    }

    foreach ($arguments as $key => $value) {
      if (is_array($value) && !empty($value['callback']) && is_callable($value['callback'])) {

        // A replacement via callback function.
        $value += ['pass message' => FALSE];

        if ($value['pass message']) {
          // Pass the message object as-well.
          $value['arguments']['message'] = $this;
        }

        $arguments[$key] = call_user_func_array($value['callback'], $value['arguments']);
      }
    }

    foreach ($output as $key => $value) {
      $output[$key] = new FormattableMarkup($value, $arguments);
    }

    return $output;
  }

  /**
   * Replace placeholders with tokens.
   *
   * @param array $output
   *   The templated text to be replaced.
   * @param bool $clear
   *   Determine if unused token should be cleared.
   * @param bool $langcode
   *   The language in which the message is being rendered.
   *
   * @return array
   *   The output with placeholders replaced with the token value,
   *   if there are indeed tokens.
   */
  protected function processTokens(array $output, $clear, $langcode) {
    $options = [
      'langcode' => $langcode,
      'clear' => $clear,
    ];

    foreach ($output as $key => $value) {
      $output[$key] = \Drupal::token()
        ->replace($value, ['message' => $this], $options);
    }

    return $output;
  }

  /**
   * {@inheritdoc}
   */
  public function save() {
    $token_options = !empty($this->data['token options']) ? $this->data['token options'] : [];

    $tokens = [];

    // Require a valid template when saving.
    if (!$this->getTemplate()) {
      throw new MessageException('No valid template found.');
    }

    // Handle hard coded arguments.
    foreach ($this->getTemplate()->getText() as $text) {
      preg_match_all('/[@|%|\!]\{([a-z0-9:_\-]+?)\}/i', $text, $matches);

      foreach ($matches[1] as $delta => $token) {
        $output = \Drupal::token()->replace('[' . $token . ']', ['message' => $this], $token_options);
        if ($output != '[' . $token . ']') {
          // Token was replaced and token sanitizes.
          $argument = $matches[0][$delta];
          $tokens[$argument] = Markup::create($output);
        }
      }
    }

    $arguments = $this->getArguments();
    $this->setArguments(array_merge($tokens, $arguments));

    return parent::save();
  }

  /**
   * {@inheritdoc}
   *
   * @deprecated in message:1.2.0 and is removed from message:2.0.0. Instead, each
   *   entity should call the ::delete() method explicitly.
   * @see https://www.drupal.org/project/message/issues/3091343
   */
  public static function deleteMultiple(array $ids) {
    @trigger_error('\Drupal\message\Entity\Message::deleteMultiple is deprecated in message:1.2.0 and is removed from message:2.0.0. Instead, each entity should call the ::delete() method explicitly. See https://www.drupal.org/project/message/issues/3091343', E_USER_DEPRECATED);
    $storage = \Drupal::entityTypeManager()->getStorage('message');
    $entities = $storage->loadMultiple($ids);
    $storage->delete($entities);
  }

  /**
   * {@inheritdoc}
   */
  public static function queryByTemplate($template) {
    return \Drupal::entityQuery('message')
      ->accessCheck(TRUE)
      ->condition('template', $template)
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function __toString() {
    return trim(implode("\n", $this->getText()));
  }

  /**
   * {@inheritdoc}
   */
  public function setLanguage($language) {
    $this->language = $language;
  }

  /**
   * {@inheritdoc}
   */
  public function getLanguage() {
    return $this->language;
  }

  /**
   * Default value callback for 'uid' base field definition.
   *
   * @see ::baseFieldDefinitions()
   *
   * @return array
   *   An array of default values.
   */
  public static function getCurrentUserId() {
    return [\Drupal::currentUser()->id()];
  }

  /**
   * {@inheritdoc}
   */
  public function label() {
    $params = [
      '@id' => $this->id(),
      '@template' => $this->getTemplate()->label(),
    ];
    return t('Message ID @id (template: @template)', $params);
  }

}
