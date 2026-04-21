<?php

namespace Drupal\redirect\Entity;

use Drupal\Component\Utility\Crypt;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\EntityPublishedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\link\LinkItemInterface;
use Drupal\redirect\Plugin\Field\FieldType\RedirectSourceItem;

/**
 * The redirect entity class.
 *
 * @ContentEntityType(
 *   id = "redirect",
 *   label = @Translation("Redirect"),
 *   bundle_label = @Translation("Redirect type"),
 *   handlers = {
 *     "list_builder" = "Drupal\Core\Entity\EntityListBuilder",
 *     "form" = {
 *       "default" = "Drupal\redirect\Form\RedirectForm",
 *       "delete" = "Drupal\redirect\Form\RedirectDeleteForm",
 *       "edit" = "Drupal\redirect\Form\RedirectForm"
 *     },
 *     "views_data" = "Drupal\redirect\RedirectViewsData",
 *     "storage_schema" = "\Drupal\redirect\RedirectStorageSchema"
 *   },
 *   base_table = "redirect",
 *   translatable = FALSE,
 *   admin_permission = "administer redirects",
 *   entity_keys = {
 *     "id" = "rid",
 *     "label" = "redirect_source",
 *     "uuid" = "uuid",
 *     "bundle" = "type",
 *     "langcode" = "language",
 *     "published" = "enabled",
 *   },
 *   links = {
 *     "canonical" = "/admin/config/search/redirect/edit/{redirect}",
 *     "delete-form" = "/admin/config/search/redirect/delete/{redirect}",
 *     "edit-form" = "/admin/config/search/redirect/edit/{redirect}",
 *   },
 *   constraints = {
 *     "RedirectUniqueHash" = {}
 *   }
 * )
 */
class Redirect extends ContentEntityBase implements EntityPublishedInterface {

  use EntityPublishedTrait;

  /**
   * Generates a unique hash for identification purposes.
   *
   * @param string $source_path
   *   Source path of the redirect.
   * @param array $source_query
   *   Source query as an array.
   * @param string $language
   *   Redirect language.
   *
   * @return string
   *   Base 64 hash.
   */
  public static function generateHash($source_path, array $source_query, $language) {
    $hash = [
      // Remove leading and trailing slashes, and convert to lowercase.
      'source' => trim(mb_strtolower($source_path), '/'),
      'language' => $language,
    ];

    if (!empty($source_query)) {
      $hash['source_query'] = $source_query;
    }
    redirect_sort_recursive($hash, 'ksort');
    return Crypt::hashBase64(serialize($hash));
  }

  /**
   * {@inheritdoc}
   */
  public static function preCreate(EntityStorageInterface $storage_controller, array &$values) {
    $values += [
      'type' => 'redirect',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage_controller) {
    $source = $this->get('redirect_source')->get(0);
    assert($source instanceof RedirectSourceItem);

    // Strip any trailing slashes as these are removed when looking for matching
    // redirects.
    // @see \Drupal\redirect\EventSubscriber\RedirectRequestSubscriber::onKernelRequestCheckRedirect()
    $source->path = rtrim($source->path, '/');

    // Get the language code directly from the field as language() might not
    // be up to date if the language was just changed.
    $this->set('hash', Redirect::generateHash($source->path, (array) $source->query, $this->get('language')->value));
  }

  /**
   * Sets the redirect language.
   *
   * @param string $language
   *   Language code.
   */
  public function setLanguage($language) {
    $this->set('language', $language);
  }

  /**
   * Sets the redirect status code.
   *
   * @param int $status_code
   *   The redirect status code.
   */
  public function setStatusCode($status_code) {
    $this->set('status_code', $status_code);
  }

  /**
   * Gets the redirect status code.
   *
   * @return int
   *   The redirect status code.
   */
  public function getStatusCode() {
    return $this->get('status_code')->value;
  }

  /**
   * Sets the redirect created datetime.
   *
   * @param int $datetime
   *   The redirect created datetime.
   */
  public function setCreated($datetime) {
    $this->set('created', $datetime);
  }

  /**
   * Gets the redirect created datetime.
   *
   * @return int
   *   The redirect created datetime.
   */
  public function getCreated() {
    return $this->get('created')->value;
  }

  /**
   * Sets the source URL data.
   *
   * @param string $path
   *   The base url of the source.
   * @param array $query
   *   Query arguments.
   */
  public function setSource($path, array $query = []) {
    $this->get('redirect_source')->set(0, ['path' => ltrim($path, '/'), 'query' => $query]);
  }

  /**
   * Gets the source URL data.
   *
   * @return array
   *   The source URL data.
   */
  public function getSource() {
    // Under some circumstances, such as when handling unsaved entities, the
    // source may have not been set yet. The source field would then be empty
    // and a fatal PHP error would occur.
    $source = $this->get('redirect_source');
    return $source->isEmpty() ? [] : $source->get(0)->getValue();
  }

  /**
   * Gets the source URL.
   *
   * @return string
   *   The source URL.
   */
  public function getSourceUrl() {
    $redirect_source = $this->get('redirect_source');
    if ($redirect_source->isEmpty()) {
      return '';
    }
    $source = $redirect_source->get(0);
    assert($source instanceof RedirectSourceItem);
    return $source->getUrl()->toString();
  }

  /**
   * Gets the source URL path with its query.
   *
   * @return string
   *   The source URL path, eventually with its query.
   */
  public function getSourcePathWithQuery() {
    $redirect_source = $this->get('redirect_source');
    if ($redirect_source->isEmpty()) {
      return '/';
    }
    $source = $redirect_source->get(0);
    assert($source instanceof RedirectSourceItem);
    $path = '/' . $source->path;
    if ($source->query) {
      $path .= '?' . UrlHelper::buildQuery($source->query);
    }
    return $path;
  }

  /**
   * Gets the redirect URL data.
   *
   * @return array
   *   The redirect URL data.
   */
  public function getRedirect() {
    $redirect = $this->get('redirect_redirect');
    return $redirect->isEmpty() ? [] : $redirect->get(0)->getValue();
  }

  /**
   * Sets the redirect destination URL data.
   *
   * @param string $url
   *   The base url of the redirect destination.
   * @param array $query
   *   Query arguments.
   * @param array $options
   *   The source url options.
   */
  public function setRedirect($url, array $query = [], array $options = []) {
    $uri = $url . ($query ? '?' . UrlHelper::buildQuery($query) : '');
    $uri = UrlHelper::isExternal($url) ? $uri : 'internal:/' . ltrim($uri, '/');
    $this->redirect_redirect->set(0, ['uri' => $uri, 'options' => $options]);
  }

  /**
   * Gets the redirect URL.
   *
   * @return \Drupal\Core\Url|null
   *   The redirect URL. NULL if the redirect is yet to be set.
   */
  public function getRedirectUrl() {
    $redirect = $this->get('redirect_redirect');
    if ($redirect->isEmpty()) {
      return NULL;
    }
    $redirect_item = $redirect->get(0);
    assert($redirect_item instanceof LinkItemInterface);
    return $redirect_item->getUrl();
  }

  /**
   * Gets the redirect URL options.
   *
   * @return array
   *   The redirect URL options.
   */
  public function getRedirectOptions() {
    $redirect = $this->get('redirect_redirect');
    return $redirect->isEmpty() ? [] : $redirect->options;
  }

  /**
   * Gets a specific redirect URL option.
   *
   * @param string $key
   *   Option key.
   * @param mixed $default
   *   Default value used in case option does not exist.
   *
   * @return mixed
   *   The option value.
   */
  public function getRedirectOption($key, $default = NULL) {
    $options = $this->getRedirectOptions();
    return $options[$key] ?? $default;
  }

  /**
   * Gets the current redirect entity hash.
   *
   * @return string|null
   *   The hash.
   */
  public function getHash() {
    return $this->get('hash')->value;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = [];
    $fields += static::publishedBaseFieldDefinitions($entity_type);

    $fields['rid'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Redirect ID'))
      ->setDescription(t('The redirect ID.'))
      ->setReadOnly(TRUE);

    $fields['uuid'] = BaseFieldDefinition::create('uuid')
      ->setLabel(t('UUID'))
      ->setDescription(t('The record UUID.'))
      ->setReadOnly(TRUE);

    $fields['hash'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Hash'))
      ->setSetting('max_length', 64)
      ->setDescription(t('The redirect hash.'));

    $fields['type'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Type'))
      ->setDescription(t('The redirect type.'));

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('User ID'))
      ->setDescription(t('The user ID of the node author.'))
      ->setDefaultValueCallback('\Drupal\redirect\Entity\Redirect::getCurrentUserId')
      ->setSettings([
        'target_type' => 'user',
      ]);

    $fields['redirect_source'] = BaseFieldDefinition::create('redirect_source')
      ->setLabel(t('From'))
      ->setDescription(t("Enter an internal Drupal path or path alias to redirect (e.g. %example1 or %example2). Fragment anchors (e.g. %anchor) are <strong>not</strong> allowed.", ['%example1' => 'node/123', '%example2' => 'taxonomy/term/123', '%anchor' => '#anchor']))
      ->setRequired(TRUE)
      ->setTranslatable(FALSE)
      ->setDisplayOptions('form', [
        'type' => 'redirect_link',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['redirect_redirect'] = BaseFieldDefinition::create('link')
      ->setLabel(t('To'))
      ->setRequired(TRUE)
      ->setTranslatable(FALSE)
      ->setSettings([
        'link_type' => LinkItemInterface::LINK_GENERIC,
        'title' => DRUPAL_DISABLED,
      ])
      ->setDisplayOptions('form', [
        'type' => 'link',
        'weight' => -4,
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['language'] = BaseFieldDefinition::create('language')
      ->setLabel(t('Language'))
      ->setDescription(t('The redirect language.'))
      ->setDisplayOptions('form', [
        'type' => 'language_select',
        'weight' => 2,
      ]);

    $fields['status_code'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Status code'))
      ->setDescription(t('The redirect status code.'))
      ->setDefaultValue(0);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The date when the redirect was created.'));

    // Use a more appropriate label for redirect status.
    $fields['enabled']
      ->setLabel(t('Enabled'))
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'settings' => [
          'display_label' => TRUE,
        ],
      ]);

    return $fields;
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

}
