<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Centered Themed Buttons</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root {
    --primary-color: #1d123c;
    --secondary-color: #c5e600;
    --background-color: linear-gradient(135deg, #2a1852, #1d123c);
    --footer-background: #1d123c;
    --icon-color: #ffffff;
    --hover-color: rgba(197, 230, 0, 0.2);
    --red-color: #ff3b3b;
    --popup-background: #ffffff;
    --popup-text-color: #333;
    --button-hover-color: rgba(255, 255, 255, 0.1);
    --shadow-color: rgba(0, 0, 0, 0.2);
    --focus-color: #c5e600;
    --scrollbar-track: #2a1852;
    --scrollbar-thumb: #1d123c;
    --transition-time: 0.3s;
}

/* Reset and Base Styles */
* { box-sizing: border-box; margin: 0; padding: 0; }
body, html {
    height: 100%;
    font-family: Arial, sans-serif;
    background: var(--background-color);
    display: flex;
    justify-content: center;
    align-items: center;
}

/* Main Container */
main { display: flex; justify-content: center; align-items: center; padding: 15px; width: 100%; }

/* Button Container */
.button-container {
    display: flex;
    flex-direction: column;
    gap: 20px;
    align-items: center;
    width: 100%;
    max-width: 600px;
}

/* Buttons */
.btn-mobile {
    background-color: var(--secondary-color);
    color: var(--primary-color);
    display: flex;
    align-items: center;
    justify-content: center;
    border: none;
    padding: 20px 25px;
    font-size: 1.2em;
    font-weight: 600;
    border-radius: 12px;
    cursor: pointer;
    box-shadow: 0 4px 6px var(--shadow-color);
    width: 100%;
    max-width: 500px;
    transition: background-color var(--transition-time) ease, transform 0.2s ease, color var(--transition-time) ease;
}

.btn-mobile i { margin-right: 10px; font-size: 1.4em; color: var(--icon-color); }
.btn-mobile:hover { background-color: var(--hover-color); transform: translateY(-3px); color: var(--primary-color); }
.btn-mobile:active { transform: translateY(0); background-color: var(--red-color); color: var(--icon-color); }
.btn-mobile:focus { outline: 3px solid var(--focus-color); outline-offset: 2px; }

/* Modal */
.modal {
    display: none;
    position: fixed;
    inset: 0;
    background: var(--primary-color);
    z-index: 1000;
    flex-direction: column;
    overflow: hidden;
}
.modal.active { display: flex; }
.modal-header {
    background: var(--secondary-color);
    color: var(--primary-color);
    padding: 10px 15px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-weight: bold;
    font-size: 1.1rem;
}
.close-btn { background: none; border: none; font-size: 1.5rem; color: var(--primary-color); cursor: pointer; }
#modalIframe { flex: 1; width: 100%; border: none; background: var(--primary-color); }
#loader {
    position: absolute;
    top: 50px; /* leave space for header */
    left: 0;
    right: 0;
    bottom: 0;
    display: flex;
    justify-content: center;
    align-items: center;
    background: var(--primary-color);
    z-index: 10;
}
#loader.hidden { display: none; }

/* Responsive */
@media (max-width: 768px) {
    .btn-mobile { font-size: 1em; padding: 15px 20px; }
    .btn-mobile i { font-size: 1.2em; }
}
</style>
</head>
<body>
<main>
    <div class="button-container">
        <button class="btn-mobile" data-page="etransfer/send_money.php"><i class="fa-solid fa-paper-plane"></i> Send Money</button>
        <button class="btn-mobile" data-page="etransfer/request_money.php"><i class="fa-solid fa-hand-holding-dollar"></i> Request Money</button>
        <button class="btn-mobile" data-page="etransfer/pending.php"><i class="fa-solid fa-clock"></i> Pending/Sent Transfers</button>
        <button class="btn-mobile" data-page="etransfer/settings.php"><i class="fa-solid fa-gear"></i> Interac Settings</button>
    </div>
</main>

<!-- Modal -->
<div class="modal" id="modal">
    <div class="modal-header">
        <span id="modalTitle">Loading...</span>
        <button class="close-btn" onclick="closeModal()">&times;</button>
    </div>
    <div id="loader">
        <i class="fas fa-spinner fa-spin fa-3x text-white"></i>
    </div>
    <iframe id="modalIframe" sandbox="allow-scripts allow-same-origin"></iframe>
</div>

<script>
const modal = document.getElementById('modal');
const iframe = document.getElementById('modalIframe');
const loader = document.getElementById('loader');
const modalTitle = document.getElementById('modalTitle');

document.querySelectorAll('.btn-mobile').forEach(btn => {
    btn.addEventListener('click', () => {
        const page = btn.dataset.page;
        modal.classList.add('active');
        loader.classList.remove('hidden');
        modalTitle.textContent = 'Loading...';
        iframe.src = page;

        iframe.onload = () => {
            loader.classList.add('hidden');
            modalTitle.textContent = btn.textContent.trim();
        };
    });
});

function closeModal() {
    iframe.src = '';
    modal.classList.remove('active');
}

// Keep background consistent
document.documentElement.style.background = 'var(--background-color)';
document.body.style.background = 'var(--background-color)';
window.addEventListener('beforeunload', () => {
    document.body.style.background = 'var(--background-color)';
    document.documentElement.style.background = 'var(--background-color)';
});
</script>
</body>
</html>