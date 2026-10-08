# stylelint-use-logical-spec (local copy)

This is a local copy of [stylelint-use-logical-spec](https://github.com/Jordan-Hall/stylelint-use-logical-spec) 5.0.1. Its peer range is widened to allow stylelint 17.

The published 5.0.1 release only allows stylelint below 17. The upstream fix, [PR #44](https://github.com/Jordan-Hall/stylelint-use-logical-spec/pull/44), widens the range but hasn't been released. It doesn't change the plugin code, so the built files here are unchanged from the npm 5.0.1 package. The only change is the `peerDependencies` range in `package.json`.

## When to remove this copy

When upstream publishes a release that supports stylelint 17:

1. In `themes/mukurtu_v4/package.json`, set `stylelint-use-logical-spec` back to the npm version.
2. Delete this directory.
3. Run `npm install`.
