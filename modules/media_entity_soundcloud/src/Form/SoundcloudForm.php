<?php

namespace Drupal\media_entity_soundcloud\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\media_entity_soundcloud\Plugin\media\Source\Soundcloud;
use Drupal\media_library\Form\AddFormBase;
use Drupal\media_library\MediaLibraryUiBuilder;
use Drupal\media_library\OpenerResolverInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Implementing form SoundcloudForm.
 */
class SoundcloudForm extends AddFormBase {

  /**
   * The http client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * Constructs a AddFormBase object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\media_library\MediaLibraryUiBuilder $library_ui_builder
   *   The media library UI builder.
   * @param \GuzzleHttp\ClientInterface $http_client
   *   Http client service.
   * @param \Drupal\media_library\OpenerResolverInterface $opener_resolver
   *   The opener resolver.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, MediaLibraryUiBuilder $library_ui_builder, ClientInterface $http_client, OpenerResolverInterface $opener_resolver = NULL) {
    parent::__construct($entity_type_manager, $library_ui_builder, $opener_resolver);
    $this->httpClient = $http_client;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('media_library.ui_builder'),
      $container->get('http_client'),
      $container->get('media_library.opener_resolver')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getMediaType(FormStateInterface $form_state) {
    if ($this->mediaType) {
      return $this->mediaType;
    }

    $media_type = parent::getMediaType($form_state);
    if (!$media_type->getSource() instanceof Soundcloud) {
      throw new \InvalidArgumentException('Can only add media types which use an Soundcloud source plugin.');
    }
    return $media_type;
  }

  /**
   * {@inheritDoc}
   */
  protected function buildInputElement(array $form, FormStateInterface $form_state) {
    $container = [
      '#type' => 'container',
    ];
    $container['soundcloud_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t("Add Soundcloud Track URL"),
      '#maxlength' => 255,
      '#attributes' => [
        'placeholder' => 'https://soundcloud.com/path-to-track',
      ],
    ];

    $container['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add'),
      '#button_type' => 'primary',
      '#submit' => ['::addButtonSubmit'],
      '#validate' => ['::validateSoundcloudUrl'],
      '#ajax' => [
        'callback' => '::updateFormCallback',
        'wrapper' => 'media-library-wrapper',
        // @todo Remove when https://www.drupal.org/project/drupal/issues/2504115
        //   is fixed.
        'url' => Url::fromRoute('media_library.ui'),
        'options' => [
          'query' => $this->getMediaLibraryState($form_state)->all() + [
            FormBuilderInterface::AJAX_FORM_REQUEST => TRUE,
          ],
        ],
      ],
    ];

    $form['container'] = $container;

    return $form;
  }

  /**
   * Validates a soundcloud url.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return void|TRUE
   */
  public function validateSoundcloudUrl(array &$form, FormStateInterface $form_state) {
    $url = $form_state->getValue('soundcloud_url');
    if (preg_match('/https?:\/\/(www\.)?soundcloud\.com\/.*/', $url)) {
      try {
        \Drupal::httpClient()->get($url);
      }
      catch (ClientException $e) {
        $form_state->setErrorByName('url', $this->t('Could not connect to the track, please make sure that the url is correct.'));
      }

    }
    else {
      $form_state->setErrorByName('url', $this->t('Invalid url.'));
    }
  }

  /**
   * {@inheritDoc}
   */
  public function addButtonSubmit(array $form, FormStateInterface $form_state) {
    $this->processInputValues([$form_state->getValue('soundcloud_url')], $form, $form_state);
  }

  /**
   * {@inheritDoc}
   */
  public function getFormId() {
    return 'soundcloud_media_add_form';
  }
}
