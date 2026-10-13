# Quessor AI Grading Plugin for Moodle (`local_quessor`)

Quessor is an AI-assisted evaluation platform for educators and educational institutions. This Moodle plugin connects your Moodle site seamlessly to Quessor, allowing teachers to evaluate student submissions (Assignments, Quiz attempts, Workshop submissions, and Forum discussions) with AI-generated feedback and rubric-based grading, while keeping full teacher oversight.

---

## Features

- **Multi-Activity Support**: Works with standard Moodle Assignments (online text, PDFs, images, Office documents), Quiz attempts, Workshop submissions, and Forum posts.
- **Embedded Evaluation Wizard**: Review extracted questions, configure assessment criteria/rubrics, and inspect student papers directly inside your Moodle course interface.
- **Teacher in the Loop**: Grades and feedback are previewed and confirmed by the teacher before being published to the Moodle Gradebook.
- **Automatic & Secure Handshake**: On installation, the plugin automatically creates a local 2048-bit RSA key pair to authenticate and sign all requests (`X-Moodle-Signature: RSA-SHA256`). No manual API secrets or credentials need to be copied into Moodle.
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
   - When an administrator visits the Quessor settings page or when a teacher opens an evaluation, Moodle generates a secure RSA key pair and establishes the link to your institution.

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
3. Review the assignment instructions, rubric criteria, and student submissions.
4. Click **Start Evaluation** to let Quessor analyze and propose grades.
5. Review the proposed scores and question-level feedback, make any desired manual adjustments, and click **Publish Grades** to commit them to the Moodle Gradebook.

---

## Privacy Policy (GDPR)

This plugin implements the Moodle core privacy subsystem (`core_privacy\local\metadata\provider`).
- Student submissions and teacher identifiers are transmitted securely over TLS to Quessor solely for evaluation and scoring.
- When an administrator processes an export or erasure request via Moodle's Data Privacy tool (**Site administration → Users → Privacy and policies**), records stored in `local_quessor_submission` are exported or deleted accordingly.

---

## License

This program is free software: you can redistribute it and/or modify it under the terms of the **GNU General Public License** as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.

See the [LICENSE](./LICENSE) file for more details.
