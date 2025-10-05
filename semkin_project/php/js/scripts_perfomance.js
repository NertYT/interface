// @ts-nocheck
// Инициализация темы
const savedTheme = localStorage.getItem('theme') || 'light';
document.documentElement.setAttribute('data-theme', savedTheme);

let cpuChart, memoryChart, logStatsChart;
let metricsData = {
    cpu: [],
    memory: [],
    timestamps: []
};

function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.innerHTML = `
        <span class="material-icons">${type === 'success' ? 'check_circle' : type === 'error' ? 'error' : 'info'}</span>
        ${message}
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.opacity = '0';
        notification.style.transform = 'translateX(100%)';
        setTimeout(() => notification.remove(), 300);
    }, 4000);
}

function updateMetrics() {
    fetch('?get_metrics=1')
        .then(response => response.json())
        .then(data => {
            // Обновляем метрики
            document.getElementById('responseTime').textContent = data.response_time || 0;
            document.getElementById('cpuLoad').textContent = data.cpu_load || 'N/A';
            document.getElementById('memoryUsage').textContent = data.memory_usage || 0;
            document.getElementById('dbConnections').textContent = data.db_connections || 0;
            document.getElementById('dbSize').textContent = data.database_size || 0;
            document.getElementById('logCount').textContent = data.log_count || 0;
            document.getElementById('queriesToday').textContent = data.queries_today || 0;

            // Проверяем статус системы
            const cpuValue = parseFloat(data.cpu_load) || 0;
            const memoryValue = parseFloat(data.memory_usage) || 0;
            const responseValue = parseFloat(data.response_time) || 0;
            
            const statusIcon = document.querySelector('#overallStatus .material-icons');
            const statusText = document.querySelector('#overallStatus span:last-child');
            
            let status = 'success';
            let statusMessage = 'Система стабильна';
            
            if (cpuValue > 80 || memoryValue > 100 || responseValue > 500) {
                status = 'warning';
                statusMessage = 'Обнаружены предупреждения';
                statusIcon.textContent = 'warning';
                statusIcon.style.color = 'var(--warning-color)';
            } else if (cpuValue > 90 || memoryValue > 150 || responseValue > 1000) {
                status = 'error';
                statusMessage = 'Критические показатели';
                statusIcon.textContent = 'error';
                statusIcon.style.color = 'var(--danger-color)';
            } else {
                statusIcon.textContent = 'check_circle';
                statusIcon.style.color = 'var(--success-color)';
            }
            
            statusText.textContent = statusMessage;

            // Обновляем графики
            const now = new Date();
            metricsData.timestamps.push(now);
            metricsData.cpu.push(cpuValue);
            metricsData.memory.push(memoryValue);

            // Ограничиваем историю 60 точками (1 минута при обновлении каждую секунду)
            if (metricsData.timestamps.length > 60) {
                metricsData.timestamps.shift();
                metricsData.cpu.shift();
                metricsData.memory.shift();
            }

            if (cpuChart) {
                cpuChart.data.labels = metricsData.timestamps.map(t => t.toLocaleTimeString('ru-RU', {hour: '2-digit', minute:'2-digit', second:'2-digit'}));
                cpuChart.data.datasets[0].data = metricsData.cpu;
                cpuChart.update('none');
            }

            if (memoryChart) {
                memoryChart.data.labels = metricsData.timestamps.map(t => t.toLocaleTimeString('ru-RU', {hour: '2-digit', minute:'2-digit', second:'2-digit'}));
                memoryChart.data.datasets[0].data = metricsData.memory;
                memoryChart.update('none');
            }
        })
        .catch(error => {
            console.error('Ошибка получения метрик:', error);
            showNotification('Ошибка обновления метрик', 'error');
        });
}

function fetchLogs() {
    // В реальной реализации здесь был бы AJAX-запрос
    // Для простоты используем статические данные
}

function confirmClearLogs() {
    if (confirm('Вы уверены, что хотите очистить все логи?\n\nЭто действие необратимо и удалит всю историю активности системы.')) {
        window.location.href = '?clear_logs=1&confirm=yes';
    }
}

function showLogStats() {
    showNotification('Функция статистики в разработке', 'info');
}

document.addEventListener('DOMContentLoaded', function() {
    // Инициализация графиков
    const cpuCtx = document.getElementById('cpuChart').getContext('2d');
    const memoryCtx = document.getElementById('memoryChart').getContext('2d');

    cpuChart = new Chart(cpuCtx, {
        type: 'line',
        data: {
            labels: [],
            datasets: [{
                label: 'CPU %',
                data: [],
                borderColor: 'rgba(59, 130, 246, 1)',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.4,
                pointRadius: 3,
                pointHoverRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                x: { 
                    display: false,
                    grid: { display: false }
                },
                y: { 
                    beginAtZero: true, 
                    max: 100,
                    grid: { color: 'rgba(0,0,0,0.05)' },
                    ticks: { 
                        stepSize: 20,
                        callback: function(value) { return value + '%'; }
                    }
                }
            },
            elements: {
                point: { hoverBackgroundColor: 'rgba(59, 130, 246, 1)' }
            },
            interaction: {
                intersect: false,
                mode: 'index'
            }
        }
    });

    memoryChart = new Chart(memoryCtx, {
        type: 'line',
        data: {
            labels: [],
            datasets: [{
                label: 'Memory MB',
                data: [],
                borderColor: 'rgba(16, 185, 129, 1)',
                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.4,
                pointRadius: 3,
                pointHoverRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                x: { 
                    display: false,
                    grid: { display: false }
                },
                y: { 
                    beginAtZero: true,
                    grid: { color: 'rgba(0,0,0,0.05)' },
                    ticks: { callback: function(value) { return value + 'MB'; } }
                }
            },
            elements: {
                point: { hoverBackgroundColor: 'rgba(16, 185, 129, 1)' }
            },
            interaction: {
                intersect: false,
                mode: 'index'
            }
        }
    });

    // Инициализация счетчиков
    document.getElementById('logCount').textContent = '<?= count($logs) ?>';
    
    // Запуск обновлений
    updateMetrics();
    setInterval(updateMetrics, 2000); // Обновление каждые 2 секунды

    // Обработка клика вне модального окна
    document.addEventListener('click', (e) => {
        if (e.target.classList.contains('modal')) {
            e.target.remove();
        }
    });
});