const defaultConfig = require("@wordpress/scripts/config/webpack.config");
const path = require("path");

module.exports = {
    ...defaultConfig,
    output: {
        ...defaultConfig.output,
        path: path.resolve(__dirname, "build"),
    },
    entry: {
        "rrze-autoshare-bluesky": "./src/bluesky/index.js",
        "rrze-autoshare-twitter": "./src/twitter/index.js",
    },
};
