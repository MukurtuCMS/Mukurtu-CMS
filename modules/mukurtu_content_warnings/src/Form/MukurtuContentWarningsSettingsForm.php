<?php

namespace Drupal\mukurtu_content_warnings\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Mukurtu content warnings settings for this site.
 */
class MukurtuContentWarningsSettingsForm extends ConfigFormBase {

  /**
   * Config settings.
   *
   * @var string
   */
  const SETTINGS = 'mukurtu_content_warnings.settings';

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mukurtu_content_warnings_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      static::SETTINGS,
    ];
  }

  // Method to get all media tag taxonomy terms.
  protected function getTerms() {
    $query = \Drupal::entityQuery('taxonomy_term')
      ->condition('vid', 'media_tag')
      ->accessCheck(FALSE);
    $tids = $query->execute();

    $terms = [];
    foreach ($tids as $tid) {
      $term = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($tid);
      if ($term) {
        $terms[$tid] = $term->getName();
      }
    }
    return $terms;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config(static::SETTINGS);

    // People Warnings settings.
    $form['people_warnings'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('People Warnings'),
    ];

    $form['people_warnings']['info'] = [
      '#type' => 'item',
      '#title' => $this->t('Configure warnings for media pertaining to a person who is deceased.'),
    ];

    $form['people_warnings']['toggle'] = [
      '#title' => $this->t('Enable People Warnings'),
      '#description' => $this->t('This is a site-wide setting.'),
      '#type' => 'checkbox',
      '#return_value' => TRUE,
      '#default_value' => $config->get('people_warnings.enabled') ?? 0,
    ];

    $form['people_warnings']['single_person_text'] = [
      '#title' => $this->t('Warning Text: Single Person'),
      '#description' => $this->t('The text that will be displayed on the media overlay for a single deceased person. Use the replacement token "[name]" to insert the person\'s name in the message.'),
      '#type' => 'textfield',
      '#default_value' => $config->get('people_warnings.warning_single') ?? $this->t('Warning: [name] is deceased. Click through to access content.'),
    ];

    $form['people_warnings']['multiple_people_text'] = [
      '#title' => $this->t('Warning Text: Multiple People'),
      '#description' => $this->t('The text that will be displayed on the media overlay for multiple deceased people. Use the replacement token "[names]" to insert the names in the message.'),
      '#type' => 'textfield',
      '#default_value' => $config->get('people_warnings.warning_multiple') ?? $this->t('Warning: The following people are deceased. Click through to access content. [names]'),
    ];

    $form['#tree'] = TRUE;

    // @todo The description part is not placed well, but I need to move on
    //   for now.
    $form['taxonomy_warnings'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Taxonomy Triggered Warnings'),
      '#description' => $this->t('Configure warnings for media tagged with a specific media tag. This is a site-wide setting'),
      '#prefix' => '<div id="taxonomy-warnings-fieldset-wrapper">',
      '#suffix' => '</div>',
    ];

    $availableTerms = $this->getTerms();

    // Note: $i here functions as a sort of id for the taxonomy warning widget.
    // $i is NOT the id of the taxonomy term inside the widget!
    $taxonomyWarnings = $config->get('taxonomy_warnings');

    // Get the number of warning widgets in the form already.
    $num_widgets = $form_state->get('num_widgets');

    // We have to ensure that there is at least one warning fieldset.
    if ($num_widgets === NULL) {
      $num_widgets = 1;
      $form_state->set('num_widgets', $num_widgets);
    }

    // Get a list of warning widgets that were removed.
    $removed_widgets = $form_state->get('removed_widgets');
    // If no warnings have been removed yet we use an empty array.
    if ($removed_widgets === NULL) {
      $form_state->set('removed_widgets', []);
      $removed_widgets = $form_state->get('removed_widgets');
    }

    // @todo The num_widgets form state value is not being retained between
    //   form reloads. Not sure if caching is allowed or useful.
    $rendered = [];
    for ($i = 0; $i < $num_widgets; $i++) {
      // Check if term was removed.
      if (in_array($i, $removed_widgets)) {
        // Skip if term was removed and move to the next term.
        continue;
      }

      // We should hopefully not have more taxonomy warnings to render than we
      // have widgets to render...
      $id = NULL;
      $text = NULL;
      // If we have taxonomy warnings from config, attempt to render them one
      // at a time.
      if ($taxonomyWarnings && isset($taxonomyWarnings[$i]) && $taxonomyWarnings[$i]) {
        $warning = $taxonomyWarnings[$i];
        if (!in_array($warning['term'], $rendered)) {
          $id = $warning['term'];
          $text = $warning['warning'];
          // Mark this warning's term as being rendered (is this even
          // necessary?).
          $rendered[] = $id;
        }
      }

      // Create a new fieldset for each taxonomy term warning where we can add
      // the term and warning text.
      // Fieldset title.
      $form['taxonomy_warnings'][$i] = [
        '#type' => 'fieldset',
      ];

      // Term select.
      $form['taxonomy_warnings'][$i]['term'] = [
        '#type' => 'select',
        '#title' => $this->t('Term'),
        '#description' => $this->t('Select the media tag attached to the media that will trigger the warning.'),
        '#options' => $availableTerms,
        '#empty_option' => $this->t('Select a term'),
        '#default_value' => $id,
      ];
      // Term warning text.
      $form['taxonomy_warnings'][$i]['text'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Warning Text'),
        '#description' => $this->t('The text that will be displayed on the media overlay.'),
        '#default_value' => $text,
      ];

      $form['taxonomy_warnings'][$i]['actions'] = [
        '#type' => 'submit',
        '#value' => $this->t('Remove'),
        '#name' => $i,
        '#submit' => ['::removeCallback'],
        '#ajax' => [
          'callback' => '::addMoreCallback',
          'wrapper' => 'taxonomy-warnings-fieldset-wrapper',
        ],
      ];
    }

    $form['taxonomy_warnings']['actions'] = [
      '#type' => 'actions',
    ];

    $form['taxonomy_warnings']['actions']['add_taxonomy_warning'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add taxonomy warning'),
      '#submit' => ['::addOne'],
      '#ajax' => [
        'callback' => '::addMoreCallback',
        'wrapper' => 'taxonomy-warnings-fieldset-wrapper',
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * Callback for both ajax-enabled buttons.
   *
   * Selects and returns the fieldset with the names in it.
   */
  public function addMoreCallback(array &$form, FormStateInterface $form_state) {
    return $form['taxonomy_warnings'];
  }

  /**
   * Submit handler for the "add-one-more" button.
   *
   * Increments the max counter and causes a rebuild.
   */
  public function addOne(array &$form, FormStateInterface $form_state) {
    $num_widgets = $form_state->get('num_widgets');
    $num_widgets++;
    $form_state->set('num_widgets', $num_widgets);
    $form_state->setRebuild();
  }

  /**
   * Submit handler for the "remove" button.
   *
   * Removes the corresponding line.
   */
  public function removeCallback(array &$form, FormStateInterface $form_state) {
    // We use the name of the remove button to find the element we want to
    // remove.
    $trigger = $form_state->getTriggeringElement();
    $indexToRemove = $trigger['#name'];

    // Remove the fieldset from $form.
    unset($form['taxonomy_warnings'][$indexToRemove]);

    // Keep track of removed warnings so we can add new fields at the bottom
    // Without this they would be added where a value was removed.
    $removed_widgets = $form_state->get('removed_widgets');
    $removed_widgets[] = $indexToRemove;
    $form_state->set('removed_widgets', $removed_widgets);

    // Rebuild form_state.
    $form_state->setRebuild();
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config(static::SETTINGS);
    $values = $form_state->getValues();

    // People Warnings settings.

    // Toggle people warnings on and off.
    $config->set('people_warnings.enabled', $values['people_warnings']['toggle']);

    // Single person text.
    $config->set('people_warnings.warning_single', $values['people_warnings']['single_person_text']);

    // Multiple people text.
    $config->set('people_warnings.warning_multiple', $values['people_warnings']['multiple_people_text']);

    // Taxonomy warnings settings.

    $taxonomyWarningsConfig = [];
    foreach ($values['taxonomy_warnings'] as $i => $warning) {

      // Skip any non-integer values for $i. All our warnings are keyed by integer $i values.
      if ($i === 'actions') {
        continue;
      }

      // Only save to config complete and non-empty taxonomy term warnings.
      if (isset($warning['term']) && $warning['term'] && isset($warning['text']) && $warning['text']) {
        $temp = [
          'term' => $warning['term'],
          'warning' => $warning['text'],
        ];
        array_push($taxonomyWarningsConfig, $temp);
      }
    }
    $config->set('taxonomy_warnings', $taxonomyWarningsConfig);

    $config->save();
    $form_state->setRebuild();
  }

}
