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

namespace mod_vocabmastery\local\lmslabs;

/**
 * Sends one HTTP request to LMS Labs. Replaced by a fake in unit tests.
 *
 * @package    mod_vocabmastery
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface transport {
    /**
     * Send a request.
     *
     * @param string $method GET or POST
     * @param string $url absolute URL on the LMS Labs host
     * @param array|null $json body for POST (sent as application/json)
     * @return array ['status' => int (0 when no HTTP response), 'body' => string, 'error' => string|null]
     */
    public function request(string $method, string $url, ?array $json = null): array;
}
