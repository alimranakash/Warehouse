jQuery(function($){
    $('.report-section').hide();

    // Debug: Check if ajaxurl is defined
    if (typeof ajaxurl === 'undefined') {
        console.error('ajaxurl is not defined!');
    } else {
        console.log('ajaxurl is defined:', ajaxurl);
    }

    function setupBinEditing($container){
        $container.find('td.blm-bin-editable').off('click').on('click', function(){
            var $td = $(this);
            if ($td.find('input').length) {
                return;
            }
            var displayText = $td.text().trim();
            var current     = $td.data('current') !== undefined ? $td.data('current').toString() : displayText;
            var productId   = $td.data('product-id');
            var $input      = $('<input type="text" list="blm-bin-options" class="blm-bin-input" />').val(current);
            $td.empty().append($input);
            $input.focus().select();

            function save(){
                var newVal = $input.val().trim().toUpperCase();
                if (!newVal) {
                    $td.text(displayText).data('current', current);
                    return;
                }
                if (newVal === current) {
                    $td.text(displayText).data('current', current);
                    return;
                }
                $.post(ajaxurl, { action: 'blm_update_product_bin', product_id: productId, bin: newVal }, function(resp){
                    if (resp && resp.success) {
                        $td.text(resp.data.bin).data('current', resp.data.bin);
                    } else {
                        alert(resp.data || 'Error updating bin');
                        $td.text(displayText).data('current', current);
                    }
                });
            }

            $input.on('blur', save);
            $input.on('keydown', function(e){
                var code = e.key || e.which || e.keyCode;
                if (code === 'Enter' || code === 13) {
                    e.preventDefault();
                    save();
                } else if (code === 'Escape' || code === 27) {
                    $td.text(displayText).data('current', current);
                }
            });
        });
    }

    function setupAddProduct($container){
        var buttons = $container.find('.blm-add-product');
        console.log('setupAddProduct called, found', buttons.length, 'buttons in container:', $container);

        // Use event delegation to handle dynamically added buttons
        $container.off('click', '.blm-add-product').on('click', '.blm-add-product', function(e){
            e.preventDefault();
            e.stopPropagation();
            console.log('Add Product button clicked!');
            var $btn = $(this);
            var bin  = $btn.data('bin');
            var $row = $btn.closest('tr');
            console.log('Bin:', bin, 'Button:', $btn);

            if(bin){
                var $input = $('<input type="text" class="blm-add-input" placeholder="Enter SKU or Barcode" />');
                $btn.replaceWith($input);
                $input.focus();

                function cancel(){
                    $input.replaceWith($('<button type="button" class="button blm-add-product" data-bin="'+bin+'">Add product</button>'));
                    setupAddProduct($container);
                }

                function save(){
                    var identifier = $input.val().trim();
                    if(!identifier){
                        cancel();
                        return;
                    }
                    $.post(ajaxurl, { action: 'blm_add_product_to_bin', bin: bin, identifier: identifier }, function(resp){
                        if(resp && resp.success){
                            var p = resp.data;
                            var hasQty = $row.closest('table').find('th').filter(function(){
                                return $(this).text().trim() === 'Quantity';
                            }).length > 0;
                            var $newRow = $(`
                                <tr data-product-id="${p.ID}" class="hover:bg-gray-50 transition border-b border-gray-200">
                                    <td class="p-3 text-center w-10">
                                        <input type="checkbox" class="blm-select-row" data-product-id="${p.ID}">
                                    </td>
                                    <td class="p-3 font-medium text-gray-800 blm-bin-editable" data-product-id="${p.ID}">
                                        ${p.bin || ''}
                                    </td>
                                    <td class="p-3 text-gray-700">${p.sku || ''}</td>
                                    <td class="p-3 text-gray-700">${p.barcode || ''}</td>
                                    <td class="p-3 w-2/5 truncate text-gray-800">${p.name || ''}</td>
                                    ${hasQty ? '<td class="p-3 text-center"></td>' : ''}
                                    <td class="p-3 w-32">
                                        <button type="button"
                                            class="blm-add-product bg-blue-600 text-white px-3 py-1.5 rounded-md hover:bg-blue-700 text-xs font-medium transition"
                                            data-bin="${p.bin}">
                                            Add Product
                                        </button>
                                    </td>
                                    <td class="p-3 w-10 text-center">
                                        <button type="button"
                                            class="blm-remove-product bg-red-500 text-white w-7 h-7 rounded-md flex items-center justify-center hover:bg-red-600 transition text-sm font-semibold"
                                            data-product-id="${p.ID}">
                                            ×
                                        </button>
                                    </td>
                                </tr>
                                `);

                            if($row.hasClass('blm-add-row')){
                                $row.before($newRow);
                                cancel();
                            } else if($row.hasClass('blm-no-products')) {
                                $row.replaceWith($newRow);
                            } else {
                                $row.after($newRow);
                                $input.closest('td').empty();
                            }
                            setupBinEditing($container);
                            setupAddProduct($container);
                            setupRemoveProduct($container);
                        } else {
                            alert((resp && resp.data) || 'Error adding product');
                            cancel();
                        }
                    });
                }

                $input.on('keydown', function(e){
                    if(e.key === 'Enter'){
                        e.preventDefault();
                        save();
                    } else if(e.key === 'Escape'){
                        cancel();
                    }
                });
                $input.on('blur', save);
            } else {
                var $binInput = $('<input type="text" class="blm-add-bin" list="blm-bin-options" placeholder="Bin" />');
                var $idInput  = $('<input type="text" class="blm-add-input" placeholder="Enter SKU or Barcode" />');
                var $wrap = $('<span class="blm-add-fields"></span>').append($binInput).append($idInput);
                $btn.replaceWith($wrap);
                $binInput.focus();

                function cancel(){
                    $wrap.replaceWith($('<button type="button" class="button blm-add-product" data-bin="">Add product</button>'));
                    setupAddProduct($container);
                }

                function save(){
                    var binVal = $binInput.val().trim().toUpperCase();
                    var identifier = $idInput.val().trim();
                    if(!binVal || !identifier){
                        cancel();
                        return;
                    }
                    $.post(ajaxurl, { action: 'blm_add_product_to_bin', bin: binVal, identifier: identifier }, function(resp){
                        if(resp && resp.success){
                            var p = resp.data;
                            var hasQty = $row.closest('table').find('th').filter(function(){
                                return $(this).text().trim() === 'Quantity';
                            }).length > 0;
                            var $newRow = $(`
                                <tr data-product-id="${p.ID}" class="border-b border-gray-200 hover:bg-gray-50 transition">
                                    <td class="p-3 text-center w-10">
                                        <input type="checkbox" class="blm-select-row rounded-md border-gray-300 focus:ring-2 focus:ring-blue-500" data-product-id="${p.ID}">
                                    </td>
                                    <td class="p-3 font-medium text-gray-800 blm-bin-editable" data-product-id="${p.ID}">
                                        ${p.bin || ''}
                                    </td>
                                    <td class="p-3 text-gray-700">${p.sku || ''}</td>
                                    <td class="p-3 text-gray-700">${p.barcode || ''}</td>
                                    <td class="p-3 w-2/5 text-gray-800 truncate">${p.name || ''}</td>
                                    ${hasQty ? '<td class="p-3 text-center"></td>' : ''}
                                    <td class="p-3 w-32"></td>
                                    <td class="p-3 w-10 text-center">
                                        <button type="button"
                                            class="blm-remove-product bg-red-500 text-white w-7 h-7 rounded-md flex items-center justify-center hover:bg-red-600 transition text-sm font-semibold"
                                            data-product-id="${p.ID}">
                                            ×
                                        </button>
                                    </td>
                                </tr>
                                `);

                            $row.before($newRow);
                            setupBinEditing($container);
                            setupRemoveProduct($container);
                            cancel();
                        } else {
                            alert((resp && resp.data) || 'Error adding product');
                            cancel();
                        }
                    });
                }

                $wrap.on('keydown', 'input', function(e){
                    if(e.key === 'Enter'){
                        e.preventDefault();
                        save();
                    } else if(e.key === 'Escape'){
                        cancel();
                    }
                });
                $idInput.on('blur', save);
            }
        });
    }

    function setupRemoveProduct($container){
        $container.find('.blm-remove-product').off('click').on('click', function(){
            var $btn = $(this);
            var productId = $btn.data('product-id');
            if(!confirm('Remove bin from this product?')){
                return;
            }
            $.post(ajaxurl, { action: 'blm_remove_product_bin', product_ids: [productId] }, function(resp){
                if(resp && resp.success){
                    var $row = $btn.closest('tr');
                    $row.find('td.blm-bin-editable').text('No Bin Assigned').data('current','');
                    $row.find('input.blm-select-row').prop('checked', false);
                    $btn.remove();
                } else {
                    alert((resp && resp.data) || 'Error removing bin');
                }
            });
        });
    }

    function enhanceTable($container) {
        $container.find('.bin-report-controls').remove();
        var $table = $container.find('table.bin-report');
        if (!$table.length) {
            return;
        }

        function refreshReport(){
            var action = $container.data('action');
            if(action){
                $container.html('<p>Loading...</p>');
                $.post(ajaxurl, { action: action }, function(response){
                    $container.html(response);
                    enhanceTable($container);
                });
            }
        }

        // var $controls = $('<div class="bin-report-controls" style="margin:10px 0;"></div>');
        // var $search   = $('<input type="text" class="bin-report-search" placeholder="Search...">').appendTo($controls);
        // var $export   = $('<button type="button" class="button bin-report-export" style="margin-left:10px;">Export CSV</button>').appendTo($controls);
        // var $unassign = $('<button type="button" class="button bin-report-unassign" style="margin-left:10px;">Unassign Bin</button>').appendTo($controls);
        // var $transfer = $('<button type="button" class="button bin-report-transfer" style="margin-left:10px;">Bin Transfer</button>').appendTo($controls);
        // var $swap     = $('<button type="button" class="button bin-report-swap" style="margin-left:10px;">Bin Swap</button>').appendTo($controls);

        var $controls = $('<div class="bin-report-controls flex flex-wrap items-center gap-3 my-4"></div>');
        var $search = $('<input type="text" class="bin-report-search w-full sm:w-auto flex-1 rounded-lg border border-gray-300 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="🔍 Search...">');
            $controls.append($search);
        var $export = $('<button type="button" class="bin-report-export bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition text-sm font-medium shadow-sm">Export CSV</button>');
            $controls.append($export);
        var $unassign = $('<button type="button" class="bin-report-unassign bg-yellow-500 text-white px-4 py-2 rounded-lg hover:bg-yellow-600 transition text-sm font-medium shadow-sm">Unassign Bin</button>');
            $controls.append($unassign);
        var $transfer = $('<button type="button" class="bin-report-transfer bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition text-sm font-medium shadow-sm">Bin Transfer</button>');
            $controls.append($transfer);
        var $swap = $('<button type="button" class="bin-report-swap bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700 transition text-sm font-medium shadow-sm">Bin Swap</button>');
            $controls.append($swap);

        $table.before($controls);

        $search.on('keyup', function(){
            var term = $(this).val().toLowerCase();
            $table.find('tbody tr').each(function(){
                var text = $(this).text().toLowerCase();
                $(this).toggle(text.indexOf(term) !== -1);
            });
        });

        $export.on('click', function(){
            var csv = [];
            $table.find('tr:visible').each(function(){
                var row = [];
                $(this).find('th,td').each(function(){
                    var text = $(this).text().trim().replace(/"/g,'""');
                    row.push('"' + text + '"');
                });
                csv.push(row.join(','));
            });
            var blob = new Blob([csv.join('\n')], { type: 'text/csv;charset=utf-8;' });
            var url  = URL.createObjectURL(blob);
            var link = document.createElement('a');
            link.href = url;
            var filename = ($container.find('h3').first().text() || 'report').replace(/\s+/g, '_').toLowerCase() + '.csv';
            link.download = filename;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(url);
        });

        $unassign.on('click', function(){
            var ids = $table.find('input.blm-select-row:checked').map(function(){
                return $(this).data('product-id');
            }).get();
            if (!ids.length) {
                alert('No rows selected');
                return;
            }
            if (!confirm('Remove bin from selected products?')) {
                return;
            }
            $.post(ajaxurl, { action: 'blm_remove_product_bin', product_ids: ids }, function(resp){
                if (resp && resp.success) {
                    ids.forEach(function(id){
                        var $row = $table.find('tr[data-product-id="' + id + '"]');
                        $row.find('td.blm-bin-editable').text('No Bin Assigned').data('current', '');
                        $row.find('.blm-remove-product').remove();
                        $row.find('input.blm-select-row').prop('checked', false);
                    });
                } else {
                    alert(resp.data || 'Error removing bin');
                }
            });
        });

        $transfer.on('click', function(){
            var source = prompt('Source bin:');
            if(!source){ return; }
            var dest = prompt('Destination bin:');
            if(!dest){ return; }
            source = source.trim().toUpperCase();
            dest   = dest.trim().toUpperCase();
            if(!source || !dest){ return; }
            $.post(ajaxurl, { action: 'blm_transfer_bin', source: source, destination: dest }, function(resp){
                if(resp && resp.success){
                    refreshReport();
                } else {
                    alert((resp && resp.data) || 'Error transferring bin');
                }
            });
        });

        $swap.on('click', function(){
            var source = prompt('Source bin:');
            if(!source){ return; }
            var dest = prompt('Target bin:');
            if(!dest){ return; }
            source = source.trim().toUpperCase();
            dest   = dest.trim().toUpperCase();
            if(!source || !dest){ return; }
            $.post(ajaxurl, { action: 'blm_swap_bins', source: source, destination: dest }, function(resp){
                if(resp && resp.success){
                    refreshReport();
                } else {
                    alert((resp && resp.data) || 'Error swapping bins');
                }
            });
        });

        $table.find('.blm-select-all').on('change', function(){
            $table.find('input.blm-select-row').prop('checked', $(this).is(':checked'));
        });

        setupBinEditing($container);
        setupAddProduct($container);
        setupRemoveProduct($container);
    }

    jQuery(document).ready(function($) {
        console.log('Document ready, initializing...');

        // Global event delegation for Add Product buttons
        $(document).on('click', '.blm-add-product', function(e) {
            e.preventDefault();
            e.stopPropagation();
            console.log('Global click handler triggered for .blm-add-product');

            var $btn = $(this);
            var bin  = $btn.data('bin');
            var $row = $btn.closest('tr');
            console.log('Bin:', bin, 'Button:', $btn);

            if(bin){
                console.log('Bin exists, creating input field...');
                var $input = $('<input type="text" class="blm-add-input" placeholder="Enter SKU or Barcode" />');
                $btn.replaceWith($input);
                $input.focus();

                function cancel(){
                    $input.replaceWith($('<button type="button" class="blm-add-product bg-blue-600 text-white px-3 py-1.5 rounded-md hover:bg-blue-700 text-xs font-medium transition" data-bin="'+bin+'">Add Product</button>'));
                }

                function save(){
                    var identifier = $input.val().trim();
                    console.log('Saving with identifier:', identifier);
                    if(!identifier){
                        cancel();
                        return;
                    }
                    $.post(ajaxurl, { action: 'blm_add_product_to_bin', bin: bin, identifier: identifier }, function(resp){
                        console.log('AJAX response:', resp);
                        if(resp && resp.success){
                            var p = resp.data;
                            var hasQty = $row.closest('table').find('th').filter(function(){
                                return $(this).text().trim() === 'Quantity';
                            }).length > 0;
                            var $newRow = $(`
                                <tr data-product-id="${p.ID}" class="hover:bg-gray-50 transition border-b border-gray-200">
                                    <td class="p-3 text-center w-10">
                                        <input type="checkbox" class="blm-select-row" data-product-id="${p.ID}">
                                    </td>
                                    <td class="p-3 font-medium text-gray-800 blm-bin-editable" data-product-id="${p.ID}">
                                        ${p.bin || ''}
                                    </td>
                                    <td class="p-3 text-gray-700">${p.sku || ''}</td>
                                    <td class="p-3 text-gray-700">${p.barcode || ''}</td>
                                    <td class="p-3 w-2/5 truncate text-gray-800">${p.name || ''}</td>
                                    ${hasQty ? '<td class="p-3 text-center"></td>' : ''}
                                    <td class="p-3 w-32">
                                        <button type="button"
                                            class="blm-add-product bg-blue-600 text-white px-3 py-1.5 rounded-md hover:bg-blue-700 text-xs font-medium transition"
                                            data-bin="${p.bin}">
                                            Add Product
                                        </button>
                                    </td>
                                    <td class="p-3 w-10 text-center">
                                        <button type="button"
                                            class="blm-remove-product bg-red-500 text-white w-7 h-7 rounded-md flex items-center justify-center hover:bg-red-600 transition text-sm font-semibold"
                                            data-product-id="${p.ID}">
                                            ×
                                        </button>
                                    </td>
                                </tr>
                                `);

                            if($row.hasClass('blm-add-row')){
                                $row.before($newRow);
                                cancel();
                            } else if($row.hasClass('blm-no-products')) {
                                $row.replaceWith($newRow);
                            } else {
                                $row.after($newRow);
                                $input.closest('td').empty();
                            }
                        } else {
                            alert((resp && resp.data) || 'Error adding product');
                            cancel();
                        }
                    });
                }

                $input.on('keydown', function(e){
                    if(e.key === 'Enter'){
                        e.preventDefault();
                        save();
                    } else if(e.key === 'Escape'){
                        cancel();
                    }
                });
                $input.on('blur', save);
            } else {
                console.log('No bin, creating bin + product input fields...');
                var $binInput = $('<input type="text" class="blm-add-bin" list="blm-bin-options" placeholder="Bin" />');
                var $idInput  = $('<input type="text" class="blm-add-input" placeholder="Enter SKU or Barcode" />');
                var $wrap = $('<span class="blm-add-fields"></span>').append($binInput).append($idInput);
                $btn.replaceWith($wrap);
                $binInput.focus();

                function cancel(){
                    $wrap.replaceWith($('<button type="button" class="blm-add-product bg-blue-600 text-white px-3 py-1.5 rounded-md hover:bg-blue-700 text-xs font-medium transition" data-bin="">Add Product</button>'));
                }

                function save(){
                    var binVal = $binInput.val().trim().toUpperCase();
                    var identifier = $idInput.val().trim();
                    console.log('Saving with bin:', binVal, 'identifier:', identifier);
                    if(!binVal || !identifier){
                        cancel();
                        return;
                    }
                    $.post(ajaxurl, { action: 'blm_add_product_to_bin', bin: binVal, identifier: identifier }, function(resp){
                        console.log('AJAX response:', resp);
                        if(resp && resp.success){
                            var p = resp.data;
                            var hasQty = $row.closest('table').find('th').filter(function(){
                                return $(this).text().trim() === 'Quantity';
                            }).length > 0;
                            var $newRow = $(`
                                <tr data-product-id="${p.ID}" class="border-b border-gray-200 hover:bg-gray-50 transition">
                                    <td class="p-3 text-center w-10">
                                        <input type="checkbox" class="blm-select-row rounded-md border-gray-300 focus:ring-2 focus:ring-blue-500" data-product-id="${p.ID}">
                                    </td>
                                    <td class="p-3 font-medium text-gray-800 blm-bin-editable" data-product-id="${p.ID}">
                                        ${p.bin || ''}
                                    </td>
                                    <td class="p-3 text-gray-700">${p.sku || ''}</td>
                                    <td class="p-3 text-gray-700">${p.barcode || ''}</td>
                                    <td class="p-3 w-2/5 text-gray-800 truncate">${p.name || ''}</td>
                                    ${hasQty ? '<td class="p-3 text-center"></td>' : ''}
                                    <td class="p-3 w-32"></td>
                                    <td class="p-3 w-10 text-center">
                                        <button type="button"
                                            class="blm-remove-product bg-red-500 text-white w-7 h-7 rounded-md flex items-center justify-center hover:bg-red-600 transition text-sm font-semibold"
                                            data-product-id="${p.ID}">
                                            ×
                                        </button>
                                    </td>
                                </tr>
                                `);

                            $row.before($newRow);
                            cancel();
                        } else {
                            alert((resp && resp.data) || 'Error adding product');
                            cancel();
                        }
                    });
                }

                $binInput.on('keydown', function(e){
                    if(e.key === 'Enter'){
                        e.preventDefault();
                        $idInput.focus();
                    } else if(e.key === 'Escape'){
                        cancel();
                    }
                });
                $idInput.on('keydown', function(e){
                    if(e.key === 'Enter'){
                        e.preventDefault();
                        save();
                    } else if(e.key === 'Escape'){
                        cancel();
                    }
                });
                $idInput.on('blur', save);
            }
        });

        // Initialize on page load for any existing tables
        $('.report-section').each(function() {
            var $container = $(this);
            console.log('Checking report section:', $container.attr('id'));
            if ($container.find('table').length) {
                console.log('Found table in section, calling enhanceTable');
                enhanceTable($container);
            }
        });

        // Also initialize for any tables already on the page (not in report sections)
        $('table.bin-report').each(function() {
            var $table = $(this);
            var $container = $table.closest('.report-section');
            if (!$container.length) {
                $container = $table.parent();
            }
            console.log('Found bin-report table, calling enhanceTable');
            enhanceTable($container);
        });

        $(document).on('click', '.load-report', function(e) {
            e.preventDefault();

            var action = $(this).data('action');
            var target = $(this).data('target');
            $('.report-section').hide();
            var $container = $('#' + target)
                .show()
                .html('<p class="text-gray-500">Loading...</p>')
                .data('action', action);

            $.post(ajaxurl, { action: action }, function(response) {
                $container.html(response);
                if (typeof enhanceTable === 'function') {
                    enhanceTable($container);
                }
            });
        });
    });


});