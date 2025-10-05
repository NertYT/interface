function toggleTheme() {
    document.body.classList.toggle('dark-theme');
    const themeIcon = document.querySelector('.theme-toggle .material-icons');
    themeIcon.textContent = document.body.classList.contains('dark-theme') ? 'light_mode' : 'dark_mode';
    localStorage.setItem('theme', document.body.classList.contains('dark-theme') ? 'dark' : 'light');
}

document.addEventListener('DOMContentLoaded', function() {
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme === 'dark') {
        document.body.classList.add('dark-theme');
        document.querySelector('.theme-toggle .material-icons').textContent = 'light_mode';
    }
});