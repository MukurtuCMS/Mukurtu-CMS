<?php

namespace Drupal\mukurtu_submissions\Plugin\Field\FieldWidget;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\Core\StringTranslation\ByteSizeMarkup;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\file\Entity\File;
use Drupal\mukurtu_media\MediaTypeExtensions;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plain multi-file upload widget for entity-reference-to-media fields on
 * the public submission form, in place of the full Media Library modal
 * (per-type tabs + a searchable grid of existing media, overkill for a
 * one-time anonymous visitor).
 *
 * Deliberately does NOT create Media entities in massageFormValues(): that
 * method runs on every validateForm() pass, including the intermediate
 * "Upload" button click that happens before the visitor ever reaches the
 * real Submit button (managed_file's own #submit handler always forces a
 * form rebuild - see \Drupal\file\Element\ManagedFile::submit()). Creating
 * the real (unpublished, service-account-owned) Media entity that early
 * revokes the anonymous visitor's own "download" access to their
 * just-uploaded file - Media/file access control has no reason to grant
 * an anonymous session access to unpublished content it doesn't own - so
 * \Drupal\file\Element\ManagedFile::valueCallback()'s re-validation of the
 * previously-uploaded fid fails on the next request, silently emptying the
 * field. Real Media creation is deferred to createMediaFromUpload(),
 * called explicitly and exactly once by
 * PublicSubmissionForm::submitForm() - the one point that's guaranteed to
 * run only on a genuine, final, validated submission.
 *
 * Only supports the file-based media bundles (image, audio, video,
 * document) - remote_video/external_embed/soundcloud are URL/embed-code
 * entry, not files, and can't be produced by an upload control.
 */
#[FieldWidget(
  id: 'mukurtu_simple_media_upload',
  label: new TranslatableMarkup('Simple media upload'),
  description: new TranslatableMarkup('A plain multi-file upload control that creates unpublished media entities directly, without the Media Library picker.'),
  field_types: ['entity_reference'],
  multiple_values: TRUE,
)]
class SimpleMediaUploadWidget extends WidgetBase {

  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    array $third_party_settings,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ConfigFactoryInterface $configFactory,
    protected AccountSwitcherInterface $accountSwitcher,
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('entity_type.manager'),
      $container->get('config.factory'),
      $container->get('account_switcher'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    return $field_definition->getSetting('target_type') === 'media';
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      // Bundle IDs (subset of MediaTypeExtensions::SUPPORTED_TYPES) this
      // instance accepts. Empty means all supported (file-based) bundles.
      'allowed_bundles' => [],
    ] + parent::defaultSettings();
  }

  /**
   * Gets which supported (file-based) bundles this instance accepts.
   */
  public function getAllowedBundles(): array {
    $allowed = array_filter($this->getSetting('allowed_bundles') ?? []);
    $supported = array_keys(MediaTypeExtensions::SUPPORTED_TYPES);
    return $allowed ? array_values(array_intersect($supported, $allowed)) : $supported;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $allowed_bundles = $this->getAllowedBundles();
    $extensions = [];
    foreach ($allowed_bundles as $bundle) {
      $extensions[] = MediaTypeExtensions::SUPPORTED_TYPES[$bundle]['extensions'];
    }
    $extensions = implode(' ', $extensions);

    $element['upload'] = [
      '#type' => 'managed_file',
      '#title' => $this->fieldDefinition->getLabel(),
      '#description' => $this->t('Allowed types: @extensions. Maximum file size: @size.', [
        '@extensions' => $extensions,
        '@size' => ByteSizeMarkup::create(\Drupal\Component\Utility\Environment::getUploadMaxSize()),
      ]),
      '#multiple' => TRUE,
      '#upload_validators' => [
        'FileExtension' => ['extensions' => $extensions],
      ],
      // Same private upload location every supported bundle's own add form
      // uses (see MediaTypeExtensions::SUPPORTED_TYPES). Files stay
      // temporary (and thus reusable by their anonymous uploader across
      // repeated requests) until createMediaFromUpload() runs.
      '#upload_location' => 'private://' . date('Y-m'),
      '#required' => (bool) $element['#required'],
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   *
   * Deliberately a no-op - see the class docblock. Real Media creation
   * happens in createMediaFromUpload(), called directly by
   * PublicSubmissionForm::submitForm().
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    return [];
  }

  /**
   * Creates real (unpublished) Media entities from raw uploaded file IDs.
   *
   * Called explicitly by PublicSubmissionForm::submitForm() - exactly
   * once, only on a genuine final submission - rather than via
   * massageFormValues(), which Drupal invokes on every validateForm()
   * pass (see class docblock for why that's unsafe here).
   *
   * @param int[] $fids
   *   Raw uploaded file IDs, e.g. from
   *   $form_state->getValue([$field_name, 'upload']).
   * @param int $owner_uid
   *   The uid to own the created Media entities (and, transitively, their
   *   file usage) - normally the "Public Submissions" service account.
   *
   * @return array
   *   A entity_reference-field-ready array of ['target_id' => media_id].
   */
  public function createMediaFromUpload(array $fids, int $owner_uid): array {
    $fids = array_filter($fids);
    if (!$fids) {
      return [];
    }

    $allowed_bundles = $this->getAllowedBundles();

    // Anonymous visitors don't have "create media" permission - this
    // widget exists specifically to let them create unpublished media
    // anyway, so it elevates permissions itself for just this operation,
    // the same way PublicSubmissionForm::asSuperuser() does for the
    // node/submission entities themselves.
    $this->accountSwitcher->switchTo($this->entityTypeManager->getStorage('user')->load(1));
    try {
      $media_storage = $this->entityTypeManager->getStorage('media');
      $target_ids = [];
      foreach ($fids as $fid) {
        $file = File::load($fid);
        if (!$file) {
          continue;
        }
        $bundle = MediaTypeExtensions::bundleForFilename($file->getFilename());
        if (!$bundle || !in_array($bundle, $allowed_bundles, TRUE)) {
          continue;
        }
        $config = MediaTypeExtensions::SUPPORTED_TYPES[$bundle];

        $filename = $file->getFilename();
        $last_dot = strrpos($filename, '.');
        $name = $last_dot !== FALSE ? substr($filename, 0, $last_dot) : $filename;

        $field_value = ['target_id' => $file->id()];
        if (!empty($config['alt'])) {
          $field_value['alt'] = $name;
        }

        $file->setPermanent();
        $file->save();

        $media = $media_storage->create([
          'bundle' => $bundle,
          'uid' => $owner_uid,
          'name' => $name,
          $config['field'] => $field_value,
          'status' => 0,
        ]);
        $media->save();
        $target_ids[] = ['target_id' => $media->id()];
      }
      return $target_ids;
    }
    finally {
      $this->accountSwitcher->switchBack();
    }
  }

}
