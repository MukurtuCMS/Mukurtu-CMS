<?php

namespace Drupal\mukurtu_footer\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\file\Entity\File;

/**
 * Provides the Mukurtu Footer block.
 *
 * Configuration is managed via the dedicated settings form at
 * /admin/config/mukurtu/footer (FooterSettingsForm). This block is a
 * thin renderer that reads from mukurtu_footer.settings config.
 *
 * @Block(
 *   id = "mukurtu_footer",
 *   admin_label = @Translation("Mukurtu Footer"),
 *   category = "Custom"
 * )
 */
class MukurtuFooterBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $config = \Drupal::config('mukurtu_footer.settings');

    // Resolve logo file entities to public URLs.
    $logos = [];
    foreach ($config->get('logos') ?? [] as $logo) {
      if (!empty($logo['fid'])) {
        $file = File::load($logo['fid']);
        if ($file) {
          $logos[] = [
            'url'      => \Drupal::service('file_url_generator')->generateAbsoluteString($file->getFileUri()),
            'alt'      => $logo['alt'] ?? '',
            'link_url' => $logo['link_url'] ?? '',
          ];
        }
      }
    }

    // Replace tokens in the copyright message at render time (not at save time).
    $copyright = $config->get('copyright_message') ?? '';
    if ($copyright) {
      $copyright = \Drupal::service('token')->replace($copyright, [], ['clear' => TRUE]);
    }

    // Build a render array for the formatted text field.
    $text_field_config = $config->get('text_field') ?? [];
    $text_field = [];
    if (!empty($text_field_config['value'])) {
      $text_field = [
        '#type'   => 'processed_text',
        '#text'   => $text_field_config['value'],
        '#format' => $text_field_config['format'] ?? 'basic_html',
      ];
    }

    return [
      '#theme'                 => 'mukurtu_footer',
      '#text_field'            => $text_field,
      '#logos'                 => $logos,
      '#social_accounts'       => $config->get('social_accounts') ?? [],
      '#other_links'           => $config->get('other_links') ?? [],
      '#contact_email_text'    => $config->get('contact_email_text') ?? '',
      '#contact_email_address' => $config->get('contact_email_address') ?? '',
      '#copyright_message'     => $copyright,
      '#cache'                 => [
        'tags' => ['config:mukurtu_footer.settings'],
      ],
    ];
  }

}
