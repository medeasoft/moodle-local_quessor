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
    const modal = document.getElementById("local-quessor-start-modal");
    if (!modal) {
        return;
    }

    const activitiesUrl = modal.dataset.activitiesUrl;
    const sesskey = modal.dataset.sesskey;

    const courseSelect = modal.querySelector("[data-searchable-select=\"course\"]");
    const assignmentSelect = modal.querySelector("[data-searchable-select=\"assignment\"]");
    const startButton = modal.querySelector("[data-action=\"start\"]");
    const emptyMessage = modal.querySelector("[data-region=\"empty-assignments\"]");

    const getFieldParts = (field) => ({
        input: field.querySelector("[data-role=\"value\"]"),
        trigger: field.querySelector("[data-action=\"toggle-select\"]"),
        label: field.querySelector("[data-role=\"selected-label\"]"),
        menu: field.querySelector("[data-role=\"menu\"]"),
        search: field.querySelector("[data-role=\"search\"]"),
        options: field.querySelector("[data-role=\"options\"]"),
        noResults: field.querySelector("[data-role=\"no-results\"]")
    });

    const closeMenus = () => {
        modal.querySelectorAll("[data-role=\"menu\"]").forEach((menu) => menu.classList.add("d-none"));
        modal.querySelectorAll("[data-action=\"toggle-select\"]").forEach((trigger) => trigger.setAttribute("aria-expanded", "false"));
    };

    const setFieldValue = (field, value, label) => {
        const parts = getFieldParts(field);
        parts.input.value = value;
        parts.label.textContent = label || parts.label.dataset.placeholder;
        parts.label.classList.toggle("is-placeholder", !value);
        parts.trigger.classList.toggle("has-value", Boolean(value));
        parts.options.querySelectorAll("[data-action=\"select-option\"]").forEach((option) => {
            option.setAttribute("aria-selected", option.dataset.value === value ? "true" : "false");
        });
    };

    const createOption = (field, value, label) => {
        const option = document.createElement("button");
        const labelElement = document.createElement("span");
        const checkElement = document.createElement("span");
        option.type = "button";
        option.className = "local-quessor-search-select__option";
        option.dataset.action = "select-option";
        option.dataset.value = String(value);
        option.setAttribute("role", "option");
        option.setAttribute("aria-selected", "false");
        labelElement.textContent = label;
        checkElement.className = "local-quessor-search-select__check";
        checkElement.setAttribute("aria-hidden", "true");
        checkElement.textContent = "✓";
        option.append(labelElement, checkElement);
        field.querySelector("[data-role=\"options\"]").append(option);
    };

    const filterOptions = (field) => {
        const parts = getFieldParts(field);
        const query = parts.search.value.trim().toLocaleLowerCase();
        let visibleCount = 0;
        parts.options.querySelectorAll("[data-action=\"select-option\"]").forEach((option) => {
            const visible = option.textContent.toLocaleLowerCase().includes(query);
            option.classList.toggle("d-none", !visible);
            if (visible) visibleCount++;
        });
        parts.noResults.classList.toggle("d-none", visibleCount > 0);
    };

    const openMenu = (field) => {
        if (getFieldParts(field).trigger.disabled) return;
        closeMenus();
        const parts = getFieldParts(field);
        parts.menu.classList.remove("d-none");
        parts.trigger.setAttribute("aria-expanded", "true");
        parts.search.value = "";
        filterOptions(field);
        window.setTimeout(() => parts.search.focus(), 0);
    };

    const updateCourseState = () => {
        populateAssignments();
    };

    const updateAssignmentState = () => {
        const assignmentParts = getFieldParts(assignmentSelect);
        const hasAssignment = Boolean(assignmentParts.input.value);
        startButton.disabled = !hasAssignment;
        assignmentParts.trigger.disabled = !courseSelect.querySelector("[data-role=\"value\"]").value;
    };

    const populateAssignments = async () => {
        const courseValue = getFieldParts(courseSelect).input.value;
        const assignmentParts = getFieldParts(assignmentSelect);
        assignmentParts.options.replaceChildren();
        setFieldValue(assignmentSelect, "", "");
        emptyMessage.classList.add("d-none");
        if (!courseValue) {
            assignmentParts.trigger.disabled = true;
            assignmentParts.search.disabled = true;
            updateAssignmentState();
            return;
        }
        assignmentParts.trigger.disabled = true;
        assignmentParts.search.disabled = true;
        try {
            const params = new URLSearchParams({
                action: "course_activities",
                courseid: courseValue,
                sesskey
            });
            const response = await fetch(activitiesUrl + "?" + params.toString(), { credentials: "same-origin" });
            const data = await response.json();
            const assignments = Array.isArray(data.activities) ? data.activities : [];
            assignments.forEach((assignment) => createOption(assignmentSelect, assignment.id, assignment.label));
            emptyMessage.classList.toggle("d-none", assignments.length > 0);
            assignmentParts.trigger.disabled = assignments.length === 0;
            assignmentParts.search.disabled = assignments.length === 0;
        } catch (error) {
            emptyMessage.classList.remove("d-none");
        }
        filterOptions(assignmentSelect);
        updateAssignmentState();
    };

    modal.querySelectorAll("[data-searchable-select]").forEach((field) => {
        const parts = getFieldParts(field);
        parts.trigger.addEventListener("click", () => {
            if (parts.menu.classList.contains("d-none")) openMenu(field);
            else closeMenus();
        });
        parts.search.addEventListener("input", () => filterOptions(field));
        parts.options.addEventListener("click", (event) => {
            const option = event.target.closest("[data-action=\"select-option\"]");
            if (!option) return;
            setFieldValue(field, option.dataset.value, option.firstElementChild.textContent);
            closeMenus();
            if (field === courseSelect) updateCourseState();
            else updateAssignmentState();
        });
        parts.trigger.addEventListener("keydown", (event) => {
            if (event.key === "ArrowDown" || event.key === "Enter" || event.key === " ") {
                event.preventDefault();
                openMenu(field);
            }
        });
        parts.search.addEventListener("keydown", (event) => {
            if (event.key === "Escape") {
                closeMenus();
                parts.trigger.focus();
            }
            if (event.key === "Enter") {
                const firstVisible = [...parts.options.querySelectorAll("[data-action=\"select-option\"]")]
                    .find((option) => !option.classList.contains("d-none"));
                if (firstVisible) firstVisible.click();
            }
        });
    });

    document.addEventListener("click", (event) => {
        if (!modal.contains(event.target)) return;
        if (!event.target.closest("[data-searchable-select]")) closeMenus();
    });

    modal.querySelector("form").addEventListener("submit", (event) => {
        if (!getFieldParts(courseSelect).input.value || !getFieldParts(assignmentSelect).input.value) event.preventDefault();
    });

    modal.addEventListener("show.bs.modal", () => {
        closeMenus();
        populateAssignments();
    });
    courseSelect.querySelector("[data-role=\"options\"]").querySelectorAll("[data-action=\"select-option\"]").forEach((option) => {
        option.setAttribute("aria-selected", "false");
    });
    updateAssignmentState();

};
