import Translator from "../Translator/Translator.js";

export default class Form {

    #setupComplete = false;
    #form;
    eventEmitter;
    #logoElement;
    #titleElement;
    #backToProfileButton;
    #closeFormButton;
    #modeTriggers;
    #modeInfo;
    #submitButton;
    #currentMode = 'monthly';
    // Each mode keeps its own amounts, so switching never carries the monthly pledge into
    // a one-time donation — and switching back restores what was there.
    #amountsByMode = {monthly: [], onetime: []};
    #paymentMethodsTriggers;
    #paymentTemplate;
    #formFieldsContainer;
    #projectInput;
    #messagesContainer;
    paymentLogoURLs =  {
        2: {
            dark: '/assets/frontend/images/payment/sepa.png',
            light: '/assets/frontend/images/payment/sepaW.png'
        },
        3: {
            dark: '/assets/frontend/images/payment/WU.png',
            light: '/assets/frontend/images/payment/wuW.png'
        },
        4: {
            dark: '/assets/frontend/images/payment/mg.png',
            light: '/assets/frontend/images/payment/mgW.png'
        }
    }
    constructor({form, eventEmitter}) {
        this.#form = form;
        this.eventEmitter = eventEmitter;
    }

    init() {
        if(this.#setupComplete) {
            return;
        }
        this.#setElements();
        this.#listenToEvents();
        this.#addListeners();
        this.#setupComplete = true;
    }

    #setElements() {
        this.#logoElement = this.#form.querySelector('#donationFormLogo');
        this.#titleElement = this.#form.querySelector('h2');
        this.#backToProfileButton = this.#form.querySelector('#backToProfile');
        this.#closeFormButton = this.#form.querySelector('#closeForm');
        this.#modeTriggers = this.#form.querySelectorAll('#frequencyFields .trigger');
        this.#modeInfo = this.#form.querySelector('#donationModeInfo');
        this.#submitButton = this.#form.querySelector('#donationFormActions button[type="submit"]');
        this.#paymentMethodsTriggers = this.#form.querySelectorAll('#paymentMethodFields .trigger');
        this.#paymentTemplate = document.getElementById('paymentTemplate');
        this.#formFieldsContainer = document.getElementById('donationFormFields');
        this.#projectInput = document.getElementById('projectInput');
        this.#messagesContainer = document.querySelector('.messagesContainer');
    }

    #listenToEvents() {
        this.eventEmitter.on('showForm', (project) => {
            window.scrollTo({
                top: 0,
                left: 0,
            });
            this.#form.className = '';
            this.#form.classList.add('active');
            this.#form.classList.add(project.id);
            this.#logoElement.innerHTML = project.logo;
            this.#titleElement.textContent = project.thankYouTitle;
            this.#projectInput.value = project.projectId;
            // Every open starts clean on the monthly mode; prefillForm (if the donor has a
            // pledge for this project) arrives right after and fills it in.
            this.#amountsByMode = {monthly: [], onetime: []};
            this.#applyMode('monthly');
        });
        this.eventEmitter.on('prefillForm', (data) => {
            this.#prefill(data);
        });
    }

    // Pre-fills the form with the donor's saved monthly pledge: one amount field per
    // saved payment method (currency is derived from the method). The one-time action
    // persists nothing, so there is never anything else to restore.
    #prefill(data) {
        this.#amountsByMode.monthly = data.paymentMethods ?? [];
        if (this.#currentMode === 'monthly') {
            this.#applyPaymentMethods(this.#amountsByMode.monthly);
        }
    }

    /**
     * Switch the form between saving a monthly pledge and creating a one-time instruction:
     * retarget the submit, relabel it, swap the explanatory line, and put that mode's own
     * amounts on screen. Falls back gracefully when the toggle is not rendered (no unmet
     * needs) — the markup defaults already describe the monthly mode.
     */
    #applyMode(mode) {
        this.#currentMode = mode;
        const trigger = Array.from(this.#modeTriggers).find((t) => t.getAttribute('data-mode') === mode);
        if (trigger) {
            this.#modeTriggers.forEach((t) => t.classList.remove('active'));
            trigger.classList.add('active');
            this.#form.action = trigger.getAttribute('data-action');
            const label = trigger.getAttribute('data-label');
            this.#submitButton.textContent = label;
            this.#submitButton.title = label;
            if (this.#modeInfo) {
                // Write into the text node only, so the info icon beside it survives.
                const infoText = this.#modeInfo.querySelector('.infoText') ?? this.#modeInfo;
                infoText.textContent = this.#modeInfo.getAttribute(`data-info-${mode}`) ?? '';
            }
        }
        this.#applyPaymentMethods(this.#amountsByMode[mode]);
    }

    /** Read the amount fields currently on screen, so a mode switch can restore them later. */
    #snapshotPaymentMethods() {
        return Array.from(this.#formFieldsContainer.querySelectorAll('.formFields[data-method]'))
            .map((fields) => ({
                type: parseInt(fields.getAttribute('data-method'), 10),
                amount: fields.querySelector('input[type="number"]')?.value ?? '',
            }));
    }

    /** Rebuild the amount fields from a list of {type, amount}; an empty list clears them. */
    #applyPaymentMethods(paymentMethods) {
        // Clear any previously built payment fields / active triggers first.
        this.#paymentMethodsTriggers.forEach((trigger) => trigger.classList.remove('active'));
        this.#formFieldsContainer.querySelectorAll('.formFields[data-method]').forEach((fields) => fields.remove());
        if(this.#projectInput.value === '2') {
            const triggers = document.querySelectorAll('#paymentMethodFields .triggerContainer .trigger');
            triggers.forEach((trigger) => {
               const method = parseInt(trigger.getAttribute('data-method'), 10);
               const img = trigger.querySelector('img');
               if(img) {
                   img.src = this.paymentLogoURLs[method].dark;
               }
            });

        }

        (paymentMethods ?? []).forEach((paymentMethod) => {
            const trigger = Array.from(this.#paymentMethodsTriggers)
                .find((trigger) => trigger.getAttribute('data-method') === String(paymentMethod.type));
            if (!trigger) {
                return;
            }
            // Reuse the trigger's own handler to build the amount/currency fields.
            trigger.click();
            const fields = this.#formFieldsContainer.querySelector(`.formFields[data-method="${paymentMethod.type}"]`);
            const amountInput = fields?.querySelector('input[type="number"]');
            if (amountInput) {
                amountInput.value = paymentMethod.amount;
            }
        });
    }


    #addListeners() {
        this.#backToProfileButton.addEventListener('click', this.#close);
        this.#closeFormButton.addEventListener('click', this.#close);
        this.#modeTriggers.forEach((trigger) => {
            trigger.addEventListener('click', () => {
                const mode = trigger.getAttribute('data-mode');
                if (mode === this.#currentMode) {
                    return;
                }
                // Stash what is on screen before swapping, so coming back restores it.
                this.#amountsByMode[this.#currentMode] = this.#snapshotPaymentMethods();
                this.#applyMode(mode);
            });
        });
        this.#paymentMethodsTriggers.forEach((trigger) => {
           trigger.addEventListener('mouseenter', () => {
               if(!trigger.classList.contains('active')) {
                   if(this.#projectInput.value !== '2') {
                       const img = trigger.querySelector('img');
                       const method = trigger.getAttribute('data-method');
                       if(img && this.#projectInput.value !== '2') {
                           img.src = this.paymentLogoURLs[parseInt(method, 10)].light;
                       }
                   }
               }
           });
            trigger.addEventListener('mouseleave', () => {
                if(!trigger.classList.contains('active')) {
                    if(this.#projectInput.value !== '2') {
                        const img = trigger.querySelector('img');
                        const method = trigger.getAttribute('data-method');
                        if(img && this.#projectInput.value !== '2') {
                            img.src = this.paymentLogoURLs[parseInt(method, 10)].dark;
                        }
                    }
                }
            });
           trigger.addEventListener('click', () => {
                const method = trigger.getAttribute('data-method');
                if(trigger.classList.contains('active')) {
                    trigger.classList.remove('active');
                    const existing = document.querySelector(`.formFields[data-method="${method}"]`);
                    existing.remove();
                    const img = trigger.querySelector('img');
                    if(img && this.#projectInput.value !== '2') {
                        img.src = this.paymentLogoURLs[parseInt(method, 10)].dark;
                    }
                } else {
                    trigger.classList.add('active');
                    const template = this.#paymentTemplate.content.cloneNode(true);
                    template.querySelector('h3').textContent = Translator.translate('AMOUNT') + ' ' + trigger.parentElement.querySelector(':scope >span').textContent;
                    template.querySelector('.formFields').setAttribute('data-method', method);
                    template.querySelector('input[type="number"]').name = `payment[${method}][value]`;
                    const img = trigger.querySelector('img');
                    if(img && this.#projectInput.value !== '2') {
                        img.src = this.paymentLogoURLs[parseInt(method, 10)].light;
                    }
                    // template.querySelectorAll('input[type="radio"]').forEach((radio) => {
                    //    radio.name = `payment[${method}][currency]`;
                    // });
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = `payment[${method}][currency]`;
                    const button = document.createElement('span');
                    button.classList.add('currency', 'active');
                    const amountInput = template.querySelector('.amountInput');
                    switch(method) {
                        case '1':
                            input.value = '1';
                            button.setAttribute('data-currency', 'RSD');
                            button.textContent = 'RSD';
                            amountInput.placeholder = 'min 500 RSD';
                            break;
                        case '2':
                        case '3':
                        case '4':
                            input.value = '2';
                            button.setAttribute('data-currency', 'EUR');
                            button.textContent = 'EUR';
                            amountInput.placeholder = 'min 10 EUR';
                            break;
                    }
                    const currenciesElement = template.querySelector('.currencies');
                    currenciesElement.appendChild(input);
                    currenciesElement.appendChild(button);
                    // template.querySelectorAll('.currency').forEach((currencyButton) => {
                    //     currencyButton.addEventListener('click', () => {
                    //        const currency = currencyButton.getAttribute('data-currency');
                    //        const inputs = currencyButton.parentElement.querySelectorAll('input');
                    //        inputs.forEach((input) => {
                    //           input.checked = currency === input.getAttribute('data-currency');
                    //        });
                    //        currencyButton.parentElement.querySelector('.currency.active').classList.remove('active');
                    //        currencyButton.classList.add('active');
                    //     });
                    // });
                    this.#formFieldsContainer.appendChild(template);
                }
           });
        });

        this.#form.addEventListener('submit', async (e) => {
            e.preventDefault();
            this.#messagesContainer.innerHTML = '';
            // Retargeted by #applyMode: the monthly pledge and the one-time instruction are
            // different endpoints. (Deliberately not e.submitter.formAction — with no
            // formaction attribute that getter returns the document URL, not the form's.)
            const action = this.#form.action;
            const res = await fetch(action, {
                method: 'POST',
                body: new FormData(this.#form)
            });
            // A 400/401 still carries a JSON body with the errors to show, so status alone
            // is not the check — only a body we cannot parse (a 404's or a fatal's HTML) is
            // unrecoverable. Without this the rejected json() killed the handler silently.
            let resData;
            try {
                resData = await res.json();
            } catch (parseError) {
                console.error(`${action} responded ${res.status} with a non-JSON body`, parseError);
                this.#messagesContainer.appendChild(this.#getMessageElement(
                    Translator.translate('Došlo je do greške, pokušajte ponovo.'),
                    'error'
                ));
                this.#scrollToMessagesContainer();
                return;
            }
            if(!resData.success) {
                if(resData.data.errors.length) {
                    resData.data.errors.forEach((error) => {
                        if (Array.isArray(error)) {
                            error.forEach((errorEntry) => {
                                this.#messagesContainer.appendChild(this.#getMessageElement(errorEntry, 'error'));
                            });
                            this.#scrollToMessagesContainer();
                        } else {
                            this.#messagesContainer.appendChild(this.#getMessageElement(error, 'error'));
                            this.#scrollToMessagesContainer();
                        }
                    });
                }
            } else {
                // A save is the only thing that moves the donor's chosen direction — merely
                // opening a card and closing it must leave the previous choice alone.
                this.eventEmitter.emit('donationSaved', this.#projectInput.value);
                if(resData?.data?.redirect) {
                    window.location.href = resData.data.redirect;
                }
                // this.#messagesContainer.appendChild(this.#getMessageElement(Translator.translate('Uspešno ste sačuvali izmene.'), 'success'));
                // const url = Translator.getLanguage() === 'en' ? '/en/how-it-works' : '/kako-funkcionise-mreza';
                // this.#messagesContainer.appendChild(this.#getMessageElement(
                //     `<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                //    <path d="M12 2C17.5228 2 22 6.47715 22 12C22 17.5228 17.5228 22 12 22C6.47715 22 2 17.5228 2 12C2 6.47715 6.47715 2 12 2ZM12 4C7.58172 4 4 7.58172 4 12C4 16.4183 7.58172 20 12 20C16.4183 20 20 16.4183 20 12C20 7.58172 16.4183 4 12 4ZM12 11C12.5523 11 13 11.4477 13 12V15H13.5C14.0523 15 14.5 15.4477 14.5 16C14.5 16.5523 14.0523 17 13.5 17H10.5C9.94772 17 9.5 16.5523 9.5 16C9.5 15.4477 9.94772 15 10.5 15H11V13H10.5C9.94772 13 9.5 12.5523 9.5 12C9.5 11.4477 9.94772 11 10.5 11H12ZM11.75 7C12.4404 7 13 7.55964 13 8.25C13 8.94036 12.4404 9.5 11.75 9.5C11.0596 9.5 10.5 8.94036 10.5 8.25C10.5 7.55964 11.0596 7 11.75 7Z" fill="#FE5101"></path>\n' +
                //     </svg><a href="${url}" title="${Translator.translate('Check out next steps.')}">${Translator.translate('Check out next steps.')}</a>`,
                //     'info', true));
                // this.#scrollToMessagesContainer();
            }
            if(resData.data.token) {
                this.#replaceCsrf(resData.data.token);
            }
        });
    }

    #scrollToMessagesContainer() {
        const top = this.#messagesContainer.getBoundingClientRect().top + window.scrollY - 100;

        window.scrollTo({
            top,
            behavior: 'smooth'
        });
    }

    #getMessageElement(message, type, asHTML = false) {
        const messageElement = document.createElement('span');
        if(!asHTML) {
            messageElement.textContent = message;
        } else {
            messageElement.innerHTML = message;
        }
        messageElement.classList.add(type);
        return messageElement;
    }

    #replaceCsrf(val) {
        this.#form.querySelector('input[name^="_csrf"]').value = val;
    }

    #close = (e) => {
        e.preventDefault();
        this.#form.className = '';
        this.#form.classList.remove('active');
        this.eventEmitter.emit('formClosed');
    }
}