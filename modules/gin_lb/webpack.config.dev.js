const merge = require('webpack-merge');
const webpackConfig = require('./webpack.config');
const SourceMapDevToolPlugin = require('webpack/lib/SourceMapDevToolPlugin');

module.exports = merge(webpackConfig, {
  mode: 'development',
  devtool: 'eval-source-map',
  plugins: [
    new SourceMapDevToolPlugin({
      filename: '[file].map',
      exclude: [/node_modules/, /images/, /spritemap/, /svg-sprites/],
    })
  ]
});
