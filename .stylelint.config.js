/** @type {import('stylelint').Config} */
module.exports = {
	extends: '@wordpress/stylelint-config/scss',
	ignoreFiles: [
		'**/*.js',
		'**/*.json',
		'**/*.jsx',
		'**/*.php',
		'**/*.svg',
		'**/*.ts',
		'**/*.tsx',
	],
	rules: {},
};
