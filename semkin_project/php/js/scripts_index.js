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
}

// Автоматический выход при бездействии
let inactivityTimer;
const INACTIVITY_TIMEOUT = 30 * 60 * 1000; // 30 минут

function resetInactivityTimer() {
    clearTimeout(inactivityTimer);
    inactivityTimer = setTimeout(() => {
        if (confirm('Сессия истекает из-за бездействия. Выйти из системы?')) {
            window.location.href = '?action=logout&confirm=yes';
        }
    }, INACTIVITY_TIMEOUT);
}

['load', 'mousemove', 'mousedown', 'click', 'scroll', 'keypress'].forEach(event => {
    document.addEventListener(event, resetInactivityTimer, true);
});

// SQL Консоль
const sqlForm = document.getElementById('sqlForm');
const resultContainer = document.getElementById('resultContainer');
const executeBtn = document.getElementById('executeBtn');
let pendingQuery = null;

sqlForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const sqlQuery = sqlForm.querySelector('textarea[name="sql_query"]').value.trim();
    
    if (!sqlQuery) {
        showMessage('Введите SQL запрос', 'error');
        return;
    }

    executeBtn.disabled = true;
    executeBtn.innerHTML = '<span class="material-icons">hourglass_empty</span> Выполняется...';

    try {
        const formData = new FormData();
        formData.append('sql_query', sqlQuery);

        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData
        });

        if (!response.ok) throw new Error(`HTTP ${response.status}`);

        const data = await response.json();

        if (data.status === 'warning') {
            pendingQuery = sqlQuery;
            document.getElementById('warningModal').style.display = 'flex';
        } else {
            showResult(data.message, data.status);
        }
    } catch (error) {
        showMessage(`Ошибка: ${error.message}`, 'error');
    } finally {
        executeBtn.disabled = false;
        executeBtn.innerHTML = '<span class="material-icons">play_arrow</span> Выполнить';
    }
});

function showResult(message, status) {
    resultContainer.innerHTML = message;
    resultContainer.className = `result-container ${status}`;
    
    // Автоматическая прокрутка к результату
    resultContainer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function showMessage(text, type) {
    resultContainer.innerHTML = `<div class="message message-${type}"><span class="material-icons">${type === 'success' ? 'check_circle' : 'error'}</span>${text}</div>`;
}

// Подтверждение опасного запроса
document.getElementById('confirmQuery').addEventListener('click', async () => {
    if (!pendingQuery) return;

    executeBtn.disabled = true;
    executeBtn.innerHTML = '<span class="material-icons">hourglass_empty</span> Выполняется...';

    try {
        const formData = new FormData();
        formData.append('sql_query', pendingQuery);
        formData.append('confirmed', 'true');

        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData
        });

        if (!response.ok) throw new Error(`HTTP ${response.status}`);

        const data = await response.json();
        showResult(data.message, data.status);
        document.getElementById('warningModal').style.display = 'none';
        pendingQuery = null;
    } catch (error) {
        showMessage(`Ошибка: ${error.message}`, 'error');
        document.getElementById('warningModal').style.display = 'none';
    } finally {
        executeBtn.disabled = false;
        executeBtn.innerHTML = '<span class="material-icons">play_arrow</span> Выполнить';
    }
});

document.getElementById('cancelQuery').addEventListener('click', () => {
    document.getElementById('warningModal').style.display = 'none';
    pendingQuery = null;
    showMessage('Запрос отменен', 'warning');
});

// Переключение консоли
function toggleConsole() {
    const content = document.getElementById('consoleContent');
    const icon = document.getElementById('consoleIcon');
    const text = document.getElementById('consoleText');
    
    if (content.classList.contains('active')) {
        content.classList.remove('active');
        icon.textContent = 'expand_more';
        text.textContent = 'Открыть';
    } else {
        content.classList.add('active');
        icon.textContent = 'expand_less';
        text.textContent = 'Скрыть';
        content.querySelector('textarea').focus();
    }
}

// Модальные окна
function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

function showLogoutModal() {
    document.getElementById('logoutModal').style.display = 'flex';
}

document.getElementById('confirmLogout').addEventListener('click', () => {
    window.location.href = '?action=logout&confirm=yes';
});

document.getElementById('cancelLogout').addEventListener('click', () => {
    closeModal('logoutModal');
});

// Закрытие модалок по клику вне
document.addEventListener('click', (e) => {
    if (e.target.classList.contains('modal')) {
        e.target.style.display = 'none';
    }
});

// Управление пользователями
async function fetchUserList(containerId) {
    const container = document.getElementById(containerId);
    container.innerHTML = '<div style="color: var(--text-secondary); text-align: center; padding: 1rem;"><span class="material-icons">hourglass_empty</span> Загрузка...</div>';

    try {
        const formData = new FormData();
        formData.append('action', 'get_users');

        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.error) {
            container.innerHTML = `<div class="message message-error"><span class="material-icons">error</span>${data.error}</div>`;
        } else if (data.users && data.users.length > 0) {
            container.innerHTML = data.users.map(user => 
                `<div class="user-item" data-role="${user.role}"><span class="material-icons" style="font-size: 16px; opacity: 0.5;">person</span>${user.username} (Last login: ${user.last_login || 'Never'})</div>`
            ).join('');
        } else {
            container.innerHTML = '<div style="color: var(--text-secondary); text-align: center; padding: 1rem;">Пользователи не найдены</div>';
        }
    } catch (error) {
        container.innerHTML = `<div class="message message-error"><span class="material-icons">error</span>Ошибка загрузки: ${error.message}</div>`;
    }
}

function showAddUserModal() {
    document.getElementById('addUserModal').style.display = 'flex';
    fetchUserList('userList');
}

function showDeleteUserModal() {
    document.getElementById('deleteUserModal').style.display = 'flex';
    fetchUserList('deleteUserList');
}

// Формы пользователей
document.getElementById('addUserForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);
    formData.append('action', 'add_user');
    
    const resultDiv = document.getElementById('addUserResult');
    resultDiv.innerHTML = '<div style="color: var(--text-secondary); text-align: center;"><span class="material-icons">hourglass_empty</span> Создание...</div>';

    try {
        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData
        });
        const data = await response.json();
        
        if (data.status === 'success') {
            resultDiv.innerHTML = `<div class="message message-success"><span class="material-icons">check_circle</span>${data.message}</div>`;
            setTimeout(() => {
                closeModal('addUserModal');
                e.target.reset();
                fetchUserList('userList');
            }, 2000);
        } else {
            resultDiv.innerHTML = `<div class="message message-error"><span class="material-icons">error</span>${data.message}</div>`;
        }
    } catch (error) {
        resultDiv.innerHTML = `<div class="message message-error"><span class="material-icons">error</span>Ошибка: ${error.message}</div>`;
    }
});

document.getElementById('deleteUserForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);
    formData.append('action', 'delete_user');
    
    if (!confirm('Вы уверены? Это действие необратимо!')) return;
    
    const resultDiv = document.getElementById('deleteUserResult');
    resultDiv.innerHTML = '<div style="color: var(--text-secondary); text-align: center;"><span class="material-icons">hourglass_empty</span> Удаление...</div>';

    try {
        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData
        });
        const data = await response.json();
        
        if (data.status === 'success') {
            resultDiv.innerHTML = `<div class="message message-success"><span class="material-icons">check_circle</span>${data.message}</div>`;
            setTimeout(() => {
                closeModal('deleteUserModal');
                e.target.reset();
                fetchUserList('deleteUserList');
            }, 2000);
        } else {
            resultDiv.innerHTML = `<div class="message message-error"><span class="material-icons">error</span>${data.message}</div>`;
        }
    } catch (error) {
        resultDiv.innerHTML = `<div class="message message-error"><span class="material-icons">error</span>Ошибка: ${error.message}</div>`;
    }
});

// VNC подключение
function launchRemmina() {
    window.open('vnc://172.17.0.250', '_blank');
    setTimeout(() => {
        alert('Если VNC не запустился автоматически:\n\n1. Установите Remmina: sudo apt install remmina remmina-plugin-vnc\n2. Или используйте TightVNC Viewer\n3. Адрес сервера: 172.17.0.250:5900');
    }, 500);
}

// Инициализация
document.addEventListener('DOMContentLoaded', () => {
    resetInactivityTimer();
});