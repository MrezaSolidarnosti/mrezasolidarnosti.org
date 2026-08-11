class Translator {

    #translations;
    BASE_LANGUAGE = 'en';
    #language = this.BASE_LANGUAGE;

    translate(word) {
        if(!this.#translations || !this.#language) {
            return word;
        }
        if (this.#translations[word] && this.#language !== this.BASE_LANGUAGE) {
            return this.#translations[word][this.#language] || word;
        }
        return word;
    }

    setLanguage(language) {
        this.#language = language;
    }

    setTranslations(translations) {
        this.#translations = translations;
    }
}

export default (new Translator());