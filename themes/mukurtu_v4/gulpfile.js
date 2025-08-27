"use strict";

const { series, parallel, watch, src, dest } = require("gulp");
const stylelint = require("stylelint");
const autoprefixer = require("gulp-autoprefixer");
const sourcemaps = require("gulp-sourcemaps");
const dartSass = require("sass");
const gulpSass = require("gulp-sass");
const { ESLint } = require("eslint");
const imagemin = require("gulp-imagemin");
const pngquant = require("imagemin-pngquant");
const fs = require("fs");

const sassCompiler = gulpSass(dartSass);

async function lintStyles(cb) {
  return stylelint.lint({
    files: [
      "./components/**/*.scss",
      "./css/*.css"
    ],
    formatter: 'string',
    console: true
  })
    .then((data) => {
      process.stdout.write(data.report);
      cb();
    })
    .catch((err) => {
      console.error(err.stack);
      cb(err);
    });
}

function buildStyles() {
  return src("./components/**/*.scss")
    .pipe(sourcemaps.init())
    .pipe(sassCompiler({ style: "compressed" }).on("error", sassCompiler.logError))
    .pipe(autoprefixer("last 2 versions"))
    .pipe(sourcemaps.write("."))
    .pipe(dest("./css"));
}

async function lintAndFixJS(eslint, filePaths) {
  const results = await eslint.lintFiles(filePaths);
  await ESLint.outputFixes(results);
  return results;
}

function outputJSLintingResults(results) {
  const problems = results.reduce(
    (acc, result) => acc + result.errorCount + result.warningCount,
    0,
  );
  if (problems > 0) {
    for (const result of results) {
      console.log(result.filePath);
      for (const message of result.messages) {
        console.log(`\t${message.ruleId}: ${message.message} on line ${message.line}`);
      }
      console.log("\n");
    }
  }
  return results;
}

async function lintScripts() {
  const results = await lintAndFixJS(
    new ESLint({
      fix: true,
      errorOnUnmatchedPattern: false,
    }),
    [
      "./components/**/*.js",
      "./js/**/*.js"
    ]
  );
  return outputJSLintingResults(results);
}

function minifyImages() {
  return src("./src/images/**/*")
    .pipe(
      imagemin({
        progressive: true,
        svgoPlugins: [{ removeViewBox: false }],
        use: [pngquant()],
      })
    )
    .pipe(dest("./images"));
}

function copyLibraries() {
  // Ensure libraries directory exists
  const librariesDir = "./libraries";
  if (!fs.existsSync(librariesDir)) {
    fs.mkdirSync(librariesDir, { recursive: true });
  }

  // Copy GLightbox files
  return src([
    "./node_modules/glightbox/dist/css/glightbox.min.css",
    "./node_modules/glightbox/dist/js/glightbox.min.js"
  ])
    .pipe(dest("./libraries/glightbox"));
}

function watchFiles() {
  watch("./components/**/*.scss", function watchScss(cb) {
    series(lintStyles, buildStyles)((err) => {
      if (err) {
        console.error(err);
      }
      cb();
    });
  });
  watch([
    "./js/**/*.js",
    "./components/**/*.js",
  ], lintScripts);
  watch([
    "./css/00-base/**/*.css",
    "./css/content-warnings.css",
    "./css/leaflet-overrides.css"
  ], lintStyles)
}

exports.imagemin = minifyImages;
exports.eslint = lintScripts;
exports.stylelint = lintStyles;
exports.buildSass = buildStyles;
exports.copyLibraries = copyLibraries;
exports.sass = series(lintStyles, buildStyles);
exports.watch = watchFiles;

exports.default = parallel(minifyImages, lintScripts, series(lintStyles, buildStyles), copyLibraries);
