<?php

namespace Drupal\geolocation_google_static_maps\Plugin\geolocation\MapProvider;

use Drupal\Core\Url;
use Drupal\geolocation\Element\GeolocationMap;
use Drupal\geolocation_google_maps\GoogleMapsProviderBase;

/**
 * Provides Google Maps.
 *
 * @MapProvider(
 *   id = "google_static_maps",
 *   name = @Translation("Google Static Maps"),
 *   description = @Translation("You do require an API key for this plugin to work."),
 * )
 */
class GoogleStaticMaps extends GoogleMapsProviderBase {

  /**
   * {@inheritdoc}
   */
  public static $googleMapsApiUrlPath = '/maps/api/staticmap';

  /**
   * {@inheritdoc}
   */
  public static function getDefaultSettings(): array {
    return array_replace_recursive(
      parent::getDefaultSettings(),
      [
        'height' => '400',
        'width' => '400',
        'scale' => '1',
        'format' => 'png',
      ]
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getSettingsForm(array $settings, array $parents = []): array {
    $form = parent::getSettingsForm($settings, $parents);
    $parents_string = '';
    if ($parents) {
      $parents_string = implode('][', $parents) . '][';
    }

    $form['width'] = array_replace($form['width'], [
      '#type' => 'number',
      '#description' => $this->t('Enter width in pixels. Free users maximum 640.'),
      '#process' => [
        ['\Drupal\Core\Render\Element\RenderElement', 'processGroup'],
      ],
      '#pre_render' => [
        ['\Drupal\Core\Render\Element\Number', 'preRenderNumber'],
        ['\Drupal\Core\Render\Element\RenderElement', 'preRenderGroup'],
      ],
    ]);
    $form['height'] = array_replace($form['height'], [
      '#type' => 'number',
      '#description' => $this->t('Enter height in pixels. Free users maximum 640.'),
      '#process' => [
        ['\Drupal\Core\Render\Element\RenderElement', 'processGroup'],
      ],
      '#pre_render' => [
        ['\Drupal\Core\Render\Element\Number', 'preRenderNumber'],
        ['\Drupal\Core\Render\Element\RenderElement', 'preRenderGroup'],
      ],
    ]);

    $form['scale'] = [
      '#group' => $parents_string . 'general_settings',
      '#type' => 'select',
      '#title' => $this->t('Scale Value'),
      '#options' => [
        '1' => $this->t('1 (default)'),
        '2' => $this->t('2'),
        '4' => $this->t('4 - Google Maps APIs Premium Plan only'),
      ],
      '#default_value' => $settings['scale'],
      '#process' => [
        ['\Drupal\Core\Render\Element\RenderElement', 'processGroup'],
        ['\Drupal\Core\Render\Element\Select', 'processSelect'],
      ],
      '#pre_render' => [
        ['\Drupal\Core\Render\Element\RenderElement', 'preRenderGroup'],
      ],
    ];

    $form['format'] = [
      '#group' => $parents_string . 'general_settings',
      '#type' => 'select',
      '#title' => $this->t('Image Format'),
      '#options' => [
        'png' => $this->t('8-bit PNG (default)'),
        'png32' => $this->t('32-bit PNG'),
        'gif' => $this->t('GIF'),
        'jpg' => $this->t('JPEG'),
        'jpg-baseline' => $this->t('non-progressive JPEG'),
      ],
      '#default_value' => $settings['format'],
      '#process' => [
        ['\Drupal\Core\Render\Element\RenderElement', 'processGroup'],
        ['\Drupal\Core\Render\Element\Select', 'processSelect'],
      ],
      '#pre_render' => [
        ['\Drupal\Core\Render\Element\RenderElement', 'preRenderGroup'],
      ],
    ];

    return $form;
  }

  /**
   * Sign a URL with a given crypto key.
   *
   * Note that this URL must be properly URL-encoded.
   *
   * @param string $url
   *   URL to sign.
   *
   * @return string
   *   Signed URL.
   */
  public function signUrl(string $url): string {
    $config = \Drupal::config('geolocation_google_static_maps.settings');
    $secret = $config->get('google_static_maps_url_secret');
    if (empty($secret)) {
      return $url;
    }

    $url_parts = parse_url($url);

    $urlPartToSign = $url_parts['path'] . "?" . $url_parts['query'];

    // Decode the private key into its binary format.
    $decodedKey = base64_decode(str_replace(['-', '_'], ['+', '/'], $secret));

    // Create a signature using the private key and the URL-encoded
    // string using HMAC SHA1. This signature will be binary.
    $signature = hash_hmac("sha1", $urlPartToSign, $decodedKey, TRUE);

    $encodedSignature = str_replace(['+', '/'], ['-', '_'], base64_encode($signature));

    return $url . '&signature=' . $encodedSignature;
  }

  /**
   * {@inheritdoc}
   */
  public function alterRenderArray(array $render_array, array $map_settings, array $context = []): array {
    $additional_parameters = [
      'type' => strtolower($map_settings['type']),
      'size' => filter_var($map_settings['width'], FILTER_SANITIZE_NUMBER_INT) . 'x' . filter_var($map_settings['height'], FILTER_SANITIZE_NUMBER_INT),
      'zoom' => $map_settings['zoom'],
      'scale' => (int) $map_settings['scale'],
      'format' => $map_settings['format'],
    ];

    // 0,0 is the default behavior anyway, so just ignore it for fitlocations.
    if (!empty($render_array['#centre']['lat']) || !empty($render_array['#centre']['lng'])) {
      $additional_parameters['center'] = $render_array['#centre']['lat'] . ',' . $render_array['#centre']['lng'];
    }

    $static_map_url = $this->getGoogleMapsApiUrl($additional_parameters);

    $locations = GeolocationMap::getLocations($render_array);

    foreach ($locations as $location) {
      $marker_string = '&markers=';
      if (!empty($location['#icon'])) {
        $marker_string .= 'icon:' . Url::fromRoute('<front>', [], ['absolute' => TRUE])->toString() . $location['#icon'] . urlencode('|');
      }
      $marker_string .= $location['#coordinates']['lat'] . ',' . $location['#coordinates']['lng'];
      $static_map_url .= $marker_string;
    }

    return [
      '#theme' => 'image',
      '#uri' => $this->signUrl($static_map_url),
      '#google_static_map' => [
        'map_settings' => $map_settings,
        'context' => $context,
      ],
    ];
  }

}
