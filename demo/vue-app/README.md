# BulkFlow Vue demo

This is the browser reference application for the two packages. It uses the source
of `@bulkflow/vue` through Vite and proxies `/bulkflow` API calls to the Laravel demo.

Run the Laravel server first from the repository root:

```bash
php demo/laravel-app/artisan serve --host=127.0.0.1 --port=8011
```

Then run this app from the repository root:

```bash
npm run dev:frontend
```

Open `http://127.0.0.1:5174`. Create an import with the Laravel demo benchmark command,
then refresh the import history here.
