jQuery(function($){
    var settings = window.bwkAdmin || {};
    var searchPlaceholder = settings.i18nSearchProducts || 'Search for a product…';
    var itemPlaceholder = settings.i18nItemName || '';

    function createItemRow() {
        var $row = $('<tr/>');
        var $itemCell = $('<td/>');
        var $itemWrapper = $('<div/>', { 'class': 'bwk-item-field' });
        var $productSelect = $('<select/>', {
            'class': 'bwk-product-search',
            name: 'product_id[]',
            'data-placeholder': searchPlaceholder,
            'data-allow_clear': 'true',
            'data-action': 'woocommerce_json_search_products_and_variations',
            style: 'width: 100%;'
        });

        $itemWrapper.append($productSelect);
        $itemWrapper.append(
            $('<input/>', {
                type: 'text',
                name: 'item_name[]',
                'class': 'bwk-item-name',
                autocomplete: 'off',
                placeholder: itemPlaceholder
            })
        );

        $itemCell.append($itemWrapper);
        $row.append($itemCell);

        $row.append(
            $('<td/>').append(
                $('<input/>', {
                    type: 'number',
                    step: '0.01',
                    name: 'qty[]',
                    'class': 'bwk-qty'
                })
            )
        );

        $row.append(
            $('<td/>').append(
                $('<input/>', {
                    type: 'number',
                    step: '0.01',
                    name: 'unit_price[]',
                    'class': 'bwk-price'
                })
            )
        );

        $row.append($('<td/>', { 'class': 'bwk-line-total', text: '0.00' }));
        $row.append(
            $('<td/>').append(
                $('<button/>', {
                    type: 'button',
                    'class': 'button bwk-remove',
                    text: '×'
                })
            )
        );

        return $row;
    }

    $('#bwk-add-row').on('click', function(){
        var $row = createItemRow();
        $('#bwk-items-table tbody').append($row);
        $(document.body).trigger('wc-enhanced-select-init');
        updateTotals();
    });

    $(document).on('click', '.bwk-remove', function(){
        $(this).closest('tr').remove();
        updateTotals();
    });

    function updateTotals(){
        var subtotal = 0;
        $('#bwk-items-table tbody tr').each(function(){
            var qty = parseFloat($(this).find('.bwk-qty').val()) || 0;
            var price = parseFloat($(this).find('.bwk-price').val()) || 0;
            var total = qty * price;
            $(this).find('.bwk-line-total').text(total.toFixed(2));
            subtotal += total;
        });
        $('#subtotal').val(subtotal.toFixed(2));
        var discount = parseFloat($('#discount_total').val()) || 0;
        var tax = parseFloat($('#tax_total').val()) || 0;
        var shipping = parseFloat($('#shipping_total').val()) || 0;
        var grand = subtotal - discount + tax + shipping;
        $('#grand_total').val(grand.toFixed(2));
    }

    $(document).on('input', '.bwk-qty,.bwk-price,#discount_total,#tax_total,#shipping_total', updateTotals);

    $(document).on('change', '.bwk-product-search', function(){
        var productId = $(this).val();
        var $row = $(this).closest('tr');

        if (!productId || !settings.ajaxUrl) {
            return;
        }

        $.ajax({
            url: settings.ajaxUrl,
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'bwk_wc_product_details',
                product_id: productId,
                nonce: settings.productNonce || ''
            }
        }).done(function(response){
            if (response && response.success && response.data) {
                if (typeof response.data.name !== 'undefined') {
                    $row.find('.bwk-item-name').val(response.data.name);
                }
                if (typeof response.data.price !== 'undefined' && response.data.price !== null) {
                    $row.find('.bwk-price').val(response.data.price);
                }
                updateTotals();
            }
        }).fail(function(){
            if ( window.console && window.console.error ) {
                window.console.error('Unable to load product details for invoice item.');
            }
        });
    });

    $(document.body).trigger('wc-enhanced-select-init');
});
