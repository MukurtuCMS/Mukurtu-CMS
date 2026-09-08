(function (Drupal, once) {
  'use strict';

  // Gin's gin_preprocess_breadcrumb() (web/themes/contrib/gin/includes/
  // breadcrumb.theme) sets breadcrumb item 0's href to $entity->toUrl(),
  // which for entity.user.canonical is this page's own URL -- a no-op
  // link. It only lets escapeAdminPath override the href via
  // data-gin-toolbar-escape-admin when access is denied/no entity is
  // found, which never applies here. Fix the href client-side instead.
  Drupal.behaviors.mukurtuUserProfileBackToSite = {
    attach(context) {
      once(
        'mukurtu-user-profile-back-to-site',
        '.gin-breadcrumb__list > li:first-child > a.gin-breadcrumb__link',
        context
      ).forEach((link) => {
        const escapeAdminPath = sessionStorage.getItem('escapeAdminPath');
        link.setAttribute('href', escapeAdminPath ?? Drupal.url(''));
      });
    },
  };
}(Drupal, once));
