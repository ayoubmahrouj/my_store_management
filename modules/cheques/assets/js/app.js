/* global jQuery, GC */
(function ($) {
  'use strict';

  var L = (GC && GC.labels) || {};
  var I = (GC && GC.i18n) || {};
  function normalizeKey(value) {
    return String(value).toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
  }
  function tr(key, fallback) {
    if (I[key]) { return I[key]; }
    if (L[key]) { return L[key]; }
    var normalized = normalizeKey(key);
    for (var candidate in L) {
      if (normalizeKey(candidate) === normalized) { return L[candidate]; }
    }
    return fallback || key;
  }

  /* ---------------------------------------------------------------- */
  /* Sidebar mobile                                                    */
  /* ---------------------------------------------------------------- */
  $(document).on('click', '#sidebarToggle', function () {
    $('#sidebar').toggleClass('open');
    $('#sidebarOverlay').toggleClass('open');
  });
  $(document).on('click', '#sidebarOverlay', function () {
    $('#sidebar').removeClass('open');
    $('#sidebarOverlay').removeClass('open');
  });

  /* ---------------------------------------------------------------- */
  /* Toasts                                                            */
  /* ---------------------------------------------------------------- */
  function dismissToast($t) {
    $t.fadeOut(150, function () { $(this).remove(); });
  }
  $(document).on('click', '.toast-close', function () {
    dismissToast($(this).closest('.toast'));
  });
  $('.toast[data-auto-dismiss]').each(function () {
    var $t = $(this);
    var delay = parseInt($t.attr('data-auto-dismiss'), 10) || 6000;
    setTimeout(function () { dismissToast($t); }, delay);
  });

  /* ---------------------------------------------------------------- */
  /* Confirmations                                                     */
  /* ---------------------------------------------------------------- */
  $(document).on('click', '[data-confirm]', function (e) {
    var msg = $(this).attr('data-confirm') || tr('confirmDelete', 'Confirmer ?');
    if (!window.confirm(msg)) {
      e.preventDefault();
      return false;
    }
  });

  /* ---------------------------------------------------------------- */
  /* Filtres : soumission automatique                                  */
  /* ---------------------------------------------------------------- */
  $(document).on('change', 'form[data-auto-submit] select, form[data-auto-submit] input[type="date"], form[data-auto-submit] input[type="month"], form[data-auto-submit] input[type="checkbox"]', function () {
    $(this).closest('form').trigger('submit');
  });

  /* ---------------------------------------------------------------- */
  /* Afficher / masquer le mot de passe                                */
  /* ---------------------------------------------------------------- */
  $(document).on('click', '.password-toggle', function () {
    var $input = $(this).closest('.password-wrap').find('input');
    var show = $input.attr('type') === 'password';
    $input.attr('type', show ? 'text' : 'password');
    $(this).toggleClass('is-visible', show);
  });

  /* ---------------------------------------------------------------- */
  /* Modale générique (création rapide)                                */
  /* ---------------------------------------------------------------- */
  function openModal() {
    $('#quickCreateModal').prop('hidden', false);
    $('body').css('overflow', 'hidden');
  }
  function closeModal() {
    $('#quickCreateModal').prop('hidden', true);
    $('body').css('overflow', '');
  }
  $(document).on('click', '[data-close-modal]', closeModal);
  $(document).on('click', '#quickCreateModal', function (e) {
    if (e.target === this) { closeModal(); }
  });
  $(document).on('keydown', function (e) {
    if (e.key === 'Escape' && !$('#quickCreateModal').prop('hidden')) { closeModal(); }
  });

  var QC_TITLES = {
    personne: 'Nouvelle personne responsable',
    beneficiaire: 'Nouveau bénéficiaire',
    carnet: 'Nouveau carnet / registre'
  };

  function optionHtml(value, label) {
    return $('<option>').val(value).text(label);
  }

  function fieldsFor(type) {
    var html = '';
    if (type === 'personne') {
      html += '<div class="field"><label for="qc-nom">' + tr('Nom', 'Nom') + ' <span class="req">*</span></label>' +
        '<input id="qc-nom" name="nom" class="input" required maxlength="150"></div>' +
        '<div class="field mt-16"><label for="qc-role">' + tr('Rôle', 'Rôle') + '</label>' +
        '<select id="qc-role" name="role" class="select">' +
        '<option value="PROPRIETAIRE_SOCIETE">' + tr('Propriétaire', 'Propriétaire') + '</option>' +
        '<option value="GERANT">' + tr('Gérant', 'Gérant') + '</option>' +
        '<option value="EMPLOYE" selected>' + tr('Employé', 'Employé') + '</option>' +
        '</select></div>' +
        '<div class="field mt-16"><label for="qc-telephone">' + tr('Téléphone', 'Téléphone') + '</label>' +
        '<input id="qc-telephone" name="telephone" class="input" maxlength="30"></div>' +
        '<div class="field mt-16"><label for="qc-email">' + tr('Email', 'Email') + '</label>' +
        '<input id="qc-email" name="email" type="email" class="input" maxlength="150"></div>';
    } else if (type === 'beneficiaire') {
      html += '<div class="field"><label for="qc-nom">' + tr('Nom', 'Nom') + ' <span class="req">*</span></label>' +
        '<input id="qc-nom" name="nom" class="input" required maxlength="150"></div>' +
        '<div class="field mt-16"><label for="qc-type">' + tr('Type', 'Type') + '</label>' +
        '<select id="qc-type" name="type" class="select">' +
        '<option value="SOCIETE">' + tr('Société', 'Société') + '</option>' +
        '<option value="PERSONNE" selected>' + tr('Personne', 'Personne') + '</option>' +
        '</select></div>' +
        '<div class="field mt-16"><label for="qc-ice">ICE</label>' +
        '<input id="qc-ice" name="ice" class="input" maxlength="20"></div>' +
        '<div class="field mt-16"><label for="qc-telephone">' + tr('Téléphone', 'Téléphone') + '</label>' +
        '<input id="qc-telephone" name="telephone" class="input" maxlength="30"></div>' +
        '<div class="field mt-16"><label for="qc-email">' + tr('Email', 'Email') + '</label>' +
        '<input id="qc-email" name="email" type="email" class="input" maxlength="150"></div>' +
        '<div class="field mt-16"><label for="qc-adresse">' + tr('Adresse', 'Adresse') + '</label>' +
        '<input id="qc-adresse" name="adresse" class="input" maxlength="255"></div>';
    } else if (type === 'carnet') {
      var options = '';
      var $mainPersonne = $('#personneId');
      if ($mainPersonne.length) {
        $mainPersonne.find('option').each(function () {
          if ($(this).val()) {
            options += '<option value="' + $('<div>').text($(this).val()).html() + '">' +
              $('<div>').text($(this).text()).html() + '</option>';
          }
        });
      }
      html += '<div class="field"><label for="qc-libelle">' + tr('Référence', 'Référence') + ' <span class="req">*</span></label>' +
        '<input id="qc-libelle" name="libelle" class="input" required maxlength="100"></div>' +
        '<div class="field mt-16"><label for="qc-type">' + tr('Type', 'Type') + '</label>' +
        '<select id="qc-type" name="type" class="select">' +
        '<option value="CHEQUE">' + tr('Chèque', 'Chèque') + '</option>' +
        '<option value="EFFET">' + tr('Effet', 'Effet') + '</option>' +
        '</select></div>' +
        '<div class="field mt-16"><label for="qc-code">' + tr('Code', 'Code') + '</label>' +
        '<input id="qc-code" name="code" type="number" class="input" min="0" max="999999"></div>' +
        '<div class="field mt-16"><label for="qc-banque">' + tr('Banque', 'Banque') + '</label>' +
        '<input id="qc-banque" name="banque" class="input" maxlength="100"></div>' +
        '<div class="field mt-16"><label for="qc-personne">' + tr('Propriétaire', 'Propriétaire') + '</label>' +
        '<select id="qc-personne" name="personneId" class="select"><option value="">—</option>' + options + '</select></div>';
    }
    return html;
  }

  $(document).on('click', '[data-quick-create]', function () {
    var type = $(this).attr('data-quick-create');
    var target = $(this).attr('data-target') || '';
    var $form = $('#qcForm');
    $form[0].reset();
    $('#qcType').val(type);
    $('#qcFields').html(fieldsFor(type));
    $('#qcTitle').text(tr(
      type === 'personne' ? 'nouveauPersonne' : (type === 'carnet' ? 'nouveauCarnet' : 'nouveauBeneficiaire'),
      QC_TITLES[type] || tr('Création rapide', 'Création rapide')
    ));
    $form.data('target', target);
    $('#qcError').hide().text('');
    openModal();
    setTimeout(function () { $('#qc-nom, #qc-libelle').trigger('focus'); }, 60);
  });

  $('#qcForm').on('submit', function (e) {
    e.preventDefault();
    var $form = $(this);
    var $btn = $('#qcSubmit');
    var $error = $('#qcError');
    $error.hide().text('');
    $btn.prop('disabled', true).text(tr('saving', 'Enregistrement…'));

    $.ajax({
      url: (GC.base || '/') + 'api/quick_create.php',
      method: 'POST',
      dataType: 'json',
      data: $form.serialize()
    }).done(function (res) {
      if (!res || !res.ok) {
        $error.text((res && res.error) || tr('error', 'Une erreur est survenue.')).show();
        return;
      }
      var item = res.item;
      var $target = $($form.data('target'));
      if ($target.length) {
        var $opt = optionHtml(item.id, item.label);
        if (item.banque !== undefined) { $opt.attr('data-banque', item.banque || ''); }
        if (item.personneId !== undefined) { $opt.attr('data-personne', item.personneId || ''); }
        if (item.type !== undefined) { $opt.attr('data-type', item.type || ''); }
        $target.append($opt).val(String(item.id)).trigger('change');
      }
      // Si une liste de personnes existe dans la modale carnet, on l'actualise.
      if (res.type === 'personne') {
        $('#qc-personne').append(optionHtml(item.id, item.label)).val(String(item.id));
      }
      closeModal();
      if (window.GCToast) { window.GCToast('success', res.message || tr('create', 'Créé')); }
    }).fail(function (xhr) {
      var msg = tr('error', 'Une erreur est survenue.');
      try {
        var data = JSON.parse(xhr.responseText);
        if (data && data.error) { msg = data.error; }
      } catch (ignore) {}
      $error.text(msg).show();
    }).always(function () {
      $btn.prop('disabled', false).text(tr('create', 'Créer'));
    });
  });

  window.GCToast = function (type, message) {
    var $stack = $('#toastStack');
    if (!$stack.length) { return; }
    var icon = type === 'success' ? '✓' : (type === 'error' ? '!' : 'i');
    var $t = $('<div class="toast toast-' + type + '" data-auto-dismiss="6000">' +
      '<div class="toast-icon">' + icon + '</div>' +
      '<div class="toast-message"></div>' +
      '<button type="button" class="toast-close">×</button></div>');
    $t.find('.toast-message').text(message);
    $stack.append($t);
    setTimeout(function () { dismissToast($t); }, 6000);
  };

  /* ---------------------------------------------------------------- */
  /* Formulaire document : échéance, total du mois, carnet             */
  /* ---------------------------------------------------------------- */
  function isoAddMonths(iso, months) {
    if (!iso) { return ''; }
    var parts = iso.split('-');
    if (parts.length !== 3) { return ''; }
    var d = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
    var day = d.getDate();
    d.setMonth(d.getMonth() + months);
    if (d.getDate() < day) { d.setDate(0); }
    return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
  }
  function isoAddDays(iso, days) {
    if (!iso) { return ''; }
    var parts = iso.split('-');
    if (parts.length !== 3) { return ''; }
    var d = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
    d.setDate(d.getDate() + days);
    return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
  }
  function fmtMoney(n) {
    return (Number(n) || 0).toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ' DH';
  }

  var $emission = $('#dateEmission');
  var $echeance = $('#dateEcheance');
  var echeanceTouched = $echeance.val() !== '';

  $emission.on('change', function () {
    if (!$echeance.val() || !echeanceTouched) {
      $echeance.val(isoAddMonths($(this).val(), 3));
      echeanceTouched = false;
      refreshMonthTotal();
    }
  });

  $('#joursCalc').on('input change', function () {
    var n = parseInt($(this).val(), 10);
    if (!isNaN(n) && $emission.val()) {
      $echeance.val(isoAddDays($emission.val(), n));
      echeanceTouched = true;
      refreshMonthTotal();
    }
  });

  $echeance.on('change', function () {
    echeanceTouched = true;
    refreshMonthTotal();
  });

  function refreshMonthTotal() {
    var date = $echeance.val();
    var $info = $('#monthTotalInfo');
    if (!date || !$info.length) { return; }
    var mois = date.substring(0, 7);
    var exclude = $('#documentId').val() || '';
    var montant = parseFloat($('#montant').val()) || 0;
    $.getJSON((GC.base || '/') + 'api/mois_totals.php', { mois: mois, exclude_id: exclude })
      .done(function (res) {
        if (!res || !res.ok) { return; }
        var total = Number(res.total) + montant;
        var seuil = Number(res.seuil) || 0;
        var cls = total > seuil && seuil > 0 ? 'warn' : '';
        var html = '<div class="info-box ' + cls + '">' +
          '<div>' + tr('totalMois', 'Total du mois d’échéance') + ' <strong>' + fmtMoney(total) + '</strong>' +
          ' · ' + tr('moyenne', 'moyenne mensuelle') + ' <strong>' + fmtMoney(res.moyenne) + '</strong>' +
          (cls ? '<br><strong>' + tr('surcharge', 'Attention : ce mois dépasse nettement la moyenne.') + '</strong>' : '') +
          '</div></div>';
        $info.html(html);
      });
  }
  if ($('#monthTotalInfo').length) { refreshMonthTotal(); }

  $('#carnetId').on('change', function () {
    var $opt = $(this).find('option:selected');
    var banque = $opt.attr('data-banque') || '';
    var personne = $opt.attr('data-personne') || '';
    if (!$('#banque').val() && banque) { $('#banque').val(banque); }
    if (!$('#personneId').val() && personne) { $('#personneId').val(personne); }
  });

  /* Type de document : filtrer les carnets compatibles (optionnel) */
  $('input[name="type"], select[name="type"]').on('change', function () {
    var type = $(this).val();
    var $carnet = $('#carnetId');
    if (!$carnet.length) { return; }
    $carnet.find('option').each(function () {
      var optType = $(this).attr('data-type');
      if (!$(this).val() || !optType) { return; }
      var match = optType === type;
      $(this).prop('disabled', !match).toggle(match);
    });
    if ($carnet.find('option:selected').prop('disabled')) {
      $carnet.val('');
    }
  });

  /* ---------------------------------------------------------------- */
  /* Confirmation suppression (formulaires)                            */
  /* ---------------------------------------------------------------- */
  $(document).on('submit', 'form[data-confirm-submit]', function (e) {
    if (!window.confirm($(this).attr('data-confirm-submit'))) {
      e.preventDefault();
    }
  });

})(jQuery);
