var _rl_q = window._rl_q || [];
document.addEventListener('DOMContentLoaded', function() {
    const ajaxUrl = customAjax.ajaxUrl;
    const body = document.body;
    var applyCouponButton = document.querySelector('button[name="apply_coupon"]');

    // Function to update the cart total
    function updateCartTotal() {
        const xhr = new XMLHttpRequest();
        xhr.open('POST', ajaxUrl);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onload = function() {
            if (xhr.status === 200) {
                const response = JSON.parse(xhr.responseText);
                if (response.success) {
                    _rl_q.push(response.data._rl_basket);
                }
            } else {
                console.error('Error: ' + xhr.status);
            }
        };
        xhr.onerror = function() {
            console.error('Request failed');
        };
        xhr.send('action=basket_update_cart_total');
    }

    // Event delegation for multiple events
    body.addEventListener('updated_cart_totals', updateCartTotal);
    body.addEventListener('wc_update_cart', updateCartTotal);
    body.addEventListener('updated_wc_div', updateCartTotal);
    body.addEventListener('added_to_cart', updateCartTotal);

    // Trigger the AJAX request on quantity change and add to cart button click
    body.addEventListener('change', function(event) {
        const target = event.target;
        if (target && target.classList.contains('qty')) {
            //setTimeout(updateCartTotal, 1500);
        }
    });

    body.addEventListener('keypress', function(event) {
        const target = event.target;
        if (event.key === 'Enter' && target && target.classList.contains('qty')) {
            setTimeout(updateCartTotal, 1500);
        }
    });

    body.addEventListener('click', function(event) {
        const target = event.target;
        if (target && target.classList.contains('add_to_cart_button')) {
            setTimeout(updateCartTotal, 1500);
        }
    });

    body.addEventListener('click', function(event) {
        const target = event.target;
        if (target && target.classList.contains('remove')) {
            const parentTd = target.closest('td.product-remove');
            if (parentTd) {
                setTimeout(updateCartTotal, 1500);
            }
        }
    });

    body.addEventListener('click', function(event) {
        const target = event.target;
        if (target && target.tagName === 'BUTTON') {
            const buttonName = target.getAttribute('name');
            const buttonValue = target.getAttribute('value');
            const buttonText = target.innerText.trim();
            if (buttonName === 'update_cart' || buttonValue === 'update cart' || buttonText === 'Update Cart') {
                setTimeout(updateCartTotal, 1500);
            }
        }
    });

    body.addEventListener('click', function(event) {
        const target = event.target;
        if (target && target.tagName === 'BUTTON') {
            const buttonName = target.getAttribute('name');
            const buttonValue = target.getAttribute('value');
            const buttonText = target.innerText.trim();
            if (buttonName === 'apply_coupon' || buttonValue === 'Apply coupon' || buttonText === 'Apply coupon') {
                setTimeout(updateCartTotal, 1500);
            }
        }
    });

    if (applyCouponButton) {
        applyCouponButton.addEventListener('click', function(e) {
            setTimeout(updateCartTotal, 1500);
        });
    }

    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('woocommerce-remove-coupon')) {
            
            setTimeout(updateCartTotal, 1500);
        }
    });

    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('remove_from_cart_button')) {
            
            setTimeout(updateCartTotal, 1500);
        }
    });

    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('ext_minicart_coupon_apply')) {
            
            setTimeout(updateCartTotal, 1500);
        }
    });

    // Trigger the initial AJAX request
    updateCartTotal();
});
