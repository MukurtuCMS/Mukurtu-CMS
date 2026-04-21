
***
## <a name="faq"></a>FAQ

### CURRENT DEVELOPMENT STATUS
A full release should be reasonable after proper feedback from the community,
some code cleanup, and optimization where needed. Patches are very much welcome.


### PROGRAMATICALLY
[blazy.api.php](https://git.drupalcode.org/project/blazy/blob/3.0.x/blazy.api.php)


### BLAZY VS. B-LAZY
`blazy` is the module namespace. `b-lazy` is the default CSS class to lazy load.

* The `blazy` class is applied to the **top level container**, e,g.. `.field`,
  `.view`, `.item-list`, etc., those which normally contain item collection.
  In this container, you can feed any `bLazy` script options into `[data-blazy]`
  attribute to override existing behaviors per particular page, only if needed.
* The `b-lazy` class is applied to the **target item** to lazy load, normally
  the children of `.blazy`, but not always. This can be IMG, VIDEO, DIV, etc.

### BLAZY:DONE VS. BIO:DONE EVENTS
The `blazy:done` event is for individual lazy-loaded elements, while `bio:done`
is for the entire collections.

Since 2.17, you can namespace colonized events like so: `blazy:done.MYMODULE`
which was problematic with dot `blazy.done`. That is why `blazy.done` is
deprecated for `blazy:done`. The dotted event names like `blazy.done` will
continue working till 3.x. Changing them to colonized `blazy:done` is strongly
recommended to pass 3.x. Newly added events will only use colons.

### WHAT `BLAZY` CSS CLASS IS FOR?
Aside from the fact that a module must reserve its namespace including for CSS
classes, the `blazy` is actually used to limit the scope to scan document.
Rather than scanning the entire DOM, you limit your work to a particular
`.blazy` container, and these can be many, no problem. This also allows each
`.blazy` container to have unique features, such as ones with multi-breakpoint
images, others with regular images; ones with a lightbox, others with
image to iframe; ones with CSS background, others with regular images; etc.
right on the same page. This is only possible and efficient within the `.blazy`
scope.

### WHY NOT `BLAZY__LAZY` FOR `B-LAZY`?
`b-lazy` is the default CSS class reserved by JS script. Rather than recreating
a new one, respecting the defaults is better. Following BEM standard is not
crucial for most JS generated CSS classes. Uniqueness matters.

### NATIVE LAZY LOADING
Blazy library last release was v1.8.2 (2016/10/25). 3 years later,
Native lazy loading is supported by Chrome 76+ as of 01/2019. Blazy or IO will
be used as fallback for other browsers instead. Currently the offset/ threshold
before loading is hard-coded to [8000px at Chrome](https://cs.chromium.org/chromium/src/third_party/blink/renderer/core/frame/settings.json5?l=971-1003&rcl=e8f3cf0bbe085fee0d1b468e84395aad3ebb2cad),
so it might only be good for super tall pages for now, be aware.
[Read more](https://web.dev/native-lazy-loading/)

This also may trick us to think lazy load not work, check out browsers' Network
tab to verify that it still does work.

**UPDATE 2020-04-24**: Added a delay to only lazy load once the first found is
  loaded, see [#3120696](https://drupal.org/node/3120696)

**UPDATE 2022-01-22**:
With bIO as the main lazyloader, the game changed, quoted from:
https://developer.mozilla.org/en-US/docs/Learn/HTML/Howto/Author_fast-loading_HTML_pages
> Note that lazily-loaded images may not be available when the load event is
fired. You can determine if a given image is loaded by checking to see if
the value of its Boolean complete property is true.

Old bLazy relies on `onload`, meaning too early loaded decision for Native,
the reason for our previous deferred invocation, not `decoding` like what bIO
did which is more precise as suggested by the quote.

Assumed, untested, fine with combo IO + `decoding` checks before blur spits.

Shortly we are in the right direction to cope with Native vs. `data-[SRC]`.
See `bio.js ::natively` for more contextual info.
[x] Todo recheck IF wrong so to put back https://drupal.org/node/3120696.

**UPDATE 2022-03-03**: The above is almost not wrong as proven by no `b-loaded`
class and no `blur` is triggered earlier, but 8000px threshold rules. Meaning
the image is immediately requested 8000px before entering viewport.
Added back a delay to only lazy load once the first found is loaded at field
formatter level via `Loading priority: defer`, see
[#3120696](https://drupal.org/node/3120696)

### ANIMATE.CSS INTEGRATION
Blazy container (`.media`) can be animated using
[animate.css](https://github.com/daneden/animate.css). The container is chosen
to be the animated element so to support various use cases:
CSS background, picture, image, or rich media contents.

See [GridStack](https://drupal.org/project/gridstack) 2.6+ for the `animate.css`
samples at Layout Builder pages.

To replace **Blur** effect with `animate.css` thingies, implements two things:
1. **Globally**: `hook_blazy_image_effects_alter` and add `animate.css` classes
   to make them available for select options at Blazy UI.
2. **Fine grained**: `hook_blazy_settings_alter`, and replace a setting named
   `fx` with one of `animate.css` CSS classes, adjust conditions based settings.

#### Requirements:

* The `animate.css` library included in your theme, or via `animate_css` module.
* Data attributes: `data-animation`, with optional: `data-animation-duration`,
  `data-animation-delay` and `data-animation-iteration-count`, as seen below.

```
function MYTHEME_preprocess_blazy(&$variables) {
  $settings = &$variables['settings'];
  $attributes = &$variables['attributes'];
  $blazies = $settings['blazies'];

  // Be sure to limit the scope, only animate for particular conditions.
  if ($blazies->get('entity.id') == 123
    && $blazies->get('field.name') == 'field_media_animated')  {
    $fx = $blazies->get('fx');

    // This was taken care of by feeding $fx, or hard-coded here.
    // Since 2.17, `data-animation` is deprecated for `data-b-animation`.
    $prefix = $blazies->use('data_b') ? 'data-b-' : 'data-';
    $attributes[$prefix . 'animation'] = $fx ?: 'wobble';

    // The following can be defined manually.
    $attributes[$prefix . 'animation-duration'] = '3s';
    $attributes[$prefix . 'animation-delay'] = '.3s';
    // Iteration can be any number, or infinite.
    $attributes[$prefix . 'animation-iteration-count'] = 'infinite';
  }
}
```
### <a name="theme-blazy"> </a>THEME_BLAZY()
Since 2.17, `theme_blazy()` is now capable to replace sub-modules
`theme_ITEM()` contents, e.g.: `theme_slick_slide()`, `theme_splide_slide()`,
`theme_mason_box()`, etc.

The `theme_blazy()` has been used all along, the only difference is captions
which are now included as inherent part of `theme_blazy()` including thumbnail
captions seen at sliders.

It is not replacing their established `theme_ITEM()`, just their contents when
we all have dups with IMAGE/MEDIA + CAPTIONS constructs. It is not a novel
thing, see `block.html.twig` with its variants, etc.
Not a sudden course of actions, it was carefully planned since
[2.x-RC1](https://git.drupalcode.org/project/blazy/-/blob/8.x-2.0-rc1/src/BlazyManager.php#L180), 4 years ago from 2023, and never made it till 2.17.

If you see no difference, nothing to do. If any, be sure it is not caused by
your non-updated overrides which should be updated prior to blazy:3.x.

#### Profits:
+ Tons of dups are reduced which is part of Blazy's job descriptions above.
+ Minimal maintenance for many of Blazy sub-modules.
+ More cool kid features like hoverable effects, etc. will be easier to apply.
+ When Blazy supports extra captions like File description for SVG, it will be
  available immediately to all once, rather than updating each modules to
  support it due their hard-coded natures.

#### Non-profits:
+ Overrides should be taken seriously from now on, or as always. Perhaps CSS
  overrides are the safest. Or at most a `hook_theme_suggestions_alter()`.
+ One blazy stupid mistake, including your override, kills em all. We'll work
  it out at Bugs reports if blazy's. It happens, and the world does not end yet.

#### Custom work migrations from theme_ITEM() into theme_blazy():
+ `THEME_preprocess_blazy()`
+ `hook_blazy_caption_alter(array &$element, array $settings, array $context)`
+ For more `hook_alter`: `grep -r ">alter(" ./blazy`, or see `blazy.api.php`
+ Use `settings.blazies` object to provoke HTML changes conditionally via the
  provided settings alters. Samples are in `blazy.api.php`, more in sub-modules.
+ If you can bear a headache, replace or decorate Blazy services.
+ As last resorts, override `blazy.html.twig`. Headaches are yours in the long
  run. FYI, even the author, me, never touch this file in any custom works.
  The above suffices at 100% own cases.

### <a name="heroes"> </a>Hero media
Building a Hero media (BG, IMG, IFRAME, VIDEO) that complies with
**Core Web Vitals** protocols. If a field is dedicated for a hero, be sure
**unlazy** or **slider** option exists only once per page, similar to Page
Title.

1. Under **Loading priority** option, choose either **unlazy** or **slider**.
   + **unlazy** serves a static Hero media, works best with a single media,
     but for a multi-value field, **Native Grid** in tandem with
     **Use CSS background** options should look decent.
     This will make the first image more prominent, while the rest having
     **Tagore** layout:

     `12x6 4x4 4x3 2x2 2x4 2x2 2x3 2x3 4x2 4x2`

     Or to look like sliders with a 4-item thumbnail navigation:

     `12x6 3x2 3x2 3x2 3x2`

     Adjust the amount of field items to match the designated grids.

   + **slider** serves a dynamic/slider Hero media, works best with multi-value
     value field which can also be a single Hero slide. Use Slick or Splide.
     Only reasonable for sliders (one visible at a time), not carousels
     (multiple visible slides at once). For non-hero sliders, use **lazy**
     instead.

2. Enable **Preloading** option, important for heroes, and specifically BG.
3. For **static Hero media** with a multi-value field, choose a
   **Thumbnail style** if you want the non-prominent ones smaller, else leave it
   empty. This only works if **Grid** is provided.

For the first media, BG will have **fetchpriority** high at the link preload,
while the rest will have it inline on their own HTML tags. A Hero will
**unlazy** the first visible, and leave the rest lazyloaded to meet **LCP**
requirements without sacrificing performance.


### <a name="galleries"> </a> USAGES: BLAZY FOR MULTIMEDIA GALLERY VIA VIEWS UI
#### Using **Blazy Grid**
1. Add a Views style **Blazy Grid** for entities containing Media or Image.
2. Add a Blazy formatter for the Media or Image field.
3. Add any lightbox under **Media switcher** option.
4. Limit the values to 1 under **Multiple field settings** > **Display**, if
   any multi-value field.

#### Without **Blazy Grid**
If you can't use **Blazy Grid** for a reason, maybe having a table, HTML list,
etc., try the following:

1. Add a CSS class under **Advanced > CSS class** for any reasonable supported/
   supportive lightbox in the format **blazy--LIGHTBOX-gallery**, e.g.:
   + **blazy--colorbox-gallery**
   + **blazy--flybox-gallery**
   + **blazy--intense-gallery**
   + **blazy--mfp-gallery** (Magnific Popup)
   + **blazy--photoswipe-gallery**
   + **blazy--slick-lightbox-gallery**
   + **blazy--splidebox-gallery**
   + **blazy--zooming-gallery**

  Note the double dashes BEM modifier "**--**", just to make sure we are on the
  same page that you are intentionally creating a blazy LIGHTBOX gallery.
  All this is taken care of if using **Blazy Grid** under **Format**.
  The View container will then have the following attributes:

  `class="blazy blazy--LIGHTBOX-gallery ..." data-blazy data-LIGHTBOX-gallery`

2. Add a Blazy formatter for the Media or Image field.
3. Add the relevant lightbox under **Media switcher** option based on the given
   CSS class at #1.

#### Bonus
* With [Splidebox](https://drupal.org/project/splidebox), this can be used to
  have simple profile, author, product, portfolio, etc. grids containing links
  to display them directly on the same page as ajaxified lightboxes.
* With [IO](https://drupal.org/project/io), this can be used to have simple
  and modern Views infinite pagers as grid displays.
* With the new 2.17 `theme_blazy()` as a replacement for sub-modules
  `theme_ITEM()` contents, it will be easier to have hoverable product effects
  like seen at many commercial e-commerce themes.


#### <a name="views-gotchas"> </a>VIEWS GOTCHAS
Be sure to leave `Use field template` under `Style settings` unchecked.
If checked, the gallery is locked to a single entity, that is no Views gallery,
but gallery per field. The same applies when using Blazy formatter with VIS/IO
pager, alike, or inside Slick Carousel, GridStack, etc. If confusing, just
toggle this option, and you'll know which works. Only checked if Blazy formatter
is a standalone output from Views so to use field template in this case.

Check out the relevant sub-module docs for details.

***
## <a name="lightboxes"> </a>LIGHTBOXES
All lightbox integrations are optional. Meaning if the relevant modules and or
libraries are not present, nothing will show up under `Media switch` option.
Except for the new default **Flybox** since 2.17.

Clear cache if they do not appear as options due to being permanently cached.

Most lightboxes, not all, supports (responsive) image, (local|remote) video.
Known lightboxes which has supports for Responsive image:
* Colorbox, Magnific popup, Slick Lightbox, Splidebox, Blazy PhotoSwipe.
* Magnific Popup/ Splidebox also supports picture.
* Splidebox also supports AJAX contents.
* Others might not.

### Blazy has two builtin minimal lightboxes:
* **Blazybox**, seen at Intense, IO Browser, Slick Browser, ElevateZoomPlus,
  etc. Normally used as a fallback when the lightbox doesn't support multimedia.
* **Flybox**, a non-disruptive lightbox aka picture in picture window, as an
  option under Media Switcher since 2.17. It was meant for (remote) video,
  audio, soundcloud, not images. Best with non grid elements to allow viewers
  browsing the rest of page while watching videos, or listening to audios, as in
  picture in picture mode. Please create an issue to sponsor the potentials.
  **Potentials**:
  + Auto-pop/flyout the Flybox when the element is visible like for ads, etc.
  + Merge Flybox with Zooming, ElevateZoomPlus, and other lightboxes.


### Lightbox requirements
* Colorbox, PhotoSwipe, etc. requires both modules and their libraries present.
* Magnific Popup, requires only libraries to be present:
  + `/libraries/magnific-popup/dist/jquery.magnific-popup.min.js`
  The reason for no modules are being required because no special settings, nor
  re-usable options to bother provided by them. Aside from the fact, Blazy has
  its own loader aka initializer for advanced features like multimedia (remote
  |local video), or (responsive|picture) image, fieldable captions, etc. which
  are not (fully) shipped/ supported by these modules.

### <a name="dompurify"> </a> Lightbox captions with DOMPurify
Install DOMPurify using composer, see [COMPOSER](#composer) section:

* `composer require npm-asset/dompurify`

* Or, if you prefer, you can download DOMPurify directly from:
  [DOMPurify](https://github.com/cure53/DOMPurify/releases/latest)

  From the above link, you can download a zip or tar.gz archive file.
  To avoid security issues, please only install the dist directory, and
  nothing else from the archive. The composer command above will install
  the whole package.

Blazy lightboxes allows you to place a caption within lightboxes.
If you wish to use HTML in your captions, you must install the DOMPurify
library. In your `libraries` folder, you will need, either one:
* `DOMPurify/dist/purify.min.js`
* `dompurify/dist/purify.min.js`

If using Colorbox module, be sure to use their supported path to avoid dup
folders. Blazy will pick up whichever available, no problem.

The DOMPurify library is optional. Without DOMPurify, Blazy (sub)-modules
will just sanitize all captions server-side, or the very basic ones.

***
## <a name="svg"> </a>SVG
Install the SVG Sanitizer using composer, see [COMPOSER](#composer) section:

`composer require enshrined/svg-sanitize`

[Read more](https://github.com/darylldoyle/svg-sanitizer)

Blazy does not want to ship it in its `composer.json` for serious reasons,
and will disable the option for Inline SVG if not installed.

Since 2.17, the formatter **Blazy Image with VEF (deprecated)** was re-purposed
to support SVG files, instead. The name is now **Blazy File**.
Core **Image** widget doesn't support SVG files, to upload SVG use **File**:
* [/admin/structure/types/manage/page/fields](/admin/structure/types/manage/page/fields)
  + *Add a new field > Reference > File* for simple needs.
  + Enable *Description field* for SVG captions.
  + Alternatively, choose *Reference > Other > File* for more complex needs.
* [/admin/structure/types/manage/page/fields](/admin/structure/types/manage/project/page)
  + Choose **Blazy File**, and adjust anything accordingly.

The **Blazy File** can also be used for Image when SVG extension is available,
otherwise just use **Blazy Image** instead. It is kept distinct so to have
relevant form items specific for SVG files.

This is the most basic SVG in core without installing another module, and
Blazy can display it just fine either as inline SVG, or embedded SVG in IMG.

For more robust solutions, consider: SVG Image Field, SVG Image, etc.

**FYI**
* The latter will override all core formatters and widgets which makes it hard
  to uninstall without deleting many things when you have images anywhere.
  Blazy works fine with this module all along.
* The SVG form options owe credits to `SVG Image Field` module. And to honor it,
  **Blazy File** provides supports for its field type so to have Grid, and
  various Blazy features, including SVG carousels, etc. It is still WIP, but
  just fine.
* The SVG title element owes credits to `SVG Formatter`.
* If the SVG is smaller than the expected, try adding `width: 100%` to it.

***
## <a name="webp"> </a>WEBP
Drupal 9.2 has supports for WEBP conversions at Image styles admin page via
**Convert WEBP**. Only if you are concerned about old browsers, Blazy supports
it via a polyfill at Blazy UI under **No JavaScript**, be sure to NOT check it.

**Benefits**:
* Modern browsers will continue using clean IMG without being forced to use
  unnecessary PICTURE for the entire WEBP extensions.
* Old browsers will have a PICTURE if they don't support WEBP.
