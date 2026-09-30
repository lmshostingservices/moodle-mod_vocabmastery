# Changelog

All notable changes to mod_vocabmastery are documented here.

## [v1.0.1] - 2026-09-30

### Added
- Help (?) icons on every setting: activity settings (including the learner colour scheme,
  training modes, "latest answers in a row" and the completion rules), set, topic and level
  forms, and the import screens.

### Changed
- All help texts rewritten in plain language with examples and the matching option names.

## [v1.0.0] - 2026-09-30

First production release. Same code as 0.9.6, which fixed every finding of the 0.9.5
production-readiness audit. No database or behaviour changes.

### Release status
- Supports Moodle 4.4 to 5.2; tested on PHP 8.3 and 8.4.
- Automated coverage: 82 PHPUnit tests and Behat scenarios for learner practice (with axe
  accessibility checks) and separate-groups reporting.
- Activation with LMS Labs stays informational: no licence gate, no paid calls.

## [v0.9.6] - 2026-09-29

Fixes everything found in the 0.9.5 production-readiness audit. No database schema changes.

### Fixed – grades and progress
- A mastery check scores each word once, even if the same answer is sent twice at the same time.
  Answers to one session are now saved one at a time (per-session lock).
- Repeating the same right answer inside one session no longer masters a word; at most 10
  answers per word are stored per session.
- Only one mastery check per topic can be open; starting a new one scores the old one as it stands.
- During a mastery check the server no longer returns the word's learning state (it hinted at
  whether an answer was right).
- A topic without core words no longer locks the topics after it, blocks "pass all topics"
  completion or holds the grade below 100%.
- A practice pass is saved as soon as the last word is mastered, not only when the session is finished.
- New scheduled task finishes sessions left open for more than a day, so progress and grades are saved.
- Editing, deleting or moving words, and changing a set, now recalculates stored progress, grades
  and completion in the background (ad-hoc task), so the teacher report matches what learners see.
- Deleting a topic or set also deletes its sessions; finishing a session whose topic was deleted
  no longer recreates progress for it.
- Two simultaneous first answers to a word no longer fail with a database error.
- Teachers previewing the trainer no longer get grades or completion.

### Fixed – import
- "Replace the whole set" updates words that are still in the file in place, so learners keep their
  progress; only words missing from the file are removed. The import summary shows how many.
- CSV/Excel imports honour the set columns "gated", "answer mode" (fixed/pool) and "instruction".
  Sets with a few unique answers (e.g. verb meanings) are no longer turned into fixed buttons.
- Word imports refuse documents that unpack to more than 20 MB; spreadsheets with more than
  10,000 rows or 30 columns are refused before loading, and formulas are not evaluated.
- The client-specific footer filter in the Word importer is replaced by a site setting
  ("Lines to ignore in Word imports").

### Fixed – teacher report and privacy
- The report respects separate groups: teachers without "Access all groups" only see and export
  learners in their own groups.
- The results download uses the site's "Show user identity" fields instead of always including email.
- The heatmap and its legend show their colours again.
- Word reports from learners are now included in backups and restored.
- Privacy metadata lists every stored field.

### Fixed – learner trainer and accessibility
- Keyboard: Enter and Space no longer hijack focused buttons; focus moves to the next question after
  an answer, a hint or the report form; Escape closes the report form.
- Double-clicking a mode no longer starts two sessions; timers stop when a session ends.
- If saving the end of a session fails, the learner sees an error with "Try again".
- Leaving Learn mode or a mastery check closes the session.
- A failed word report keeps the comment and can be sent again.
- Focus outline, small grey text and passed-topic cells meet WCAG contrast.
- Screen readers hear "Correct"/"Wrong" in the results grid; feedback is announced once; the set
  picker supports arrow keys; the "Report a problem" link is a 24 px target.
- Filtering the word list unticks hidden rows so they cannot be bulk-deleted by accident.
- Wording: correct singular/plural, en dashes, consistent "learner" and "…".

## [v0.9.5] - 2026-09-29

### Changed
- New colour scheme, "Harbour & Coral", replacing the stock indigo/violet palette and the dark
  purple results header:
  - navy brand (#23408e) with navy-tinted neutrals;
  - coral highlight (#f26b4f) for Blitz, Streak and personal bests;
  - honey for "needs work";
  - green and red only for right and wrong.

  All plugin colours now come from one set of tokens, with a matching dark scheme. There are no
  code, database or language changes.

## [v0.9.4] - 2026-09-29

### Added
- New results screen after every session, including the mastery check:
  - score ring coloured by result, with a headline and encouragement line;
  - time taken, personal best, and comparison with the last session of the same kind
    ("Last time 45% → now 60% (+15)", with a "Big jump!" tag at +20 or more);
  - an answer grid (one tile per word, right or wrong);
  - "You're strong in" and "Let's work on" by set, when a session covers several sets;
  - one recommended next step (practise the missed words, take the mastery check, go again or
    start practising);
  - "Review answers", listing every word with the correct form, the learner's answer and the
    translation.
- Learners can report a problem with a word ("Report a problem": wrong answer, spelling,
  translation or other, with an optional comment). This is available after answering in practice
  and in the answer review. Teachers see open reports on the word table of the set and a
  "N reported" badge on the manage page, and mark each report as fixed. Stored in the new table
  `vocabmastery_flag` and covered by the Privacy API, instance deletion, item deletion and course
  reset. Reports are not included in backups.
- Elapsed-time clock in the session header (Blitz keeps its countdown).
- Web service `mod_vocabmastery_report_item` (requires `mod/vocabmastery:attempt`).

### Changed
- Practice and difficult-word sessions are scored on words right first time, which matches the
  answer grid. Earlier sessions keep their old score (right answers out of all answers given).
- A missed word comes back at most twice in one practice session, so a session always ends. Words
  still missed are kept for next time.

### Fixed
- Word forms in feedback and reviews: only short one-word answers from fixed buttons (articles)
  are written before the word ("die Frau"). Longer answers now read "wohnen → to live" instead of
  "to live wohnen".
- A 0% progress ring no longer shows a stray dot.

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
