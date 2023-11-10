## Theme Basics

This is the base theme for the Mukurtu Version 4 CMS.

This theme uses a single directory component structure, which is based on how the components (or elements) of the site are organized. Each component recieves its own directory, which contains any relevant template, Sass, or JavaScript files the component may utilize.

In addition to single directory components, the theme also borrows from the [SMACSS](https://smacss.com/) concept when organizing the components within the `components` directory. Components are organized by size, and range from base elements (colors, variables, etc) to template-level elements (full content types).

The components build upon each other and combine from smallest (00-base) to largest (04-template).

In addition to the `components` directory, the theme also includes a `templates` directory. This is where Drupal-specific templates, such as `page.html.twig`, live.

## Assets Compiling

The theme is compiled with Gulp and includes globbing for support of partials. For more information on how the compilation is working check the [gulpfile.js](gulpfile.js).

### Running the build process

At this stage in the theming, you will need to have `gulp-cli` installed on your system globally. To do this, run the following command from your Terminal:

`npm install --global gulp-cli`

From within the `mukurtu_v4` theme directory, run `npm install`. This will ensure that all of the necessary theme dependencies are installed.

Once this is complete, you should be able to run the following two commands:

- `gulp sass`: run this any time you need to manually compile Sass.
- `gulp watch`: once this command is running, Gulp will watch for any changes to Sass files in the theme, and will automatically compile all changes while it is running.

