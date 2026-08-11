import {events as mediaLibraryEvents} from "../../MediaLibrary/events.js";
import {contentEditorSelectors} from "../contentEditorSelectors.js";
import {events} from "./events.js";
import BaseModule from "../BaseModule.js";

export default class FeaturedImage extends BaseModule {

    #setupComplete = false;
    featuredImageButton;
    input;
    id;
    src = null;

    init() {
        if(this.#setupComplete) {
            return;
        }
        this.#setElements();
        if(!this.featuredImageButton || !this.input) {
            return;
        }
        if(this.isReadOnly()) {
            this.featuredImageButton.style.pointerEvents = 'none';
        }
        this.#listenToEvents();

        this.#setupComplete = true;
    }

    #setElements() {
        this.featuredImageButton = document.getElementById(contentEditorSelectors.ids.featuredImage);
        this.input = document.getElementById(contentEditorSelectors.ids.featuredImageInput);
    }

    #listenToEvents() {
        if(this.isReadOnly()) return;
        window.mediaLibrary.eventEmitter.on(mediaLibraryEvents.mediaReadyForInsert, (data) => {
           if(data.initiator === this.featuredImageButton) {
               if(data?.mediaData[0]?.img) {
                   const existingImg = this.featuredImageButton.querySelector('img');
                   if(existingImg) {
                       existingImg.remove();
                   }
                   this.featuredImageButton.insertAdjacentHTML('beforeend', data.mediaData[0].img);
                   this.input.value = data?.mediaData[0].id
                   this.src = data?.mediaData[0].filename;
                   this.eventEmitter.emit(events.newImageInserted, {...data});
               }
           }
        });
    }

    getData() {
        return {id: this.getId(), src: this.src};
    }


    setFeaturedImage({id, src}) {
        if(id && src) {
            const existingImg = this.featuredImageButton.querySelector('img');
            if(existingImg) {
                existingImg.remove();
            }
            this.featuredImageButton.insertAdjacentHTML('beforeend', `<img src="${(this.config.imagePath ?? '') + src}" alt="Featured Image" />`);
            this.input.value = id;
            this.src = src;
            this.eventEmitter.emit(events.newImageInserted, {id: id, src: src});
        }
    }

    getId() {
        if(this.input.value.trim() === '') {
            return null;
        }
        return parseInt(this.input.value, 10);
    }


    destroy() {
        super.destroy();
    }
}