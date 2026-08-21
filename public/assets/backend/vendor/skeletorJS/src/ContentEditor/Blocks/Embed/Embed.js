import Block from "../Block.js";
import Tooltip from "../../../Tooltip/Tooltip.js";
import Translator from "../../../Translator/Translator.js";

export default class Embed extends Block {
    static label = 'Embed';
    static keywords = ['embed', 'youtube', 'video', 'vimeo', 'spotify', 'map', 'iframe'];
    static icon = `<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="m380-300 280-180-280-180v360ZM160-160q-33 0-56.5-23.5T80-240v-480q0-33 23.5-56.5T160-800h640q33 0 56.5 23.5T880-720v480q0 33-23.5 56.5T800-160H160Zm0-80h640v-480H160v480Zm0 0v-480 480Z"/></svg>`;
    static isText = false;
    static name = 'core/embed';
    static category = 'media';
    static description = 'Embed video, audio and other content from external sites.';
    static PROVIDERS = [
        {
            name: 'youtube',
            label: 'YouTube',
            resolve: (url) => {
                const match = url.match(/(?:youtube\.com\/(?:watch\?v=|embed\/|shorts\/)|youtu\.be\/)([\w-]{11})/);
                return match ? `https://www.youtube.com/embed/${match[1]}` : null;
            }
        },
        {
            name: 'vimeo',
            label: 'Vimeo',
            resolve: (url) => {
                const match = url.match(/vimeo\.com\/(?:video\/)?(\d+)/);
                return match ? `https://player.vimeo.com/video/${match[1]}` : null;
            }
        },
        {
            name: 'spotify',
            label: 'Spotify',
            resolve: (url) => {
                const match = url.match(/open\.spotify\.com\/(track|album|playlist|episode|show)\/(\w+)/);
                return match ? `https://open.spotify.com/embed/${match[1]}/${match[2]}` : null;
            }
        },
        {
            name: 'googlemaps',
            label: 'Google Maps',
            resolve: (url) => {
                if (!/google\.[a-z.]+\/maps/.test(url)) {
                    return null;
                }
                const coords = url.match(/@(-?\d+\.\d+),(-?\d+\.\d+)/);
                if (coords) {
                    return `https://maps.google.com/maps?q=${coords[1]},${coords[2]}&z=15&output=embed`;
                }
                const place = url.match(/\/place\/([^/@]+)/);
                if (place) {
                    return `https://maps.google.com/maps?q=${encodeURIComponent(decodeURIComponent(place[1]))}&output=embed`;
                }
                return `https://maps.google.com/maps?q=${encodeURIComponent(url)}&output=embed`;
            }
        }
    ];
    container;
    iframeInput;
    tooltip;
    embedButton;
    iframe;
    provider;
    src;

    render() {
       this.container = document.createElement('div');
       this.container.tabIndex = -1;
       const tooltipElement = this.#getTooltip();
       this.tooltip = new Tooltip(tooltipElement);
       this.tooltip.init();
       this.container.appendChild(tooltipElement);
       const title = document.createElement('h2');
       title.textContent = Translator.translate('Paste a link to the content you want to display.');
       this.container.appendChild(title);
       const inputContainer = document.createElement('div');
       this.iframeInput = document.createElement('input');
       this.iframeInput.placeholder = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';
       inputContainer.appendChild(this.iframeInput);
       this.embedButton = document.createElement('button');
       this.embedButton.textContent = Translator.translate('Embed');
       inputContainer.appendChild(this.embedButton);
       this.container.appendChild(inputContainer);
       this.iframe = this.#buildIframe();
       this.container.appendChild(this.iframe);
        if (this.data && this.data.src) {
            this.iframeInput.value = this.data.src;
            this.resolveEmbed(this.data.src);
        }
       this.#addListeners();
       return this.container;
    }

    #addListeners() {
        this.embedButton.addEventListener('click', this.resolveEmbed);
        this.iframeInput.addEventListener('keydown', this.#handleInputKeydown);
    }


    resolveEmbed = (src = null) => {
        const raw = (typeof src === 'string') ? src : this.iframeInput.value;
        const trimmed = raw.trim();
        if(trimmed === this.src) {
            return;
        }

        if (!trimmed) {
            return null;
        }

        let provider = null;
        let embedSrc = null;

        for (const candidate of Embed.PROVIDERS) {
            const resolved = candidate.resolve(trimmed);
            if (resolved) {
                provider = candidate.name;
                embedSrc = resolved;
                break;
            }
        }
        if (!embedSrc) {
            return null;
        }

        this.provider = provider;
        this.src = trimmed;
        this.iframe.src = embedSrc;
    };

    #handleInputKeydown = (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            e.stopPropagation();
            this.resolveEmbed();
        }
    }

    #buildIframe() {
        const iframe = document.createElement('iframe');
        iframe.loading = 'lazy';
        iframe.setAttribute('allowfullscreen', 'true');
        iframe.setAttribute('allow', 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share');
        return iframe;
    }


    #getTooltip() {
        const providerLabels = Embed.getSupportedProviderLabels();
        let listItems = '';
        providerLabels.forEach((label) => {
            listItems +=  `<li>${label}</li>`;
        });
        const container = document.createElement('div');
        container.innerHTML = `<div class="tooltipTrigger">
                                     
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path d="M464 256A208 208 0 1 0 48 256a208 208 0 1 0 416 0zM0 256a256 256 0 1 1 512 0A256 256 0 1 1 0 256zm169.8-90.7c7.9-22.3 29.1-37.3 52.8-37.3h58.3c34.9 0 63.1 28.3 63.1 63.1c0 22.6-12.1 43.5-31.7 54.8L280 264.4c-.2 13-10.9 23.6-24 23.6c-13.3 0-24-10.7-24-24V250.5c0-8.6 4.6-16.5 12.1-20.8l44.3-25.4c4.7-2.7 7.6-7.7 7.6-13.1c0-8.4-6.8-15.1-15.1-15.1H222.6c-3.4 0-6.4 2.1-7.5 5.3l-.4 1.2c-4.4 12.5-18.2 19-30.6 14.6s-19-18.2-14.6-30.6l.4-1.2zM224 352a32 32 0 1 1 64 0 32 32 0 1 1 -64 0z"/></svg>
                               </div>
                               <span class="tooltipContent">
                                    ${Translator.translate('Supported Providers:')}
                                    <ul>
                                        ${listItems}
                                    </ul>
                                </span>`
        return container;
    }

    static getSupportedProviderLabels()
    {
        return Embed.PROVIDERS.map(provider => provider.label);
    }

    getContainer() {
        return this.container;
    }

    focus() {
        this.iframeInput.focus();
    }

    getData() {
        // Keys must match what render() reads back: it restores from data.src.
        return {src: this.src ?? null, provider: this.provider ?? null};
    }

    static registerProvider(name, label, callback) {
        Embed.PROVIDERS.push({
            name,
            label,
            resolve: callback
        })
    }

    static unRegisterProvider(name) {
        const index = Embed.PROVIDERS.findIndex(provider => provider.name === name);

        if (index !== -1) {
            Embed.PROVIDERS.splice(index, 1);
            return true;
        }

        return false;
    }

    destroy() {
        this.tooltip.destroy();
        this.embedButton.removeEventListener('click', this.resolveEmbed);
        this.iframeInput.removeEventListener('keydown', this.#handleInputKeydown);
        this.container.remove();
        super.destroy();
    }
}
