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

/**
 * Template context helpers for the mod_vocabmastery/icon partial.
 *
 * The SVG markup lives in templates/icon.mustache; PHP only passes flags.
 *
 * @package    mod_vocabmastery
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class icons {
    /** Icon names available in templates/icon.mustache. */
    const NAMES = ['learn', 'practice', 'blitz', 'streak', 'difficult', 'test', 'check', 'lock', 'play', 'right', 'down',
        'left', 'trophy', 'alert', 'settings', 'upload', 'chart', 'close', 'hint', 'award', 'layers', 'map', 'list'];

    /**
     * Context for one icon.
     *
     * @param string $name
     * @return array
     */
    public static function flag(string $name): array {
        return ['ic_' . (in_array($name, self::NAMES, true) ? $name : 'check') => true];
    }

    /**
     * Contexts for all icons, keyed by name (use as {{#icons.NAME}} sections).
     *
     * @return array
     */
    public static function all(): array {
        $out = [];
        foreach (self::NAMES as $name) {
            $out[$name] = self::flag($name);
        }
        return $out;
    }
}
