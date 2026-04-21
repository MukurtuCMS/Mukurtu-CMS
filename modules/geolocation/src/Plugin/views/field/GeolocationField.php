<?php

namespace Drupal\geolocation\Plugin\views\field;

use Drupal\Core\Form\FormStateInterface;
use Drupal\geolocation\Plugin\Field\FieldType\GeolocationItem;
use Drupal\views\Plugin\views\field\EntityField;

/**
 * Field handler for geolocaiton field.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("geolocation_field")
 */
class GeolocationField extends EntityField {

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    // Remove the click sort field selector.
    unset($form['click_sort_column']);
  }

  /**
   * {@inheritdoc}
   */
  protected function documentSelfTokens(&$tokens) {
    parent::documentSelfTokens($tokens);
    $tokens['{{ ' . $this->options['id'] . '__lat_sex }}'] = $this->t('Latitude in sexagesimal notation.');
    $tokens['{{ ' . $this->options['id'] . '__lng_sex }}'] = $this->t('Longitude in sexagesimal notation.');
  }

  /**
   * {@inheritdoc}
   */
  protected function addSelfTokens(&$tokens, $item) {
    parent::addSelfTokens($tokens, $item);
    if (empty($item['raw'])) {
      return;
    }

    /** @var \Drupal\geolocation\Plugin\Field\FieldType\GeolocationItem $geolocationItem */
    $geolocationItem = $item['raw'];
    if ($geolocationItem->isEmpty()) {
      return;
    }

    $tokens['{{ ' . $this->options['id'] . '__lat_sex }}'] = GeolocationItem::decimalToSexagesimal($geolocationItem->get('lat')->getValue());
    $tokens['{{ ' . $this->options['id'] . '__lng_sex }}'] = GeolocationItem::decimalToSexagesimal($geolocationItem->get('lng')->getValue());
  }

}
