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
 * Who may manage and import vocabulary by default.
 *
 * Sites running the LMS Labs Premium SaaS theme (theme_lmslabs) have a Site Owner role: there it is the
 * Site Owner who manages vocabulary, not the Manager. Other sites use the Manager role. Editing teachers
 * never get it by default. The defaults are applied once per case, so an administrator's later changes
 * under Site administration > Users > Permissions > Define roles are kept.
 *
 * @package    mod_vocabmastery
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class roles {
    /** The capability these defaults are about. */
    const CAPABILITY = 'mod/vocabmastery:managecontent';
    /** Short name of the Site Owner role created by the Premium SaaS theme. */
    const SITEOWNER = 'siteowner';
    /** Plugin config key recording which default has been applied. */
    const CONFIG = 'managecontentdefaults';

    /**
     * Remove the site-wide default allow from editing-teacher roles (upgrade to 1.2.0).
     * Course or activity overrides set by an administrator are kept.
     */
    public static function remove_teacher_default(): void {
        global $DB;
        $syscontext = \context_system::instance();
        foreach ($DB->get_records('role', ['archetype' => 'editingteacher'], '', 'id') as $role) {
            unassign_capability(self::CAPABILITY, $role->id, $syscontext->id);
        }
        $syscontext->mark_dirty();
    }

    /**
     * Apply the default for this site, once: Site Owner when the role exists, otherwise Manager.
     *
     * @return string the default now in place: siteowner, manager or '' when nothing changed
     */
    public static function apply_defaults(): string {
        global $DB;
        if (!get_capability_info(self::CAPABILITY)) {
            return '';
        }
        $applied = (string)get_config('mod_vocabmastery', self::CONFIG);
        $siteowner = $DB->get_record('role', ['shortname' => self::SITEOWNER]);
        $syscontext = \context_system::instance();
        if ($siteowner && $applied !== 'siteowner') {
            // Premium SaaS theme: the Site Owner manages vocabulary instead of the Manager.
            assign_capability(self::CAPABILITY, CAP_ALLOW, $siteowner->id, $syscontext->id, true);
            foreach ($DB->get_records('role', ['archetype' => 'manager'], '', 'id, shortname') as $role) {
                if ($role->shortname !== self::SITEOWNER) {
                    unassign_capability(self::CAPABILITY, $role->id, $syscontext->id);
                }
            }
            set_config(self::CONFIG, 'siteowner', 'mod_vocabmastery');
            $syscontext->mark_dirty();
            return 'siteowner';
        }
        if (!$siteowner && $applied === '') {
            // No Premium SaaS theme: Managers manage vocabulary (the plugin's own default).
            foreach ($DB->get_records('role', ['archetype' => 'manager'], '', 'id') as $role) {
                assign_capability(self::CAPABILITY, CAP_ALLOW, $role->id, $syscontext->id, false);
            }
            set_config(self::CONFIG, 'manager', 'mod_vocabmastery');
            $syscontext->mark_dirty();
            return 'manager';
        }
        return '';
    }
}
