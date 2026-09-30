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

use mod_vocabmastery\instance;
use mod_vocabmastery\progress;
use renderable;
use renderer_base;
use templatable;

/**
 * Student home screen.
 *
 * @package    mod_vocabmastery
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dashboard implements renderable, templatable {
    /** @var instance */
    protected instance $instance;
    /** @var progress */
    protected progress $progress;

    /**
     * Constructor.
     *
     * @param instance $instance
     * @param progress $progress
     */
    public function __construct(instance $instance, progress $progress) {
        $this->instance = $instance;
        $this->progress = $progress;
    }

    /**
     * Topic card data.
     *
     * @param instance $instance
     * @param \stdClass $topic
     * @param \stdClass $s summary
     * @return array
     */
    public static function topic_card(instance $instance, \stdClass $topic, \stdClass $s): array {
        $status = progress::status($s);
        $open = $s->unlocked || $instance->bypass_locks();
        return [
            'id' => (int)$topic->id,
            'name' => format_string($topic->name),
            'url' => $open ? $instance->url('view.php', ['topic' => $topic->id])->out(false) : null,
            'status' => $status,
            'statuslabel' => get_string('status_' . $status, 'mod_vocabmastery'),
            'icon' => icons::flag(['passed' => 'check', 'locked' => 'lock', 'active' => 'play', 'available' => 'right'][$status]),
            'passed' => $status === 'passed',
            'locked' => $status === 'locked',
            'active' => $status === 'active',
            'available' => $status === 'available',
            'mastery' => (int)round($s->passed ? max($s->mastery, (float)$instance->vm->masterythreshold) : $s->mastery),
            'realmastery' => (int)round($s->mastery),
            'corecount' => $s->corecount,
            'mastered' => $s->mastered,
            'difficult' => $s->difficult,
            'besttest' => $s->besttest >= 0 ? (int)round($s->besttest) : null,
            'pathkey' => 0,
        ];
    }

    /**
     * Template data.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $vm = $this->instance->vm;
        $c = 'mod_vocabmastery';
        $summaries = $this->progress->get_summaries();
        $keys = $this->progress->path_keys();
        $levels = [];
        $matrixcols = [];
        foreach ($this->instance->get_levels() as $level) {
            $topics = [];
            $sum = 0;
            $passed = 0;
            $countable = 0;
            foreach ($this->instance->get_level_topics((int)$level->id) as $t) {
                $s = $summaries[(int)$t->id];
                $card = self::topic_card($this->instance, $t, $s);
                $card['pathkey'] = $keys[(int)$t->id];
                $matrixcols[$card['pathkey']] = true;
                $topics[] = $card;
                if (!$s->empty) {
                    $countable++;
                    $sum += $s->passed ? 100 : min(99, $s->mastery);
                    $passed += $s->passed ? 1 : 0;
                }
            }
            $percent = $countable ? (int)round($sum / $countable) : 0;
            $levels[] = [
                'id' => (int)$level->id,
                'name' => format_string($level->name),
                'description' => format_string((string)$level->description),
                'percent' => $percent,
                'passedcount' => $passed,
                'topiccount' => $countable,
                'complete' => $countable && $passed === $countable,
                'topics' => $topics,
                'hastopics' => (bool)$topics,
            ];
        }

        // Pathway grid (levels x path position) for the two-way progression.
        ksort($matrixcols);
        $matrix = null;
        if (in_array($vm->progressionmode, ['dual', 'level'], true) && count($levels) > 1) {
            $cols = array_keys($matrixcols);
            $rows = [];
            foreach ($levels as $l) {
                $cells = [];
                $bykey = [];
                foreach ($l['topics'] as $t) {
                    $bykey[$t['pathkey']] = $t;
                }
                foreach ($cols as $k) {
                    $cells[] = isset($bykey[$k]) ? ['has' => true] + $bykey[$k] : ['has' => false];
                }
                $rows[] = ['name' => $l['name'], 'cells' => $cells];
            }
            $matrix = ['cols' => array_map(fn($k) => ['key' => $k], $cols), 'rows' => $rows];
        }

        $next = $this->progress->next_topic();
        $continue = null;
        if ($next) {
            $t = $this->instance->get_topic($next->topicid);
            $continue = [
                'url' => $this->instance->url('view.php', ['topic' => $t->id])->out(false),
                'level' => format_string($this->instance->get_level((int)$t->levelid)->name),
                'name' => format_string($t->name),
                'remaining' => max(0, $next->corecount - $next->mastered),
                'started' => $next->started,
            ];
        }
        $difficult = $this->progress->difficult_items(null);
        $difficulttop = array_map(fn($r) => [
            'prompt' => $r->prompt,
            'correct' => $r->correctvalue,
            'accuracy' => $r->attempts ? (int)round($r->correct / $r->attempts * 100) : 0,
        ], array_slice($difficult, 0, 6));

        $total = count($this->progress->countable_summaries());
        $counts = \mod_vocabmastery\content::counts((int)$vm->id);
        return [
            'cmid' => (int)$this->instance->cm->id,
            'name' => format_string($vm->name),
            'targetlanguage' => format_string((string)$vm->targetlanguage),
            'overall' => (int)round($this->progress->overall_percent()),
            'topicspassed' => $this->progress->topics_passed(),
            'topicstotal' => $total,
            'itemcount' => $counts['items'],
            'continue' => $continue,
            'allpassed' => $total > 0 && $this->progress->topics_passed() === $total,
            'difficultcount' => count($difficult),
            'difficulttop' => $difficulttop,
            'hasdifficult' => (bool)$difficult,
            'levels' => $levels,
            'matrix' => $matrix,
            'hascontent' => $total > 0,
            'threshold' => (int)$vm->masterythreshold,
            'progressionhelp' => get_string('progressionexplain_' . $vm->progressionmode, $c),
            'canmanage' => $this->instance->can_manage(),
            'canreport' => $this->instance->can_view_all(),
            'manageurl' => $this->instance->url('manage.php')->out(false),
            'importurl' => $this->instance->url('import.php')->out(false),
            'reporturl' => $this->instance->url('report.php')->out(false),
            'scheme' => $vm->colourscheme === 'dark' ? 'dark' : 'light',
            'icons' => icons::all(),
        ];
    }
}
