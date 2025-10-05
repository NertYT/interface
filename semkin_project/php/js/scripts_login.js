// Инициализация темы
const savedTheme = localStorage.getItem('theme') || 'light';
document.documentElement.setAttribute('data-theme', savedTheme);
document.getElementById('themeIcon').textContent = savedTheme === 'dark' ? 'light_mode' : 'dark_mode';

function toggleTheme() {
    const currentTheme = document.documentElement.getAttribute('data-theme');
    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', newTheme);
    localStorage.setItem('theme', newTheme);
    document.getElementById('themeIcon').textContent = newTheme === 'dark' ? 'light_mode' : 'dark_mode';
    
    // Обновляем reCAPTCHA при смене темы
    if (typeof grecaptcha !== 'undefined' && grecaptcha.getResponse().length === 0) {
        grecaptcha.reset();
    }
}

// Переключение видимости пароля
function togglePassword() {
    const passwordField = document.getElementById('password');
    const toggleBtn = document.querySelector('.password-toggle');
    
    if (passwordField.type === 'password') {
        passwordField.type = 'text';
        toggleBtn.textContent = 'visibility_off';
        toggleBtn.setAttribute('aria-label', 'Скрыть пароль');
    } else {
        passwordField.type = 'password';
        toggleBtn.textContent = 'visibility';
        toggleBtn.setAttribute('aria-label', 'Показать пароль');
    }
}

// Callback для reCAPTCHA - разблокирует кнопку только при успешной проверке
function onRecaptchaSuccess(token) {
    const loginBtn = document.getElementById('loginBtn');
    loginBtn.disabled = false;
    loginBtn.style.opacity = '1';
    loginBtn.style.cursor = 'pointer';
}

// Обработка формы входа
document.getElementById('loginForm').addEventListener('submit', function(e) {
    const submitBtn = document.getElementById('loginBtn');
    const recaptchaResponse = grecaptcha.getResponse();
    
    // Проверяем reCAPTCHA только при отправке формы
    if (!recaptchaResponse.length) {
        e.preventDefault();
        showMessage('Пожалуйста, пройдите проверку reCAPTCHA', 'error');
        return false;
    }

    // Показываем индикатор загрузки
    submitBtn.disabled = true;
    submitBtn.style.opacity = '0.7';
    submitBtn.innerHTML = `
        <div class="loading"></div>
        Проверка доступа...
    `;
});

function showMessage(text, type) {
    // Удаляем существующие сообщения
    const existingMessage = document.querySelector('.message');
    if (existingMessage) {
        existingMessage.remove();
    }

    const messageDiv = document.createElement('div');
    messageDiv.className = `message message-${type}`;
    messageDiv.innerHTML = `
        <span class="material-icons">${type === 'error' ? 'error' : type === 'warning' ? 'warning' : 'check_circle'}</span>
        ${text}
    `;
    messageDiv.setAttribute('role', 'alert');
    messageDiv.setAttribute('aria-live', 'polite');
    
    const form = document.getElementById('loginForm');
    form.insertBefore(messageDiv, form.firstChild);
    
    // Автофокус на поле логина при ошибке
    if (type === 'error') {
        setTimeout(() => {
            document.getElementById('username').focus();
            document.getElementById('username').select();
        }, 100);
    }
}

// Модальное окно согласия
function acceptTerms() {
    // Сохраняем согласие в сессии через AJAX
    fetch('', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'accepted_terms=1'
    }).then(response => {
        if (response.ok) {
            const modal = document.getElementById('termsModal');
            modal.style.opacity = '0';
            modal.style.transform = 'scale(0.9)';
            modal.style.transition = 'all 0.3s ease-out';
            
            setTimeout(() => {
                modal.classList.remove('active');
                modal.style.display = 'none';
                
                // Фокус на поле логина
                setTimeout(() => {
                    document.getElementById('username').focus();
                }, 300);
                
                // Инициализируем reCAPTCHA после закрытия модалки
                if (typeof grecaptcha !== 'undefined') {
                    grecaptcha.ready(function() {
                        grecaptcha.render('recaptcha-container', {
                            'sitekey': '6LepmP0qAAAAAJe27ickgNFe7iqwIdWwR7FGjw2f',
                            'callback': onRecaptchaSuccess
                        });
                    });
                }
            }, 300);
        }
    });
}

function declineTerms() {
    // Показываем более информативное сообщение
    const modal = document.getElementById('termsModal');
    const acceptBtn = modal.querySelector('.btn-accept');
    const declineBtn = modal.querySelector('.btn-decline');
    
    declineBtn.innerHTML = '<span class="material-icons">refresh</span> Попробовать снова';
    acceptBtn.style.display = 'none';
    
    declineBtn.onclick = function() {
        acceptBtn.style.display = 'flex';
        declineBtn.innerHTML = '<span class="material-icons">close</span> Отказаться';
        declineBtn.onclick = declineTerms;
    };
}

// Закрытие модального окна по ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const modal = document.getElementById('termsModal');
        if (modal.classList.contains('active')) {
            declineTerms();
        }
    }
});

// Инициализация
document.addEventListener('DOMContentLoaded', function() {
    // Плавная анимация появления формы
    const loginCard = document.getElementById('loginCard');
    loginCard.style.opacity = '0';
    loginCard.style.transform = 'translateY(20px)';
    
    setTimeout(() => {
        loginCard.style.transition = 'all 0.4s cubic-bezier(0.4, 0, 0.2, 1)';
        loginCard.style.opacity = '1';
        loginCard.style.transform = 'translateY(0)';
    }, 100);

    // Фокус на поле логина после загрузки
    setTimeout(() => {
        document.getElementById('username').focus();
    }, 500);

    // Инициализация reCAPTCHA только после принятия условий
    const acceptedTerms = JSON.parse(document.body.getAttribute('data-accepted-terms'));
    if (acceptedTerms) {
        grecaptcha.ready(function() {
            grecaptcha.render('recaptcha-container', {
                'sitekey': '6LepmP0qAAAAAJe27ickgNFe7iqwIdWwR7FGjw2f',
                'callback': onRecaptchaSuccess
            });
        });
    }
});

// Предотвращение автозаполнения пароля в некоторых браузерах
document.getElementById('password').addEventListener('animationstart', function(e) {
    if (e.animationName === 'onAutoFillStart') {
        e.target.type = 'password';
    }


});