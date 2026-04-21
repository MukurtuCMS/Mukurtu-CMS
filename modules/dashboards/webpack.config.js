const path = require('path');

module.exports = {
  entry: {
    chart: path.resolve(path.join(__dirname, 'js', 'chart.jsx')),
  },
  output: {
    path: path.resolve(path.join(__dirname, 'js', 'es')),
    filename: '[name].js',
  },
  watchOptions: {
    poll: true,
  },
  module: {
    rules: [
      {
        test: /\.jsx$/,
        exclude: /node_modules/,
        loader: 'babel-loader',
        options: {
          presets: [
            [
              '@babel/preset-env',
              {
                useBuiltIns: 'entry',
              },
            ],
          ],
        },
      },
    ],
  },
};
