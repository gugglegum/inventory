const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

const source = fs.readFileSync(path.join(__dirname, '../../backend/web/js/theme.js'), 'utf8');

function setup(saved = null, dark = false, blocked = false) {
    const storage = new Map(saved === null ? [] : [['stockhub.theme', saved]]);
    const events = {};
    const select = {id: 'theme-select', value: 'auto'};
    const root = {setAttribute(name, value) { this[name] = value; }};
    const system = {
        matches: dark,
        addEventListener(name, callback) { events.system = callback; },
    };
    vm.runInNewContext(source, {
        window: {
            matchMedia: () => system,
            localStorage: {
                getItem(key) {
                    if (blocked) throw new Error('Storage unavailable');
                    return storage.get(key) ?? null;
                },
                setItem(key, value) {
                    if (blocked) throw new Error('Storage unavailable');
                    storage.set(key, value);
                },
                removeItem(key) {
                    if (blocked) throw new Error('Storage unavailable');
                    storage.delete(key);
                },
            },
            addEventListener(name, callback) { events[name] = callback; },
        },
        document: {
            documentElement: root,
            getElementById: () => select,
            addEventListener(name, callback) { events[name] = callback; },
        },
    });
    return {
        storage, events, select, root, system,
        change(value) {
            select.value = value;
            events.change({target: select});
        },
    };
}

test('auto follows system changes without storing a default', () => {
    const app = setup(null, true);
    assert.equal(app.root['data-bs-theme'], 'dark');
    assert.equal(app.select.value, 'auto');
    app.system.matches = false;
    app.events.system();
    assert.equal(app.root['data-bs-theme'], 'light');
    assert.equal(app.storage.size, 0);
});

test('explicit themes persist and auto removes the key', () => {
    const app = setup();
    for (const theme of ['dark', 'light']) {
        app.change(theme);
        assert.equal(app.storage.get('stockhub.theme'), theme);
        assert.equal(setup(theme).root['data-bs-theme'], theme);
    }
    app.system.matches = true;
    app.events.system();
    assert.equal(app.root['data-bs-theme'], 'light');
    app.change('auto');
    assert.equal(app.storage.has('stockhub.theme'), false);
    assert.equal(app.root['data-bs-theme'], 'dark');
});

test('cross-tab updates and storage clearing update the page and select', () => {
    const app = setup();
    app.events.storage({key: 'stockhub.theme', newValue: 'dark'});
    assert.equal(app.root['data-bs-theme'], 'dark');
    assert.equal(app.select.value, 'dark');
    app.events.storage({key: 'unrelated', newValue: 'light'});
    assert.equal(app.root['data-bs-theme'], 'dark');
    app.events.storage({key: 'stockhub.theme', newValue: null});
    assert.equal(app.select.value, 'auto');
    assert.equal(app.root['data-bs-theme'], 'light');
    app.change('dark');
    app.events.storage({key: null, newValue: null});
    assert.equal(app.select.value, 'auto');
});

test('invalid settings and unavailable storage preserve working selection', () => {
    assert.equal(setup('invalid', true).root['data-bs-theme'], 'dark');
    const app = setup(null, false, true);
    app.change('dark');
    assert.equal(app.root['data-bs-theme'], 'dark');
    app.change('auto');
    assert.equal(app.root['data-bs-theme'], 'light');
});
