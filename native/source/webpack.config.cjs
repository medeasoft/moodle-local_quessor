const path = require("node:path");
const webpack = require("webpack");

const source = __dirname;
const plugin = path.resolve(source, "../..");
const evaluationUi = path.join(source, "packages/evaluation-ui/src");
const shared = path.join(source, "packages/shared/src");

module.exports = {
  mode: "production",
  target: ["web", "es2017"],
  entry: path.join(plugin, "native-entry.tsx"),
  output: {
    path: path.join(plugin, "native"),
    filename: "evaluation-ui.js",
  },
  resolve: {
    extensions: [".tsx", ".ts", ".js"],
    modules: [path.join(source, "node_modules"), "node_modules"],
    alias: {
      "@quessor/evaluation-ui": evaluationUi,
      "@fullstack/shared$": path.join(shared, "browser.ts"),
      "@fullstack/shared/types$": path.join(shared, "types/index.ts"),
      "@fullstack/shared/types/page.dto$": path.join(shared, "types/page.dto.ts"),
    },
  },
  resolveLoader: {
    modules: [path.join(source, "node_modules"), "node_modules"],
  },
  module: {
    rules: [
      {
        test: /\.tsx?$/,
        exclude: /node_modules/,
        use: {
          loader: "ts-loader",
          options: { transpileOnly: true, configFile: path.join(source, "tsconfig.json") },
        },
      },
    ],
  },
  optimization: { minimize: true },
  plugins: [
    new webpack.DefinePlugin({
      "process.env.NODE_ENV": JSON.stringify("production"),
    }),
  ],
};
