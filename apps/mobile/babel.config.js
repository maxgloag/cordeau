module.exports = function (api) {
  api.cache.using(() => JSON.stringify(require("./tailwind.config.js")));
  return {
    presets: [
      ["babel-preset-expo", { jsxImportSource: "nativewind" }],
      "nativewind/babel",
    ],
    plugins: [],
  };
};
