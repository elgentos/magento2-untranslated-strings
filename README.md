# elgentos/magento2-untranslated-strings

Magento 2 module that detects **untranslated strings at runtime**, reports them
in an admin grid and can **translate them automatically with Claude**
(Anthropic API) through Magento's message queue.

A spiritual successor to the Magento 1 extension
[EW_UntranslatedStrings](https://github.com/ericthehacker/magento-untranslatedstrings).

## How it works

1. **Detect** — a plugin on `Magento\Framework\Phrase\Renderer\Translate`
   checks every rendered phrase against the loaded translation dictionary
   (i18n CSVs, theme translations and database translations) for the current
   store locale. Strings without a translation are buffered per request and
   flushed to the database in a single query at shutdown.
2. **Report** — findings appear under **Reports → Untranslated Strings**:
   filterable, sortable and exportable, with occurrence counts, store view,
   locale and the URL where the string was first seen.
3. **Translate** — select rows and use the **Translate with Claude** mass
   action. The rows are queued (Magento message queue, DB transport — no
   RabbitMQ required) and a consumer sends them to the Anthropic API in
   batches. Successful translations are written to Magento's native
   `translation` database table (store-scoped) and go live after the
   automatically-invalidated `translate` / `full_page` caches are refreshed.
   Failed rows get status *Error* with the error message in the grid;
   re-select them to retry.

## Installation

```bash
composer require elgentos/magento2-untranslated-strings
bin/magento module:enable Elgentos_UntranslatedStrings
bin/magento setup:upgrade
```

## Configuration

**Stores → Configuration → Advanced → Untranslated Strings**

| Setting | Scope | Description |
|---|---|---|
| Log Untranslated Strings | store view | Enables runtime detection. Off by default. |
| Anthropic API Key | global | Encrypted. Create one at [console.anthropic.com](https://console.anthropic.com). |
| Claude Model | global | Defaults to `claude-opus-4-8`. |

Detection adds a small overhead per rendered phrase — enable it per store
view, ideally on development or staging while you browse/crawl the shop, then
review the report.

## The queue consumer

Translation runs asynchronously through the consumer
`elgentosUntranslatedStringsTranslate`. With Magento's default
`consumers_runner` cron configuration it starts automatically. To run it
manually:

```bash
bin/magento queue:consumers:start elgentosUntranslatedStringsTranslate
```

## Notes

- Translations are stored in the core `translation` table, so they survive
  deployments and can be reviewed/edited with any DB-translation tooling.
- The Claude prompt instructs the model to preserve placeholders
  (`%1`…`%9`, `%s`, `%d`, named placeholders), HTML and punctuation, and to
  keep brand/product names untranslated. Structured output (JSON schema)
  guarantees a parseable response.
- Detection never breaks rendering: all failure paths are caught and logged.

## License

MIT
