jQuery(function ($) {
  console.log('SM_INV_FIXED admin.js LOADED', window.location.href);

  function renderPreview($wrap, url) {
    $wrap.find('img.sm-inv-fixed-thumb').remove();
    if (url) {
      $('<img/>', { class: 'sm-inv-fixed-thumb', src: url, alt: '' }).prependTo($wrap);
    }
  }

  $(document).on('click', '.sm-inv-fixed-pick', function (e) {
    e.preventDefault();
    var $wrap = $(this).closest('.sm-inv-fixed-media');
    var field = $wrap.data('field');
    // For repeater rows we may have a complex name like additional_products[0][icon]
    // We still keep it in data-field, but also support data-input if you prefer.
    var inputName = $wrap.data('input') || field;
    var frame = wp.media({
      title: 'Wybierz plik',
      button: { text: 'Użyj' },
      multiple: false
    });
    frame.on('select', function () {
      var attachment = frame.state().get('selection').first().toJSON();
      $wrap.find('input[type="hidden"][name="' + inputName + '"]').val(attachment.id);
      renderPreview($wrap, attachment.url);
    });
    frame.open();
  });

  $(document).on('click', '.sm-inv-fixed-clear', function (e) {
    e.preventDefault();
    var $wrap = $(this).closest('.sm-inv-fixed-media');
    var field = $wrap.data('field');
    var inputName = $wrap.data('input') || field;
    $wrap.find('input[type="hidden"][name="' + inputName + '"]').val('0');
    renderPreview($wrap, null);
  });

  // ---- Repeater: Additional products ----

  $(document).on('click', '.sm-inv-fixed-add-row', function (e) {
    e.preventDefault();
    var $repeater = $(this).closest('.sm-inv-fixed-repeater');
    var $tbody = $repeater.find('.sm-inv-fixed-repeater-rows');
    // Template can be stored in a <script> tag. In some admin setups, the
    // selector may fail (e.g. DOM moved by the browser) – so we provide a
    // couple of fallbacks.
    var tpl = $repeater.find('script.sm-inv-fixed-repeater-template').first().html();
    if (!tpl) {
      tpl = $repeater.find('#sm-inv-fixed-repeater-template').first().html();
    }
    if (!tpl) {
      // Last resort: look for a data attribute.
      tpl = $repeater.attr('data-template') || '';
    }
    if (!tpl) {
      tpl = $('script.sm-inv-fixed-repeater-template').first().html() || '';
    }

    if (!tpl) {
      // If nothing is found, don't throw a JS error – just no-op.
      // (Open browser console to diagnose.)
      return;
    }

    // next index: max existing + 1
    var maxIdx = -1;
    $tbody.find('.sm-inv-fixed-repeater-row').each(function () {
      var idx = parseInt($(this).attr('data-index'), 10);
      if (!isNaN(idx) && idx > maxIdx) maxIdx = idx;
    });
    var next = maxIdx + 1;

    tpl = tpl.replace(/__i__/g, next);
    $tbody.append($.trim(tpl));
  });

  $(document).on('click', '.sm-inv-fixed-remove-row', function (e) {
    e.preventDefault();
    var $row = $(this).closest('.sm-inv-fixed-repeater-row');
    var $tbody = $row.closest('.sm-inv-fixed-repeater-rows');
    $row.remove();

    // If no rows left, add one empty
    if ($tbody.find('.sm-inv-fixed-repeater-row').length === 0) {
      $tbody.closest('.sm-inv-fixed-repeater').find('.sm-inv-fixed-add-row').trigger('click');
    }
  });

  // ---- Gallery (multiple images -> CSV of attachment IDs) ----

  function parseCsvIds(csv) {
    csv = (csv || '').toString();
    var m = csv.match(/\d+/g);
    if (!m) return [];
    return m.map(function (v) { return parseInt(v, 10); }).filter(function (v) { return v > 0; });
  }

  function renderGalleryPreview($wrap, ids) {
    var $preview = $wrap.find('.sm-inv-fixed-gallery-preview');
    $preview.empty();

    // We don't have URLs for existing IDs without extra AJAX.
    // For newly selected items we render from attachment URLs.
    // If you want live preview for existing CSV, simplest is to re-pick once.
    if (!ids || !ids.length) {
      return;
    }

    // Try to render previews using wp.media.attachment(id).get('url') (works when the model is cached).
    ids.forEach(function (id) {
      try {
        var att = wp.media.attachment(id);
        att.fetch();
        att.on('change', function () {
          var url = att.get('url');
          if (url) {
            $('<img/>', { class: 'sm-inv-fixed-thumb', src: url, alt: '' }).appendTo($preview);
          }
        });
      } catch (err) { }
    });
  }

  $(document).on('click', '.sm-inv-fixed-pick-gallery', function (e) {
    e.preventDefault();
    var $wrap = $(this).closest('.sm-inv-fixed-gallery');
    var field = $wrap.data('field');
    var $textarea = $wrap.find('textarea[name="' + field + '"]');

    var frame = wp.media({
      title: 'Wybierz zdjęcia do galerii',
      button: { text: 'Użyj' },
      multiple: true,
      library: { type: 'image' }
    });

    frame.on('select', function () {
      var selection = frame.state().get('selection');
      var ids = [];
      var urls = [];
      selection.each(function (model) {
        var json = model.toJSON();
        ids.push(json.id);
        if (json.url) urls.push(json.url);
      });
      $textarea.val(ids.join(','));

      // Render preview from selected URLs
      var $preview = $wrap.find('.sm-inv-fixed-gallery-preview');
      $preview.empty();
      urls.forEach(function (url) {
        $('<img/>', { class: 'sm-inv-fixed-thumb', src: url, alt: '' }).appendTo($preview);
      });
    });

    frame.open();
  });

  $(document).on('click', '.sm-inv-fixed-clear-gallery', function (e) {
    e.preventDefault();
    var $wrap = $(this).closest('.sm-inv-fixed-gallery');
    var field = $wrap.data('field');
    $wrap.find('textarea[name="' + field + '"]').val('');
    $wrap.find('.sm-inv-fixed-gallery-preview').empty();
  });

  // Initial render for gallery fields (best-effort)
  $('.sm-inv-fixed-gallery').each(function () {
    var $wrap = $(this);
    var field = $wrap.data('field');
    var ids = parseCsvIds($wrap.find('textarea[name="' + field + '"]').val());
    renderGalleryPreview($wrap, ids);
  });
  $('#sm-refresh-poi').on('click', function () {

    const btn = $(this);
    const status = $('#sm-poi-status');
    const investmentId = btn.data('investment-id');

    btn.prop('disabled', true);
    status.text('Pobieranie POI...');

    $.post(SM_INV_ADMIN.ajaxurl, {
      action: 'sm_inv_refresh_poi',
      investment_id: investmentId,
      _ajax_nonce: SM_INV_ADMIN.nonce
    }, function (response) {

      if (response.success) {
        status.html('✅ Pobrano <strong>' + response.data.count + '</strong> POI');
      } else {
        status.html('❌ ' + response.data);
      }

      btn.prop('disabled', false);
    });

  });

});
