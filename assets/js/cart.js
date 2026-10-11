let cart = JSON.parse(localStorage.getItem('cart')) || [];

function updateCartBadge() {
    const count = cart.reduce((sum, item) => sum + item.qty, 0);
    const badge = document.getElementById('cartBadge');
    if (badge) {
        badge.textContent = count;
        badge.style.display = count > 0 ? 'flex' : 'none';
    }
}

function addToCart(id, name, chef, price) {
    const existing = cart.find(item => item.id === id);
    if (existing) {
        existing.qty++;
    } else {
        cart.push({ id, name, chef, price, qty: 1 });
    }
    localStorage.setItem('cart', JSON.stringify(cart));
    updateCartBadge();
    if (typeof showToastMsg === 'function') {
        showToastMsg(name + ' добавлен в корзину!');
    } else if (typeof showToast === 'function') {
        showToast('✅ ' + name + ' добавлен в корзину!');
    }
}

updateCartBadge();
