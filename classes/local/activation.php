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

use mod_vocabmastery\local\lmslabs\curl_transport;
use mod_vocabmastery\local\lmslabs\transport;

/**
 * One-time LMS Labs activation (unlock) for this plugin: access check, live price and unlock.
 *
 * Everything here runs server-side. The API key is only ever placed in request bodies sent to the
 * fixed LMS Labs host; it is never returned, stored by this class, rendered or logged.
 *
 * This class does NOT gate any learning feature. Activation status is informational until the
 * owner approves a separate licensing gate.
 *
 * @package    mod_vocabmastery
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class activation {
    /** Fixed LMS Labs host. Not configurable, so a browser or setting cannot redirect credentials. */
    public const BASE_URL = 'https://lms-labs.com';
    /** Unlock plugin id. */
    public const PLUGIN_ID = 'vocabmastery';
    /** Frankenstyle component. */
    public const COMPONENT = 'mod_vocabmastery';

    /** Access state: never checked on this site. */
    public const STATE_NOT_CHECKED = 'notchecked';
    /** Access state: LMS Labs says locked. */
    public const STATE_LOCKED = 'locked';
    /** Access state: LMS Labs says unlocked. */
    public const STATE_UNLOCKED = 'unlocked';
    /** Access state: the last check or purchase could not be confirmed. */
    public const STATE_UNVERIFIED = 'unverified';

    /** @var transport */
    protected transport $transport;

    /**
     * Constructor.
     *
     * @param transport|null $transport injected in tests
     */
    public function __construct(?transport $transport = null) {
        $this->transport = $transport ?? new curl_transport();
    }

    /**
     * Stored activation state (no secrets).
     *
     * @return array
     */
    public static function get_state(): array {
        $c = 'mod_vocabmastery';
        $state = get_config($c, 'act_state') ?: self::STATE_NOT_CHECKED;
        return [
            'state' => $state,
            'checked' => (int)get_config($c, 'act_checked'),
            'credits' => (string)get_config($c, 'act_credits'),
            'unlockedat' => (string)get_config($c, 'act_unlockedat'),
            'source' => (string)get_config($c, 'act_source'),
            'pending' => (bool)get_config($c, 'act_pending'),
        ];
    }

    /**
     * Save part of the state.
     *
     * @param array $values
     */
    protected static function save_state(array $values): void {
        foreach ($values as $k => $v) {
            set_config('act_' . $k, is_bool($v) ? (int)$v : (string)$v, 'mod_vocabmastery');
        }
    }

    /**
     * Normalise a balance value from LMS Labs.
     *
     * Unlimited is accepted as the string "unlimited", a negative number, or an explicit
     * unlimited flag.
     *
     * @param array $data decoded response
     * @return string digits, "unlimited", or "" when not supplied
     */
    public static function parse_balance(array $data): string {
        if (!empty($data['unlimited']) || !empty($data['unlimitedCredits'])) {
            return 'unlimited';
        }
        if (!array_key_exists('credits', $data) || $data['credits'] === null) {
            return '';
        }
        $v = $data['credits'];
        if (is_string($v) && in_array(strtolower(trim($v)), ['unlimited', 'infinity', '∞'], true)) {
            return 'unlimited';
        }
        if (is_numeric($v)) {
            return ((float)$v < 0) ? 'unlimited' : (string)(int)$v;
        }
        return '';
    }

    /**
     * Credential pair and its source.
     *
     * @return array ['siteid', 'apikey']
     * @throws \moodle_exception when no complete pair exists
     */
    protected function pair(): array {
        return credentials::resolve();
    }

    /**
     * Send a request and decode JSON.
     *
     * @param string $method
     * @param string $path
     * @param array|null $body
     * @return array ['status' => int, 'data' => array|null, 'neterror' => bool]
     */
    protected function call(string $method, string $path, ?array $body = null): array {
        $r = $this->transport->request($method, self::BASE_URL . $path, $body);
        $data = null;
        if ($r['body'] !== '') {
            $decoded = json_decode($r['body'], true);
            $data = is_array($decoded) ? $decoded : null;
        }
        return ['status' => (int)$r['status'], 'data' => $data, 'neterror' => !empty($r['error']) || (int)$r['status'] === 0];
    }

    /**
     * Error code from a response body, lower case, or ''.
     *
     * @param array|null $data
     * @return string
     */
    protected static function error_code(?array $data): string {
        if (!$data) {
            return '';
        }
        foreach (['code', 'errorCode', 'error', 'reason', 'status'] as $k) {
            if (isset($data[$k]) && is_string($data[$k])) {
                return strtolower($data[$k]);
            }
        }
        return '';
    }

    /**
     * Free access check (POST /api/plugin-unlock/verify). Never spends credits.
     *
     * @return array ['outcome' => unlocked|locked|nocredentials|autherror|unverifiable]
     */
    public function check(): array {
        try {
            $pair = $this->pair();
        } catch (\moodle_exception $e) {
            return ['outcome' => 'nocredentials'];
        }
        $r = $this->call(
            'POST',
            '/api/plugin-unlock/verify',
            [
                'pluginId' => self::PLUGIN_ID,
                'siteId' => $pair['siteid'],
                'apiKey' => $pair['apikey'],
            ]
        );
        $now = time();
        if ($r['status'] === 401 || $r['status'] === 403) {
            self::save_state(['state' => self::STATE_UNVERIFIED, 'checked' => $now]);
            return ['outcome' => 'autherror'];
        }
        if ($r['status'] !== 200 || !$r['data'] || !array_key_exists('unlocked', $r['data'])) {
            self::save_state(['state' => self::STATE_UNVERIFIED, 'checked' => $now]);
            return ['outcome' => 'unverifiable'];
        }
        $d = $r['data'];
        $unlocked = $d['unlocked'] === true || $d['unlocked'] === 1 || $d['unlocked'] === 'true';
        self::save_state(
            [
                'state' => $unlocked ? self::STATE_UNLOCKED : self::STATE_LOCKED,
                'checked' => $now,
                'credits' => self::parse_balance($d),
                'unlockedat' => is_scalar($d['unlockedAt'] ?? null) ? clean_param((string)$d['unlockedAt'], PARAM_TEXT) : '',
                'source' => is_scalar($d['entitlementSource'] ?? null)
                    ? clean_param((string)$d['entitlementSource'], PARAM_TEXT) : '',
                // A definite answer resolves any earlier uncertain purchase.
                'pending' => false,
            ]
        );
        return ['outcome' => $unlocked ? 'unlocked' : 'locked'];
    }

    /**
     * Pick the first present key.
     *
     * @param array $a
     * @param array $keys
     * @return mixed|null
     */
    protected static function first(array $a, array $keys) {
        foreach ($keys as $k) {
            if (array_key_exists($k, $a) && $a[$k] !== null && $a[$k] !== '') {
                return $a[$k];
            }
        }
        return null;
    }

    /**
     * Live release metadata for this plugin (GET /api/plugin-versions).
     *
     * The response is an object whose "plugins" member is an object keyed by component:
     * plugins["mod_vocabmastery"] = {version, numericVersion, status, component, zipExists, credits,
     * creditsRequired, acquisitionMode, sha256, downloadUrl}.
     *
     * @return array [
         'ok' => bool, 'reason' => string, 'sha256' => string, 'credits' => int|null,
     *                'status' => string, 'mode' => string, 'version' => string,
     ]
     */
    public function manifest(): array {
        $fail = fn(string $reason, array $extra = []) => $extra + [
            'ok' => false, 'reason' => $reason, 'sha256' => '',
            'credits' => null, 'status' => '', 'mode' => '', 'version' => '',
        ];
        $r = $this->call('GET', '/api/plugin-versions');
        if ($r['status'] !== 200 || !$r['data']) {
            return $fail('manifestunavailable');
        }
        $plugins = $r['data']['plugins'] ?? null;
        $entry = is_array($plugins) ? ($plugins[self::COMPONENT] ?? null) : null;
        if (!is_array($entry)) {
            return $fail('notinmanifest');
        }
        $sha = strtolower(is_string($entry['sha256'] ?? null) ? $entry['sha256'] : '');
        $credits = $entry['creditsRequired'] ?? ($entry['credits'] ?? null);
        $status = is_string($entry['status'] ?? null) ? $entry['status'] : '';
        $info = [
            'sha256' => $sha,
            'status' => clean_param($status, PARAM_ALPHANUMEXT),
            'mode' => clean_param((string)($entry['acquisitionMode'] ?? ''), PARAM_TEXT),
            'version' => clean_param((string)($entry['version'] ?? ''), PARAM_TEXT),
        ];
        if (!preg_match('/^[a-f0-9]{64}$/', $sha)) {
            return $fail('nosha', $info);
        }
        $validprice = is_int($credits) || (is_string($credits) && ctype_digit($credits))
            || (is_float($credits) && $credits >= 0 && floor($credits) === $credits);
        if (!$validprice || (int)$credits < 0) {
            return $fail('noprice', $info);
        }
        $info['credits'] = (int)$credits;
        if ($status !== 'ready' || ($entry['zipExists'] ?? true) === false) {
            return $fail('notavailable', $info);
        }
        return ['ok' => true, 'reason' => ''] + $info;
    }

    /**
     * Server message text, cleaned for display (never contains the API key).
     *
     * @param array|null $data
     * @param string $apikey
     * @return string
     */
    protected static function server_message(?array $data, string $apikey = ''): string {
        $msg = '';
        foreach (['message', 'error'] as $k) {
            if (isset($data[$k]) && is_string($data[$k]) && trim($data[$k]) !== '') {
                $msg = $data[$k];
                break;
            }
        }
        $msg = trim(clean_param($msg, PARAM_TEXT));
        if ($apikey !== '' && strpos($msg, $apikey) !== false) {
            $msg = str_replace($apikey, '…', $msg);
        }
        return \core_text::substr($msg, 0, 300);
    }

    /**
     * Unlock after the administrator confirmed a price and release.
     *
     * The live manifest is fetched again; if the price or release changed since confirmation,
     * nothing is sent. Nothing is ever retried automatically. After an uncertain result, a definite
     * access check is required before another submission.
     *
     * @param string $confirmedsha release SHA-256 shown on the confirmation screen
     * @param int $confirmedcredits price shown on the confirmation screen
     * @return array ['outcome' => string, 'charged' => int|null, 'required' => int|null, 'message' => string]
     */
    public function unlock(string $confirmedsha, int $confirmedcredits): array {
        $state = self::get_state();
        if ($state['pending']) {
            return ['outcome' => 'checkfirst'];
        }
        if ($state['state'] === self::STATE_UNLOCKED) {
            return ['outcome' => 'alreadyunlocked', 'charged' => 0];
        }
        try {
            $pair = $this->pair();
        } catch (\moodle_exception $e) {
            return ['outcome' => 'nocredentials'];
        }
        $live = $this->manifest();
        if (!$live['ok']) {
            return ['outcome' => $live['reason']];
        }
        if ($live['sha256'] !== strtolower($confirmedsha) || $live['credits'] !== $confirmedcredits) {
            return ['outcome' => 'stale', 'required' => $live['credits']];
        }
        $r = $this->call(
            'POST',
            '/api/plugin-unlock',
            [
                'pluginId' => self::PLUGIN_ID,
                'pluginComponent' => self::COMPONENT,
                'siteId' => $pair['siteid'],
                'apiKey' => $pair['apikey'],
                'releaseSha256' => $live['sha256'],
                'expectedCredits' => $live['credits'],
            ]
        );
        $d = $r['data'] ?? [];
        $code = self::error_code($r['data']);
        $message = self::server_message($r['data'], $pair['apikey']);

        // No definite answer: the purchase may or may not have happened.
        if ($r['neterror'] || $r['status'] >= 500 || $r['status'] === 408 || ($r['status'] === 200 && !$r['data'])) {
            self::save_state(['state' => self::STATE_UNVERIFIED, 'pending' => true]);
            return ['outcome' => 'uncertain', 'message' => $message];
        }
        if (array_key_exists('remainingCredits', $d)) {
            self::save_state(['credits' => self::parse_balance(['credits' => $d['remainingCredits']])]);
        }
        if ($r['status'] === 401 || $r['status'] === 403) {
            return ['outcome' => 'autherror', 'message' => $message];
        }
        if ($r['status'] === 402 || strpos($code, 'insufficient') !== false) {
            $required = $d['creditsRequired'] ?? null;
            return [
                'outcome' => 'insufficient', 'required' => is_numeric($required) ? (int)$required : $live['credits'],
                'message' => $message,
            ];
        }
        if ($r['status'] === 409) {
            // 409 covers several conditions; only a stale price is a known "review and confirm again" case.
            if ($code === 'stale_credit_price') {
                return ['outcome' => 'stale', 'required' => $live['credits'], 'message' => $message];
            }
            if ($code === 'marketplace_entitlement_ambiguous') {
                return ['outcome' => 'ambiguous', 'message' => $message];
            }
            return ['outcome' => 'conflict', 'message' => $message];
        }
        if ($r['status'] < 200 || $r['status'] >= 300 || ($d['success'] ?? null) !== true) {
            return ['outcome' => 'rejected', 'message' => $message];
        }
        $consumed = is_numeric($d['creditsConsumed'] ?? null) ? (int)$d['creditsConsumed'] : null;
        $source = is_scalar($d['entitlementSource'] ?? null) ? clean_param((string)$d['entitlementSource'], PARAM_TEXT) : '';
        if (!empty($d['alreadyUnlocked'])) {
            // The creditsConsumed value describes the original unlock here, not a new charge.
            $outcome = 'alreadyunlocked';
            $charged = 0;
        } else if ($consumed === 0 && in_array($source, ['marketplace', 'purchase'], true)) {
            $outcome = 'restored';
            $charged = 0;
        } else {
            $outcome = 'unlocked';
            $charged = $consumed;
        }
        self::save_state(
            [
                'state' => self::STATE_UNLOCKED,
                'pending' => false,
                'checked' => time(),
                'source' => $source,
            ]
        );
        return ['outcome' => $outcome, 'charged' => $charged, 'message' => $message];
    }

    /**
     * Message and notification type for an outcome.
     *
     * @param array $result
     * @return array [string, string]
     */
    public static function message(array $result): array {
        $c = 'mod_vocabmastery';
        $outcome = $result['outcome'];
        $types = [
            'unlocked' => \core\output\notification::NOTIFY_SUCCESS,
            'restored' => \core\output\notification::NOTIFY_SUCCESS,
            'alreadyunlocked' => \core\output\notification::NOTIFY_SUCCESS,
            'locked' => \core\output\notification::NOTIFY_INFO,
            'cancelled' => \core\output\notification::NOTIFY_INFO,
            'uncertain' => \core\output\notification::NOTIFY_WARNING,
            'checkfirst' => \core\output\notification::NOTIFY_WARNING,
            'stale' => \core\output\notification::NOTIFY_WARNING,
            'ambiguous' => \core\output\notification::NOTIFY_WARNING,
            'conflict' => \core\output\notification::NOTIFY_WARNING,
        ];
        $a = (object)['charged' => $result['charged'] ?? '', 'required' => $result['required'] ?? ''];
        $servermsg = trim((string)($result['message'] ?? ''));
        if ($outcome === 'unlocked' && ($result['charged'] ?? null) === null) {
            $outcome = 'unlockednocharge';
        }
        $type = $types[$result['outcome']] ?? \core\output\notification::NOTIFY_ERROR;
        $text = get_string('act_result_' . $outcome, $c, $a);
        if ($servermsg !== '') {
            $text .= ' ' . get_string('act_servermessage', $c, s($servermsg));
        }
        return [$text, $type];
    }
}
