<?php

namespace Drupal\mukurtu_footer\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;

/**
 * Provides configurable Mukurtu Footer block.
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
  public function build()
  {
    $config = $this->getConfiguration();
    // Right now, logo_upload contains fids of the logos, which isn't useful for
    // templating. Instead, let's load the logo file entities to get a url to
    // put in the img src attribute. Filename is used as the alt text for now.
    foreach ($config['logo_upload'] as $i => $fid) {
      $logoFile = File::load($fid);
      if ($logoFile) {
        $logoUrl = \Drupal::service('file_url_generator')->generateAbsoluteString($logoFile->getFileUri());
        $this->configuration['logo_upload'][$i] = [
          'url' => $logoUrl,
          'alt' => $logoFile->getFilename(),
        ];
      }
    }
    // We must refresh the config object to get our changes.
    $config = $this->getConfiguration();
    return [
      '#theme' => 'mukurtu_footer',
      '#logos' => $config['logo_upload'],
      '#email_us_text' => $config['email_us_text'],
      '#contact_email_address' => $config['contact_email_address'],
      '#twitter' => $config['social_media']['twitter']['message_text'],
      '#twitter_accounts' => [
        $config['social_media']['twitter']['account_1'],
        $config['social_media']['twitter']['account_2'],
        $config['social_media']['twitter']['account_3'],
      ],
      '#facebook' => $config['social_media']['facebook']['message_text'],
      '#facebook_accounts' => [
        $config['social_media']['facebook']['account_1'],
        $config['social_media']['facebook']['account_2'],
        $config['social_media']['facebook']['account_3'],
      ],
      '#instagram' => $config['social_media']['instagram']['message_text'],
      '#instagram_accounts' => [
        $config['social_media']['instagram']['account_1'],
        $config['social_media']['instagram']['account_2'],
        $config['social_media']['instagram']['account_3'],
      ],
      '#copyright_message' => $config['copyright_message'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $config = $this->getConfiguration();

    $form['logo_upload'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Logo(s)'),
      '#description' => $this->t('Supports multiple images.'),
      '#upload_location' => $this->t("private://"),
      '#upload_validators' => [
        'file_validate_extensions' => ['png gif jpg jpeg'],
      ],
      '#multiple' => TRUE,
      '#default_value' => $config['logo_upload'] ?? '',
    ];

    $form['email_us_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Email us text'),
      '#description' => $this->t('Leave empty to omit from display.'),
      '#default_value' => $config['email_us_text'] ?? '',
      '#maxlength' => '255',
    ];

    $form['contact_email_address'] = [
      '#type' => 'email',
      '#title' => $this->t('Contact email address'),
      '#description' => $this->t('Leave empty to omit from display.'),
      '#default_value' => $config['contact_email_address'] ?? '',
      '#maxlength' => 64,
      '#size' => 30,
    ];

    $form['social_media'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Social media'),
    ];

    // Add options for Twitter (X) social media accounts.
    $form['social_media']['twitter'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Twitter (X)'),
    ];
    $form['social_media']['twitter']['message_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Message text'),
      '#description' => $this->t('Displays in front of Twitter (X) social media account links.'),
      '#default_value' => $config['social_media']['twitter']['message_text'] ?? '',
    ];
    $form['social_media']['twitter']['account_1'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Account 1'),
      '#default_value' => $config['social_media']['twitter']['account_1'] ?? '',
    ];
    $form['social_media']['twitter']['account_2'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Account 2'),
      '#default_value' => $config['social_media']['twitter']['account_2'] ?? '',
    ];
    $form['social_media']['twitter']['account_3'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Account 3'),
      '#default_value' => $config['social_media']['twitter']['account_3'] ?? '',
    ];

    // Add options for Facebook social media accounts.
    $form['social_media']['facebook'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Facebook'),
    ];
    $form['social_media']['facebook']['message_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Message text'),
      '#description' => $this->t('Displays in front of Facebook social media account links.'),
      '#default_value' => $config['social_media']['facebook']['message_text'] ?? '',
    ];
    $form['social_media']['facebook']['account_1'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Account 1'),
      '#default_value' => $config['social_media']['facebook']['account_1'] ?? '',
    ];
    $form['social_media']['facebook']['account_2'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Account 2'),
      '#default_value' => $config['social_media']['facebook']['account_2'] ?? '',
    ];
    $form['social_media']['facebook']['account_3'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Account 3'),
      '#default_value' => $config['social_media']['facebook']['account_3'] ?? '',
    ];

    // Add options for Instagram social media accounts.
    $form['social_media']['instagram'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Instagram'),
    ];
    $form['social_media']['instagram']['message_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Message text'),
      '#description' => $this->t('Displays in front of Instagram social media account links.'),
      '#default_value' => $config['social_media']['instagram']['message_text'] ?? '',
    ];

    $form['social_media']['instagram']['account_1'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Account 1'),
      '#default_value' => $config['social_media']['instagram']['account_1'] ?? '',
    ];
    $form['social_media']['instagram']['account_2'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Account 2'),
      '#default_value' => $config['social_media']['instagram']['account_2'] ?? '',
    ];
    $form['social_media']['instagram']['account_3'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Account 3'),
      '#default_value' => $config['social_media']['instagram']['account_3'] ?? '',
    ];

    // Copyright message.
    $form['copyright_message'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Footer copyright message'),
      '#description' => $this->t('Use replacement token [current-date:html_year] for the current year.'),
      '#default_value' => $config['copyright_message'] ?? '',
    ];
    // Add the token tree UI.
    $form['token_wrapper'] = [
      '#type' => 'item',
    ];
    $form['token_wrapper']['token_tree'] = [
      '#theme' => 'token_tree_link',
      '#token_types' => ['user', 'node'],
      '#show_restricted' => FALSE,
      '#weight' => 90,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    parent::blockSubmit($form, $form_state);

    $this->configuration['logo_upload'] = $form_state->getValue('logo_upload');

    // Email us at text
    $this->configuration['email_us_text'] = $form_state->getValue('email_us_text');

    // Contact email address
    $this->configuration['contact_email_address'] = $form_state->getValue('contact_email_address');

    // Twitter message text
    $this->configuration['social_media']['twitter']['message_text'] = $form_state->getValue(['social_media', 'twitter', 'message_text']);

    // Twitter accounts
    for ($i = 1; $i <= 3; $i++) {
      $this->configuration['social_media']['twitter']['account_' . $i] = $form_state->getValue(['social_media', 'twitter', 'account_' . $i]);
    }

    // Facebook message text
    $this->configuration['social_media']['facebook']['message_text'] = $form_state->getValue(['social_media', 'facebook', 'message_text']);

    // Facebook accounts
    for ($i = 1; $i <= 3; $i++) {
      $this->configuration['social_media']['facebook']['account_' . $i] = $form_state->getValue(['social_media', 'facebook', 'account_' . $i]);
    }

    // Instagram message text
    $this->configuration['social_media']['instagram']['message_text'] = $form_state->getValue(['social_media', 'instagram', 'message_text']);

    // Instagram accounts
    for ($i = 1; $i <= 3; $i++) {
      $this->configuration['social_media']['instagram']['account_' . $i] = $form_state->getValue(['social_media', 'instagram', 'account_' . $i]);
    }

    // Footer copyright message
    $tokenService = \Drupal::service("token");

    // Token::replace() requires a keyed array of token types.
    // Some tokens are not replaced by default.
    $entity = $form_state->getformObject()->getEntity();
    $data = [
      'node' => $entity,
      'language' => $entity->language(),
      'random' => $entity,
    ];

    // Clear tokens that do not have a replacement value.
    $options = [
      'clear' => TRUE
    ];

    // Replace tokens in footer copyright message.
    $this->configuration['copyright_message'] = $tokenService->replace($form_state->getValue('copyright_message'), $data, $options);
  }
}
