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
    'retrycheck', 'wherenext', 'route_horizontal', 'route_vertical', 'route_next', 'topicmasteredpct', 'masterynow',
    'yourmistakes', 'typeanswer', 'startpractice', 'loading', 'errorsaving', 'optionaltag', 'difficulttag', 'reviewedn',
    'backtooverview', 'yougot'];

const ICONS = ['learn', 'practice', 'blitz', 'streak', 'difficult', 'test', 'check', 'close', 'hint', 'award', 'trophy',
    'right', 'down'];

const TEMPLATES = ['shell', 'question', 'feedback', 'flashcard', 'stats', 'summary', 'message']
    .map((t) => `${COMPONENT}/trainer_${t}`);

let cfg = null;
let app = null;
let stage = null;
let live = null;
let session = null;
let strings = {};
let keysBound = false;

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
    return s.replace(/\{\$a\}/g, a);
};

const loadStrings = () => getStrings(STRING_KEYS.map((key) => ({key: `js_${key}`, component: COMPONENT})))
    .then((values) => {
        STRING_KEYS.forEach((key, i) => {
            strings[key] = values[i];
        });
        return strings;
    });

const normalise = (v) => String(v || '').replace(/\s+/g, ' ').trim().toLowerCase();

const randInt = (min, max) => min + Math.floor(Math.random() * (max - min + 1));

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
 * Full answer: "der Vater" for fixed buttons, "sich einleben → in + Akk." otherwise.
 *
 * @param {Object} q
 * @param {string} value
 * @returns {string}
 */
const fullAnswer = (q, value) => (q.fixedorder ? `${value} ${q.prompt}` : `${q.prompt} → ${value}`);

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

const shell = () => render(stage, 'shell', {mode: session.mode, modetitle: modeTitle(session.mode), exitlabel: str('exit')})
    .then(() => {
        session.ui = {count: region('count'), bar: region('bar'), card: region('card'), stats: region('stats')};
        window.scrollTo({top: app.getBoundingClientRect().top + window.scrollY - 80, behavior: 'smooth'});
        return null;
    });

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
    s.pending = s.pending.then(() => call('record_response', args)).catch(() => {
        s.failed++;
    });
    return s.pending;
};

const stopTimer = () => {
    if (session && session.timer) {
        window.clearInterval(session.timer);
        session.timer = null;
    }
};

const finish = () => {
    const s = session;
    if (!s.finishing) {
        s.done = true;
        stopTimer();
        s.finishing = s.pending.then(() => call('finish_session', {attemptid: s.attemptid, beststreak: s.bestStreak}));
    }
    return s.finishing;
};

const finishAndSummarise = () => finish().then(showSummary).catch(Notification.exception);

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
        if (s.ui.input) {
            s.ui.input.focus();
        }
        return null;
    });
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
    });
};

const showContinue = (fn) => {
    const s = session;
    s.ui.actions.textContent = '';
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'vm-btn vm-btn-primary vm-continue-btn';
    btn.dataset.action = 'continue';
    btn.textContent = str('continue');
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
        // The word comes back a few cards later and must be answered correctly before the end.
        s.queue.splice(Math.min(s.queue.length, randInt(3, 5)), 0, Object.assign({}, q, {retry: true}));
        extra = str('appearsagain');
    }
    // Mix in a previously difficult word every 5-8 answers.
    if (s.inject.length && s.count >= s.nextInject) {
        s.queue.splice(Math.min(1, s.queue.length), 0, Object.assign({}, s.inject.shift(), {injected: true}));
        s.nextInject = s.count + randInt(5, 8);
    }
    setProgress(Object.keys(s.cleared).length, s.total);
    practiceStats();
    showFeedback(q, value, correct, extra).then(() => {
        if (correct) {
            s.advanceTimer = window.setTimeout(nextPractice, reducedMotion() ? 900 : 650);
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
    }).then(() => {
        s.ui.card.querySelector('[data-action="reveal"]').focus();
        return null;
    }).catch(Notification.exception);
};

const revealCard = () => {
    const s = session;
    if (s.revealed) {
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
    if (!s.revealed) {
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

const formatTime = (sec) => {
    const m = Math.floor(sec / 60);
    const r = sec % 60;
    return `${m}:${r < 10 ? '0' : ''}${r}`;
};

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
        window.setTimeout(nextTimed, 180);
        return;
    }
    s.wrong++;
    s.streak = 0;
    s.mistakes[q.itemid] = q;
    showFeedback(q, value, false, '').catch(Notification.exception);
    if (s.mode === 'streak') {
        window.setTimeout(finishAndSummarise, 1400);
    } else {
        window.setTimeout(nextTimed, 1000);
    }
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
                const left = Math.max(0, Math.ceil((s.endAt - Date.now()) / 1000));
                setProgress(cfg.blitzduration - left, cfg.blitzduration, formatTime(left));
                if (left <= 0) {
                    stopTimer();
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
    s.index++;
    window.setTimeout(nextTest, 250);
};

const runTest = () => {
    session.total = session.queue.length;
    session.index = 0;
    session.onAnswer = answerTest;
    return shell().then(nextTest);
};

// Summary.

const showSummary = (r) => {
    const s = session;
    stopTimer();
    s.ui.count.textContent = '';
    s.ui.bar.style.width = '100%';
    setStats([]);
    s.ui.card.className = 'vm-tr-card vm-summary';
    let title;
    let big;
    let icon = 'practice';
    const lines = [];
    if (r.mode === 'test') {
        title = r.passed ? str('testpassed') : str('testnotyet');
        icon = r.passed ? 'award' : 'test';
        big = `${Math.round(r.score)}%`;
        lines.push(str('yougot', {correct: r.correct, total: r.questioncount}));
    } else if (r.mode === 'blitz') {
        title = str('blitzdone');
        icon = 'blitz';
        big = String(r.correct);
        lines.push(`${str('answeredcorrect', {correct: r.correct, total: r.total})} · ${str('accuracy')} ${Math.round(r.score)}%`);
        lines.push(str('beststreakn', r.beststreak));
    } else if (r.mode === 'streak') {
        title = str('streakdone');
        icon = 'streak';
        big = String(r.beststreak);
    } else if (r.mode === 'learn') {
        title = str('learndone');
        icon = 'learn';
        big = String(r.total);
        lines.push(str('reviewedn', r.total));
    } else {
        title = str('sessiondone');
        big = `${Math.round(r.score)}%`;
        lines.push(str('answeredcorrect', {correct: r.correct, total: r.total}));
        if (s.bestStreak >= 3) {
            lines.push(str('beststreakn', s.bestStreak));
        }
    }
    if (!r.personalbest && r.previousbest && (r.mode === 'blitz' || r.mode === 'streak')) {
        lines.push(str('previousbestn', r.previousbest));
    }
    const showroutes = r.routes && r.routes.length && (r.mode !== 'test' || r.passed);
    const mistakes = r.mode === 'learn' ? [] : r.mistakes;
    const actions = [];
    if (mistakes.length && r.mode !== 'streak') {
        actions.push({action: 'mistakes', label: str('practisenmistakes', mistakes.length), cls: 'vm-btn-warn'});
    }
    if (r.mode === 'test' && !r.passed) {
        actions.push({action: 'retry', label: str('retrycheck'), cls: 'vm-btn-primary'});
    } else if (r.mode === 'learn') {
        actions.push({action: 'startpractice', label: str('startpractice'), cls: 'vm-btn-primary'});
    } else if (r.mode !== 'test') {
        actions.push({action: 'again', label: str('againbtn'), cls: 'vm-btn-primary'});
    }
    actions.push({action: 'back', label: s.topicid ? str('backtotopic') : str('backtooverview'), cls: 'vm-btn-ghost'});
    s.mistakeIds = mistakes.map((m) => m.itemid);
    s.summary = true;
    announce(`${title} ${big}`);
    return render(s.ui.card, 'summary', {
        icon: icons[icon],
        iconclass: `vm-summary-icon-${icon}${r.mode === 'test' && !r.passed ? ' vm-summary-icon-muted' : ''}`,
        title,
        big,
        bigclass: r.mode === 'test' ? (r.passed ? 'vm-good' : 'vm-bad') : '',
        lines: lines.map((text) => ({text})),
        best: r.personalbest,
        bestlabel: str('personalbest'),
        showmastery: r.mastery >= 0 && s.topicid > 0,
        masterylabel: str('masterynow'),
        mastery: Math.round(r.mastery),
        newlypassed: r.newlypassed ? str('topicmasteredpct', Math.round(r.mode === 'test' ? r.score : r.mastery)) : '',
        hasroutes: !!showroutes,
        wherenextlabel: str('wherenext'),
        routes: showroutes ? r.routes.map((route) => ({
            url: route.url,
            type: route.type,
            icon: icons[route.type === 'vertical' ? 'down' : 'right'],
            label: str(`route_${route.type}`),
            name: `${route.level} · ${route.name}`,
        })) : [],
        hasmistakes: mistakes.length > 0,
        mistakeslabel: str('yourmistakes'),
        mistakes: mistakes.map((m) => ({
            full: fullAnswer({fixedorder: (s.byId[m.itemid] || {fixedorder: s.fixedorder}).fixedorder, prompt: m.prompt},
                m.correct),
            translation: m.translation,
        })),
        actions,
        failedsave: s.failed ? str('errorsaving') : '',
    }).then(() => {
        const first = s.ui.card.querySelector('.vm-summary-actions button');
        if (first) {
            first.focus();
        }
        return null;
    });
};

// Lifecycle.

const goBack = () => {
    window.location.href = session && session.topicid && cfg.topicurl ? cfg.topicurl : cfg.homeurl;
};

const exitSession = () => {
    const s = session;
    if (!s || s.summary || s.done || (s.count + s.correct + s.wrong) === 0) {
        goBack();
        return;
    }
    stopTimer();
    s.done = true;
    s.pending.then(() => call('finish_session', {attemptid: s.attemptid, beststreak: s.bestStreak}))
        .then(goBack)
        .catch(goBack);
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
        .catch((e) => showMessage((e && e.message) ? e.message : String(e), false));
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
                hint.textContent = s.current.hint;
                target.replaceWith(hint);
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
            start('test', s.topicid, 0, []);
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
    const inInput = e.target && (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA');
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
    if (s.summary) {
        return;
    }
    if (s.mode === 'learn') {
        if (e.key === ' ' || e.key === 'Spacebar') {
            e.preventDefault();
            revealCard();
        } else if (e.key === '1' || e.key === 'ArrowRight') {
            rateCard(true);
        } else if (e.key === '2' || e.key === 'ArrowLeft') {
            rateCard(false);
        }
        return;
    }
    if (e.key === 'Enter' && s.answered && s.continueFn) {
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
            app.querySelectorAll('[data-action="vm-selectset"]').forEach((c) => {
                c.classList.toggle('vm-selected', c === chip);
                c.setAttribute('aria-checked', c === chip ? 'true' : 'false');
            });
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
    if (!keysBound) {
        keysBound = true;
        document.addEventListener('keydown', onKey);
    }
};
