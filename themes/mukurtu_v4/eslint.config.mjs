import globals from "globals";
import pluginJs from "@eslint/js";

/** @type {import('eslint').Linter.Config[]} */
export default [
  {
    files: ["**/*.js"],
    languageOptions: {sourceType: "commonjs"}
  },
  {
    languageOptions: {
      globals: {
        ...globals.browser,
        Drupal: true,
        drupalSettings: true,
        drupalTranslations: true,
        domready: true,
        jQuery: true,
        _: true,
        matchMedia: true,
        Backbone: true,
        Modernizr: true,
        CKEDITOR: true,
        once: true,
        Splide: true,
      }
    }
  },
  pluginJs.configs.recommended,
];
