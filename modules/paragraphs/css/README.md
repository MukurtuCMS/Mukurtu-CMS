## Contributing to paragraphs CSS code

Paragraphs uses SASS for CSS development. For contributors who want to modify
the CSS code, you have two options:

1. If you want to propose CSS improvements but do not want to use our SASS
   toolchain, just change the compiled CSS and create an issue with a patch.
   When the patch is accepted, we will transfer your changes to SASS and
   recompile CSS files.
2. The recommended way is to modify the appropriate SASS files and recompile
   them to CSS using the steps below.


## Setting up your development environment

- First, install Node.js on your machine. See
  https://nodejs.org/en/download/package-manager/ for installation instructions.

- Change directory to the paragraphs CSS folder:

  `cd paragraphs/css`

- Install required dependencies:

  `npm install`

- Compile SASS files to CSS:

  `npm run build`


## Available commands

| Command         | Description                        |
|-----------------|------------------------------------|
| `npm run build` | Lint SCSS files and compile to CSS |
| `npm run sass`  | Compile SCSS to CSS only           |
| `npm run lint`  | Lint SCSS files only               |


## Making changes

1. Locate the CSS selector rule you want to change
2. Find the corresponding rule in the appropriate SASS file
3. Make your changes in the SASS file
4. Run `npm run build` to lint and compile
5. Create a Drupal issue and patch with your changes


## Code standards

If you see warnings when running `npm run build`, they come from stylelint
which checks that generated CSS follows paragraphs coding standards.

Before submitting changes, ensure all warnings are fixed. In rare cases where
warnings cannot be avoided, use stylelint disable comments as explained in
https://stylelint.io/user-guide/ignore-code/


## Resources

SASS documentation: https://sass-lang.com/guide
