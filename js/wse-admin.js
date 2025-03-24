jQuery(document).ready(function($) {
    toastr.options = {
        "closeButton": true,
        "progressBar": true,
        "positionClass": "toast-bottom-right",
        "timeOut": "2000",
        "extendedTimeOut": "500",
    };

    // Dark Mode Toggle
    $('#dark-mode-toggle').on('change', function() {
        if ($(this).is(':checked')) {
            $('body').addClass('dark');
            localStorage.setItem('wse_dark_mode', 'enabled');
        } else {
            $('body').removeClass('dark');
            localStorage.setItem('wse_dark_mode', 'disabled');
        }
    });

    if (localStorage.getItem('wse_dark_mode') === 'enabled' || wse_ajax_object.enable_dark_mode === 'yes') {
        $('body').addClass('dark');
        $('#dark-mode-toggle').prop('checked', true);
    }

    // Focus/Blur for Product Name Editing
    $(document).on('focus', '.product-name-input', function() {
        // Orijinal değeri saklayalım
        $(this).data('original-value', $(this).val());
    });

    $(document).on('blur', '.product-name-input', function() {
        var $input = $(this);
        var newName = $input.val();
        var productId = $input.data('product-id');
        var originalName = $input.data('original-value');

        // Değer değişmişse AJAX ile kaydet
        if (newName !== originalName) {
            if (confirm(wse_ajax_object.messages.update_success || "Changes detected. Do you want to save?")) {
                $.ajax({
                    url: wse_ajax_object.ajax_url,
                    method: 'POST',
                    data: {
                        action: 'wse_update_product',
                        nonce: wse_ajax_object.wse_nonce,
                        product_id: productId,
                        field: 'name',
                        value: newName,
                    },
                    beforeSend: function() {
                        $input.prop('disabled', true);
                    },
                    success: function(response) {
                        if (response.success) {
                            // Yanıt
                            var updated = response.data;
                            var $row = $('tr[data-product-id="' + updated.product_id + '"]');

                            // Yeni adı DOM'a uygula
                            if (updated.product_name) {
                                $row.find('.product-name-input').val(updated.product_name);
                            }

                            // 2 sn highlight
                            $row.addClass('wse-highlight');
                            setTimeout(function(){
                                $row.removeClass('wse-highlight');
                            }, 2000);

                            toastr.success(wse_ajax_object.messages.update_success || 'Updated successfully!');
                        } else {
                            toastr.error(wse_ajax_object.messages.update_error || 'Update failed!');
                            $input.val(originalName);
                        }
                    },
                    error: function() {
                        toastr.error(wse_ajax_object.messages.ajax_error || 'An error occurred!');
                        $input.val(originalName);
                    },
                    complete: function() {
                        $input.prop('disabled', false);
                    }
                });
            } else {
                // Değişikliği iptal edersek orijinal adı geri koyuyoruz
                $input.val(originalName);
            }
        }
    });

    var current_orderby = wse_ajax_object.initial_orderby;
    var current_order = wse_ajax_object.initial_order;

    function fetch_products(paged = 1) {
        var data = {
            action: 'wse_fetch_products',
            nonce: wse_ajax_object.wse_nonce,
            category: $('#category-filter').val(),
            type: $('#type-filter').val(),
            search: $('#product-filter').val(),
            min_price: $('#min-price-filter').val(),
            max_price: $('#max-price-filter').val(),
            paged: paged,
            orderby: current_orderby,
            order: current_order,
        };

        $.ajax({
            url: wse_ajax_object.ajax_url,
            method: 'POST',
            data: data,
            beforeSend: function() {
                $('#product-table-body').html('<tr><td colspan="10">' + wse_ajax_object.messages.loading + '</td></tr>');
            },
            success: function(response) {
                if (response.success) {
                    $('#product-table-body').html(response.data.products_html);
                    $('#pagination').html(response.data.pagination_html);
                    update_sort_icons();
                } else {
                    $('#product-table-body').html('<tr><td colspan="10">' + wse_ajax_object.messages.no_products + '</td></tr>');
                    $('#pagination').html('');
                    toastr.error(wse_ajax_object.messages.no_products);
                }
            },
            error: function() {
                $('#product-table-body').html('<tr><td colspan="10">' + wse_ajax_object.messages.error_occurred + '</td></tr>');
                $('#pagination').html('');
                toastr.error(wse_ajax_object.messages.fetch_error);
            }
        });
    }

    // Filtre değişince ürünleri yeniden çek
    $('#category-filter, #type-filter, #min-price-filter, #max-price-filter').on('change', function() {
        fetch_products();
    });

    $('#product-filter').on('keyup', function() {
        fetch_products();
    });

    $(document).on('click', '.wse-pagination a', function(e) {
        e.preventDefault();
        var paged = $(this).data('page');
        fetch_products(paged);
    });

    $(document).on('click', 'th.sortable', function() {
        var new_orderby = $(this).data('orderby');
        if (current_orderby === new_orderby) {
            current_order = (current_order === 'asc') ? 'desc' : 'asc';
        } else {
            current_orderby = new_orderby;
            current_order = 'asc';
        }
        fetch_products();
    });

    function update_sort_icons() {
        $('th.sortable').each(function() {
            var sort_field = $(this).data('orderby');
            $(this).removeClass('asc desc');
            if (sort_field === current_orderby) {
                $(this).addClass(current_order); 
            }
        });
    }

    // İlk açılışta ürünleri çek
    fetch_products();

    // Variable product modal açma
    $(document).on('click', '.product-row', function(event) {
        // eğer input, select, label'a tıklanmadıysa modal açalım
        if (
            $(event.target).is('input') ||
            $(event.target).is('select') ||
            $(event.target).is('label') ||
            $(event.target).is('.product-select-checkbox')
        ) {
            return;
        }

        var productId = $(this).data('product-id');
        var productType = $(this).data('product-type');

        if (productType === 'variable') {
            var modal = $('#variation-modal');
            var modalContent = $('#variation-modal-content');

            modalContent.html('<p>' + wse_ajax_object.messages.loading_variations + '</p>');
            modal.show();

            $.ajax({
                url: wse_ajax_object.ajax_url,
                method: 'POST',
                data: {
                    action: 'wse_get_variations',
                    product_id: productId,
                    _wpnonce: wse_ajax_object.wse_variation_nonce
                },
                success: function(response) {
                    if (response.success) {
                        modalContent.html(response.data.html);
                    } else {
                        modalContent.html('<p>' + wse_ajax_object.messages.error_loading_variations + '</p>');
                        toastr.error(wse_ajax_object.messages.error_loading_variations);
                    }
                },
                error: function() {
                    modalContent.html('<p>' + wse_ajax_object.messages.error_loading_variations + '</p>');
                    toastr.error(wse_ajax_object.messages.error_loading_variations);
                }
            });
        }
    });

    // Modal kapama
    $(document).on('click', '.close-modal', function() {
        $('#variation-modal').hide();
    });
    $(window).on('click', function(event) {
        if ($(event.target).is('#variation-modal')) {
            $('#variation-modal').hide();
        }
    });

    // Instantly update a product field (stock, fiyat, vb.)
    $(document).on('change', '.instant-update', function() {
        var $input = $(this);
        var productId = $input.closest('tr').data('product-id');
        var field = $input.data('field');
        var value = $input.val();

        if ($input.attr('type') === 'checkbox') {
            value = $input.is(':checked') ? 'yes' : 'no';
        }

        var data = {
            action: 'wse_update_product',
            nonce: wse_ajax_object.wse_nonce,
            product_id: productId,
            field: field,
            value: value
        };

        $.ajax({
            url: wse_ajax_object.ajax_url,
            method: 'POST',
            data: data,
            beforeSend: function() {
                $input.prop('disabled', true);
            },
            success: function(response) {
                if (response.success) {
                    var updated = response.data;
                    var $row = $('tr[data-product-id="' + updated.product_id + '"]');

                    // Örneğin stok
                    if (typeof updated.stock_quantity !== 'undefined') {
                        $row.find('input[data-field="stock_quantity"]').val(updated.stock_quantity);
                    }
                    // Regular price
                    if (typeof updated.regular_price !== 'undefined') {
                        $row.find('input[data-field="regular_price"]').val(updated.regular_price);
                    }
                    // Sale price
                    if (typeof updated.sale_price !== 'undefined') {
                        $row.find('input[data-field="sale_price"]').val(updated.sale_price);
                    }
                    // Ürün adı
                    if (updated.field === 'name' && updated.product_name) {
                        $row.find('.product-name-input').val(updated.product_name);
                    }
                    // Stok statüsü
                    if (typeof updated.stock_status !== 'undefined') {
                        $row.find('select[data-field="stock_status"]').val(updated.stock_status);
                    }
                    // Stok göstergesi rengi
                    if (updated.stock_indicator_color) {
                        $row.find('.stock-indicator').css('background-color', updated.stock_indicator_color);
                    }

                    // --------------------
                    // PARENT UPDATE KISMI:
                    // Eğer güncellenen bir varyasyon ise yanıt içinde "parent_id" gelir.
                    if (updated.parent_id) {
                        var $parentRow = $('tr[data-product-id="' + updated.parent_id + '"]');
                        // Renk balonunu güncelle
                        $parentRow.find('.stock-indicator').css('background-color', updated.parent_stock_indicator_color);
                        // Metin kısmını güncelle
                        $parentRow.find('.parent-stock-qty').text(updated.parent_stock_total);
                    }
                    // --------------------

                    // 2 sn highlight
                    $row.addClass('wse-highlight');
                    setTimeout(function(){
                        $row.removeClass('wse-highlight');
                    }, 2000);

                    toastr.success(wse_ajax_object.messages.update_success);
                } else {
                    toastr.error(response.data || wse_ajax_object.messages.update_error);
                }
            },
            complete: function() {
                $input.prop('disabled', false);
            },
            error: function(jqXHR, textStatus) {
                toastr.error(wse_ajax_object.messages.ajax_error + textStatus);
                $input.prop('disabled', false);
            }
        });
    });

    // Bulk Edit
    $('#select-all-products').on('change', function() {
        $('.product-select-checkbox').prop('checked', $(this).is(':checked'));
    });

    $('#bulk-update-button').on('click', function() {
        var selectedProductIds = [];
        $('.product-select-checkbox:checked').each(function() {
            selectedProductIds.push($(this).val());
        });

        if (selectedProductIds.length === 0) {
            toastr.error(wse_ajax_object.messages.no_products_selected || 'No products selected.');
            return;
        }

        var field = $('#bulk-field').val();
        var value = $('#bulk-value').val();
        var operation = $('#bulk-operation').val();

        if (!field || !value) {
            toastr.error(wse_ajax_object.messages.please_specify || 'Please specify a field and value for bulk update.');
            return;
        }

        $.ajax({
            url: wse_ajax_object.ajax_url,
            method: 'POST',
            data: {
                action: 'wse_bulk_update_products',
                nonce: wse_ajax_object.wse_nonce,
                product_ids: selectedProductIds,
                field: field,
                value: value,
                operation: operation
            },
            beforeSend: function() {
                $('#bulk-update-button').prop('disabled', true);
            },
            success: function(response) {
                if (response.success) {
                    toastr.success(response.data.message || response.data || 'Bulk update completed.');

                    // Eğer tabloyu tamamen yenilemek isterseniz:
                    // setTimeout(function() { fetch_products(); }, 2000);

                } else {
                    toastr.error(response.data || wse_ajax_object.messages.bulk_update_failed || 'Bulk update failed.');
                }
            },
            error: function() {
                toastr.error(wse_ajax_object.messages.bulk_update_error || 'An error occurred during bulk update.');
            },
            complete: function() {
                $('#bulk-update-button').prop('disabled', false);
            }
        });
    });
});
