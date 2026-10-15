const fs = require("node:fs");
const path = require("node:path");

const source = path.join(__dirname, "packages/evaluation-ui/src/styles.css");
const destination = path.resolve(__dirname, "../../native/evaluation-ui.css");
fs.copyFileSync(source, destination);
process.stdout.write(`Copied ${path.relative(process.cwd(), destination)}\n`);
