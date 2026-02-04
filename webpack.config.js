const defaultConfig = require('@wordpress/scripts/config/webpack.config');

module.exports = {
  ...defaultConfig,
  entry: {
    'qf-editor': './src/editor-app.js',
  },
  output: {
    path: __dirname + '/assets/js',
    filename: '[name].bundle.js',
  },
  externals: {
    'react': 'React',
    'react-dom': 'ReactDOM',
  },
};
