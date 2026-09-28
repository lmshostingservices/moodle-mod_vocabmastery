# Changelog

All notable changes to mod_vocabmastery are documented here.

## [v0.9.2] - 2026-09-28

### Security
- Use Moodle's `require_login($course)` on the course activity index, retaining the
  course-specific login gate and satisfying the release reviewer's page security check.

## [v0.9.1] - 2026-09-27

### Added
- LMS Labs credentials: the Site ID and API key are read from AI Grader Central Config
  (`local_aiconfig`) when it holds a complete pair. A complete standalone pair in this plugin's
  settings is used only when Central Config is missing or incomplete. Pairs are never mixed, central
  values are never copied into this plugin, and the API key is never sent to the browser.
  Resolver: `\mod_vocabmastery\local\credentials::resolve()`.
- Site settings page showing which credential source is in use (the key itself is never displayed).
- Upgrade step: light colour scheme is the default for new activities.
- Import vocabulary › Excel / CSV / paste: step-by-step instructions, a column reference, a
  downloadable sample Excel file (with a "How to use" sheet) and sample CSV, and a ChatGPT prompt
  that turns a word list into an import table (copy button and .txt download). Downloads need the
  `mod/vocabmastery:managecontent` capability.

### Changed
- Maturity set to stable (`MATURITY_STABLE`).
- Light, premium default look for all student screens (line icons, progress rings, cards); dark
  scheme remains available per activity.
- Trainer and item editor rewritten as ES6 AMD modules built with Moodle's grunt (with source maps);
  all trainer strings are loaded through `core/str`, all trainer markup through Mustache templates.
- Teacher pages (manage, item editor, import preview/mapping, reports) render through Mustache
  templates.
- All SQL uses named placeholders; no `PARAM_RAW`; import staging and view logging moved into
  namespaced classes; every CSS selector is scoped to the plugin; animations only run when the user
  has not asked for reduced motion.
- A failed mastery check no longer offers "Where next?" routes.
- Reports hide empty abandoned sessions; CSV export column headers are translated.
- `$plugin->supported` narrowed to `[404, 502]`: Moodle 5.3 is not released, so support for it
  cannot be claimed or tested yet.
- Lucide icon licence: full upstream ISC notice (and MIT notice for Feather-derived icons) shipped
  as `LICENSE-LUCIDE.txt`; copyrights declared in `thirdpartylibs.xml`.
- PHPUnit tests added (25 tests: credentials resolver, attempt capability on all three web services,
  answer checking, item mastery, difficult words, topic passing, secure mastery check, table and
  Excel import) with a data generator.

### Security
- Practice, Blitz, Streak, Learn and mastery-check web services (`start_session`, `record_response`,
  `finish_session`) now require `mod/vocabmastery:attempt` (content managers may still preview).
  Previously `mod/vocabmastery:view` was enough. Users who can view but not attempt see a notice
  instead of the training buttons.

### Fixed
- Excel (.xlsx/.xls/.ods) upload failed on Moodle 4.4 and 4.5 with `Class
  "PhpOffice\PhpSpreadsheet\IOFactory" not found`; PhpSpreadsheet is now loaded through Moodle's
  Excel library on every supported version.

## [v0.9.0] - 2026-09-27

### Added
- Initial MVP: levels → topics → sets → items content model; Word (.docx), Excel/CSV and paste
  importers with preview and validation; bulk item editor; Learn, Practice (adaptive), Blitz,
  Streak, Difficult words and Mastery check modes; item mastery and topic mastery rules; dual,
  linear, level and open progression; teacher reports (overview, heatmap, difficult vocabulary,
  student detail); gradebook, custom completion, events, privacy provider, backup/restore, course
  reset and web services.
