jQuery(function($){
    var settings = window.bwkAdminData || {};
    settings.i18n = settings.i18n || {};
    var productsEnabled = settings.productsEnabled !== false;

    function htmlEscape(str) {
        return String(str === undefined ? '' : str)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    function rowTemplate() {
        var label = htmlEscape(settings.i18n.useProductLabel || 'Use existing product');
        var placeholder = htmlEscape(settings.i18n.searchPlaceholder || 'Search for a product…');
        var help = htmlEscape(settings.i18n.searchHelp || 'Start typing to search WooCommerce products.');
        var prompt = htmlEscape(settings.i18n.selectPrompt || 'Select a product');
        return '<tr class="bwk-item-row">'
            + '<td>'
            + '<input type="text" name="item_name[]" />'
            + '<input type="hidden" class="bwk-product-id" name="product_id[]" value="" />'
            + '<input type="hidden" class="bwk-product-sku" name="product_sku[]" value="" />'
            + '<div class="bwk-product-toggle">'
            + '<label><input type="checkbox" class="bwk-use-product" /> ' + label + '</label>'
            + '<div class="bwk-product-picker">'
            + '<input type="search" class="bwk-product-search" placeholder="' + placeholder + '" autocomplete="off" />'
            + '<select class="bwk-product-select">'
            + ( prompt ? '<option value="">' + prompt + '</option>' : '' )
            + '</select>'
            + ( help ? '<p class="description">' + help + '</p>' : '' )
            + '</div>'
            + '</div>'
            + '</td>'
            + '<td><input type="number" step="0.01" name="qty[]" class="bwk-qty" /></td>'
            + '<td><input type="number" step="0.01" name="unit_price[]" class="bwk-price" /></td>'
            + '<td class="bwk-line-total">0</td>'
            + '<td><button type="button" class="button bwk-remove">&times;</button></td>'
            + '</tr>';
    }

    function updateTotals(){
        var subtotal = 0;
        $('#bwk-items-table tbody tr').each(function(){
            var $row = $(this);
            var qty = parseFloat($row.find('.bwk-qty').val()) || 0;
            var price = parseFloat($row.find('.bwk-price').val()) || 0;
            var total = qty * price;
            $row.find('.bwk-line-total').text(total.toFixed(2));
            subtotal += total;
        });
        $('#subtotal').val(subtotal.toFixed(2));
        var discount = parseFloat($('#discount_total').val()) || 0;
        var tax = parseFloat($('#tax_total').val()) || 0;
        var shipping = parseFloat($('#shipping_total').val()) || 0;
        var grand = subtotal - discount + tax + shipping;
        $('#grand_total').val(grand.toFixed(2));
    }

    function togglePicker($row, show) {
        var $picker = $row.find('.bwk-product-picker');
        if ( show ) {
            $picker.addClass('is-active');
        } else {
            $picker.removeClass('is-active');
        }
    }

    function resetSelect($select) {
        $select.empty();
        var prompt = settings.i18n.selectPrompt || 'Select a product';
        if ( prompt ) {
            $select.append($('<option>').val('').text(prompt));
        }
    }

    function setSelectMessage($select, message) {
        $select.empty();
        if ( message ) {
            $select.append($('<option>').val('').text(message).prop('disabled', true));
        }
    }

    function populateSelect($select, items) {
        resetSelect($select);
        if (!items || !items.length) {
            if ( settings.i18n.noResults ) {
                $select.append($('<option>').val('').text(settings.i18n.noResults).prop('disabled', true));
            }
            return;
        }
        $.each(items, function(_, item){
            var id = item.id;
            if (!id) {
                return;
            }
            var name = item.name || '';
            var sku = item.sku || '';
            var label = name;
            if ( sku ) {
                label += ' (' + sku + ')';
            }
            var $option = $('<option>').val(id).text(label).attr('data-name', name);
            if ( sku ) {
                $option.attr('data-sku', sku);
            }
            if ( item.price !== undefined && item.price !== null && item.price !== '' ) {
                $option.attr('data-price', item.price);
            }
            $select.append($option);
        });
    }

    function resetProductFields($row) {
        $row.find('.bwk-product-id').val('');
        $row.find('.bwk-product-sku').val('');
        $row.find('.bwk-product-search').val('');
    }

    function extractItems(response) {
        if (!response) {
            return [];
        }
        if (Array.isArray(response)) {
            return response;
        }
        if (response.items && Array.isArray(response.items)) {
            return response.items;
        }
        if (response.data) {
            if (Array.isArray(response.data)) {
                return response.data;
            }
            if (response.data.items && Array.isArray(response.data.items)) {
                return response.data.items;
            }
        }
        return [];
    }

    function fetchProducts(term, $select) {
        var request;
        var searchingText = settings.i18n.searching || 'Searching…';
        var errorText = settings.i18n.error || 'Unable to load products.';
        setSelectMessage($select, searchingText);

        if ( settings.restUrl && settings.restNonce ) {
            request = $.ajax({
                url: settings.restUrl,
                method: 'GET',
                dataType: 'json',
                data: { term: term },
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', settings.restNonce);
                }
            });
        } else if ( settings.ajaxUrl && settings.searchNonce ) {
            request = $.ajax({
                url: settings.ajaxUrl,
                method: 'GET',
                dataType: 'json',
                data: {
                    action: 'bwk_search_products',
                    nonce: settings.searchNonce,
                    term: term
                }
            });
        }

        if ( ! request ) {
            setSelectMessage($select, errorText);
            return;
        }

        request.done(function(response){
            if ( response && response.success === false ) {
                var message = errorText;
                if ( response.data && response.data.message ) {
                    message = response.data.message;
                }
                setSelectMessage($select, message);
                return;
            }
            var items = extractItems(response);
            if ( items.length ) {
                populateSelect($select, items);
            } else {
                populateSelect($select, []);
            }
        }).fail(function(jqXHR){
            var message = errorText;
            if ( jqXHR && jqXHR.responseJSON && jqXHR.responseJSON.message ) {
                message = jqXHR.responseJSON.message;
            }
            setSelectMessage($select, message);
        });
    }

    $('#bwk-add-row').on('click', function(){
        $('#bwk-items-table tbody').append(rowTemplate());
        updateTotals();
    });

    $(document).on('click','.bwk-remove',function(){
        $(this).closest('tr').remove();
        updateTotals();
    });

    $(document).on('change', '.bwk-use-product', function(){
        var $checkbox = $(this);
        var $row = $checkbox.closest('tr');
        if ( $checkbox.is(':checked') ) {
            if ( ! productsEnabled ) {
                if ( settings.i18n.noWooCommerce ) {
                    window.alert(settings.i18n.noWooCommerce);
                }
                $checkbox.prop('checked', false);
                return;
            }
            togglePicker($row, true);
            $row.find('.bwk-product-search').trigger('focus');
        } else {
            togglePicker($row, false);
            resetProductFields($row);
            resetSelect($row.find('.bwk-product-select'));
        }
    });

    $(document).on('input', '.bwk-product-search', function(){
        var $input = $(this);
        var $row = $input.closest('tr');
        var $select = $row.find('.bwk-product-select');
        var term = $.trim($input.val());
        var timerId = $input.data('bwkTimer');
        if ( timerId ) {
            window.clearTimeout(timerId);
        }
        if ( term.length < 2 ) {
            resetSelect($select);
            $input.data('bwkTimer', null);
            return;
        }
        var newTimer = window.setTimeout(function(){
            fetchProducts(term, $select);
        }, 300);
        $input.data('bwkTimer', newTimer);
    });

    $(document).on('change', '.bwk-product-select', function(){
        var $select = $(this);
        var $row = $select.closest('tr');
        var productId = parseInt($select.val(), 10);
        if ( ! productId ) {
            return;
        }
        var $option = $select.find('option:selected');
        var label = $option.text() || '';
        var name = $option.data('name') || '';
        var price = parseFloat($option.data('price'));
        var sku = $option.data('sku') || '';
        if ( name ) {
            $row.find('input[name="item_name[]"]').val(name);
        }
        if ( ! isNaN(price) ) {
            $row.find('input[name="unit_price[]"]').val(price.toFixed(2));
        }
        var $qty = $row.find('.bwk-qty');
        if ( ! $qty.val() ) {
            $qty.val('1');
        }
        $row.find('.bwk-product-id').val(productId);
        $row.find('.bwk-product-sku').val(sku);
        if ( label ) {
            $row.find('.bwk-product-search').val(label);
        }
        updateTotals();
    });

    $(document).on('input','.bwk-qty,.bwk-price,#discount_total,#tax_total,#shipping_total',updateTotals);
});
