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

namespace mod_vocabmastery\import;

/**
 * Holds a parsed import between the preview and confirm steps.
 *
 * Data is kept in a managed temp directory and the token is bound to the user's session
 * and course module, so one user cannot confirm another user's import.
 *
 * @package    mod_vocabmastery
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class staging {
    /**
     * Temp file for a token.
     *
     * @param string $token
     * @return string
     */
    protected static function path(string $token): string {
        return make_temp_directory('mod_vocabmastery/import') . '/' . clean_param($token, PARAM_ALPHANUM) . '.json';
    }

    /**
     * Store parsed data and return a token owned by this session.
     *
     * @param array $data
     * @param int $cmid
     * @return string
     */
    public static function store(array $data, int $cmid): string {
        global $SESSION, $USER;
        $token = sha1(random_string(32) . $USER->id . microtime());
        file_put_contents(self::path($token), json_encode($data, JSON_UNESCAPED_UNICODE));
        $SESSION->vocabmastery_imports = $SESSION->vocabmastery_imports ?? [];
        $SESSION->vocabmastery_imports[$token] = $cmid;
        return $token;
    }

    /**
     * Load stored data, checking ownership.
     *
     * @param string $token
     * @param int $cmid
     * @return array
     */
    public static function load(string $token, int $cmid): array {
        global $SESSION;
        $path = self::path($token);
        if ((($SESSION->vocabmastery_imports[$token] ?? 0) !== $cmid) || !is_readable($path)) {
            throw new \moodle_exception('importexpired', 'mod_vocabmastery');
        }
        return json_decode((string)file_get_contents($path), true) ?: [];
    }

    /**
     * Remove stored data.
     *
     * @param string $token
     */
    public static function forget(string $token): void {
        global $SESSION;
        $path = self::path($token);
        if (file_exists($path)) {
            unlink($path);
        }
        unset($SESSION->vocabmastery_imports[$token]);
    }
}
