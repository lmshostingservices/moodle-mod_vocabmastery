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
 * Scripted transport for unit tests. Records every request; never touches the network.
 *
 * @package    mod_vocabmastery
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class fake_transport implements transport {
    /** @var array path => list of queued responses */
    public array $responses = [];
    /** @var array recorded requests */
    public array $requests = [];

    /**
     * Queue a response for a path.
     *
     * @param string $path e.g. /api/plugin-unlock
     * @param int $status HTTP status, 0 for no response
     * @param mixed $body array (JSON-encoded) or raw string
     * @param string|null $error transport error
     * @return self
     */
    public function add(string $path, int $status, $body = [], ?string $error = null): self {
        $this->responses[$path][] = ['status' => $status,
            'body' => is_array($body) ? json_encode($body) : (string)$body, 'error' => $error];
        return $this;
    }

    /**
     * Send a request.
     *
     * @param string $method
     * @param string $url
     * @param array|null $json
     * @return array
     */
    public function request(string $method, string $url, ?array $json = null): array {
        $path = parse_url($url, PHP_URL_PATH);
        $this->requests[] = ['method' => $method, 'url' => $url, 'path' => $path, 'json' => $json];
        if (empty($this->responses[$path])) {
            throw new \coding_exception('Unexpected request to ' . $path);
        }
        return array_shift($this->responses[$path]);
    }

    /**
     * Requests sent to a path.
     *
     * @param string $path
     * @return array
     */
    public function sent(string $path): array {
        return array_values(array_filter($this->requests, fn($r) => $r['path'] === $path));
    }
}
