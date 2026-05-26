<?php

namespace Drupal\mukurtu_media\Form;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\media\MediaTypeInterface;
use Drupal\media_entity_soundcloud\Form\SoundcloudForm;
use GuzzleHttp\Exception\RequestException;

/**
 * Media library add form for SoundCloud, with automatic name fill-in.
 *
 * Extends the contrib SoundcloudForm to fetch the track/playlist title from
 * SoundCloud's oEmbed endpoint and pre-fill the media name field, matching
 * the autofill behaviour already present for YouTube and Vimeo.
 */
class SoundCloudAddForm extends SoundcloudForm {

  /**
   * Title fetched from the SoundCloud oEmbed endpoint.
   */
  private ?string $fetchedTitle = NULL;

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return $this->getBaseFormId() . '_soundcloud';
  }

  /**
   * {@inheritdoc}
   */
  public function addButtonSubmit(array $form, FormStateInterface $form_state): void {
    $url = trim($form_state->getValue('soundcloud_url') ?? '');
    if ($url) {
      $this->fetchedTitle = $this->fetchSoundCloudTitle($url);
    }
    parent::addButtonSubmit($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function createMediaFromValue(MediaTypeInterface $media_type, EntityStorageInterface $media_storage, $source_field_name, $source_field_value) {
    $media = parent::createMediaFromValue($media_type, $media_storage, $source_field_name, $source_field_value);
    if ($this->fetchedTitle) {
      $media->setName($this->fetchedTitle);
    }
    return $media;
  }

  /**
   * Calls SoundCloud's oEmbed endpoint and returns the track/playlist title.
   *
   * Returns NULL on any network or parse error so the form can still save with
   * whatever default name the source plugin derives.
   */
  private function fetchSoundCloudTitle(string $url): ?string {
    try {
      $response = $this->httpClient->get('https://soundcloud.com/oembed', [
        'query' => ['format' => 'json', 'url' => $url],
        'timeout' => 5,
      ]);
      $data = json_decode((string) $response->getBody(), TRUE);
      return $data['title'] ?? NULL;
    }
    catch (RequestException $e) {
      return NULL;
    }
  }

}
