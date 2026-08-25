import {mediaLibrarySelectors} from "./mediaLibrarySelectors.js";
import Initiator from "./Initiator.js";
import EventEmitter from "../EventEmitter/EventEmitter.js";
import {events} from "./events.js";
import Loader from "../Loader/Loader.js";
import Cell from "./Cell/Cell.js";
import Response from "../Response/Response.js";
import Message from "../Message/Message.js";
import ProgressBar from "../ProgressBar/ProgressBar.js";


export default class MediaLibrary {

    overlay;
    uploadMediaButton;
    topBar;
    uploadInput;
    searchInput;
    fileTypeSelect;
    closeButton;
    cellsContainer;
    cellsScrollableContainer;
    sidebar;
    insertMediaButton;
    bottomBar;
    existingInitiators = [];
    activeInitiator;
    fromDateFilter;
    toDateFilter;
    #cells = [];
    #selectedCells = new Map();

    #insertable;
    #multiple;
    #allowDocuments;
    #allowImages;
    #scrollingDisabled = false;

    #page = 0;
    #perPage = 30;

    static FIlE_TYPES = {
        IMAGE: 0,
        DOCUMENT: 1
    }


    dateFilterColumn = 'createdAt';
    static imagePath = '/images';
    static filePath = '/files';
    static imageEndpoint = '/image/tableHandler/';
    static documentEndpoint = '/file/tableHandler/';
    static imageCreateEndpoint = '/image/create/';
    static documentCreateEndpoint = '/file/create/';
    static imageUpdateEndpoint = '/image/update/';
    static documentUpdateEndpoint = '/file/update/';

    eventEmitter = new EventEmitter();
    #loader = new Loader();
    #progressBar = new ProgressBar();
    #searchTimeout;

    #fetchController;
    #fetchSignal;

    init() {
        try {
            this.#setProperties();
            this.#addListeners();
            this.#listenToEvents();
            this.#initExisting();
        } catch (e) {
            console.error(e);
        }
    }

    #setProperties() {
        this.overlay = document.getElementById(mediaLibrarySelectors.ids.overlay);
        this.uploadMediaButton = document.getElementById(mediaLibrarySelectors.ids.uploadMediaButton);
        this.uploadInput = document.getElementById(mediaLibrarySelectors.ids.uploadInput);
        this.searchInput = document.getElementById(mediaLibrarySelectors.ids.searchInput);
        this.fileTypeSelect = document.getElementById(mediaLibrarySelectors.ids.fileTypeSelect);
        this.closeButton = document.getElementById(mediaLibrarySelectors.ids.closeButton);
        this.cellsContainer = document.getElementById(mediaLibrarySelectors.ids.cellsContainer);
        this.sidebar = document.getElementById(mediaLibrarySelectors.ids.sidebar);
        this.insertMediaButton = document.getElementById(mediaLibrarySelectors.ids.insertMediaButton);
        this.cellsScrollableContainer = document.getElementById(mediaLibrarySelectors.ids.cellsScrollableContainer);
        this.bottomBar = document.getElementById(mediaLibrarySelectors.ids.bottomBar);
        this.topBar = document.getElementById(mediaLibrarySelectors.ids.topBar);
        this.progressBarContainer = document.getElementById(mediaLibrarySelectors.ids.progressBarContainer);
        this.fromDateFilter = document.getElementById(mediaLibrarySelectors.ids.filterFrom);
        this.toDateFilter = document.getElementById(mediaLibrarySelectors.ids.filterTo);
        if(!this.overlay) {
            throw new Error(`${mediaLibrarySelectors.ids.overlay} not found`);
        }
        if(!this.uploadInput) {
            throw new Error(`${mediaLibrarySelectors.ids.uploadInput} not found`);
        }
        if(!this.uploadMediaButton) {
            throw new Error(`${mediaLibrarySelectors.ids.uploadMediaButton} not found`);
        }
        if(!this.searchInput) {
            throw new Error(`${mediaLibrarySelectors.ids.searchInput} not found`);
        }
        if(!this.fileTypeSelect) {
            throw new Error(`${mediaLibrarySelectors.ids.fileTypeSelect} not found`);
        }
        if(!this.closeButton) {
            throw new Error(`${mediaLibrarySelectors.ids.closeButton} not found`);
        }
        if(!this.cellsContainer) {
            throw new Error(`${mediaLibrarySelectors.ids.cellsContainer} not found`);
        }
        if(!this.sidebar) {
            throw new Error(`${mediaLibrarySelectors.ids.sidebar} not found`);
        }
        if(!this.insertMediaButton) {
            throw new Error(`${mediaLibrarySelectors.ids.insertMediaButton} not found`);
        }
        if(!this.cellsScrollableContainer) {
            throw new Error(`${mediaLibrarySelectors.ids.cellsScrollableContainer} not found`);
        }
        if(!this.bottomBar) {
            throw new Error(`${mediaLibrarySelectors.ids.bottomBar} not found`);
        }
        if(!this.topBar) {
            throw new Error(`${mediaLibrarySelectors.ids.topBar} not found`);
        }
        if(!this.progressBarContainer) {
            throw new Error(`${mediaLibrarySelectors.ids.progressBarContainer} not found`);
        }
        if(!this.fromDateFilter) {
            throw new Error(`${mediaLibrarySelectors.ids.filterFrom} not found`);
        }
        if(!this.toDateFilter) {
            throw new Error(`${mediaLibrarySelectors.ids.filterTo} not found`);
        }
    }

    #addListeners() {
        this.#addCloseListener();
        this.#addSearchListener();
        this.#addUploadListener();
        this.#addFileTypeSelectListener();
        this.#addInsertListener();
        this.#addLazyLoadListener();
        this.#addDateListeners();
    }

    #addCloseListener() {
        this.closeButton.addEventListener('click', this.#closeHandler);
    }

    #closeHandler = () => {
        this.eventEmitter.emit(events.close);
    }

    #addSearchListener() {
        this.searchInput.addEventListener('input', this.#searchHandler);
    }

    #searchHandler = () => {
        this.#scrollingDisabled = true;
        clearTimeout(this.#searchTimeout);
        this.#searchTimeout = setTimeout(async () => {
            this.#cells.forEach((cell) => {
                cell.destroy();
            });
            this.#cells = [];
            this.#selectedCells.clear();
            this.#page = 0;
            await this.#fetchMedia();
        },300);
    }

    #addUploadListener() {
        this.uploadMediaButton.addEventListener('click', this.#uploadClickHandler);
        this.uploadInput.addEventListener('change', this.#uploadHandler);
    }

    #uploadClickHandler = () => {
        this.uploadInput.click();
    }

    #uploadHandler = async () => {
        Message.removeMessages(this.cellsContainer);
        let endpoint = null;
        let entity;
        switch(this.getActiveFileType()) {
            case MediaLibrary.FIlE_TYPES.IMAGE:
                endpoint = MediaLibrary.imageCreateEndpoint;
                entity = 'image';
                break;
            case MediaLibrary.FIlE_TYPES.DOCUMENT:
                entity = 'file';
                endpoint = MediaLibrary.documentCreateEndpoint;
                break;
        }
        if(endpoint) {
            let dummyCells = {};
            for (let i = 0; i < this.uploadInput.files.length; i++) {
                dummyCells[i] = {};
                dummyCells[i].cell = Cell.generateDummyCell();
                dummyCells[i].loader = new Loader();
                this.cellsContainer.prepend(dummyCells[i].cell);
                dummyCells[i].loader.start(dummyCells[i].cell);
            }
            for(let i = 0; i < this.uploadInput.files.length; i++) {
                const data = new FormData();
                data.append(entity, this.uploadInput.files[i]);
                try {

                    let req = await fetch(endpoint, {
                        method: 'POST',
                        body: data
                    });
                    let res = await req.json();
                    const response = new Response(res);
                    if(response.getData() && response.getStatus()) {
                        this.#replaceDummyWithCell([response.getData()]);
                    } else {
                        if (response.getErrors()) {
                            response.getErrors().forEach((error) => {
                                Message.spawn({
                                    message: error.message,
                                    type: Message.TYPES.ERROR,
                                    view: {
                                        container: this.cellsContainer,
                                        prepend: true
                                    },
                                })
                            })
                        }
                        if(response.getGeneralErrors()) {
                            response.getGeneralErrors().forEach((error) => {
                                Message.spawn({
                                    message: error.message,
                                    type: Message.TYPES.ERROR,
                                    view: {
                                        container: this.cellsContainer,
                                        prepend: true
                                    },
                                })
                            })
                        }
                    }
                    dummyCells[i].loader.stop();
                    dummyCells[i].loader.destroy();
                    dummyCells[i].cell.remove();
                } catch(e) {
                    dummyCells[i].loader.stop();
                    dummyCells[i].loader.destroy();
                    dummyCells[i].cell.remove();
                    console.error(e);
                }
            }
            dummyCells = null;
        }
        this.uploadInput.value = null;
    }

    #replaceDummyWithCell(data) {
        let fragment = document.createDocumentFragment();
        data.forEach((mediaData) => {
            const cell = new Cell({
                data: mediaData,
                fileType: this.getActiveFileType(),
                eventEmitter: this.eventEmitter
            });
            this.#cells.push(cell);
            fragment.appendChild(cell.getView());
        });
        let lastDummyCell = Cell.getLastDummyCell();
        if(lastDummyCell) {
            this.cellsContainer.insertBefore(fragment, lastDummyCell.nextSibling);
        }
    }

    #addFileTypeSelectListener() {
        this.fileTypeSelect.addEventListener('change', this.#fileTypeSelectHandler);
    }

    #fileTypeSelectHandler = async () => {
        this.#scrollingDisabled = true;
        this.cellsScrollableContainer.scrollTop = 0;
        this.searchInput.value = '';
        this.#page = 0;
        this.#cells.forEach((cell) => {
            cell.destroy();
        });
        this.#cells = [];
        this.#selectedCells.clear();
        switch(this.getActiveFileType()) {
            case MediaLibrary.FIlE_TYPES.IMAGE:
                this.uploadInput.setAttribute('accept', 'image/*');
                break;
            case MediaLibrary.FIlE_TYPES.DOCUMENT:
                this.uploadInput.setAttribute('accept', '.xlsx,.xls,.doc, .docx,.ppt, .pptx,.txt,.pdf');
                break;
        }
        await this.#fetchMedia();
    }

    #addInsertListener() {
        this.insertMediaButton.addEventListener('click', this.#insertHandler);
    }

    #insertHandler = () => {
        let data = [];
        this.#selectedCells.forEach((cell) => {
            const cellData = {};
            cellData.id = cell.id;
            Object.keys(cell.data).forEach((key) => {
                cellData[key] = cell.data[key];
            });
            data.push(cellData);
        });
        this.eventEmitter.emit(events.mediaReadyForInsert, {
            initiator: this.activeInitiator.getInitiatorElement(),
            mediaData:data
        });
        this.eventEmitter.emit(events.close);
    }

    #addLazyLoadListener() {
        this.cellsScrollableContainer.addEventListener('scroll', this.#lazyLoadHandler);
    }

    #lazyLoadHandler = async () => {
        if(this.#scrollingDisabled) {
            return;
        }
        if(this.cellsScrollableContainer.scrollTop + this.cellsScrollableContainer.clientHeight + 1 >= this.cellsScrollableContainer.scrollHeight) {
            this.#scrollingDisabled = true;
            await this.#fetchMedia();
        }
    }

    #addDateListeners() {
        this.fromDateFilter.addEventListener('change', this.#dateFilterHandler);
        this.toDateFilter.addEventListener('change', this.#dateFilterHandler);
    }

    #dateFilterHandler = async () => {
        this.#scrollingDisabled = true;
        this.#cells.forEach((cell) => {
            cell.destroy();
        });
        this.#cells = [];
        this.#selectedCells.clear();
        this.#page = 0;
        await this.#fetchMedia();
    }

    #listenToEvents() {
        this.#listenToOpen();
        this.#listenToClose();
        this.#listenToCellSelected();
        this.#listenToCellUnselected();
    }

    #listenToOpen() {
        this.eventEmitter.on(events.open, async (data) => {
            this.#scrollingDisabled = true;
            this.#setPropertiesFromData(data);
            this.#setAttributesFromProperties();
            this.#open();
            this.#fetchController = new AbortController();
            this.#fetchSignal = this.#fetchController.signal;
            await this.#fetchMedia();
            this.#scrollingDisabled = false;
        });
    }

    #listenToClose() {
        this.eventEmitter.on(events.close, () => {
            if (!this.#fetchSignal.aborted) {
                this.#fetchController.abort('MediaLibrary closed');
            }
            this.#fetchController = null;
            this.#fetchSignal = null;
            Message.removeMessages(this.cellsContainer);
            this.#clearProperties();
            this.#resetAttributesFromProperties();
            this.#close();
            this.#cells.forEach((cell) => {
                cell.destroy();
            });
            this.#cells = [];
            this.#selectedCells.clear();
            this.searchInput.value = '';
            this.#hideInsertButton();
            this.#page = 0;
            this.#scrollingDisabled = false;
            this.#progressBar.stop();
        });
    }

    #listenToCellSelected() {
        this.eventEmitter.on(events.cellSelected, (cell) => {
            this.#selectedCells.forEach((selectedCell) => {
                if(!this.#multiple) {
                    selectedCell.unselect();
                } else {
                    selectedCell.removeForm();
                }
            });
            this.#selectedCells.set(cell.id, cell);
            this.#showInsertButton();
            this.#cellToSideBar(cell);
        });
    }

    #listenToCellUnselected() {
        this.eventEmitter.on(events.cellUnselected, (cell) => {
            cell.removeForm();
            this.#selectedCells.delete(cell.id);
            if (this.#multiple && this.#selectedCells.size > 0) {
                // Add the last selected cell to the sidebar
                /*
                * Cells is a map, so we can access the last element by using the index of the last element,
                * which is the size of the map - 1,
                * and then we access the value of the last element which is the cell (first element of the array)
                * */
                this.#cellToSideBar([...this.#selectedCells][this.#selectedCells.size - 1][1]);
            } else {
                this.#hideInsertButton();
            }
        });

    }

    #showInsertButton() {
        if(this.#insertable) {
            this.insertMediaButton.classList.remove(mediaLibrarySelectors.classes.hidden);
        }
    }

    #hideInsertButton() {
        this.insertMediaButton.classList.add(mediaLibrarySelectors.classes.hidden);
    }

    #cellToSideBar(cell) {
        this.sidebar.appendChild(cell.getForm());
        this.eventEmitter.emit(events.formRendered, {form: cell.getForm(), data: cell.getData()});
    }

    #setPropertiesFromData(data) {
        this.#insertable = data.insertable;
        this.#multiple = data.multiple;
        this.#allowDocuments = data.allowDocuments;
        this.#allowImages = data.allowImages;
    }

    #clearProperties() {
        this.#insertable = null;
        this.#multiple = null;
        this.#allowDocuments = null;
        this.#allowImages = null;
    }

    #setAttributesFromProperties() {
        this.fileTypeSelect.querySelector(`option[value="${MediaLibrary.FIlE_TYPES.DOCUMENT.toString()}"]`).disabled = true;
        this.fileTypeSelect.querySelector(`option[value="${MediaLibrary.FIlE_TYPES.IMAGE.toString()}"]`).disabled = true;
        if(this.#allowDocuments) {
            this.fileTypeSelect.querySelector(`option[value="${MediaLibrary.FIlE_TYPES.DOCUMENT.toString()}"]`).removeAttribute('disabled');
            this.fileTypeSelect.value = MediaLibrary.FIlE_TYPES.DOCUMENT.toString();
            this.uploadInput.setAttribute('accept', '.xlsx,.xls,.doc, .docx,.ppt, .pptx,.txt,.pdf');
        }
        if(this.#allowImages) {
            this.fileTypeSelect.querySelector(`option[value="${MediaLibrary.FIlE_TYPES.IMAGE.toString()}"]`).removeAttribute('disabled');
            this.fileTypeSelect.value = MediaLibrary.FIlE_TYPES.IMAGE.toString();
            this.uploadInput.setAttribute('accept', 'image/*');
        }
    }

    #resetAttributesFromProperties() {
        if(this.#allowDocuments) {
            this.fileTypeSelect.querySelector(`option[value="${MediaLibrary.FIlE_TYPES.DOCUMENT}"]`).setAttribute('disabled', 'disabled');
        }
        if(this.#allowImages) {
            this.fileTypeSelect.querySelector(`option[value="${MediaLibrary.FIlE_TYPES.IMAGE}"]`).setAttribute('disabled', 'disabled');
        }
    }


    #open() {
        this.overlay.classList.remove(mediaLibrarySelectors.classes.hidden);
    }

    #close() {
        this.overlay.classList.add(mediaLibrarySelectors.classes.hidden);
        if(this.activeInitiator) {
            this.activeInitiator.destroy();
            this.activeInitiator = null;
        }
    }

    async #fetchMedia() {
        this.#progressBar.start(this.progressBarContainer, 1);
        Message.removeMessages(this.cellsContainer);
        this.#makeCellContainerTransparent();
        let endpoint = null;
        switch(this.getActiveFileType()) {
            case MediaLibrary.FIlE_TYPES.IMAGE:
                endpoint = MediaLibrary.imageEndpoint;
                break;
            case MediaLibrary.FIlE_TYPES.DOCUMENT:
                endpoint = MediaLibrary.documentEndpoint;
                break;
        }
        if(endpoint) {
            const formData = new FormData();
            formData.append('filter[page]', (this.#page++).toString());
            formData.append('offset', ((this.#page > 1 ? ((this.#page - 1) * this.#perPage) : 0).toString()));
            if(this.searchInput.value.trim() !== '') {
                formData.append('search', `%${this.searchInput.value.trim()}%`);
            }
            const dateFilter = {};
            dateFilter[this.dateFilterColumn] = JSON.stringify({
                from: this.fromDateFilter.value,
                to: this.toDateFilter.value
            });
            formData.append('filter[rangeFilters]', JSON.stringify(dateFilter));
            try {
                let req = await fetch(endpoint, {
                    method: 'POST',
                    body: formData,
                    signal: this.#fetchSignal
                });
                let res = await req.json();
                this.#handleMediaResponse(res);
            } catch(e) {
                if(e.name === 'AbortError') {
                    console.log('Fetch aborted');
                    return;
                } else {
                    console.error(e);
                }
            }

        }
        this.#makeCellContainerOpaque();
        this.#progressBar.stop();
    }

    #makeCellContainerTransparent() {
        this.cellsContainer.style.opacity = '0.4';
        this.cellsContainer.style.pointerEvents = 'none';
    }

    #makeCellContainerOpaque() {
        this.cellsContainer.style.opacity = '1';
        this.cellsContainer.style.pointerEvents = 'all';
    }

    #handleMediaResponse(res) {
        if (res && res.entities && res.entities.data) {
            if(res.entities.data.length === 0) {
                this.#scrollingDisabled = true;
                return;
            }
            const fragment = document.createDocumentFragment();
            res.entities.data.forEach((mediaData) => {
                const cell = new Cell({
                    data: mediaData,
                    fileType: this.getActiveFileType(),
                    eventEmitter: this.eventEmitter
                });
                this.#cells.push(cell);
                fragment.appendChild(cell.getView());
                this.eventEmitter.emit(events.cellRendered, {cellView: cell.getView(), data: cell.getData()});
            });
            this.cellsContainer.appendChild(fragment);
            this.#scrollingDisabled = false;
        }
    }

    getActiveFileType() {
        return parseInt(this.fileTypeSelect.value, 10);
    }

    open(initiatorElement) {
        if(initiatorElement) {
            this.activeInitiator = new Initiator({initiatorElement});
            this.activeInitiator.init();
            this.eventEmitter.emit(events.open, this.activeInitiator.getAttributes());
        }
    }

    close() {
        this.eventEmitter.emit(events.close);
    }

    isOpen() {
        return !this.overlay.classList.contains(mediaLibrarySelectors.classes.hidden);
    }

    #initExisting() {
        const initiators = document.querySelectorAll(`.${mediaLibrarySelectors.classes.initiator}`);
        if(initiators.length === 0) {
            return;
        }
        initiators.forEach((existingInitiator) => {
            const initiator = new Initiator({
                initiatorElement: existingInitiator
            });
            initiator.init(existingInitiator);
            this.existingInitiators.push(initiator);
        });
    }

    destroy() {
        this.overlay = null;
        this.uploadInput.removeEventListener('change', this.#uploadHandler);
        this.#uploadHandler = null;
        this.uploadInput = null;
        this.uploadMediaButton.removeEventListener('click', this.#uploadClickHandler);
        this.#uploadClickHandler = null;
        this.uploadMediaButton = null;
        this.searchInput.removeEventListener('input', this.#searchHandler);
        this.#searchHandler = null;
        this.searchInput = null;
        this.fileTypeSelect.removeEventListener('change', this.#fileTypeSelectHandler);
        this.#fileTypeSelectHandler = null;
        this.fileTypeSelect = null;
        this.closeButton.removeEventListener('click', this.#closeHandler);
        this.closeButton = null;
        this.#closeHandler = null;
        this.cellsContainer = null;
        this.sidebar = null;
        this.existingInitiators.forEach((initiator) => {
            initiator.destroy();
        });
        this.existingInitiators = null;
        this.#cells.forEach((cell) => {
            cell.destroy();
        });
        this.#cells = null;
        this.eventEmitter.destroy();
        this.eventEmitter = null;
        this.#insertable = null;
        this.#multiple = null;
        this.#allowDocuments = null;
        this.#allowImages = null;
        if(this.activeInitiator) {
            this.activeInitiator.destroy();
            this.activeInitiator = null;
        }
        this.#clearProperties();
        this.#resetAttributesFromProperties();
        this.#loader.destroy();
        this.#loader = null;
        MediaLibrary.imageEndpoint = null;
        MediaLibrary.documentEndpoint = null;
        MediaLibrary.imagePath = null;
        MediaLibrary.imageCreateEndpoint = null;
        MediaLibrary.documentCreateEndpoint = null;
        MediaLibrary.imageUpdateEndpoint = null;
        MediaLibrary.documentUpdateEndpoint = null;
        this.#selectedCells.clear();
        this.#selectedCells = null;
        this.insertMediaButton.removeEventListener('click', this.#insertHandler);
        this.#insertHandler = null;
        this.insertMediaButton = null;
        if (!this.#fetchSignal.aborted) {
            this.#fetchController.abort('MediaLibrary destroyed');
        }
        this.#fetchController = null;
        this.#fetchSignal = null;
        this.cellsScrollableContainer.removeEventListener('scroll', this.#lazyLoadHandler);
        this.#lazyLoadHandler = null;
        this.cellsScrollableContainer = null;
        this.#scrollingDisabled = false;
        this.bottomBar = null;
        this.topBar = null;
        this.progressBarContainer = null;
        this.#progressBar.destroy();
        this.#progressBar = null;
        this.fromDateFilter.removeEventListener('change', this.#dateFilterHandler);
        this.toDateFilter.removeEventListener('change', this.#dateFilterHandler);
        this.fromDateFilter = null;
        this.toDateFilter = null;
        this.#dateFilterHandler = null;
    }
}