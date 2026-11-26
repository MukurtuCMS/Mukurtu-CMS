/**
 * @file
 * Lists the items saved on this device on the offline page (#400 spike).
 */

((Drupal, once) => {
  // Drupal.t() escapes placeholders for HTML; this turns them back into text
  // for textContent, without running any markup.
  const plain = (html) =>
    new DOMParser().parseFromString(html, 'text/html').documentElement
      .textContent;

  Drupal.behaviors.mukurtuPwaOffline = {
    attach(context) {
      if (!Drupal.mukurtuPwa || !Drupal.mukurtuPwa.supported) {
        return;
      }
      once('mukurtu-pwa-offline', '.mukurtu-pwa-offline', context).forEach(
        (wrapper) => {
          const list = wrapper.querySelector('.mukurtu-pwa-offline__list');
          const empty = wrapper.querySelector('.mukurtu-pwa-offline__empty');
          const expired = wrapper.querySelector('.mukurtu-pwa-offline__expired');

          Drupal.mukurtuPwa
            .ask({ type: 'listItems' })
            .then((items) => {
              const usable = items.filter(
                (item) => item.present && !item.expired,
              );
              empty.hidden = usable.length > 0;
              expired.hidden = !items.some((item) => item.expired);
              Drupal.mukurtuPwa.replaceChildren(
                list,
                usable.map((item) => {
                  const li = document.createElement('li');
                  const link = document.createElement('a');
                  link.href = item.url;
                  link.textContent = plain(
                    Drupal.t('@title, saved @date', {
                      '@title': item.title,
                      '@date': new Date(item.savedAt).toLocaleDateString(),
                    }),
                  );
                  li.append(link);
                  return li;
                }),
              );
            })
            .catch(() => {});
        },
      );
    },
  };
})(Drupal, once);
