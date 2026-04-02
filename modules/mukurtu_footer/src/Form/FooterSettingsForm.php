<?php

namespace Drupal\mukurtu_footer\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;

/**
 * Provides the Mukurtu Footer settings form.
 */
class FooterSettingsForm extends ConfigFormBase {

  const SETTINGS = 'mukurtu_footer.settings';

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mukurtu_footer_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [static::SETTINGS];
  }

  /**
   * Returns the list of supported social platforms.
   */
  protected function getSocialPlatforms(): array {
    return [
      'twitter'   => $this->t('Twitter / X'),
      'facebook'  => $this->t('Facebook'),
      'instagram' => $this->t('Instagram'),
      'youtube'   => $this->t('YouTube'),
      'linkedin'  => $this->t('LinkedIn'),
      'tiktok'    => $this->t('TikTok'),
      'bluesky'   => $this->t('Bluesky'),
      'mastodon'  => $this->t('Mastodon'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config(static::SETTINGS);

    // --- Text field (element order: first) ---
    $form['text_field'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Footer text'),
      '#description' => $this->t('Formatted text displayed at the top of the footer. Supports links.'),
      '#default_value' => $config->get('text_field.value') ?? '',
      '#format' => $config->get('text_field.format') ?? 'mukurtu_html',
    ];

    // --- Logos (element order: second) ---
    $saved_logos = $config->get('logos') ?? [];
    $logo_count = $form_state->get('logo_count');
    if ($logo_count === NULL) {
      $logo_count = max(count($saved_logos), 1);
      $form_state->set('logo_count', $logo_count);
    }

    $form['logos_fieldset'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Logos'),
      '#description' => $this->t('Upload logos to display in the footer. To remove a logo, use the "Remove" button on the image, then save.'),
      '#prefix' => '<div id="logos-wrapper">',
      '#suffix' => '</div>',
      '#tree' => TRUE,
    ];

    for ($i = 0; $i < $logo_count; $i++) {
      $saved = $saved_logos[$i] ?? [];
      $form['logos_fieldset']['logo_' . $i] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Logo @num', ['@num' => $i + 1]),
      ];
      $form['logos_fieldset']['logo_' . $i]['fid'] = [
        '#type' => 'managed_file',
        '#title' => $this->t('Image file'),
        '#upload_location' => 'public://footer/',
        '#upload_validators' => [
          'FileExtension' => ['extensions' => 'png gif jpg jpeg svg webp'],
        ],
        '#default_value' => !empty($saved['fid']) ? [$saved['fid']] : [],
      ];
      $form['logos_fieldset']['logo_' . $i]['alt'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Alt text'),
        '#default_value' => $saved['alt'] ?? '',
        '#maxlength' => 255,
      ];
      $form['logos_fieldset']['logo_' . $i]['link_url'] = [
        '#type' => 'url',
        '#title' => $this->t('Link URL (optional)'),
        '#description' => $this->t('Wrap the logo in a link to this URL.'),
        '#default_value' => $saved['link_url'] ?? '',
      ];
    }

    $form['logos_fieldset']['add_logo'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add another logo'),
      '#submit' => ['::addLogoSubmit'],
      '#ajax' => [
        'callback' => '::updateLogosWrapper',
        'wrapper' => 'logos-wrapper',
      ],
      '#limit_validation_errors' => [],
    ];

    // --- Contact email ---
    $form['contact'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Contact email'),
      '#tree' => TRUE,
    ];
    $form['contact']['contact_email_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Link text'),
      '#description' => $this->t('Label shown before the email address. Leave both fields empty to hide.'),
      '#default_value' => $config->get('contact_email_text') ?: 'Email us at',
      '#maxlength' => 255,
    ];
    $form['contact']['contact_email_address'] = [
      '#type' => 'email',
      '#title' => $this->t('Email address'),
      '#default_value' => $config->get('contact_email_address') ?? '',
    ];

    // --- Social media accounts (element order: third) ---
    $saved_socials = $config->get('social_accounts') ?? [];
    $social_count = $form_state->get('social_count');
    if ($social_count === NULL) {
      $social_count = max(count($saved_socials), 1);
      $form_state->set('social_count', $social_count);
    }

    $form['social_fieldset'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Social media accounts'),
      '#description' => $this->t('Add links to social media profiles. Leave URL empty to omit on save.'),
      '#prefix' => '<div id="social-wrapper">',
      '#suffix' => '</div>',
      '#tree' => TRUE,
    ];

    $platforms = $this->getSocialPlatforms();

    for ($i = 0; $i < $social_count; $i++) {
      $saved = $saved_socials[$i] ?? [];
      $form['social_fieldset']['social_' . $i] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Account @num', ['@num' => $i + 1]),
      ];
      $form['social_fieldset']['social_' . $i]['platform'] = [
        '#type' => 'select',
        '#title' => $this->t('Platform'),
        '#options' => ['' => $this->t('- Select -')] + $platforms,
        '#default_value' => $saved['platform'] ?? '',
      ];
      $form['social_fieldset']['social_' . $i]['url'] = [
        '#type' => 'url',
        '#title' => $this->t('Profile URL'),
        '#default_value' => $saved['url'] ?? '',
      ];
      $form['social_fieldset']['social_' . $i]['label'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Display label'),
        '#description' => $this->t('Text shown for the link, e.g. @username or "Our Facebook page".'),
        '#default_value' => $saved['label'] ?? '',
        '#maxlength' => 255,
      ];
    }

    $form['social_fieldset']['add_social'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add another account'),
      '#submit' => ['::addSocialSubmit'],
      '#ajax' => [
        'callback' => '::updateSocialWrapper',
        'wrapper' => 'social-wrapper',
      ],
      '#limit_validation_errors' => [],
    ];

    // --- Other links (element order: fourth) ---
    $saved_links = $config->get('other_links') ?? [];
    $link_count = $form_state->get('link_count');
    if ($link_count === NULL) {
      $link_count = max(count($saved_links), 1);
      $form_state->set('link_count', $link_count);
    }

    $form['links_fieldset'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Other links'),
      '#description' => $this->t('Links to organizational pages, partners, privacy policy, etc. Leave URL empty to omit on save.'),
      '#prefix' => '<div id="links-wrapper">',
      '#suffix' => '</div>',
      '#tree' => TRUE,
    ];

    for ($i = 0; $i < $link_count; $i++) {
      $saved = $saved_links[$i] ?? [];
      $form['links_fieldset']['link_' . $i] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Link @num', ['@num' => $i + 1]),
      ];
      $form['links_fieldset']['link_' . $i]['url'] = [
        '#type' => 'url',
        '#title' => $this->t('URL'),
        '#default_value' => $saved['url'] ?? '',
      ];
      $form['links_fieldset']['link_' . $i]['label'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Label'),
        '#default_value' => $saved['label'] ?? '',
        '#maxlength' => 255,
      ];
    }

    $form['links_fieldset']['add_link'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add another link'),
      '#submit' => ['::addLinkSubmit'],
      '#ajax' => [
        'callback' => '::updateLinksWrapper',
        'wrapper' => 'links-wrapper',
      ],
      '#limit_validation_errors' => [],
    ];

    // --- Copyright (element order: last) ---
    $form['copyright_message'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Copyright message'),
      '#description' => $this->t('Use the token [current-date:html_year] for the current year. Leave empty to hide.'),
      '#default_value' => $config->get('copyright_message') ?? '',
      '#maxlength' => 255,
    ];

    return parent::buildForm($form, $form_state);
  }

  // --- AJAX add-more handlers ---

  /**
   * Submit handler for "Add another logo".
   */
  public function addLogoSubmit(array &$form, FormStateInterface $form_state) {
    $form_state->set('logo_count', $form_state->get('logo_count') + 1);
    $form_state->setRebuild();
  }

  /**
   * AJAX callback returning the logos fieldset.
   */
  public function updateLogosWrapper(array &$form, FormStateInterface $form_state) {
    return $form['logos_fieldset'];
  }

  /**
   * Submit handler for "Add another account".
   */
  public function addSocialSubmit(array &$form, FormStateInterface $form_state) {
    $form_state->set('social_count', $form_state->get('social_count') + 1);
    $form_state->setRebuild();
  }

  /**
   * AJAX callback returning the social fieldset.
   */
  public function updateSocialWrapper(array &$form, FormStateInterface $form_state) {
    return $form['social_fieldset'];
  }

  /**
   * Submit handler for "Add another link".
   */
  public function addLinkSubmit(array &$form, FormStateInterface $form_state) {
    $form_state->set('link_count', $form_state->get('link_count') + 1);
    $form_state->setRebuild();
  }

  /**
   * AJAX callback returning the links fieldset.
   */
  public function updateLinksWrapper(array &$form, FormStateInterface $form_state) {
    return $form['links_fieldset'];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config(static::SETTINGS);

    // Formatted text field.
    $text_field = $form_state->getValue('text_field');
    $config->set('text_field.value', $text_field['value'] ?? '');
    $config->set('text_field.format', $text_field['format'] ?? 'mukurtu_html');

    // Contact email.
    $config->set('contact_email_text', $form_state->getValue(['contact', 'contact_email_text']) ?? '');
    $config->set('contact_email_address', $form_state->getValue(['contact', 'contact_email_address']) ?? '');

    // Logos — diff old vs new fids to manage file usage and permanence.
    $file_usage = \Drupal::service('file.usage');
    $old_fids = array_column($config->get('logos') ?? [], 'fid');

    $logo_count = $form_state->get('logo_count') ?? count($config->get('logos') ?? []);
    $logos = [];
    $new_fids = [];
    for ($i = 0; $i < $logo_count; $i++) {
      $logo_data = $form_state->getValue(['logos_fieldset', 'logo_' . $i]);
      $fids = array_filter((array) ($logo_data['fid'] ?? []));
      if (!empty($fids)) {
        $fid = (int) reset($fids);
        $file = File::load($fid);
        if ($file) {
          $new_fids[] = $fid;
          // Only mark permanent and add usage for newly added files.
          if (!in_array($fid, $old_fids)) {
            $file->setPermanent();
            $file->save();
            $file_usage->add($file, 'mukurtu_footer', 'config', 1);
          }
          $logos[] = [
            'fid'      => $fid,
            'alt'      => $logo_data['alt'] ?? '',
            'link_url' => $logo_data['link_url'] ?? '',
          ];
        }
      }
    }

    // Release usage and set temporary for any removed files.
    foreach (array_diff($old_fids, $new_fids) as $removed_fid) {
      $file = File::load($removed_fid);
      if ($file) {
        $file_usage->delete($file, 'mukurtu_footer', 'config', 1);
        // Only set temporary if nothing else is using the file.
        if (empty($file_usage->listUsage($file))) {
          $file->setTemporary();
          $file->save();
        }
      }
    }

    $config->set('logos', $logos);

    // Social accounts — filter entries with no URL.
    $social_count = $form_state->get('social_count');
    $social_accounts = [];
    for ($i = 0; $i < $social_count; $i++) {
      $social_data = $form_state->getValue(['social_fieldset', 'social_' . $i]);
      if (!empty($social_data['url'])) {
        $social_accounts[] = [
          'platform' => $social_data['platform'] ?? '',
          'url'      => $social_data['url'],
          'label'    => $social_data['label'] ?? '',
        ];
      }
    }
    $config->set('social_accounts', $social_accounts);

    // Other links — filter entries with no URL.
    $link_count = $form_state->get('link_count');
    $other_links = [];
    for ($i = 0; $i < $link_count; $i++) {
      $link_data = $form_state->getValue(['links_fieldset', 'link_' . $i]);
      if (!empty($link_data['url'])) {
        $other_links[] = [
          'url'   => $link_data['url'],
          'label' => $link_data['label'] ?? '',
        ];
      }
    }
    $config->set('other_links', $other_links);

    // Copyright message — stored as raw string; token replaced at render time.
    $config->set('copyright_message', $form_state->getValue('copyright_message') ?? '');

    $config->save();

    parent::submitForm($form, $form_state);
  }

}
