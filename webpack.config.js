const defaultConfig = require("@wordpress/scripts/config/webpack.config");
const { basename, dirname, resolve } = require("path");
const srcDir = "src";

const admin = resolve(process.cwd(), "src", "admin");
const blockeditor = resolve(process.cwd(), "src", "blockeditor");

module.exports = {
    ...defaultConfig,
    entry: {
        admin,
        blockeditor,
    },
    output: {
        path: resolve(process.cwd(), "build"),
        filename: "[name].js",
        clean: true,
    },
    optimization: {
        ...defaultConfig.optimization,
        splitChunks: {
            cacheGroups: {
                style: {
                    type: "css/mini-extract",
                    test: /[\\/]style(\.module)?\.(pc|sc|sa|c)ss$/,
                    chunks: "all",
                    enforce: true,
                    name(_, chunks, cacheGroupKey) {
                        const chunkName = chunks[0].name;
                        return `${dirname(chunkName)}/${basename(
                            chunkName
                        )}.${cacheGroupKey}`;
                    },
                },
                default: false,
            },
        },
    },
};
