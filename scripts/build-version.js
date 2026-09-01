/* eslint-disable no-console */
'use strict';

var fs = require('fs');
var path = require('path');

function readJson(filePath) {
    var raw = fs.readFileSync(filePath, 'utf8');
    return JSON.parse(raw);
}

function writeJson(filePath, obj) {
    var out = JSON.stringify(obj, null, 2) + '\n';
    fs.writeFileSync(filePath, out, 'utf8');
}

function getString(obj, key, fallback) {
    if (!obj || typeof obj !== 'object' || typeof obj[key] !== 'string') {
        return fallback;
    }

    return obj[key].trim() || fallback;
}

function escapeRegExp(value) {
    return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

function parseSemver(version) {
    var m = version.match(/^(\d+)\.(\d+)\.(\d+)(?:-([0-9A-Za-z.-]+))?$/);
    if (!m) {
        throw new Error('Invalid semver: ' + version);
    }

    return {
        major: parseInt(m[1], 10),
        minor: parseInt(m[2], 10),
        patch: parseInt(m[3], 10),
        prerelease: m[4] || ''
    };
}

function formatSemver(v) {
    var base = String(v.major) + '.' + String(v.minor) + '.' + String(v.patch);
    if (v.prerelease) {
        return base + '-' + v.prerelease;
    }
    return base;
}

function bumpDev(version) {
    var v = parseSemver(version);

    if (!v.prerelease) {
        v.prerelease = '1';
        return formatSemver(v);
    }

    var m = v.prerelease.match(/^(\d+)$/);
    if (!m) {
        v.prerelease = '1';
        return formatSemver(v);
    }

    var n = parseInt(m[1], 10);
    v.prerelease = String(n + 1);

    return formatSemver(v);
}

function bumpProd(version) {
    var v = parseSemver(version);

    v.prerelease = '';
    v.patch = v.patch + 1;

    return formatSemver(v);
}

function bumpRelease(version) {
    var v = parseSemver(version);

    v.prerelease = '';
    v.minor = v.minor + 1;
    v.patch = 0;

    return formatSemver(v);
}

function replaceInFile(filePath, replacer) {
    var content = fs.readFileSync(filePath, 'utf8');
    var updated = replacer(content);

    if (updated !== content) {
        fs.writeFileSync(filePath, updated, 'utf8');
    }
}

function setReadmeTxtVersion(pluginRoot, newVersion) {
    var filePath = path.join(pluginRoot, 'readme.txt');
    var replacements = 0;

    if (!fs.existsSync(filePath)) {
        return;
    }

    replaceInFile(filePath, function replaceReadmeVersion(content) {
        content = content.replace(
            /^(Stable tag:\s*)(.+)$/m,
            function replaceStableTag(match, p1) {
                replacements++;
                return p1 + newVersion;
            }
        );

        content = content.replace(
            /^(Version:\s*)(.+)$/m,
            function replaceVersion(match, p1) {
                replacements++;
                return p1 + newVersion;
            }
        );

        return content;
    });

    if (replacements === 0) {
        throw new Error('No version field found in readme.txt');
    }
}

function setPluginVersion(pluginRoot, pkg, newVersion) {
    if (!pkg.main || typeof pkg.main !== 'string') {
        throw new Error('package.json has no valid "main" entry');
    }

    var filePath = path.join(pluginRoot, pkg.main);
    var replacements = 0;

    if (!fs.existsSync(filePath)) {
        throw new Error('Plugin main file not found: ' + filePath);
    }

    replaceInFile(filePath, function replacePluginVersion(content) {
        content = content.replace(
            /^(\s*(?:\*\s*)?Version:\s*)(.+)$/m,
            function replaceHeaderVersion(match, p1) {
                replacements++;
                return p1 + newVersion;
            }
        );

        return content;
    });

    if (replacements === 0) {
        throw new Error('No plugin header version field found in ' + pkg.main);
    }
}

function replacePluginHeaderValue(content, field, value) {
    var pattern = new RegExp(
        '^(\\s*(?:\\*\\s*)?' + escapeRegExp(field) + ':\\s*)(.+)$',
        'm'
    );

    return content.replace(pattern, function replacePluginHeaderField(match, prefix) {
        return prefix + value;
    });
}

function setPluginHeaderMetadata(pluginRoot, pkg) {
    var filePath;
    var repository;
    var author;
    var compatibility;
    var fields = [];

    if (!pkg.main || typeof pkg.main !== 'string') {
        throw new Error('package.json has no valid "main" entry');
    }

    filePath = path.join(pluginRoot, pkg.main);
    if (!fs.existsSync(filePath)) {
        throw new Error('Plugin main file not found: ' + filePath);
    }

    repository = pkg.repository && typeof pkg.repository === 'object' ? pkg.repository : {};
    author = pkg.author && typeof pkg.author === 'object' ? pkg.author : {};
    compatibility = pkg.compatibility && typeof pkg.compatibility === 'object' ? pkg.compatibility : {};

    fields = [
        ['Plugin Name', getString(pkg, 'title', '')],
        ['Plugin URI', getString(repository, 'url', '')],
        ['Description', getString(pkg, 'description', '')],
        ['Author', getString(author, 'name', '')],
        ['Author URI', getString(author, 'url', '')],
        ['License', getString(pkg, 'license', '')],
        ['License URI', getString(pkg, 'licenseurl', '')],
        ['Text Domain', getString(pkg, 'textdomain', '')],
        ['Requires at least', getString(compatibility, 'wprequires', '')],
        ['Requires PHP', getString(compatibility, 'phprequires', '')],
    ];

    replaceInFile(filePath, function replacePluginHeaderMetadata(content) {
        var updated = content;

        fields.forEach(function replaceHeaderField(field) {
            if (field[1] !== '') {
                updated = replacePluginHeaderValue(updated, field[0], field[1]);
            }
        });

        return updated;
    });
}

function setPluginCompatibility(pluginRoot, pkg) {
    if (!pkg.main || typeof pkg.main !== 'string') {
        throw new Error('package.json has no valid "main" entry');
    }

    var compatibility = pkg.compatibility;
    var filePath = path.join(pluginRoot, pkg.main);

    if (!compatibility || typeof compatibility !== 'object') {
        return;
    }

    if (!fs.existsSync(filePath)) {
        throw new Error('Plugin main file not found: ' + filePath);
    }

    replaceInFile(filePath, function replacePluginCompatibility(content) {
        var updated = content;

        if (typeof compatibility.phprequires === 'string' && compatibility.phprequires.trim() !== '') {
            updated = updated.replace(
                /^(\s*(?:\*\s*)?Requires PHP:\s*)(.+)$/m,
                function replacePhpRequirement(match, p1) {
                    return p1 + compatibility.phprequires.trim();
                }
            );

            updated = updated.replace(
                /const\s+RRZE_PHP_VERSION\s*=\s*['"][^'"]*['"]\s*;/,
                function replacePhpConstant() {
                    return "const RRZE_PHP_VERSION = '" + compatibility.phprequires.trim() + "';";
                }
            );
        }

        if (typeof compatibility.wprequires === 'string' && compatibility.wprequires.trim() !== '') {
            updated = updated.replace(
                /^(\s*(?:\*\s*)?Requires at least:\s*)(.+)$/m,
                function replaceWpRequirement(match, p1) {
                    return p1 + compatibility.wprequires.trim();
                }
            );

            updated = updated.replace(
                /const\s+RRZE_WP_VERSION\s*=\s*['"][^'"]*['"]\s*;/,
                function replaceWpConstant() {
                    return "const RRZE_WP_VERSION = '" + compatibility.wprequires.trim() + "';";
                }
            );
        }

        return updated;
    });
}

function setPackageLockVersion(pluginRoot, newVersion) {
    var filePath = path.join(pluginRoot, 'package-lock.json');
    var lock;

    if (!fs.existsSync(filePath)) {
        return;
    }

    lock = readJson(filePath);
    lock.version = newVersion;

    if (lock.packages && lock.packages['']) {
        lock.packages[''].version = newVersion;
    }

    writeJson(filePath, lock);
}

function getNextVersion(mode, currentVersion) {
    if (mode === 'dev') {
        return bumpDev(currentVersion);
    }

    if (mode === 'prod') {
        return bumpProd(currentVersion);
    }

    if (mode === 'release') {
        return bumpRelease(currentVersion);
    }

    throw new Error('Unsupported mode: ' + mode);
}

function main() {
    var mode = process.argv[2];
    if (mode !== 'dev' && mode !== 'prod' && mode !== 'release') {
        console.error('Usage: node scripts/build-version.js dev|prod|release');
        process.exit(1);
    }

    var pluginRoot = process.cwd();
    var packagePath = path.join(pluginRoot, 'package.json');
    var pkg = readJson(packagePath);

    if (!pkg.version || typeof pkg.version !== 'string') {
        throw new Error('package.json has no valid version');
    }

    var current = pkg.version;
    var next = getNextVersion(mode, current);

    pkg.version = next;
    writeJson(packagePath, pkg);

    setReadmeTxtVersion(pluginRoot, next);
    setPluginVersion(pluginRoot, pkg, next);
    setPluginHeaderMetadata(pluginRoot, pkg);
    setPluginCompatibility(pluginRoot, pkg);
    setPackageLockVersion(pluginRoot, next);

    console.log('Version bumped (' + mode + '): ' + current + ' -> ' + next);
}

main();
