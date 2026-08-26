import LanguageSwitcher from "./languageSwitcher/LanguageSwitcher.js";
import Navigation from "./navigation/Navigation.js";
import Faq from "./faq/faq.js";
import LineGrid from "./lineGrid/lineGrid.js";
import InstructionsTable from "./instructionsTable/InstructionsTable.js?v=0.0.4";
import Translator from "./Translator/Translator.js";
import {translations} from "./config/translations.js?v=0.0.3";

Translator.setTranslations(translations);
const isEnglish = window.location.pathname === '/en' || window.location.pathname.startsWith('/en/');
Translator.setLanguage(isEnglish ? 'en' : 'sr');

document.addEventListener('DOMContentLoaded', () => {
    const languageSwitcherContainers = document.querySelectorAll('.languageSwitcher');
    languageSwitcherContainers.forEach((container) => {
        const languageSwitcher = new LanguageSwitcher(container);
        languageSwitcher.init();
    });

    const headerContent = document.getElementById('headerContent');
    const hamburger = document.getElementById('hamburger');
    const closeNav = document.getElementById('closeNavigation');
    if(headerContent && hamburger && closeNav) {
        const navigation = new Navigation({
            container: headerContent,
            hamburger,
            closeNav
        });
        navigation.init();
    }

    const faqContainers = document.querySelectorAll('.faq');
    faqContainers.forEach((faqContainer) => {
        const faq = new Faq(faqContainer);
        faq.init();
    });

    const testimonialSliders = document.querySelectorAll('.testimonialSlider');
    testimonialSliders.forEach((testimonialSlider) => {
        new Swiper(testimonialSlider, {
            loop:true,
            navigation:{
                nextEl:'.swiperNext',
                prevEl:'.swiperPrev',
            },
            pagination:{
                el:'.swiperPagination',
                clickable:true,
            },
        });
    });

    const lineGridDesktop = document.querySelector('.lineGrid');
    const lineGridMobile = document.querySelector('.lineGridWrapperMobile');
    if(lineGridDesktop && lineGridMobile) {
        const lineGrid = new LineGrid(lineGridDesktop, lineGridMobile);
        lineGrid.init();
    }

    const instructionsTables = document.querySelectorAll('.instructionsTable');
    instructionsTables.forEach((instructionsTable) => {
        const table = new InstructionsTable({container: instructionsTable});
        table.init();
    });

    const nlSignupForm = document.getElementById('nlSignup');
    const emailInput = document.getElementById('nlEmail');
    if(nlSignupForm) {
        const messagesContainer = nlSignupForm.querySelector('.messagesContainer');
        nlSignupForm.addEventListener('submit', async (e) => {
           e.preventDefault();
           let valid = true;
           messagesContainer.innerHTML = '';
            if(emailInput.value.trim() === '') {
                messagesContainer.appendChild(getMessageElement(Translator.translate('Email is required.'), 'error'));
                valid = false;
            }
            if(!validateEmail(emailInput.value)) {
                messagesContainer.appendChild(getMessageElement(Translator.translate('Email is not valid.'), 'error'));
                valid = false;
            }
            if(!valid) {
                return;
            }
           const res = await fetch('/emailList', {method: 'POST', body: new FormData(nlSignupForm)});
           const resData = await res.json();
            if(!resData.success) {
                if(resData.data.errors.length) {
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
                if(resData.data.token) {
                    replaceCsrf(nlSignupForm, resData.data.token);
                }
            } else {
                messagesContainer.appendChild(getMessageElement(Translator.translate('You have successfully subscribed.'), 'success'));
            }
        });
    }

    function getMessageElement(message, type) {
        const messageElement = document.createElement('span');
        messageElement.textContent = message;
        messageElement.classList.add(type);
        return messageElement;
    }

    function validateEmail(email) {
        const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return regex.test(email);
    }

    function replaceCsrf(form, val) {
        form.querySelector('input[name^="_csrf"]').value = val;
    }

});