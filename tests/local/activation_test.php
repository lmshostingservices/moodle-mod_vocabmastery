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

use mod_vocabmastery\local\lmslabs\fake_transport;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/vocabmastery/tests/fixtures/fake_transport.php');
require_once($CFG->libdir . '/adminlib.php');

/**
 * Activation (access check, live price, one-time unlock) against a scripted LMS Labs.
 *
 * No test touches the network or spends credits.
 *
 * @package    mod_vocabmastery
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(activation::class)]
final class activation_test extends \advanced_testcase {
    /** Release checksum used by the fake manifest. */
    public const SHA = '0edd4302132160b24226d0022de83f23f840a1574c31cd1f9233b19d745ba613';
    /** Plugin-local API key used in tests. */
    public const KEY = 'TEST-SECRET-KEY-987';

    /** @var fake_transport */
    protected fake_transport $fake;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        // Complete standalone pair; Central Config (if installed) is cleared so the source is certain.
        set_config('siteid', '', 'local_aiconfig');
        set_config('apikey', '', 'local_aiconfig');
        set_config('siteid', 'SITE-1', 'mod_vocabmastery');
        set_config('apikey', self::KEY, 'mod_vocabmastery');
        $this->fake = new fake_transport();
    }

    /**
     * The live manifest entry, in the real LMS Labs shape.
     *
     * @param int $credits
     * @param string $sha
     * @return array
     */
    protected static function entry(int $credits = 50, string $sha = self::SHA): array {
        return ['version' => '0.9.2', 'numericVersion' => '2026092801', 'status' => 'ready',
            'component' => 'mod_vocabmastery', 'zipExists' => true, 'credits' => $credits, 'creditsRequired' => $credits,
            'acquisitionMode' => 'credit-unlock', 'sha256' => $sha,
            'downloadUrl' => 'https://lms-labs.com/api/downloads/vocabmastery?sha256=' . $sha];
    }

    /**
     * Queue the live manifest: an object whose "plugins" member is keyed by component.
     *
     * @param int $credits
     * @param string $sha
     * @param array $override fields to change in the vocabmastery entry
     */
    protected function manifest(int $credits = 50, string $sha = self::SHA, array $override = []): void {
        $this->fake->add('/api/plugin-versions', 200, ['plugins' => [
            'block_other' => ['component' => 'block_other', 'status' => 'ready', 'sha256' => str_repeat('a', 64),
                'creditsRequired' => 10],
            'mod_vocabmastery' => array_merge(self::entry($credits, $sha), $override),
        ]]);
    }

    public function test_check_locked(): void {
        $this->fake->add('/api/plugin-unlock/verify', 200, ['unlocked' => false, 'credits' => 120]);
        $this->assertSame('locked', (new activation($this->fake))->check()['outcome']);
        $state = activation::get_state();
        $this->assertSame(activation::STATE_LOCKED, $state['state']);
        $this->assertSame('120', $state['credits']);
        $body = $this->fake->sent('/api/plugin-unlock/verify')[0]['json'];
        $this->assertSame(['pluginId' => 'vocabmastery', 'siteId' => 'SITE-1', 'apiKey' => self::KEY], $body);
        $this->assertCount(0, $this->fake->sent('/api/plugin-unlock'));
    }

    public function test_check_unlocked_with_unlimited_balance(): void {
        $this->fake->add('/api/plugin-unlock/verify', 200, ['unlocked' => true, 'credits' => 'unlimited',
            'unlockedAt' => '2026-09-28T10:00:00Z', 'entitlementSource' => 'purchase']);
        $this->assertSame('unlocked', (new activation($this->fake))->check()['outcome']);
        $state = activation::get_state();
        $this->assertSame(activation::STATE_UNLOCKED, $state['state']);
        $this->assertSame('unlimited', $state['credits']);
        $this->assertSame('purchase', $state['source']);
    }

    public function test_balance_formats(): void {
        $this->assertSame('unlimited', activation::parse_balance(['credits' => -1]));
        $this->assertSame('unlimited', activation::parse_balance(['credits' => null, 'unlimited' => true]));
        $this->assertSame('0', activation::parse_balance(['credits' => 0]));
        $this->assertSame('', activation::parse_balance([]));
    }

    public function test_check_without_credentials_sends_nothing(): void {
        set_config('apikey', '', 'mod_vocabmastery');
        $this->assertSame('nocredentials', (new activation($this->fake))->check()['outcome']);
        $this->assertCount(0, $this->fake->requests);
    }

    public function test_check_unreachable_and_auth_errors(): void {
        $this->fake->add('/api/plugin-unlock/verify', 0, '', 'curl error 28');
        $this->fake->add('/api/plugin-unlock/verify', 401, ['error' => 'invalid_credentials']);
        $service = new activation($this->fake);
        $this->assertSame('unverifiable', $service->check()['outcome']);
        $this->assertSame(activation::STATE_UNVERIFIED, activation::get_state()['state']);
        $this->assertSame('autherror', $service->check()['outcome']);
    }

    public function test_manifest_reads_live_entry(): void {
        $this->manifest(50);
        $live = (new activation($this->fake))->manifest();
        $this->assertTrue($live['ok']);
        $this->assertSame(50, $live['credits']);
        $this->assertSame(self::SHA, $live['sha256']);
        $this->assertSame('credit-unlock', $live['mode']);
        $this->assertSame('ready', $live['status']);
        $this->assertSame('0.9.2', $live['version']);
    }

    public function test_manifest_not_ready_or_missing(): void {
        $this->manifest(50, self::SHA, ['status' => 'testing']);
        $this->manifest(50, self::SHA, ['zipExists' => false]);
        $this->manifest(50, self::SHA, ['creditsRequired' => null]);
        $this->manifest(50, self::SHA, ['creditsRequired' => null, 'credits' => null]);
        $this->fake->add('/api/plugin-versions', 200, ['plugins' => ['block_other' => ['status' => 'ready']]]);
        $this->fake->add('/api/plugin-versions', 503, 'Service unavailable');
        $service = new activation($this->fake);
        $this->assertSame('notavailable', $service->manifest()['reason']);
        $this->assertSame('notavailable', $service->manifest()['reason']);
        // The "credits" field is used when "creditsRequired" is absent.
        $this->assertSame(50, $service->manifest()['credits']);
        $this->assertSame('noprice', $service->manifest()['reason']);
        $this->assertSame('notinmanifest', $service->manifest()['reason']);
        $this->assertSame('manifestunavailable', $service->manifest()['reason']);
    }

    public function test_unlock_charges_live_price_once(): void {
        $this->manifest(50);
        $this->fake->add('/api/plugin-unlock', 200, ['success' => true, 'creditsConsumed' => 50,
            'entitlementSource' => 'credits', 'remainingCredits' => 70, 'message' => 'Plugin unlocked',
            'downloadUrl' => 'https://lms-labs.com/api/downloads/vocabmastery']);
        $result = (new activation($this->fake))->unlock(self::SHA, 50);
        $this->assertSame('unlocked', $result['outcome']);
        $this->assertSame(50, $result['charged']);
        $sent = $this->fake->sent('/api/plugin-unlock');
        $this->assertCount(1, $sent);
        $this->assertSame(['pluginId' => 'vocabmastery', 'pluginComponent' => 'mod_vocabmastery', 'siteId' => 'SITE-1',
            'apiKey' => self::KEY, 'releaseSha256' => self::SHA, 'expectedCredits' => 50], $sent[0]['json']);
        $this->assertSame('POST', $sent[0]['method']);
        $this->assertSame('https://lms-labs.com/api/plugin-unlock', $sent[0]['url']);
        $state = activation::get_state();
        $this->assertSame(activation::STATE_UNLOCKED, $state['state']);
        $this->assertSame('70', $state['credits']);
        $this->assertSame('credits', $state['source']);
        // Once unlocked, a second unlock sends nothing.
        $this->assertSame('alreadyunlocked', (new activation($this->fake))->unlock(self::SHA, 50)['outcome']);
        $this->assertCount(1, $this->fake->sent('/api/plugin-unlock'));
    }

    public function test_already_unlocked_is_not_a_new_debit(): void {
        $this->manifest(50);
        // The creditsConsumed value here describes the original unlock, not a new charge.
        $this->fake->add('/api/plugin-unlock', 200, ['success' => true, 'alreadyUnlocked' => true, 'creditsConsumed' => 50,
            'entitlementSource' => 'credits', 'remainingCredits' => 120, 'message' => 'Already unlocked']);
        $result = (new activation($this->fake))->unlock(self::SHA, 50);
        $this->assertSame('alreadyunlocked', $result['outcome']);
        $this->assertSame(0, $result['charged']);
        $this->assertSame('120', activation::get_state()['credits']);
        [$msg] = activation::message($result);
        $this->assertStringContainsString(get_string('act_result_alreadyunlocked', 'mod_vocabmastery'), $msg);
    }

    public function test_zero_credit_purchase_restoration(): void {
        foreach (['marketplace', 'purchase'] as $source) {
            set_config('act_state', '', 'mod_vocabmastery');
            $this->manifest(50);
            $this->fake->add('/api/plugin-unlock', 200, ['success' => true, 'creditsConsumed' => 0,
                'entitlementSource' => $source, 'remainingCredits' => 120, 'message' => 'Restored']);
            $result = (new activation($this->fake))->unlock(self::SHA, 50);
            $this->assertSame('restored', $result['outcome'], $source);
            $this->assertSame(0, $result['charged']);
            $this->assertSame(activation::STATE_UNLOCKED, activation::get_state()['state']);
        }
    }

    public function test_insufficient_balance(): void {
        $this->manifest(50);
        $this->fake->add('/api/plugin-unlock', 402, ['success' => false, 'remainingCredits' => 20,
            'message' => 'Insufficient credits']);
        $result = (new activation($this->fake))->unlock(self::SHA, 50);
        $this->assertSame('insufficient', $result['outcome']);
        $this->assertSame(50, $result['required']);
        $this->assertSame('Insufficient credits', $result['message']);
        $this->assertNotSame(activation::STATE_UNLOCKED, activation::get_state()['state']);
        $this->assertSame('20', activation::get_state()['credits']);
        $this->assertFalse(activation::get_state()['pending']);
    }

    public function test_stale_price_sends_nothing(): void {
        $this->manifest(60);
        $result = (new activation($this->fake))->unlock(self::SHA, 50);
        $this->assertSame('stale', $result['outcome']);
        $this->assertSame(60, $result['required']);
        $this->assertCount(0, $this->fake->sent('/api/plugin-unlock'));
    }

    public function test_stale_release_sends_nothing(): void {
        $this->manifest(50, str_repeat('b', 64));
        $this->assertSame('stale', (new activation($this->fake))->unlock(self::SHA, 50)['outcome']);
        $this->assertCount(0, $this->fake->sent('/api/plugin-unlock'));
    }

    public function test_server_stale_credit_price(): void {
        $this->manifest(50);
        $this->fake->add('/api/plugin-unlock', 409, ['success' => false, 'error' => 'stale_credit_price',
            'message' => 'The credit price has changed.']);
        $result = (new activation($this->fake))->unlock(self::SHA, 50);
        $this->assertSame('stale', $result['outcome']);
        $this->assertSame('The credit price has changed.', $result['message']);
        $this->assertFalse(activation::get_state()['pending']);
        $this->assertCount(1, $this->fake->sent('/api/plugin-unlock'), 'never retried automatically');
    }

    public function test_ambiguous_marketplace_entitlement(): void {
        $this->manifest(50);
        $this->fake->add('/api/plugin-unlock', 409, ['success' => false, 'code' => 'marketplace_entitlement_ambiguous',
            'message' => 'More than one purchase matches this site.']);
        $result = (new activation($this->fake))->unlock(self::SHA, 50);
        $this->assertSame('ambiguous', $result['outcome']);
        [$msg] = activation::message($result);
        $this->assertStringContainsString('More than one purchase matches this site.', $msg);
        $this->assertNotSame(activation::STATE_UNLOCKED, activation::get_state()['state']);
        $this->assertCount(1, $this->fake->sent('/api/plugin-unlock'));
    }

    public function test_other_conflict_is_not_treated_as_stale(): void {
        $this->manifest(50);
        // A 409 without a machine-readable code keeps the server text and is not called stale.
        $this->fake->add('/api/plugin-unlock', 409, ['success' => false, 'message' => 'Release <b>0.9.2</b> is not available.']);
        $result = (new activation($this->fake))->unlock(self::SHA, 50);
        $this->assertSame('conflict', $result['outcome']);
        [$msg] = activation::message($result);
        $this->assertStringContainsString('Release 0.9.2 is not available.', $msg);
        $this->assertStringNotContainsString('<b>', $msg);
        $this->assertCount(1, $this->fake->sent('/api/plugin-unlock'));
    }

    public function test_unsuccessful_200_is_rejected(): void {
        $this->manifest(50);
        $this->fake->add('/api/plugin-unlock', 200, ['success' => false, 'message' => 'Not permitted']);
        $result = (new activation($this->fake))->unlock(self::SHA, 50);
        $this->assertSame('rejected', $result['outcome']);
        $this->assertNotSame(activation::STATE_UNLOCKED, activation::get_state()['state']);
    }

    public function test_uncertain_result_requires_check_before_retry(): void {
        $this->manifest(50);
        $this->fake->add('/api/plugin-unlock', 0, '', 'curl error 28');
        $service = new activation($this->fake);
        $this->assertSame('uncertain', $service->unlock(self::SHA, 50)['outcome']);
        $state = activation::get_state();
        $this->assertTrue($state['pending']);
        $this->assertSame(activation::STATE_UNVERIFIED, $state['state']);
        // Another submission is refused without any request.
        $count = count($this->fake->requests);
        $this->assertSame('checkfirst', $service->unlock(self::SHA, 50)['outcome']);
        $this->assertCount($count, $this->fake->requests);
        // A definite "locked" answer clears the flag and allows a new, confirmed attempt.
        $this->fake->add('/api/plugin-unlock/verify', 200, ['unlocked' => false, 'credits' => 120]);
        $this->assertSame('locked', $service->check()['outcome']);
        $this->assertFalse(activation::get_state()['pending']);
        $this->manifest(50);
        $this->fake->add('/api/plugin-unlock', 200, ['success' => true, 'creditsConsumed' => 50, 'remainingCredits' => 70]);
        $this->assertSame('unlocked', $service->unlock(self::SHA, 50)['outcome']);
    }

    public function test_uncertain_then_check_finds_purchase_went_through(): void {
        $this->manifest(50);
        $this->fake->add('/api/plugin-unlock', 502, '<html>Bad gateway</html>');
        $service = new activation($this->fake);
        $this->assertSame('uncertain', $service->unlock(self::SHA, 50)['outcome']);
        $this->fake->add('/api/plugin-unlock/verify', 200, ['unlocked' => true, 'credits' => 70]);
        $this->assertSame('unlocked', $service->check()['outcome']);
        $this->assertSame('alreadyunlocked', $service->unlock(self::SHA, 50)['outcome']);
        $this->assertCount(1, $this->fake->sent('/api/plugin-unlock'));
    }

    public function test_cancel_after_review_sends_no_purchase(): void {
        // Review = free check + live price; the administrator then cancels.
        $this->fake->add('/api/plugin-unlock/verify', 200, ['unlocked' => false, 'credits' => 120]);
        $this->manifest(50);
        $service = new activation($this->fake);
        $this->assertSame('locked', $service->check()['outcome']);
        $this->assertTrue($service->manifest()['ok']);
        [$msg] = activation::message(['outcome' => 'cancelled']);
        $this->assertSame(get_string('act_result_cancelled', 'mod_vocabmastery'), $msg);
        $this->assertCount(0, $this->fake->sent('/api/plugin-unlock'));
    }

    public function test_key_never_stored_or_shown(): void {
        $this->fake->add('/api/plugin-unlock/verify', 200, ['unlocked' => false, 'credits' => 5]);
        (new activation($this->fake))->check();
        $this->assertStringNotContainsString(self::KEY, json_encode(activation::get_state()));
        foreach (['locked', 'uncertain', 'insufficient', 'stale', 'autherror'] as $outcome) {
            [$msg] = activation::message(['outcome' => $outcome, 'required' => 50]);
            $this->assertStringNotContainsString(self::KEY, $msg);
        }
    }

    public function test_activation_page_is_admin_only(): void {
        $this->setAdminUser();
        $node = admin_get_root(true, false)->locate('mod_vocabmastery_activation');
        $this->assertInstanceOf(\admin_externalpage::class, $node);
        $this->assertSame(['moodle/site:config'], $node->req_capability);
        $this->assertStringContainsString('/mod/vocabmastery/activation.php', $node->url);
    }
}
