<?php

namespace Drupal\better_exposed_filters\Plugin\better_exposed_filters\filter;

use Drupal\better_exposed_filters\Attribute\FiltersWidget;
use Drupal\Core\Form\FormStateInterface;
use Drupal\better_exposed_filters\BetterExposedFiltersHelper;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Default widget implementation.
 */
#[FiltersWidget(
  id: 'bef_links',
  title: new TranslatableMarkup('Links'),
)]
class Links extends FilterWidgetBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return parent::defaultConfiguration() + [
      'select_all_none' => FALSE,
      'soft_limit' => 0,
      'soft_limit_label_less' => '',
      'soft_limit_label_more' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    /** @var \Drupal\views\Plugin\views\filter\FilterPluginBase $filter */
    $filter = $this->handler;

    $form = parent::buildConfigurationForm($form, $form_state);

    $form['select_all_none'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Add select all/none links'),
      '#default_value' => !empty($this->configuration['select_all_none']),
      '#disabled' => !$filter->options['expose']['multiple'],
      '#description' => $this->t('Add a "Select All/None" link when rendering the exposed filter using checkboxes. If this option is disabled, edit the filter and check the "Allow multiple selections".'
      ),
    ];

    $options = [50, 40, 30, 20, 15, 10, 5, 3];
    $form['soft_limit'] = [
      '#type' => 'select',
      '#title' => $this->t('Soft limit'),
      '#default_value' => $this->configuration['soft_limit'] ?: 0,
      '#options' => array_combine($options, $options),
      '#empty_option' => $this->t('No limit'),
      "#empty_value" => 0,
      '#description' => $this->t('Limit the number of displayed items via JavaScript.'),
    ];
    $form['soft_limit_label_less'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Show less label'),
      '#description' => $this->t('This text will be used for "Show less" link.'),
      '#default_value' => $this->configuration['soft_limit_label_less'] ?: t('Show less'),
      '#states' => [
        'invisible' => [
          ':input[name="exposed_form_options[bef][filter][' . $filter->field . '][configuration][soft_limit]"]' =>
            [
              'value' => 0,
            ],
        ],
      ],
    ];
    $form['soft_limit_label_more'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Show more label'),
      '#description' => $this->t('This text will be used for "Show more" link.'),
      '#default_value' => $this->configuration['soft_limit_label_more'] ?: t('Show more'),
      '#states' => [
        'invisible' => [
          ':input[name="exposed_form_options[bef][filter][' . $filter->field . '][configuration][soft_limit]"]' =>
            [
              'value' => 0,
            ],
        ],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function exposedFormAlter(array &$form, FormStateInterface $form_state): void {
    /** @var \Drupal\views\Plugin\views\filter\FilterPluginBase $filter */
    $filter = $this->handler;
    $field_id = $this->getExposedFilterFieldId();

    parent::exposedFormAlter($form, $form_state);

    if (!empty($form[$field_id]['#options'])) {
      // Clean up filters that pass objects as options instead of strings.
      $form[$field_id]['#options'] = BetterExposedFiltersHelper::flattenOptions($form[$field_id]['#options']);

      // Support rendering hierarchical links (e.g. taxonomy terms).
      if (!empty($filter->options['hierarchy'])) {
        $form[$field_id]['#bef_nested'] = TRUE;
      }

      $form[$field_id]['#theme'] = 'bef_links';
      // Exposed form displayed as blocks can appear on pages other than
      // the view results appear on. This can cause problems with
      // select_as_links options as they will use the wrong path. We
      // provide a hint for theme functions to correct this.
      $form[$field_id]['#bef_path'] = $this->getExposedFormActionUrl($form_state);

      if ($filter->view->ajaxEnabled() || $filter->view->display_handler->ajaxEnabled()) {
        $form[$field_id]['#attributes']['class'][] = 'bef-links-use-ajax';
        $form['#attached']['library'][] = 'better_exposed_filters/links_use_ajax';
      }
    }

    $soft_limit = (int) $this->configuration['soft_limit'];
    if ($soft_limit !== 0) {
      $form['#attached']['library'][] = 'better_exposed_filters/soft_limit';
      $form['#attached']['drupalSettings']['better_exposed_filters']['soft_limit'][$field_id]['limit'] = $soft_limit;
      $form['#attached']['drupalSettings']['better_exposed_filters']['soft_limit'][$field_id]['list_selector'] = '> ul';
      $form['#attached']['drupalSettings']['better_exposed_filters']['soft_limit'][$field_id]['item_selector'] = '> li';
      $form['#attached']['drupalSettings']['better_exposed_filters']['soft_limit'][$field_id]['show_less'] = $this->configuration['soft_limit_label_less'];
      $form['#attached']['drupalSettings']['better_exposed_filters']['soft_limit'][$field_id]['show_more'] = $this->configuration['soft_limit_label_more'];
    }
  }

}
