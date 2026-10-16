<?php

namespace Drupal\mukurtu_dictionary\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Configure what languages are available on the dictionary language field.
 * This is a sitewide setting.
 *
 * The route for this config form is
 * mukurtu_dictionary.configure_dictionary_languages.
 *
 * The dictionary language field options get set inside of
 * mukurtu_dictionary_form_node_dictionary_word_form_alter() at
 * mukurtu_dictionary.module.
 *
 * @see mukurtu_dictionary.routing.yml
 * @see mukurtu_dictionary.module
 */
class MukurtuDictionaryLanguagesSettingsForm extends ConfigFormBase
{
  /**
   * Config settings.
   *
   * @var string
   */
  const SETTINGS = 'mukurtu_dictionary_languages.settings';

  /**
   * {@inheritdoc}
   */
  public function getFormId()
  {
    return 'mukurtu_dictionary_languages_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames()
  {
    return [
      static::SETTINGS,
    ];
  }

  // Method to get all language taxonomy terms.
  protected function getTerms()
  {
    $query = \Drupal::entityQuery('taxonomy_term')
      ->condition('vid', 'language')
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
  public function buildForm(array $form, FormStateInterface $form_state)
  {
    $form = parent::buildForm($form, $form_state);
    $config = $this->config(static::SETTINGS);

    $defaults = $config->get('available_languages') ?? [];
    $terms = $this->getTerms();

    if (!empty($terms)) {
      // If there's only one language on the site, select it automatically.
      if (count($terms) == 1) {
        $tid = array_key_first($terms);
        $defaults = [ $tid => strval($tid) ];
      }

      $form['available_languages'] = [
        '#type' => 'checkboxes',
        '#options' => $terms,
        '#title' => $this->t('Languages available for dictionary'),
        '#default_value' => $defaults,
        '#required' => TRUE,
      ];
    }
    else {
      $form['no_languages_on_site'] = [
        '#type' => 'item',
        '#markup' => $this->t('There are no languages on the site. <a href="@link">Add a language</a>.', [
          '@link' => Url::fromRoute('entity.taxonomy_term.add_form', ['taxonomy_vocabulary' => 'language'])->toString()
        ])
      ];
    }

    $form['in_language_fields'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t("Fields written in the word's language"),
      '#description' => $this->t("The term, alternate spellings, word entries, and sample sentences are always marked as being in the word's language, so a screen reader pronounces them with that language's rules instead of the site language's. The fields below are usually written in the site's language, so they are not marked by default. Select any that your dictionary writes in the word's language instead. Leave the rest clear: marking text with the wrong language is worse for a screen reader user than not marking it at all. Marking has an effect only on words whose language has a Language code set."),
      '#options' => [
        'field_definition' => $this->t('Definition'),
        'field_pronunciation' => $this->t('Pronunciation'),
        'field_translation' => $this->t('Translation'),
        'field_word_origin' => $this->t('Word Origin'),
      ],
      '#default_value' => $config->get('in_language_fields') ?? [],
    ];

    return $form;
  }


  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    $config = $this->config(static::SETTINGS);
    $values = $form_state->getValue('available_languages');
    $enabledLanguages = array_filter($values, fn($element) => $element !== 0);
    $config->set('available_languages', $enabledLanguages);

    // Stored as a plain list, so the hook can intersect it against the
    // fields it knows about without caring how the form keyed it.
    $config->set('in_language_fields', array_values(array_filter($form_state->getValue('in_language_fields'))));

    $config->save();

    parent::submitForm($form, $form_state);
  }
}
