// This file is part of Moodle - http://moodle.org/.
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// @copyright 2026 Medeasoft
// @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

export const init = (iframeId, fallbackId, expectedOrigin) => {
    const frame = document.getElementById(iframeId);
    const fallback = document.getElementById(fallbackId);
    if (!frame) {
        return;
    }

    const showFallback = () => {
        fallback?.classList.remove('d-none');
        frame.classList.add('d-none');
    };

    window.addEventListener('message', (event) => {
        if (event.source !== frame.contentWindow || event.origin !== expectedOrigin) {
            return;
        }
        if (event.data?.type !== 'quessor:moodle-reauth' || frame.dataset.reauth === '1') {
            return;
        }
        frame.dataset.reauth = '1';
        window.location.reload();
    });

    document.addEventListener('securitypolicyviolation', (event) => {
        const directive = String(event.effectiveDirective || event.violatedDirective || '');
        if (!['frame-src', 'child-src', 'default-src'].some((part) => directive.includes(part))) {
            return;
        }
        if (String(event.blockedURI || '').startsWith(expectedOrigin)) {
            showFallback();
        }
    });
};
