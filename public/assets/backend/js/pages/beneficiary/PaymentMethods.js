export default class PaymentMethods {
    paymentMethodCheckboxes;
    #initCompleted = false;
    injectedInputsContainer = null;
    init() {
        if (this.#initCompleted) {
            throw new Error('Init of Beneficiary PaymentMethods had already been completed.');
        }
        this.#setElements();
        this.#addListeners();
        this.#initCompleted = true;
    }


    injectPaymentMethodsData() {
        this.paymentMethodCheckboxes.forEach((checkbox, index) => {
           if(checkbox.checked) {
               this.#injectInputForCheckbox(checkbox, index);
           }
        });
    }

    #injectInputForCheckbox(checkbox, index) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = `paymentMethods[${index}][type]`;
        input.value = checkbox.value;
        this.injectedInputsContainer.appendChild(input);
        const parent = checkbox.closest('.paymentMethod');
        if(checkbox.value === '1') {
            const bankAccountInput = document.createElement('input');
            bankAccountInput.type = 'hidden';
            bankAccountInput.name = `paymentMethods[${index}][bankAccount]`;
            bankAccountInput.value = parent.querySelector('.bankAccount').value;
            this.injectedInputsContainer.appendChild(bankAccountInput);
        }
        if(checkbox.value === '2') {
            const instructionsInput = document.createElement('input');
            instructionsInput.type = 'hidden';
            instructionsInput.name = `paymentMethods[${index}][wireInstructions]`;
            instructionsInput.value = parent.querySelector('.wireInstructions').value;
            this.injectedInputsContainer.appendChild(instructionsInput);
        }

    }

    removeInjectedPaymentMethodsData() {
        this.injectedInputsContainer.innerHTML = '';
    }

    #setElements()  {
        this.paymentMethodCheckboxes = document.querySelectorAll('.paymentMethodHandle input[type="checkbox"]');
        this.injectedInputsContainer = document.getElementById('injectedInputsForPaymentMethods');
        if(!this.paymentMethodCheckboxes || !this.injectedInputsContainer) {
            throw new Error('Required elements for PaymentMethods are missing in the DOM');
        }
    }

    #addListeners()  {
        this.paymentMethodCheckboxes.forEach((checkbox) => {
           checkbox.addEventListener('change', this.#checkboxCallback)
        });
    }

    #checkboxCallback = (e) => {
        const container = e.target.closest('.paymentMethod');
        const inputContainer = container.querySelector('.inputContainer');
        if(inputContainer) {
            if (e.target.checked) {
                inputContainer.classList.remove('hidden');
            } else {
                inputContainer.classList.add('hidden');
            }
        }
    };

    destroy() {
        this.paymentMethodCheckboxes.forEach((checkbox) => {
           checkbox.removeEventListener('change', this.#checkboxCallback);
        });
    }
}