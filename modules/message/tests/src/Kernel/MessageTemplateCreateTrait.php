<?php

namespace Drupal\Tests\message\Kernel;

use Drupal\Core\Language\Language;
use Drupal\message\Entity\MessageTemplate;

/**
 * Trait to assist message template creation for tests.
 */
trait MessageTemplateCreateTrait {

  /**
   * Helper function to create and save a message template entity.
   *
   * @param string $template
   *   The message template.
   * @param string $label
   *   The message template label.
   * @param string $description
   *   The message template description.
   * @param array $text
   *   The text array for the message template.
   * @param array $settings
   *   Data overrides.
   * @param string $langcode
   *   The language to use.
   *
   * @return \Drupal\message\MessageTemplateInterface
   *   A saved message template entity.
   */
  protected function createMessageTemplate($template = NULL, $label = NULL, $description = NULL, array $text = [], array $settings = [], $langcode = Language::LANGCODE_NOT_SPECIFIED) {
    $settings += [
      'token options' => [
        'token replace' => TRUE,
        'clear' => FALSE,
      ],
    ];
    $template = $template ?: mb_strtolower($this->randomMachineName());
    $label = $label ?: $this->randomString();
    $description = $description ?: $this->randomString();
    $text = $text ?: [$this->randomString()];

    // If the $text array is simple text values, transform to text + format.
    foreach ($text as $key => $detail) {
      if (!is_array($detail)) {
        $text[$key] = [
          'value' => $detail,
          'format' => filter_default_format(),
        ];
      }
      elseif (!isset($detail['format'])) {
        $text[$key]['format'] = 'plain_text';
      }
    }

    $message_template = MessageTemplate::Create([
      'template' => $template,
      'label' => $label,
      'description' => $description,
      'text' => $text,
      'settings' => $settings,
      'langcode' => $langcode,
    ]);
    $message_template->save();

    return $message_template;
  }

}
