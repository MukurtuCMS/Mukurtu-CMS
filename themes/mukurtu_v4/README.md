## Theme Basics

This is the base theme for the Mukurtu CMS.

This theme uses a single directory component structure, which is based on how the components (or elements) of the site are organized. Each component recieves its own directory, which contains any relevant template, Sass, or JavaScript files the component may utilize.

In addition to single directory components, the theme also borrows from the [SMACSS](https://smacss.com/) concept when organizing the components within the `components` directory. Components are organized by size, and range from base elements (colors, variables, etc) to template-level elements (full content types).

The components build upon each other and combine from smallest (00-base) to largest (04-template).

In addition to the `components` directory, the theme also includes a `templates` directory. This is where Drupal-specific templates, such as `page.html.twig`, live.

## Assets Compiling

The theme is compiled with Gulp and includes globbing for support of partials. For more information on how the compilation is working check the [gulpfile.js](gulpfile.js).

### Running the build process

At this stage in the theming, you will need to have `gulp-cli`  and `sass` installed on your system globally. To do this, run the following command from your Terminal:

`npm install --global gulp-cli`

`npm install -g sass`

From within the `mukurtu_v4` theme directory, run `npm install`. This will ensure that all of the necessary theme dependencies are installed.

Once this is complete, you should be able to run the following two commands:

- `gulp sass`: run this any time you need to manually compile Sass.
- `gulp watch`: once this command is running, Gulp will watch for any changes to Sass files in the theme, and will automatically compile all changes while it is running.

_Note: if `gulp-watch` isn't tracking your changes, you may need to run `gulp-sass` once before running your `gulp-watch` command._

## Configuration

Theme-specific configuration - such as display modes for content types or custom
image styles - can be found within the theme directory in the `config/install`
folder. These will automatically be applied to the Drupal install once the
Mukurtu Base Theme is installed and set as default in the Appearances section of
the site.

One caveat to this is if the configuration file in your theme touches the same
file in the Mukurtu profile configuration, or in any of the custom modules found
in the `modules/custom` folder within the install profile. If your configuration
is in the same file, simply update the existing configuration in the profile or
module, rather than creating a new file in `mukurtu_v4/config/install`.

## Styles

As it stands, the `css` directory must be pushed up to the repo for the styles to be applied to the live site. When making changes locally, be sure to run `gulp sass` (to build the styles), or `gulp watch` (while developing) to ensure changes are captured. When done, commit all of your changes, including scss, css, templates, or configuration.

## Layout

The layout has been created with CSS Grid. The initialization can be found in `components/00-base/layout/_layout.scss`.

To use the grid, apply one of the grid mixins to your container class, then add each element within your container (your grid items) to the grid as needed.

There are two main grid mixins used in the theme currently:
- `layout--full-width-grid`
- `layout--full-width-grid-no-padding`

Full Width Grid can be applied to most content, and includes left and right padding. The grid itself starts after the left padding, and ends before the right padding. An example of this would be the body section of a page, for instance.

Full Width Grid No Padding is for areas you need 'full bleed' or would like your content to run the full width of the page regardless of viewport size. And example of this would be the hero image/text on a page, the breacrumbs, menus, etc.

If you need something to be full bleed, but also need its contents to adhere to the grid with padding, you can use `layout--full-width-grid-no-padding` on the container, then use `layout--full-width-grid` on an additional wrapper around your grid items.

## Icons

The icons used on the site can be found in the `images` folder of the theme. They will have .svg file extensions.

When adding an icon, it's important to do it directly in the template file using the following format:

`{% include active_theme_path() ~ '/images/name_of_icon.svg' %}`

This ensures that the color palette controls found in the Configuration page of the site can update the color of the icons when a new palette is switched.

Icons should NOT be added as `background` in CSS, or as `before` or `after` elements as the color of these can't be controlled with CSS and therefore won't be updated when the palette is switched.

While you should have all of the icons necessary for theme development in the `images` folder already, if you are needing additional icons for your work, I would recommend [Phosphor icons](https://phosphoricons.com/) as they are open source. There is a wide selection of icons to choose from, and the quality matches that of any paid icon suite.

## Font

The main font on the site is [BC Sans](https://developer.gov.bc.ca/Typography), which was selected for its wide support of Indigenous languages. If newer versions of the font become available and you'd like to add them to the theme, simply download the appropriate font files (WOFF and WOFF2 generally) to the `fonts` folder in the theme, and update the relative path in `components/00-base/typography/_fonts.scss` as needed.

If a serif font is ever required, I would recommend checking out [First Nations Unicode Font](https://fnel.arts.ubc.ca/resources/font/#:~:text=The%20First%20Nations%20Unicode%20Font,First%20Nations%20Unicode%20Font%20%5BFNuni_v2.), which seems similar to BC Sans in its support of Indigenous languages.
