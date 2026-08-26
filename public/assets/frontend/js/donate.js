import Donate from "./donate/Donate.js?v=1.0.0";

document.addEventListener('DOMContentLoaded', () => {
    const donate = new Donate({
        container: document.getElementById('profileContent'),
        initiatorElements: document.querySelectorAll('.project')
    });
    donate.init();
});