jQuery(function($){
    $('#bwk-add-row').on('click', function(){
        var row = '<tr><td><input type="text" name="item_name[]" /><input type="hidden" class="bwk-product-id" name="product_id[]" value="" /><input type="hidden" class="bwk-product-sku" name="product_sku[]" value="" /></td><td><input type="number" step="0.01" name="qty[]" class="bwk-qty" /></td><td><input type="number" step="0.01" name="unit_price[]" class="bwk-price" /></td><td class="bwk-line-total">0</td><td><button type="button" class="button bwk-remove">&times;</button></td></tr>';
        $('#bwk-items-table tbody').append(row);
    });
    $(document).on('click','.bwk-remove',function(){
        $(this).closest('tr').remove();
    });
    function updateTotals(){
        var subtotal=0;
        $('#bwk-items-table tbody tr').each(function(){
            var qty=parseFloat($(this).find('.bwk-qty').val())||0;
            var price=parseFloat($(this).find('.bwk-price').val())||0;
            var total=qty*price;
            $(this).find('.bwk-line-total').text(total.toFixed(2));
            subtotal+=total;
        });
        $('#subtotal').val(subtotal.toFixed(2));
        var discount=parseFloat($('#discount_total').val())||0;
        var tax=parseFloat($('#tax_total').val())||0;
        var shipping=parseFloat($('#shipping_total').val())||0;
        var grand=subtotal-discount+tax+shipping;
        $('#grand_total').val(grand.toFixed(2));
    }
    $(document).on('input','.bwk-qty,.bwk-price,#discount_total,#tax_total,#shipping_total',updateTotals);
});
