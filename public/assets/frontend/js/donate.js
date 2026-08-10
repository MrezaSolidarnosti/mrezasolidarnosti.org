import Donate from "./donate/Donate.js?v=0.0.8";

document.addEventListener('DOMContentLoaded', () => {
    const donate = new Donate({
        container: document.getElementById('profileContent'),
        initiatorElements: document.querySelectorAll('.project')
    });
    donate.init();
});