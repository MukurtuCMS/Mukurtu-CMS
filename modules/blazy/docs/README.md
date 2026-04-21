
## <a name="top"> </a>CONTENTS OF THIS FILE

 * [Introduction](#introduction)
 * [Upgrading from 1.x](https://www.drupal.org/project/blazy#blazy-upgrade)
 * [Update SOP](#updating)
 * [Requirements](#requirements)
 * [Recommended modules](#recommended-modules)
 * [Installation](#installation)
 * [Installing libraries via Composer](#composer)
 * [Configuration](#configuration)
 * [theme_blazy()](#theme-blazy)
 * [Hero media](#heroes)
 * [Multimedia galleries](#galleries)
 * [Lightboxes](#lightboxes)
 * [SVG](#svg)
 * [WEBP](#webp)
 * [Features](#features)
 * [Troubleshooting](#troubleshooting)
 * [Aspect ratio](#aspect-ratio)
 * [Aspect ratio template](#aspect-ratio-template)
 * [Roadmap](#roadmap)
 * [FAQ](#faq)
 * [Contribution](#contribution)
 * [Maintainers](#maintainers)
 * [Notable changes](#changes)


***
## <a name="introduction"></a>INTRODUCTION
Provides integration with bLazy and or Intersection Observer API, or browser
native lazy loading to lazy load and multi-serve images to save bandwidth and
server requests. The user will have faster load times and save data usage if
they don't browse the whole page.

Check out [project home](https://www.drupal.org/project/blazy) for most updated
info.

***
## <a name="first"> </a>FIRST THINGS FIRST!
Blazy and its sub-modules are tightly coupled. Be sure to have matching versions
or the latest release date in the least. DEV for DEV, Beta for Beta/RC,
etc. Mismatched versions (DEV vs. Full release) may lead to errors, except for
minor versions like Beta vs. RC. Mismatched branches (1.x vs. 2.x) will surely
be errors, unless declared clearly as supported. What is `coupled`? Blazy
sub-modules are dependent on Blazy, just like Blazy depends on core Media.
If core Media is not installed, Blazy is not usable. In the case of Blazy, it is
a bit `tighter` since it also acts as a DRY buster aka boilerplate reducer for
many similar sub-modules with some degree of difference. If confusing, just
match the latest releases.
We tried to minimize this issue, but if that happens you are well informed.

***
## <a name="requirements"> </a>REQUIREMENTS
Core modules:
1. Media
2. Filter

***
## <a name="recommended-modules"> </a>RECOMMENDED LIBRARIES/ MODULES
For better admin help page, either way will do, ordered by recommendation:

* `composer require league/commonmark`
* `composer require michelf/php-markdown`
* [Markdown](https://www.drupal.org/project/markdown)

To make reading this README a breeze at [Blazy help](/admin/help/blazy_ui)


### MODULES THAT INTEGRATE WITH OR REQUIRE BLAZY
* [Ajaxin](https://www.drupal.org/project/ajaxin)
* [Intersection Observer](https://www.drupal.org/project/io)
* [Blazy PhotoSwipe](https://www.drupal.org/project/blazy_photoswipe)
* [GridStack](https://www.drupal.org/project/gridstack)
* [Outlayer](https://www.drupal.org/project/outlayer)
* [Intense](https://www.drupal.org/project/intense)
* [Mason](https://www.drupal.org/project/mason)
* [Slick](https://www.drupal.org/project/slick)
* [Slick Lightbox](https://www.drupal.org/project/slick_lightbox)
* [Slick Views](https://www.drupal.org/project/slick_views)
* [Slick Paragraphs](https://www.drupal.org/project/slick_paragraphs)
* [Slick Browser](https://www.drupal.org/project/slick_browser)
* [Splide](https://www.drupal.org/project/splide)
* [Splidebox](https://www.drupal.org/project/splidebox)
* [Jumper](https://www.drupal.org/project/jumper)
* [Zooming](https://www.drupal.org/project/zooming)
* [ElevateZoom Plus](https://www.drupal.org/project/elevatezoomplus)
* [Ultimenu](https://www.drupal.org/project/ultimenu)

Most duplication efforts from the above modules will be merged into
`\Drupal\blazy\Dejavu`, or anywhere else namespaces.


**What dups?**

The most obvious is the removal of formatters from Intense, Zooming,
Slick Lightbox, Blazy PhotoSwipe, and other (quasi-)lightboxes. Any lightbox
supported by Blazy can use Blazy, or Slick formatters if applicable instead.
We do not have separate formatters when its prime functionality is embedding
a lightbox, or superceded by Blazy.


### SIMILAR MODULES
[Lazyloader](https://www.drupal.org/project/lazyloader)


***
## <a name="installation"> </a>INSTALLATION
1. **MANUAL:**

   Install the module as usual, more info can be found on:

   [Installing Drupal Modules](https://drupal.org/node/1897420)

2. **COMPOSER:**

   See [Composer](#composer) section below for details.


***
## <a name="configuration"> </a>CONFIGURATION
Visit the following to configure and make use of Blazy:

1. [/admin/config/media/blazy](/admin/config/media/blazy)

   Enable Blazy UI sub-module first, otherwise regular **404|403**.
   Contains few global options. Blazy UI can be uninstalled at production later
   without problems.

2. Visit any entity types:

   + [Content types](/admin/structure/types)
   + [Block types](/admin/structure/block/block-content/types)
   + `/admin/structure/paragraphs_type`
   + etc.

   Use Blazy as a formatter under **Manage display** for the supported fields:
   Image, Media, Entity reference, or even Text.

3. `/admin/structure/views`

   Use `Blazy Grid` as standalone blocks, or pages.

***
## <a name="features"> </a>FEATURES
* **Deep Integration**:

  Seamlessly works with Core Media, Views, Paragraphs, and Media contrib
  modules. Supports Image, Responsive image, (local|remote|iframe) videos, SVG,
  DIV (CSS backgrounds), either inline, fields, views, or within lightboxes.
  * Field formatters: Blazy with Media, Paragraphs, and entities integrations.
  * Instagram, Pinterest, Twitter, Youtube, Vimeo, Soundcloud, Facebook
    within some lightboxes.
* **LCP & CLS Management**:

  Engineered for a **"CLS-zero" strategy**, our framework integrates
  **sophisticated preloading** alongside native `fetchpriority` and `decoding`
  to systematically eliminate LCP discovery delays. We provide rigorous
  optimization for every asset—from **standard images**, **CSS backgrounds**
  and **responsive picture elements** to **optimized video posters**. While we
  leverage modern CSS `aspect-ratio` for layout stability, we maintain a refined
  **padding-bottom fallback** to ensure backward compatibility (BC) without
  sacrificing precision.
* **Intelligent Lazy-loading**:

  Supports modern Native lazyload since [incubation](https://drupal.org/node/3104542)
  before Firefox or core had it, or old `data-[src|srcset]` since eons.
  Sophisticated preloading via the Blazy engine  for images, CSS backgrounds,
  iframes, SVG, HTML5 video, audio, and HTML media. Must be
  noted very clearly due to some thought Blazy was retarded from core.
  * Supports WEBP.
  * Works absurdly fine at IE9 for Blazy 2.6.
  * Works without JavaScript within/without JavaScript browsers.
  * Works with AMP, or static/ archived sites, e.g.: Tome, HTTrack, etc.
  * Multi-serving lazyloaded images, including multi-breakpoint CSS backgrounds.
  * Delay loading for below-fold images until 100px (configurable) before they
    are visible at viewport.
  * A simple effortless CSS loading indicator.
* **Privacy & GDPR Compliance**:

  Utilizes a **Two-Click Media Loader** via the "Image to Iframe" option.
  No third-party tracking scripts are initialized until the user actively
  engages with the play button—satisfying strict **GDPR and ePrivacy**
  requirements.
* **Developer Friendly**:

  Features a "Vanilla" mode and a
  [robust API](https://git.drupalcode.org/project/blazy/blob/3.0.x/blazy.api.php)
  for custom/theme implementations.
* **Robust content supports:**

  HTML, responsive image/ picture, responsive iframe, SVG, video, audio and
  third party contents.
* **Inline & lightbox mixed-media:**

  A single **Media switcher** option for various interactions: image to content,
  iframe, and (quasi-)lightboxes: Slick lightbox, Colorbox, PhotoSwipe, Flybox,
  Magnific Popup, Zooming, etc.
* **Advanced Gallery Grids:**

  * Blazy Grid formatter and Views style for multi-value Image, Media and Text:
    CSS3 Columns, Grid Foundation, Flexbox, Native Grid.
  * Supports inline galleries, and grid or CSS3 Masonry via Blazy Filter.
    Enable Blazy Filter at **/admin/config/content/formats**.
  * Simple shortcodes for inline galleries, check out **/filter/tips**.

* It doesn't take over all images, so it can be enabled as needed via Blazy
  formatter, or its supporting modules.


### OPTIONAL FEATURES
* Views fields for File Entity and Media integration, see:
  + [IO Browser](https://www.drupal.org/project/io)
  + [Slick Browser](https://www.drupal.org/project/slick_browser).
* Views style plugin `Blazy Grid` for CSS3 Columns, Grid Foundation, Flexbox,
  and Native Grid.

***
## <a name="maintainers"> </a>MAINTAINERS/CREDITS
* [Gaus Surahman](https://www.drupal.org/user/159062)
* [geek-merlin](https://www.drupal.org/u/geek-merlin)
* [sun](https://www.drupal.org/u/sun)
* [gambry](https://www.drupal.org/u/gambry)
* [Contributors](https://www.drupal.org/node/2663268/committers)
* CHANGELOG.txt for helpful souls with their patches, suggestions and reports.


## READ MORE
See the project page on drupal.org for more updated info:

* [Blazy module](https://www.drupal.org/project/blazy)

See the bLazy docs at:

* [Blazy library](https://github.com/dinbror/blazy)
* [Blazy website](https://dinbror.dk/blazy/)
