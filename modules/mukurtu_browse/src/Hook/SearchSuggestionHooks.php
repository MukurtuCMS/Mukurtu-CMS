<?php

declare(strict_types=1);

namespace Drupal\mukurtu_browse\Hook;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Order\OrderAfter;
use Drupal\Core\Render\Element;
use Drupal\Core\Url;
use Drupal\search_api_autocomplete\Element\SearchApiAutocomplete;

/**
 * Swaps Search API Autocomplete's dropdown for Mukurtu's accessible one.
 *
 * The module's dropdown is core's jQuery UI autocomplete, which has no
 * combobox roles and announces results in English only. The module's server
 * side (its route, access check and suggesters) is kept; only the widget in
 * the browser changes.
 */
class SearchSuggestionHooks {

  /**
   * Implements hook_form_FORM_ID_alter() for views_exposed_form.
   */
  #[Hook('form_views_exposed_form_alter', order: new OrderAfter(['search_api_autocomplete']))]
  public function formViewsExposedFormAlter(array &$form, FormStateInterface $form_state): void {
    $this->replaceWidgets($form);
  }

  /**
   * Replaces every Search API Autocomplete element below $elements.
   */
  protected function replaceWidgets(array &$elements): void {
    foreach (Element::children($elements) as $key) {
      if (($elements[$key]['#type'] ?? NULL) === 'search_api_autocomplete') {
        $this->replaceWidget($elements[$key]);
      }
      else {
        $this->replaceWidgets($elements[$key]);
      }
    }
  }

  /**
   * Turns one autocomplete element back into a text field with our widget.
   */
  protected function replaceWidget(array &$element): void {
    $search_id = $element['#search_id'];
    $data = $element['#additional_data'] ?? [];

    $element['#type'] = 'textfield';
    unset($element['#search_id'], $element['#additional_data']);
    if (isset($element['#process'])) {
      $element['#process'] = array_values(array_filter(
        $element['#process'],
        fn ($callback) => $callback !== [SearchApiAutocomplete::class, 'processSearchApiAutocomplete'],
      ));
    }

    /** @var \Drupal\search_api_autocomplete\SearchInterface|null $search */
    $search = \Drupal::entityTypeManager()->getStorage('search_api_autocomplete_search')->load($search_id);
    if (!$search) {
      return;
    }
    $access = \Drupal::service('search_api_autocomplete.helper')->access($search, \Drupal::currentUser());
    $metadata = CacheableMetadata::createFromRenderArray($element)
      ->merge(CacheableMetadata::createFromObject($access));
    if ($access->isAllowed()) {
      $url = Url::fromRoute('search_api_autocomplete.autocomplete', ['search_api_autocomplete_search' => $search_id] + $data)
        ->toString(TRUE);
      $metadata = $metadata->merge(CacheableMetadata::createFromObject($url));

      // Stops the browser's own form history from covering the list.
      $element['#attributes']['autocomplete'] = 'off';
      $element['#attributes']['data-mukurtu-search-suggest'] = $url->getGeneratedUrl();
      $element['#attributes']['data-mukurtu-search-suggest-min'] = (int) $search->getOption('min_length');
      $element['#attached']['library'][] = 'mukurtu_browse/search-suggest';
    }
    $metadata->applyTo($element);
  }

}
