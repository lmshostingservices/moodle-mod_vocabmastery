# Changelog

All notable changes to mod_vocabmastery are documented here.

## [v0.9.4] - 2026-09-28

### Fixed
- A low balance warns instead of blocking Marketplace/direct-purchase restoration at zero credits.
- A purchase is offered only for a ready ZIP with a positive integer credit price and exact `credit-unlock` acquisition mode.
- An uncertain unlock stays pending from before the outbound call until a strictly boolean free check resolves it; malformed verification and ambiguous success replies cannot be mistaken for definitive results.
- Existing learning and local practice do not require a commercial entitlement or a network connection.

## [v0.9.3] - 2026-09-28

### Added
- LMS Labs activation page for administrators (Site administration › Plugins › Activity modules ›
  Vocabulary Mastery › LMS Labs activation; also linked from the plugin settings). It shows:
  - which credential source is in use (Central Config or a complete standalone pair), with settings links;
  - access state: Not checked / Locked / Unlocked / Unable to verify;
  - credit balance, including unlimited;
  - the live one-time activation price and release, from `plugins["mod_vocabmastery"]` in
    `GET /api/plugin-versions` (`creditsRequired`, falling back to `credits`; `sha256`; `status`
    must be `ready`; `zipExists` must not be false).
- "Check access" calls `POST /api/plugin-unlock/verify`. It is free and never spends credits.
- "Unlock…" first runs a free access check and loads the live price and release SHA-256. It then
  shows a confirmation screen with a required tick box. Only after confirmation is
  `POST /api/plugin-unlock` sent, with the live `sha256` as `releaseSha256` and the live price as
  `expectedCredits`. Cancel sends nothing.
- Unlock responses are read from `success`, `alreadyUnlocked`, `creditsConsumed`,
  `entitlementSource`, `remainingCredits` and `message`:
  - Already unlocked: `creditsConsumed` refers to the original unlock and is not shown as a new charge.
  - Purchase restoration: `creditsConsumed` is 0 and `entitlementSource` is `marketplace` or `purchase`.
  - A new unlock reports `creditsConsumed` as the charge. The balance is updated from
    `remainingCredits`.
- Unlock errors are handled without automatic retry, and the server's message text is shown
  (escaped):
  - 402: insufficient credits.
  - 409 `stale_credit_price`: review the new price and confirm again.
  - 409 `marketplace_entitlement_ambiguous`: contact LMS Labs.
  - Any other 409: a conflict. It is not treated as stale.
  - 401/403: credentials rejected.
  - Other non-success responses: rejected.
  - Timeout, 5xx or an unreadable reply: uncertain. Another unlock is then blocked until
    "Check access" gives a definite answer.
- Stale price or release is also caught before sending: the live metadata is re-read, and nothing
  is sent if either changed since confirmation.
- 22 unit tests for the activation service, using a scripted LMS Labs that returns the real
  manifest and unlock response shapes. No network is used.

### Security
- All LMS Labs requests are server-side, through Moodle's curl wrapper, to the fixed host
  https://lms-labs.com, with bounded timeouts and no redirects. The API key is sent only in request
  bodies. It is never rendered, stored in activation state, logged or sent to the browser.
- The page requires `moodle/site:config`. Every action is POST-only with sesskey.
- `index.php` (activity list for a course) uses `require_login($course)` instead of
  `require_course_login($course)`. This carries over the course-index security adjustment recorded
  for the promoted 0.9.2 package. It also lists only activities the user can view
  (`mod/vocabmastery:view`).

### Not changed
- Activation status is informational. No learning feature is gated by it, no runtime licence
  check was added, and learners are unaffected whether or not the site is activated.

## [v0.9.2] - 2026-09-28

### Changed
- Release bump to 0.9.2 (version 2026092801) so the release pipeline receives a new, unused
  version. No code, database or language changes since the final 0.9.1 package
  (SHA-256 85f78868dac8066aa2ba5b104e6d585c8ce8c2b3a84b6d528263de254ee829b4). No upgrade step is
  needed; the highest savepoint remains 2026092800.

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
