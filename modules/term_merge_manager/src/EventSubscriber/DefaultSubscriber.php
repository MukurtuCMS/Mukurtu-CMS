<?php

namespace Drupal\term_merge_manager\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\path_alias\AliasManagerInterface;
use Drupal\redirect\Entity\Redirect;
use Drupal\term_merge\TermsMergedEvent;
use Drupal\term_merge_manager\Entity\TermMergeFrom;
use Drupal\term_merge_manager\Entity\TermMergeInto;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * The Default Subscriber.
 */
class DefaultSubscriber implements EventSubscriberInterface {

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Alias manager.
   *
   * @var \Drupal\path_alias\AliasManagerInterface
   */
  protected $aliasManager;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs DefaultSubscriber Subscriber.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   * @param \Drupal\path_alias\AliasManagerInterface $alias_manager
   *   The alias manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Configuration factory.
   */
  public function __construct(
    ModuleHandlerInterface $module_handler,
    AliasManagerInterface $alias_manager,
    ConfigFactoryInterface $configFactory
  ) {
    $this->moduleHandler = $module_handler;
    $this->aliasManager = $alias_manager;
    $this->configFactory = $configFactory;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events['term_merge.terms_merged'] = ['termMergeMergeAction'];

    return $events;
  }

  /**
   * The termMergeMergeAction function.
   *
   * This method is called whenever the term_merge.merge_action event is
   * dispatched.
   *
   * @param \Drupal\term_merge\TermsMergedEvent $event
   *   The TermsMergedEvent event.
   */
  public function termMergeMergeAction(TermsMergedEvent $event) {

    $into = $event->getTargetTerm();
    $from = $event->getSourceTerms();

    // Load existing rule or create it.
    $mergeintoid = TermMergeInto::loadIdByTid($into->id());

    if ($mergeintoid === FALSE) {
      $terminto = TermMergeInto::create([
        'tid' => $into->id(),
        'vid' => $into->bundle(),
      ]);
      $terminto->save();
      $mergeintoid = $terminto->id();
    }

    $moduleHandler = $this->moduleHandler;
    $redirectEnabled = FALSE;
    if ($moduleHandler->moduleExists('redirect')) {
      $redirectEnabled = TRUE;
    }

    // Create or update items.
    /** @var \Drupal\taxonomy\TermInterface $item */
    foreach ($from as $item) {
      $vid = $item->bundle();
      $name = $item->getName();

      $from = TermMergeFrom::loadByVidName($vid, $name);
      if ($from === FALSE) {
        $from = TermMergeFrom::create();
      }

      $from->set('tmiid', $mergeintoid);
      $from->set('vid', $vid);
      $from->set('name', $name);
      $from->save();

      // Add redirect if module enabled and auto redirect is checked.
      if ($redirectEnabled) {

        $config = $this->configFactory->get('redirect.settings');
        if (!$config->get('auto_redirect')) {
          return;
        }

        $lang = $item->language()->getId();
        $alias = $this->aliasManager->getAliasByPath('/taxonomy/term/' . $item->id());
        $aliasinto = $this->aliasManager->getAliasByPath('/taxonomy/term/' . $into->id());

        // Delete all redirects having the same source as this alias.
        redirect_delete_by_path($aliasinto, $lang, FALSE);

        // Create redirect from the old path alias to the new one.
        if ($alias != $aliasinto) {
          if (!redirect_repository()->findMatchingRedirect($alias, [], $lang)) {
            $redirect = Redirect::create();
            $redirect->setSource($alias);
            $redirect->setRedirect('/taxonomy/term/' . $into->id());
            $redirect->setLanguage($lang);
            $redirect->setStatusCode($config->get('default_status_code'));
            $redirect->save();
          }
        }
      }
    }
  }

}
