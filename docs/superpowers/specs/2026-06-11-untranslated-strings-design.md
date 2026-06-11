# Elgentos_UntranslatedStrings — Design

**Date:** 2026-06-11
**Status:** Approved (feature scope and async queue confirmed by Peter Jaap)

## Purpose

A Magento 2 equivalent of the Magento 1 extension
[EW_UntranslatedStrings](https://github.com/ericthehacker/magento-untranslatedstrings):
detect strings that are rendered without a translation for the current store
locale, report them in an admin grid, and (new in this module) translate them
automatically with Claude (Anthropic API).

## Scope (user-selected)

| Area | In scope | Out of scope |
|---|---|---|
| Detection | Runtime detection via phrase renderer plugin | Static CLI scan, batch multi-locale checks, exclude regexes |
| Reporting | DB table + admin grid (filter/sort/export/mass actions) | Log file, agency CSV export |
| Fixing | AI auto-translate with Claude via async message queue | Manual admin translation editing, CSV import |
| Automation | Message-queue consumer (DB transport) | Cron collection, frontend highlighting |

## Architecture

### 1. Detection

- `Plugin\DetectUntranslatedString` — `after` plugin on
  `Magento\Framework\Phrase\Renderer\Translate::render()`.
- A string is untranslated when it is not a key in the loaded translation
  dictionary (`TranslateInterface::getData()`) for the current locale. This
  covers i18n CSVs, theme translations, and DB translations uniformly.
- Guards: module disabled (memoized per request), empty/whitespace strings,
  re-entrancy flag, never throws into the rendering path.
- Findings go to `Model\Collector`: an in-request buffer keyed by
  `sha1(locale|store|string)` with occurrence counts, flushed to the DB once
  at request shutdown (destructor). Existing rows get `count` incremented;
  new rows are inserted with `insertOnDuplicate` to tolerate concurrent
  requests racing on the unique hash.

### 2. Storage & reporting

- Table `elgentos_untranslated_strings`: `entity_id`, `string_hash` (unique),
  `string`, `locale`, `store_id`, `url` (first seen), `count`, `status`
  (`new` / `queued` / `error`), `error`, timestamps.
- Admin grid under **Reports → Untranslated Strings**
  (`elgentos_untranslatedstrings/strings/index`): UI component listing with
  filters, sorting, paging, bookmarks, CSV/XML export, and mass actions
  **Delete** and **Translate with Claude**. ACL resource
  `Elgentos_UntranslatedStrings::untranslated_strings` under
  `Magento_Reports::report`.

### 3. AI translation (async queue)

- Mass action `Strings\MassTranslate` chunks the selected row IDs (40 per
  message), publishes JSON payloads `{"ids": [...]}` to topic
  `elgentos.untranslatedstrings.translate`, and marks rows `queued`.
- Queue uses the **DB transport** (`connection="db"`), so it works without
  RabbitMQ. Consumer name: `elgentosUntranslatedStringsTranslate`. Magento's
  default cron consumers-runner picks it up, or run
  `bin/magento queue:consumers:start elgentosUntranslatedStringsTranslate`.
- `Model\Queue\TranslateConsumer` groups rows by (locale, store), calls
  `Model\Translation\ClaudeTranslator`, and:
  - writes results into Magento's core `translation` table via
    `Model\Translation\TranslationWriter` (`insertOnDuplicate`, including
    `crc_string`), store-scoped — Magento loads these natively;
  - deletes successfully translated rows from the grid;
  - marks failed rows `status=error` with the error message (no automatic
    retry — re-select and re-queue from the grid);
  - invalidates the `translate` and `full_page` caches when anything was
    written.

### 4. Claude integration

- Official `anthropic-ai/sdk` PHP package (composer requirement).
- One Messages API call per (locale, store) group, non-streaming,
  `max_tokens` 16000, structured output (`output_config.format` with a JSON
  schema: `{translations: [{source, translation}]}`) so responses are
  guaranteed parseable.
- System prompt: professional e-commerce translator; preserve Magento
  placeholders (`%1`…`%n`, `%s`, `%d`, named placeholders), HTML tags, and
  punctuation; never add commentary.
- `stop_reason: refusal` and API errors surface as row-level `error` status.

### 5. Configuration

**Stores → Configuration → Advanced → Untranslated Strings** (own section —
the Developer section is hidden in production mode, this one is not):

| Field | Scope | Notes |
|---|---|---|
| `untranslated_strings/general/enabled` | store view | default 0 |
| `untranslated_strings/general/api_key` | default | obscure + encrypted backend |
| `untranslated_strings/general/model` | default | default `claude-opus-4-8` |

## Compatibility

Magento 2.4.4+ (declarative schema, UI components, message queue DB
transport), PHP 8.1+.

## Error handling summary

- Detection never breaks rendering (catch-all, re-entrancy guard).
- Collector flush failures are logged and swallowed.
- Translation failures are per-group: one bad batch doesn't block others;
  rows keep their text and show the error in the grid.

## Testing

No Magento installation is available in this workspace; verification is
`php -l` + `xmllint` on all files. Functional testing happens on a real
Magento 2 installation (see README).
