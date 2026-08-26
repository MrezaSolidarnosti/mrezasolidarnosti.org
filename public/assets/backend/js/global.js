import Config from "https://skeletor.greenfriends.systems/skeletorjs/src/Config/Config.js";
import Translator from "https://skeletor.greenfriends.systems/skeletorjs/src/Translator/Translator.js";
import {translations} from "./config/translations.js";

const configDirectory = './config';
import(`${configDirectory}/config-local.js?v=2.0.2`).then(({configLocal: configLocal}) => {
    Object.keys(configLocal).forEach((key) => {
        Config.set(key, configLocal[key]);
    });
}).catch((e) => {
    console.error(e);
    console.error('No config local found.');
});

Translator.setTranslations(translations);
Translator.setLanguage('en');