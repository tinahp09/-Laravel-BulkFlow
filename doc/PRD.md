# PRD — Laravel BulkFlow

> نسخه: 0.1 (پیش‌نویس محصول)  
> وضعیت: مبنای طراحی و پیاده‌سازی  
> محصول: Laravel BulkFlow — پکیج متن‌باز برای ورود و خروج داده‌های حجیم در Laravel

## 1. خلاصهٔ محصول

BulkFlow یک پکیج Laravel است که فرایندهای import و export داده را از یک API قابل‌خواندن و یکپارچه فراهم می‌کند. مخاطب، تیمی است که می‌خواهد مثلاً ده‌ها یا صدها هزار ردیف را از CSV/XLSX به مدل‌های Eloquent وارد کند، یا خروجی قابل‌دانلود بسازد، بدون آن‌که هر بار منطق parsing، نگاشت ستون، اعتبارسنجی، batch، صف، گزارش خطا و مشاهدهٔ پیشرفت را از نو پیاده‌سازی کند.

پکیج ابتدا به‌صورت backend-first عرضه می‌شود. یک پکیج Vue مستقل در نسخه‌های بعدی، تجربهٔ انتخاب فایل، نگاشت ستون‌ها، وضعیت اجرا و خطاها را برای پنل‌های مدیریت فراهم می‌کند.

## 2. مسئله

پیاده‌سازی bulk data flow در یک پروژهٔ Laravel معمولاً پراکنده و شکننده است:

- خواندن فایل‌ها و تشخیص headerها برای هر پروژه تکرار می‌شود.
- نام ستون‌های منبع با فیلدهای مدل سازگار نیستند و mapping دستی نیاز است.
- validation و تبدیل مقدارها اغلب میان controller، job و model پخش می‌شود.
- ورود فایل بزرگ می‌تواند memory یا timeout ایجاد کند.
- صف، progress، ردیف‌های خطادار، retry و deduplication قرارداد استانداردی ندارند.
- exportهای بزرگ یا مجبور به نگه‌داشتن همهٔ داده‌ها در حافظه‌اند یا برایشان کد اختصاصی نوشته می‌شود.

BulkFlow باید این پیچیدگی را به یک flow قابل‌پیش‌بینی تبدیل کند، در حالی که توسعه‌دهنده همچنان کنترل کامل بر mapping، validation، تبدیل داده و روش persist داشته باشد.

## 3. چشم‌انداز و ارزش پیشنهادی

**چشم‌انداز:** استاندارد ساده و قابل‌اعتماد Laravel برای جابه‌جایی دادهٔ حجیم بین فایل و application.

**ارزش‌ها:**

- API fluent و نزدیک به زبان Laravel
- پردازش کم‌حافظه و قابل‌گسترش از فایل کوچک تا importهای بزرگ
- خطاهای ردیفی قابل‌ردیابی، نه خطاهای مبهم در log
- پشتیبانی از اجرای synchronous در توسعه و queue در production
- وابستگی کم به UI؛ backend به‌تنهایی مفید و قابل‌آزمون باشد
- قابلیت توسعه با driverها، listenerها و strategyهای ذخیره‌سازی

## 4. اهداف و خارج از محدوده

### اهداف V1.0

1. import و export پایدار CSV و XLSX برای Laravel.
2. API عمومی برای mapping، validation، transforms، upsert و مدیریت خطا.
3. پردازش chunked و queue-aware برای دادهٔ بزرگ.
4. مشاهدهٔ وضعیت، آمار، تاریخچه و گزارش خطا از طریق backend API و Vue package.
5. مستندات، مثال demo، تست خودکار، انتشار Packagist و NPM.

### خارج از محدودهٔ V1.0

- ETL عمومی بین هر نوع منبع داده (database-to-database، API-to-API و غیره).
- ویرایش کامل فایل Excel، فرمول‌ها، chartها و macroها.
- تضمین exactly-once در همهٔ queue driverها؛ به‌جای آن، عملیات باید idempotent طراحی شود.
- جایگزینی authorization layer پروژه؛ BulkFlow فقط hook و policy integration فراهم می‌کند.
- پشتیبانی هم‌زمان از همهٔ فرمت‌ها؛ CSV و XLSX اولویت قطعی هستند.

## 5. کاربران و سناریوهای اصلی

| کاربر | نیاز | نتیجهٔ موفق |
| --- | --- | --- |
| توسعه‌دهندهٔ Laravel | ساخت import/export با کد کم و قابل‌تست | یک definition واضح و قابل استفادهٔ مجدد دارد |
| ادمین پنل | بارگذاری فایل و دیدن وضعیت و خطاها | می‌فهمد چه چیزی وارد شد، چه چیزی نشد و چرا |
| اپراتور داده | اصلاح و retry داده‌های خطادار | فقط ردیف‌های ناموفق را دوباره اجرا می‌کند |
| تیم عملیات | اجرای امن دادهٔ بسیار بزرگ | صف، batch، timeout و memory قابل‌کنترل است |

### سناریوی کلیدی: ورود کاربران

ادمین فایل `users.xlsx` با 100,000 ردیف را آپلود می‌کند. سیستم headerها را می‌خواند؛ ادمین یا توسعه‌دهنده mapping را تعیین می‌کند؛ هر ردیف transform و validate می‌شود؛ داده در chunkهای قابل‌پیکربندی upsert می‌شود؛ وضعیت زنده و آمار نگهداری می‌شود؛ ردیف‌های نامعتبر با شمارهٔ ردیف، دادهٔ ورودی و پیام خطا ذخیره می‌شوند؛ سپس امکان export گزارش خطا یا retry همان ردیف‌ها وجود دارد.

## 6. اصول محصول

1. **Fail informatively:** خطای یک ردیف باید context کافی داشته باشد: run ID، شمارهٔ ردیف، column/attribute، مقدار امن‌شده و پیام.
2. **Streaming first:** خواندن و نوشتن نباید کل داده را پیش‌فرض در حافظه نگه دارد.
3. **Safe by default:** فایل، نوع MIME، اندازه، header و mass assignment باید کنترل‌پذیر باشند.
4. **Laravel native:** از Validation، Queue، Bus Batch، Storage، Events، Notifications و Eloquent قراردادهای آشنا استفاده شود.
5. **Progress is approximate but honest:** هنگام مشخص نبودن تعداد کل ردیف‌ها، UI نباید درصد ساختگی نشان دهد.
6. **Extensible core:** reader، writer، transformer، persistence strategy و reporting باید قابل‌جایگزینی باشند.

## 7. دامنهٔ نسخه‌ها

### V0.1 — Core

- CSV و XLSX read/write
- import و export synchronous
- header discovery و mapping برنامه‌نویسی‌شده
- validation مبتنی بر Laravel
- transformهای پایه و persistence با Eloquent
- تست واحد و integration برای flowهای اصلی

**معیار پذیرش:** توسعه‌دهنده بتواند یک فایل CSV/XLSX کوچک را با mapping و validation وارد کند، خروجی بسازد، و خطاهای validation را در نتیجه دریافت کند.

### V0.2 — Large Data

- chunking configurable برای read، validate و persist
- queue jobs و Laravel Bus batches
- retry برای خطاهای قابل‌retry در سطح job
- کاهش مصرف حافظه با streaming/cursor-oriented processing
- batch metrics و پیکربندی timeout/tries/backoff

**معیار پذیرش:** import صد‌هزار ردیف بدون نگه‌داشتن کل فایل در حافظه اجرا شود و یک job ناموفق طبق policy دوباره تلاش شود.

### V0.3 — Error System

- ذخیرهٔ failed/skipped rows
- error report قابل‌دانلود (CSV/XLSX)
- partial import با policyهای `continue`، `stop-on-threshold` و `fail-fast`
- retry فقط برای ردیف‌های شکست‌خورده

**معیار پذیرش:** اپراتور بتواند علت هر failure را ببیند، گزارش بگیرد و بدون پردازش مجدد ردیف‌های موفق، failures را retry کند.

### V0.4 — UI

- Vue Import Wizard مستقل
- نمایش progress و summary
- Mapping UI با preview نمونه داده
- Error viewer با فیلتر و export

**معیار پذیرش:** یک پنل Vue بتواند از API پکیج فایل را ثبت، mapping را ارسال، run را آغاز و نتایج را مشاهده کند.

### V0.5 — Realtime

- Redis-backed state و cache policy
- broadcast eventهای Laravel و adapter WebSocket
- progress زنده و dashboard importها

**معیار پذیرش:** با فعال‌بودن broadcasting، پیشرفت run بدون refresh در UI تغییر کند؛ با غیرفعال‌بودن آن polling همچنان کار کند.

### V0.6 — Advanced

- scheduled exports
- storage driverهای متعدد و S3
- notificationها
- import history و retention policy
- permission hooks و integration با policy/gate پروژه

**معیار پذیرش:** export زمان‌بندی‌شده روی storage انتخابی نوشته شود و کاربران مجاز فقط runهای قابل‌مشاهدهٔ خود را ببینند.

### V1.0 — انتشار پایدار

- Laravel package و Vue package پایدار با versioning مشخص
- مستندات کامل، demo application و migration guide
- test matrix و verification محلیِ قابل‌تکرار (بدون workflow خودکار در این repository)
- انتشار Packagist و NPM
- compatibility matrix برای نسخه‌های پشتیبانی‌شدهٔ PHP و Laravel

## 8. تجربهٔ توسعه‌دهنده و API پیشنهادی

### Import پایه

```php
use BulkFlow\\BulkFlow;
use App\\Models\\User;

$result = BulkFlow::import(User::class)
    ->from($file)
    ->map([
        'نام' => 'name',
        'ایمیل' => 'email',
    ])
    ->validate([
        'name' => ['required', 'string', 'max:255'],
        'email' => ['required', 'email'],
    ])
    ->upsertBy(['email'])
    ->run();
```

### Import صفی

```php
$run = BulkFlow::import(User::class)
    ->fromDisk('imports', $path)
    ->map($mapping)
    ->transform(fn (array $row) => [
        ...$row,
        'email' => strtolower(trim($row['email'] ?? '')),
    ])
    ->validate($rules)
    ->chunkSize(1_000)
    ->onError('continue')
    ->upsertBy(['email'])
    ->queue();
```

### Export پایه

```php
return BulkFlow::export(User::query()->active())
    ->columns([
        'name' => 'نام',
        'email' => 'ایمیل',
        'created_at' => 'تاریخ عضویت',
    ])
    ->asXlsx('active-users.xlsx')
    ->download();
```

### قراردادهای API

- `from()` فایل آپلودشده یا مسیر محلی را می‌پذیرد؛ `fromDisk()` برای Laravel Storage است.
- `map()` header منبع را به attribute مقصد نگاشت می‌کند. mapping ناقص باید قبل از اجرا قابل‌تشخیص باشد.
- `transform()` قبل از validation اجرا می‌شود و می‌تواند ردیف را تغییر دهد یا رد کند.
- `validate()` از Laravel validation ruleها استفاده می‌کند و باید شمارهٔ ردیف را به خطا پیوند دهد.
- `create()`، `insert()` و `upsertBy()` strategy ذخیره‌سازی را مشخص می‌کنند؛ هیچ strategy پیش‌فرضی نباید silently دادهٔ تکراری بسازد.
- `run()` synchronous و `queue()` asynchronous است؛ هر دو یک `ImportRun` با summary مشترک برمی‌گردانند.
- API عمومی باید type-friendly بوده و برای facade، dependency injection و config قابل استفاده باشد.

## 9. نیازمندی‌های عملکردی

### 9.1 ورودی و parsing

- CSV باید delimiter، enclosure، encoding و header row قابل‌پیکربندی داشته باشد.
- XLSX باید sheet قابل‌انتخاب داشته باشد و اولین sheet را فقط با انتخاب صریح یا default مستند پردازش کند.
- سیستم باید preview محدودی از header و نمونه ردیف‌ها بدهد؛ preview نباید import را شروع کند.
- حد اندازهٔ فایل، پسوندهای مجاز و MIME type از config قابل تعیین باشند.
- فایل نامعتبر باید پیش از dispatch job با خطای قابل‌فهم رد شود.

### 9.2 Mapping و تبدیل

- mapping مستقیم، callback transform و value default پشتیبانی شود.
- header matching می‌تواند case-insensitive و trim-aware باشد، اما رفتار باید config و مستند داشته باشد.
- attributeهای غیرfillable فقط با strategy یا resolver صریح مجاز باشند.
- transform exception باید به failure همان row تبدیل شود، مگر policy `fail-fast` فعال باشد.

### 9.3 Validation و persistence

- validation برای هر ردیف و در صورت نیاز برای batch قابل اجرا باشد.
- کاربر بتواند ruleهای Laravel، custom rule و message سفارشی تعریف کند.
- strategyها شامل `create`، `insert` و `upsert` هستند؛ eventهای Eloquent و تفاوت هر strategy مستند شود.
- `upsertBy` باید کلید یکتا، ستون‌های قابل‌به‌روزرسانی و رفتار مقدار null را صریح کند.
- transaction scope به‌صورت پیش‌فرض per-chunk باشد؛ atomic import کامل یک گزینهٔ جداگانه و محدود به حجم امن است.

### 9.4 اجرای صف و پیشرفت

- هر اجرا یک شناسهٔ پایدار UUID داشته باشد.
- stateهای run: `draft`، `queued`، `processing`، `completed`، `completed_with_errors`، `failed`، `cancelled`.
- شمارنده‌ها: `total_rows`، `processed_rows`، `successful_rows`، `failed_rows`، `skipped_rows` و `percentage`.
- از Laravel queue و Bus Batch استفاده شود؛ driver queue به پکیج تحمیل نشود.
- cancel فقط chunkهای شروع‌نشده را متوقف می‌کند؛ نتیجهٔ chunkهای تکمیل‌شده حفظ می‌شود.

### 9.5 خطا، گزارش و retry

- هر failed row باید source row number، raw/mapped data با redaction قابل‌پیکربندی، error code، message و timestamp داشته باشد.
- خطاهای validation، transform، persistence و infrastructure از هم تفکیک شوند.
- گزارش failure باید CSV حداقل و XLSX در صورت فعال‌بودن writer باشد.
- retry failed rows باید definition، mapping و policy اصلی را snapshot کند تا تغییرات آینده نتایج قدیمی را عوض نکند.
- برای جلوگیری از loop، تعداد retry ردیفی حد قابل‌پیکربندی دارد.

### 9.6 Export

- export از Eloquent Builder، iterable، LazyCollection یا callback row source پشتیبانی کند.
- exportهای بزرگ باید streaming/chunked باشند.
- انتخاب ستون، عنوان ستون و transform خروجی پشتیبانی شود.
- خروجی download، store روی disk و queue برای فایل بزرگ فراهم شود.

## 10. معماری پیشنهادی

```
Developer / Vue UI
        │ definition + file + options
        ▼
BulkFlow Builder ──► Import/Export Definition Snapshot
        │                         │
        │ run / queue             ▼
        ▼                   ImportRun + RowFailure store
Synchronous Runner or Queue/Batch Orchestrator
        │
        ▼
Reader → Header Resolver → Mapper → Transformer → Validator → Persistence Strategy
        │                                                        │
        └────────────── metrics / events / progress ◄──────────┘
```

### اجزای اصلی

| جزء | مسئولیت | قرارداد پیشنهادی |
| --- | --- | --- |
| `ImportBuilder` / `ExportBuilder` | fluent API و اعتبارسنجی definition | API عمومی پکیج |
| `ImportDefinition` | snapshot قابل‌serializing از تنظیمات run | immutable value object |
| `Reader` / `Writer` | خواندن/نوشتن streaming فرمت‌ها | driver interface |
| `RowPipeline` | map، transform، validate و classify row | pipeline داخلی قابل‌آزمون |
| `PersistenceStrategy` | create/insert/upsert | interface مستقل از reader |
| `RunRepository` | state، metrics و history | database implementation پیش‌فرض |
| `FailureRepository` | ذخیره و query ردیف‌های خطادار | database implementation پیش‌فرض |
| `BatchOrchestrator` | chunk jobها و retry/cancel | Laravel Queue/Bus adapter |
| `ProgressPublisher` | event/polling/realtime state | null و broadcasting adapter |

### مدل دادهٔ پیشنهادی

`bulkflow_import_runs`: شناسه، نام definition/مدل مقصد، disk/path یا reference فایل، definition snapshot، state، شمارنده‌ها، زمان‌ها، actor/tenant reference اختیاری و metadata.

`bulkflow_row_failures`: run ID، attempt، source row number، نوع failure، attribute/column، پیام/کد، payload redacted، timestamp و resolved/retried state.

migrationهای پکیج باید publishable باشند و retention/cleanup به‌صورت command زمان‌بندی‌شدنی ارائه شود.

## 11. الزامات غیرعملکردی

- **سازگاری:** نسخه‌های پشتیبانی‌شدهٔ PHP/Laravel باید در زمان V1.0 در compatibility matrix قفل شوند؛ حداقل آخرین LTS Laravel و نسخهٔ پایدار فعلی هدف هستند.
- **کارایی:** حافظه باید تابع chunk size باشد، نه تابع تعداد کل ردیف‌ها. معیار بنچمارک دقیق بر اساس محیط demo مستند می‌شود.
- **قابلیت اطمینان:** jobهای تکراری نباید سبب duplicate غیرمنتظره شوند، مشروط به آن‌که consumer کلید idempotency/`upsertBy` درست تعریف کرده باشد.
- **امنیت و حریم خصوصی:** raw payload خطادار ممکن است PII داشته باشد؛ redaction، authorization و retention ضروری‌اند. فایل‌های آپلودی نباید public-by-default باشند.
- **قابلیت مشاهده:** eventها، log context و metricها برای شروع/پایان run، completion chunk و failureهای infrastructure لازم‌اند.
- **دسترسی‌پذیری:** Vue UI باید keyboard-friendly، قابل‌ترجمه و دارای پیام‌های خطای خوانا باشد.

## 12. UI و API سطح برنامه

Vue package نباید backend business ruleها را تکرار کند. UI فقط consumer API پکیج یا endpointهای application است.

Flow پیشنهادی Wizard: انتخاب فایل → preview/header → mapping → validation summary/options → confirm → progress → result/error viewer.

endpointهای نمونه (اختیاری؛ application می‌تواند نام دلخواه داشته باشد):

- `POST /bulkflow/imports/preview`
- `POST /bulkflow/imports`
- `GET /bulkflow/imports/{run}`
- `POST /bulkflow/imports/{run}/cancel`
- `GET /bulkflow/imports/{run}/failures`
- `POST /bulkflow/imports/{run}/retry-failures`
- `GET /bulkflow/imports/{run}/failure-report`

پکیج Laravel باید service/actionهای مستقل از HTTP ارائه کند؛ route/controllerهای آماده فقط در صورت publish/opt-in فعال شوند.

## 13. رویدادها و extension points

- Eventها: `ImportQueued`، `ImportStarted`، `ChunkProcessed`، `RowFailed`، `ImportCompleted`، `ImportFailed`، `ExportCompleted`.
- Hookها: custom reader/writer، row transformer، persistence strategy، error formatter، run authorization resolver و progress publisher.
- Configها: disk، chunk size، queue connection/name، retry policy، accepted MIME/extension، retention، redaction keys، default error policy.

## 14. معیارهای پذیرش V1.0

1. توسعه‌دهنده با یک definition مستند بتواند CSV و XLSX را import و export کند.
2. importهای صفی progress و final summary دقیق شمارنده‌ها داشته باشند.
3. validation و persistence failures به row number و علت قابل‌مشاهده متصل باشند.
4. retry failed rows، ردیف‌های موفق قبلی را دوباره persist نکند.
5. تست‌ها parsing، mapping، validation، create/insert/upsert، queue orchestration، cancellation، export و failure report را پوشش دهند.
6. یک demo app، فایل نمونه و مسیر end-to-end برای 100,000 ردیف ارائه شود.
7. verification محلی روی matrix تعریف‌شدهٔ PHP/Laravel، lint/test/package build را پوشش دهد؛ workflow خودکار در این repository ایجاد نمی‌شود.
8. مستندات نصب، quick start، configuration، performance tuning، security، upgrade و troubleshooting داشته باشند.

## 15. راهبرد تست و کیفیت

- **Unit:** mapper، normalizer، transformer، rule adapter، state machine و persistence strategy.
- **Integration:** CSV/XLSX واقعی، database test، Storage fake، Queue fake و Bus batch.
- **End-to-end:** import synchronous، queued import، export queued، failure report و retry.
- **Performance regression:** fixtureهای 10k و 100k ردیفی، ثبت زمان و peak memory در اجرای محلیِ مستندِ maintainer.
- **Contract tests:** هر reader/writer driver باید contract مشترک را پاس کند.
- **Static quality:** formatter، static analysis و mutation testing به‌عنوان اهداف پس از پایدارشدن core.

## 16. مستندات و انتشار

### Laravel package

- نام، namespace و minimum dependencies پیش از اولین tag نهایی می‌شوند.
- نصب از Composer، provider/facade، migration publish و config publish مستند می‌شود.
- نسخه‌گذاری Semantic Versioning؛ تغییرات breaking فقط در major release.

### Vue package

- توزیع در NPM با peer dependencyهای شفاف برای Vue.
- headless client/composables از componentهای UI جدا باشد تا design system مصرف‌کننده قابل‌استفاده بماند.

### کانال‌های انتشار

- Packagist برای Laravel package
- NPM برای Vue package
- release tag در repository canonical و changelog برای هر release
- اجرای دستی و مستند test matrix و build پیش از انتشار کنترل‌شده با tag

## 17. ریسک‌ها و تصمیم‌های باز

| موضوع | ریسک | تصمیم پیشنهادی |
| --- | --- | --- |
| کتابخانهٔ XLSX | memory و ویژگی‌های متفاوت | یک adapter پشت interface بسازید؛ انتخاب library را قبل از V0.1 با benchmark تثبیت کنید |
| دادهٔ تکراری | retry/job duplication | upsert/idempotency را explicit کنید و create را unsafe-by-default مستند کنید |
| PII در failure store | نشت داده | redaction پیش‌فرض، private disk و retention command |
| queue driverها | semantics متفاوت retry | فقط Laravel queue contract تضمین شود؛ driver-specific caveat مستند شود |
| transaction بزرگ | lock و rollback پرهزینه | per-chunk default؛ atomic global فقط opt-in و با محدودیت |
| چند tenancy | اختلاط داده | `tenant_id`/scope resolver hook از V0.3، نه hard-code در core |

تصمیم‌هایی که پیش از آغاز V0.1 باید نهایی شوند: حداقل نسخهٔ PHP/Laravel، کتابخانهٔ XLSX، schema و نام package، و policy پیش‌فرض برای create در برابر upsert.

## 18. شاخص‌های موفقیت

- یک پروژهٔ demo بتواند import 100,000-row را با queue اجرا و summary معتبر ثبت کند.
- یک consumer بدون نوشتن parser سفارشی، در کمتر از 30 دقیقه quick start را تکمیل کند.
- failure report برای هر row ناموفق علت عملیاتی داشته باشد.
- verification دستی برای همهٔ نسخه‌های پشتیبانی‌شده سبز باشد و releaseها reproducible باشند.
- issueهای مربوط به memory/timeout در importهای بزرگ با benchmark و configuration قابل‌بازآفرینی باشند.

## 19. مراحل پیشنهادی اجرای Roadmap

1. **Foundation:** repository skeleton، package discovery، config، contracts، test fixtures و تصمیم XLSX.
2. **V0.1:** parsing و export، pipeline ردیفی، persistence، quick start و test suite.
3. **V0.2–V0.3:** snapshot/run storage، chunk jobs، metrics، failure store و retry flow.
4. **V0.4–V0.5:** HTTP adapter اختیاری، Vue wizard، polling سپس broadcasting.
5. **V0.6–V1.0:** S3/scheduler/notification/authorization hooks، hardening، demo، docs، verification محلی و انتشار.

هر milestone باید با یک نمونهٔ end-to-end، benchmark متناسب و release note بسته شود. ویژگی‌ای که API عمومی یا schema را تغییر می‌دهد قبل از توسعه باید ADR کوتاه داشته باشد.
