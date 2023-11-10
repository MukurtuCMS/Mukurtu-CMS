## Assets Compiling

The theme is compiled with Gulp and includes globbing for support of partials. For more information on how the compilation is working check the [gulpfile.js](gulpfile.js).

### Running the build process

At this stage in the theming, you will need to have `gulp-cli` installed on your system globally. To do this, run the following command from your Terminal:

`npm install --global gulp-cli`

From within the `mukurtu_v4` theme directory, run `npm install`. This will ensure that all of the necessary theme dependencies are installed.

Once this is complete, you should be able to run the following two commands:

- `gulp sass`: run this any time you need to manually compile Sass.
- `gulp watch`: once this command is running, Gulp will watch for any changes to Sass files in the theme, and will automatically compile all changes while it is running.

