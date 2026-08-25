<script>
(function () {
    'use strict';

    const rootUrl = @json(route('login'));
    const loginUrl = @json(route('tab.login'));
    const dashboardUrl = @json(route('dashboard'));
    const content = document.getElementById('app-content');
    const initialScripts = document.getElementById('initial-module-scripts');
    const nativeFormSubmit = HTMLFormElement.prototype.submit;
    let currentModule = '/dashboard';
    let trackedListeners = [];

    if (!sessionStorage.getItem('tab_token')) {
        window.location.replace(loginUrl);
        return;
    }

    function pathOf(value) {
        const url = new URL(value, window.location.origin);
        return url.pathname + url.search;
    }

    function isDownload(url, anchor) {
        return anchor?.hasAttribute('download') ||
            /\/(descargar|download)(\/|$)/i.test(url.pathname) ||
            /\/exportar\//i.test(url.pathname) ||
            /\.(pdf|xlsx?|csv|zip|docx?)(\?|$)/i.test(url.pathname);
    }

    function isInternalModule(url, element) {
        return url.origin === window.location.origin &&
            !element?.hasAttribute('data-native-navigation') &&
            !element?.target &&
            url.pathname !== '/' &&
            url.pathname !== '/login' &&
            url.pathname !== '/logout' &&
            !isDownload(url, element);
    }

    function cleanupModule() {
        trackedListeners.forEach(([target, type, listener, options]) => {
            target.removeEventListener(type, listener, options);
        });
        trackedListeners = [];

        const match = currentModule.match(/^\/casos\/(\d+)(?:$|\?)/);
        if (match && window.Echo?.leave) {
            window.Echo.leave(`caso.${match[1]}`);
        }
        document.querySelectorAll('[data-shell-module-style]').forEach(node => node.remove());
    }

    function installStyles(parsed) {
        const holder = parsed.getElementById('module-styles');
        if (!holder) return;
        holder.querySelectorAll('link[rel="stylesheet"], style').forEach(style => {
            const copy = style.cloneNode(true);
            copy.setAttribute('data-shell-module-style', 'true');
            document.head.appendChild(copy);
        });
    }

    function showFlash(parsed) {
        const flash = parsed.getElementById('module-flash');
        if (!flash) return;
        const success = flash.dataset.success;
        const error = flash.dataset.error;
        if (success) Toast.fire({ icon: 'success', title: '¡Éxito!', text: success });
        if (error) Toast.fire({ icon: 'error', title: 'Error', text: error });
    }

    function executeScripts(nodes) {
        const originalAdd = EventTarget.prototype.addEventListener;
        EventTarget.prototype.addEventListener = function (type, listener, options) {
            if (this === document && type === 'DOMContentLoaded' && document.readyState !== 'loading') {
                listener.call(this, new Event('DOMContentLoaded'));
                return;
            }
            trackedListeners.push([this, type, listener, options]);
            return originalAdd.call(this, type, listener, options);
        };

        try {
            nodes.forEach(source => {
                if (source.src) return;
                let code = source.textContent || '';
                const names = [...code.matchAll(/(?:^|\n)\s*function\s+([A-Za-z_$][\w$]*)\s*\(/g)].map(match => match[1]);
                if (names.length) {
                    code += '\n' + names.map(name => `window.${name} = typeof ${name} === "function" ? ${name} : window.${name};`).join('\n');
                }
                try {
                    new Function(code)();
                } catch (error) {
                    console.error('Error inicializando m�dulo', currentModule, error);
                }
            });
        } finally {
            EventTarget.prototype.addEventListener = originalAdd;
        }
        window.lucide?.createIcons();
    }

    function updateActiveNavigation(path) {
        const groups = [
            '.sidebar-nav .nav-item[href]',
            '.bottom-nav .bottom-nav-item[href]',
            '.mobile-drawer .drawer-nav-item[href]'
        ];

        groups.forEach(selector => {
            const links = [...document.querySelectorAll(selector)];
            const matches = links.filter(link => {
                const linkPath = new URL(link.href, location.origin).pathname;
                return path === linkPath || (linkPath !== '/dashboard' && path.startsWith(linkPath + '/'));
            });
            const bestLength = Math.max(0, ...matches.map(link => new URL(link.href, location.origin).pathname.length));

            links.forEach(link => {
                const linkPath = new URL(link.href, location.origin).pathname;
                link.classList.toggle('active', matches.includes(link) && linkPath.length === bestLength);
            });
        });

        const drawerHasActiveItem = Boolean(document.querySelector('.mobile-drawer .drawer-nav-item.active'));
        document.querySelector('[data-mobile-more]')?.classList.toggle('active', drawerHasActiveItem);
    }

    async function renderResponse(response, requestedPath, pushHistory) {
        if (response.status === 419) {
            await Swal.fire('Sesi�n expirada', 'Inicia sesi�n nuevamente.', 'warning');
            window.location.assign(loginUrl);
            return;
        }
        if (response.status === 401 || response.status === 403) {
            const message = response.status === 403 ? 'No tienes permiso para acceder a esta secci�n.' : 'Tu sesi�n ya no es v�lida.';
            await Swal.fire('Acceso no disponible', message, 'warning');
            if (response.status === 401) window.location.assign(loginUrl);
            return;
        }
        if (!response.ok) throw new Error(`HTTP ${response.status}`);

        const contentType = response.headers.get('content-type') || '';
        if (!contentType.includes('text/html')) {
            window.location.assign(requestedPath);
            return;
        }

        const html = await response.text();
        const parsed = new DOMParser().parseFromString(html, 'text/html');
        const fragment = parsed.getElementById('module-fragment');
        if (!fragment) {
            window.location.assign(response.url || rootUrl);
            return;
        }

        const scriptNodes = [...fragment.querySelectorAll('script'), ...parsed.querySelectorAll('#module-scripts script')];
        fragment.querySelectorAll('script').forEach(script => script.remove());
        cleanupModule();
        installStyles(parsed);
        content.innerHTML = fragment.innerHTML;

        const finalPath = pathOf(response.url || requestedPath);
        currentModule = finalPath === '/' ? '/dashboard' : finalPath;
        sessionStorage.setItem('current_module', currentModule);
        updateActiveNavigation(new URL(currentModule, location.origin).pathname);
        executeScripts(scriptNodes);
        showFlash(parsed);

        const title = parsed.querySelector('[data-module-title]')?.getAttribute('data-module-title');
        if (title) document.title = title;
        if (pushHistory) history.pushState({ shellModule: currentModule }, '', '/');
        window.scrollTo({ top: 0, behavior: 'instant' });
    }

    async function loadModule(path, options = {}) {
        const requestedPath = pathOf(path);
        const response = await fetch(requestedPath, {
            method: 'GET',
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' }
        });
        await renderResponse(response, requestedPath, options.pushHistory !== false);
    }

    async function submitForm(form, submitter = null) {
        const url = new URL(form.action || currentModule, location.origin);
        if (!isInternalModule(url, form)) {
            nativeFormSubmit.call(form);
            return;
        }
        const method = (form.method || 'GET').toUpperCase();
        const data = new FormData(form);
        if (submitter?.name) data.append(submitter.name, submitter.value);
        let requestUrl = url.pathname + url.search;
        const options = {
            method,
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' }
        };
        if (method === 'GET') {
            const query = new URLSearchParams(data).toString();
            requestUrl = url.pathname + (query ? `?${query}` : '');
        } else {
            options.body = data;
        }
        const response = await fetch(requestUrl, options);
        await renderResponse(response, requestUrl, true);
    }

    document.addEventListener('click', event => {
        const anchor = event.target.closest('a[href]');
        if (!anchor || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        const url = new URL(anchor.href, location.origin);
        if (!isInternalModule(url, anchor)) return;
        event.preventDefault();
        window.closeMobileDrawer?.();
        loadModule(url.href).catch(error => {
            console.error('Error de navegaci�n interna', error);
            Swal.fire('Error', 'No fue posible cargar esta secci�n.', 'error');
        });
    });

    document.addEventListener('submit', event => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || event.defaultPrevented) return;
        const url = new URL(form.action || currentModule, location.origin);
        if (!isInternalModule(url, form)) return;
        event.preventDefault();
        submitForm(form, event.submitter).catch(error => {
            console.error('Error enviando formulario', error);
            Swal.fire('Error', 'No fue posible procesar la solicitud.', 'error');
        });
    });

    window.addEventListener('popstate', event => {
        const path = event.state?.shellModule || sessionStorage.getItem('current_module') || '/dashboard';
        loadModule(path, { pushHistory: false }).catch(console.error);
    });

    window.ShellNavigation = { loadModule, submitForm };
    HTMLFormElement.prototype.submit = function () {
        const url = new URL(this.action || currentModule, location.origin);
        if (!isInternalModule(url, this)) {
            nativeFormSubmit.call(this);
            return;
        }
        submitForm(this).catch(error => {
            console.error('Error enviando formulario', error);
            Swal.fire('Error', 'No fue posible procesar la solicitud.', 'error');
        });
    };
    currentModule = sessionStorage.getItem('current_module') || '/dashboard';

    if (currentModule !== '/dashboard') {
        loadModule(currentModule, { pushHistory: false }).catch(() => loadModule('/dashboard', { pushHistory: false }));
    } else if (initialScripts) {
        executeScripts([...initialScripts.content.querySelectorAll('script')]);
        sessionStorage.setItem('current_module', '/dashboard');
        updateActiveNavigation('/dashboard');
    }
})();
</script>