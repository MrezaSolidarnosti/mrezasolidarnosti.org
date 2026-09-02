import Config from "../../../vendor/skeletorjs/src/Config/Config.js";
import {translations} from "./config/translations.js";
import Translator from "../../../vendor/skeletorjs/src/Translator/Translator.js";

const configDirectory = './config';
import(`${configDirectory}/config-local.js`).then(({configLocal: configLocal}) => {
    Object.keys(configLocal).forEach((key) => {
        Config.set(key, configLocal[key]);
    });
}).catch((e) => {
    console.error(e);
    console.error('No config local found.');
});

Translator.setTranslations(translations);
Translator.setLanguage('en');