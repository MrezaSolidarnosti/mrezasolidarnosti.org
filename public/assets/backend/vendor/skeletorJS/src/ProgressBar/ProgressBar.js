import {progressBarAssets} from "./progressBarAssets.js";

export default class ProgressBar {

    #container;
    #progressBar;
    start(container, duration = 1.5 ,prepend = false) {
        this.#container = document.createElement('div');
        this.#container .classList.add(progressBarAssets.classes.progressBar);
        this.#progressBar = document.createElement('div');
        this.#progressBar.classList.add(progressBarAssets.classes.fill);
        if(duration) {
            this.#progressBar.style.animation = `progressAnimation ${duration}s linear forwards`;
        } else {
            this.#progressBar.style.animation = 'none';
        }
        this.#container .appendChild(this.#progressBar);
        if(prepend) {
            container.prepend(this.#container);
            return;
        }
        container.appendChild(this.#container);
    }

    stop() {
        if(this.#progressBar) {
            this.setProgress(100);
        }
        if(this.#container) {
            this.#container.remove();
        }
        this.#container = null;
        this.#progressBar = null;
    }

    setProgress(percentage) {
        if(!this.#progressBar) {
            console.warn('Setting progress on a non-existent progress bar. Did you forget to call start()?');
        }
        this.#progressBar.style.animation = 'none';
        if(percentage > 100) {
            percentage = 100;
        }
        if(percentage < 0) {
            percentage = 0;
        }
        this.#progressBar.style.width = `${percentage}%`;
    }


    destroy() {
        if(this.#container) {
            this.#container.remove();
        }
        this.#container = null;
        this.#progressBar = null;
    }


}