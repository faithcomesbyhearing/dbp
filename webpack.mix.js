const mix = require('laravel-mix')

mix.js('resources/assets/js/app.js', 'public/js').vue();

mix.sass('resources/assets/sass/app.scss', 'public/css');
mix.sass('resources/assets/sass/style.scss', 'public/css');
mix.sass('resources/assets/sass/app_api_key.scss', 'public/css');

if (mix.inProduction()) {
	module.exports = { mode: 'production' };
	mix.version();
}

mix.browserSync(process.env.APP_URL || 'https://dbp.test');
