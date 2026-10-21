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

namespace mod_vocabmastery\output;

use mod_vocabmastery\engine;
use mod_vocabmastery\instance;
use mod_vocabmastery\progress;
use renderable;
use renderer_base;
use templatable;

/**
 * Topic screen with training modes and word list.
 *
 * @package    mod_vocabmastery
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class topic_page implements renderable, templatable {
    /** @var instance */
    protected instance $instance;
    /** @var progress */
    protected progress $progress;
    /** @var int */
    protected int $topicid;

    /**
     * Constructor.
     *
     * @param instance $instance
     * @param progress $progress
     * @param int $topicid
     */
    public function __construct(instance $instance, progress $progress, int $topicid) {
        $this->instance = $instance;
        $this->progress = $progress;
        $this->topicid = $topicid;
    }

    /**
     * Template data.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        global $DB;
        $vm = $this->instance->vm;
        $c = 'mod_vocabmastery';
        $topic = $this->instance->get_topic($this->topicid);
        $level = $this->instance->get_level((int)$topic->levelid);
        $s = $this->progress->get_summary($this->topicid);
        $rows = array_filter($this->progress->get_item_rows(), fn($r) => (int)$r->topicid === $this->topicid);

        $sets = [];
        $first = true;
        foreach ($this->instance->get_topic_sets($this->topicid) as $set) {
            $srows = array_filter($rows, fn($r) => (int)$r->setid === (int)$set->id);
            if (!$srows) {
                continue;
            }
            $core = array_filter($srows, fn($r) => $r->iscore);
            $mastered = count(array_filter($core, fn($r) => $r->mastered));
            $words = [];
            $items = $DB->get_records(
                'vocabmastery_item',
                ['setid' => $set->id],
                'sortorder, id',
                'id, prompt, correctvalue, translation, plural, iscore'
            );
            foreach ($items as $it) {
                $r = $srows[$it->id] ?? null;
                if (!$r) {
                    continue;
                }
                $state = engine::state_label($r);
                $words[] = [
                    'prompt' => $it->prompt,
                    'correct' => $it->correctvalue,
                    'translation' => (string)$it->translation,
                    'plural' => (string)$it->plural,
                    'optional' => !$it->iscore,
                    'state' => $state,
                    'statelabel' => get_string('state_' . $state, $c),
                    'accuracy' => $r->attempts ? (int)round($r->correct / $r->attempts * 100) : null,
                    'attempts' => (int)$r->attempts,
                    'answerfirst' => $set->itemtype === 'choice' && $set->answermode === 'fixed',
                ];
            }
            $sets[] = [
                'id' => (int)$set->id,
                'name' => format_string($set->name),
                'itemcount' => count($srows),
                'corecount' => count($core),
                'optionalcount' => count($srows) - count($core),
                'mastery' => $core ? (int)round($mastered / count($core) * 100) : 0,
                'gated' => (bool)$set->gated,
                'difficult' => count(array_filter($srows, fn($r) => $r->difficult)),
                'selected' => $first,
                'words' => $words,
            ];
            $first = false;
        }

        $locklearn = $vm->enablelearn && $vm->requirelearn && !$s->learned && !$this->instance->bypass_locks();
        $modes = [];
        if ($vm->enablelearn) {
            $modes[] = ['mode' => 'learn', 'icon' => icons::flag('learn'), 'title' => get_string('mode_learn', $c),
                'desc' => get_string('mode_learn_desc', $c), 'locked' => false, 'setscoped' => true];
        }
        $modes[] = ['mode' => 'practice', 'icon' => icons::flag('practice'), 'title' => get_string('mode_practice', $c),
            'desc' => get_string('mode_practice_desc', $c), 'locked' => $locklearn, 'setscoped' => true];
        if ($vm->enableblitz) {
            $modes[] = ['mode' => 'blitz', 'icon' => icons::flag('blitz'), 'title' => get_string('mode_blitz', $c),
                'desc' => get_string('mode_blitz_desc', $c, (int)$vm->blitzduration), 'locked' => $locklearn,
                'setscoped' => true];
        }
        if ($vm->enablestreak) {
            $modes[] = ['mode' => 'streak', 'icon' => icons::flag('streak'), 'title' => get_string('mode_streak', $c),
                'desc' => get_string('mode_streak_desc', $c), 'locked' => $locklearn, 'setscoped' => true];
        }
        $modes[] = ['mode' => 'difficult', 'icon' => icons::flag('difficult'), 'title' => get_string('mode_difficult', $c),
            'desc' => $s->difficult ? get_string('mode_difficult_desc', $c, $s->difficult)
                : get_string('mode_difficult_none', $c),
            'locked' => !$s->difficult, 'setscoped' => false, 'count' => $s->difficult];

        $besttest = $s->besttest >= 0 ? (int)round($s->besttest) : null;
        $unlockby = $vm->unlockby;
        $routes = [];
        if ($s->passed || !empty($vm->openprevious)) {
            $topics = $this->instance->get_topics();
            foreach ($this->progress->routes_after($this->topicid) as $route) {
                if (!$s->passed && $route['type'] !== 'backward') {
                    // Before passing, only the way back within the level is offered.
                    continue;
                }
                $t = $topics[$route['topicid']];
                $routes[] = [
                    'type' => $route['type'],
                    'label' => get_string('route_' . $route['type'], $c),
                    'icon' => icons::flag(['vertical' => 'down', 'backward' => 'left'][$route['type']] ?? 'right'),
                    'name' => format_string($t->name),
                    'level' => format_string($this->instance->get_level((int)$t->levelid)->name),
                    'url' => $this->instance->url('view.php', ['topic' => $t->id])->out(false),
                ];
            }
        }

        return [
            'cmid' => (int)$this->instance->cm->id,
            'topicid' => $this->topicid,
            'backurl' => $this->instance->url('view.php')->out(false),
            'activityname' => format_string($vm->name),
            'level' => format_string($level->name),
            'name' => format_string($topic->name),
            'description' => format_text((string)$topic->description, FORMAT_PLAIN),
            'hasdescription' => trim((string)$topic->description) !== '',
            'mastery' => (int)round($s->mastery),
            'corecount' => $s->corecount,
            'mastered' => $s->mastered,
            'learning' => $s->learning,
            'difficult' => $s->difficult,
            'newcount' => $s->newcount,
            'passed' => $s->passed,
            'locked' => !$s->unlocked,
            'preview' => !$s->unlocked && $this->instance->bypass_locks(),
            'threshold' => (int)$vm->masterythreshold,
            'besttest' => $besttest,
            'hasbesttest' => $besttest !== null,
            'testenabled' => $unlockby !== 'practice',
            'testlocked' => $locklearn || !$s->corecount,
            'testquestions' => (int)$vm->testlength > 0 ? min((int)$vm->testlength, $s->corecount) : $s->corecount,
            'unlockhelp' => get_string('unlockhelp_' . $unlockby, $c, (int)$vm->masterythreshold),
            'learnfirst' => $locklearn,
            'sets' => $sets,
            'multisets' => count($sets) > 1,
            'hassets' => (bool)$sets,
            'modes' => $modes,
            'routes' => $routes,
            'hasroutes' => (bool)$routes,
            'scheme' => $vm->colourscheme === 'dark' ? 'dark' : 'light',
            'icons' => icons::all(),
            'ringdash' => (int)round($s->mastery),
        ];
    }
}
