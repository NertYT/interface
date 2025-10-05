// @ts-nocheck
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

// Переменные для модального окна удаления
let currentDeleteId = null;
let currentDeleteChefName = '';

// Загрузка списка шеф-поваров
async function loadChefs(page = 1) {
    const tableBody = document.getElementById('chefsTableBody');
    const pagination = document.getElementById('pagination');
    
    tableBody.innerHTML = '<tr><td colspan="4" style="text-align: center; color: var(--text-secondary);"><span class="material-icons">hourglass_empty</span> Загрузка...</td></tr>';

    try {
        const formData = new FormData();
        formData.append('action', 'get_chefs');
        formData.append('page', page);

        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.chefs && data.chefs.length > 0) {
            tableBody.innerHTML = data.chefs.map(chef => `
                <tr>
                    <td>${chef.ID}</td>
                    <td>${chef.Name}</td>
                    <td>${chef.Surname}</td>
                    <td>
                        <div class="action-buttons">
                            <a href="edit_chef.php?id=${chef.ID}" class="btn-action btn-edit" title="Редактировать">
                                <span class="material-icons">edit</span>
                            </a>
                            <button class="btn-action btn-delete" onclick="showDeleteModal(${chef.ID}, '${chef.Name} ${chef.Surname}')" title="Удалить">
                                <span class="material-icons">delete</span>
                            </button>
                        </div>
                    </td>
                </tr>
            `).join('');
        } else {
            tableBody.innerHTML = '<tr><td colspan="4" style="text-align: center; color: var(--text-secondary);">Шеф-повара не найдены</td></tr>';
        }

        // Генерация пагинации
        let paginationHtml = '';
        for (let i = 1; i <= data.pages; i++) {
            paginationHtml += `<a href="#" class="pagination-btn ${i == data.current_page ? 'active' : ''}" onclick="loadChefs(${i}); return false;">${i}</a>`;
        }
        pagination.innerHTML = paginationHtml;

    } catch (error) {
        tableBody.innerHTML = `<tr><td colspan="4" style="text-align: center;"><div class="message message-error"><span class="material-icons">error</span>Ошибка загрузки: ${error.message}</div></td></tr>`;
    }
}

// Форма добавления шеф-повара
document.getElementById('addChefForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    formData.append('action', 'add_chef');
    
    const addBtn = document.getElementById('addChefBtn');
    const resultDiv = document.getElementById('addChefResult');
    
    addBtn.disabled = true;
    addBtn.innerHTML = '<span class="material-icons">hourglass_empty</span> Добавление...';

    try {
        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.status === 'success') {
            resultDiv.innerHTML = `<div class="message message-success"><span class="material-icons">check_circle</span>${data.message}</div>`;
            e.target.reset();
            loadChefs(); // Перезагрузка списка
            setTimeout(() => {
                resultDiv.innerHTML = '';
            }, 3000);
        } else {
            resultDiv.innerHTML = `<div class="message message-error"><span class="material-icons">error</span>${data.message}</div>`;
        }
    } catch (error) {
        resultDiv.innerHTML = `<div class="message message-error"><span class="material-icons">error</span>Ошибка: ${error.message}</div>`;
    } finally {
        addBtn.disabled = false;
        addBtn.innerHTML = '<span class="material-icons">person_add</span> Добавить';
    }
});

// Модальное окно удаления
function showDeleteModal(id, chefName) {
    currentDeleteId = id;
    currentDeleteChefName = chefName;
    document.getElementById('deleteModalText').textContent = `Вы действительно хотите удалить шеф-повара "${chefName}"? Это действие необратимо.`;
    document.getElementById('deleteModal').style.display = 'flex';
}

document.getElementById('confirmDeleteBtn').addEventListener('click', async () => {
    if (!currentDeleteId) return;

    const formData = new FormData();
    formData.append('action', 'delete_chef');
    formData.append('id', currentDeleteId);

    const resultDiv = document.getElementById('deleteChefResult');
    resultDiv.innerHTML = '<div style="color: var(--text-secondary); text-align: center;"><span class="material-icons">hourglass_empty</span> Удаление...</div>';

    try {
        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.status === 'success') {
            resultDiv.innerHTML = `<div class="message message-success"><span class="material-icons">check_circle</span>${data.message}</div>`;
            loadChefs(); // Перезагрузка списка
            closeModal('deleteModal');
            setTimeout(() => {
                resultDiv.innerHTML = '';
            }, 3000);
        } else {
            resultDiv.innerHTML = `<div class="message message-error"><span class="material-icons">error</span>${data.message}</div>`;
            closeModal('deleteModal');
        }
    } catch (error) {
        resultDiv.innerHTML = `<div class="message message-error"><span class="material-icons">error</span>Ошибка: ${error.message}</div>`;
        closeModal('deleteModal');
    }
    
    currentDeleteId = null;
    currentDeleteChefName = '';
});

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

// VNC подключение
function launchRemmina() {
    window.open('vnc://172.17.0.250', '_blank');
    setTimeout(() => {
        alert('Если VNC не запустился автоматически:\n\n1. Установите Remmina: sudo apt install remmina remmina-plugin-vnc\n2. Или используйте TightVNC Viewer\n3. Адрес сервера: 172.17.0.250:5900');
    }, 500);
}

// Управление пользователями (из index.php)
async function fetchUserList(containerId) {
    const container = document.getElementById(containerId);
    container.innerHTML = '<div style="color: var(--text-secondary); text-align: center; padding: 1rem;"><span class="material-icons">hourglass_empty</span> Загрузка...</div>';

    try {
        const formData = new FormData();
        formData.append('action', 'get_users');

        const response = await fetch('index.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.error) {
            container.innerHTML = `<div class="message message-error"><span class="material-icons">error</span>${data.error}</div>`;
        } else if (data.users && data.users.length > 0) {
            container.innerHTML = data.users.map(user => 
                `<div class="user-item"><span class="material-icons" style="font-size: 16px; opacity: 0.5;">person</span>${user}</div>`
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
        const response = await fetch('index.php', {
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
        const response = await fetch('index.php', {
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

// Инициализация
document.addEventListener('DOMContentLoaded', () => {
    loadChefs();
    resetInactivityTimer();
});