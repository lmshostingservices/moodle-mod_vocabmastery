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

/**
 * Administrator-only LMS Labs activation page: access check, live price and one-time unlock.
 *
 * Activation status is informational: it does not restrict learners or any feature.
 *
 * @package    mod_vocabmastery
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use mod_vocabmastery\local\activation;

require_login(null, false);
admin_externalpage_setup('mod_vocabmastery_activation');
require_capability('moodle/site:config', context_system::instance());

$c = 'mod_vocabmastery';
$url = new moodle_url('/mod/vocabmastery/activation.php');
$action = optional_param('action', '', PARAM_ALPHA);
$service = new activation();

if ($action !== '') {
    if (!data_submitted()) {
        throw new moodle_exception('invalidrequest', 'error');
    }
    require_sesskey();
    switch ($action) {
        case 'check':
            [$msg, $type] = activation::message($service->check());
            redirect($url, $msg, null, $type);
            break;
        case 'cancel':
            [$msg, $type] = activation::message(['outcome' => 'cancelled']);
            redirect($url, $msg, null, $type);
            break;
        case 'review':
            if (activation::get_state()['pending']) {
                [$msg, $type] = activation::message(['outcome' => 'checkfirst']);
                redirect($url, $msg, null, $type);
            }
            // A free access check first: never offer a purchase to a site that is already unlocked.
            $check = $service->check();
            if ($check['outcome'] !== 'locked') {
                $check['outcome'] = $check['outcome'] === 'unlocked' ? 'alreadyunlocked' : $check['outcome'];
                [$msg, $type] = activation::message($check);
                redirect($url, $msg, null, $type);
            }
            $live = $service->manifest();
            if (!$live['ok']) {
                [$msg, $type] = activation::message(['outcome' => $live['reason']]);
                redirect($url, $msg, null, $type);
            }
            $state = activation::get_state();
            // A low balance may still be a paid Marketplace or LMS Labs purchase restored at zero credits.
            $lowbalance = $state['credits'] !== '' && $state['credits'] !== 'unlimited'
                && (int)$state['credits'] < $live['credits'];
            echo $OUTPUT->header();
            echo $OUTPUT->render_from_template('mod_vocabmastery/activation_confirm', [
                'actionurl' => $url->out(false),
                'sesskey' => sesskey(),
                'credits' => $live['credits'],
                'free' => $live['credits'] === 0,
                'releasesha' => $live['sha256'],
                'releaseshort' => substr($live['sha256'], 0, 12),
                'balance' => $state['credits'] === 'unlimited' ? get_string('act_unlimited', $c) : $state['credits'],
                'hasbalance' => $state['credits'] !== '',
                'lowbalance' => $lowbalance,
            ]);
            echo $OUTPUT->footer();
            die;
        case 'unlock':
            if (!optional_param('confirmspend', 0, PARAM_BOOL)) {
                redirect($url, get_string('act_result_notconfirmed', $c), null, \core\output\notification::NOTIFY_WARNING);
            }
            $sha = required_param('releasesha', PARAM_ALPHANUM);
            $credits = required_param('expectedcredits', PARAM_INT);
            [$msg, $type] = activation::message($service->unlock($sha, $credits));
            redirect($url, $msg, null, $type);
            break;
        default:
            throw new moodle_exception('invalidparameter', 'debug');
    }
}

// Page view: stored state plus the live price (a free, read-only request).
$status = \mod_vocabmastery\local\credentials::status();
$state = activation::get_state();
$live = $service->manifest();
$statelabels = [
    activation::STATE_NOT_CHECKED => 'act_state_notchecked',
    activation::STATE_LOCKED => 'act_state_locked',
    activation::STATE_UNLOCKED => 'act_state_unlocked',
    activation::STATE_UNVERIFIED => 'act_state_unverified',
];
$hascreds = $status['source'] !== \mod_vocabmastery\local\credentials::SOURCE_NONE;
$centralurl = null;
if ($status['centralinstalled']) {
    $centralurl = (new moodle_url('/admin/settings.php', ['section' => 'local_aiconfig']))->out(false);
}
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('mod_vocabmastery/activation', [
    'actionurl' => $url->out(false),
    'sesskey' => sesskey(),
    'sourcecentral' => $status['source'] === \mod_vocabmastery\local\credentials::SOURCE_CENTRAL,
    'sourcelocal' => $status['source'] === \mod_vocabmastery\local\credentials::SOURCE_LOCAL,
    'sourcenone' => !$hascreds,
    'siteid' => $status['siteid'],
    'centralurl' => $centralurl,
    'localurl' => (new moodle_url('/admin/settings.php', ['section' => 'modsettingvocabmastery']))->out(false),
    'statelabel' => get_string($statelabels[$state['state']] ?? 'act_state_notchecked', $c),
    'stateclass' => [
        activation::STATE_UNLOCKED => 'bg-success text-white',
        activation::STATE_LOCKED => 'bg-warning text-dark',
        activation::STATE_UNVERIFIED => 'bg-danger text-white',
    ][$state['state']] ?? 'bg-secondary text-white',
    'checked' => $state['checked'] ? userdate($state['checked']) : '',
    'unlockedat' => $state['unlockedat'],
    'entitlementsource' => $state['source'],
    'balance' => $state['credits'] === 'unlimited' ? get_string('act_unlimited', $c) : $state['credits'],
    'hasbalance' => $state['credits'] !== '',
    'pending' => $state['pending'],
    'haslive' => $live && $live['ok'],
    'price' => $live['credits'] ?? '',
    'pricefree' => ($live['credits'] ?? null) === 0,
    'releaseshort' => $live ? substr($live['sha256'], 0, 12) : '',
    'livereason' => $live && !$live['ok'] ? get_string('act_result_' . $live['reason'], $c) : '',
    'cancheck' => $hascreds,
    'canunlock' => $hascreds && !$state['pending'] && $state['state'] !== activation::STATE_UNLOCKED && $live && $live['ok'],
    'isunlocked' => $state['state'] === activation::STATE_UNLOCKED,
]);
echo $OUTPUT->footer();
