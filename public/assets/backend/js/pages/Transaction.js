import CrudPage from "../../../../vendor/skeletorjs/src/Page/CrudPage.js";
import Message from "../../../../vendor/skeletorjs/src/Message/Message.js";


export default class Educator extends CrudPage {
    #data;
    #formTabs;
    #formAction;
    #projectPeriodFilterCleanup = null;
    #paymentMethodPreviewCleanup = null;

    constructor() {
        super();
        this.dataTableOptions = {
            enableCheckboxes: true,
            shiftCheckboxModifier: true
        };
        this.modalOptions = {
            createModalWidth: '70%',
            createModalHeight: '70%',
            editModalWidth: '70%',
            editModalHeight: '70%'
        }
    }

    onFormReady(data) {
        this.#initProjectPeriodFilter();
        this.#initPaymentMethodPreview();
    }

    onModalBeforeClose() {
        if (this.#projectPeriodFilterCleanup) {
            this.#projectPeriodFilterCleanup();
            this.#projectPeriodFilterCleanup = null;
        }
        if (this.#paymentMethodPreviewCleanup) {
            this.#paymentMethodPreviewCleanup();
            this.#paymentMethodPreviewCleanup = null;
        }
    }

    #initProjectPeriodFilter() {
        const mapEl = document.getElementById('transactionPeriodMap');
        if (!mapEl) return;

        const periodProjectMap = JSON.parse(mapEl.textContent);
        const context = mapEl.parentElement;
        const projectSelect = context.querySelector('[name="project"]');
        const periodSelect = context.querySelector('[name="period"]');
        if (!projectSelect || !periodSelect) return;

        periodSelect.querySelectorAll('option').forEach(option => {
            const projectId = periodProjectMap[option.value];
            if (projectId !== undefined) {
                option.setAttribute('data-project-id', projectId);
            }
        });

        const filterPeriods = () => {
            const selectedProject = projectSelect.value;
            periodSelect.querySelectorAll('option').forEach(option => {
                const projectId = option.getAttribute('data-project-id');
                if (!projectId || String(projectId) === String(selectedProject)) {
                    option.style.display = '';
                    option.disabled = false;
                } else {
                    option.style.display = 'none';
                    option.disabled = true;
                }
            });
            const selected = periodSelect.querySelector(`option[value="${periodSelect.value}"]`);
            if (selected && selected.style.display === 'none') {
                periodSelect.value = '';
            }
        };

        projectSelect.addEventListener('change', filterPeriods);
        if (projectSelect.value) filterPeriods();

        this.#projectPeriodFilterCleanup = () => {
            projectSelect.removeEventListener('change', filterPeriods);
        };
    }

    #initPaymentMethodPreview() {
        const mapEl = document.getElementById('transactionPeriodMap');
        const context = mapEl ? mapEl.parentElement : document;
        const donorInput = context.querySelector('[name="donor"]');
        const beneficiaryInput = context.querySelector('[name="beneficiary"]');
        const projectSelect = context.querySelector('[name="project"]');
        const periodSelect = context.querySelector('[name="period"]');
        const previewEl = document.getElementById('paymentMethodPreview');
        if (!donorInput || !beneficiaryInput || !previewEl) return;

        // The preview div is rendered outside the <form> tag — move it inside, before the submit button
        const submitBtn = context.querySelector('[type="submit"]');
        if (submitBtn) {
            submitBtn.before(previewEl);
        }

        const fetchPreview = async () => {
            const donorId = donorInput.value;
            const beneficiaryId = beneficiaryInput.value;
            const projectId = projectSelect ? projectSelect.value : '';
            const periodId = periodSelect ? periodSelect.value : '';
            if (!donorId || !beneficiaryId) {
                previewEl.innerHTML = '';
                return;
            }
            try {
                let url = `/transaction/getPaymentMethodPreview/?donorId=${donorId}&beneficiaryId=${beneficiaryId}`;
                if (projectId && periodId) {
                    url += `&projectId=${projectId}&periodId=${periodId}`;
                }
                const res = await fetch(url);
                if (!res.ok) {
                    previewEl.innerHTML = `<div class="paymentMethodPreviewBox error">Greška servera (${res.status}).</div>`;
                    return;
                }
                const data = await res.json();
                if (data.success) {
                    const detail = data.data.accountNumber || data.data.instructions || '—';
                    let html = `<div class="paymentMethodPreviewBox">
                        <strong>Način plaćanja:</strong> ${data.data.paymentTypeLabel}<br>
                        <strong>Detalji:</strong> ${detail}`;
                    if (data.data.maxAmount !== undefined) {
                        html += `<br><strong>Dozvoljeni iznos:</strong> 0 - ${data.data.maxAmount.toLocaleString('sr-RS')} RSD`;
                    }
                    html += `</div>`;
                    previewEl.innerHTML = html;
                } else {
                    previewEl.innerHTML = `<div class="paymentMethodPreviewBox error">${data.message}</div>`;
                }
            } catch (e) {
                previewEl.innerHTML = `<div class="paymentMethodPreviewBox error">Greška pri učitavanju načina plaćanja.</div>`;
            }
        };

        // Poll for value changes — AjaxInputSearch sets the hidden input value
        // programmatically so a plain change listener is not enough
        let prevState = `${donorInput.value}|${beneficiaryInput.value}|${projectSelect?.value}|${periodSelect?.value}`;
        const pollInterval = setInterval(() => {
            const state = `${donorInput.value}|${beneficiaryInput.value}|${projectSelect?.value}|${periodSelect?.value}`;
            if (state !== prevState) {
                prevState = state;
                fetchPreview();
            }
        }, 300);

        // change event is still dispatched by AjaxInputSearch on clear — keep as extra trigger
        donorInput.addEventListener('change', fetchPreview);
        beneficiaryInput.addEventListener('change', fetchPreview);
        if (projectSelect) projectSelect.addEventListener('change', fetchPreview);
        if (periodSelect) periodSelect.addEventListener('change', fetchPreview);

        if (donorInput.value && beneficiaryInput.value) fetchPreview();

        this.#paymentMethodPreviewCleanup = () => {
            clearInterval(pollInterval);
            donorInput.removeEventListener('change', fetchPreview);
            beneficiaryInput.removeEventListener('change', fetchPreview);
            if (projectSelect) projectSelect.removeEventListener('change', fetchPreview);
            if (periodSelect) periodSelect.removeEventListener('change', fetchPreview);
        };
    }

    preload() {
        this.setDataTableAction({
            name: 'confirm',
            label: 'Potvrdi',
            asText: true,
            content: 'Potvrdi',
            order: 1,
            callback: async (entity) => {
                // change status here with fetch
                const req = await fetch('/transaction/updateStatus/' + entity.id + '/?status=3');
                const res = await req.json();
                if (res.success) {
                    Message.spawn({
                        message: res.message,
                        type: Message.TYPES.SUCCESS,
                        view: {
                            container: this.getMessagesContainerFixed(),
                            type: Message.VIEW_TYPES.NOTIFICATION,
                        },
                        ephemeralTimeout: 4000
                    });
                } else {
                    Message.spawn({
                        message: res.message,
                        type: Message.TYPES.ERROR,
                        view: {
                            container: this.getMessagesContainerFixed(),
                            type: Message.VIEW_TYPES.NOTIFICATION,
                        },
                        ephemeralTimeout: 4000
                    });
                }

                this.reloadTable(true);
            }
        });
        this.setDataTableAction({
            name: 'cancel',
            label: 'Otkaži',
            asText: true,
            content: 'Otkaži',
            order: 1,
            callback: async (entity) => {
                // change status here with fetch
                const req = await fetch('/transaction/updateStatus/' + entity.id + '/?status=4');
                const res = await req.json();
                if (res.success) {
                    Message.spawn({
                        message: res.message,
                        type: Message.TYPES.SUCCESS,
                        view: {
                            container: this.getMessagesContainerFixed(),
                            type: Message.VIEW_TYPES.NOTIFICATION,
                        },
                        ephemeralTimeout: 4000
                    });
                } else {
                    Message.spawn({
                        message: res.message,
                        type: Message.TYPES.ERROR,
                        view: {
                            container: this.getMessagesContainerFixed(),
                            type: Message.VIEW_TYPES.NOTIFICATION,
                        },
                        ephemeralTimeout: 4000
                    });
                }

                this.reloadTable(true);
            }
        });

        this.setDataTableBulkAction({
            name: 'confirm',
            content: 'Potvrdi',
            useLoader: true,
            // promptMessage: Translator.translate('Are you sure?'),
            callback: async (ids) => {
                if(ids.length === 0) {
                    return;
                }
                const req = await fetch('/transaction/updateStatusBulk/?status=3', {
                    method: 'POST',
                    body: JSON.stringify({ids: ids})
                });
                if(req.redirected && req.url.includes('loginForm')) {
                    Message.spawn({
                        message: `<div>${'Your session has expired'}. ${'Please'} <a style="color:#4fc46d" href="/" title="log in">${'log in'}</a> ${'again'}.</div>`,
                        type: Message.TYPES.ERROR,
                        view: {
                            container: this.getMessagesContainerFixed(),
                            type: Message.VIEW_TYPES.NOTIFICATION,
                        }
                    });
                    return;
                }
                const res = await req.json();
                if(res.success) {
                    Message.spawn({
                        message: res.message,
                        type: Message.TYPES.SUCCESS,
                        view: {
                            container: this.getMessagesContainerFixed(),
                            type: Message.VIEW_TYPES.NOTIFICATION,
                        },
                        ephemeralTimeout: 4000
                    });
                    this.reloadTable();
                } else {
                    Message.spawn({
                        message: res.message,
                        type: Message.TYPES.ERROR,
                        view: {
                            container: this.getMessagesContainerFixed(),
                            type: Message.VIEW_TYPES.NOTIFICATION,
                        },
                        ephemeralTimeout: 4000
                    });
                }
            }
        });

        this.setDataTableBulkAction({
            name: 'cancel',
            content: 'Otkaži',
            useLoader: true,
            // promptMessage: Translator.translate('Are you sure?'),
            callback: async (ids) => {
                if(ids.length === 0) {
                    return;
                }
                const req = await fetch('/transaction/updateStatusBulk/?status=4', {
                    method: 'POST',
                    body: JSON.stringify({ids: ids})
                });
                if(req.redirected && req.url.includes('loginForm')) {
                    Message.spawn({
                        message: `<div>${'Your session has expired'}. ${'Please'} <a style="color:#4fc46d" href="/" title="log in">${'log in'}</a> ${'again'}.</div>`,
                        type: Message.TYPES.ERROR,
                        view: {
                            container: this.getMessagesContainerFixed(),
                            type: Message.VIEW_TYPES.NOTIFICATION,
                        }
                    });
                    return;
                }
                const res = await req.json();
                if(res.success) {
                    Message.spawn({
                        message: res.message,
                        type: Message.TYPES.SUCCESS,
                        view: {
                            container: this.getMessagesContainerFixed(),
                            type: Message.VIEW_TYPES.NOTIFICATION,
                        },
                        ephemeralTimeout: 4000
                    });
                    this.reloadTable();
                } else {
                    Message.spawn({
                        message: res.message,
                        type: Message.TYPES.ERROR,
                        view: {
                            container: this.getMessagesContainerFixed(),
                            type: Message.VIEW_TYPES.NOTIFICATION,
                        },
                        ephemeralTimeout: 4000
                    });
                }
            }
        });
    }

    actionFilter = (action, entity) => {
        const role = document.getElementById('navigation').dataset.role;
        if (action.getName() === 'delete' && role != 1) {
            return false;
        }
        // Confirm/cancel are available while the transaction is still open — status NEW
        // ("Čeka se uplata") or WAITING_CONFIRMATION ("Čeka se potvrda građana"). Once it's
        // finalized (confirmed/cancelled/paid/expired) the actions are hidden.
        const changeableStatuses = ['Čeka se uplata', 'Čeka se potvrda građana'];
        if (!changeableStatuses.includes(entity.columns.status) && (action.getName() === 'confirm' || action.getName() === 'cancel')) {
            return false;
        }

        return action;
    }

    tdStyler = (td, columnName, columnValue, entity) => {
        if (columnName === 'project') {
            switch (columnValue) {
                case 'MSP':
                    this.makeTDValueToBadge(td, columnValue, CrudPage.BADGE_TYPES.BLUE);
                    break;
                case 'MSPR':
                    this.makeTDValueToBadge(td, columnValue, CrudPage.BADGE_TYPES.GREEN);
                    break;
            }
        }
        return td;
    }
}