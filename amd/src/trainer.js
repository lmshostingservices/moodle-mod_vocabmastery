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
 * Vocabulary trainer: Learn, Practice, Blitz, Streak, Difficult words and Mastery check.
 *
 * Every answer is sent to the server, which re-checks it and owns all mastery data.
 * Practice feedback is computed locally so the next card appears without waiting.
 * All markup comes from mod_vocabmastery/trainer_* templates.
 *
 * @module     mod_vocabmastery/trainer
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Notification from 'core/notification';
import Templates from 'core/templates';
import {getStrings} from 'core/str';

const COMPONENT = 'mod_vocabmastery';

/** Language strings used by the trainer (keys in lang file are prefixed with js_). */
const STRING_KEYS = ['mode_learn', 'mode_practice', 'mode_blitz', 'mode_streak', 'mode_difficult', 'masterycheck',
    'reveal', 'iknowthis', 'needpractice', 'continue', 'check', 'hint', 'exit', 'correctmsg', 'wrongmsg', 'wrongchose',
    'correctanswer', 'appearsagain', 'streakmsg', 'questionnofm', 'cardnofm', 'blitzcorrect', 'blitzwrong',
    'blitzstreak', 'sessiondone', 'blitzdone', 'streakdone', 'learndone', 'testpassed', 'testnotyet', 'answeredcorrect',
    'accuracy', 'beststreakn', 'personalbest', 'previousbestn', 'againbtn', 'practisenmistakes', 'backtotopic',
    'retrycheck', 'wherenext', 'route_horizontal', 'route_vertical', 'route_next', 'route_backward', 'res_tip_hidden',
    'next_practisetopic', 'next_practisetopic_text', 'topicmasteredpct', 'masterynow',
    'yourmistakes', 'typeanswer', 'startpractice', 'loading', 'errorsaving', 'optionaltag', 'difficulttag', 'reviewedn',
    'backtooverview', 'yougot', 'res_excellent', 'res_great', 'res_solid', 'res_start', 'res_tip_excellent',
    'res_tip_great', 'res_tip_solid', 'res_tip_start', 'res_passed', 'res_notyet', 'completedin', 'lasttime', 'bigjump',
    'youranswers', 'strongin', 'workon', 'reviewanswers', 'youranswer', 'noanswer', 'nextstep', 'next_mistakes',
    'next_mistakes_text', 'next_check', 'next_check_text', 'next_again', 'next_again_text', 'next_practice',
    'next_practice_text', 'next_go', 'report', 'report_title', 'reason_wronganswer', 'reason_typo', 'reason_translation',
    'reason_other', 'report_comment', 'report_send', 'report_thanks', 'report_failed', 'cancel', 'seenexttime',
    'correctofn', 'streakof', 'blitzscore', 'sameaslast', 'next_mistakes_one', 'reviewedn_one', 'finishfailed', 'retry',
    'key_space', 'key_enter'];

const ICONS = ['learn', 'practice', 'blitz', 'streak', 'difficult', 'test', 'check', 'close', 'hint', 'award', 'trophy',
    'right', 'down', 'left'];

const TEMPLATES = ['shell', 'question', 'feedback', 'flashcard', 'stats', 'summary', 'message', 'report']
    .map((t) => `${COMPONENT}/trainer_${t}`);

let cfg = null;
let app = null;
let stage = null;
let live = null;
let session = null;
let strings = {};
let keysBound = false;
let starting = false;

const icons = {};
ICONS.forEach((name) => {
    icons[name] = {[`ic_${name}`]: true};
});

/**
 * Format a loaded language string.
 *
 * @param {string} key
 * @param {*} a
 * @returns {string}
 */
const str = (key, a) => {
    const s = strings[key] || '';
    if (a === undefined) {
        return s;
    }
    if (typeof a === 'object') {
        return s.replace(/\{\$a->(\w+)\}/g, (m, k) => (a[k] !== undefined ? a[k] : m));
    }
    // A function replacement, so "$&" and similar in user text are not interpreted.
    return s.replace(/\{\$a\}/g, () => a);
};

/**
 * Pick the singular string variant (key_one) for a count of 1.
 *
 * @param {string} key
 * @param {number} n
 * @returns {string}
 */
const strn = (key, n) => (Number(n) === 1 && strings[`${key}_one`] ? str(`${key}_one`, n) : str(key, n));

const loadStrings = () => getStrings(STRING_KEYS.map((key) => ({key: `js_${key}`, component: COMPONENT})))
    .then((values) => {
        STRING_KEYS.forEach((key, i) => {
            strings[key] = values[i];
        });
        return strings;
    });

const normalise = (v) => String(v || '').replace(/\s+/g, ' ').trim().toLowerCase();

const randInt = (min, max) => min + Math.floor(Math.random() * (max - min + 1));

const formatTime = (sec) => {
    const m = Math.floor(sec / 60);
    const r = sec % 60;
    return `${m}:${r < 10 ? '0' : ''}${r}`;
};

/**
 * Whether a key event target is a control that handles Enter/Space itself.
 *
 * @param {EventTarget} el
 * @returns {boolean}
 */
const isControl = (el) => !!(el && el.closest
    && el.closest('button, a[href], input, textarea, select, summary, [contenteditable]'));

const reducedMotion = () => window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

const announce = (text) => {
    if (live) {
        live.textContent = '';
        window.setTimeout(() => {
            live.textContent = text;
        }, 30);
    }
};

const isCorrect = (q, value) => {
    const given = normalise(value);
    if (!given) {
        return false;
    }
    const accepted = [normalise(q.correct)];
    String(q.alternatives || '').split('|').forEach((a) => {
        if (a.trim()) {
            accepted.push(normalise(a));
        }
    });
    return accepted.includes(given);
};

/**
 * Whether an answer is written before the word (a short one-word answer from fixed buttons, e.g. an article).
 *
 * @param {boolean|number} fixed
 * @param {string} value
 * @returns {boolean}
 */
const isPrefix = (fixed, value) => {
    const v = String(value || '').trim();
    return !!fixed && v !== '' && !/\s/.test(v) && v.length <= 4;
};

/**
 * Full answer: "der Vater" for article-style answers, "sich einleben → in + Akk." otherwise.
 *
 * @param {Object} q
 * @param {string} value
 * @returns {string}
 */
const fullAnswer = (q, value) => (isPrefix(q.fixedorder, value) ? `${value} ${q.prompt}` : `${q.prompt} → ${value}`);

const call = (methodname, args) => {
    args.cmid = cfg.cmid;
    return Ajax.call([{methodname: `${COMPONENT}_${methodname}`, args}])[0];
};

const modeTitle = (mode) => (mode === 'test' ? str('masterycheck') : str(`mode_${mode}`));

const region = (name, root) => (root || stage).querySelector(`[data-region="${name}"]`);

/**
 * Render a template into a node.
 *
 * @param {HTMLElement} node
 * @param {string} template short name, e.g. question
 * @param {Object} context
 * @returns {Promise}
 */
const render = (node, template, context) => Templates.renderForPromise(`${COMPONENT}/trainer_${template}`,
    Object.assign({icons}, context))
    .then(({html, js}) => {
        Templates.replaceNodeContents(node, html, js);
        return node;
    });

const showMessage = (message, loading) => render(stage, 'message', {
    message,
    loading,
    backurl: loading ? null : (cfg.topicurl || cfg.homeurl),
    backlabel: str('backtotopic'),
});

// Shell, progress and stats.

const shell = () => {
    const s = session;
    return render(stage, 'shell', {mode: s.mode, modetitle: modeTitle(s.mode), exitlabel: str('exit')}).then(() => {
        s.ui = {count: region('count'), bar: region('bar'), card: region('card'), stats: region('stats'),
            clock: region('clock')};
        s.started = Date.now();
        if (s.mode !== 'blitz') {
            // The interval belongs to this session only.
            const tick = () => {
                if (session !== s) {
                    stopTimer(s);
                    return;
                }
                s.ui.clock.textContent = formatTime(Math.floor((Date.now() - s.started) / 1000));
            };
            tick();
            s.clock = window.setInterval(tick, 1000);
        }
        window.scrollTo({top: app.getBoundingClientRect().top + window.scrollY - 80, behavior: 'smooth'});
        return null;
    });
};

const setProgress = (done, total, label) => {
    const ui = session.ui;
    ui.count.textContent = label || `${done} / ${total}`;
    ui.bar.style.width = `${total ? Math.min(100, Math.round(done / total * 100)) : 0}%`;
};

const setStats = (stats) => render(session.ui.stats, 'stats', {stats});

const practiceStats = () => setStats([
    {label: str('blitzcorrect'), value: session.correct, cls: 'vm-good'},
    {label: str('blitzwrong'), value: session.wrong, cls: 'vm-bad'},
    {label: str('blitzstreak'), value: session.streak, cls: ''},
]);

// Recording.

const record = (q, value, hinted) => {
    const s = session;
    const args = {
        attemptid: s.attemptid,
        itemid: q.itemid,
        response: String(value),
        responsetime: Math.max(0, Date.now() - (s.qstart || Date.now())),
        hinted: !!hinted,
    };
    s.responses++;
    s.pending = s.pending.then(() => call('record_response', args)).catch(() => {
        s.failed++;
    });
    return s.pending;
};

/**
 * Stop the timers of a session.
 *
 * @param {Object} s session, defaults to the current one
 */
const stopTimer = (s = session) => {
    if (s && s.timer) {
        window.clearInterval(s.timer);
        s.timer = null;
    }
    if (s && s.clock) {
        window.clearInterval(s.clock);
        s.clock = null;
    }
};

/**
 * Close the attempt on the server. Every path (summary, exit) shares one promise; a failure can be retried.
 *
 * @returns {Promise}
 */
const finish = () => {
    const s = session;
    if (!s.finishing) {
        s.done = true;
        stopTimer(s);
        s.finishing = s.pending.then(() => call('finish_session', {attemptid: s.attemptid, beststreak: s.bestStreak}))
            .catch((e) => {
                s.finishing = null;
                throw e;
            });
    }
    return s.finishing;
};

/**
 * Saving the results failed: offer to retry or to leave.
 *
 * @param {Object} s session
 * @returns {Promise}
 */
const showFinishError = (s) => {
    s.ui.card.className = 'vm-tr-card';
    return render(s.ui.card, 'message', {
        message: str('finishfailed'),
        loading: false,
        retrylabel: str('retry'),
        backurl: s.topicid && cfg.topicurl ? cfg.topicurl : cfg.homeurl,
        backlabel: s.topicid ? str('backtotopic') : str('backtooverview'),
    }).then(() => {
        const retry = s.ui.card.querySelector('[data-action="finishretry"]');
        if (retry) {
            retry.focus();
        }
        return null;
    });
};

const finishAndSummarise = () => {
    const s = session;
    return finish()
        .then((r) => {
            if (session !== s || s.exiting) {
                return null;
            }
            return showSummary(r).catch(Notification.exception);
        })
        .catch(() => {
            if (session === s && !s.exiting) {
                showFinishError(s).catch(Notification.exception);
            }
        });
};

// Question card.

/**
 * Render a question card.
 *
 * @param {Object} q
 * @param {boolean} secure mastery check (no hints, no tags)
 * @returns {Promise}
 */
const renderQuestion = (q, secure) => {
    const s = session;
    s.current = q;
    s.answered = false;
    s.hinted = false;
    s.continueFn = null;
    s.advanceTimer = null;
    const choice = q.itemtype !== 'typed' && q.options.length > 0;
    const injected = !!q.injected;
    const optional = !secure && !q.iscore;
    const context = {
        instruction: q.instruction,
        prompt: q.prompt,
        translation: q.translation,
        hastags: injected || optional,
        injected,
        optional,
        difficulttag: str('difficulttag'),
        optionaltag: str('optionaltag'),
        choice,
        optioncount: q.options.length,
        options: q.options.map((value, i) => ({value, key: i < 9 ? i + 1 : null})),
        typeanswerlabel: str('typeanswer'),
        checklabel: str('check'),
        hashint: !!q.hint && !secure,
        hintlabel: str('hint'),
    };
    s.ui.card.className = 'vm-tr-card';
    return render(s.ui.card, 'question', context).then(() => {
        s.qstart = Date.now();
        s.ui.buttons = Array.from(s.ui.card.querySelectorAll('[data-action="answer"]'));
        s.ui.input = region('input', s.ui.card);
        s.ui.feedback = region('feedback', s.ui.card);
        s.ui.actions = region('actions', s.ui.card);
        s.ui.prompt = region('prompt', s.ui.card);
        if (s.ui.input) {
            s.ui.input.focus();
        } else if (focusLost()) {
            // The answered card was replaced: keep the learner's place on the new question.
            s.ui.prompt.focus({preventScroll: true});
        }
        return null;
    });
};

/**
 * Whether keyboard focus has dropped to the page body (e.g. a focused button was disabled or removed).
 *
 * @returns {boolean}
 */
const focusLost = () => !document.activeElement || document.activeElement === document.body
    || !document.activeElement.isConnected || document.activeElement.disabled;

/**
 * After answering, the answer controls are disabled: move focus to the prompt so it is not lost.
 *
 * @param {Object} s session
 */
const keepFocus = (s) => {
    if (s.ui.prompt && (focusLost() || s.ui.buttons.includes(document.activeElement) || document.activeElement === s.ui.input)) {
        s.ui.prompt.focus({preventScroll: true});
    }
};

/**
 * Colour answer buttons. Value based: every button carrying a correct value is correct.
 *
 * @param {Object} q
 * @param {string} chosen
 * @param {boolean} correct
 */
const markButtons = (q, chosen, correct) => {
    const s = session;
    s.ui.buttons.forEach((b) => {
        const v = b.dataset.value;
        b.disabled = true;
        if (isCorrect(q, v)) {
            b.classList.add('vm-correct');
        } else if (v === chosen && !correct) {
            b.classList.add('vm-wrong');
        }
    });
    if (s.ui.input) {
        s.ui.input.disabled = true;
        s.ui.input.classList.add(correct ? 'vm-correct' : 'vm-wrong');
    }
    keepFocus(s);
};

const showFeedback = (q, chosen, correct, extra) => {
    const s = session;
    s.ui.card.classList.add(correct ? 'vm-flash-good' : 'vm-flash-bad');
    const sub = [q.translation, q.plural ? `(${q.plural})` : ''].filter(Boolean).join(' ');
    announce(correct ? `${str('correctmsg')} ${fullAnswer(q, q.correct)}`
        : `${str('wrongmsg')} ${str('correctanswer')} ${fullAnswer(q, q.correct)}`);
    return render(s.ui.feedback, 'feedback', {
        correct,
        full: fullAnswer(q, q.correct),
        sub,
        chosenmsg: str('wrongchose', chosen),
        correctlabel: str('correctanswer'),
        explanation: correct ? '' : (q.explanation || q.note),
        extra,
        itemid: q.itemid,
        reportlabel: str('report'),
    });
};

const showContinue = (fn) => {
    const s = session;
    s.ui.actions.textContent = '';
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'vm-btn vm-btn-primary vm-continue-btn';
    btn.dataset.action = 'continue';
    btn.setAttribute('aria-keyshortcuts', 'Enter');
    btn.textContent = str('continue');
    const kbd = document.createElement('kbd');
    kbd.className = 'vm-kbd';
    kbd.setAttribute('aria-hidden', 'true');
    kbd.textContent = str('key_enter');
    btn.appendChild(kbd);
    s.ui.actions.appendChild(btn);
    s.continueFn = fn;
    btn.focus();
};

const handleAnswer = (value) => {
    const s = session;
    if (!s || s.answered || s.done || !s.current) {
        return;
    }
    s.answered = true;
    s.onAnswer(s.current, value);
};

// Practice and difficult words.

const nextPractice = () => {
    const s = session;
    if (s.done) {
        return;
    }
    if (!s.queue.length) {
        finishAndSummarise();
        return;
    }
    const q = s.queue.shift();
    setProgress(Object.keys(s.cleared).length, s.total);
    practiceStats();
    renderQuestion(q, false).catch(Notification.exception);
};

const answerPractice = (q, value) => {
    const s = session;
    const correct = isCorrect(q, value);
    record(q, value, s.hinted);
    s.count++;
    markButtons(q, value, correct);
    let extra = '';
    if (correct) {
        s.correct++;
        s.streak++;
        s.bestStreak = Math.max(s.bestStreak, s.streak);
        if (!q.injected) {
            s.cleared[q.itemid] = true;
        }
        extra = s.streak >= 3 ? str('streakmsg', s.streak) : '';
    } else {
        s.wrong++;
        s.streak = 0;
        s.mistakes[q.itemid] = q;
        // The word comes back a few cards later (at most twice per session, so a session always ends).
        s.retries[q.itemid] = (s.retries[q.itemid] || 0) + 1;
        if (s.retries[q.itemid] <= 2) {
            s.queue.splice(Math.min(s.queue.length, randInt(3, 5)), 0, Object.assign({}, q, {retry: true}));
            extra = str('appearsagain');
        } else {
            extra = str('seenexttime');
        }
    }
    // Mix in a previously difficult word every 5-8 answers.
    if (s.inject.length && s.count >= s.nextInject) {
        s.queue.splice(Math.min(1, s.queue.length), 0, Object.assign({}, s.inject.shift(), {injected: true}));
        s.nextInject = s.count + randInt(5, 8);
    }
    setProgress(Object.keys(s.cleared).length, s.total);
    practiceStats();
    showFeedback(q, value, correct, extra).then(() => {
        if (session !== s || s.done) {
            return null;
        }
        if (correct) {
            s.advanceTimer = window.setTimeout(() => {
                if (session === s) {
                    nextPractice();
                }
            }, reducedMotion() ? 900 : 650);
            s.continueFn = () => {
                window.clearTimeout(s.advanceTimer);
                nextPractice();
            };
        } else {
            showContinue(nextPractice);
        }
        return null;
    }).catch(Notification.exception);
};

const runPractice = () => {
    const s = session;
    s.total = new Set(s.queue.map((q) => q.itemid)).size;
    s.cleared = {};
    s.retries = {};
    s.nextInject = randInt(5, 8);
    s.onAnswer = answerPractice;
    return shell().then(nextPractice);
};

// Learn.

const nextCard = () => {
    const s = session;
    if (!s.queue.length) {
        finishAndSummarise();
        return;
    }
    const q = s.queue.shift();
    s.current = q;
    s.revealed = false;
    s.qstart = Date.now();
    setProgress(s.seen, s.total, str('cardnofm', {n: Math.min(s.seen + 1, s.total), m: s.total}));
    setStats([]);
    s.ui.card.className = 'vm-tr-card vm-flashcard';
    render(s.ui.card, 'flashcard', {
        setname: q.setname,
        prompt: q.prompt,
        translation: q.translation,
        full: fullAnswer(q, q.correct),
        plural: q.plural,
        hint: q.hint,
        explanation: q.explanation || q.note,
        reveallabel: str('reveal'),
        knowlabel: str('iknowthis'),
        morelabel: str('needpractice'),
        spacekey: str('key_space'),
    }).then(() => {
        s.ui.card.querySelector('[data-action="reveal"]').focus();
        return null;
    }).catch(Notification.exception);
};

const revealCard = () => {
    const s = session;
    if (s.revealed || s.done) {
        return;
    }
    s.revealed = true;
    region('back', s.ui.card).hidden = false;
    s.ui.card.querySelector('[data-action="reveal"]').hidden = true;
    const know = s.ui.card.querySelector('[data-action="know"]');
    know.hidden = false;
    s.ui.card.querySelector('[data-action="more"]').hidden = false;
    announce(fullAnswer(s.current, s.current.correct));
    know.focus();
};

const rateCard = (ok) => {
    const s = session;
    if (!s.revealed || s.done) {
        return;
    }
    const q = s.current;
    record(q, ok ? '1' : '0', false);
    s.seen++;
    if (!ok && !q.relearn) {
        s.queue.splice(Math.min(s.queue.length, randInt(3, 6)), 0, Object.assign({}, q, {relearn: true}));
        s.total++;
    }
    nextCard();
};

const runLearn = () => {
    session.total = session.queue.length;
    session.seen = 0;
    return shell().then(nextCard);
};

// Blitz and streak.


const nextTimed = () => {
    const s = session;
    if (s.done) {
        return;
    }
    if (s.cursor >= s.pool.length) {
        // Reshuffle and go round again.
        s.pool.sort(() => Math.random() - 0.5);
        s.cursor = 0;
    }
    const q = s.pool[s.cursor++];
    const stats = [
        {label: str('blitzcorrect'), value: s.correct, cls: 'vm-good'},
        {label: str('blitzwrong'), value: s.wrong, cls: 'vm-bad'},
        {label: str('blitzstreak'), value: s.streak, cls: ''},
    ];
    setStats(s.mode === 'streak' ? [stats[2]] : stats);
    renderQuestion(q, false).catch(Notification.exception);
};

const answerTimed = (q, value) => {
    const s = session;
    const correct = isCorrect(q, value);
    record(q, value, false);
    markButtons(q, value, correct);
    if (correct) {
        s.correct++;
        s.streak++;
        s.bestStreak = Math.max(s.bestStreak, s.streak);
        s.ui.card.classList.add('vm-flash-good');
        if (s.mode === 'streak') {
            setProgress(0, 1, `${str('blitzstreak')} ${s.streak}`);
        }
        window.setTimeout(() => {
            if (session === s) {
                nextTimed();
            }
        }, 180);
        return;
    }
    s.wrong++;
    s.streak = 0;
    s.mistakes[q.itemid] = q;
    showFeedback(q, value, false, '').catch(Notification.exception);
    const moveOn = () => {
        if (session !== s || s.exiting) {
            return;
        }
        if (s.mode === 'streak') {
            finishAndSummarise();
        } else {
            nextTimed();
        }
    };
    // Stored so that opening the report form can hold the card (see openReport).
    s.continueFn = moveOn;
    s.advanceTimer = window.setTimeout(moveOn, s.mode === 'streak' ? 1400 : 1000);
};

const runTimed = () => {
    const s = session;
    s.pool = s.queue.slice();
    s.cursor = 0;
    s.onAnswer = answerTimed;
    return shell().then(() => {
        if (s.mode === 'blitz') {
            s.endAt = Date.now() + cfg.blitzduration * 1000;
            setProgress(0, cfg.blitzduration, formatTime(cfg.blitzduration));
            s.timer = window.setInterval(() => {
                if (session !== s) {
                    stopTimer(s);
                    return;
                }
                const left = Math.max(0, Math.ceil((s.endAt - Date.now()) / 1000));
                setProgress(cfg.blitzduration - left, cfg.blitzduration, formatTime(left));
                if (left <= 0) {
                    stopTimer(s);
                    if (!s.done) {
                        finishAndSummarise();
                    }
                }
            }, 250);
        } else {
            setProgress(0, 1, `${str('blitzstreak')} 0`);
        }
        nextTimed();
        return null;
    });
};

// Mastery check.

const nextTest = () => {
    const s = session;
    if (s.index >= s.total) {
        showMessageInCard();
        finishAndSummarise();
        return;
    }
    setProgress(s.index, s.total, str('questionnofm', {n: s.index + 1, m: s.total}));
    setStats([]);
    renderQuestion(s.queue[s.index], true).catch(Notification.exception);
};

const showMessageInCard = () => render(session.ui.card, 'message', {message: str('loading'), loading: true});

const answerTest = (q, value) => {
    const s = session;
    record(q, value, false);
    s.ui.buttons.forEach((b) => {
        b.disabled = true;
        if (b.dataset.value === value) {
            b.classList.add('vm-chosen');
        }
    });
    keepFocus(s);
    s.index++;
    window.setTimeout(() => {
        if (session === s && !s.exiting) {
            nextTest();
        }
    }, 250);
};

const runTest = () => {
    session.total = session.queue.length;
    session.index = 0;
    session.onAnswer = answerTest;
    return shell().then(nextTest);
};

// Summary.

/**
 * Headline and tip for a score band.
 *
 * @param {number} pct
 * @returns {Object} {band, headline, tip}
 */
const band = (pct) => {
    if (pct >= 90) {
        return {band: 'vm-band-top', key: 'excellent'};
    }
    if (pct >= 75) {
        return {band: 'vm-band-good', key: 'great'};
    }
    if (pct >= 50) {
        return {band: 'vm-band-mid', key: 'solid'};
    }
    return {band: 'vm-band-low', key: 'start'};
};

const RING = 2 * Math.PI * 52;

const showSummary = (r) => {
    const s = session;
    stopTimer(s);
    s.ui.count.textContent = '';
    if (s.ui.clock) {
        s.ui.clock.textContent = '';
    }
    s.ui.bar.style.width = '100%';
    setStats([]);
    s.ui.card.className = 'vm-tr-card vm-summary';
    const answers = r.answers || [];
    const mistakes = r.mode === 'learn' ? [] : r.mistakes;
    const pct = Math.round(r.score);
    const b = band(pct);
    let headline = str(`res_${b.key}`);
    let tip = str(`res_tip_${b.key}`);
    let hasring = true;
    let ringsub = str('correctofn', {correct: r.mode === 'test' ? r.correct : answers.filter((x) => x.iscorrect).length,
        total: r.mode === 'test' ? r.questioncount : answers.length});
    let big = '';
    let bigsub = '';
    if (r.mode === 'test') {
        headline = r.passed ? str('res_passed') : str('res_notyet');
        tip = r.passed ? str('res_tip_excellent') : str('res_tip_start');
        if (r.answershidden && !r.passed) {
            tip = str('res_tip_hidden');
        }
    } else if (r.mode === 'blitz') {
        hasring = false;
        big = String(r.correct);
        bigsub = str('blitzscore', {correct: r.correct, pct});
        headline = str('blitzdone');
        tip = '';
    } else if (r.mode === 'streak') {
        hasring = false;
        big = String(r.beststreak);
        bigsub = str('streakof');
        headline = str('streakdone');
        tip = '';
    } else if (r.mode === 'learn') {
        hasring = false;
        big = String(r.total);
        bigsub = strn('reviewedn', r.total);
        headline = str('learndone');
        tip = '';
    }
    const ringpct = pct;

    const meta = [];
    if (r.seconds > 0) {
        meta.push({text: str('completedin', formatTime(r.seconds)), cls: ''});
    }
    if (r.personalbest) {
        meta.push({text: str('personalbest'), cls: 'vm-meta-best'});
    } else if (r.previousbest && (r.mode === 'blitz' || r.mode === 'streak')) {
        meta.push({text: str('previousbestn', r.previousbest), cls: ''});
    }
    if (s.bestStreak >= 3 && r.mode !== 'streak' && r.mode !== 'test') {
        meta.push({text: str('beststreakn', s.bestStreak), cls: ''});
    }
    let improve = null;
    if (r.previousscore >= 0 && ['practice', 'difficult', 'test'].includes(r.mode)) {
        const prev = Math.round(r.previousscore);
        const delta = pct - prev;
        improve = {
            text: delta === 0 ? str('sameaslast', pct)
                : str('lasttime', {prev, now: pct, delta: delta > 0 ? `+${delta}` : String(delta)}),
            bigjump: delta >= 20 ? str('bigjump') : '',
            cls: delta > 0 ? 'vm-meta-improve' : '',
        };
    }

    // Insights per set, when the session covered more than one set.
    const sets = (r.sets || []).filter((x) => x.total > 0).map((x) => ({name: x.name,
        pct: Math.round(x.correct / x.total * 100)}));
    const strong = sets.filter((x) => x.pct >= 90);
    const work = sets.filter((x) => x.pct < 75).sort((x, y) => x.pct - y.pct);

    // Recommended next step.
    let next = null;
    const showroutes = r.routes && r.routes.length && (r.mode !== 'test' || r.passed);
    if (!showroutes) {
        if (mistakes.length && r.mode !== 'streak') {
            next = {title: strn('next_mistakes', mistakes.length), text: str('next_mistakes_text'), action: 'mistakes'};
        } else if (r.mode === 'learn') {
            next = {title: str('next_practice'), text: str('next_practice_text'), action: 'startpractice'};
        } else if (s.topicid && r.mode !== 'test' && r.mastery >= 0 && r.mastery >= Math.min(80, r.threshold)) {
            next = {title: str('next_check'), text: str('next_check_text', r.threshold), action: 'startcheck'};
        } else if (r.mode !== 'test') {
            next = {title: str('next_again'), text: str('next_again_text'), action: 'again'};
        } else if (r.answershidden && !r.passed) {
            next = {title: str('next_practisetopic'), text: str('next_practisetopic_text'), action: 'startpractice'};
        }
    }
    const actions = [];
    if (r.mode === 'test' && !r.passed) {
        actions.push({action: 'retry', label: str('retrycheck'), cls: 'vm-btn-ghost'});
    } else if (r.mode !== 'test' && r.mode !== 'learn' && (!next || next.action !== 'again')) {
        actions.push({action: 'again', label: str('againbtn'), cls: 'vm-btn-ghost'});
    }
    actions.push({action: 'back', label: s.topicid ? str('backtotopic') : str('backtooverview'), cls: 'vm-btn-ghost'});

    const review = answers.map((x) => ({
        itemid: x.itemid,
        ok: x.iscorrect,
        full: x.prefix ? `${x.correct} ${x.prompt}` : `${x.prompt} → ${x.correct}`,
        given: x.iscorrect ? '' : (x.given ? str('youranswer', x.given) : str('noanswer')),
        translation: x.translation,
        reportlabel: str('report'),
    }));
    // Mistakes first, then the rest in the order asked.
    review.sort((x, y) => Number(x.ok) - Number(y.ok));

    s.mistakeIds = mistakes.map((m) => m.itemid);
    s.summary = true;
    announce(`${headline} ${hasring ? `${ringpct}%` : big}`);
    return render(s.ui.card, 'summary', {
        modelabel: modeTitle(r.mode),
        headline,
        tip,
        hasring,
        ringpct,
        ringdash: `${(RING * Math.max(0, Math.min(100, ringpct)) / 100).toFixed(1)} ${RING.toFixed(1)}`,
        ringband: hasring ? b.band : 'vm-band-neutral',
        ringlabel: `${ringpct}%`,
        ringsub,
        big,
        bigsub,
        meta,
        improve,
        newlypassed: r.newlypassed ? str('topicmasteredpct', Math.round(r.mode === 'test' ? r.score : r.mastery)) : '',
        hasgrid: answers.length > 0 && answers.length <= 120,
        gridlabel: str('youranswers'),
        correctlabel: str('blitzcorrect'),
        wronglabel: str('blitzwrong'),
        grid: answers.map((x, i) => ({n: i + 1, ok: x.iscorrect,
            title: x.prefix ? `${x.correct} ${x.prompt}` : `${x.prompt} → ${x.correct}`})),
        hasinsights: sets.length > 1 && (strong.length > 0 || work.length > 0),
        hasstrong: strong.length > 0,
        haswork: work.length > 0,
        stronglabel: str('strongin'),
        worklabel: str('workon'),
        strong,
        work,
        hasnext: !!next,
        nextlabel: str('nextstep'),
        nexttitle: next ? next.title : '',
        nexttext: next ? next.text : '',
        nextaction: next ? next.action : '',
        nextbutton: str('next_go'),
        hasroutes: !!showroutes,
        routes: showroutes ? r.routes.map((route) => ({
            url: route.url,
            type: route.type,
            icon: icons[{vertical: 'down', backward: 'left'}[route.type] || 'right'],
            label: str(`route_${route.type}`),
            name: `${route.level} · ${route.name}`,
        })) : [],
        hasreview: review.length > 0,
        reviewlabel: str('reviewanswers', review.length),
        review,
        actions,
        failedsave: s.failed ? str('errorsaving') : '',
    }).then(() => {
        const first = s.ui.card.querySelector('.vm-res-next-btn, .vm-route, .vm-summary-actions button');
        if (first) {
            first.focus();
        }
        return null;
    });
};

// Reporting a problem with a word.

const openReport = (btn) => {
    const s = session;
    if (s && !s.summary && s.advanceTimer) {
        // Keep the card on screen while the learner writes.
        window.clearTimeout(s.advanceTimer);
        s.advanceTimer = null;
        if (s.continueFn) {
            showContinue(s.continueFn);
        }
    }
    const slot = btn.closest('.vm-report-slot');
    const itemid = parseInt(btn.dataset.itemid, 10);
    const reasons = ['wronganswer', 'typo', 'translation', 'other'].map((value, i) => ({value,
        label: str(`reason_${value}`), checked: i === 0}));
    return render(slot, 'report', {itemid, uid: `vmr${itemid}${Date.now()}`, title: str('report_title'), reasons,
        commentlabel: str('report_comment'), sendlabel: str('report_send'), cancellabel: str('cancel')})
        .then(() => {
            const form = slot.querySelector('form');
            form.addEventListener('submit', (e) => e.preventDefault());
            const first = form.querySelector('input');
            if (first) {
                first.focus();
            }
            return null;
        });
};

const closeReport = (form, message) => {
    const slot = form.closest('.vm-report-slot');
    const itemid = form.dataset.itemid;
    slot.textContent = '';
    if (message) {
        const done = document.createElement('span');
        done.className = 'vm-report-done';
        done.tabIndex = -1;
        done.textContent = message;
        slot.appendChild(done);
        // Focus (rather than a live announcement) reads the message and keeps the learner's place.
        done.focus();
        return;
    }
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'vm-linkbtn';
    btn.dataset.action = 'report';
    btn.dataset.itemid = itemid;
    btn.textContent = str('report');
    slot.appendChild(btn);
    btn.focus();
};

const sendReport = (btn) => {
    const form = btn.closest('form');
    const checked = form.querySelector('input[type="radio"]:checked');
    // Plain text only: the web service rejects markup.
    const comment = form.querySelector('textarea').value.replace(/<[^>]*>/g, '').replace(/[<>]/g, '').slice(0, 500);
    const error = form.querySelector('[data-region="reporterror"]');
    if (error) {
        error.hidden = true;
    }
    btn.disabled = true;
    return call('report_item', {itemid: parseInt(form.dataset.itemid, 10), reason: checked ? checked.value : 'other',
        comment})
        .then(() => closeReport(form, str('report_thanks')))
        .catch(() => {
            // Keep the form and the comment so the learner can try again.
            btn.disabled = false;
            if (error) {
                error.textContent = str('report_failed');
                error.hidden = false;
            }
            btn.focus();
        });
};

// Lifecycle.

const goBack = () => {
    window.location.href = session && session.topicid && cfg.topicurl ? cfg.topicurl : cfg.homeurl;
};

const exitSession = () => {
    const s = session;
    if (!s || s.summary || (!s.finishing && !s.responses)) {
        stopTimer(s);
        goBack();
        return;
    }
    // Close the attempt whenever anything was answered, sharing the single finish() request.
    s.exiting = true;
    finish().then(goBack).catch(goBack);
};

/**
 * Start a session.
 *
 * @param {string} mode
 * @param {number} topicid
 * @param {number} setid
 * @param {Array} itemids
 * @returns {Promise}
 */
const start = (mode, topicid, setid, itemids) => {
    if (starting) {
        // A double click must not start two sessions.
        return Promise.resolve();
    }
    starting = true;
    stopTimer();
    session = null;
    showMessage(str('loading'), true);
    return call('start_session', {mode, topicid: topicid || 0, setid: setid || 0, itemids: itemids || []})
        .then((res) => {
            session = {
                mode,
                topicid: topicid || 0,
                setid: setid || 0,
                itemids: itemids || [],
                attemptid: res.attemptid,
                queue: res.questions.slice(),
                inject: res.inject.slice(),
                byId: {},
                pending: Promise.resolve(),
                failed: 0,
                responses: 0,
                count: 0,
                correct: 0,
                wrong: 0,
                streak: 0,
                bestStreak: 0,
                mistakes: {},
                done: false,
                fixedorder: res.questions.length ? res.questions[0].fixedorder : 0,
            };
            res.questions.concat(res.inject).forEach((q) => {
                session.byId[q.itemid] = q;
            });
            if (mode === 'learn') {
                return runLearn();
            }
            if (mode === 'test') {
                return runTest();
            }
            if (mode === 'blitz' || mode === 'streak') {
                return runTimed();
            }
            return runPractice();
        })
        .catch((e) => showMessage((e && e.message) ? e.message : String(e), false))
        .catch(Notification.exception)
        .then(() => {
            starting = false;
            return null;
        });
};

const onStageAction = (action, target) => {
    const s = session;
    switch (action) {
        case 'exit':
            exitSession();
            break;
        case 'answer':
            handleAnswer(target.dataset.value);
            break;
        case 'check':
            if (s && s.ui.input && s.ui.input.value.trim() !== '') {
                handleAnswer(s.ui.input.value.trim());
            }
            break;
        case 'hint':
            if (s && s.current) {
                s.hinted = true;
                const hint = document.createElement('div');
                hint.className = 'vm-tr-hint';
                hint.tabIndex = -1;
                hint.textContent = s.current.hint;
                target.replaceWith(hint);
                if (s.ui.input && !s.ui.input.disabled) {
                    s.ui.input.focus();
                } else {
                    hint.focus();
                }
            }
            break;
        case 'continue':
            if (s && s.continueFn) {
                const fn = s.continueFn;
                s.continueFn = null;
                fn();
            }
            break;
        case 'reveal':
            revealCard();
            break;
        case 'know':
            rateCard(true);
            break;
        case 'more':
            rateCard(false);
            break;
        case 'mistakes':
            start('difficult', s.topicid, 0, s.mistakeIds);
            break;
        case 'retry':
        case 'startcheck':
            start('test', s.topicid, 0, []);
            break;
        case 'report':
            openReport(target).catch(Notification.exception);
            break;
        case 'reportsend':
            sendReport(target);
            break;
        case 'reportcancel':
            closeReport(target.closest('form'), '');
            break;
        case 'finishretry':
            if (s) {
                render(s.ui.card, 'message', {message: str('loading'), loading: true})
                    .then(finishAndSummarise)
                    .catch(Notification.exception);
            }
            break;
        case 'startpractice':
            start('practice', s.topicid, s.setid, []);
            break;
        case 'again':
            start(s.mode, s.topicid, s.setid, s.itemids);
            break;
        case 'back':
            goBack();
            break;
        default:
            break;
    }
};

const onKey = (e) => {
    const s = session;
    if (!s || !s.ui || e.ctrlKey || e.metaKey || e.altKey) {
        return;
    }
    const target = e.target;
    // Only react to keys pressed inside the trainer (or with nothing focused), never in the theme's own controls.
    if (target && target !== document.body && target !== document.documentElement && !app.contains(target)) {
        return;
    }
    const report = target && target.closest && target.closest('.vm-report');
    if (report) {
        if (e.key === 'Escape') {
            e.preventDefault();
            closeReport(report, '');
        }
        return;
    }
    if (s.summary) {
        return;
    }
    const inInput = target && (target.tagName === 'INPUT' || target.tagName === 'TEXTAREA');
    if (e.key === 'Escape') {
        exitSession();
        return;
    }
    if (inInput) {
        if (e.key === 'Enter') {
            e.preventDefault();
            if (s.answered && s.continueFn) {
                onStageAction('continue');
            } else {
                onStageAction('check');
            }
        }
        return;
    }
    if (s.mode === 'learn') {
        // A focused button activates itself with Space and Enter; links and form controls keep all their keys.
        const onButton = !!(target && target.closest && target.closest('button'));
        if (isControl(target) && !onButton) {
            return;
        }
        if (e.key === ' ' || e.key === 'Spacebar') {
            if (!onButton) {
                e.preventDefault();
                revealCard();
            }
        } else if (e.key === '1' || e.key === 'ArrowRight') {
            rateCard(true);
        } else if (e.key === '2' || e.key === 'ArrowLeft') {
            rateCard(false);
        }
        return;
    }
    if (e.key === 'Enter' && s.answered && s.continueFn && !isControl(target)) {
        e.preventDefault();
        onStageAction('continue');
        return;
    }
    if (/^[1-9]$/.test(e.key) && !s.answered && s.ui.buttons) {
        const b = s.ui.buttons[parseInt(e.key, 10) - 1];
        if (b && !b.disabled) {
            e.preventDefault();
            b.click();
        }
    }
};

/**
 * Select a set chip (radio) and make it the single tab stop of the group.
 *
 * @param {HTMLElement} chip
 */
const selectChip = (chip) => {
    app.querySelectorAll('[data-action="vm-selectset"]').forEach((c) => {
        c.classList.toggle('vm-selected', c === chip);
        c.setAttribute('aria-checked', c === chip ? 'true' : 'false');
        c.tabIndex = c === chip ? 0 : -1;
    });
};

/**
 * Arrow keys, Home and End move the selection within the set picker (radio group pattern).
 *
 * @param {KeyboardEvent} e
 */
const onChipKey = (e) => {
    const chip = e.target.closest && e.target.closest('[data-action="vm-selectset"]');
    if (!chip || e.ctrlKey || e.metaKey || e.altKey) {
        return;
    }
    const chips = Array.from(app.querySelectorAll('[data-action="vm-selectset"]'));
    const i = chips.indexOf(chip);
    let next = null;
    if (e.key === 'ArrowRight' || e.key === 'ArrowDown') {
        next = chips[(i + 1) % chips.length];
    } else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') {
        next = chips[(i - 1 + chips.length) % chips.length];
    } else if (e.key === 'Home') {
        next = chips[0];
    } else if (e.key === 'End') {
        next = chips[chips.length - 1];
    }
    if (next) {
        e.preventDefault();
        selectChip(next);
        next.focus();
    }
};

const selectedSet = () => {
    const chip = app.querySelector('[data-action="vm-selectset"].vm-selected')
        || app.querySelector('[data-action="vm-selectset"]');
    return chip ? parseInt(chip.dataset.setid, 10) : 0;
};

/**
 * Initialise the trainer on the dashboard or topic page.
 *
 * @param {Object} config cmid, topicid, blitzduration, threshold, topicurl, homeurl
 */
export const init = (config) => {
    app = document.querySelector('[data-region="vm-app"]');
    if (!app) {
        return;
    }
    cfg = config;
    stage = app.querySelector('[data-region="vm-stage"]');
    live = app.querySelector('[data-region="vm-live"]');
    Templates.prefetchTemplates(TEMPLATES.concat([`${COMPONENT}/icon`]));
    const ready = loadStrings().catch(Notification.exception);

    app.addEventListener('click', (e) => {
        const chip = e.target.closest('[data-action="vm-selectset"]');
        if (chip) {
            selectChip(chip);
            return;
        }
        const btn = e.target.closest('[data-action]');
        if (!btn || btn.disabled) {
            return;
        }
        const action = btn.dataset.action;
        if (action === 'vm-start') {
            const topicid = btn.hasAttribute('data-topicid') ? parseInt(btn.dataset.topicid, 10) : cfg.topicid;
            const setid = btn.dataset.setscoped === '1' ? selectedSet() : 0;
            ready.then(() => start(btn.dataset.mode, topicid, setid, [])).catch(Notification.exception);
        } else if (stage.contains(btn)) {
            onStageAction(action, btn);
        }
    });
    const chips = app.querySelectorAll('[data-action="vm-selectset"]');
    if (chips.length) {
        selectChip(app.querySelector('[data-action="vm-selectset"].vm-selected') || chips[0]);
        app.addEventListener('keydown', onChipKey);
    }
    if (!keysBound) {
        keysBound = true;
        document.addEventListener('keydown', onKey);
    }
};
