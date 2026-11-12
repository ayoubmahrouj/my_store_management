(function () {
    'use strict';

    function toNumber(value) {
        var number = parseFloat(String(value == null ? '' : value).replace(',', '.'));
        return Number.isFinite(number) ? number : 0;
    }

    function formatQty(value) {
        return new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 3 }).format(toNumber(value));
    }

    var currency = (document.body && document.body.getAttribute('data-currency')) || 'MAD';

    function formatMoney(value) {
        return new Intl.NumberFormat('fr-FR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).format(toNumber(value)) + ' ' + currency;
    }

    function each(root, selector, callback) {
        if (!root) return;
        if (root.nodeType === 1 && root.matches(selector)) {
            callback(root);
        }
        root.querySelectorAll(selector).forEach(callback);
    }

    function bindConfirmations(root) {
        each(root, 'form[data-confirm]', function (form) {
            if (form.dataset.confirmReady === '1') return;
            form.dataset.confirmReady = '1';
            form.addEventListener('submit', function (event) {
                var message = form.getAttribute('data-confirm') || 'Confirmer cette action ?';
                if (!window.confirm(message)) {
                    event.preventDefault();
                }
            });
        });

        each(root, 'button[data-confirm], input[data-confirm]', function (button) {
            if (button.dataset.confirmReady === '1') return;
            button.dataset.confirmReady = '1';
            button.addEventListener('click', function (event) {
                var message = button.getAttribute('data-confirm') || 'Confirmer cette action ?';
                if (!window.confirm(message)) {
                    event.preventDefault();
                    event.stopImmediatePropagation();
                }
            });
        });
    }

    function bindPasswordToggles(root) {
        each(root, '[data-toggle-password]', function (button) {
            if (button.dataset.passwordReady === '1') return;
            button.dataset.passwordReady = '1';
            button.addEventListener('click', function () {
                var target = document.querySelector(button.getAttribute('data-toggle-password'));
                if (!target) return;
                var isPassword = target.getAttribute('type') === 'password';
                target.setAttribute('type', isPassword ? 'text' : 'password');
                var icon = button.querySelector('i');
                if (icon) {
                    icon.classList.toggle('bi-eye', !isPassword);
                    icon.classList.toggle('bi-eye-slash', isPassword);
                }
                button.setAttribute('aria-label', isPassword ? 'Masquer le mot de passe' : 'Afficher le mot de passe');
            });
        });
    }

    function bindPrints(root) {
        each(root, '[data-print]', function (button) {
            if (button.dataset.printReady === '1') return;
            button.dataset.printReady = '1';
            button.addEventListener('click', function () {
                window.print();
            });
        });
    }

    function bindAlerts(root) {
        each(root, '.alert-dismissible', function (alert) {
            if (alert.dataset.alertReady === '1') return;
            alert.dataset.alertReady = '1';
            window.setTimeout(function () {
                if (window.bootstrap && window.bootstrap.Alert) {
                    window.bootstrap.Alert.getOrCreateInstance(alert).close();
                }
            }, 6000);
        });
    }

    function setupLineEditor(editor) {
        if (!editor || editor.dataset.lineEditorReady === '1') return;
        editor.dataset.lineEditorReady = '1';

        var form = editor.closest('form') || document;
        var body = editor.querySelector('tbody');
        var template = editor.querySelector('#lineTemplate');
        var type = editor.getAttribute('data-type') || '';
        var counter = body ? body.querySelectorAll('[data-line]').length : 0;

        function setProductData(row) {
            var select = row.querySelector('.line-product');
            if (!select) return;
            var option = select.options[select.selectedIndex];
            var unitLabel = row.querySelector('.line-unit');
            var costInput = row.querySelector('.line-cost');
            var priceInput = row.querySelector('.line-price');
            var costLabel = row.querySelector('.line-cost-label');

            var unit = option ? (option.getAttribute('data-unit') || '') : '';
            if (unitLabel) unitLabel.textContent = unit;

            if (!option || !option.value) {
                if (costInput && type !== 'entree') costInput.value = '';
                if (costLabel) costLabel.textContent = '—';
                calculateTotals();
                return;
            }

            var cost = option.getAttribute('data-cost') || '0';
            var price = option.getAttribute('data-price') || '0';

            if (type === 'entree') {
                if (costInput && String(costInput.value).trim() === '') costInput.value = cost;
                if (priceInput && String(priceInput.value).trim() === '') priceInput.value = price;
            } else {
                if (costInput) costInput.value = cost;
                if (costLabel) costLabel.textContent = formatMoney(cost);
                if (priceInput && String(priceInput.value).trim() === '') priceInput.value = price;
            }

            calculateTotals();
        }

        function calculateRow(row) {
            var qty = toNumber((row.querySelector('.line-qty') || {}).value);
            var cost = toNumber((row.querySelector('.line-cost') || {}).value);
            var total = row.querySelector('.line-total');
            if (total) total.textContent = formatMoney(qty * cost);
        }

        function calculateTotals() {
            var totalCost = 0;
            var totalSale = 0;
            if (!body) return;
            body.querySelectorAll('[data-line]').forEach(function (row) {
                var qty = toNumber((row.querySelector('.line-qty') || {}).value);
                var cost = toNumber((row.querySelector('.line-cost') || {}).value);
                var price = toNumber((row.querySelector('.line-price') || {}).value);
                totalCost += qty * cost;
                totalSale += qty * price;
                calculateRow(row);
            });
            var costEl = form.querySelector('#totalCost');
            var saleEl = form.querySelector('#totalSale');
            if (costEl) costEl.textContent = formatMoney(totalCost);
            if (saleEl) saleEl.textContent = formatMoney(totalSale);
        }

        function bindRow(row) {
            var select = row.querySelector('.line-product');
            if (select) {
                select.addEventListener('change', function () {
                    setProductData(row);
                });
            }
            row.querySelectorAll('.line-qty, .line-cost, .line-price').forEach(function (input) {
                input.addEventListener('input', calculateTotals);
            });
            var remove = row.querySelector('[data-remove-line]');
            if (remove) {
                remove.addEventListener('click', function () {
                    row.remove();
                    if (body && body.querySelectorAll('[data-line]').length === 0) {
                        addLine();
                    }
                    calculateTotals();
                });
            }
        }

        function addLine() {
            if (!template || !body) return;
            var html = template.innerHTML.replace(/__INDEX__/g, String(counter++));
            var wrapper = document.createElement('tbody');
            wrapper.innerHTML = html.trim();
            var row = wrapper.querySelector('[data-line]');
            if (!row) return;
            body.appendChild(row);
            bindRow(row);
            setProductData(row);
            calculateTotals();
            var first = row.querySelector('.line-product');
            if (first) first.focus();
        }

        if (body) {
            body.querySelectorAll('[data-line]').forEach(function (row) {
                bindRow(row);
                setProductData(row);
            });
        }

        var addButton = form.querySelector('[data-add-line]');
        if (addButton) addButton.addEventListener('click', addLine);

        calculateTotals();
    }

    function setupInventory(root) {
        each(root, '.count-input', function (input) {
            if (input.dataset.inventoryReady === '1') return;
            input.dataset.inventoryReady = '1';
            input.addEventListener('input', function () {
                var row = input.closest('tr');
                if (!row) return;
                var theoretical = toNumber(input.getAttribute('data-theoretical'));
                var cost = toNumber(input.getAttribute('data-cost'));
                var raw = String(input.value || '').trim();
                var gapLabel = row.querySelector('.gap-label');
                var gapValue = row.querySelector('.gap-value');

                if (raw === '') {
                    if (gapLabel) {
                        gapLabel.textContent = '—';
                        gapLabel.className = 'gap-label muted';
                    }
                    if (gapValue) {
                        gapValue.textContent = '—';
                        gapValue.className = 'gap-value muted';
                    }
                    return;
                }

                var gap = toNumber(raw) - theoretical;
                var className = gap < 0 ? 'text-danger' : (gap > 0 ? 'text-success' : 'muted');
                if (gapLabel) {
                    gapLabel.textContent = (gap > 0 ? '+' : '') + formatQty(gap);
                    gapLabel.className = 'gap-label ' + className;
                }
                if (gapValue) {
                    gapValue.textContent = formatMoney(gap * cost);
                    gapValue.className = 'gap-value ' + className;
                }
            });
        });

        var inventoryFilter = document.getElementById('inventoryFilter');
        if (inventoryFilter && inventoryFilter.dataset.filterReady !== '1') {
            inventoryFilter.dataset.filterReady = '1';
            inventoryFilter.addEventListener('input', function () {
                var term = inventoryFilter.value.toLocaleLowerCase('fr');
                document.querySelectorAll('[data-inventory-row]').forEach(function (row) {
                    var text = (row.textContent || '').toLocaleLowerCase('fr');
                    row.style.display = term === '' || text.indexOf(term) !== -1 ? '' : 'none';
                });
            });
        }
    }

    function enhance(root, autoDismissAlerts) {
        bindConfirmations(root);
        bindPasswordToggles(root);
        bindPrints(root);
        if (autoDismissAlerts) bindAlerts(root);
        if (root) {
            root.querySelectorAll('[data-lines-editor]').forEach(setupLineEditor);
            if (root.nodeType === 1 && root.matches('[data-lines-editor]')) setupLineEditor(root);
        }
        setupInventory(root);
    }

    function setupSidebar() {
        var sidebar = document.getElementById('sidebar');
        var overlay = document.getElementById('sidebarOverlay');
        var toggle = document.getElementById('sidebarToggle');

        function openSidebar() {
            if (!sidebar || !overlay) return;
            sidebar.classList.add('open');
            overlay.classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        function closeSidebar() {
            if (!sidebar || !overlay) return;
            sidebar.classList.remove('open');
            overlay.classList.remove('show');
            document.body.style.overflow = '';
        }

        if (toggle) {
            toggle.addEventListener('click', function () {
                if (sidebar && sidebar.classList.contains('open')) closeSidebar();
                else openSidebar();
            });
        }

        if (overlay) overlay.addEventListener('click', closeSidebar);

        window.addEventListener('resize', function () {
            if (window.innerWidth >= 992) closeSidebar();
        });

        return closeSidebar;
    }

    function setupKeyboard(closeSidebar) {
        document.addEventListener('keydown', function (event) {
            if ((event.ctrlKey || event.metaKey) && String(event.key).toLowerCase() === 'k') {
                var search = document.getElementById('globalSearch');
                if (search) {
                    event.preventDefault();
                    search.focus();
                    search.select();
                }
            }
            if (event.key === 'Escape' && closeSidebar) closeSidebar();
        });
    }

    function setupModals() {
        var modalElement = document.getElementById('quickModal');
        if (!modalElement || !window.bootstrap || !window.bootstrap.Modal) {
            return;
        }

        var modal = window.bootstrap.Modal.getOrCreateInstance(modalElement);
        var body = modalElement.querySelector('[data-quick-body]');
        var title = modalElement.querySelector('[data-quick-title]');
        var requestToken = 0;

        function alertBox(message) {
            body.innerHTML = '<div class="alert alert-premium alert-danger-soft" role="alert">' +
                '<i class="bi bi-exclamation-octagon"></i> ' + message + '</div>';
        }

        function insertContent(html) {
            var doc = new DOMParser().parseFromString(html, 'text/html');
            var main = doc.querySelector('main.content');
            if (!main) {
                alertBox('Le formulaire n’a pas pu être chargé.');
                return;
            }

            var heading = main.querySelector('.page-head h2');
            if (heading && heading.textContent.trim() !== '') {
                title.textContent = heading.textContent.trim();
            }

            main.querySelectorAll('.page-head, .content-footer, script').forEach(function (element) {
                element.remove();
            });

            body.innerHTML = main.innerHTML;
            enhance(body, false);
            window.setTimeout(function () {
                var first = body.querySelector('input:not([type="hidden"]), select, textarea');
                if (first && typeof first.focus === 'function') first.focus();
            }, 180);
        }

        function isLoginUrl(url) {
            try {
                var parsed = new URL(url, window.location.href);
                return parsed.pathname === '/login' || parsed.pathname === '/' || parsed.pathname.endsWith('/login');
            } catch (error) {
                return false;
            }
        }

        function openModal(url, label) {
            requestToken++;
            var token = requestToken;
            title.textContent = label || 'Création rapide';
            body.innerHTML = '<div class="quick-loading"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span><span>Chargement du formulaire…</span></div>';
            modal.show();

            fetch(url, {
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            }).then(function (response) {
                if (token !== requestToken) return null;
                if (isLoginUrl(response.url)) {
                    window.location.assign(response.url);
                    return null;
                }
                return response.text().then(function (html) {
                    if (token !== requestToken) return;
                    if (!response.ok) {
                        alertBox('Impossible de charger le formulaire (HTTP ' + response.status + ').');
                        return;
                    }
                    insertContent(html);
                });
            }).catch(function () {
                if (token === requestToken) {
                    alertBox('Connexion impossible. Vérifiez votre réseau puis réessayez.');
                }
            });
        }

        document.addEventListener('click', function (event) {
            if (event.defaultPrevented || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) {
                return;
            }
            var link = event.target.closest ? event.target.closest('a') : null;
            if (!link || link.target === '_blank') {
                return;
            }

            var text = (link.textContent || '').replace(/\s+/g, ' ').trim();
            var isNewAction = link.hasAttribute('data-modal') || /^(Nouveau|Nouvelle|Nouvel)\b/i.test(text);
            if (!isNewAction) {
                return;
            }

            var url;
            try {
                url = new URL(link.href, window.location.href);
            } catch (error) {
                return;
            }
            if (url.origin !== window.location.origin) {
                return;
            }

            event.preventDefault();
            openModal(url.href, link.getAttribute('data-modal-title') || text);
        });

        modalElement.addEventListener('submit', function (event) {
            var form = event.target;
            if (!(form instanceof HTMLFormElement) || !body.contains(form)) {
                return;
            }

            event.preventDefault();
            if (form.dataset.submitting === '1') {
                return;
            }
            form.dataset.submitting = '1';

            var submitter = event.submitter || null;
            var submitButtons = form.querySelectorAll('button[type="submit"], input[type="submit"]');

            var originalHtml = '';
            if (submitter) {
                originalHtml = submitter.innerHTML;
                submitter.innerHTML = '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span> Enregistrement…';
            }

            var payload;
            try {
                payload = submitter ? new FormData(form, submitter) : new FormData(form);
            } catch (error) {
                payload = new FormData(form);
                if (submitter && submitter.name) {
                    payload.append(submitter.name, submitter.value);
                }
            }

            submitButtons.forEach(function (button) {
                button.disabled = true;
            });

            var action = form.getAttribute('action') || window.location.href;
            var method = (form.getAttribute('method') || 'POST').toUpperCase();

            fetch(action, {
                method: method,
                body: payload,
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            }).then(function (response) {
                return response.text().then(function (html) {
                    if (isLoginUrl(response.url)) {
                        window.location.assign(response.url);
                        return;
                    }
                    if (response.redirected) {
                        modal.hide();
                        window.location.assign(response.url);
                        return;
                    }
                    insertContent(html);
                });
            }).catch(function () {
                form.dataset.submitting = '0';
                submitButtons.forEach(function (button) {
                    button.disabled = false;
                });
                if (submitter) {
                    submitter.innerHTML = originalHtml;
                }
                alertBox('L’enregistrement a échoué. Vérifiez votre connexion puis réessayez.');
            });
        });

        modalElement.addEventListener('hidden.bs.modal', function () {
            requestToken++;
            body.innerHTML = '<div class="quick-loading"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span><span>Chargement du formulaire…</span></div>';
            title.textContent = 'Nouveau';
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var closeSidebar = setupSidebar();
        setupKeyboard(closeSidebar);
        enhance(document, true);
        setupModals();
    });

    window.SuiviStock = {
        enhance: function (root) {
            enhance(root || document, false);
        }
    };
})();
