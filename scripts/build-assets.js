#!/usr/bin/env node

'use strict';

var fs = require('fs');
var path = require('path');
var esbuild = require('esbuild');
var sass = require('sass');

function ensureDir(dirPath) {
    if (!fs.existsSync(dirPath)) {
        fs.mkdirSync(dirPath, { recursive: true });
    }
}

function removeFileIfExists(filePath) {
    if (fs.existsSync(filePath)) {
        fs.unlinkSync(filePath);
    }
}

function parseArgs(argv) {
    var mode = 'dev';
    var watch = false;
    var i;

    for (i = 2; i < argv.length; i++) {
        if (argv[i] === 'dev' || argv[i] === 'prod') {
            mode = argv[i];
        }
        if (argv[i] === '--watch') {
            watch = true;
        }
    }

    return { mode: mode, watch: watch };
}

function getBuildPaths() {
    var packageJson = JSON.parse(fs.readFileSync('package.json', 'utf8'));

    return {
        sourceJs: packageJson.source.js,
        sourceCss: packageJson.source.css,
        targetJs: packageJson.target.js,
        targetCss: packageJson.target.css
    };
}

function getEntryFiles(dirPath, extension) {
    return fs.readdirSync(dirPath, { withFileTypes: true })
        .filter(function filterEntry(entry) {
            return entry.isFile() && path.extname(entry.name) === extension && entry.name.charAt(0) !== '_';
        })
        .map(function mapEntry(entry) {
            return path.join(dirPath, entry.name);
        })
        .sort();
}

function buildCss(mode, paths) {
    var files = getEntryFiles(paths.sourceCss, '.scss');
    var isProd = mode === 'prod';
    var i;

    for (i = 0; i < files.length; i++) {
        var outFile = path.join(paths.targetCss, path.basename(files[i], '.scss') + '.css');
        var result = sass.compile(files[i], {
            style: isProd ? 'compressed' : 'expanded',
            sourceMap: !isProd,
            sourceMapIncludeSources: !isProd
        });

        fs.writeFileSync(outFile, result.css);

        if (isProd || !result.sourceMap) {
            removeFileIfExists(outFile + '.map');
        } else {
            fs.writeFileSync(outFile + '.map', JSON.stringify(result.sourceMap));
        }
    }
}

function getJsBuildOptions(mode, paths) {
    return {
        entryPoints: getEntryFiles(paths.sourceJs, '.js'),
        bundle: true,
        format: 'iife',
        minify: mode === 'prod',
        outdir: paths.targetJs,
        sourcemap: mode === 'prod' ? false : true,
        target: ['es2018']
    };
}

function removeProductionSourceMaps(paths) {
    var dirs = [paths.targetJs, paths.targetCss];
    var i;

    for (i = 0; i < dirs.length; i++) {
        getEntryFiles(dirs[i], '.map').forEach(function removeMap(filePath) {
            removeFileIfExists(filePath);
        });
    }
}

function buildOnce(mode, paths) {
    return esbuild.build(getJsBuildOptions(mode, paths)).then(function onJsBuilt() {
        buildCss(mode, paths);

        if (mode === 'prod') {
            removeProductionSourceMaps(paths);
        }
    });
}

function watchCss(paths) {
    fs.watch(paths.sourceCss, { recursive: true }, function onCssChanged() {
        buildCss('dev', paths);
    });
}

function watch(mode, paths) {
    return esbuild.context(getJsBuildOptions(mode, paths)).then(function onContextCreated(context) {
        return context.watch().then(function onWatchStarted() {
            watchCss(paths);
            console.log('Watch-Modus aktiv (JS + SCSS).');
        });
    });
}

function main() {
    var args = parseArgs(process.argv);
    var paths = getBuildPaths();

    ensureDir(paths.targetJs);
    ensureDir(paths.targetCss);

    if (args.watch) {
        return buildOnce(args.mode, paths).then(function onInitialBuildDone() {
            return watch(args.mode, paths);
        });
    }

    return buildOnce(args.mode, paths);
}

main().catch(function onBuildError(error) {
    console.error(error);
    process.exit(1);
});
