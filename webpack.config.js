const path = require('path');
const { CleanWebpackPlugin } = require('clean-webpack-plugin');
const defaultConfig = require('@wordpress/scripts/config/webpack.config');
const CopyWebpackPlugin = require('copy-webpack-plugin');

module.exports = {
	...defaultConfig,
	entry: {
		'qf-editor': './src/editor-app.js',
		'qf-block': './src/block-edit.js',
	},
	output: {
		...defaultConfig.output,
		path: path.resolve(__dirname, 'assets/js'),
		filename: '[name].bundle.js',
	},
	externals: {
		react: 'React',
		'react-dom': 'ReactDOM',
	},
	plugins: [
		...(defaultConfig.plugins || []).map((plugin) => {
			if (plugin.constructor.name !== 'CleanWebpackPlugin') {
				return plugin;
			}
			// Match @wordpress/scripts defaults, but exclude qf-widget.js from
			// cleanAfterEveryBuildPatterns. Copied assets are not always listed in
			// stats.assets, so del() would otherwise remove them after each build.
			return new CleanWebpackPlugin({
				cleanAfterEveryBuildPatterns: [
					'!fonts/**',
					'!images/**',
					'!qf-widget.js',
				],
				cleanStaleWebpackAssets: false,
			});
		}),
		new CopyWebpackPlugin({
			patterns: [
				{
					from: path.resolve(__dirname, 'src/qf-widget.js'),
					to: 'qf-widget.js',
				},
			],
		}),
	],
};
