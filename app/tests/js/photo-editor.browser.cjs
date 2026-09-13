const assert = require('node:assert/strict');
const path = require('node:path');
const {test, before, after} = require('node:test');
const {chromium} = require('playwright');

const app = path.resolve(__dirname, '../..');
const png = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aD1sAAAAASUVORK5CYII=', 'base64');
let browser;

before(async () => {
    browser = await chromium.launch({
        headless: true,
        executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH || undefined,
    });
});
after(async () => { await browser?.close(); });

async function until(predicate) {
    for (let attempt = 0; attempt < 200; attempt++) {
        if (await predicate()) return;
        await new Promise(resolve => setTimeout(resolve, 25));
    }
    assert.fail('Timed out waiting for browser state');
}

async function setup(t, {editors = 1, session = true, mobile = false} = {}) {
    const page = await browser.newPage({viewport: mobile ? {width: 390, height: 844} : {width: 1280, height: 900}});
    t.after(() => page.close());
    const uploads = [];
    const deletes = [];
    const sessions = [];
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    t.after(() => assert.deepEqual(errors, []));
    const editorHtml = Array.from({length: editors}, (_, index) => `
        <div data-photo-editor id="editor-${index}" data-create-session-url="/session"
            data-upload-url="${session ? '/upload' : ''}" data-repo-id="1" data-upload-context="item-update">
            <input type="hidden" name="manifest-${index}" data-photo-editor-manifest>
            <input type="hidden" name="token-${index}" data-photo-editor-token>
            <div data-photo-editor-message hidden></div>
            <div class="photo-editor__cards" data-photo-editor-list>
                <div data-photo-editor-drop-slot><div data-photo-editor-droparea>
                    <input type="file" multiple accept="image/png" data-photo-editor-input>
                </div></div>
            </div>
            <p data-photo-editor-empty>Фотографии пока не добавлены.</p>
        </div>`).join('');
    await page.route('https://stockhub.test/**', async route => {
        const url = new URL(route.request().url());
        if (url.pathname === '/upload') uploads.push(route);
        else if (url.pathname === '/delete') deletes.push(route);
        else if (url.pathname === '/session') sessions.push(route);
        else if (url.pathname.endsWith('.woff2')) await route.fulfill({
            path: path.join(app, 'vendor/twbs/bootstrap-icons/font/fonts/bootstrap-icons.woff2'),
            contentType: 'font/woff2',
        });
        else if (url.pathname === '/form') await route.fulfill({contentType: 'text/html', body: `
            <!doctype html><html lang="ru"><meta charset="UTF-8"><body>
            <form id="item-form" style="padding:20px">
                <div id="name-field"><input id="name" name="name" value="Предмет"><div class="invalid-feedback"></div></div>
                <button id="top" type="submit" name="save" value="top" class="btn btn-primary" title="Сохранить предмет">Сохранить</button>
                ${editorHtml}
                <button id="bottom" type="submit" name="save" value="bottom" class="btn btn-primary">Сохранить</button>
                <button id="disabled" type="submit" disabled>Недоступно</button>
            </form></body></html>`});
        else await route.fulfill({contentType: 'image/png', body: png});
    });
    await page.goto('https://stockhub.test/form');
    for (const script of ['vendor/bower-asset/jquery/dist/jquery.js', 'vendor/yiisoft/yii2/assets/yii.js', 'vendor/yiisoft/yii2/assets/yii.activeForm.js']) {
        await page.addScriptTag({path: path.join(app, script)});
    }
    for (const css of ['vendor/twbs/bootstrap/dist/css/bootstrap.css', 'vendor/twbs/bootstrap-icons/font/bootstrap-icons.css', 'backend/web/css/photo-editor.css']) {
        await page.addStyleTag({path: path.join(app, css)});
    }
    await page.evaluate(() => {
        window.saved = [];
        const form = document.querySelector('form');
        // Replace only final transport; exercise the real native click and Yii validation.
        form.submit = function() { window.saved.push(Object.fromEntries(new FormData(form))); };
        window.jQuery(form).yiiActiveForm([{
            id: 'name', name: 'name', container: '#name-field', input: '#name', error: '.invalid-feedback',
            validate(attribute, value, messages) { if (!value) messages.push('Укажите название.'); },
        }], {scrollToError: false});
    });
    await page.addScriptTag({path: path.join(app, 'backend/web/js/photo-editor.js')});
    return {
        page, uploads, deletes, sessions,
        async add(names, index = 0) {
            await page.locator(`#editor-${index} input[type=file]`).setInputFiles(names.map(name => ({name, mimeType: 'image/png', buffer: png})));
        },
        async ready(id, route = uploads.shift()) {
            assert.ok(route, 'Expected a pending upload');
            await route.fulfill({json: {id, thumbnail_url: '/image', preview_url: '/image', delete_url: '/delete'}});
            await until(() => page.locator(`[data-entry-id="${id}"][data-status="ready"]`).count());
        },
        async saved() { return page.evaluate(() => window.saved); },
    };
}

test('waits for the entire queue, shows both spinners, and submits the ordered manifest once', async t => {
    const ui = await setup(t);
    await ui.add(['4.png', '2.png', '1.png', '3.png']);
    await until(() => ui.uploads.length === 3);
    assert.equal(await ui.page.locator('#top').isEnabled(), true);
    await ui.page.locator('#bottom').click();
    assert.equal(await ui.page.locator('button[aria-busy=true] .spinner-border').count(), 2);
    assert.equal(await ui.page.locator('#disabled').isDisabled(), true);
    await ui.ready(2, ui.uploads.splice(1, 1)[0]);
    await until(() => ui.uploads.length === 3);
    await ui.ready(1);
    await ui.ready(3);
    assert.deepEqual(await ui.saved(), []);
    await ui.ready(4);
    await until(async () => (await ui.saved()).length === 1);
    const [saved] = await ui.saved();
    assert.deepEqual(JSON.parse(saved['manifest-0']), [1, 2, 3, 4].map(id => ({type: 'temporary', id})));
    assert.equal(saved.save, 'bottom');
    assert.equal(await ui.page.locator('.spinner-border').count(), 0);
    assert.equal(await ui.page.locator('#top').getAttribute('title'), 'Сохранить предмет');
    assert.equal(await ui.page.locator('#bottom').getAttribute('aria-busy'), null);
});

test('either button cancels waiting without cancelling uploads, and waiting can be enabled again', async t => {
    const ui = await setup(t, {mobile: true});
    await ui.add(['1.png', '2.png']);
    await until(() => ui.uploads.length === 2);
    await ui.page.locator('#top').click();
    await ui.page.locator('#bottom .spinner-border').click();
    assert.equal(await ui.page.locator('.spinner-border').count(), 0);
    await ui.ready(1);
    assert.deepEqual(await ui.saved(), []);
    await ui.page.locator('#bottom').click();
    await ui.ready(2);
    await until(async () => (await ui.saved()).length === 1);
});

test('cancelled waiting never submits after the last upload finishes', async t => {
    const ui = await setup(t);
    await ui.add(['1.png']);
    await until(() => ui.uploads.length === 1);
    await ui.page.locator('#top').click();
    await ui.page.locator('#top').click();
    await ui.ready(1);
    await ui.page.evaluate(() => new Promise(resolve => setTimeout(resolve, 30)));
    assert.deepEqual(await ui.saved(), []);
    await ui.page.locator('#top').click();
    await until(async () => (await ui.saved()).length === 1);
});

test('upload errors cancel waiting; retry requires a new save request', async t => {
    const ui = await setup(t);
    await ui.add(['1.png']);
    await until(() => ui.uploads.length === 1);
    await ui.page.locator('#top').click();
    await ui.uploads.shift().fulfill({status: 500, json: {message: 'Ошибка загрузки'}});
    await ui.page.locator('[data-status=error]').waitFor();
    assert.equal(await ui.page.locator('.spinner-border').count(), 0);
    assert.match(await ui.page.locator('[data-photo-editor-message]').textContent(), /Повторите загрузку/);
    await ui.page.locator('#top').click();
    assert.equal(await ui.page.locator('.spinner-border').count(), 0);
    await ui.page.locator('[data-photo-editor-retry]').click();
    await until(() => ui.uploads.length === 1);
    await ui.ready(1);
    assert.deepEqual(await ui.saved(), []);
    await ui.page.locator('#top').click();
    await until(async () => (await ui.saved()).length === 1);
});

test('files added while waiting are included, and lazy session creation is awaited', async t => {
    const ui = await setup(t, {session: false});
    await ui.add(['1.png']);
    await until(() => ui.sessions.length === 1);
    await ui.page.locator('#top').click();
    await ui.add(['2.png']);
    assert.deepEqual(await ui.saved(), []);
    await ui.sessions.shift().fulfill({json: {token: 'session-token', upload_url: '/upload'}});
    await until(() => ui.uploads.length === 2);
    await ui.ready(1);
    assert.deepEqual(await ui.saved(), []);
    await ui.ready(2);
    await until(async () => (await ui.saved()).length === 1);
    assert.equal((await ui.saved())[0]['token-0'], 'session-token');
});

test('automatic submit preserves Yii validation and lets the user correct fields', async t => {
    const ui = await setup(t);
    await ui.page.locator('#name').fill('');
    await ui.add(['1.png']);
    await until(() => ui.uploads.length === 1);
    await ui.page.locator('#top').click();
    await ui.ready(1);
    await until(async () => (await ui.page.locator('.invalid-feedback').textContent()).includes('Укажите название'));
    assert.deepEqual(await ui.saved(), []);
    assert.equal(await ui.page.locator('.spinner-border').count(), 0);
    await ui.page.locator('#name').fill('Исправлено');
    await ui.page.locator('#bottom').click();
    await until(async () => (await ui.saved()).length === 1);
    assert.equal((await ui.saved())[0].name, 'Исправлено');
});

test('Enter starts waiting and repeated click cancels even if HTML validation becomes invalid', async t => {
    const ui = await setup(t);
    await ui.add(['1.png']);
    await until(() => ui.uploads.length === 1);
    await ui.page.locator('#name').press('Enter');
    assert.equal(await ui.page.locator('.spinner-border').count(), 2);
    await ui.page.locator('#name').evaluate(input => { input.required = true; input.value = ''; });
    await ui.page.locator('#bottom').click();
    assert.equal(await ui.page.locator('.spinner-border').count(), 0);
    await ui.ready(1);
    assert.deepEqual(await ui.saved(), []);
});

test('Yii beforeSubmit defers a save if uploads start during validation', async t => {
    const ui = await setup(t);
    await ui.add(['1.png']);
    await until(() => ui.uploads.length === 1);
    await ui.page.evaluate(() => {
        const form = window.jQuery('form');
        form.data('yiiActiveForm').validated = true;
        form.trigger('submit');
    });
    assert.equal(await ui.page.locator('.spinner-border').count(), 2);
    assert.deepEqual(await ui.saved(), []);
    await ui.ready(1);
    await until(async () => (await ui.saved()).length === 1);
});

test('all editors in one form must finish before saving', async t => {
    const ui = await setup(t, {editors: 2});
    await ui.add(['1.png'], 0);
    await ui.add(['2.png'], 1);
    await until(() => ui.uploads.length === 2);
    await ui.page.locator('#top').click();
    await ui.ready(1);
    assert.deepEqual(await ui.saved(), []);
    await ui.ready(2);
    await until(async () => (await ui.saved()).length === 1);
    assert.deepEqual(JSON.parse((await ui.saved())[0]['manifest-1']), [{type: 'temporary', id: 2}]);
});

test('temporary deletion is awaited even when the final uploading card is removed', async t => {
    const ui = await setup(t);
    await ui.add(['1.png', '2.png']);
    await until(() => ui.uploads.length === 2);
    await ui.ready(1);
    await ui.page.locator('#top').click();
    await ui.page.locator('[data-entry-id="1"] [data-photo-editor-remove]').click();
    await until(() => ui.deletes.length === 1);
    await ui.page.locator('[data-status=uploading] [data-photo-editor-remove]').click();
    assert.deepEqual(await ui.saved(), []);
    await ui.deletes.shift().fulfill({json: {}});
    await until(async () => (await ui.saved()).length === 1);
    assert.deepEqual(JSON.parse((await ui.saved())[0]['manifest-0']), []);
});

async function drag(page, type, {target = 'body', names = ['photo.png'], text = null} = {}) {
    return page.evaluate(({type, target, names, text, bytes}) => {
        const dataTransfer = new DataTransfer();
        if (text !== null) dataTransfer.setData('text/plain', text);
        else names.forEach(name => dataTransfer.items.add(new File([new Uint8Array(bytes)], name, {type: 'image/png'})));
        const event = new DragEvent(type, {dataTransfer, bubbles: true, cancelable: true});
        document.querySelector(target).dispatchEvent(event);
        return event.defaultPrevented;
    }, {type, target, names, text, bytes: Array.from(png)});
}

test('file drag anywhere on the page shows an overlay and drops one sorted batch', async t => {
    const ui = await setup(t);
    await ui.page.evaluate(() => {
        const header = document.createElement('header');
        header.textContent = 'За пределами формы';
        document.body.prepend(header);
    });
    assert.equal(await drag(ui.page, 'dragenter', {target: 'header'}), true);
    const overlay = ui.page.locator('[data-photo-editor-drop-overlay]');
    assert.equal(await overlay.isVisible(), true);
    assert.equal(await overlay.evaluate(element => getComputedStyle(element).pointerEvents), 'none');
    assert.equal(await drag(ui.page, 'dragover', {target: '#name'}), true);
    assert.equal(await drag(ui.page, 'drop', {target: 'header', names: ['10.png', '2.png']}), true);
    await until(() => ui.uploads.length === 2);
    assert.equal(await overlay.isVisible(), false);
    assert.equal(await ui.page.locator('[data-photo-editor-card]').count(), 2);
    assert.deepEqual(await ui.page.locator('.photo-editor__name').allTextContents(), ['2.png', '10.png']);
    assert.equal(ui.page.url(), 'https://stockhub.test/form');
});

test('dropping directly on the original dropzone or a text field uploads files only once', async t => {
    const ui = await setup(t);
    for (const target of ['[data-photo-editor-droparea]', '#name']) {
        await drag(ui.page, 'dragenter', {target});
        assert.equal(await drag(ui.page, 'drop', {target}), true);
    }
    await until(() => ui.uploads.length === 2);
    assert.equal(await ui.page.locator('[data-photo-editor-card]').count(), 2);
    assert.equal(await ui.page.locator('.photo-editor__droparea--active').count(), 0);
    assert.equal(await ui.page.locator('#name').inputValue(), 'Предмет');
});

test('overlay survives nested drag events and clears when leaving or cancelling the drag', async t => {
    const ui = await setup(t);
    const overlay = ui.page.locator('[data-photo-editor-drop-overlay]');
    await drag(ui.page, 'dragenter');
    await drag(ui.page, 'dragenter', {target: '#top'});
    await drag(ui.page, 'dragleave');
    assert.equal(await overlay.isVisible(), true);
    await drag(ui.page, 'dragleave', {target: '#top'});
    assert.equal(await overlay.isVisible(), false);
    for (const cancel of ['escape', 'blur', 'dragend', 'pagehide']) {
        await drag(ui.page, 'dragenter');
        assert.equal(await overlay.isVisible(), true);
        await ui.page.evaluate(cancel => {
            if (cancel === 'escape') document.dispatchEvent(new KeyboardEvent('keydown', {key: 'Escape'}));
            else if (cancel === 'dragend') document.dispatchEvent(new DragEvent('dragend'));
            else window.dispatchEvent(new Event(cancel));
        }, cancel);
        assert.equal(await overlay.isVisible(), false);
        assert.equal(await ui.page.locator('.photo-editor__droparea--active').count(), 0);
    }
    assert.equal(ui.uploads.length, 0);
});

test('text drags and pages without a visible photo form are left alone', async t => {
    const ui = await setup(t);
    for (const type of ['dragenter', 'dragover', 'drop']) {
        assert.equal(await drag(ui.page, type, {target: '#name', text: 'Текст'}), false);
    }
    assert.equal(await ui.page.locator('[data-photo-editor-drop-overlay]').count(), 0);
    await ui.page.locator('form').evaluate(form => { form.hidden = true; });
    for (const type of ['dragenter', 'dragover', 'drop']) {
        assert.equal(await drag(ui.page, type), false);
    }
    assert.equal(await ui.page.locator('[data-photo-editor-drop-overlay]').count(), 0);
    assert.equal(ui.uploads.length, 0);
});

test('page drops join an existing deferred save', async t => {
    const ui = await setup(t);
    await ui.add(['1.png']);
    await until(() => ui.uploads.length === 1);
    await ui.page.locator('#top').click();
    await drag(ui.page, 'dragenter');
    await drag(ui.page, 'drop', {names: ['2.png']});
    await until(() => ui.uploads.length === 2);
    await ui.ready(1);
    assert.deepEqual(await ui.saved(), []);
    await ui.ready(2);
    await until(async () => (await ui.saved()).length === 1);
    assert.equal(JSON.parse((await ui.saved())[0]['manifest-0']).length, 2);
});

test('a drop uses the hovered editor, then the active editor for the rest of the page', async t => {
    const ui = await setup(t, {editors: 2});
    await drag(ui.page, 'dragenter', {target: '#editor-1'});
    await drag(ui.page, 'drop', {target: '#editor-1', names: ['1.png']});
    await drag(ui.page, 'dragenter');
    await drag(ui.page, 'drop', {names: ['2.png']});
    await until(() => ui.uploads.length === 2);
    assert.equal(await ui.page.locator('#editor-0 [data-photo-editor-card]').count(), 0);
    assert.equal(await ui.page.locator('#editor-1 [data-photo-editor-card]').count(), 2);
});

test('overlay fits desktop and mobile viewports in both color themes', async t => {
    for (const mobile of [false, true]) {
        const ui = await setup(t, {mobile});
        for (const theme of ['light', 'dark']) {
            await ui.page.locator('html').evaluate((root, theme) => root.setAttribute('data-bs-theme', theme), theme);
            await drag(ui.page, 'dragenter');
            const overlay = ui.page.locator('[data-photo-editor-drop-overlay]');
            const frame = await overlay.boundingBox();
            const message = await ui.page.locator('.photo-editor-drop-overlay__message').boundingBox();
            const viewport = ui.page.viewportSize();
            assert.ok(frame.x >= 0 && frame.y >= 0 && frame.x + frame.width <= viewport.width);
            assert.ok(frame.y + frame.height <= viewport.height);
            assert.ok(message.x >= frame.x && message.x + message.width <= frame.x + frame.width);
            assert.ok(message.y >= frame.y && message.y + message.height <= frame.y + frame.height);
            if (process.env.PHOTO_EDITOR_SCREENSHOT_DIR) {
                await ui.page.evaluate(() => document.fonts.ready);
                await ui.page.screenshot({path: path.join(process.env.PHOTO_EDITOR_SCREENSHOT_DIR, `photo-drop-${mobile ? 'mobile' : 'desktop'}-${theme}.png`)});
            }
            await drag(ui.page, 'dragleave');
        }
    }
});
