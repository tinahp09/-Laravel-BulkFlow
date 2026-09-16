# Laravel BulkFlow

Monorepo for the Laravel BulkFlow ecosystem.

BulkFlow provides durable, streaming CSV/XLSX import and export flows for Laravel, plus a Vue 3 UI package for mapping, progress, history and failed-row review. The demo includes a reproducible 100,000-row queued benchmark.

## Packages

- `packages/laravel-bulkflow`: Laravel package; will be published to Packagist.
- `packages/vue-bulkflow`: Vue 3 UI package; will be published to NPM.
- `demo/laravel-app`: local Laravel application used for end-to-end verification.
- `demo/vue-app`: independent Vite application which consumes the Vue package and proxies to the Laravel demo API.

## Local development

The Vue workspace uses Node.js 20 or later. The Laravel package requires PHP 8.2 or later and a supported Laravel version. The demo app is intentionally kept separate from the packages so it can exercise their public APIs like a consumer application.

GitHub Actions and release workflows are intentionally not included.

Supported version details and the manually verified framework matrix are in
[doc/COMPATIBILITY.md](doc/COMPATIBILITY.md).

## Run the full demo locally

Install JavaScript dependencies once, then start each side in a separate terminal:

```bash
npm install
php demo/laravel-app/artisan migrate
php demo/laravel-app/artisan serve --host=127.0.0.1 --port=8011
```

```bash
npm run dev:frontend
```

Open `http://127.0.0.1:5174`. The frontend proxies its `/bulkflow` requests to the Laravel demo on port `8011`. Create a smoke-test run with:

```bash
php demo/laravel-app/artisan bulkflow:benchmark-import --rows=1000 --chunk=100 --queued --sync
```

## License

[MIT](LICENSE)
