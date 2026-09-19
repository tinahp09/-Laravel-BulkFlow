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

In another terminal, process queued browser imports:

```bash
php demo/laravel-app/artisan queue:work
```

Open `http://127.0.0.1:5174`. Use **Import users** to select a CSV or XLSX,
preview it, and map one source column each to `name`, `email`, and `password`.
The demo imports only `User` records and upserts by email. For example:

```csv
name,email,password
Neda,neda@example.test,change-me
```

After submission, the queued run is selected automatically. An invalid email is
stored as a failed row and appears in the Error Viewer instead of failing the
whole import.
