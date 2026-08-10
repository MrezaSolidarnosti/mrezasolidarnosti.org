import Donate from "./donate/Donate.js?v=0.0.4";

document.addEventListener('DOMContentLoaded', () => {
    const donate = new Donate({
        container: document.getElementById('profileContent'),
        initiatorElements: document.querySelectorAll('.project')
    });
    donate.init();
});