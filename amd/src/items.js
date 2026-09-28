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
 * Bulk item table helpers: filter, select all, add rows, bulk delete confirmation.
 *
 * @module     mod_vocabmastery/items
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Notification from 'core/notification';
import {getStrings} from 'core/str';

/**
 * Initialise the item editor.
 */
export const init = () => {
    const form = document.getElementById('vm-items-form');
    if (!form) {
        return;
    }
    const table = document.getElementById('vm-items-table');
    const filter = document.getElementById('vm-items-filter');
    const selectAll = document.getElementById('vm-selectall');
    const bulk = document.getElementById('vm-bulk');
    const target = document.getElementById('vm-targetset');
    const addBtn = document.getElementById('vm-addrow');
    let newIndex = table.querySelectorAll('tr.vm-newrow').length;

    if (filter) {
        filter.addEventListener('input', () => {
            const q = filter.value.trim().toLowerCase();
            table.querySelectorAll('tbody tr[data-search]').forEach((tr) => {
                tr.hidden = q !== '' && !tr.dataset.search.includes(q);
            });
        });
    }
    if (selectAll) {
        selectAll.addEventListener('change', () => {
            table.querySelectorAll('tbody tr:not([hidden]) input.vm-sel').forEach((cb) => {
                cb.checked = selectAll.checked;
            });
        });
    }
    if (bulk && target) {
        bulk.addEventListener('change', () => {
            target.classList.toggle('d-none', bulk.value !== 'move');
        });
    }
    if (addBtn) {
        addBtn.addEventListener('click', () => {
            const rows = table.querySelectorAll('tr.vm-newrow');
            const template = rows[rows.length - 1];
            const tbody = table.querySelector('tbody');
            let first = null;
            for (let i = 0; i < 5; i++) {
                const clone = template.cloneNode(true);
                const prefix = `new[${newIndex}]`;
                clone.querySelectorAll('input').forEach((input) => {
                    input.name = input.name.replace(/^new\[\d+\]/, prefix);
                    if (input.type === 'text') {
                        input.value = '';
                    } else {
                        input.checked = true;
                    }
                });
                newIndex++;
                tbody.appendChild(clone);
                first = first || clone;
            }
            const focus = first.querySelector('input[type=text]');
            if (focus) {
                focus.focus();
            }
        });
    }
    // Enter in the last new row's last text field adds more rows instead of submitting.
    table.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && e.target.matches('tr.vm-newrow:last-child input.vm-f-hint')) {
            e.preventDefault();
            addBtn.click();
        }
    });
    form.addEventListener('submit', (e) => {
        if (!bulk || bulk.value !== 'delete' || form.dataset.confirmed) {
            return;
        }
        const count = form.querySelectorAll('input.vm-sel:checked').length;
        if (!count) {
            return;
        }
        e.preventDefault();
        getStrings([
            {key: 'confirm', component: 'core'},
            {key: 'confirmdeleteitems', component: 'mod_vocabmastery', param: count},
            {key: 'delete', component: 'core'},
            {key: 'cancel', component: 'core'},
        ]).then(([title, question, yes, no]) => {
            Notification.confirm(title, question, yes, no, () => {
                form.dataset.confirmed = '1';
                form.submit();
            });
            return null;
        }).catch(Notification.exception);
    });
};
