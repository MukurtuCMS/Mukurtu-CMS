<?php

namespace Drupal\mukurtu_taxonomy\Plugin\EntityBrowser\Widget;

use Drupal\entity_browser\WidgetBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Uses an entity query to provide entity listing in a browser's widget.
 *
 * @EntityBrowserWidget(
 *   id = "mukurtu_taxonomy_record_term_selection",
 *   label = @Translation("Mukurtu Taxonomy Record Term Selection"),
 *   provider = "mukurtu_taxonomy",
 *   description = @Translation("Select taxonomy record terms."),
 * )
 */
class MukurtuTaxonomyRecordSelection extends WidgetBase implements ContainerFactoryPluginInterface {

  protected function buildEntityQuery($match = NULL, $match_operator = 'CONTAINS', $exclude = []) {
    // Get the vocabs enabled for taxonomy records.
    $config = \Drupal::config('mukurtu_taxonomy.settings');

    // In the future when we support taxonomy record relationships for other
    // content types, fetch their enabled vocabs and append them here.
    $enabledVocabs = $config->get('person_records_enabled_vocabularies') ?? [];

    $query = $this->entityTypeManager->getStorage('taxonomy_term')->getQuery();

    // Restrict to only enabled taxonomy vocabularies.
    if (!empty($enabledVocabs)) {
      $query->condition('vid', $enabledVocabs, 'IN');
    } else {
      // Can't have an empty array for this condition.
      $query->condition('vid', '');
    }

    // Match search string.
    if (isset($match)) {
      $conditionGroup = $query->orConditionGroup()
        ->condition('name', $match, $match_operator)
        ->condition('description', $match, $match_operator)
        ->condition('vid', $match, $match_operator);
      $query->condition($conditionGroup);
    }

    // Exclude already selected.
    if (!empty($exclude)) {
      $query->condition('tid', $exclude, 'NOT IN');
    }

    $query->condition('status', TRUE);
    $query->accessCheck(TRUE);
    $query->pager(20);
    $query->sort('name', 'ASC');
    return $query;
  }

  /**
   * Build the display label for a taxonomy term.
   */
  protected function buildTermLabel($term) {
    $name = $term->getName();
    $taxonomyVocab = $this->entityTypeManager->getStorage('taxonomy_vocabulary')->load($term->bundle());
    $vocab = $taxonomyVocab->label();
    return "$name ($vocab)";
  }

  /**
   * {@inheritdoc}
   */
  public function getForm(array &$original_form, FormStateInterface $form_state, array $additional_widget_parameters) {
    $form = parent::getForm($original_form, $form_state, $additional_widget_parameters);

    $form['search'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Search'),
      '#default_value' => '',
      '#size' => 60,
      '#maxlength' => 128,
    ];

    $form['search_submit'] = [
      '#type' => 'button',
      '#value' => $this->t('Search'),
    ];

    // Get the already selected users so we can remove them from the query.
    $storage = $form_state->getStorage();
    $selected = $storage['entity_browser']['selected_entities'] ?? [];
    $excludeTIDs = array_map(fn ($term) => $term->id(), $selected);

    // Build the query for the user selection.
    $query = $this->buildEntityQuery($form_state->getValue('search'), 'CONTAINS', $excludeTIDs);
    $results = $query->execute();

    $terms = $this->entityTypeManager->getStorage('taxonomy_term')->loadMultiple($results);

    foreach ($terms as $term) {
      $form['users']['term:' . $term->id()] = [
        '#type' => 'checkbox',
        '#title' => $this->buildTermLabel($term),
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function prepareEntities(array $form, FormStateInterface $form_state) {
    $entities = [];

    $values = $form_state->getValues();
    $termKeys = array_filter(array_keys($values), fn ($key) => stripos($key, 'term:') !== FALSE);

    foreach ($termKeys as $termKey) {
      if ($values[$termKey]) {
        list(, $tid) = explode(':', $termKey);
        $entities[] = $this->entityTypeManager->getStorage('taxonomy_term')->load($tid);
      }
    }

    return $entities;
  }

  /**
   * {@inheritdoc}
   */
  public function submit(array &$element, array &$form, FormStateInterface $form_state) {
    $entities = $this->prepareEntities($form, $form_state);
    $this->selectEntities($entities, $form_state);
  }

}
