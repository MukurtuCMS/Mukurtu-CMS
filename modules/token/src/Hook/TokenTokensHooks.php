<?php

namespace Drupal\token\Hook;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\TitleResolverInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\Image\ImageFactory;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Menu\MenuLinkManagerInterface;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\RouteObjectInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\Core\Utility\Token;
use Drupal\image\Entity\ImageStyle;
use Drupal\menu_link_content\MenuLinkContentInterface;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\path_alias\AliasManagerInterface;
use Drupal\system\Entity\Menu;
use Drupal\token\TokenEntityMapperInterface;
use Drupal\token\TokenModuleProvider;
use Drupal\user\UserInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tokens hook implementations for token.
 */
final class TokenTokensHooks {

  use StringTranslationTrait;

  public function __construct(
    protected ModuleHandlerInterface $moduleHandler,
    protected RendererInterface $renderer,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityRepositoryInterface $entityRepository,
    protected Token $token,
    protected TokenEntityMapperInterface $tokenEntityMapper,
    protected TokenModuleProvider $tokenModuleProvider,
    protected DateFormatterInterface $dateFormatter,
    protected TimeInterface $time,
    protected LanguageManagerInterface $languageManager,
    protected MenuLinkManagerInterface $menuLinkManager,
    protected FieldTypePluginManagerInterface $fieldTypePluginManager,
    protected RequestStack $requestStack,
    protected ConfigFactoryInterface $configFactory,
    protected ImageFactory $imageFactory,
    protected ?AliasManagerInterface $aliasManager,
    protected TitleResolverInterface $titleResolver,
    protected CurrentPathStack $currentPathStack,
  ) {
  }

  /**
   * Implements hook_tokens().
   */
  #[Hook('tokens')]
  public function tokens($type, array $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata) {
    $replacements = [];
    // Call proxy implementations.
    if ($this->moduleHandler->moduleExists('book')) {
      $replacements += $this->bookTokens($type, $tokens, $data, $options, $bubbleable_metadata);
    }
    if ($this->moduleHandler->moduleExists('menu_ui')) {
      $replacements += $this->menuUiTokens($type, $tokens, $data, $options, $bubbleable_metadata);
    }
    if ($this->moduleHandler->moduleExists('field')) {
      $replacements += $this->fieldTokens($type, $tokens, $data, $options, $bubbleable_metadata);
    }
    $language_manager = $this->languageManager;
    $url_options = [
      'absolute' => TRUE,
    ];
    if (isset($options['langcode'])) {
      $url_options['language'] = $language_manager->getLanguage($options['langcode']);
      $langcode = $options['langcode'];
    }
    else {
      $langcode = $language_manager->getCurrentLanguage()->getId();
    }
    // Date tokens.
    if ($type == 'date') {
      $date = !empty($data['date']) ? $data['date'] : $this->time->getRequestTime();
      // @todo Remove when http://drupal.org/node/1173706 is fixed.
      $date_format_types = $this->entityTypeManager->getStorage('date_format')->loadMultiple();
      foreach ($tokens as $name => $original) {
        if (isset($date_format_types[$name]) && $this->tokenModuleProvider->getTokenModule('date', $name) == 'token') {
          $replacements[$original] = $this->dateFormatter->format($date, $name, '', NULL, $langcode);
        }
      }
    }
    // Current date tokens.
    // @todo Remove when http://drupal.org/node/943028 is fixed.
    if ($type == 'current-date') {
      $replacements += $this->token->generate('date', $tokens, [
        'date' => $this->time->getRequestTime(),
      ], $options, $bubbleable_metadata);
    }
    // Comment tokens.
    if ($type == 'comment' && !empty($data['comment'])) {
      /** @var \Drupal\comment\CommentInterface $comment */
      $comment = $data['comment'];
      // Chained token relationships.
      if ($url_tokens = $this->token->findWithPrefix($tokens, 'url')) {
        // Add fragment to url options.
        $replacements += $this->token->generate('url', $url_tokens, [
          'url' => $comment->toUrl('canonical', [
            'fragment' => "comment-{$comment->id()}",
          ]),
        ], $options, $bubbleable_metadata);
      }
    }
    // Node tokens.
    if ($type == 'node' && !empty($data['node'])) {
      /** @var \Drupal\node\NodeInterface $node */
      $node = $data['node'];
      foreach ($tokens as $name => $original) {
        switch ($name) {
          case 'log':
            $replacements[$original] = (string) $node->revision_log->value;
            break;

          case 'content-type':
            $type_name = $this->entityTypeManager->getStorage('node_type')->load($node->getType())->label();
            $replacements[$original] = $type_name;
            break;
        }
      }
      // Chained token relationships.
      if (($parent_tokens = $this->token->findWithPrefix($tokens, 'source')) && $source_node = $node->getUntranslated()) {
        $replacements += $this->token->generate('node', $parent_tokens, [
          'node' => $source_node,
        ], $options, $bubbleable_metadata);
      }
      if (($node_type_tokens = $this->token->findWithPrefix($tokens, 'content-type')) && $node_type = NodeType::load($node->bundle())) {
        $replacements += $this->token->generate('content-type', $node_type_tokens, [
          'node_type' => $node_type,
        ], $options, $bubbleable_metadata);
      }
      if ($url_tokens = $this->token->findWithPrefix($tokens, 'url')) {
        $replacements += $this->token->generate('url', $url_tokens, [
          'url' => $node->toUrl(),
        ], $options, $bubbleable_metadata);
      }
    }
    // Content type tokens.
    if ($type == 'content-type' && !empty($data['node_type'])) {
      /** @var \Drupal\node\NodeTypeInterface $node_type */
      $node_type = $data['node_type'];
      foreach ($tokens as $name => $original) {
        switch ($name) {
          case 'name':
            $replacements[$original] = $node_type->label();
            break;

          case 'machine-name':
            $replacements[$original] = $node_type->id();
            break;

          case 'description':
            $replacements[$original] = $node_type->getDescription();
            break;

          case 'node-count':
            $count = $this->entityTypeManager->getStorage('node')->getAggregateQuery()->aggregate('nid', 'COUNT')->condition('type', $node_type->id())->accessCheck(TRUE)->execute();
            $replacements[$original] = (int) $count;
            break;

          case 'edit-url':
            $result = $node_type->toUrl('edit-form', $url_options)->toString(TRUE);
            $bubbleable_metadata->addCacheableDependency($result);
            $replacements[$original] = $result->getGeneratedUrl();
            break;
        }
      }
    }
    // Taxonomy term tokens.
    if ($type == 'term' && !empty($data['term'])) {
      /** @var \Drupal\taxonomy\TermInterface $term */
      $term = $data['term'];
      /** @var \Drupal\taxonomy\TermStorageInterface $term_storage */
      $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
      foreach ($tokens as $name => $original) {
        switch ($name) {
          case 'edit-url':
            $result = Url::fromRoute('entity.taxonomy_term.edit_form', [
              'taxonomy_term' => $term->id(),
            ], $url_options)->toString(TRUE);
            $bubbleable_metadata->addCacheableDependency($result);
            $replacements[$original] = $result->getGeneratedUrl();
            break;

          case 'parents':
            if ($parents = token_taxonomy_term_load_all_parents($term->id(), $langcode)) {
              $replacements[$original] = token_render_array($parents, $options);
            }
            break;

          case 'root':
            $parents = $term_storage->loadAllParents($term->id());
            $root_term = end($parents);
            if ($root_term->id() != $term->id()) {
              $root_term = $this->entityRepository->getTranslationFromContext($root_term, $langcode);
              $replacements[$original] = $root_term->label();
            }
            break;
        }
      }
      // Chained token relationships.
      if (($parent_tokens = $this->token->findWithPrefix($tokens, 'source')) && $source_term = $this->entityRepository->getTranslationFromContext($term, LanguageInterface::LANGCODE_DEFAULT)) {
        $replacements += $this->token->generate('term', $parent_tokens, [
          'term' => $source_term,
        ], [
          'langcode' => $source_term->language()->getId(),
        ] + $options, $bubbleable_metadata);
      }
      if ($url_tokens = $this->token->findWithPrefix($tokens, 'url')) {
        $replacements += $this->token->generate('url', $url_tokens, [
          'url' => $term->toUrl(),
        ], $options, $bubbleable_metadata);
      }
      // [term:parents:*] chained tokens.
      if ($parents_tokens = $this->token->findWithPrefix($tokens, 'parents')) {
        if ($parents = token_taxonomy_term_load_all_parents($term->id(), $langcode)) {
          $replacements += $this->token->generate('array', $parents_tokens, [
            'array' => $parents,
          ], $options, $bubbleable_metadata);
        }
      }
      if ($root_tokens = $this->token->findWithPrefix($tokens, 'root')) {
        $parents = $term_storage->loadAllParents($term->id());
        $root_term = end($parents);
        if ($root_term->tid != $term->id()) {
          $replacements += $this->token->generate('term', $root_tokens, [
            'term' => $root_term,
          ], $options, $bubbleable_metadata);
        }
      }
    }
    // Vocabulary tokens.
    if ($type == 'vocabulary' && !empty($data['vocabulary'])) {
      $vocabulary = $data['vocabulary'];
      foreach ($tokens as $name => $original) {
        switch ($name) {
          case 'machine-name':
            $replacements[$original] = $vocabulary->id();
            break;

          case 'edit-url':
            $result = Url::fromRoute('entity.taxonomy_vocabulary.edit_form', [
              'taxonomy_vocabulary' => $vocabulary->id(),
            ], $url_options)->toString(TRUE);
            $bubbleable_metadata->addCacheableDependency($result);
            $replacements[$original] = $result->getGeneratedUrl();
            break;
        }
      }
    }
    // File tokens.
    if ($type == 'file' && !empty($data['file'])) {
      $file = $data['file'];
      foreach ($tokens as $name => $original) {
        switch ($name) {
          case 'basename':
            $basename = pathinfo($file->uri->value, PATHINFO_BASENAME);
            $replacements[$original] = $basename;
            break;

          case 'extension':
            $extension = pathinfo($file->uri->value, PATHINFO_EXTENSION);
            $replacements[$original] = $extension;
            break;

          case 'size-raw':
            $replacements[$original] = (int) $file->filesize->value;
            break;
        }
      }
    }
    // User tokens.
    if ($type == 'user' && !empty($data['user'])) {
      /** @var \Drupal\user\UserInterface $account */
      $account = $data['user'];
      foreach ($tokens as $name => $original) {
        switch ($name) {
          case 'picture':
            if ($account instanceof UserInterface && $account->hasField('user_picture')) {
              $output = [
                '#theme' => 'user_picture',
                '#account' => $account,
              ];
              $replacements[$original] = $this->renderer->renderInIsolation($output);
            }
            break;

          case 'roles':
            $roles = $account->getRoles();
            $roles_names = array_combine($roles, $roles);
            $replacements[$original] = token_render_array($roles_names, $options);
            break;
        }
      }
      // Chained token relationships.
      if ($account instanceof UserInterface && $account->hasField('user_picture') && $picture_tokens = $this->token->findWithPrefix($tokens, 'picture')) {
        $replacements += $this->token->generate('file', $picture_tokens, [
          'file' => $account->user_picture->entity,
        ], $options, $bubbleable_metadata);
      }
      if ($url_tokens = $this->token->findWithPrefix($tokens, 'url')) {
        $replacements += $this->token->generate('url', $url_tokens, [
          'url' => $account->toUrl(),
        ], $options, $bubbleable_metadata);
      }
      if ($role_tokens = $this->token->findWithPrefix($tokens, 'roles')) {
        $roles = $account->getRoles();
        $roles_names = array_combine($roles, $roles);
        $replacements += $this->token->generate('array', $role_tokens, [
          'array' => $roles_names,
        ], $options, $bubbleable_metadata);
      }
    }
    // Current user tokens.
    if ($type == 'current-user') {
      foreach ($tokens as $name => $original) {
        switch ($name) {
          case 'ip-address':
            $ip = $this->requestStack->getCurrentRequest()->getClientIp();
            $replacements[$original] = $ip;
            break;
        }
      }
    }
    // Menu link tokens.
    if ($type == 'menu-link' && !empty($data['menu-link'])) {
      /** @var \Drupal\Core\Menu\MenuLinkInterface $link */
      $link = $data['menu-link'];
      if ($link instanceof MenuLinkContentInterface) {
        $link = $this->menuLinkManager->createInstance($link->getPluginId());
      }
      foreach ($tokens as $name => $original) {
        switch ($name) {
          case 'id':
            $replacements[$original] = $link->getPluginId();
            break;

          case 'title':
            $replacements[$original] = token_menu_link_translated_title($link, $langcode);
            break;

          case 'url':
            $result = $link->getUrlObject()->setAbsolute()->toString(TRUE);
            $bubbleable_metadata->addCacheableDependency($result);
            $replacements[$original] = $result->getGeneratedUrl();
            break;

          case 'parent':
            /** @var \Drupal\Core\Menu\MenuLinkInterface $parent */
            if ($link->getParent() && $parent = $this->menuLinkManager->createInstance($link->getParent())) {
              $replacements[$original] = token_menu_link_translated_title($parent, $langcode);
            }
            break;

          case 'parents':
            if ($parents = token_menu_link_load_all_parents($link->getPluginId(), $langcode)) {
              $replacements[$original] = token_render_array($parents, $options);
            }
            break;

          case 'root':
            if ($link->getParent() && $parent_ids = array_keys(token_menu_link_load_all_parents($link->getPluginId(), $langcode))) {
              $root = $this->menuLinkManager->createInstance(array_shift($parent_ids));
              $replacements[$original] = token_menu_link_translated_title($root, $langcode);
            }
            break;
        }
      }
      // Chained token relationships.
      /** @var \Drupal\Core\Menu\MenuLinkInterface $parent */
      if ($link->getParent() && ($parent_tokens = $this->token->findWithPrefix($tokens, 'parent')) && $parent = $this->menuLinkManager->createInstance($link->getParent())) {
        $replacements += $this->token->generate('menu-link', $parent_tokens, [
          'menu-link' => $parent,
        ], $options, $bubbleable_metadata);
      }
      // [menu-link:parents:*] chained tokens.
      if ($parents_tokens = $this->token->findWithPrefix($tokens, 'parents')) {
        if ($parents = token_menu_link_load_all_parents($link->getPluginId(), $langcode)) {
          $replacements += $this->token->generate('array', $parents_tokens, [
            'array' => $parents,
          ], $options, $bubbleable_metadata);
        }
      }
      if (($root_tokens = $this->token->findWithPrefix($tokens, 'root')) && $link->getParent() && $parent_ids = array_keys(token_menu_link_load_all_parents($link->getPluginId(), $langcode))) {
        $root = $this->menuLinkManager->createInstance(array_shift($parent_ids));
        $replacements += $this->token->generate('menu-link', $root_tokens, [
          'menu-link' => $root,
        ], $options, $bubbleable_metadata);
      }
      if ($url_tokens = $this->token->findWithPrefix($tokens, 'url')) {
        $replacements += $this->token->generate('url', $url_tokens, [
          'url' => $link->getUrlObject(),
        ], $options, $bubbleable_metadata);
      }
    }
    // Language tokens.
    if ($type == 'language' && !empty($langcode)) {
      $language = $language_manager->getLanguage($langcode);
      if ($language) {
        foreach ($tokens as $name => $original) {
          switch ($name) {
            case 'name':
              $replacements[$original] = $language->getName();
              break;

            case 'langcode':
              $replacements[$original] = $langcode;
              break;

            case 'direction':
              $replacements[$original] = $language->getDirection();
              break;

            case 'domain':
              if (!isset($language_url_domains)) {
                $language_url_domains = $this->configFactory->get('language.negotiation')->get('url.domains');
              }
              if (isset($language_url_domains[$langcode])) {
                $replacements[$original] = $language_url_domains[$langcode];
              }
              break;

            case 'prefix':
              if (!isset($language_url_prefixes)) {
                $language_url_prefixes = $this->configFactory->get('language.negotiation')->get('url.prefixes');
              }
              if (isset($language_url_prefixes[$langcode])) {
                $replacements[$original] = $language_url_prefixes[$langcode];
              }
              break;
          }
        }
      }
    }
    // Current page tokens.
    if ($type == 'current-page') {
      $request = $this->requestStack->getCurrentRequest();
      foreach ($tokens as $name => $original) {
        switch ($name) {
          case 'title':
            $route = $request->attributes->get(RouteObjectInterface::ROUTE_OBJECT);
            if ($route) {
              $title = $this->titleResolver->getTitle($request, $route);
              $replacements[$original] = token_render_array_value($title);
            }
            break;

          case 'url':
            $bubbleable_metadata->addCacheContexts([
              'url.path',
            ]);
            try {
              $url = Url::createFromRequest($request)->setOptions($url_options);
            }
            catch (\Exception) {
              // Url::createFromRequest() can fail, e.g. on 404 pages.
              // Fall back and try again with Url::fromUserInput().
              try {
                $url = Url::fromUserInput($request->getPathInfo(), $url_options);
              }
              catch (\Exception) {
                // Instantiation would fail again on malformed urls.
              }
            }
            if (isset($url)) {
              $result = $url->toString(TRUE);
              $bubbleable_metadata->addCacheableDependency($result);
              $replacements[$original] = $result->getGeneratedUrl();
            }
            break;

          case 'page-number':
            if ($page = $request->query->get('page')) {
              // @see PagerDefault::execute()
              $pager_page_array = explode(',', $page);
              $page = $pager_page_array[0];
            }
            $replacements[$original] = (int) $page + 1;
            break;
        }
        // [current-page:interface-language:*] chained tokens.
        if ($language_interface_tokens = $this->token->findWithPrefix($tokens, 'interface-language')) {
          $language_interface = $language_manager->getCurrentLanguage(LanguageInterface::TYPE_INTERFACE);
          $langcode = $language_interface->getId();
          $replacements += $this->token->generate('language', $language_interface_tokens, $data, [
            'langcode' => $langcode,
          ] + $options, $bubbleable_metadata);
        }
        // [current-page:content-language:*] chained tokens.
        if ($language_content_tokens = $this->token->findWithPrefix($tokens, 'content-language')) {
          $language_content = $language_manager->getCurrentLanguage(LanguageInterface::TYPE_CONTENT);
          $langcode = $language_content->getId();
          $replacements += $this->token->generate('language', $language_content_tokens, $data, [
            'langcode' => $langcode,
          ] + $options, $bubbleable_metadata);
        }
      }
      // @deprecated
      // [current-page:arg] dynamic tokens.
      if ($arg_tokens = $this->token->findWithPrefix($tokens, 'arg')) {
        $path = ltrim($this->currentPathStack->getPath(), '/');
        // Make sure its a system path.
        $path = $this->aliasManager->getPathByAlias($path);
        foreach ($arg_tokens as $name => $original) {
          $parts = explode('/', $path);
          if (is_numeric($name) && isset($parts[$name])) {
            $replacements[$original] = $parts[$name];
          }
        }
      }
      // [current-page:query] dynamic tokens.
      if ($query_tokens = $this->token->findWithPrefix($tokens, 'query')) {
        $bubbleable_metadata->addCacheContexts([
          'url.query_args',
        ]);
        foreach ($query_tokens as $name => $original) {
          if ($this->requestStack->getCurrentRequest()->query->has($name)) {
            $value = $this->requestStack->getCurrentRequest()->query->get($name);
            $replacements[$original] = $value;
          }
        }
      }
      // Chained token relationships.
      if ($url_tokens = $this->token->findWithPrefix($tokens, 'url')) {
        $url = NULL;
        try {
          $url = Url::createFromRequest($request)->setOptions($url_options);
        }
        catch (\Exception) {
          // Url::createFromRequest() can fail, e.g. on 404 pages.
          // Fall back and try again with Url::fromUserInput().
          try {
            $url = Url::fromUserInput($request->getPathInfo(), $url_options);
          }
          catch (\Exception) {
            // Instantiation would fail again on malformed urls.
          }
        }
        // Add cache contexts to ensure this token functions on a per-path basis.
        $bubbleable_metadata->addCacheContexts([
          'url.path',
        ]);
        $replacements += $this->token->generate('url', $url_tokens, [
          'url' => $url,
        ], $options, $bubbleable_metadata);
      }
    }
    // URL tokens.
    if ($type == 'url' && !empty($data['url'])) {
      /** @var \Drupal\Core\Url $url */
      $url = $data['url'];
      // To retrieve the correct path, modify a copy of the Url object.
      $path_url = clone $url;
      $path = '/';
      // Ensure the URL is routed to avoid throwing an exception.
      if ($url->isRouted()) {
        $path .= $path_url->setAbsolute(FALSE)->setOption('fragment', NULL)->getInternalPath();
      }
      foreach ($tokens as $name => $original) {
        switch ($name) {
          case 'path':
            $value = !$url->getOption('alias') ? $this->aliasManager->getAliasByPath($path, $langcode) : $path;
            $replacements[$original] = $value;
            break;

          case 'alias':
            // @deprecated
            $alias = $this->aliasManager->getAliasByPath($path, $langcode);
            $replacements[$original] = $alias;
            break;

          case 'absolute':
            $result = $url->setAbsolute()->toString(TRUE);
            $bubbleable_metadata->addCacheableDependency($result);
            $replacements[$original] = $result->getGeneratedUrl();
            break;

          case 'relative':
            $result = $url->setAbsolute(FALSE)->toString(TRUE);
            $bubbleable_metadata->addCacheableDependency($result);
            $replacements[$original] = $result->getGeneratedUrl();
            break;

          case 'brief':
            $result = $url->setAbsolute()->toString(TRUE);
            $bubbleable_metadata->addCacheableDependency($result);
            $replacements[$original] = preg_replace([
              '!^https?://!',
              '!/$!',
            ], '', $result->getGeneratedUrl());
            break;

          case 'unaliased':
            $unaliased = clone $url;
            $result = $unaliased->setAbsolute()->setOption('alias', TRUE)->toString(TRUE);
            $bubbleable_metadata->addCacheableDependency($result);
            $replacements[$original] = $result->getGeneratedUrl();
            break;

          case 'args':
            $value = !$url->getOption('alias') ? $this->aliasManager->getAliasByPath($path, $langcode) : $path;
            $replacements[$original] = token_render_array(explode('/', $value), $options);
            break;
        }
      }
      // [url:args:*] chained tokens.
      if ($arg_tokens = $this->token->findWithPrefix($tokens, 'args')) {
        $value = !$url->getOption('alias') ? $this->aliasManager->getAliasByPath($path, $langcode) : $path;
        $replacements += $this->token->generate('array', $arg_tokens, [
          'array' => explode('/', ltrim($value, '/')),
        ], $options, $bubbleable_metadata);
      }
      // [url:unaliased:*] chained tokens.
      if ($unaliased_tokens = $this->token->findWithPrefix($tokens, 'unaliased')) {
        $url->setOption('alias', TRUE);
        $replacements += $this->token->generate('url', $unaliased_tokens, [
          'url' => $url,
        ], $options, $bubbleable_metadata);
      }
    }
    // Entity tokens.
    if (!empty($data[$type]) && $entity_type = $this->tokenEntityMapper->getEntityTypeForTokenType($type)) {
      /** @var \Drupal\Core\Entity\EntityInterface $entity */
      $entity = $data[$type];
      foreach ($tokens as $name => $original) {
        switch ($name) {
          case 'url':
            if ($this->tokenModuleProvider->getTokenModule($type, 'url') === 'token' && !$entity->isNew() && $entity->hasLinkTemplate('canonical')) {
              $result = $entity->toUrl('canonical')->toString(TRUE);
              $bubbleable_metadata->addCacheableDependency($result);
              $replacements[$original] = $result->getGeneratedUrl();
            }
            break;

          case 'original':
            if ($this->tokenModuleProvider->getTokenModule($type, 'original') == 'token' && !empty($entity->original)) {
              $label = $entity->original->label();
              $replacements[$original] = $label;
            }
            break;
        }
      }
      // [entity:url:*] chained tokens.
      if (($url_tokens = $this->token->findWithPrefix($tokens, 'url')) && $this->tokenModuleProvider->getTokenModule($type, 'url') == 'token') {
        $replacements += $this->token->generate('url', $url_tokens, [
          'url' => $entity->toUrl(),
        ], $options, $bubbleable_metadata);
      }
      // [entity:original:*] chained tokens.
      if (($original_tokens = $this->token->findWithPrefix($tokens, 'original')) && $this->tokenModuleProvider->getTokenModule($type, 'original') == 'token' && !empty($entity->original)) {
        $replacements += $this->token->generate($type, $original_tokens, [
          $type => $entity->original,
        ], $options, $bubbleable_metadata);
      }
      // [entity:language:*] chained tokens.
      if (($language_tokens = $this->token->findWithPrefix($tokens, 'language')) && $this->tokenModuleProvider->getTokenModule($type, 'language') == 'token') {
        $language_options = array_merge($options, [
          'langcode' => $entity->get('langcode')->value,
        ]);
        $replacements += $this->token->generate('language', $language_tokens, [], $language_options, $bubbleable_metadata);
      }
      // Pass through to an generic 'entity' token type generation.
      $entity_data = [
        'entity_type' => $entity_type,
        'entity' => $entity,
        'token_type' => $type,
      ];
      // @todo Investigate passing through more data like everything from entity_extract_ids().
      $replacements += $this->token->generate('entity', $tokens, $entity_data, $options, $bubbleable_metadata);
    }
    // Array tokens.
    if ($type == 'array' && !empty($data['array']) && is_array($data['array'])) {
      $array = $data['array'];
      $sort = $options['array sort'] ?? TRUE;
      $keys = token_element_children($array, $sort);
      foreach ($tokens as $name => $original) {
        switch ($name) {
          case 'first':
            $value = $array[$keys[0]];
            $value = is_array($value) ? $this->renderer->renderInIsolation($value) : (string) $value;
            $replacements[$original] = $value;
            break;

          case 'last':
            $value = $array[$keys[count($keys) - 1]];
            $value = is_array($value) ? $this->renderer->renderInIsolation($value) : (string) $value;
            $replacements[$original] = $value;
            break;

          case 'count':
            $replacements[$original] = count($keys);
            break;

          case 'keys':
            $replacements[$original] = token_render_array($keys, $options);
            break;

          case 'reversed':
            $reversed = array_reverse($array, TRUE);
            $replacements[$original] = token_render_array($reversed, $options);
            break;

          case 'join':
            $replacements[$original] = token_render_array($array, [
              'join' => '',
            ] + $options);
            break;
        }
      }
      // [array:value:*] dynamic tokens.
      if ($value_tokens = $this->token->findWithPrefix($tokens, 'value')) {
        foreach ($value_tokens as $key => $original) {
          if ((is_int($key) || $key[0] !== '#') && isset($array[$key])) {
            $replacements[$original] = token_render_array_value($array[$key], $options);
          }
        }
      }
      // [array:join:*] dynamic tokens.
      if ($join_tokens = $this->token->findWithPrefix($tokens, 'join')) {
        foreach ($join_tokens as $join => $original) {
          $replacements[$original] = token_render_array($array, [
            'join' => $join,
          ] + $options);
        }
      }
      // [array:keys:*] chained tokens.
      if ($key_tokens = $this->token->findWithPrefix($tokens, 'keys')) {
        $replacements += $this->token->generate('array', $key_tokens, [
          'array' => $keys,
        ], $options, $bubbleable_metadata);
      }
      // [array:reversed:*] chained tokens.
      if ($reversed_tokens = $this->token->findWithPrefix($tokens, 'reversed')) {
        $replacements += $this->token->generate('array', $reversed_tokens, [
          'array' => array_reverse($array, TRUE),
        ], [
          'array sort' => FALSE,
        ] + $options, $bubbleable_metadata);
      }
      // @todo Handle if the array values are not strings and could be chained.
    }
    // Random tokens.
    if ($type == 'random') {
      foreach ($tokens as $name => $original) {
        switch ($name) {
          case 'number':
            $replacements[$original] = mt_rand();
            break;
        }
      }
      // [custom:hash:*] dynamic token.
      if ($hash_tokens = $this->token->findWithPrefix($tokens, 'hash')) {
        $algos = hash_algos();
        foreach ($hash_tokens as $name => $original) {
          if (in_array($name, $algos)) {
            $replacements[$original] = hash($name, random_bytes(55));
          }
        }
      }
    }
    // If $type is a token type, $data[$type] is empty but $data[$entity_type] is
    // not, re-run token replacements.
    if (empty($data[$type]) && ($entity_type = $this->tokenEntityMapper->getEntityTypeForTokenType($type)) && $entity_type != $type && !empty($data[$entity_type]) && empty($options['recursive'])) {
      $data[$type] = $data[$entity_type];
      $options['recursive'] = TRUE;
      $replacements += $this->moduleHandler->invokeAll('tokens', [
        $type,
        $tokens,
        $data,
        $options,
        $bubbleable_metadata,
      ]);
    }
    // If the token type specifics a 'needs-data' value, and the value is not
    // present in $data, then throw an error.
    if (!empty($GLOBALS['drupal_test_info']['test_run_id'])) {
      // Only check when tests are running.
      $type_info = $this->token->getTypeInfo($type);
      if (!empty($type_info['needs-data']) && !isset($data[$type_info['needs-data']])) {
        trigger_error($this->t('Attempting to perform token replacement for token type %type without required data', [
          '%type' => $type,
        ]), E_USER_WARNING);
      }
    }
    return $replacements;
  }

  /**
   * Proxy implementation of hook_tokens() on behalf of book.module.
   */
  protected function bookTokens($type, $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata) {
    $replacements = [];

    // Node tokens.
    if ($type == 'node' && !empty($data['node'])) {
      $book = $data['node']->book;

      if (!empty($book['bid'])) {
        if ($book_tokens = $this->token->findWithPrefix($tokens, 'book')) {
          $child_node = Node::load($book['nid']);
          $replacements += $this->token->generate('book', $book_tokens, ['book' => $child_node], $options, $bubbleable_metadata);
        }
      }
    }
    // Book tokens.
    elseif ($type == 'book' && !empty($data['book'])) {
      $book = $data['book']->book;

      if (!empty($book['bid'])) {
        $book_node = Node::load($book['bid']);

        foreach ($tokens as $name => $original) {
          switch ($name) {
            case 'root':
            case 'title':
              $replacements[$original] = $book_node->getTitle();
              break;

            case 'parent':
              if (!empty($book['pid'])) {
                $parent_node = Node::load($book['pid']);
                $replacements[$original] = $parent_node->getTitle();
              }
              break;

            case 'parents':
              if ($parents = token_book_load_all_parents($book)) {
                $replacements[$original] = token_render_array($parents, $options);
              }
              break;
          }
        }

        if ($book_tokens = $this->token->findWithPrefix($tokens, 'author')) {
          $replacements += $this->token->generate('user', $book_tokens, ['user' => $book_node->getOwner()], $options, $bubbleable_metadata);
        }
        if ($book_tokens = $this->token->findWithPrefix($tokens, 'root')) {
          $replacements += $this->token->generate('node', $book_tokens, ['node' => $book_node], $options, $bubbleable_metadata);
        }
        if (!empty($book['pid']) && $book_tokens = $this->token->findWithPrefix($tokens, 'parent')) {
          $parent_node = Node::load($book['pid']);
          $replacements += $this->token->generate('node', $book_tokens, ['node' => $parent_node], $options, $bubbleable_metadata);
        }
        if ($book_tokens = $this->token->findWithPrefix($tokens, 'parents')) {
          $parents = token_book_load_all_parents($book);
          $replacements += $this->token->generate('array', $book_tokens, ['array' => $parents], $options, $bubbleable_metadata);
        }
      }
    }

    return $replacements;
  }

  /**
   * Proxy implementation of hook_tokens() on behalf of menu_ui.module.
   */
  protected function menuUiTokens($type, $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata) {
    $replacements = [];

    $url_options = ['absolute' => TRUE];
    if (isset($options['langcode'])) {
      $url_options['language'] = $this->languageManager->getLanguage($options['langcode']);
      $langcode = $options['langcode'];
    }
    else {
      $langcode = NULL;
    }

    // Node tokens.
    if ($type == 'node' && !empty($data['node'])) {
      /** @var \Drupal\node\NodeInterface $node */
      $node = $data['node'];

      foreach ($tokens as $name => $original) {
        switch ($name) {
          case 'menu-link':
            // On node-form save we populate a calculated field with a menu_link
            // references.
            // @see token_node_menu_link_submit()
            if ($node->getFieldDefinition('menu_link') && $menu_link = $node->menu_link->entity) {
              /** @var \Drupal\menu_link_content\MenuLinkContentInterface $menu_link */
              $replacements[$original] = $menu_link->getTitle();
            }
            else {
              $url = $node->toUrl();
              if ($links = $this->menuLinkManager->loadLinksByRoute($url->getRouteName(), $url->getRouteParameters())) {
                $link = $this->menuLinkBestMatch($node, $links);
                $replacements[$original] = token_menu_link_translated_title($link, $langcode);
              }
            }
            break;
        }

        // Chained token relationships.
        if ($menu_tokens = $this->token->findWithPrefix($tokens, 'menu-link')) {
          if ($node->getFieldDefinition('menu_link') && $menu_link = $node->menu_link->entity) {
            if ($menu_link instanceof MenuLinkContentInterface) {
              $menu_link = $this->menuLinkManager->createInstance($menu_link->getPluginId());
            }
            /** @var \Drupal\menu_link_content\MenuLinkContentInterface $menu_link */
            $replacements += $this->token->generate('menu-link', $menu_tokens, ['menu-link' => $menu_link], $options, $bubbleable_metadata);
          }
          else {
            $url = $node->toUrl();
            if ($links = $this->menuLinkManager->loadLinksByRoute($url->getRouteName(), $url->getRouteParameters())) {
              $link = $this->menuLinkBestMatch($node, $links);
              $replacements += $this->token->generate('menu-link', $menu_tokens, ['menu-link' => $link], $options, $bubbleable_metadata);
            }
          }
        }
      }
    }

    // Menu link tokens.
    if ($type == 'menu-link' && !empty($data['menu-link'])) {
      /** @var \Drupal\Core\Menu\MenuLinkInterface $link */
      $link = $data['menu-link'];

      if ($link instanceof MenuLinkContentInterface) {
        $link = $this->menuLinkManager->createInstance($link->getPluginId());
      }

      foreach ($tokens as $name => $original) {
        switch ($name) {
          case 'menu':
            if ($menu = Menu::load($link->getMenuName())) {
              $replacements[$original] = $menu->label();
            }
            break;

          case 'edit-url':
            $route = $link->getEditRoute();
            if ($route) {
              $result = $link->getEditRoute()->setOptions($url_options)->toString(TRUE);
              $bubbleable_metadata->addCacheableDependency($result);
              $replacements[$original] = $result->getGeneratedUrl();
            }
            break;
        }
      }

      // Chained token relationships.
      if (($menu_tokens = $this->token->findWithPrefix($tokens, 'menu')) && $menu = Menu::load($link->getMenuName())) {
        $replacements += $this->token->generate('menu', $menu_tokens, ['menu' => $menu], $options, $bubbleable_metadata);
      }
    }

    // Menu tokens.
    if ($type == 'menu' && !empty($data['menu'])) {
      /** @var \Drupal\system\MenuInterface $menu */
      $menu = $data['menu'];

      foreach ($tokens as $name => $original) {
        switch ($name) {
          case 'name':
            $replacements[$original] = $menu->label();
            break;

          case 'machine-name':
            $replacements[$original] = $menu->id();
            break;

          case 'description':
            $replacements[$original] = $menu->getDescription();
            break;

          case 'menu-link-count':
            $replacements[$original] = $this->menuLinkManager->countMenuLinks($menu->id());
            break;

          case 'edit-url':
            $result = Url::fromRoute('entity.menu.edit_form', ['menu' => $menu->id()], $url_options)->toString(TRUE);
            $bubbleable_metadata->addCacheableDependency($result);
            $replacements[$original] = $result->getGeneratedUrl();
            break;
        }
      }
    }

    return $replacements;
  }

  /**
   * Returns a best matched link for a given node.
   *
   * If the url exists in multiple menus, default to the one set on the node
   * itself.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to look up the default menu settings from.
   * @param array $links
   *   An array of instances keyed by plugin ID.
   *
   * @return \Drupal\Core\Menu\MenuLinkInterface
   *   A Link instance.
   */
  protected function menuLinkBestMatch(NodeInterface $node, array $links) {
    // Get the menu ui defaults so we can determine what menu was
    // selected for this node. This ensures that if the node was added
    // to the menu via the node UI, we use that as a default. If it
    // was not added via the node UI then grab the first in the
    // retrieved array.
    $defaults = menu_ui_get_menu_link_defaults($node);
    if (isset($defaults['id']) && isset($links[$defaults['id']])) {
      $link = $links[$defaults['id']];
    }
    else {
      $link = reset($links);
    }
    return $link;
  }

  /**
   * Proxy implementation of hook_tokens() on behalf of field.module.
   */
  protected function fieldTokens($type, $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata) {
    $replacements = [];

    $langcode = $options['langcode'] ?? NULL;
    // Entity tokens.
    if ($type == 'entity' && !empty($data['entity_type']) && !empty($data['entity']) && !empty($data['token_type'])) {
      /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
      $entity = $data['entity'];
      if (!($entity instanceof ContentEntityInterface)) {
        return $replacements;
      }

      if (!isset($options['langcode'])) {
        // Set the active language in $options, so that it is passed along.
        $langcode = $options['langcode'] = $entity->language()->getId();
      }
      // Obtain the entity with the correct language.
      $entity = $this->entityRepository->getTranslationFromContext($entity, $langcode);

      foreach ($tokens as $name => $original) {
        // For the [entity:field_name] token.
        if (strpos($name, ':') === FALSE) {
          $field_name = $name;
          $token_name = $name;
        }
        // For [entity:field_name:0], [entity:field_name:0:value] and
        // [entity:field_name:value] tokens.
        else {
          [$field_name, $delta] = explode(':', $name, 2);
          if (!is_numeric($delta)) {
            unset($delta);
          }
          $token_name = $field_name;
        }
        // Ensure the entity has the requested field and that the token for it is
        // defined by token.module.
        if (!$entity->hasField($field_name) ||$this->tokenModuleProvider->getTokenModule($data['token_type'], $token_name) != 'token') {
          continue;
        }

        $display_options = 'token';
        // Do not continue if the field is empty.
        if ($entity->get($field_name)->isEmpty()) {
          continue;
        }
        // Handle [entity:field_name] and [entity:field_name:0] tokens.
        if ($field_name === $name || isset($delta)) {
          $view_display = token_get_token_view_display($entity);
          if (!$view_display) {
            // We don't have the token view display and should fall back on
            // default formatters. If the field has specified a specific formatter
            // to be used by default with tokens, use that, otherwise use the
            // default formatter.
            $field_type_definition = $this->fieldTypePluginManager->getDefinition($entity->getFieldDefinition($field_name)->getType());
            if (empty($field_type_definition['default_token_formatter']) && empty($field_type_definition['default_formatter'])) {
              continue;
            }
            $display_options = [
              'type' => !empty($field_type_definition['default_token_formatter']) ? $field_type_definition['default_token_formatter'] : $field_type_definition['default_formatter'],
              'label' => 'hidden',
              'weight' => 0,
            ];
          }

          // Render only one delta.
          if (isset($delta)) {
            if ($field_delta = $entity->{$field_name}[$delta]) {
              $field_output = $field_delta->view($display_options);
            }
            // If no such delta exists, let's not replace the token.
            else {
              continue;
            }
          }
          // Render the whole field (with all deltas).
          else {
            $field_output = $entity->$field_name->view($display_options);
            // If we are displaying all field items we need this #pre_render
            // callback.
            $field_output['#pre_render'][] = '\Drupal\token\TokenFieldRender::preRender';
          }
          $field_output['#token_options'] = $options;
          $replacements[$original] = $this->renderer->renderInIsolation($field_output);
        }
        // Handle [entity:field_name:value] and [entity:field_name:0:value]
        // tokens.
        elseif ($field_tokens = $this->token->findWithPrefix($tokens, $field_name)) {
          // With multiple nested tokens for the same field name, this might
          // match the same field multiple times. Filter out those that have
          // already been replaced.
          $field_tokens = array_filter($field_tokens, function ($token) use ($replacements) {
            return !isset($replacements[$token]);
          });

          if ($field_tokens) {
            $property_token_data = [
              'field_property' => TRUE,
              $data['entity_type'] . '-' . $field_name => $entity->$field_name,
              'field_name' => $data['entity_type'] . '-' . $field_name,
            ];
            // The optimization above only works on the root level, if there is
            // more than one nested field it would still replace all in every
            // call. Replace them one by one instead, which is slightly slower
            // but ensures that the number of replacements do not grow
            // exponentially.
            foreach ($field_tokens as $field_token_key => $field_token_value) {
              $replacements += $this->token->generate($field_name, [$field_token_key => $field_token_value], $property_token_data, $options, $bubbleable_metadata);
            }
          }
        }
      }

      // Remove the cloned object from memory.
      unset($entity);
    }
    elseif (!empty($data['field_property'])) {
      foreach ($tokens as $token => $original) {
        $filtered_tokens = $tokens;
        $delta = 0;
        $parts = explode(':', $token);
        if (is_numeric($parts[0])) {
          if (count($parts) > 1) {
            $delta = $parts[0];
            $property_name = $parts[1];
            // Pre-filter the tokens to select those with the correct delta.
            $filtered_tokens = $this->token->findWithPrefix($tokens, $delta);
            // Remove the delta to unify between having and not having one.
            array_shift($parts);
          }
          else {
            // Token is fieldname:delta, which is invalid.
            continue;
          }
        }
        else {
          $property_name = $parts[0];
        }

        if (isset($data[$data['field_name']][$delta])) {
          $field_item = $data[$data['field_name']][$delta];
        }
        else {
          // The field has no such delta, abort replacement.
          continue;
        }

        if (isset($field_item->$property_name) && ($field_item->$property_name instanceof FieldableEntityInterface)) {
          // Entity reference field.
          $entity = $field_item->$property_name;
          // Obtain the referenced entity with the correct language.
          $entity = $this->entityRepository->getTranslationFromContext($entity, $langcode);

          if (count($parts) > 1) {
            $field_tokens = $this->token->findWithPrefix($filtered_tokens, $property_name);
            $token_type = $this->tokenEntityMapper->getTokenTypeForEntityType($entity->getEntityTypeId(), TRUE);
            $replacements += $this->token->generate($token_type, $field_tokens, [$token_type => $entity], $options, $bubbleable_metadata);
          }
          else {
            $replacements[$original] = $entity->label();
          }
        }
        elseif (($field_item->getFieldDefinition()->getType() == 'image') && ($style = ImageStyle::load($property_name))) {
          // Handle [node:field_name:image_style:property] tokens and multivalued
          // [node:field_name:delta:image_style:property] tokens. If the token is
          // of the form [node:field_name:image_style], provide the URL as a
          // replacement.
          $property_name = $parts[1] ?? 'url';
          $entity = $field_item->entity;
          if (!empty($field_item->entity)) {
            $original_uri = $entity->getFileUri();

            // Only generate the image derivative if needed.
            if ($property_name === 'width' || $property_name === 'height') {
              $dimensions = [
                'width' => $field_item->width,
                'height' => $field_item->height,
              ];
              $style->transformDimensions($dimensions, $original_uri);
              $replacements[$original] = $dimensions[$property_name];
            }
            elseif ($property_name === 'uri') {
              $replacements[$original] = $style->buildUri($original_uri);
            }
            elseif ($property_name === 'url') {
              $replacements[$original] = $style->buildUrl($original_uri);
            }
            else {
              // Generate the image derivative, if it doesn't already exist.
              $derivative_uri = $style->buildUri($original_uri);
              $derivative_exists = TRUE;
              if (!file_exists($derivative_uri)) {
                $derivative_exists = $style->createDerivative($original_uri, $derivative_uri);
              }
              if ($derivative_exists) {
                $image = $this->imageFactory->get($derivative_uri);
                // Provide the replacement.
                switch ($property_name) {
                  case 'mimetype':
                    $replacements[$original] = $image->getMimeType();
                    break;

                  case 'filesize':
                    $replacements[$original] = $image->getFileSize();
                    break;
                }
              }
            }
          }
        }
        elseif (in_array($field_item->getFieldDefinition()->getType(), ['datetime', 'daterange', 'date_recur']) && in_array($property_name, ['date', 'start_date', 'end_date']) && !empty($field_item->$property_name)) {
          $timestamp = $field_item->{$property_name}->getTimestamp();
          // If the token is an exact match for the property or the delta and the
          // property, use the timestamp as-is.
          if ($property_name == $token || "$delta:$property_name" == $token) {
            $replacements[$original] = $timestamp;
          }
          else {
            $date_tokens = $this->token->findWithPrefix($filtered_tokens, $property_name);
            $replacements += $this->token->generate('date', $date_tokens, ['date' => $timestamp], $options, $bubbleable_metadata);
          }
        }
        elseif (in_array($field_item->getFieldDefinition()->getType(), ['timestamp', 'created', 'changed']) && in_array($property_name, ['date'])) {
          $timestamp = $field_item->value;
          if ($property_name == $token || "$delta:$property_name" == $token) {
            $replacements[$original] = $timestamp;
          }
          else {
            $field_tokens = $this->token->findWithPrefix($filtered_tokens, $property_name);
            $replacements += $this->token->generate('date', $field_tokens, ['date' => $timestamp], $options, $bubbleable_metadata);
          }
        }
        else {
          $replacements[$original] = $field_item->$property_name;
        }
      }
    }
    return $replacements;
  }

}
