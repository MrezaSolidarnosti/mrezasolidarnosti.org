export default class PaymentMethods {
    #initCompleted = false;
    container;
    addButton;
    template;
    #nextAvailableIdentifier = 0;
    #paymentMethods = new Map();
    init() {
        if (this.#initCompleted) {
            throw new Error('Init of Donor PaymentMethods had already been completed.');
        }
        this.#setElements();
        this.#addListeners();
        this.#initExisting();
        this.#initCompleted = true;
    }


    #setElements() {
        this.container = document.getElementById('paymentMethodsDonorList');
        this.addButton = document.getElementById('addPaymentMethodDonor');
        this.template = document.getElementById('donorPaymentMethodsTemplate');
        if(!this.container || !this.addButton || !this.template) {
            throw new Error('Required elements for Donor PaymentMethods are missing in the DOM');
        }
    }

    #addListeners() {
        this.addButton.addEventListener('click', this.#addHandler);
    }

    #initExisting() {
        const containers = document.querySelectorAll('.paymentMethodDonor');
        containers.forEach((container) => {
            this.#attachPaymentMethodFunctionality(container);
        });
    }

    #addHandler = (e) => {
        e.preventDefault();
        this.#attachPaymentMethodFunctionality();
    };


    #attachPaymentMethodFunctionality(container = null) {
        let containerExisted = container;
        if(!container) {
            const fragment = this.template.content.cloneNode(true);
            container = fragment.querySelector('.paymentMethodDonor');
        }
        const identifier = this.#nextAvailableIdentifier;
        const projectSelect = container.querySelector('.project');
        const paymentTypeSelect = container.querySelector('.paymentType');
        const monthlySelect = container.querySelector('.monthly');
        const amountInput = container.querySelector('.amount');
        const currencyView = container.querySelector('.currencyView');
        const currencyValue = container.querySelector('.currencyValue');
        const deleteButton = container.querySelector('.deletePaymentMethod');
        const paymentTypeCallback = () => {
            if(paymentTypeSelect.value === '1') {
                currencyView.value = 'RSD';
                currencyValue.value = '1';
            } else {
                currencyView.value = 'EUR';
                currencyValue.value = '2';
            }
        };
        paymentTypeSelect.addEventListener('change', paymentTypeCallback);

        const deleteCallback = (e) => {
            e.preventDefault();
            const id = parseInt(container.getAttribute('data-id'));
            if(this.#paymentMethods.has(id)) {
                this.#paymentMethods.delete(id);
            }
            container.remove();
        };
        deleteButton.addEventListener('click', deleteCallback, {once: true});

        projectSelect.name = `paymentMethods[${identifier}][project]`;
        paymentTypeSelect.name = `paymentMethods[${identifier}][paymentType]`;
        monthlySelect.name = `paymentMethods[${identifier}][monthly]`;
        amountInput.name = `paymentMethods[${identifier}][amount]`;
        currencyValue.name = `paymentMethods[${identifier}][currency]`;
        this.#paymentMethods.set(identifier, {
            container,
            paymentTypeSelect,
            deleteButton,
            deleteCallback,
            paymentTypeCallback,
        });

        if(!containerExisted) {
            this.container.appendChild(container);
        }
        this.#nextAvailableIdentifier++;
    }



    destroy() {
        if(this.addButton) {
            this.addButton.removeEventListener('click', this.#addHandler);
        }
        if(this.#paymentMethods.size) {
            this.#paymentMethods.forEach((data) => {
                data.deleteButton.removeEventListener('click', data.deleteCallback);
                data.paymentTypeSelect.removeEventListener('change', data.paymentTypeCallback);
            });
            this.#paymentMethods.clear();
        }
    }
}