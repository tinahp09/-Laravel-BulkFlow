# @bulkflow/vue

Vue 3 UI primitives for Laravel BulkFlow.

```ts
import {
  BulkFlowClient,
  ErrorViewer,
  ImportDashboard,
  ImportProgress,
  ImportWizard,
} from '@bulkflow/vue';

const client = new BulkFlowClient('/bulkflow');
```

`ImportWizard` emits a source-header to destination-attribute mapping. `ImportProgress` and `ErrorViewer` are presentational components. `ImportDashboard` renders history returned by `client.listRuns()`.
## Retry selected failures

`ErrorViewer` emits the current run ID and, when the user ticks rows, their failure IDs:

```vue
<ErrorViewer
  :run-id="runId"
  :failures="failures"
  @retry="(id, failureIds) => client.retryFailures(id, failureIds)"
/>
```

With no selected IDs, `retryFailures(runId)` preserves the backend default of retrying all pending failures. Resolved failures are included in the type returned by `getFailures()` so consumers can render or filter them appropriately.

`ErrorViewer` emits `filter` with `pending`, `resolved`, or `undefined` when its status selector changes. Load the corresponding page through `client.getFailuresPage(runId, { status })` and pass its `data` back to the component.

For server-side reports, pass `client.failureReportUrl(runId, 'csv')` as `report-url` and `client.failureReportUrl(runId, 'xlsx')` as `xlsx-report-url`; the component renders standard download links. When `getFailuresPage()` returns more than one page, also pass `current-page`, `last-page`, and `total-failures`, then reload data in response to the component's `page-change` event.

## Cancel an active import

Use `client.cancelImport(runId)` to cancel a `queued` or `processing` run. The returned run summary has state `cancelled`; treat it as terminal and stop polling it.

## Realtime adapter

`ImportProgressTracker` has no direct npm dependency on Laravel Echo. The package includes `LaravelEchoProgressSource`, which accepts an Echo-compatible instance; the tracker ignores updates with an older `revision` than its current polling or realtime state.

Use `tracker.poll(3000, onUpdate, onError)` when no realtime transport is available. It returns a stop function; stop polling once the run reaches a terminal state or the component unmounts. `isTerminalImportState(run.state)` includes `cancelled` as well as completed and failed states.

```ts
import { ImportProgressTracker, LaravelEchoProgressSource } from '@bulkflow/vue';

const tracker = new ImportProgressTracker(client, runId);
const disconnect = tracker.connect(new LaravelEchoProgressSource(echo));

// Call disconnect() when the component unmounts.
```

The adapter subscribes to the private `bulkflow.imports.{runId}` channel and
the namespaced event `.bulkflow.progress.updated` automatically.
