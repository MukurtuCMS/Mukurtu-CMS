<?php

namespace Drupal\search_api\Plugin\views\argument;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a contextual filter searching through all indexed taxonomy fields.
 *
 * Note: The plugin attribute below is commented out because, due to dependency
 * problems, the plugin is not defined here but in
 * search_api_views_plugins_argument_alter().
 *
 * @ingroup views_argument_handlers
 *
 * @see search_api_views_plugins_argument_alter()
 *
 * #[ViewsArgument('search_api_all_terms')]
 */
class SearchApiAllTerms extends SearchApiTerm {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|null
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $plugin = parent::create($container, $configuration, $plugin_id, $plugin_definition);

    $plugin->setEntityTypeManager($container->get('entity_type.manager'));

    return $plugin;
  }

  /**
   * Retrieves the entity type manager.
   *
   * @return \Drupal\Core\Entity\EntityTypeManagerInterface
   *   The entity type manager.
   */
  public function getEntityTypeManager(): EntityTypeManagerInterface {
    return $this->entityTypeManager ?: \Drupal::entityTypeManager();
  }

  /**
   * Sets the entity type manager.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The new entity type manager.
   *
   * @return $this
   */
  public function setEntityTypeManager(EntityTypeManagerInterface $entity_type_manager): self {
    $this->entityTypeManager = $entity_type_manager;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function query($group_by = FALSE) {
    if (empty($this->value)) {
      $this->fillValue();
      if (empty($this->value)) {
        return;
      }
    }

    $not_negated = empty($this->options['not']);

    // If using an OR query, we can use IN for improved performance.
    $conjunction = strtoupper($this->operator);
    $use_in_conditions = $conjunction === 'OR';
    if ($not_negated) {
      $operator = ($use_in_conditions ? 'IN' : '=');
    }
    else {
      if ($use_in_conditions) {
        $conjunction = 'AND';
        $operator = 'NOT IN';
      }
      else {
        $conjunction = 'OR';
        $operator = '<>';
      }
    }

    try {
      $terms = $this->getEntityTypeManager()->getStorage('taxonomy_term')
        ->loadMultiple($this->value);
    }
    catch (PluginException) {
      $this->query->abort($this->t('Could not load taxonomy terms.'));
      return;
    }
    // If values were given, but weren't valid taxonomy term IDs, we abort the
    // query, as this wouldn't have yielded any results. (Unless the filter is
    // negated, in which case this is of course fine.)
    if (empty($terms)) {
      if ($not_negated) {
        $this->query->abort($this->t('No valid taxonomy term IDs given for "All taxonomy term fields" contextual filter.'));
      }
      return;
    }
    // Same if at least one term couldn't be loaded and we use the "AND"
    // conjunction.
    if ($not_negated
        && $conjunction == 'AND'
        && count($terms) < count($this->value)) {
      $this->query->abort($this->t('Invalid taxonomy term ID given for "All taxonomy term fields" contextual filter.'));
      return;
    }

    $vocabulary_fields = $this->definition['vocabulary_fields'];
    // Add an empty array for the "all vocabularies" fields, so this is always
    // present (to simplify the code below a bit).
    $vocabulary_fields += ['' => []];
    $values = $multi_field_values = [];
    $term_conditions = $this->query->createAndAddConditionGroup($conjunction);
    foreach ($terms as $term) {
      // Set filters for all term reference fields which don't specify a
      // vocabulary, as well as for all fields specifying the term's vocabulary.
      $vocabulary_id = $term->bundle();
      $term_id = $term->id();

      $fields_count = count($vocabulary_fields[$vocabulary_id] ?? [])
        + count($vocabulary_fields['']);
      // If we are using an AND conjunction for our conditions, we need to make
      // sure all terms actually lead to (at least) one query condition (as a
      // term not matching any indexed field has to be treated as a FALSE
      // condition).
      if ($conjunction === 'AND' && $fields_count === 0) {
        $variables = [
          '@id' => $term_id,
          '%label' => $term->label(),
          '%vocabulary' => $vocabulary_id,
        ];
        $this->query->abort($this->t('"All taxonomy term fields" contextual filter could not be applied as taxonomy term %label (ID: @id) belongs to vocabulary %vocabulary, not contained in any indexed fields.', $variables));
        return;
      }

      // If the operator is "AND" (commas in the argument) and there are more
      // than one fields for a term, things get complicated: we need to create a
      // condition group for each individual value, containing the conditions
      // for all its associated fields.
      if ($this->operator === 'and' && $fields_count > 1) {
        foreach ($vocabulary_fields[$vocabulary_id] ?? [] as $field) {
          $multi_field_values[$term_id][] = $field;
        }
        foreach ($vocabulary_fields[''] as $field) {
          $multi_field_values[$term_id][] = $field;
        }
      }
      else {
        foreach ($vocabulary_fields[$vocabulary_id] ?? [] as $field) {
          $values[$field][] = $term_id;
        }
        foreach ($vocabulary_fields[''] as $field) {
          $values[$field][] = $term_id;
        }
      }
    }

    // Add the collected field/value conditions to the condition group.
    foreach ($values as $field => $items) {
      if ($use_in_conditions) {
        $term_conditions->addCondition($field, $items, $operator);
      }
      else {
        foreach ($items as $value) {
          $term_conditions->addCondition($field, $value, $operator);
        }
      }
    }
    foreach ($multi_field_values as $value => $fields) {
      $flipped_conjunction = $conjunction === 'AND' ? 'OR' : 'AND';
      $group = $this->query->createConditionGroup($flipped_conjunction);
      $term_conditions->addConditionGroup($group);
      foreach ($fields as $field) {
        $group->addCondition($field, $value, $not_negated ? '=' : '<>');
      }
    }

    if (!$term_conditions->getConditions()) {
      $labels = [];
      foreach ($terms as $term) {
        $labels[] = $term->label();
      }
      $variables = [
        '@terms' => implode(', ', $labels),
      ];
      $this->query->abort($this->t('"All taxonomy term fields" contextual filter could not be applied as no indexed fields were found matching the given vocabularies of the given terms (@terms).', $variables));
    }
  }

}
