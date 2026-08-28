import Config from "../../../vendor/skeletorjs/src/Config/Config.js";
import {translations} from "./config/translations.js?v=1.0.0";
import Translator from "../../../vendor/skeletorjs/src/Translator/Translator.js";

const configDirectory = './config';
import(`${configDirectory}/config-local.js?v=2.0.3`).then(({configLocal: configLocal}) => {
    Object.keys(configLocal).forEach((key) => {
        Config.set(key, configLocal[key]);
    });
}).catch((e) => {
    console.error(e);
    console.error('No config local found.');
});

Translator.setTranslations(translations);
Translator.setLanguage('en');