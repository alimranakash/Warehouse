jQuery(function($){

    const $result = $('#blm-result');
    const $actions = $('#blm-actions');
    const $clear = $('<button class="blm-clear">Clear</button>').appendTo($actions).hide();
    const $manual = $('#blm-manual');

    function clearActions(){
        // Remove action buttons but keep the clear button to preserve events
        $actions.children().not($clear).remove();
        $clear.hide();
    }

    function styleButtons(){
        $actions.find('button').css({marginRight:'10px', marginBottom:'10px'});
    }

    function manualInput(){
        $manual.show().off('change').on('change', function(){
            const val = $(this).val().trim();
            $(this).val('');
            if(val) process(val);
        }).focus();
    }

    manualInput();

    let currentRequest = null;
    let lookupSeq = 0;

    let html5QrCode = null;
    if (typeof Html5Qrcode !== 'undefined') {
        html5QrCode = new Html5Qrcode('blm-scanner');
        Html5Qrcode.getCameras().then(function(devices){
            const id = devices && devices.length ? devices[0].id : null;
            if(id){
                html5QrCode.start({deviceId:{exact:id}}, {fps:10, qrbox:250}, function(decoded){
                    html5QrCode.pause();
                    process(decoded.trim());
                }).then(function(){
                    $manual.hide();
                }).catch(function(){
                    manualInput();
                });
            } else {
                manualInput();
            }
        }).catch(function(){
            manualInput();
        });
    }

    function reset(){
        $result.empty();
        clearActions();
        if(html5QrCode){
            html5QrCode.resume();
            $manual.hide();
        } else {
            manualInput();
        }
    }

    $clear.on('click', reset);

    function showBin(bin, products){
        clearActions();
        $result.empty();

        const $binInfo = $('<div class="blm-scanned-bin"></div>')
            .css({margin:'10px 0', fontSize:'14px', textAlign:'center'})
            .html(`Bin Scanned: <span style="font-weight:bold;">${bin}</span>`);

        const $table = $('<table class="blm-bin-table"><thead><tr><th>Product</th><th>Qty</th></tr></thead><tbody></tbody></table>');
        const $tbody = $table.find('tbody');
        products.forEach(function(p){
            $('<tr></tr>')
                .append(`<td>${p.name || ''}</td>`, `<td>${p.quantity === null ? '' : p.quantity}</td>`)
                .appendTo($tbody);
        });

        $result.append($binInfo, $table);
        const $transfer = $('<button class="blm-transfer">Transfer Bin</button>');
        const $empty = $('<button class="blm-empty">Empty Bin</button>');
        $actions.prepend($transfer, $empty);
        $clear.show();
        styleButtons();

        $transfer.on('click', function(){
            const dest = prompt('New bin number');
            if(!dest) return;
            $.post(blmScanner.ajax, {action:'blm_transfer_bin', source:bin, destination:dest, nonce:blmScanner.nonce}, function(resp){
                alert(resp && resp.success ? 'Bin transferred' : (resp && resp.data) || 'Error');
                reset();
            });
        });

        $empty.on('click', function(){
            if(!confirm('Empty this bin?')) return;
            $.post(blmScanner.ajax, {action:'blm_empty_bin', bin:bin, nonce:blmScanner.nonce}, function(resp){
                alert(resp && resp.success ? 'Bin emptied' : (resp && resp.data) || 'Error');
                reset();
            });
        });
    }

    function showProduct(p){
        clearActions();
        $result.html(`<div class="blm-product-bin" style="background:#4caf50;color:#fff;padding:10px;font-size:1.5em;text-align:center;">${p.bin || 'No Bin Assigned'}</div>`);
        const $unassign = $('<button class="blm-unassign">Unassign Product</button>');
        const $move = $('<button class="blm-move">Move Product</button>');
        $actions.prepend($unassign, $move);
        $clear.show();
        styleButtons();

        $unassign.on('click', function(){
            $.post(blmScanner.ajax, {action:'blm_remove_product_bin', product_ids:[p.ID], nonce:blmScanner.nonce}, function(resp){
                alert(resp && resp.success ? 'Product unassigned' : (resp && resp.data) || 'Error');
                reset();
            });
        });

        $move.on('click', function(){
            const dest = prompt('New bin number');
            if(!dest) return;
            $.post(blmScanner.ajax, {action:'blm_update_product_bin', product_id:p.ID, bin:dest, nonce:blmScanner.nonce}, function(resp){
                alert(resp && resp.success ? 'Product moved' : (resp && resp.data) || 'Error');
                reset();
            });
        });
    }

    function process(code){
        // Abort any in-flight lookup to prevent stale results from stacking
        if(currentRequest){
            currentRequest.abort();
        }
        const requestId = ++lookupSeq;
        $result.empty();
        clearActions();
        currentRequest = $.post(blmScanner.ajax, {action:'blm_scan_lookup', code:code, nonce:blmScanner.nonce}, function(resp){
            // Ignore outdated responses so previous scans can't append buttons
            if(requestId !== lookupSeq){
                return;
            }
            if(!resp || !resp.success){
                alert(resp && resp.data ? resp.data : 'Not found');
                reset();
                return;
            }
            if(resp.data.type === 'bin'){
                showBin(resp.data.bin, resp.data.products);
            } else if(resp.data.type === 'product'){
                showProduct(resp.data.product);
            }
        }).always(function(){
            if(requestId === lookupSeq){
                currentRequest = null;
            }
        });
    }

});