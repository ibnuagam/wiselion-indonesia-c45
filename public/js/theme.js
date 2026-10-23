(() => {
    try { document.documentElement.dataset.theme = localStorage.getItem('wiselion-theme') === 'dark' ? 'dark' : 'light'; }
    catch { document.documentElement.dataset.theme = 'light'; }
})();
