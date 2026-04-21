<?php

namespace Drupal\redirect\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Language\Language;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Url;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Redirect entity form.
 */
class RedirectForm extends ContentEntityForm {

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $form = parent::create($container);
    $form->languageManager = $container->get('language_manager');
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function prepareEntity() {
    /** @var \Drupal\redirect\Entity\Redirect $redirect */
    $redirect = $this->entity;

    if ($redirect->isNew()) {

      // To pass in the query set parameters into GET as follows:
      // source_query[key1]=value1&source_query[key2]=value2
      $source_query = [];
      if ($this->getRequest()->query->has('source_query')) {
        $source_query = $this->getRequest()->query->all()['source_query'];
      }

      $redirect_options = [];
      $redirect_query = [];
      if ($this->getRequest()->query->has('redirect_options')) {
        $redirect_options = $this->getRequest()->query->all()['redirect_options'];
        if (isset($redirect_options['query'])) {
          $redirect_query = $redirect_options['query'];
          unset($redirect_options['query']);
        }
      }

      $source_url = urldecode($this->getRequest()->query->get('source', ''));
      if (!empty($source_url)) {
        $redirect->setSource($source_url, $source_query);
      }

      $redirect_url = urldecode($this->getRequest()->query->get('redirect', ''));
      if (!empty($redirect_url)) {
        $redirect->setRedirect($redirect_url, $redirect_query, $redirect_options);
      }

      $redirect->setLanguage($this->getRequest()->query->get('language') ?: Language::LANGCODE_NOT_SPECIFIED);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    /** @var \Drupal\redirect\Entity\Redirect $redirect */
    $redirect = $this->entity;

    // Only add the configured languages and a single key for all languages.
    if (isset($form['language']['widget'][0]['value'])) {
      foreach ($this->languageManager->getLanguages(LanguageInterface::STATE_CONFIGURABLE) as $langcode => $language) {
        $form['language']['widget'][0]['value']['#options'][$langcode] = $language->getName();
      }
      $form['language']['widget'][0]['value']['#options'][LanguageInterface::LANGCODE_NOT_SPECIFIED] = $this->t('- All languages -');
    }

    $default_code = $redirect->getStatusCode() ?: $this->config('redirect.settings')->get('default_status_code');

    $form['status_code'] = [
      '#type' => 'select',
      '#title' => $this->t('Redirect status'),
      '#description' => $this->t('You can find more information about HTTP redirect status codes at <a href="@status-codes">@status-codes</a>.', ['@status-codes' => 'http://en.wikipedia.org/wiki/List_of_HTTP_status_codes#3xx_Redirection']),
      '#default_value' => $default_code,
      '#options' => redirect_status_code_options(),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $entity = parent::validateForm($form, $form_state);

    $source = $form_state->getValue(['redirect_source', 0]);
    // Trim any trailing spaces from source url, leaving leading space as is.
    // leading space is still a valid candidate to add for 301 source url.
    $source['path'] = rtrim($source['path']);
    $form_state->setValue('redirect_source', [$source]);
    $redirect = $form_state->getValue(['redirect_redirect', 0]);

    if ($source['path'] == '<front>') {
      $form_state->setErrorByName('redirect_source', $this->t('It is not allowed to create a redirect from the front page.'));
    }
    if (str_contains($source['path'], '#')) {
      $form_state->setErrorByName('redirect_source', $this->t('The anchor fragments are not allowed.'));
    }
    if (str_starts_with($source['path'], '/')) {
      $form_state->setErrorByName('redirect_source', $this->t('The url to redirect from should not start with a forward slash (/).'));
    }

    try {
      $source_url = Url::fromUri('internal:/' . $source['path']);
      $redirect_url = Url::fromUri($redirect['uri']);

      // It is relevant to do this comparison only in case the source path has
      // a valid route. Otherwise the validation will fail on the redirect path
      // being an invalid route.
      if ($source_url->toString() == $redirect_url->toString()) {
        $form_state->setErrorByName('redirect_redirect', $this->t('You are attempting to redirect the page to itself. This will result in an infinite loop.'));
      }
    }
    catch (\InvalidArgumentException) {
      // Do nothing, we want to only compare the resulting URLs.
    }

    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $save_flag = $this->entity->save();
    $this->messenger()->addMessage($this->t('The redirect has been saved.'));
    $form_state->setRedirect('redirect.list');
    return $save_flag;
  }

}
