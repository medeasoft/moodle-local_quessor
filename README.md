# Quessor AI Grading Plugin for Moodle (`local_quessor`)

Quessor is an AI-assisted evaluation platform for educators and educational institutions. This Moodle plugin connects your Moodle site seamlessly to Quessor, allowing teachers to evaluate student submissions (Assignments, Quiz attempts, Workshop submissions, and Forum discussions) with AI-generated feedback and rubric-based grading, while keeping full teacher oversight.

---

## Features

- **Multi-Activity Support**: Works with standard Moodle Assignments (online text, PDFs, images, Office documents), Quiz attempts, Workshop submissions, and Forum posts.
- **Embedded Evaluation Wizard**: Review extracted questions, configure assessment criteria/rubrics, and inspect student papers directly inside your Moodle course interface.
- **Teacher in the Loop**: Grades and feedback are previewed and confirmed by the teacher before being published to the Moodle Gradebook.
- **Administrator-Controlled Connection**: No network registration or student data transfer occurs until a site administrator enables Quessor in the plugin settings. A teacher must then connect each activity before it is used.
- **Secure Handshake**: The plugin creates an RSA key pair when the administrator enables the integration and signs requests (`X-Moodle-Signature: RSA-SHA256`). No manual API secrets or credentials need to be copied into Moodle.
- **Privacy & GDPR Compliant**: Implements Moodle's official Privacy API (`core_privacy`) for metadata declaration, data export, and user data deletion requests.

---

## Requirements

- **Moodle**: 4.1 LTS or later (4.1, 4.2, 4.3, 4.4, 4.5+).
- **PHP**: 8.1 or higher with the `openssl` and `curl` extensions enabled.
- **Moodle Cron**: Standard Moodle background cron enabled and running regularly.

---

## Installation

### Method 1: Via Moodle Web Interface (Recommended)
1. Download the release package (`local_quessor.zip` or `quessor-moodle.zip`).
2. Log in as an administrator on your Moodle site.
3. Navigate to **Site administration → Plugins → Install plugins**.
4. Upload the ZIP package and click **Install plugin from the ZIP file**.
5. Follow the on-screen prompts and visit **Site administration → Notifications** to complete the database installation.

### Method 2: Via Git
1. Navigate to the root directory of your Moodle installation.
2. Clone this repository into the `local/quessor` folder:
   ```bash
   git clone https://github.com/medeasoft/moodle-local_quessor.git local/quessor
   ```
   *(Note: The folder inside `local/` must be named `quessor`.)*
3. Visit **Site administration → Notifications** or run the CLI upgrade command:
   ```bash
   php admin/cli/upgrade.php
   ```

---

## Configuration

1. **Automatic Initialization**:
   - The plugin communicates with `https://api.quessor.com`. No manual API keys are required.
   - Enable **Quessor integration** and save the settings. This explicit administrator action registers the site and creates its signing key. Before this opt-in, installing the plugin or visiting its pages sends no data.

2. **Institution Contact Email**:
   - Go to **Site administration → Plugins → Local plugins → Quessor** (or the Quessor tab in Site administration).
   - Enter your institution's contact / billing email address.

3. **Content Security Policy (CSP)**:
   - If your Moodle server enforces a strict Content Security Policy, ensure `https://quessor.com` is permitted for framing if using embedded views:
     ```text
     frame-src 'self' https://quessor.com;
     ```

---

## Usage for Teachers

1. Open any supported course activity (e.g. an Assignment or Quiz).
2. Click the **Quessor** tab in the course activity navigation.
3. On first use, choose **Connect and continue** to enable Quessor for that activity. Later student submissions for a connected activity are synchronized automatically; existing submissions are queued when the teacher launches evaluation.
4. Click **Start Evaluation** to let Quessor analyze and propose grades.
5. Review the proposed scores and question-level feedback, make any desired manual adjustments, and click **Publish Grades** to commit them to the Moodle Gradebook.

---

## Privacy Policy (GDPR)

This plugin implements the Moodle core privacy subsystem (`core_privacy\local\metadata\provider`).
- After an administrator enables the integration, site name and the configured institution contact email are sent to Quessor for registration.
- After a teacher connects an activity, the teacher's Moodle ID, name, and email, the activity/course context, and the connected course's visible learner roster may be sent to Quessor. Submission text and converted or attached files are synchronized for connected activities. These transfers stop when the administrator disables the integration.
- When an administrator processes an export or erasure request via Moodle's Data Privacy tool (**Site administration → Users → Privacy and policies**), records stored in `local_quessor_submission` are exported or deleted accordingly.

---

## License

This program is free software: you can redistribute it and/or modify it under the terms of the **GNU General Public License** as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.

See the [LICENSE](./LICENSE) file for more details.

## Native evaluation UI source

The native wizard source and standalone build are included in this repository and plugin archive. From the plugin root, run `cd native/source && npm ci && npm run build` to regenerate `native/evaluation-ui.js` and `native/evaluation-ui.css`; the locked build toolchain and all first-party sources needed by the build are included there. The Quessor monorepo maintainers can refresh the vendored sources and generated bundle with `npm run build:moodle-native` from the monorepo root. Build the Moodle AMD modules from the Moodle root with `npx grunt amd --component=local_quessor`. Bundled library versions and licences are listed in `thirdpartylibs.xml`.

Native JSON requests use the Moodle External Service `local_quessor_native_request` through `core/ajax`. The `ajax.php` endpoint is limited to multipart uploads because Moodle's External Services transport does not carry browser `FormData` uploads.

The RSA signing key is stored in Moodle configuration and is retained across plugin upgrades. Uninstalling the plugin removes the retained key through `db/uninstall.php`; reinstalling creates a new Quessor site identity on the next administrator opt-in.

## Screenshots and support

For current UI screenshots, use representative Moodle pages from a configured test site when preparing the Moodle Plugins directory listing. Report issues at <https://github.com/medeasoft/moodle-local_quessor/issues>.
