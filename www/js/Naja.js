/*
 * Naja.js
 * 3.2.1
 *
 * by Jiří Pudil <https://jiripudil.cz>
 */
(function (global, factory) {
    typeof exports === 'object' && typeof module !== 'undefined' ? module.exports = factory() :
    typeof define === 'function' && define.amd ? define(factory) :
    (global = typeof globalThis !== 'undefined' ? globalThis : global || self, global.naja = factory());
})(this, (function () { 'use strict';

    // ready
    const onDomReady = (callback) => {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback);
        }
        else {
            callback();
        }
    };

    class UIHandler extends EventTarget {
        constructor(naja) {
            super();
            this.naja = naja;
            this.selector = '.ajax';
            this.allowedOrigins = [window.location.origin];
            this.handler = this.handleUI.bind(this);
            naja.addEventListener('init', this.initialize.bind(this));
        }
        initialize() {
            onDomReady(() => this.bindUI(window.document.body));
            this.naja.snippetHandler.addEventListener('afterUpdate', (event) => {
                const { snippet } = event.detail;
                this.bindUI(snippet);
            });
        }
        bindUI(element) {
            const selector = `a${this.selector}`;
            const bindElement = (element) => {
                element.removeEventListener('click', this.handler);
                element.addEventListener('click', this.handler);
            };
            if (element.matches(selector)) {
                return bindElement(element);
            }
            const elements = element.querySelectorAll(selector);
            elements.forEach((element) => bindElement(element));
            const bindForm = (form) => {
                form.removeEventListener('submit', this.handler);
                form.addEventListener('submit', this.handler);
            };
            if (element instanceof HTMLFormElement) {
                return bindForm(element);
            }
            const forms = element.querySelectorAll('form');
            forms.forEach((form) => bindForm(form));
        }
        handleUI(event) {
            const element = event.currentTarget;
            const options = this.naja.prepareOptions();
            const ignoreErrors = () => {
                // don't reject the promise in case of an error as developers have no way of handling the rejection
                // in this situation; errors should be handled in `naja.addEventListener('error', errorHandler)`
            };
            if (event instanceof MouseEvent) {
                if (event.altKey || event.ctrlKey || event.shiftKey || event.metaKey || event.button) {
                    return;
                }
                this.clickElement(element, options, event).catch(ignoreErrors);
                return;
            }
            const { submitter } = event;
            if (this.selector === '' || element.matches(this.selector) || submitter?.matches(this.selector)) {
                this.submitForm(submitter ?? element, options, event).catch(ignoreErrors);
            }
        }
        async clickElement(element, options = {}, event) {
            if (element instanceof HTMLAnchorElement) {
                return this.processInteraction(element, 'GET', element.href, null, options, event);
            }
            if ((element instanceof HTMLInputElement || element instanceof HTMLButtonElement) && element.form) {
                return this.submitForm(element, options, event);
            }
            throw new Error('Unsupported element in clickElement(): element must be an anchor or a submitter element attached to a form.');
        }
        async submitForm(formOrSubmitter, options = {}, event) {
            let form;
            let submitter = null;
            if ((formOrSubmitter instanceof HTMLInputElement || formOrSubmitter instanceof HTMLButtonElement) && formOrSubmitter.form) {
                // eslint-disable-next-line prefer-destructuring
                form = formOrSubmitter.form;
                submitter = formOrSubmitter;
            }
            else if (formOrSubmitter instanceof HTMLFormElement) {
                form = formOrSubmitter;
                submitter = event instanceof SubmitEvent ? event.submitter : null;
            }
            else {
                throw new Error('Unsupported element in submitForm(): formOrSubmitter must be either a form or a submitter element attached to a form.');
            }
            const method = (submitter?.getAttribute('formmethod') ?? form.getAttribute('method') ?? 'GET').toUpperCase();
            const url = submitter?.getAttribute('formaction') ?? form.getAttribute('action') ?? window.location.pathname + window.location.search;
            const data = new FormData(form, submitter);
            return this.processInteraction(submitter ?? form, method, url, data, options, event);
        }
        async processInteraction(element, method, url, data = null, options = {}, event) {
            if (!this.dispatchEvent(new CustomEvent('interaction', { cancelable: true, detail: { element, originalEvent: event, options } }))) {
                event?.preventDefault();
                return {};
            }
            if (!this.isUrlAllowed(`${url}`)) {
                throw new Error(`Cannot dispatch async request, URL is not allowed: ${url}`);
            }
            event?.preventDefault();
            return this.naja.makeRequest(method, url, data, options);
        }
        isUrlAllowed(url) {
            const urlObject = new URL(url, location.href);
            // ignore non-URL URIs (javascript:, data:, mailto:, ...)
            if (urlObject.origin === 'null') {
                return false;
            }
            return this.allowedOrigins.includes(urlObject.origin);
        }
    }

    class FormsHandler {
        constructor(naja) {
            this.naja = naja;
            naja.addEventListener('init', this.initialize.bind(this));
            naja.uiHandler.addEventListener('interaction', this.processForm.bind(this));
        }
        initialize() {
            onDomReady(() => this.initForms(window.document.body));
            this.naja.snippetHandler.addEventListener('afterUpdate', (event) => {
                const { snippet } = event.detail;
                this.initForms(snippet);
            });
        }
        initForms(element) {
            const netteForms = this.netteForms || window.Nette;
            if (!netteForms) {
                return;
            }
            if (element instanceof HTMLFormElement) {
                netteForms.initForm(element);
                return;
            }
            const forms = element.querySelectorAll('form');
            forms.forEach((form) => netteForms.initForm(form));
        }
        processForm(event) {
            const { element, originalEvent } = event.detail;
            const isForm = element instanceof HTMLFormElement;
            const isSubmitter = (element instanceof HTMLInputElement || element instanceof HTMLButtonElement) && element.form;
            if (isSubmitter) {
                element.form['nette-submittedBy'] = element;
            }
            const netteForms = this.netteForms || window.Nette;
            if ((isForm || isSubmitter) && netteForms && !netteForms.validateForm(element)) {
                originalEvent?.stopImmediatePropagation();
                originalEvent?.preventDefault();
                event.preventDefault();
            }
        }
    }

    class RedirectHandler extends EventTarget {
        constructor(naja) {
            super();
            this.naja = naja;
            naja.uiHandler.addEventListener('interaction', (event) => {
                const { element, options } = event.detail;
                if (element.hasAttribute('data-naja-force-redirect') || element.form?.hasAttribute('data-naja-force-redirect')) {
                    const value = element.getAttribute('data-naja-force-redirect') ?? element.form?.getAttribute('data-naja-force-redirect');
                    options.forceRedirect = value !== 'off';
                }
            });
            naja.addEventListener('success', (event) => {
                const { payload, options } = event.detail;
                if (!payload.redirect) {
                    return;
                }
                this.makeRedirect(payload.redirect, options.forceRedirect ?? false, options);
                event.stopImmediatePropagation();
            });
            this.locationAdapter = {
                assign: (url) => window.location.assign(url),
            };
        }
        makeRedirect(url, force, options = {}) {
            if (url instanceof URL) {
                url = url.href;
            }
            let isHardRedirect = force || !this.naja.uiHandler.isUrlAllowed(url);
            const canRedirect = this.dispatchEvent(new CustomEvent('redirect', {
                cancelable: true,
                detail: {
                    url,
                    setUrl(value) {
                        url = value;
                    },
                    isHardRedirect,
                    setHardRedirect(value) {
                        isHardRedirect = !!value;
                    },
                    options,
                },
            }));
            if (!canRedirect) {
                return;
            }
            if (isHardRedirect) {
                this.locationAdapter.assign(url);
            }
            else {
                this.naja.makeRequest('GET', url, null, options);
            }
        }
    }

    class SnippetHandler extends EventTarget {
        constructor(naja) {
            super();
            this.op = {
                replace: {
                    updateElement(snippet, content) {
                        snippet.innerHTML = content;
                    },
                    updateIndex(_, newContent) {
                        return newContent;
                    },
                },
                prepend: {
                    updateElement(snippet, content) {
                        snippet.insertAdjacentHTML('afterbegin', content);
                    },
                    updateIndex(currentContent, newContent) {
                        return newContent + currentContent;
                    },
                },
                append: {
                    updateElement(snippet, content) {
                        snippet.insertAdjacentHTML('beforeend', content);
                    },
                    updateIndex(currentContent, newContent) {
                        return currentContent + newContent;
                    },
                },
            };
            naja.addEventListener('success', (event) => {
                const { options, payload } = event.detail;
                if (!payload.snippets) {
                    return;
                }
                this.updateSnippets(payload.snippets, false, options);
            });
        }
        static findSnippets(predicate, document = window.document) {
            const result = {};
            const snippets = document.querySelectorAll('[id^="snippet-"]');
            snippets.forEach((snippet) => {
                if (predicate?.(snippet) ?? true) {
                    result[snippet.id] = snippet.innerHTML;
                }
            });
            return result;
        }
        async updateSnippets(snippets, fromCache = false, options = {}) {
            await Promise.all(Object.keys(snippets).map(async (id) => {
                const snippet = document.getElementById(id);
                if (snippet) {
                    await this.updateSnippet(snippet, snippets[id], fromCache, options);
                }
            }));
        }
        async updateSnippet(snippet, content, fromCache, options) {
            let operation = this.op.replace;
            if ((snippet.hasAttribute('data-naja-snippet-prepend') || snippet.hasAttribute('data-ajax-prepend')) && !fromCache) {
                operation = this.op.prepend;
            }
            else if ((snippet.hasAttribute('data-naja-snippet-append') || snippet.hasAttribute('data-ajax-append')) && !fromCache) {
                operation = this.op.append;
            }
            const canUpdate = this.dispatchEvent(new CustomEvent('beforeUpdate', {
                cancelable: true,
                detail: {
                    snippet,
                    content,
                    fromCache,
                    operation,
                    changeOperation(value) {
                        operation = value;
                    },
                    options,
                },
            }));
            if (!canUpdate) {
                return;
            }
            this.dispatchEvent(new CustomEvent('pendingUpdate', {
                detail: {
                    snippet,
                    content,
                    fromCache,
                    operation,
                    options,
                },
            }));
            const updateElement = typeof operation === 'function' ? operation : operation.updateElement;
            await updateElement(snippet, content);
            this.dispatchEvent(new CustomEvent('afterUpdate', {
                detail: {
                    snippet,
                    content,
                    fromCache,
                    operation,
                    options,
                },
            }));
        }
    }

    const originalTitleKey = Symbol();
    class HistoryHandler extends EventTarget {
        constructor(naja) {
            super();
            this.naja = naja;
            this.initialized = false;
            this.cursor = 0;
            this.popStateHandler = this.handlePopState.bind(this);
            naja.addEventListener('init', this.initialize.bind(this));
            naja.addEventListener('before', this.saveUrl.bind(this));
            naja.addEventListener('before', this.saveOriginalTitle.bind(this));
            naja.addEventListener('before', this.replaceInitialState.bind(this));
            naja.addEventListener('success', this.pushNewState.bind(this));
            naja.redirectHandler.addEventListener('redirect', this.saveRedirectedUrl.bind(this));
            naja.uiHandler.addEventListener('interaction', this.configureMode.bind(this));
            this.historyAdapter = {
                replaceState: (state, title, url) => window.history.replaceState(state, title, url),
                pushState: (state, title, url) => window.history.pushState(state, title, url),
            };
        }
        set uiCache(value) {
            console.warn('Naja: HistoryHandler.uiCache is deprecated, use options.snippetCache instead.');
            this.naja.defaultOptions.snippetCache = value;
        }
        handlePopState(event) {
            const { state } = event;
            if (state?.source !== 'naja') {
                return;
            }
            const direction = state.cursor - this.cursor;
            this.cursor = state.cursor;
            const options = this.naja.prepareOptions();
            this.dispatchEvent(new CustomEvent('restoreState', { detail: { state, direction, options } }));
        }
        initialize() {
            window.addEventListener('popstate', this.popStateHandler);
        }
        saveOriginalTitle(event) {
            const { options } = event.detail;
            options[originalTitleKey] = window.document.title;
        }
        saveUrl(event) {
            const { url, options } = event.detail;
            options.href ??= url;
        }
        saveRedirectedUrl(event) {
            const { url, options } = event.detail;
            options.href = url;
        }
        replaceInitialState(event) {
            const { options } = event.detail;
            const mode = HistoryHandler.normalizeMode(options.history);
            if (mode !== false && !this.initialized) {
                onDomReady(() => this.historyAdapter.replaceState(this.buildState(window.location.href, 'replace', this.cursor, options), window.document.title, window.location.href));
                this.initialized = true;
            }
        }
        configureMode(event) {
            const { element, options } = event.detail;
            if (element.hasAttribute('data-naja-history') || element.form?.hasAttribute('data-naja-history')) {
                const value = element.getAttribute('data-naja-history') ?? element.form?.getAttribute('data-naja-history');
                options.history = HistoryHandler.normalizeMode(value);
            }
        }
        static normalizeMode(mode) {
            if (mode === 'off' || mode === false) {
                return false;
            }
            else if (mode === 'replace') {
                return 'replace';
            }
            return true;
        }
        pushNewState(event) {
            const { payload, options } = event.detail;
            const mode = HistoryHandler.normalizeMode(options.history);
            if (mode === false) {
                return;
            }
            if (payload.postGet && payload.url) {
                options.href = payload.url;
            }
            const method = mode === 'replace' ? 'replaceState' : 'pushState';
            const cursor = mode === 'replace' ? this.cursor : ++this.cursor;
            const state = this.buildState(options.href, mode, cursor, options);
            // before the state is pushed into history, revert to the original title
            const newTitle = window.document.title;
            window.document.title = options[originalTitleKey];
            this.historyAdapter[method](state, newTitle, options.href);
            // after the state is pushed into history, update back to the new title
            window.document.title = newTitle;
        }
        buildState(href, mode, cursor, options) {
            const state = {
                source: 'naja',
                cursor,
                href,
            };
            this.dispatchEvent(new CustomEvent('buildState', {
                detail: {
                    state,
                    operation: mode === 'replace' ? 'replaceState' : 'pushState',
                    options,
                },
            }));
            return state;
        }
    }

    class SnippetCache extends EventTarget {
        constructor(naja) {
            super();
            this.naja = naja;
            this.currentSnippets = new Map();
            this.storages = {
                off: new OffCacheStorage(naja),
                history: new HistoryCacheStorage(),
                session: new SessionCacheStorage(),
            };
            naja.addEventListener('init', this.initializeIndex.bind(this));
            naja.snippetHandler.addEventListener('pendingUpdate', this.updateIndex.bind(this));
            naja.uiHandler.addEventListener('interaction', this.configureCache.bind(this));
            naja.historyHandler.addEventListener('buildState', this.buildHistoryState.bind(this));
            naja.historyHandler.addEventListener('restoreState', this.restoreHistoryState.bind(this));
        }
        resolveStorage(option) {
            let storageType;
            if (option === true || option === undefined) {
                storageType = 'history';
            }
            else if (option === false) {
                storageType = 'off';
            }
            else {
                storageType = option;
            }
            return this.storages[storageType];
        }
        static shouldCacheSnippet(snippet) {
            return !snippet.hasAttribute('data-naja-history-nocache')
                && !snippet.hasAttribute('data-history-nocache')
                && (!snippet.hasAttribute('data-naja-snippet-cache')
                    || snippet.getAttribute('data-naja-snippet-cache') !== 'off');
        }
        initializeIndex() {
            onDomReady(() => {
                const currentSnippets = SnippetHandler.findSnippets(SnippetCache.shouldCacheSnippet);
                this.currentSnippets = new Map(Object.entries(currentSnippets));
            });
        }
        updateIndex(event) {
            const { snippet, content, operation } = event.detail;
            if (!SnippetCache.shouldCacheSnippet(snippet)) {
                return;
            }
            const currentContent = this.currentSnippets.get(snippet.id) ?? '';
            const updateIndex = typeof operation === 'object'
                ? operation.updateIndex
                : () => content;
            this.currentSnippets.set(snippet.id, updateIndex(currentContent, content));
            // update nested snippets
            const snippetContent = SnippetCache.parser.parseFromString(content, 'text/html');
            const nestedSnippets = SnippetHandler.findSnippets(SnippetCache.shouldCacheSnippet, snippetContent);
            for (const [id, content] of Object.entries(nestedSnippets)) {
                this.currentSnippets.set(id, content);
            }
        }
        configureCache(event) {
            const { element, options } = event.detail;
            if (!element) {
                return;
            }
            if (element.hasAttribute('data-naja-snippet-cache') || element.form?.hasAttribute('data-naja-snippet-cache')
                || element.hasAttribute('data-naja-history-cache') || element.form?.hasAttribute('data-naja-history-cache')) {
                const value = element.getAttribute('data-naja-snippet-cache')
                    ?? element.form?.getAttribute('data-naja-snippet-cache')
                    ?? element.getAttribute('data-naja-history-cache')
                    ?? element.form?.getAttribute('data-naja-history-cache');
                options.snippetCache = value;
            }
        }
        buildHistoryState(event) {
            const { state, options } = event.detail;
            if ('historyUiCache' in options) {
                console.warn('Naja: options.historyUiCache is deprecated, use options.snippetCache instead.');
                options.snippetCache = options.historyUiCache;
            }
            const presentSnippetIds = Object.keys(SnippetHandler.findSnippets(SnippetCache.shouldCacheSnippet));
            const snippets = Object.fromEntries(Array.from(this.currentSnippets).filter(([id]) => presentSnippetIds.includes(id)));
            if (!this.dispatchEvent(new CustomEvent('store', { cancelable: true, detail: { snippets, state, options } }))) {
                return;
            }
            const storage = this.resolveStorage(options.snippetCache);
            state.snippets = {
                storage: storage.type,
                key: storage.store(snippets),
            };
        }
        restoreHistoryState(event) {
            const { state, options } = event.detail;
            if (state.snippets === undefined) {
                return;
            }
            options.snippetCache = state.snippets.storage;
            if (!this.dispatchEvent(new CustomEvent('fetch', { cancelable: true, detail: { state, options } }))) {
                return;
            }
            const storage = this.resolveStorage(options.snippetCache);
            const snippets = storage.fetch(state.snippets.key, state, options);
            if (snippets === null) {
                return;
            }
            if (!this.dispatchEvent(new CustomEvent('restore', { cancelable: true, detail: { snippets, state, options } }))) {
                return;
            }
            this.naja.snippetHandler.updateSnippets(snippets, true, options);
        }
    }
    SnippetCache.parser = new DOMParser();
    class OffCacheStorage {
        constructor(naja) {
            this.naja = naja;
            this.type = 'off';
        } // eslint-disable-line no-empty-function
        store() {
            return null;
        }
        fetch(key, state, options) {
            this.naja.makeRequest('GET', state.href, null, {
                ...options,
                history: false,
                snippetCache: false,
            });
            return null;
        }
    }
    class HistoryCacheStorage {
        constructor() {
            this.type = 'history';
        }
        store(data) {
            return data;
        }
        fetch(key) {
            return key;
        }
    }
    class SessionCacheStorage {
        constructor() {
            this.type = 'session';
        }
        store(data) {
            const key = Math.random().toString(36).substring(2, 8);
            window.sessionStorage.setItem(key, JSON.stringify(data));
            return key;
        }
        fetch(key) {
            const data = window.sessionStorage.getItem(key);
            if (data === null) {
                return null;
            }
            return JSON.parse(data);
        }
    }

    class ScriptLoader {
        constructor(naja) {
            this.naja = naja;
            this.loadedScripts = new Set();
            naja.addEventListener('init', this.initialize.bind(this));
        }
        initialize() {
            onDomReady(() => {
                document.querySelectorAll('script[data-naja-script-id]').forEach((script) => {
                    const scriptId = script.getAttribute('data-naja-script-id');
                    if (scriptId !== null && scriptId !== '') {
                        this.loadedScripts.add(scriptId);
                    }
                });
            });
            this.naja.snippetHandler.addEventListener('afterUpdate', (event) => {
                const { content } = event.detail;
                this.loadScripts(content);
            });
        }
        loadScripts(snippetsOrSnippet) {
            if (typeof snippetsOrSnippet === 'string') {
                this.loadScriptsInSnippet(snippetsOrSnippet);
                return;
            }
            Object.keys(snippetsOrSnippet).forEach((id) => {
                const content = snippetsOrSnippet[id];
                this.loadScriptsInSnippet(content);
            });
        }
        loadScriptsInSnippet(content) {
            if (!/<script/i.test(content)) {
                return;
            }
            const snippetContent = ScriptLoader.parser.parseFromString(content, 'text/html');
            const scripts = snippetContent.querySelectorAll('script');
            scripts.forEach((script) => {
                const scriptId = script.getAttribute('data-naja-script-id');
                if (scriptId !== null && scriptId !== '' && this.loadedScripts.has(scriptId)) {
                    return;
                }
                const scriptEl = window.document.createElement('script');
                scriptEl.innerHTML = script.innerHTML;
                if (script.hasAttributes()) {
                    for (const attribute of script.attributes) {
                        scriptEl.setAttribute(attribute.name, attribute.value);
                    }
                }
                window.document.head.appendChild(scriptEl)
                    .parentNode.removeChild(scriptEl);
                if (scriptId !== null && scriptId !== '') {
                    this.loadedScripts.add(scriptId);
                }
            });
        }
    }
    ScriptLoader.parser = new DOMParser();

    class Naja extends EventTarget {
        constructor(uiHandler, redirectHandler, snippetHandler, formsHandler, historyHandler, snippetCache, scriptLoader) {
            super();
            this.VERSION = 3;
            this.initialized = false;
            this.extensions = [];
            this.defaultOptions = {};
            this.uiHandler = new (uiHandler ?? UIHandler)(this);
            this.redirectHandler = new (redirectHandler ?? RedirectHandler)(this);
            this.snippetHandler = new (snippetHandler ?? SnippetHandler)(this);
            this.formsHandler = new (formsHandler ?? FormsHandler)(this);
            this.historyHandler = new (historyHandler ?? HistoryHandler)(this);
            this.snippetCache = new (snippetCache ?? SnippetCache)(this);
            this.scriptLoader = new (scriptLoader ?? ScriptLoader)(this);
        }
        registerExtension(extension) {
            if (this.initialized) {
                extension.initialize(this);
            }
            this.extensions.push(extension);
        }
        initialize(defaultOptions = {}) {
            if (this.initialized) {
                throw new Error('Cannot initialize Naja, it is already initialized.');
            }
            this.defaultOptions = this.prepareOptions(defaultOptions);
            this.extensions.forEach((extension) => extension.initialize(this));
            this.dispatchEvent(new CustomEvent('init', { detail: { defaultOptions: this.defaultOptions } }));
            this.initialized = true;
        }
        prepareOptions(options) {
            return {
                ...this.defaultOptions,
                ...options,
                fetch: {
                    ...this.defaultOptions.fetch,
                    ...options?.fetch,
                },
            };
        }
        async makeRequest(method, url, data = null, options = {}) {
            // normalize url to instanceof URL
            if (typeof url === 'string') {
                url = new URL(url, location.href);
            }
            options = this.prepareOptions(options);
            const headers = new Headers(options.fetch.headers || {});
            const body = this.transformData(url, method, data);
            const abortController = new AbortController();
            const request = new Request(url.toString(), {
                credentials: 'same-origin',
                ...options.fetch,
                method,
                headers,
                body,
                signal: abortController.signal,
            });
            // impersonate XHR so that Nette can detect isAjax()
            request.headers.set('X-Requested-With', 'XMLHttpRequest');
            // hint the server that Naja expects response to be JSON
            request.headers.set('Accept', 'application/json');
            if (!this.dispatchEvent(new CustomEvent('before', { cancelable: true, detail: { request, method, url: url.toString(), data, options } }))) {
                return {};
            }
            const promise = window.fetch(request);
            this.dispatchEvent(new CustomEvent('start', { detail: { request, promise, abortController, options } }));
            let response, payload;
            try {
                response = await promise;
                if (!response.ok) {
                    throw new HttpError(response);
                }
                payload = await response.json();
            }
            catch (error) {
                if (error.name === 'AbortError') {
                    this.dispatchEvent(new CustomEvent('abort', { detail: { request, error, options } }));
                    this.dispatchEvent(new CustomEvent('complete', { detail: { request, response, payload: undefined, error, options } }));
                    return {};
                }
                this.dispatchEvent(new CustomEvent('error', { detail: { request, response, error, options } }));
                this.dispatchEvent(new CustomEvent('complete', { detail: { request, response, payload: undefined, error, options } }));
                throw error;
            }
            this.dispatchEvent(new CustomEvent('payload', { detail: { request, response, payload, options } }));
            this.dispatchEvent(new CustomEvent('success', { detail: { request, response, payload, options } }));
            this.dispatchEvent(new CustomEvent('complete', { detail: { request, response, payload, error: undefined, options } }));
            return payload;
        }
        appendToQueryString(searchParams, key, value) {
            if (value === null || value === undefined) {
                return;
            }
            if (Array.isArray(value) || Object.getPrototypeOf(value) === Object.prototype) {
                for (const [subkey, subvalue] of Object.entries(value)) {
                    this.appendToQueryString(searchParams, `${key}[${subkey}]`, subvalue);
                }
            }
            else {
                searchParams.append(key, String(value));
            }
        }
        transformData(url, method, data) {
            const isGet = ['GET', 'HEAD'].includes(method.toUpperCase());
            // sending a form via GET -> serialize FormData into URL and return empty request body
            if (isGet && data instanceof FormData) {
                for (const [key, value] of data) {
                    if (value !== null && value !== undefined) {
                        url.searchParams.append(key, String(value));
                    }
                }
                return null;
            }
            // sending a POJO -> serialize it recursively into URLSearchParams
            const isDataPojo = data !== null && Object.getPrototypeOf(data) === Object.prototype;
            if (isDataPojo || Array.isArray(data)) {
                // for GET requests, append values to URL and return empty request body
                // otherwise build `new URLSearchParams()` to act as the request body
                const transformedData = isGet ? url.searchParams : new URLSearchParams();
                for (const [key, value] of Object.entries(data)) {
                    this.appendToQueryString(transformedData, key, value);
                }
                return isGet
                    ? null
                    : transformedData;
            }
            return data;
        }
    }
    class HttpError extends Error {
        constructor(response) {
            const message = `HTTP ${response.status}: ${response.statusText}`;
            super(message);
            this.name = this.constructor.name;
            this.stack = new Error(message).stack;
            this.response = response;
        }
    }

    class AbortExtension {
        constructor() {
            this.abortControllers = new Set();
        }
        initialize(naja) {
            naja.uiHandler.addEventListener('interaction', this.checkAbortable.bind(this));
            naja.addEventListener('init', this.onInitialize.bind(this));
            naja.addEventListener('start', this.saveAbortController.bind(this));
            naja.addEventListener('complete', this.removeAbortController.bind(this));
        }
        onInitialize() {
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && !(event.ctrlKey || event.shiftKey || event.altKey || event.metaKey)) {
                    for (const controller of this.abortControllers) {
                        controller.abort();
                    }
                    this.abortControllers.clear();
                }
            });
        }
        checkAbortable(event) {
            const { element, options } = event.detail;
            if (element.hasAttribute('data-naja-abort') || element.form?.hasAttribute('data-naja-abort')) {
                options.abort = (element.getAttribute('data-naja-abort') ?? element.form?.getAttribute('data-naja-abort')) !== 'off';
            }
        }
        saveAbortController(event) {
            const { abortController, options } = event.detail;
            if (options.abort !== false) {
                this.abortControllers.add(abortController);
                options.clearAbortExtension = () => this.abortControllers.delete(abortController);
            }
        }
        removeAbortController(event) {
            const { options } = event.detail;
            if (options.abort !== false && !!options.clearAbortExtension) {
                options.clearAbortExtension();
            }
        }
    }

    class UniqueExtension {
        constructor() {
            this.abortControllers = new Map();
        }
        initialize(naja) {
            naja.uiHandler.addEventListener('interaction', this.checkUniqueness.bind(this));
            naja.addEventListener('start', this.abortPreviousRequest.bind(this));
            naja.addEventListener('complete', this.clearRequest.bind(this));
        }
        checkUniqueness(event) {
            const { element, options } = event.detail;
            if (element.hasAttribute('data-naja-unique') ?? element.form?.hasAttribute('data-naja-unique')) {
                const unique = element.getAttribute('data-naja-unique') ?? element.form?.getAttribute('data-naja-unique');
                options.unique = unique === 'off' ? false : unique ?? 'default';
            }
        }
        abortPreviousRequest(event) {
            const { abortController, options } = event.detail;
            if (options.unique !== false) {
                this.abortControllers.get(options.unique ?? 'default')?.abort();
                this.abortControllers.set(options.unique ?? 'default', abortController);
            }
        }
        clearRequest(event) {
            const { request, options } = event.detail;
            if (!request.signal.aborted && options.unique !== false) {
                this.abortControllers.delete(options.unique ?? 'default');
            }
        }
    }

    const naja = new Naja();
    naja.registerExtension(new AbortExtension());
    naja.registerExtension(new UniqueExtension());
    naja.Naja = Naja;
    naja.HttpError = HttpError;

    return naja;

}));
//# sourceMappingURL=Naja.js.map
