// This file is part of Moodle - http://moodle.org/.
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// @copyright 2026 Medeasoft
// @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

import {call} from 'core/ajax';

export const init = () => {
    window.localQuessorExternalCall = (action, args) => call([{
        methodname: 'local_quessor_native_request',
        args: {action, ...args},
    }])[0];
    window.dispatchEvent(new Event('local-quessor:external-ready'));
};
