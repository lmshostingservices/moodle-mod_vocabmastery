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

namespace mod_vocabmastery\local;

/**
 * Resolves the site's LMS Labs credentials (Site ID + API key).
 *
 * Central Config (local_aiconfig) wins when it holds a complete pair. Otherwise a complete
 * pair from this plugin's own settings is used. Pairs are never mixed and central values are
 * never copied locally, so credential changes in Central Config take effect immediately.
 * The API key must stay server-side: never send it to JavaScript, templates, AJAX or logs.
 *
 * @package    mod_vocabmastery
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class credentials {
    /** Source: Central Config. */
    public const SOURCE_CENTRAL = 'central';
    /** Source: this plugin's settings. */
    public const SOURCE_LOCAL = 'local';
    /** Source: none (not configured). */
    public const SOURCE_NONE = 'none';

    /**
     * Complete central pair, if Central Config is installed and configured.
     *
     * @return array|null
     */
    protected static function central(): ?array {
        if (!class_exists('\\local_aiconfig\\config')) {
            return null;
        }
        $central = \local_aiconfig\config::get_credentials();
        $siteid = trim((string)($central['siteid'] ?? ''));
        $apikey = trim((string)($central['apikey'] ?? ''));
        return ($siteid !== '' && $apikey !== '') ? ['siteid' => $siteid, 'apikey' => $apikey] : null;
    }

    /**
     * Complete plugin-local pair, if both values are set.
     *
     * @return array|null
     */
    protected static function local(): ?array {
        $siteid = trim((string)get_config('mod_vocabmastery', 'siteid'));
        $apikey = trim((string)get_config('mod_vocabmastery', 'apikey'));
        return ($siteid !== '' && $apikey !== '') ? ['siteid' => $siteid, 'apikey' => $apikey] : null;
    }

    /**
     * Get a complete credential pair, central first.
     *
     * @return array ['siteid' => string, 'apikey' => string]
     * @throws \moodle_exception lmslabscredentialsmissing when no complete pair exists
     */
    public static function resolve(): array {
        $pair = self::central() ?? self::local();
        if ($pair === null) {
            throw new \moodle_exception('lmslabscredentialsmissing', 'mod_vocabmastery');
        }
        return $pair;
    }

    /**
     * Which source resolve() would use, without exposing the key.
     *
     * @return array ['source' => central|local|none, 'siteid' => string, 'centralinstalled' => bool]
     */
    public static function status(): array {
        $installed = class_exists('\\local_aiconfig\\config');
        if ($pair = self::central()) {
            return ['source' => self::SOURCE_CENTRAL, 'siteid' => $pair['siteid'], 'centralinstalled' => true];
        }
        if ($pair = self::local()) {
            return ['source' => self::SOURCE_LOCAL, 'siteid' => $pair['siteid'], 'centralinstalled' => $installed];
        }
        return ['source' => self::SOURCE_NONE, 'siteid' => '', 'centralinstalled' => $installed];
    }
}
