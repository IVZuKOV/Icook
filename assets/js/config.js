// assets/js/config.js
/**
 * ============================================
 *  КОНФИГУРАЦИЯ ПРОЕКТА iCook
 * ============================================
 * При переносе на сервер меняй ТОЛЬКО этот файл!
 */

const CONFIG = {
    // 🌐 Базовый URL API (n8n или твой бэкенд)
    // Локально: оставь пустым '' или 'http://localhost:5678', если n8n запущен на ПК
    // Сервер:   'https://api.icook.ru' (или твой домен)
    API_BASE_URL: '', 

    // 🔗 Webhook пути (как в твоем n8n проекте)
    ENDPOINTS: {
        LOGIN: '/webhook/92b13143-c5eb-4e1c-8c28-8ac1542f1d44',
        REGISTER: '/webhook/api/registr_user',
        PROFILE: '/webhook/profile',
        QR_GEN: '/webhook/qr-gen',
        LOGOUT: '/webhook/3d9f6cae-8bed-4a52-9dd8-e38121aafede',
        // Пути к страницам (для локальной работы меняем на .html файлы)
        PAGES: {
            HOME: 'index.html',
            MENU: 'menu.html',
            CART: 'cart.html',
            PROFILE: 'profile.html',
            CHEAP: 'cheap-honest.html',
            RESTAURANTS: 'restaurants.html'
        }
    },

    // 🛒 Настройки корзины
    CART: {
        DELIVERY_FEE: 250,
        STORAGE_KEY: 'cart',
        PROMO_CODES: {
            'FOOD2026': 10,
            'WELCOME': 15,
            'NEON2026': 20
        }
    },

    // 🎨 Настройки темы
    THEME: {
        DEFAULT: 'light', // 'light' или 'dark'
        STORAGE_KEY: 'site-theme'
    },

    // 🔐 Настройки сессии
    SESSION: {
        USER_KEY: 'user',
        COOKIE_NAME: 'session',
        DURATION_DAYS: 7
    },

    // 🖼️ Картинки (Unsplash или локальные)
    IMAGES: {
        HERO_BG: 'https://images.unsplash.com/photo-1556910103-1c02745aae4d?q=80&w=2000&auto=format&fit=crop',
        LOGO_SVG: 'assets/images/logo.svg' // Если будешь использовать локальный логотип
    }
};

// 🛠️ Вспомогательная функция для построения полного URL API
function apiUrl(endpoint) {
    return CONFIG.API_BASE_URL + endpoint;
}

// Экспорт в глобальную область (чтобы видели все скрипты)
window.CONFIG = CONFIG;
window.apiUrl = apiUrl;