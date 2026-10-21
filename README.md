# Vocabulary Mastery (mod_vocabmastery)

A Moodle activity for vocabulary that has to be memorised rather than derived from rules: noun genders/articles, verb + preposition, irregular forms, translations, terminology.

Nothing language-specific is hard-coded. German der/die/das, A1/A2/B1 and lesson names are **content**, not code.

- Component: `mod_vocabmastery`, version 1.1.0 (stable)
- Target: Moodle 4.4 – 5.2 (`requires` 2024042200, `supported` [404, 502]). Runtime-tested on Moodle 5.0.10 / PHP 8.4 /
  PostgreSQL 16 (full), Moodle 4.4.12 / PHP 8.3 (upgrade, PHPUnit) and Moodle 5.2.3 (upgrade, PHPUnit, Behat); the other versions are covered by the CI matrix in `.github/workflows/ci.yml` (repository only, not in the ZIP).
- Moodle code checker (moodle-cs): 0 errors, 0 warnings. PHPDoc checker (local_moodlecheck): 0 errors. Grunt (ESLint,
  stylelint, AMD build): clean.

## Install

1. Unzip into `mod/vocabmastery` (Moodle 5.1+: `public/mod/vocabmastery`).
2. Visit Site administration › Notifications.
3. Add a "Vocabulary Mastery" activity to a course, then use **Import vocabulary**.

## Content model

```
Activity → Levels → Topics → Sets → Items
            (A1)   (Lektion 1) (Nomen + Artikel) (der · Vater · father)
```

- **Item**: prompt, correct value, alternatives (`a|b`), translation, plural/extra form, hint, explanation, core/optional, JSON metadata (source, verb type…).
- **Set**: how items are asked. You can use a *fixed* button list (der / die / das), or the correct answer plus *distractors* taken from other items (prepositions, meanings). Typed answers are also supported. A **gated** set counts towards topic mastery.
- **Topic path position**: aligns topics across levels (lesson 1 in A1, A2, B1) for the two-way progression.
- Duplicate key = set + prompt + answer, so the same word can appear in several topics.

## Importing

**Word (.docx), many files at once**: the parser recognises the client's *Wortschatzüberblick* layout. It reads the level line, "Lektion N – Title", core noun tables, optional noun tables, regular/separable verb tables, verb + preposition tables and the "Hinweise" notes, which are stored as teacher notes. Any other Word table with a header row is read generically. The import flow is Upload → Parse → Preview (tick sections, edit level names) → duplicate policy (skip / merge / import anyway / replace set) → Import.

Verified on the 34 supplied lesson files (A1 1–12, A2 1–12, B1 1–9, 12). Result: 3 levels, 34 topics, 125 sets, 1,539 items. The parser also:

- A2 tables list the preposition inside the verb column ("sich ärgern über" + "+ Akk."). These are normalised to prompt "sich ärgern", answer "über + Akk.", the same shape as B1.
- Reflexive verbs without a fixed preposition ("sich kämmen –") are moved into a separate "…: meaning" set instead of being lost.
- Duplicate rows (e.g. "die Gesellschaft" twice in B1 L1) and rows with no answer are skipped and listed in the preview.

**Excel / CSV / paste**: paste from Word or Excel, or upload .csv/.xlsx/.ods. Columns are auto-detected (header names, or the column holding a few short repeated values is taken as the answer column). The teacher can change the mapping and see a validated preview before importing. Optional `level`, `topic` and `set` columns let one sheet build a whole course. **Export CSV** on the manage page produces a file in this same format, so content can go out and back in.

The Excel / CSV / paste tab has step-by-step instructions, a column reference, **Download sample Excel file** / **sample CSV** (columns `level, topic, set, answer, prompt, translation, plural, alternatives, hint, core`; optional set columns `gated`, `answer mode` (fixed/pool) and `instruction`), and a **ChatGPT prompt** with a Copy button and a .txt download. The prompt tells ChatGPT to convert a pasted or attached word list into exactly that table (articles for nouns, preposition + case for verb + preposition, English meaning for verbs, no invented words, umlauts kept, core 1/0) and to list anything uncertain under "Check:". Nothing is sent to ChatGPT by Moodle; the teacher pastes the prompt themselves and checks the result in the import preview.

**Manual**: the manage page has levels, topics and sets, with reordering. Each set has a bulk item table: inline edits, add rows, search, and select many rows to delete, duplicate, move to another set, or mark core/optional.

## Student experience

- **Dashboard**: overall progress, a "Continue learning" card, difficult words with a one-tap retrain button, a pathway grid (levels × topics), and level/topic cards showing mastered, in-progress and locked states.
- **Topic screen**: mastery stats (mastered / learning / difficult / new), a set picker, modes, the mastery check, and the word list with each word's state and accuracy.
- **Learn**: flashcards (Space reveals; 1 = I know this, 2 = need more practice). This never affects mastery.
- **Practice**: items are ordered by weight (new ×2, missed once ×3, 2+ misses ×5, previously difficult ×4, mastered ×0.5). A missed word comes back 3–5 cards later and must be answered correctly before the session ends. Every 5–8 answers a difficult word from another unlocked topic with the same kind of answers is mixed in.
- **Blitz** (timed, default 60 s) and **Streak** (until the first mistake): these track personal bests and by default do **not** count towards mastery.
- **Difficult words**: for a topic, or across the whole activity.
- **Mastery check**: no feedback and no hints until the end. The correct answers are never sent to the browser. After a failed check, the missed items are always included in the next one, and the result screen offers "Practise these N". Optionally (setting "After the mastery check" = hide), learners see only their score and per-set totals, not which words were wrong or the correct answers.
- **Results**: every session ends with a results screen. It shows a score ring, time taken, comparison with last time, a right/wrong tile per word, strengths and weak sets, one recommended next step, and a full answer review.
- **Report a problem**: learners can flag a word (wrong answer, spelling, translation or other). Teachers see open reports on the set's word table and mark them as fixed.
- **Keyboard**: 1–9 answer, Enter continue, Space reveal, Esc exit. The layout is mobile-first, with ARIA live feedback, visible focus, correct/wrong shown by more than colour, and reduced-motion support. Clean light design by default in its own "Harbour & Coral" palette (navy brand, coral highlight, green/red only for right and wrong), with a matching dark scheme per activity.

## Mastery and progression rules (all configurable)

- **Item mastered**: at least 3 correct answers in total AND the last 2 correct (defaults). A later mistake removes mastery.
- **Difficult**: 2+ wrong answers, or under 60% accuracy after 3 attempts, until the item is mastered again.
- **Topic mastery**: % of the topic's gated core items that are mastered. Optional items never block progress.
- **Topic passed**: mastery check ≥ threshold (default), OR practice mastery ≥ threshold, OR either. Default threshold 90%.
- **Progression**: *dual pathway* (default: after passing, go right to the next topic in the same level or down to the same topic in the next level), *linear*, *level pathway*, or *open*. Optional backward progression ("Also open earlier topics in the same level"): once a topic is open, the earlier topics of its level open too. Teachers and managers can preview locked topics.

## Teacher reports

These are available to users with `viewallprogress`, and respect group mode.

- **Overview**: progress, topics mastered, accuracy, answers, difficult count, last practice. Downloadable in any Moodle dataformat.
- **Heatmap**: students × topics (mastered / in progress / struggling / not started).
- **Difficult vocabulary**: the class's most-missed words, with wrong rate and how many students have each as a difficult word.
- **Student detail**: per-level and per-topic mastery, best check, accuracy, difficult items, recent sessions, and reset progress.

## LMS Labs credentials

The Site ID and API key issued by LMS Labs are read from **AI Grader Central Config** (`local_aiconfig`,
Site administration › Plugins › Local plugins) so they only need entering once per site.

Resolution (`\mod_vocabmastery\local\credentials::resolve()`):

1. The complete pair from Central Config (`\local_aiconfig\config::get_credentials()`), when that plugin is installed and
   both values are set.
2. Otherwise a complete standalone pair from this plugin's settings (Site administration › Plugins › Activity modules ›
   Vocabulary Mastery).
3. Otherwise the `lmslabscredentialsmissing` error is thrown; nothing calls LMS Labs.

A central Site ID is never combined with a local API key (or the reverse), central values are never copied into this
plugin's settings, and the API key is never sent to JavaScript, templates, AJAX responses or logs. The settings page shows
which source is in use and the Site ID, never the key.

### Activation (one-time unlock)

Administrators can open **Site administration › Plugins › Activity modules › Vocabulary Mastery › LMS Labs activation**. The page shows:

- the credential source, with links to its settings;
- access (Not checked / Locked / Unlocked / Unable to verify);
- the credit balance, including unlimited;
- the live one-time price.

**Check access** is free (`POST /api/plugin-unlock/verify`). **Unlock…** runs a free check, loads the live price and release from `GET /api/plugin-versions`, and asks for explicit confirmation. Only then does it send `POST /api/plugin-unlock` with that release SHA-256 and price. After an uncertain result, another unlock is blocked until an access check gives a definite answer.

**Licensing:** activation status is informational only. The plugin's locks are learning-progression locks (topics unlock as students pass them), not commercial entitlement. No feature is disabled on an unactivated site, and no runtime licence check or connectivity requirement exists. Adding one needs separate owner approval.

There are no metered or AI charges: no AI generation, speech or AI-provider calls.

## Moodle integration

- **Capabilities**: addinstance, view, attempt, viewownprogress, viewallprogress, managecontent, resetprogress, exportresults. Training web services require `attempt` (or `managecontent`, for teacher preview); `view` alone only shows the pages. There is no student picker and no teacher password: identity and permissions come from Moodle.
- **Gradebook**: % of topics passed, or the average of best mastery-check scores. Point and scale grades are both supported.
- **Completion**: view; pass N topics; pass all topics; reach X% overall progress.
- **Events**: module viewed, instance list viewed, topic passed, session completed, content imported.
- **Background tasks**: a scheduled task finishes sessions left open for more than a day; an ad-hoc task recalculates progress, grades and completion after teachers change words.
- **Other**: backup/restore (content plus optional user data, with ids remapped), Privacy API (metadata, export, delete), course reset, and web services (`mod_vocabmastery_start_session`, `_record_response`, `_finish_session`; AJAX- and mobile-service enabled).
- **Server-side checking**: every answer is re-checked and stored on the server (`vocabmastery_response`), including response time, which gives the data needed for spaced repetition later.

## Not in 1.0 (planned)

- Matching mode (value-based so identical answers are interchangeable; the engine already compares by value).
- Spaced review (due intervals 1/3/7/14/30 days).
- Audio per item and optional TTS.
- A print view and per-set progress in teacher reports.

## Tests

`vendor/bin/phpunit -c mod/vocabmastery` (after `admin/tool/phpunit/cli/util.php --buildcomponentconfigs`): 82 tests. Behat features in `tests/behat` cover learner practice (with axe
accessibility checks) and separate-groups reporting. The
Central Config cases run only where `local_aiconfig` is installed; the "not installed" case runs only where it is not.

## Third-party

Icon paths from Lucide (ISC; Feather-derived icons MIT). Full notice: `LICENSE-LUCIDE.txt`.

## Database tables

`vocabmastery`, `_level`, `_topic`, `_set`, `_item`, `_attempt`, `_response`, `_itemstate`, `_topicstate`, `_flag` (learners' word reports).
