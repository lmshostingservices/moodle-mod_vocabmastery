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
 * Transport using Moodle's curl wrapper, so the site's proxy, TLS and HTTP security settings apply.
 *
 * Request bodies may contain the API key, so they are never logged or echoed.
 *
 * @package    mod_vocabmastery
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class curl_transport implements transport {
    /** Connection timeout in seconds. */
    public const CONNECT_TIMEOUT = 10;
    /** Total timeout in seconds. */
    public const TIMEOUT = 30;

    /**
     * Send a request.
     *
     * @param string $method
     * @param string $url
     * @param array|null $json
     * @return array
     */
    public function request(string $method, string $url, ?array $json = null): array {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');
        $curl = new \curl(['ignoresecurity' => false]);
        $curl->setHeader(['Accept: application/json']);
        $options = [
            'CURLOPT_CONNECTTIMEOUT' => self::CONNECT_TIMEOUT,
            'CURLOPT_TIMEOUT' => self::TIMEOUT,
            'CURLOPT_FOLLOWLOCATION' => false,
        ];
        if ($method === 'POST') {
            $curl->setHeader(['Content-Type: application/json']);
            $body = $curl->post($url, json_encode($json, JSON_UNESCAPED_SLASHES), $options);
        } else {
            $body = $curl->get($url, [], $options);
        }
        $info = $curl->get_info();
        $errno = $curl->get_errno();
        return [
            'status' => (int)($info['http_code'] ?? 0),
            'body' => is_string($body) ? $body : '',
            'error' => $errno ? 'curl error ' . $errno : null,
        ];
    }
}
