<?php

namespace Drupal\consent_popup\Plugin\Block;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Language\LanguageManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Drupal\Component\Utility\Xss;

/**
 * Provides an consent popup block.
 *
 * @Block(
 *   id = "consent_popup",
 *   admin_label = @Translation("Consent Popup"),
 *   category = @Translation("Custom")
 * )
 */
class ConsentPopupBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Constructs a new ConsentPopupBlock object.
   *
   * @param array $configuration
   *   The block configuration.
   * @param string $plugin_id
   *   The block plugin ID.
   * @param mixed $plugin_definition
   *   The block plugin definition.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, LanguageManagerInterface $language_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('language_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $config = $this->getConfiguration();
    $form = parent::blockForm($form, $form_state);
    $languages = $this->languageManager->getLanguages();
    foreach ($languages as $key => $language) {
      $frontPageUrl = Url::fromRoute('<front>', [], [
        'language' => $language,
      ]);
      $form[$key] = [
        '#type' => 'details',
        '#title' => $language->getName(),
        '#collapsible' => TRUE,
        '#collapsed' => TRUE,
      ];
      $form[$key]['text'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Popup Text'),
        '#default_value' => $config[$key]['text'] ?? $this->t("Are you an adult?"),
        '#required' => TRUE,
      ];
      $form[$key]['text_decline'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Declined Text'),
        '#default_value' => $config[$key]['text_decline'] ??
        $this->t("You can't access this page"),
        '#required' => TRUE,
      ];
      $form[$key]['accept'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Accept button text'),
        '#default_value' => $config[$key]['accept'] ?? $this->t('Yes'),
        '#description' => $this->t('Default value: Yes'),
        '#required' => TRUE,
      ];
      $form[$key]['decline'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Decline button text'),
        '#default_value' => $config[$key]['decline'] ?? $this->t('No'),
        '#description' => $this->t('Default value: No'),
        '#required' => TRUE,
      ];
      $form[$key]['decline_link'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Decline url options'),
        '#description' => $this->t('Options for the link to show when declined'),
      ];
      $form[$key]['decline_link']['decline_url'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Link url if declined'),
        '#default_value' => $config[$key]['decline_link']['decline_url'] ?? $frontPageUrl->toString(),
        '#description' => $this->t("Default value @frontpage", ['@frontpage' => $frontPageUrl->toString()]),
        '#required' => TRUE,
      ];
      $form[$key]['decline_link']['decline_url_text'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Text for url if declined'),
        '#default_value' => $config[$key]['decline_link']['decline_url_text'] ?? $this->t('Keep browsing our site'),
        '#description' => $this->t("Link text. Default value 'Keep browsing our site'"),
        '#required' => TRUE,
      ];
    }
    $form['non_blocking'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Non blocking'),
      '#default_value' => $config['non_blocking'] ?? FALSE,
      '#description' => $this->t("Allow user to see the page even if declined"),
    ];
    $form['redirect'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Redirect on declined'),
      '#default_value' => $config['redirect'] ?? FALSE,
      '#description' => $this->t("Automatically redirect to the declined url"),
    ];
    $form['cookie'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Cookie Info'),
      '#description' => $this->t('Options for the link to show when declined'),
    ];
    $form['cookie']['cookie_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Cookie Name'),
      '#default_value' => $config['cookie']['cookie_name'] ?? 'consent_popup',
      '#description' => $this->t("Name of the cookie (defaults to consent_popup)"),
      '#required' => TRUE,
    ];
    $form['cookie']['cookie_life'] = [
      '#type' => 'number',
      '#title' => $this->t('Cookie life time'),
      '#default_value' => $config['cookie']['cookie_life'] ?? 7,
      '#description' => $this->t("how many days until the cookie is deleted (defaults to 7)"),
    ];
    $form['design'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Cookie Info'),
      '#description' => $this->t('Options for the link to show when declined'),
    ];
    $form['design']['color'] = [
      '#type' => 'color',
      '#title' => $this
        ->t('Background Color'),
      '#default_value' => $config['design']['color'] ?? '#000000',
    ];
    $form['design']['color_opacity'] = [
      '#type' => 'select',
      '#title' => $this->t('Background Opacity'),
      '#description' => $this->t('Chose the filter opacity'),
      '#options' => range(0, 1, 0.1),
      '#default_value' => $config['design']['color_opacity'] ?? '8',
    ];
    $form['design']['blur'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Elements to blur'),
      '#default_value' => $config['design']['blur'] ?? '',
      '#description' => $this->t("Use css selectors to chose elements to blur separated with ,"),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    parent::blockSubmit($form, $form_state);
    $values = $form_state->getValues();
    $this->configuration = $values;
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $language = $this->languageManager->getCurrentLanguage();
    $languageKey = $language->getId();
    $config = $this->getConfiguration();
    [$r, $g, $b] = sscanf($config['design']['color'], "#%02x%02x%02x");
    $opacity = $config['design']['color_opacity'] / 10;
    $color = "rgb(" . $r . " " . $g . " " . $b . " / " . $opacity . ")";
    $blurElements = explode(',', $config['design']['blur']);

    $decline_url_value = $config[$languageKey]['decline_link']['decline_url'] ?? '';
    if (preg_match('#^https?://#', $decline_url_value)) {
      // External URL.
      $decline_url = $decline_url_value;
    }
    else {
      // Internal path.
      $decline_url = Url::fromUserInput('/' . ltrim($decline_url_value, '/'), ['absolute' => TRUE])->toString();
    }

    $build = [
      '#theme' => 'consent_popup',
      '#items' => [
        'text' => Xss::filterAdmin($config[$languageKey]['text']),
        'accept' => $config[$languageKey]['accept'],
        'decline' => $config[$languageKey]['decline'],
        'url' => $decline_url,
        'url_text' => $config[$languageKey]['decline_link']['decline_url_text'],
      ],
      '#attached' => [
        'library' => [
          'consent_popup/consent_popup',
        ],
        'drupalSettings' => [
          'consent_popup' => [
            'cookie_life' => $config['cookie']['cookie_life'],
            'cookie_name' => $config['cookie']['cookie_name'],
            'bg_color' => $color,
            'text_decline' => Xss::filterAdmin($config[$languageKey]['text_decline']),
            'to_blur' => $blurElements,
            'non_blocking' => $config['non_blocking'],
            'redirect' => $config['redirect'],
            'redirect_url' => $decline_url,
          ],
        ],
      ],
    ];
    return $build;
  }

}
