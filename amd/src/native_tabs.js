// This file is part of Moodle - http://moodle.org/.
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// @copyright 2026 Medeasoft
// @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

export const init = () => {
    const root = document.querySelector('[data-native-evaluation]');
    if (!root) {
        return;
    }

    const tabs = root.querySelectorAll('[data-native-tab]');
    const panels = root.querySelectorAll('[data-native-panel]');
    const steps = root.querySelectorAll('[data-native-step]');
    const fill = root.querySelector('.local-quessor-native__progress-fill');
    const activate = (name) => root.querySelector(`[data-native-tab="${name}"]`)?.click();

    steps.forEach((step) => step.addEventListener('click', () => activate(step.dataset.nativeStep)));
    tabs.forEach((tab) => tab.addEventListener('click', () => {
        const name = tab.dataset.nativeTab;
        tabs.forEach((item) => {
            const active = item === tab;
            item.classList.toggle('is-active', active);
            item.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        steps.forEach((step) => {
            const active = step.dataset.nativeStep === name;
            step.classList.toggle('is-active', active);
            step.classList.toggle('is-complete', name === 'preview' && step.dataset.nativeStep === 'questions');
        });
        panels.forEach((panel) => {
            const active = panel.dataset.nativePanel === name;
            panel.classList.toggle('is-active', active);
            panel.hidden = !active;
        });
        if (fill) {
            fill.style.width = name === 'preview' ? '100%' : '0%';
        }
    }));
};
