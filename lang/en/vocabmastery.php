<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * English strings for mod_vocabmastery.
 *
 * @package    mod_vocabmastery
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['accuracy'] = 'Accuracy';
$string['act_access'] = 'Access';
$string['act_balance'] = 'Credit balance';
$string['act_balanceunknown'] = 'Not known yet. Use Check access.';
$string['act_check'] = 'Check access';
$string['act_confirm_button'] = 'Confirm and unlock';
$string['act_confirm_checkbox'] = 'I confirm spending {$a} LMS Labs credits to activate Vocabulary Mastery on this site.';
$string['act_confirm_free'] = 'LMS Labs lists this activation as free (0 credits).';
$string['act_confirm_spend'] = 'This will spend {$a} LMS Labs credits from this site\'s balance. It cannot be undone from Moodle.';
$string['act_confirm_title'] = 'Confirm activation';
$string['act_credentials'] = 'Credentials';
$string['act_entitlementsource'] = 'Entitlement source';
$string['act_intro'] = 'Check whether this site has activated Vocabulary Mastery with LMS Labs, and activate it with a one-time credit payment. Checking access is free. Nothing is ever purchased without your confirmation.';
$string['act_lastchecked'] = 'last checked {$a}';
$string['act_licensingnote'] = 'Activation status is informational. It does not currently lock or unlock any feature: teachers and learners can use the activity whether or not this site is activated.';
$string['act_localsettings'] = 'Plugin settings (standalone Site ID and API key)';
$string['act_pendingnotice'] = 'The last unlock request did not return a definite answer, so credits may or may not have been spent. Use Check access before trying again.';
$string['act_price'] = 'One-time activation price';
$string['act_pricecredits'] = '{$a} credits';
$string['act_pricefree'] = 'Free (0 credits)';
$string['act_release'] = 'release {$a}…';
$string['act_releasesha'] = 'Release (SHA-256)';
$string['act_result_alreadyunlocked'] = 'This site is already activated. No credits were charged.';
$string['act_result_ambiguous'] = 'LMS Labs could not match this site to a single existing purchase, so the unlock was not completed. Contact LMS Labs support before trying again.';
$string['act_result_autherror'] = 'LMS Labs did not accept this site\'s Site ID and API key. Check the credentials.';
$string['act_result_cancelled'] = 'Cancelled. Nothing was sent to LMS Labs and no credits were spent.';
$string['act_result_checkfirst'] = 'The last unlock request is unconfirmed. Use Check access first; no new request was sent.';
$string['act_result_conflict'] = 'LMS Labs refused the unlock request (for example, the release is not currently available). Use Check access before trying again.';
$string['act_result_insufficient'] = 'Not enough credits: {$a->required} are needed. Nothing was charged.';
$string['act_result_locked'] = 'Access checked: this site is not activated.';
$string['act_result_manifestunavailable'] = 'The live price could not be loaded from LMS Labs.';
$string['act_result_nocredentials'] = 'Configure this site\'s LMS Labs Site ID and API key in Central Config, or provide both in this plugin\'s settings.';
$string['act_result_noprice'] = 'The LMS Labs release metadata has no valid price for this plugin.';
$string['act_result_nosha'] = 'The LMS Labs release metadata has no valid release checksum for this plugin.';
$string['act_result_notavailable'] = 'LMS Labs lists this plugin as not available for activation.';
$string['act_result_notconfirmed'] = 'Tick the confirmation box to continue. Nothing was charged.';
$string['act_result_notinmanifest'] = 'This plugin is not listed in the LMS Labs release metadata.';
$string['act_result_rejected'] = 'LMS Labs did not accept the unlock request. Use Check access to see the current status.';
$string['act_result_restored'] = 'Activation restored from an existing purchase. No credits were charged.';
$string['act_result_stale'] = 'The price or release changed after you confirmed. Nothing was charged. Review the new price ({$a->required} credits) and confirm again.';
$string['act_result_uncertain'] = 'LMS Labs did not return a definite answer. Credits may or may not have been spent. Use Check access before trying again.';
$string['act_result_unlocked'] = 'Activated. {$a->charged} credits were charged.';
$string['act_result_unlockednocharge'] = 'Activated. Use Check access to see the updated balance.';
$string['act_result_unverifiable'] = 'LMS Labs could not be reached or its answer could not be read. Access status is unknown.';
$string['act_servermessage'] = 'LMS Labs said: "{$a}"';
$string['act_state_locked'] = 'Locked';
$string['act_state_notchecked'] = 'Not checked';
$string['act_state_unlocked'] = 'Unlocked';
$string['act_state_unverified'] = 'Unable to verify';
$string['act_status'] = 'Activation status';
$string['act_title'] = 'LMS Labs activation';
$string['act_unlimited'] = 'Unlimited';
$string['act_unlock'] = 'Unlock…';
$string['act_unlockedat'] = 'Unlocked on';
$string['act_unlockeddone'] = 'This site is activated. No further purchase is needed.';
$string['addlevel'] = 'Add level';
$string['addlevelfirst'] = 'Add a level first.';
$string['addrows'] = 'Add 5 rows';
$string['addset'] = 'Add set';
$string['addtopic'] = 'Add topic';
$string['addtopicfirst'] = 'Add a topic first.';
$string['allmastered'] = 'Every topic mastered. Keep them fresh with Blitz and Difficult words.';
$string['analyse'] = 'Analyse';
$string['answermode'] = 'Answer buttons';
$string['answermode_fixed'] = 'Fixed list';
$string['answermode_help'] = 'Where the answer buttons come from.

* **Fixed list**: the same buttons for every word, in the order you give, e.g. der / die / das. Best for articles or cases.
* **Distractors from other items**: the correct answer plus wrong answers taken from other words in the set, e.g. prepositions or meanings.';
$string['answermode_pool'] = 'Distractors from other items';
$string['answers'] = 'Answers';
$string['attemptfinished'] = 'This session has already finished.';
$string['besttest'] = 'best check {$a}%';
$string['besttestshort'] = 'Best check';
$string['blitzcountsmastery'] = 'Blitz and Streak answers count towards mastery';
$string['blitzcountsmastery_help'] = 'When ticked, answers in Blitz and Streak count towards word mastery like Practice answers do.

Off by default, so that fast guessing under time pressure cannot unlock topics.';
$string['blitzduration'] = 'Blitz duration (seconds)';
$string['blitzduration_help'] = 'Length of a Blitz round in seconds, between 10 and 600. Default 60.';
$string['chatgptheading'] = 'ChatGPT prompt: turn your word lists into an import table';
$string['chatgptintro'] = 'Copy this prompt into ChatGPT (or another AI assistant), replace the three placeholders at the bottom with your level, lesson and word list (paste text or attach the Word/PDF file), and send it. Download the file it produces, or copy the table it prints, then upload or paste it above. Always check the result before importing: AI assistants can make mistakes.';
$string['chatgptprivacy'] = 'Nothing is sent to ChatGPT by this page. Only paste material you are allowed to share with an external service.';
$string['chatgptprompt'] = 'You are helping a language teacher prepare vocabulary for import into a Moodle vocabulary trainer.

TASK
Convert the learning material at the bottom of this message into ONE table with exactly these 10 columns, in this order, with this header row:

level | topic | set | answer | prompt | translation | plural | alternatives | hint | core

COLUMN RULES
- level: the course level exactly as given below (for example A1). Same value on every row.
- topic: the lesson or unit name exactly as given below, including its number (for example "Lektion 1 – Hallo Welt!"). The number is used to line up the same lesson across levels.
- set: the group of words that are practised together. Every row in one set must have the SAME KIND of answer. Use these set names:
  - "Nomen + Artikel" for nouns (answer = der, die or das)
  - "Verb + Präposition" for verbs with a fixed preposition (answer = the preposition + case)
  - "Verben" for verbs practised by meaning (answer = the English meaning)
  - For any other kind of item, choose a short descriptive set name.
- answer: what the learner must choose or type.
  - Nouns: only the article, in lower case: der, die or das.
  - Verb + preposition: preposition and case, written as "über + Akk." or "mit + Dat.".
  - Verbs: the English meaning, starting with "to", for example "to live".
- prompt: the word the learner sees, WITHOUT the article (for example "Vater", not "der Vater"). For reflexive verbs keep "sich" (for example "sich freuen").
- translation: the English meaning. Leave empty for the "Verben" set, because the meaning is already the answer.
- plural: the plural form without article (for example "Väter"). Leave empty if there is none, or if the material does not give one. Do not guess plurals.
- alternatives: other answers that must also be accepted, separated by | (for example "über + Akkusativ|über + Akk"). Usually empty.
- hint: a short learning hint only if the material gives one (for example a note that compound nouns take the article of the last word). Otherwise empty.
- core: 1 for words that learners must master, 0 for optional or extra words (for example words listed under "optional" or "zusätzlich"). If the material does not say, use 1.

STRICT RULES
1. Use ONLY words that appear in the material. Do not add, invent, correct or "improve" words.
2. Keep German spelling exactly, including ä, ö, ü and ß. Never write ae, oe, ue or ss instead.
3. One word per row. No duplicate rows (same set + prompt + answer).
4. No empty answer or prompt cells. If you cannot determine an answer, leave the word out and list it under "Check:".
5. Do not merge cells, add colours, totals, notes rows or extra columns.

OUTPUT
A) An Excel file (.xlsx) named vocabulary_import.xlsx with the table on the first sheet, header in row 1, if you can create files.
B) The same table as a tab-separated code block (header row first), so it can be copied and pasted directly.
C) After the table, a short list headed "Check:" with any word you left out or were unsure about, and why. If there is nothing, write "Check: none".

MY MATERIAL
Level: [LEVEL, for example A1]
Topic: [LESSON NUMBER AND TITLE, for example Lektion 3 – Essen und Trinken]
Word list: [PASTE THE WORD LIST HERE, OR ATTACH THE WORD/PDF FILE]';
$string['chooseset'] = 'Choose what to train';
$string['col_alternatives_desc'] = 'Other accepted answers, separated by | (for example über + Akkusativ|über + Akk). Optional.';
$string['col_answer_desc'] = 'What the learner must choose or type: an article (der/die/das), a preposition + case, a meaning, a form… Each set should hold one kind of answer.';
$string['col_core_desc'] = '1 = core word that counts towards topic mastery, 0 = optional extra word. Also accepts yes/no. Empty = 1.';
$string['col_hint_desc'] = 'A short hint learners can reveal during practice. Optional.';
$string['col_level_desc'] = 'Level name, for example A1. Created if it does not exist. If empty, the level chosen on the next screen is used.';
$string['col_plural_desc'] = 'Plural or other extra form shown after answering. Optional.';
$string['col_prompt_desc'] = 'The word or phrase shown to the learner, without the answer (for example Vater, not der Vater).';
$string['col_set_desc'] = 'Set name, for example Nomen + Artikel. A set whose answers use 6 or fewer different values (der/die/das) gets fixed answer buttons; other sets get multiple choice with distractors from the same set. You can change this later under Manage content.';
$string['col_topic_desc'] = 'Topic or lesson name, for example Lektion 1 – Hallo Welt!. The number in the name lines up the same lesson across levels. If empty, the topic chosen on the next screen is used.';
$string['col_translation_desc'] = 'Meaning in the learners\' language, shown after answering. Optional.';
$string['colourscheme'] = 'Learner colour scheme';
$string['colourscheme_dark'] = 'Dark';
$string['colourscheme_help'] = 'Light or dark design for the learner\'s screens. Teacher pages always follow the site theme.';
$string['colourscheme_light'] = 'Light';
$string['column'] = 'Column';
$string['columnn'] = 'Column {$a}';
$string['complete'] = 'Complete';
$string['completionalltopics'] = 'Learner must pass all topics';
$string['completionalltopics_help'] = 'The activity is complete when the learner has passed every topic that has core words.';
$string['completiondetail:alltopics'] = 'Pass all topics';
$string['completiondetail:progress'] = 'Reach {$a}% overall progress';
$string['completiondetail:topics'] = 'Pass topics: {$a}';
$string['completionprogress'] = 'Learner must reach this overall progress (%):';
$string['completionprogress_help'] = 'The activity is complete when the learner\'s overall progress reaches this percentage. Passed topics count as 100%; other topics count with their current mastery.';
$string['completiontopics'] = 'Learner must pass this many topics:';
$string['completiontopics_help'] = 'The activity is complete when the learner has passed at least this many topics.';
$string['confirmdelete_level'] = 'Delete level "{$a}" with all its topics, sets, items and learner progress?';
$string['confirmdelete_set'] = 'Delete set "{$a}" with all its items and learner progress on them?';
$string['confirmdelete_topic'] = 'Delete topic "{$a}" with all its sets, items and learner progress?';
$string['confirmdeleteitems'] = 'Delete {$a} selected items and all learner answers for them?';
$string['confirmdeleteitems_one'] = 'Delete 1 selected item and all learner answers for it?';
$string['confirmreset'] = 'Delete all vocabulary progress and answers of {$a}? This cannot be undone.';
$string['contentcounts'] = '{$a->levels} levels · {$a->topics} topics · {$a->sets} sets · {$a->items} items';
$string['continuelearning'] = 'Continue learning';
$string['copyprompt'] = 'Copy prompt';
$string['defaultlevel'] = 'Level 1';
$string['defaultlevelname'] = 'Level';
$string['defaultset'] = 'Vocabulary';
$string['defaultsetname'] = 'Set';
$string['defaulttopic'] = 'Topic 1';
$string['defaulttopicname'] = 'Topic';
$string['deleted'] = '"{$a}" deleted.';
$string['difficultitems'] = 'Difficult items';
$string['difficultwords_help'] = 'Words most often answered wrongly by this class (at least 3 answers). Good candidates for revision in class.';
$string['downloadprompt'] = 'Download ChatGPT prompt (.txt)';
$string['downloadresults'] = 'Download results';
$string['downloadsamplecsv'] = 'Download sample CSV';
$string['downloadsamplexlsx'] = 'Download sample Excel file';
$string['dup_add'] = 'Import anyway';
$string['dup_replace'] = 'Replace the whole set (unchanged words keep learners\' progress; words not in the file are removed)';
$string['dup_skip'] = 'Skip';
$string['dup_update'] = 'Merge (update translation, plural…)';
$string['duplicates'] = 'Items that already exist';
$string['editlevel'] = 'Edit level';
$string['editset'] = 'Edit set';
$string['editsetsettings'] = 'Set settings';
$string['edittopic'] = 'Edit topic';
$string['enableblitz'] = 'Enable Blitz (timed)';
$string['enableblitz_help'] = 'Blitz is a timed round: answer as many words as possible before the clock runs out. Learners see their personal best.';
$string['enablehints'] = 'Show hints in Practice (never in mastery checks)';
$string['enablehints_help'] = 'Shows a Hint button in Practice for words that have a hint. A hinted answer is never counted as correct for mastery. Hints are never shown in the mastery check.';
$string['enablelearn'] = 'Enable Learn mode (flashcards)';
$string['enablelearn_help'] = 'Learn mode shows flip cards: the word, then its answer and translation. Learners mark each card "I know this" or "Need more practice". Learn never changes mastery or grades.';
$string['enablestreak'] = 'Enable Streak mode';
$string['enablestreak_help'] = 'Streak keeps asking words until the first mistake. Learners see their longest streak as a personal best.';
$string['err_blitzduration'] = 'Enter 10 to 600 seconds.';
$string['err_percent'] = 'Enter a number from 1 to 100.';
$string['err_positive'] = 'Enter a positive number.';
$string['err_streakgtcorrect'] = 'Cannot be more than the total correct answers required.';
$string['err_twooptions'] = 'Enter at least two button values.';
$string['errorbusy'] = 'This session is busy saving another answer. Please try again.';
$string['eventcontentimported'] = 'Vocabulary imported';
$string['eventsessioncompleted'] = 'Training session completed';
$string['eventtopicpassed'] = 'Topic passed';
$string['exportcsv'] = 'Export CSV';
$string['field_altanswers'] = 'Also accept (a|b)';
$string['field_answermode'] = 'Answer mode: fixed / pool (set)';
$string['field_correctvalue'] = 'Answer (buttons)';
$string['field_explanation'] = 'Explanation';
$string['field_gated'] = 'Counts for mastery (set)';
$string['field_hint'] = 'Hint';
$string['field_ignore'] = '— ignore —';
$string['field_instruction'] = 'Instruction (set)';
$string['field_iscore'] = 'Core';
$string['field_level'] = 'Level';
$string['field_plural'] = 'Plural / extra form';
$string['field_prompt'] = 'Prompt (word)';
$string['field_set'] = 'Set';
$string['field_source'] = 'Source';
$string['field_topic'] = 'Topic';
$string['field_translation'] = 'Translation';
$string['firstrowheader'] = 'First row is a header';
$string['fixedoptions'] = 'Button values (one per line)';
$string['fixedoptions_help'] = 'One button per line, in the order learners should see them. Keyboard keys 1, 2, 3… follow this order. Example:

der
die
das';
$string['gated'] = 'Counts towards topic mastery';
$string['gated_help'] = 'When ticked, the core words in this set count towards the topic\'s mastery and the mastery check. When not ticked, the set is practice only.

If no set in a topic is ticked, all core words in the topic count.';
$string['gatedshort'] = 'mastery';
$string['grademethod'] = 'Grade calculation';
$string['grademethod_help'] = 'How the grade is worked out. Topics without core words are left out.

* **Percentage of topics passed**: the percentage of topics passed. Example: 6 of 12 topics passed = 50%.
* **Average best mastery-check score**: the average of each topic\'s best mastery check score. Topics never tested count as 0.';
$string['grademethod_test'] = 'Average best mastery-check score';
$string['grademethod_topics'] = 'Percentage of topics passed';
$string['heat_active'] = 'In progress (mastery %)';
$string['heat_none'] = 'Not started';
$string['heat_passed'] = 'Mastered';
$string['heat_struggling'] = 'Struggling (accuracy under 60%)';
$string['importdocxinvalid'] = 'The file could not be read as a Word (.docx) document.';
$string['importdone'] = 'Import finished: {$a->created} items created, {$a->updated} updated, {$a->skipped} skipped, {$a->deleted} removed · {$a->levels} new levels, {$a->topics} new topics, {$a->sets} new sets.';
$string['importexpired'] = 'This import preview has expired. Please upload again.';
$string['importhelp_columns'] = 'Column reference';
$string['importhelp_columnsnote'] = 'Only answer and prompt are required. Column headers can be in English or German (Artikel, Wort, Übersetzung, Lektion, Kategorie…) and in any order; you can also change the column mapping on the next screen. One file can fill several levels, topics and sets.';
$string['importhelp_step1'] = 'Download the sample Excel file and look at how the example rows are filled in (the second sheet explains every column).';
$string['importhelp_step2'] = 'Fill in your own words, one word per row, keeping the header row. Or let ChatGPT build the table for you with the prompt below.';
$string['importhelp_step3'] = 'Upload the .xlsx, .ods or .csv file, or copy the cells in Excel/Word and paste them into the box.';
$string['importhelp_step4'] = 'Click Analyse, check the detected columns and the preview, and fix any rows marked with warnings.';
$string['importhelp_step5'] = 'Choose what to do with duplicates and click Import. You can edit, move or delete words afterwards under Manage content.';
$string['importhelp_title'] = 'How to import a vocabulary table';
$string['importignorelines'] = 'Lines to ignore in Word imports';
$string['importignorelines_desc'] = 'One per line. Lines in imported Word documents that start with any of these (for example a company footer) are not stored as teacher notes. Lines starting with "Quelle:", "Source:", "©" or "Copyright" are always ignored.';
$string['importmapping'] = 'Check the columns';
$string['importnotes'] = 'Keep document notes as teacher notes';
$string['importnow'] = 'Import selected';
$string['importnrows'] = 'Import {$a} rows';
$string['importpreview'] = 'Import preview';
$string['importpreview_help'] = 'Tick what to import. Optional (not scored) items are unticked by default; tick them to add them to the same set as practice-only words. Levels, topics and sets are created as needed; existing ones with the same names are reused.';
$string['importsettings'] = 'Import';
$string['importsummary'] = '{$a->files} files · {$a->sets} sets · {$a->core} core items · {$a->optional} optional items · {$a->skipped} rows skipped · {$a->warnings} warnings';
$string['importtable_help'] = 'Paste straight from Word or Excel (tab separated), or upload .csv / .xlsx. Typical layout: answer · word · translation, e.g. "der · Vater · father". Optional extra columns: plural, level, topic, set, core, hint, alternatives. You choose the column mapping on the next screen.';
$string['importtoolarge'] = 'This file is too large to import. Split it into smaller files.';
$string['importvocab'] = 'Import vocabulary';
$string['importword_help'] = 'Upload one or many .docx files. Structured vocabulary overviews (level line, "Lektion N – Title", tables for nouns + articles, optional nouns, verb groups and verb + preposition) are recognised automatically, including core vs optional items. Any other Word table with a header row also works. Nothing is imported until you confirm the preview.';
$string['injectdifficult'] = 'Mix difficult words into Practice';
$string['injectdifficult_help'] = 'Every 5–8 questions, Practice mixes in a word the learner has found difficult elsewhere, from any unlocked topic with the same kind of answer buttons. This keeps weak words coming back.';
$string['instruction'] = 'Question shown to learners';
$string['instruction_article'] = 'Which article?';
$string['instruction_help'] = 'Short prompt above each card, e.g. "Which article?" or "Past participle?". Optional.';
$string['instruction_meaning'] = 'What does it mean?';
$string['instruction_preposition'] = 'Which preposition / case?';
$string['invalidattempt'] = 'Session not found.';
$string['invaliditem'] = 'That item is not part of this session.';
$string['invalidlevel'] = 'Level not found in this activity.';
$string['invalidset'] = 'Set not found in this activity.';
$string['invalidtopic'] = 'Topic not found in this activity.';
$string['itemcorrectrequired'] = 'Correct answers to master an item';
$string['itemcorrectrequired_help'] = 'How many times in total a word must be answered correctly before it counts as mastered. Default 3.

It works together with the next setting: a word is mastered when both are met. A later wrong answer removes mastery until the learner gets it right again.';
$string['itemsadded'] = '{$a} items added.';
$string['itemsdeleted'] = '{$a} items deleted.';
$string['itemsmoved'] = '{$a} items moved.';
$string['itemstreakrequired'] = 'How many of the latest answers must be correct in a row';
$string['itemstreakrequired_help'] = 'How many of the learner\'s most recent answers to a word must be correct, one after the other, for it to count as mastered. Default 2.

Example with the defaults (3 correct, 2 in a row): right, wrong, right, right = mastered. Right, right, right, wrong = not mastered until the next two answers are right.

This cannot be higher than "Correct answers to master an item".';
$string['itemtype'] = 'Answer type';
$string['itemtype_choice'] = 'Buttons (multiple choice)';
$string['itemtype_help'] = '* **Buttons (multiple choice)**: the learner taps one of several answers.
* **Typed answer**: the learner types it. Capitals and extra spaces are ignored, and alternatives you add to a word are also accepted.';
$string['itemtype_typed'] = 'Typed answer';
$string['js_accuracy'] = 'Accuracy';
$string['js_againbtn'] = 'Again';
$string['js_answeredcorrect'] = '{$a->correct} of {$a->total} correct';
$string['js_appearsagain'] = 'This word will appear again shortly.';
$string['js_backtooverview'] = 'Back to overview';
$string['js_backtotopic'] = 'Back to topic';
$string['js_beststreakn'] = 'Best streak: {$a}';
$string['js_bigjump'] = 'Big jump!';
$string['js_blitzcorrect'] = 'Correct';
$string['js_blitzdone'] = 'Blitz complete';
$string['js_blitzscore'] = '{$a->correct} correct · {$a->pct}% accuracy';
$string['js_blitzstreak'] = 'Streak';
$string['js_blitzwrong'] = 'Wrong';
$string['js_cancel'] = 'Cancel';
$string['js_cardnofm'] = 'Card {$a->n} of {$a->m}';
$string['js_check'] = 'Check';
$string['js_completedin'] = 'Completed in {$a}';
$string['js_continue'] = 'Continue';
$string['js_correctanswer'] = 'Correct:';
$string['js_correctmsg'] = 'Correct.';
$string['js_correctofn'] = '{$a->correct} of {$a->total} right';
$string['js_difficulttag'] = 'difficult word';
$string['js_errorsaving'] = 'Some answers could not be saved (connection problem).';
$string['js_exit'] = 'Exit';
$string['js_finishfailed'] = 'Your results could not be saved. Check your connection and try again.';
$string['js_hint'] = 'Hint';
$string['js_iknowthis'] = 'I know this';
$string['js_key_enter'] = 'Enter';
$string['js_key_space'] = 'Space';
$string['js_lasttime'] = 'Last time {$a->prev}% → now {$a->now}% ({$a->delta})';
$string['js_learndone'] = 'Cards reviewed';
$string['js_loading'] = 'Loading…';
$string['js_masterycheck'] = 'Mastery check';
$string['js_masterynow'] = 'Topic mastery now:';
$string['js_mode_blitz'] = 'Blitz';
$string['js_mode_difficult'] = 'Difficult words';
$string['js_mode_learn'] = 'Learn';
$string['js_mode_practice'] = 'Practice';
$string['js_mode_streak'] = 'Streak';
$string['js_needpractice'] = 'Need more practice';
$string['js_next_again'] = 'Go again';
$string['js_next_again_text'] = 'Another round moves more words to mastered.';
$string['js_next_check'] = 'Take the mastery check';
$string['js_next_check_text'] = 'You are close. Score {$a}% to unlock what comes next.';
$string['js_next_go'] = 'Start';
$string['js_next_mistakes'] = 'Practise the {$a} words you missed';
$string['js_next_mistakes_one'] = 'Practise the word you missed';
$string['js_next_mistakes_text'] = 'They come first, so they stick before your next round.';
$string['js_next_practice'] = 'Start practising';
$string['js_next_practice_text'] = 'Test yourself on the words you just learned.';
$string['js_next_practisetopic'] = 'Practise this topic';
$string['js_next_practisetopic_text'] = 'Work through the words again, then retake the mastery check.';
$string['js_nextstep'] = 'What to try next';
$string['js_noanswer'] = 'No answer';
$string['js_optionaltag'] = 'optional';
$string['js_personalbest'] = 'New personal best!';
$string['js_practisenmistakes'] = 'Practise these {$a}';
$string['js_previousbestn'] = 'Your best: {$a}';
$string['js_questionnofm'] = 'Question {$a->n} of {$a->m}';
$string['js_reason_other'] = 'Something else';
$string['js_reason_translation'] = 'The translation is wrong';
$string['js_reason_typo'] = 'Spelling mistake';
$string['js_reason_wronganswer'] = 'The answer is wrong';
$string['js_report'] = 'Report a problem';
$string['js_report_comment'] = 'Details (optional)';
$string['js_report_failed'] = 'Sorry, the report could not be sent. Please try again.';
$string['js_report_send'] = 'Send';
$string['js_report_thanks'] = 'Thanks! Your teacher will check this word.';
$string['js_report_title'] = 'What is wrong with this word?';
$string['js_res_excellent'] = 'Excellent!';
$string['js_res_great'] = 'Great work.';
$string['js_res_notyet'] = 'Not yet – keep going.';
$string['js_res_passed'] = 'Passed!';
$string['js_res_solid'] = 'Solid work.';
$string['js_res_start'] = 'Good start.';
$string['js_res_tip_excellent'] = 'You know these words well.';
$string['js_res_tip_great'] = 'Nearly there. A little more practice and they will stick.';
$string['js_res_tip_hidden'] = 'Your teacher has hidden the answers for this check. Practise the topic, then try again.';
$string['js_res_tip_solid'] = 'Decent result. Keep practising the words you missed.';
$string['js_res_tip_start'] = 'Every round helps. Start with the words you missed.';
$string['js_retry'] = 'Try again';
$string['js_retrycheck'] = 'Retry mastery check';
$string['js_reveal'] = 'Show answer';
$string['js_reviewanswers'] = 'Review answers ({$a})';
$string['js_reviewedn'] = 'You reviewed {$a} cards.';
$string['js_reviewedn_one'] = 'You reviewed 1 card.';
$string['js_route_backward'] = 'Go back in this level';
$string['js_route_horizontal'] = 'Continue in this level';
$string['js_route_next'] = 'Next available topic';
$string['js_route_vertical'] = 'Go deeper: next level';
$string['js_sameaslast'] = 'Same as last time ({$a}%)';
$string['js_seenexttime'] = 'You will see this word again next time.';
$string['js_sessiondone'] = 'Session complete';
$string['js_startpractice'] = 'Start practice';
$string['js_streakdone'] = 'Streak over';
$string['js_streakmsg'] = '{$a} correct in a row!';
$string['js_streakof'] = 'correct in a row';
$string['js_strongin'] = 'You\'re strong in';
$string['js_testnotyet'] = 'Not yet – keep going.';
$string['js_testpassed'] = 'Passed!';
$string['js_topicmasteredpct'] = 'Topic mastered – {$a}%';
$string['js_typeanswer'] = 'Type your answer';
$string['js_wherenext'] = 'Where next?';
$string['js_workon'] = 'Let\'s work on';
$string['js_wrongchose'] = 'Your answer: {$a}';
$string['js_wrongmsg'] = 'Not quite.';
$string['js_yougot'] = '{$a->correct} / {$a->total} correct';
$string['js_youranswer'] = 'Your answer: {$a}';
$string['js_youranswers'] = 'Your answers';
$string['js_yourmistakes'] = 'Words to review';
$string['lastpractice'] = 'Last practice';
$string['learnfirst'] = 'Please work through Learn mode for this topic first.';
$string['level'] = 'Level';
$string['leveldescription'] = 'Description';
$string['leveldescription_help'] = 'Optional short text shown to learners under the level heading.';
$string['levelname'] = 'Level name';
$string['levelname_help'] = 'Any label, e.g. A1, Beginner or Year 7. Levels appear in the order you arrange them on the manage page.';
$string['lmslabs_apikey'] = 'LMS Labs API key (standalone)';
$string['lmslabs_central_missing'] = 'AI Grader Central Config (local_aiconfig) is not installed on this site.';
$string['lmslabs_heading'] = 'LMS Labs credentials';
$string['lmslabs_heading_desc'] = 'Site ID and API key issued by LMS Labs for this Moodle site. They are read automatically from AI Grader Central Config (local_aiconfig), so they normally only need entering there once. These are LMS Labs credentials, not AI-provider keys.';
$string['lmslabs_local_desc'] = 'Only used when Central Config is not installed or does not hold both values. Enter both the Site ID and the API key here; a single value on its own is ignored.';
$string['lmslabs_open_central'] = 'Open AI Grader Central Config';
$string['lmslabs_siteid'] = 'LMS Labs Site ID (standalone)';
$string['lmslabs_using_central'] = 'Using the credentials from Central Config (Site ID {$a}).';
$string['lmslabs_using_local'] = 'Central Config has no complete credentials, so this plugin\'s standalone credentials are used (Site ID {$a}).';
$string['lmslabscredentialsmissing'] = 'Configure this site\'s LMS Labs Site ID and API key in Central Config, or provide both in this plugin\'s settings.';
$string['managecontent'] = 'Manage vocabulary';
$string['manualentryhint'] = 'Prefer typing? Add sets manually and use the item table editor:';
$string['mappingincomplete'] = 'Map at least one column to "Prompt (word)" and one to "Answer (buttons)".';
$string['markcore'] = 'Mark as core';
$string['markoptional'] = 'Mark as optional';
$string['mastery'] = 'Mastery';
$string['masterycheck'] = 'Mastery check';
$string['masterycheck_desc'] = '{$a->n} questions, no hints or feedback until the end. {$a->threshold}% unlocks the next topics.';
$string['masterysettings'] = 'Mastery';
$string['masterythreshold'] = 'Topic mastery threshold (%)';
$string['masterythreshold_help'] = 'The score a learner needs to pass a topic and unlock the topics after it. Default 90%.

It applies to the mastery check score or to practice mastery, depending on "Unlock next topics after". Only core words in sets that count for mastery are included; optional words never hold a learner back.';
$string['meaningset'] = '{$a}: meaning';
$string['mode'] = 'Mode';
$string['mode_blitz'] = 'Blitz';
$string['mode_blitz_desc'] = '{$a} seconds – how many can you get?';
$string['mode_difficult'] = 'Difficult words';
$string['mode_difficult_desc'] = 'Train your weakest words ({$a})';
$string['mode_difficult_none'] = 'No difficult words here – nice!';
$string['mode_learn'] = 'Learn';
$string['mode_learn_desc'] = 'Flashcards: see and review the words';
$string['mode_practice'] = 'Practice';
$string['mode_practice_desc'] = 'Adaptive training until every word is right';
$string['mode_streak'] = 'Streak';
$string['mode_streak_desc'] = 'How far can you go without a mistake?';
$string['modedisabled'] = 'This training mode is switched off for this activity.';
$string['modulename'] = 'Vocabulary Mastery';
$string['modulename_help'] = 'Vocabulary Mastery trains anything that has to be memorised rather than derived from rules: noun genders/articles, verb + preposition, irregular forms, translations, terminology.

Content is organised as levels → topics → sets → items. Learners learn, practise adaptively, race in Blitz mode, retrain their difficult words, and unlock the next topic once they reach the mastery threshold (default 90%).';
$string['modulenameplural'] = 'Vocabulary Mastery activities';
$string['moveto'] = 'Move to set…';
$string['nativelanguage'] = 'Translation language';
$string['nativelanguage_help'] = 'The language of the translations, for example "English". It is shown as a label next to translations only.';
$string['ncore'] = '{$a} core';
$string['ndifficult'] = 'Words needing attention: {$a}';
$string['nitems'] = '{$a->total} items ({$a->core} core)';
$string['noattemptcapability'] = 'You can look at this activity, but your role does not allow you to practise or take mastery checks.';
$string['nocontentstudent'] = 'Your teacher has not added any vocabulary yet.';
$string['nocontentyet'] = 'No vocabulary yet. Import Word documents or a table, or add levels, topics and sets manually.';
$string['nodifficultitems'] = 'No difficult items.';
$string['noitems'] = 'There are no items to train here yet.';
$string['noptional'] = '{$a} optional';
$string['norows'] = 'No rows found. Paste a table or choose a file.';
$string['nosectionsfound'] = 'No vocabulary tables found in {$a}.';
$string['nosessions'] = 'No training sessions yet.';
$string['nostudents'] = 'No learners to report on.';
$string['notgated'] = 'Practice only (not counted for mastery)';
$string['notopics'] = 'No topics yet.';
$string['nrows'] = '{$a} rows';
$string['nskipped'] = '{$a} skipped';
$string['ntopics'] = '{$a} topics';
$string['numoptions'] = 'Number of buttons';
$string['numoptions_help'] = 'How many buttons each question shows: the correct answer plus wrong answers from other words in the set. 2–6, default 4.';
$string['nwarnings'] = '{$a} warnings (these rows are skipped or flagged):';
$string['nwords'] = 'Words: {$a}';
$string['openprevious'] = 'Also open earlier topics in the same level';
$string['openprevious_help'] = 'Lets learners go back within a level. As soon as a topic is open, every earlier topic in the same level opens too.

Example with the dual pathway: a learner who reaches B1 Lektion 3 (from B1 Lektion 2, or by going deeper from A2 Lektion 3) can also open B1 Lektion 2 and 1. Forward (next topic in the level) and downward (same topic in the next level) still work as before.

Off by default. Topics a learner has already passed or started always stay open.';
$string['optional'] = 'optional';
$string['orupload'] = 'or upload a file';
$string['orupload_help'] = 'A .csv, .xlsx, .xls or .ods file with one word per row, up to 10,000 rows and 30 columns. Download the sample file above for the column layout.';
$string['overallprogress'] = 'Overall progress';
$string['pastetable'] = 'Paste table';
$string['pastetable_help'] = 'Paste rows copied from Excel, Word or Google Sheets, one word per row, e.g. "der → Vater → father". A header row is recognised automatically. You check the column mapping on the next screen before anything is imported.';
$string['pathkey'] = 'Path position';
$string['pathkey_help'] = 'Lines topics up across levels for the dual and level pathways. Topics with the same number in consecutive levels count as "the same topic, one level deeper", e.g. lesson 1 in A1, A2 and B1.

0 = use the topic\'s position within its level.';
$string['pathkeyshort'] = '#{$a}';
$string['pathwaymap'] = 'Your pathway';
$string['pluginadministration'] = 'Vocabulary Mastery administration';
$string['pluginname'] = 'Vocabulary Mastery';
$string['previewn'] = 'Preview ({$a} rows)';
$string['privacy:metadata:attempt'] = 'Training sessions and mastery checks';
$string['privacy:metadata:attempt:beststreak'] = 'The longest run of correct answers in the session.';
$string['privacy:metadata:attempt:correct'] = 'Correct answers in the session';
$string['privacy:metadata:attempt:itemids'] = 'The words chosen for the session.';
$string['privacy:metadata:attempt:mode'] = 'Training mode';
$string['privacy:metadata:attempt:passed'] = 'Whether a mastery check was passed.';
$string['privacy:metadata:attempt:score'] = 'Session score';
$string['privacy:metadata:attempt:timefinish'] = 'When the session was finished.';
$string['privacy:metadata:attempt:total'] = 'Answers in the session';
$string['privacy:metadata:flag'] = 'Problems with a word reported by the learner.';
$string['privacy:metadata:flag:commenttext'] = 'The learner\'s optional comment.';
$string['privacy:metadata:flag:reason'] = 'The kind of problem reported.';
$string['privacy:metadata:flag:resolved'] = 'Whether a teacher has dealt with the report.';
$string['privacy:metadata:itemstate'] = 'Learning state per vocabulary item';
$string['privacy:metadata:itemstate:attempts'] = 'Number of answers';
$string['privacy:metadata:itemstate:correct'] = 'How often the word was answered correctly.';
$string['privacy:metadata:itemstate:difficult'] = 'Whether the item is a difficult word for the user';
$string['privacy:metadata:itemstate:lastseen'] = 'When the word was last practised.';
$string['privacy:metadata:itemstate:mastered'] = 'Whether the item is mastered';
$string['privacy:metadata:itemstate:streak'] = 'The current run of correct answers for the word.';
$string['privacy:metadata:itemstate:wrong'] = 'How often the word was answered wrongly.';
$string['privacy:metadata:response'] = 'Individual answers';
$string['privacy:metadata:response:correct'] = 'Whether it was correct';
$string['privacy:metadata:response:hinted'] = 'Whether a hint was used for the answer.';
$string['privacy:metadata:response:response'] = 'The answer given';
$string['privacy:metadata:response:responsetime'] = 'Time taken to answer';
$string['privacy:metadata:timecreated'] = 'When the answer was given';
$string['privacy:metadata:timestart'] = 'When the session started';
$string['privacy:metadata:topicstate'] = 'Progress per topic';
$string['privacy:metadata:topicstate:besttest'] = 'Best mastery-check score';
$string['privacy:metadata:topicstate:learned'] = 'Whether Learn mode was completed for the topic.';
$string['privacy:metadata:topicstate:mastery'] = 'Topic mastery percentage';
$string['privacy:metadata:topicstate:passed'] = 'Whether the topic is passed';
$string['privacy:metadata:topicstate:timepassed'] = 'When the topic was passed.';
$string['privacy:metadata:userid'] = 'The user';
$string['progress'] = 'Progress';
$string['progression_dual'] = 'Dual pathway (next topic or next level)';
$string['progression_level'] = 'Level pathway (same topic up the levels first)';
$string['progression_linear'] = 'Linear (topic by topic, level by level)';
$string['progression_open'] = 'Open (all topics available)';
$string['progressionexplain_dual'] = 'After mastering a topic, go right (next topic, same level) or down (same topic, next level).';
$string['progressionexplain_level'] = 'Master a topic in each level before moving on to the next topic.';
$string['progressionexplain_linear'] = 'Topics open one after another.';
$string['progressionexplain_open'] = 'All topics are open.';
$string['progressionmode'] = 'Progression mode';
$string['progressionmode_help'] = 'How topics unlock. Topics form a grid: levels (e.g. A1, A2, B1) down, and topics within each level across. Topics in different levels line up by their path position (e.g. lesson number).

* **Dual pathway**: after passing a topic, the learner can go on to the next topic in the same level, or to the same topic in the next level.
* **Linear**: one topic after another, level by level.
* **Level pathway**: topic 1 in every level, then topic 2 in every level, and so on.
* **Open**: every topic is available from the start.

Teachers can always preview locked topics.';
$string['progressionsettings'] = 'Progression';
$string['progressreset'] = 'Progress of {$a} was reset.';
$string['promptcopied'] = 'Prompt copied. Paste it into ChatGPT.';
$string['reason_other'] = 'Something else';
$string['reason_translation'] = 'The translation is wrong';
$string['reason_typo'] = 'Spelling mistake';
$string['reason_wronganswer'] = 'The answer is wrong';
$string['recentsessions'] = 'Recent sessions';
$string['report_heatmap'] = 'Heatmap';
$string['report_overview'] = 'Overview';
$string['report_words'] = 'Difficult vocabulary';
$string['reports'] = 'Vocabulary reports';
$string['reports_by'] = 'Reported by {$a->name} on {$a->date}';
$string['reports_count'] = '{$a} reported';
$string['reports_intro'] = 'Learners flagged these words. Check them in the table below, then mark each report as fixed.';
$string['reports_none'] = 'No open reports.';
$string['reports_open'] = 'Reported by learners ({$a})';
$string['reports_resolve'] = 'Mark as fixed';
$string['reports_resolved'] = 'Report marked as fixed.';
$string['requirelearn'] = 'Learners must complete Learn before Practice, Blitz and the mastery check';
$string['requirelearn_help'] = 'When ticked, learners must go through Learn mode for a topic once before they can start Practice, Blitz, Streak or the mastery check for that topic.';
$string['resetallprogress'] = 'Delete all vocabulary progress and training history';
$string['resetprogress'] = 'Reset this learner\'s progress';
$string['route_backward'] = 'Go back in this level';
$string['route_horizontal'] = 'Continue in this level';
$string['route_next'] = 'Next available topic';
$string['route_vertical'] = 'Go deeper: next level';
$string['rown'] = 'Row {$a}';
$string['samplehelpnote'] = 'Delete the example rows, keep the header row in row 1 of the Vocabulary sheet, then upload the file on the Import vocabulary page (Excel / CSV / paste).';
$string['samplehelpsheetname'] = 'How to use';
$string['samplehelptitle'] = 'Column reference';
$string['samplesheetname'] = 'Vocabulary';
$string['score'] = 'Score';
$string['setname'] = 'Set name';
$string['setname_help'] = 'A name for this group of words inside the topic, for example "Nomen + Artikel" or "Verben: Trennbar". Learners can practise one set at a time.';
$string['showtranslation'] = 'Show translation on the question card';
$string['showtranslation_help'] = 'Shows the word\'s translation on the question card, e.g. "Vater (father)". Untick it when the translation would give away the answer, for example in a "What does it mean?" set.';
$string['skip_duplicate'] = 'duplicate';
$string['skip_noanswer'] = 'no answer';
$string['source_table'] = 'Excel / CSV / paste';
$string['source_word'] = 'Word documents';
$string['startcheck'] = 'Start check';
$string['startlearning'] = 'Start here';
$string['state_difficult'] = 'Difficult';
$string['state_familiar'] = 'Familiar';
$string['state_learning'] = 'Learning';
$string['state_mastered'] = 'Mastered';
$string['state_new'] = 'New';
$string['status_active'] = 'In progress';
$string['status_available'] = 'Ready';
$string['status_locked'] = 'Locked';
$string['status_passed'] = 'Mastered';
$string['student'] = 'Learner';
$string['studentsstruggling'] = 'Learners with it as a difficult word';
$string['targethelp'] = 'Rows go into this level / topic / set unless they have their own level, topic or set column. Missing ones are created.';
$string['targetlanguage'] = 'Language being learned';
$string['targetlanguage_help'] = 'The language learners are learning, for example "German". It is shown on the learner\'s screens as a label only; nothing in the trainer depends on it.';
$string['task_closestale'] = 'Finish vocabulary sessions left open';
$string['task_recompute'] = 'Recalculate vocabulary progress after content changes';
$string['teachernotes'] = 'Teacher notes';
$string['teachernotes_help'] = 'Notes for teachers only; learners never see them. Word imports put the lesson\'s "Hinweise" here.';
$string['teacherpreview'] = 'Teacher preview: this topic is still locked for learners who have not unlocked it.';
$string['testlength'] = 'Questions per mastery check';
$string['testlength_help'] = 'How many words a mastery check asks.

* **0** (default): every core word of the topic.
* **A number**, e.g. 20: a random sample of that many words. Words missed in a failed check are always asked again in the next one.';
$string['testreview'] = 'After the mastery check';
$string['testreview_help'] = 'What learners see when a mastery check ends.

* **Show answers** (default): the score plus each word, right or wrong, with the correct answer.
* **Hide answers**: only the score, pass or not, and the result per set. Learners do not see which words were wrong or what the right answers were, so they cannot copy them into the next attempt. They are sent back to practise the topic instead.

When answers are hidden, the server does not send them to the browser at all. Words missed in a failed check are still asked again in the next check.';
$string['testreview_hide'] = 'Hide answers (score only)';
$string['testreview_show'] = 'Show answers (right/wrong and correct answers)';
$string['topic'] = 'Topic';
$string['topicdescription'] = 'Description for learners';
$string['topicdescription_help'] = 'Optional short text shown to learners at the top of the topic page.';
$string['topiclocked'] = 'This topic is still locked.';
$string['topicmastered'] = 'Topic mastered';
$string['topicname'] = 'Topic name';
$string['topicname_help'] = 'Shown to learners on the dashboard and topic page, e.g. "Lektion 1 – Hallo Welt!".';
$string['topics'] = 'Topics';
$string['topicspassed'] = 'Topics mastered';
$string['topicspassedof'] = '{$a->passed} of {$a->total} topics mastered';
$string['traindifficult'] = 'Train difficult words';
$string['trainingmodes'] = 'Training modes';
$string['unfinished'] = 'unfinished';
$string['unlockby'] = 'Unlock next topics after';
$string['unlockby_either'] = 'Either';
$string['unlockby_help'] = 'What a learner has to do to pass a topic.

* **Passing the mastery check** (recommended): a separate test with no feedback or hints must reach the threshold.
* **Practice mastery**: the share of the topic\'s core words the learner has mastered in practice must reach the threshold. No test is needed.
* **Either**: whichever happens first.';
$string['unlockby_practice'] = 'Practice mastery';
$string['unlockby_test'] = 'Passing the mastery check';
$string['unlockhelp_either'] = 'Master {$a}% of the core words or pass the mastery check with {$a}% to move on.';
$string['unlockhelp_practice'] = 'Master {$a}% of the core words to unlock what comes next.';
$string['unlockhelp_test'] = 'Pass the mastery check with {$a}% to unlock what comes next.';
$string['updatepreview'] = 'Update preview';
$string['vocabmastery:addinstance'] = 'Add a new Vocabulary Mastery activity';
$string['vocabmastery:attempt'] = 'Train in Vocabulary Mastery (counted in reports)';
$string['vocabmastery:exportresults'] = 'Export vocabulary results';
$string['vocabmastery:managecontent'] = 'Manage vocabulary content';
$string['vocabmastery:resetprogress'] = 'Reset student vocabulary progress';
$string['vocabmastery:view'] = 'View Vocabulary Mastery';
$string['vocabmastery:viewallprogress'] = 'View everyone\'s vocabulary progress';
$string['vocabmastery:viewownprogress'] = 'View own vocabulary progress';
$string['warn_duplicate'] = 'duplicate of row {$a}';
$string['warn_noanswer'] = 'no correct answer';
$string['warn_noprompt'] = 'no prompt';
$string['warn_unknownanswer'] = '"{$a->prompt}" has the unusual answer "{$a->value}" (added as an extra button).';
$string['wedetected'] = 'We detected:';
$string['wherenext'] = 'Where next?';
$string['withselected'] = 'With selected…';
$string['worddocuments'] = 'Word documents (.docx)';
$string['worddocuments_help'] = 'One or more .docx lesson overviews. Nothing is imported until you check the preview on the next screen.';
$string['wordlist'] = 'Word list and your results';
$string['wordsremaining'] = '{$a} words to master';
$string['wrongofattempts'] = 'Wrong / attempts';
$string['wrongrate'] = 'Wrong rate';
$string['yourdifficultwords'] = 'Your difficult words';
$string['yourprogress'] = 'Your progress';
