<?php

namespace Drupal\search_api\Plugin\views\field;

use Drupal\views\Attribute\ViewsField;
use Drupal\Component\Render\MarkupInterface;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\search_api\SearchApiException;
use Drupal\search_api\Utility\Utility;

/**
 * Handles the display of text fields in Search API Views.
 *
 * @ingroup views_field_handlers
 */
#[ViewsField('search_api_text')]
class SearchApiText extends SearchApiStandard {

  /**
   * {@inheritdoc}
   */
  public function defineOptions(): array {
    $options = parent::defineOptions();

    $options['filter_type'] = [
      'default' => !empty($this->definition['filter_type']) ? $this->definition['filter_type'] : 'plain',
    ];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state): void {
    parent::buildOptionsForm($form, $form_state);

    $t_args = [
      '@strip' => $this->t('Strip HTML tags'),
      '@rewrite' => $this->t('Rewrite results'),
    ];

    $xss_allowed_tags = '<' . implode('> <', Xss::getHtmlTagList()) . '>';
    $xss_allowed_admin_tags = '<' . implode('> <', Xss::getAdminTagList()) . '>';

    $form['filter_type'] = [
      '#title' => $this->t('Enable HTML in this field'),
      '#type' => 'radios',
      '#options' => [
        'plain' => $this->t('Do not allow HTML'),
        'xss' => $this->t('Allow safe HTML only'),
        'xss_admin' => $this->t('Allow almost any HTML'),
      ],
      '#default_value' => $this->options['filter_type'],
      'plain' => [
        '#description' => $this->t('This will display any HTML tags in the field value escaped as plain text.'),
      ],
      'xss' => [
        '#description' => $this->t('Allowed tags: @tags. For instead removing those tags, use the "@strip" option under "@rewrite".', ['@tags' => $xss_allowed_tags] + $t_args),
      ],
      'xss_admin' => [
        '#description' => $this->t('Allowed tags: @tags. <strong>Use with caution.</strong> For instead removing those tags, use the "@strip" option under "@rewrite".', ['@tags' => $xss_allowed_admin_tags] + $t_args),
      ],
    ];

    $combined_property_path = $this->getCombinedPropertyPath();
    $allow_unsanitized = $this->options['filter_type'] === 'unfiltered'
      || $combined_property_path === 'search_api_excerpt';
    if (!$allow_unsanitized) {
      [$datasource_id, $property_path] = Utility::splitCombinedId($combined_property_path);
      [$parent_path, $name] = Utility::splitPropertyPath($property_path);
      if (!$parent_path) {
        try {
          $datasource_properties = $this->getIndex()
            ->getPropertyDefinitions($datasource_id);
        }
        catch (SearchApiException) {
          // Ignored.
        }
        if (isset($datasource_properties[$name])) {
          $property = $datasource_properties[$name];
          $type = $property->getDataType();
          $allow_unsanitized = $type === 'search_api_html';
        }
      }
    }
    if ($allow_unsanitized) {
      $form['filter_type']['#options']['unfiltered'] = $this->t('Allow any HTML');
      $form['filter_type']['unfiltered']['#description'] = $this->t('Allow all HTML. <strong>Use with caution: must only be used with previously sanitized data</strong>, for instance, when displaying a field that contains a rendered view mode.');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function render_item($count, $item): MarkupInterface|string {
    if ($this->options['filter_type'] === 'unfiltered') {
      return Markup::create($item['value']);
    }

    return $this->sanitizeValue($item['value'], $this->options['filter_type']);
  }

}
