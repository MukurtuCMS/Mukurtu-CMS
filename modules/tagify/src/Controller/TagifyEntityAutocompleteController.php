<?php

namespace Drupal\tagify\Controller;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Site\Settings;
use Drupal\tagify\TagifyEntityAutocompleteMatcher;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Defines a route controller for tagify entity autocomplete form elements.
 */
class TagifyEntityAutocompleteController extends ControllerBase {

  /**
   * The autocomplete matcher for entity references.
   *
   * @var \Drupal\tagify\TagifyEntityAutocompleteMatcher
   */
  protected $matcher;

  /**
   * Constructs a TagifyEntityAutocompleteController object.
   *
   * @param \Drupal\tagify\TagifyEntityAutocompleteMatcher $matcher
   *   The matcher.
   */
  public function __construct(TagifyEntityAutocompleteMatcher $matcher) {
    $this->matcher = $matcher;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('tagify.autocomplete_matcher')
    );
  }

  /**
   * Set the entity autocomplete matcher.
   *
   * @param \Drupal\tagify\TagifyEntityAutocompleteMatcher $matcher
   *   The autocomplete matcher for entity references.
   */
  protected function setMatcher(TagifyEntityAutocompleteMatcher $matcher) {
    $this->matcher = $matcher;
  }

  /**
   * Autocomplete the label of an entity.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object that contains the typed tags.
   * @param string $target_type
   *   The ID of the target entity type.
   * @param string $selection_handler
   *   The plugin ID of the entity reference selection handler.
   * @param string $selection_settings_key
   *   The hashed key of the key/value entry that holds the selection handler
   *   settings.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The matched entity labels as a JSON response.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   *   Thrown if the selection settings key is not found in the key/value store
   *   or if it does not match the stored data.
   */
  public function handleAutocomplete(Request $request, $target_type, $selection_handler, $selection_settings_key) {
    $matches = [];
    // Get already selected entity ids.
    $selected = $request->query->get('selected')
      ? explode(',', $request->query->get('selected'))
      : [];
    // Get the typed string from the URL, if it exists.
    $input = $request->query->get('q');
    if ($input !== NULL) {
      // Selection settings are passed in as a hashed key of a serialized array
      // stored in the key/value store.
      $selection_settings = $this->keyValue('entity_autocomplete')->get($selection_settings_key, FALSE);
      // Validate the autocomplete minimum length.
      if ($input === '' && isset($selection_settings['suggestions_dropdown']) && $selection_settings['suggestions_dropdown']) {
        return new JsonResponse([]);
      }

      if ($selection_settings !== FALSE) {
        $selection_settings_hash = Crypt::hmacBase64(serialize($selection_settings) . $target_type . $selection_handler, Settings::getHashSalt());
        if (!hash_equals($selection_settings_hash, $selection_settings_key)) {
          // Disallow access when the selection settings hash does not match the
          // passed-in key.
          throw new AccessDeniedHttpException('Invalid selection settings key.');
        }
      }
      else {
        // Disallow access when the selection settings key is not found in the
        // key/value store.
        throw new AccessDeniedHttpException();
      }
      $matches = $this->matcher->getMatches($target_type, $selection_handler, $selection_settings, mb_strtolower($input), $selected);
    }

    return new JsonResponse($matches);
  }

}
