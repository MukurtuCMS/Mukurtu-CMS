const path = require('path');
const isDev = (process.env.NODE_ENV !== 'production');
const glbClassesGenerator = require('./postcss-glb-classes-generator')({targetPath: path.resolve(__dirname, './src/classes.json')});
const CleanWebpackPlugin = require('clean-webpack-plugin');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const FriendlyErrorsWebpackPlugin = require('friendly-errors-webpack-plugin');
const globImporter = require('sass-glob-importer');
const FixStyleOnlyEntriesPlugin = require('webpack-fix-style-only-entries');
const autoprefixer = require('autoprefixer');

module.exports = {
  entry: {
    gin_lb: ['./styles/gin_lb.scss'],
    olivero: ['./styles/olivero.scss'],
    gin_lb_10: ['./styles/gin_lb_10.scss'],
  },
  output: {
    devtoolLineToLine: true,
    path: path.resolve(__dirname, 'dist'),
    chunkFilename: 'js/async/[name].chunk.js',
    pathinfo: true,
    filename: 'js/[name].js',
    publicPath: '../',
  },
  module: {
    rules: [{
        test: /\.(config.js)$/,
        use: [
          {
            loader: 'file-loader',
            options: {
              name: '[path][name].[ext]',
              outputPath: './'
            }
          }
        ]
      },
      {
        test: /\.(png|jpe?g|gif|svg)$/,
        exclude: /sprite\.svg$/,
        use: [{
            loader: 'file-loader',
            options: {
              name: 'media/[name].[ext]?[hash]',
            },
          },
        ],
      },
      {
        test: /sprite\.svg$/,
        use: [{
            loader: 'file-loader',
            options: {
              name: 'media/[name].[ext]',
            },
          },
        ],
      },
      {
        test: /\.js$/,
        exclude: /node_modules/,
        use: {
          loader: 'babel-loader',
        },
      },
      {
        test: /\.(css|sass|scss)$/,
        use: [
          {
            loader: MiniCssExtractPlugin.loader,
            options: {
              name: '[name].[ext]?[hash]',
            }
          },
          {
            loader: 'css-loader',
            options: {
              sourceMap: isDev,
              importLoaders: 2,
            },
          },
          {
            loader: 'postcss-loader',
            options: {
              postcssOptions: {
                plugins: [
                  autoprefixer(),
                  glbClassesGenerator.generate
                ],
                sourceMap: isDev,
              }
            },
          },
          {
            loader: 'sass-loader',
            options: {
              sourceMap: isDev,
              sassOptions: {
                importer: globImporter()
              },
            },
          },
        ],
      },
    ],
  },
  resolve: {
    modules: [
      path.join(__dirname, 'node_modules'),
    ],
    alias: {
      gin: path.resolve('build/themes/contrib/gin/styles'),
      claro: path.resolve('build/core/themes/claro/css')
    },
    extensions: ['.js', '.json'],
  },
  plugins: [
    new FriendlyErrorsWebpackPlugin(),
    new FixStyleOnlyEntriesPlugin(),
    new CleanWebpackPlugin(['dist'], {
      root: path.resolve(__dirname),
    }),
    new MiniCssExtractPlugin({
      filename: 'css/[name].css',
    }),
  ],
  watchOptions: {
    aggregateTimeout: 300,
  }
};
