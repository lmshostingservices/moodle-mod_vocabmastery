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
 * Tests for the LMS Labs credential resolver.
 *
 * The Central Config cases run only where local_aiconfig is installed; the plugin-local and
 * "not installed" cases always run.
 *
 * @package    mod_vocabmastery
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_vocabmastery\local\credentials::class)]
final class credentials_test extends \advanced_testcase {
    /** @var bool whether Central Config is available on this site */
    protected bool $central;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->central = class_exists('\\local_aiconfig\\config');
    }

    /**
     * Set or clear Central Config values.
     *
     * @param string $siteid
     * @param string $apikey
     */
    protected function set_central(string $siteid, string $apikey): void {
        set_config('siteid', $siteid, 'local_aiconfig');
        set_config('apikey', $apikey, 'local_aiconfig');
    }

    /**
     * Set or clear this plugin's own values.
     *
     * @param string $siteid
     * @param string $apikey
     */
    protected function set_local(string $siteid, string $apikey): void {
        set_config('siteid', $siteid, 'mod_vocabmastery');
        set_config('apikey', $apikey, 'mod_vocabmastery');
    }

    public function test_local_only(): void {
        $this->set_central('', '');
        $this->set_local('L-SITE', 'L-KEY');
        $this->assertSame(['siteid' => 'L-SITE', 'apikey' => 'L-KEY'], credentials::resolve());
        $this->assertSame(credentials::SOURCE_LOCAL, credentials::status()['source']);
    }

    public function test_incomplete_local_throws(): void {
        $this->set_central('', '');
        $this->set_local('L-SITE', '');
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('lmslabscredentialsmissing', 'mod_vocabmastery'));
        credentials::resolve();
    }

    public function test_nothing_configured_throws(): void {
        $this->set_central('', '');
        $this->set_local('', '');
        $this->assertSame(credentials::SOURCE_NONE, credentials::status()['source']);
        $this->expectException(\moodle_exception::class);
        credentials::resolve();
    }

    public function test_status_never_contains_key(): void {
        $this->set_central('', '');
        $this->set_local('L-SITE', 'SECRET-KEY-123');
        $status = credentials::status();
        $this->assertArrayNotHasKey('apikey', $status);
        $this->assertStringNotContainsString('SECRET-KEY-123', json_encode($status));
    }

    public function test_central_only(): void {
        if (!$this->central) {
            $this->markTestSkipped('local_aiconfig is not installed.');
        }
        $this->set_central('C-SITE', 'C-KEY');
        $this->set_local('', '');
        $this->assertSame(['siteid' => 'C-SITE', 'apikey' => 'C-KEY'], credentials::resolve());
        $this->assertSame(credentials::SOURCE_CENTRAL, credentials::status()['source']);
    }

    public function test_both_prefers_central_and_never_mixes(): void {
        if (!$this->central) {
            $this->markTestSkipped('local_aiconfig is not installed.');
        }
        $this->set_central('C-SITE', 'C-KEY');
        $this->set_local('L-SITE', 'L-KEY');
        $this->assertSame(['siteid' => 'C-SITE', 'apikey' => 'C-KEY'], credentials::resolve());
        // Central values are never copied into this plugin's settings.
        $this->assertSame('L-SITE', get_config('mod_vocabmastery', 'siteid'));
    }

    public function test_incomplete_central_uses_complete_local_pair(): void {
        if (!$this->central) {
            $this->markTestSkipped('local_aiconfig is not installed.');
        }
        $this->set_central('C-SITE', '');
        $this->set_local('L-SITE', 'L-KEY');
        $this->assertSame(['siteid' => 'L-SITE', 'apikey' => 'L-KEY'], credentials::resolve());
    }

    public function test_not_installed_uses_local(): void {
        if ($this->central) {
            $this->markTestSkipped('local_aiconfig is installed.');
        }
        $this->set_local('L-SITE', 'L-KEY');
        $this->assertFalse(credentials::status()['centralinstalled']);
        $this->assertSame('L-SITE', credentials::resolve()['siteid']);
    }
}
