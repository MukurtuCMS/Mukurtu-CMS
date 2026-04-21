<?php

namespace Drupal\search_api_solr_log\Plugin\facets\processor;

use Drupal\Core\Cache\UnchangingCacheableDependencyTrait;
use Drupal\Core\Form\FormStateInterface;
use Drupal\facets\FacetInterface;
use Drupal\facets\Processor\PostQueryProcessorInterface;
use Drupal\facets\Processor\ProcessorPluginBase;
use Drupal\user\Entity\User;

/**
 * Replaces facet values based on a given mapping.
 *
 * @FacetsProcessor(
 *   id = "search_api_solr_log_user",
 *   label = @Translation("Translate User IDs to names."),
 *   description = @Translation("Display user names instead of user IDs provided as raw integers, for example in recent log messages."),
 *   stages = {
 *     "post_query" = 50,
 *   },
 * )
 */
class UserProcessor extends ProcessorPluginBase implements PostQueryProcessorInterface {

  use UnchangingCacheableDependencyTrait;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'keep_uid' => TRUE,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state, FacetInterface $facet): array {
    $keep_uid = $this->getConfiguration()['keep_uid'];
    $build['keep_uid'] = [
      '#title' => $this->t('Keep UID'),
      '#type' => 'checkbox',
      '#default_value' => $keep_uid,
      '#description' => $this->t("Display user ID in addition to the name."),
    ];
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function postQuery(FacetInterface $facet): void {
    $keep_uid = (bool) $this->getConfiguration()['keep_uid'];
    foreach ($facet->getResults() as $result) {
      $user_id = $result->getRawValue();
      // Load the user entity.
      $user = User::load($user_id);

      if ($user) {
        // Generate the URL to the user profile.
        $name = $user->getDisplayName();
        if ($keep_uid) {
          $name .= ' [' . $user_id . ']';
        }
        $result->setDisplayValue($name);
      }
    }
  }

}
