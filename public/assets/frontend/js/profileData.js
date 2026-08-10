import Translator from "./Translator/Translator.js";

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('formProfileData');
    const submitButton = document.getElementById('formSubmit');
    const formEmailNotice = document.querySelector('#profileDataInfo p');
    const inputs = document.querySelectorAll('#profileDataInfo input');
    const messagesContainer = form.querySelector('.messagesContainer');
    const firstNameInput = form.querySelector('input[name="firstName"]');
    const lastNameInput = form.querySelector('input[name="lastName"]');
    const emailInput = form.querySelector('input[name="email"]');
    let isEdit = false;
    submitButton.addEventListener('click', (e) => {
        if(!isEdit) {
            e.preventDefault();
            submitButton.textContent = Translator.translate('Save');
            // formEmailNotice.textContent = 'U slučaju da želite da promenite e-mail stići će Vam verifikacioni e-mail koji ćete morati potvrditi.';
            inputs.forEach((input) => {
                if(input.name !== 'email') {
                    input.disabled = false;
                }
            });
        }
        isEdit = true;
    });

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        let valid = true;
        messagesContainer.innerHTML = '';
        if(firstNameInput.value.trim() === '') {
            messagesContainer.appendChild(getMessageElement(Translator.translate('Name is required.'), 'error'));
            valid = false;
        }
        if(lastNameInput.value.trim() === '') {
            messagesContainer.appendChild(getMessageElement(Translator.translate('Surname is required.'), 'error'));
            valid = false;
        }
        if(!valid) {
            return;
        }
        let resData = null;
        try {
            const res = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form)
            });
            resData = await res.json();
            if (!resData.success) {
                if (resData.data.errors.length) {
                    resData.data.errors.forEach((error) => {
                        if (Array.isArray(error)) {
                            error.forEach((errorEntry) => {
                                messagesContainer.appendChild(getMessageElement(errorEntry, 'error'));
                            });
                        } else {
                            messagesContainer.appendChild(getMessageElement(error, 'error'));
                        }
                    });
                }
            } else {
                messagesContainer.appendChild(getMessageElement(Translator.translate('Data has been changed successfully.'), 'success'));
            }
        } catch (e) {

        } finally {
            if (resData?.data?.token) {
                replaceCsrf(form, resData?.data?.token);
            }
        }
    });


    function getMessageElement(message, type) {
        const messageElement = document.createElement('span');
        messageElement.textContent = message;
        messageElement.classList.add(type);
        return messageElement;
    }

    function replaceCsrf(form, val) {
        form.querySelector('input[name^="_csrf"]').value = val;
    }

});