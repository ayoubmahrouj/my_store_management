/* =========================================================================
   Boutons « + » de création rapide à côté de chaque liste déroulante.
   Usage : <select name="brand_id" data-quick="brand">…</select>
   ========================================================================= */
(function () {
    'use strict';

    var ENTITIES = {
        brand: {
            title: 'Nouvelle marque',
            fields: [
                { name: 'name', label: 'Nom de la marque', required: true }
            ]
        },
        category: {
            title: 'Nouvelle catégorie',
            fields: [
                { name: 'name', label: 'Nom de la catégorie', required: true }
            ]
        },
        unit: {
            title: 'Nouvelle unité',
            fields: [
                { name: 'label', label: 'Libellé (ex. Pièce, Carton)', required: true },
                { name: 'code', label: 'Code court (ex. PCS, CTN)', required: true }
            ]
        },
        supplier: {
            title: 'Nouveau fournisseur',
            fields: [
                { name: 'name', label: 'Nom du fournisseur', required: true },
                { name: 'ice', label: 'ICE' },
                { name: 'phone', label: 'Téléphone', type: 'tel' },
                { name: 'email', label: 'E-mail', type: 'email' },
                { name: 'address', label: 'Adresse' }
            ]
        },
        location: {
            title: 'Nouveau site',
            fields: [
                { name: 'name', label: 'Nom du site', required: true },
                { name: 'code', label: 'Code (ex. DEP01)', required: true },
                { name: 'type', label: 'Type', type: 'select', options: [['depot', 'Dépôt'], ['point_vente', 'Point de vente']], required: true },
                { name: 'address', label: 'Adresse' },
                { name: 'phone', label: 'Téléphone', type: 'tel' }
            ]
        },
        client: {
            title: 'Nouveau client',
            fields: [
                { name: 'name', label: 'Nom / raison sociale', required: true },
                { name: 'type', label: 'Type', type: 'select', options: [['PERSONNE', 'Personne'], ['SOCIETE', 'Société']], required: true },
                { name: 'phone', label: 'Téléphone', type: 'tel' },
                { name: 'email', label: 'E-mail', type: 'email' },
                { name: 'ice', label: 'ICE' },
                { name: 'address', label: 'Adresse' },
                { name: 'credit_limit', label: 'Plafond de crédit', type: 'number', step: '0.01', value: '0' }
            ]
        },
        societe: {
            title: 'Nouvelle société',
            fields: [
                { name: 'name', label: 'Nom / raison sociale', required: true },
                { name: 'ice', label: 'ICE' },
                { name: 'phone', label: 'Téléphone', type: 'tel' },
                { name: 'email', label: 'E-mail', type: 'email' },
                { name: 'address', label: 'Adresse' },
                { name: 'credit_limit', label: 'Plafond de crédit', type: 'number', step: '0.01', value: '0' }
            ]
        },
        product: {
            title: 'Nouveau produit',
            fields: [
                { name: 'designation', label: 'Désignation', required: true },
                { name: 'sku', label: 'Référence / SKU' },
                { name: 'sale_price', label: 'Prix de vente', type: 'number', step: '0.01', value: '0' },
                { name: 'purchase_cost', label: 'Prix d\'achat / coût', type: 'number', step: '0.01', value: '0' },
                { name: 'min_stock', label: 'Seuil d\'alerte', type: 'number', step: '0.001', value: '0' }
            ]
        },
        carnet: {
            title: 'Nouveau carnet / registre',
            fields: [
                { name: 'libelle', label: 'Libellé', required: true },
                { name: 'banque', label: 'Banque' },
                { name: 'type', label: 'Type', type: 'select', options: [['CHEQUE', 'Chèques'], ['EFFET', 'Effets']], required: true }
            ]
        },
        personne: {
            title: 'Nouvelle personne responsable',
            fields: [
                { name: 'nom', label: 'Nom complet', required: true },
                { name: 'role', label: 'Rôle', type: 'select', options: [['EMPLOYE', 'Employé'], ['GERANT', 'Gérant'], ['PROPRIETAIRE_SOCIETE', 'Propriétaire / société']], required: true },
                { name: 'telephone', label: 'Téléphone', type: 'tel' },
                { name: 'email', label: 'E-mail', type: 'email' },
                { name: 'adresse', label: 'Adresse' }
            ]
        }
    };

    var modal = document.getElementById('gosQuickModal');
    var form = document.getElementById('gosQuickForm');
    var fieldsBox = document.getElementById('gosQuickFields');
    var titleEl = document.getElementById('gosQuickTitle');
    var errorEl = document.getElementById('gosQuickError');
    var submitBtn = document.getElementById('gosQuickSubmit');
    if (!modal || !form || !fieldsBox) return;

    var activeEntity = null;
    var activeSelect = null;

    function csrfToken() {
        var meta = document.querySelector('meta[name="gos-csrf"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function openModal(entity, trigger) {
        var config = ENTITIES[entity];
        if (!config) return;
        activeEntity = entity;
        activeSelect = trigger || null;
        titleEl.textContent = config.title;
        fieldsBox.innerHTML = '';
        errorEl.hidden = true;

        config.fields.forEach(function (field) {
            var wrap = document.createElement('div');
            wrap.className = 'gos-form-field';
            var label = document.createElement('label');
            label.textContent = field.label;
            wrap.appendChild(label);

            var input;
            if (field.type === 'select') {
                input = document.createElement('select');
                (field.options || []).forEach(function (opt) {
                    var option = document.createElement('option');
                    option.value = opt[0];
                    option.textContent = opt[1];
                    input.appendChild(option);
                });
                if (field.options_source === 'personnes') {
                    input.setAttribute('data-quick', 'personne');
                }
            } else if (field.type === 'textarea') {
                input = document.createElement('textarea');
                input.rows = 3;
            } else {
                input = document.createElement('input');
                input.type = field.type || 'text';
                if (field.step) input.step = field.step;
                if (field.value !== undefined) input.value = field.value;
            }
            input.name = field.name;
            if (field.required) input.required = true;
            wrap.appendChild(input);
            fieldsBox.appendChild(wrap);
        });

        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        var first = fieldsBox.querySelector('input, select, textarea');
        if (first) setTimeout(function () { first.focus(); }, 40);
    }

    function closeModal() {
        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        activeEntity = null;
        activeSelect = null;
        form.reset();
        fieldsBox.innerHTML = '';
    }

    modal.addEventListener('click', function (event) {
        if (event.target.closest('[data-quick-close]')) closeModal();
    });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && !modal.hidden) closeModal();
    });

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        if (!activeEntity) return;
        var payload = { entity: activeEntity };
        new FormData(form).forEach(function (value, key) {
            payload[key] = value;
        });

        submitBtn.disabled = true;
        submitBtn.textContent = 'Enregistrement…';
        errorEl.hidden = true;

        fetch(document.body.dataset.quickUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            body: JSON.stringify(payload)
        }).then(function (response) {
            return response.json().catch(function () {
                throw new Error('Réponse invalide du serveur.');
            });
        }).then(function (data) {
            if (!data.ok) throw new Error(data.error || 'Création impossible.');
            applyCreated(activeEntity, data.item);
            closeModal();
            toast('« ' + data.item.label + ' » a été ajouté.', 'success');
        }).catch(function (error) {
            errorEl.textContent = error.message || 'Une erreur est survenue.';
            errorEl.hidden = false;
        }).finally(function () {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Enregistrer';
        });
    });

    function applyCreated(entity, item) {
        // Met à jour toutes les listes déroulantes de cette entité sur la page.
        document.querySelectorAll('select[data-quick="' + entity + '"]').forEach(function (select) {
            var exists = false;
            Array.prototype.forEach.call(select.options, function (option) {
                if (String(option.value) === String(item.id)) exists = true;
            });
            if (!exists) {
                var option = document.createElement('option');
                option.value = item.id;
                option.textContent = item.label;
                select.appendChild(option);
            }
            select.value = String(item.id);
            select.dispatchEvent(new Event('change', { bubbles: true }));
        });
        if (activeSelect) activeSelect.value = String(item.id);
        document.dispatchEvent(new CustomEvent('gos:quick-created', { detail: { entity: entity, item: item } }));
    }

    function toast(message, type) {
        var el = document.createElement('div');
        el.className = 'gos-alert gos-alert-' + (type || 'success');
        el.style.cssText = 'position:fixed;right:20px;bottom:20px;z-index:200;margin:0;max-width:360px;box-shadow:0 20px 50px rgba(0,0,0,.35)';
        el.innerHTML = '<span></span><button type="button" aria-label="Fermer">&times;</button>';
        el.querySelector('span').textContent = message;
        el.querySelector('button').addEventListener('click', function () { el.remove(); });
        document.body.appendChild(el);
        setTimeout(function () { el.remove(); }, 4200);
    }

    function enhance(select) {
        if (!select || select.dataset.quickDone === '1') return;
        var entity = select.getAttribute('data-quick');
        if (!entity || !ENTITIES[entity]) return;
        if (select.closest('.gos-quick-wrap')) {
            select.dataset.quickDone = '1';
            return;
        }
        var wrap = document.createElement('span');
        wrap.className = 'gos-quick-wrap';
        select.parentNode.insertBefore(wrap, select);
        wrap.appendChild(select);

        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'gos-quick-add';
        button.title = 'Créer un nouvel élément';
        button.setAttribute('aria-label', 'Nouveau');
        button.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>';
        button.addEventListener('click', function (event) {
            event.preventDefault();
            openModal(entity, select);
        });
        wrap.appendChild(button);
        select.dataset.quickDone = '1';
    }

    function enhanceAll(root) {
        (root || document).querySelectorAll('select[data-quick]').forEach(enhance);
    }

    enhanceAll(document);

    // Les modules ajoutent parfois des lignes dynamiquement (vente, articles…).
    var observer = new MutationObserver(function (mutations) {
        mutations.forEach(function (mutation) {
            mutation.addedNodes.forEach(function (node) {
                if (node.nodeType !== 1) return;
                if (node.matches && node.matches('select[data-quick]')) enhance(node);
                if (node.querySelectorAll) enhanceAll(node);
            });
        });
    });
    observer.observe(document.body, { childList: true, subtree: true });
})();
