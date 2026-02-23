'use strict';

const DEFAULT = document.documentElement.lang;
const FALLBACK = 'en';

class Translator {
    constructor(props) {
        this.dict = {};
    }

    add(...tran) {
        tran.forEach((el) => {
            const dict = this.dict[el.locale] || [];
            dict.push(el.translation);
            this.dict[el.locale] = dict;
        });
    }

    trans(message, params = {}, locale = DEFAULT) {

        const dict = this.dict[locale];
        let translated = this._getMessage(message, dict);

        if (translated === null) {
            if (locale !== FALLBACK) {
                return this.trans(message, {}, FALLBACK);
            }
            return message;
        }

        for (let key in params) {
            translated = translated.replace(`{{ ${key} }}`, params[key])
        }

        return translated;
    }

    _getMessage(message, dict) {

        if (dict === undefined) {
            return null;
        }

        for (let i = 0; i < dict.length; i++) {
            if (dict[i][message] !== undefined) {
                return dict[i][message];
            }
        }

        return null;
    }
}

const i18n = new Translator();
export default i18n;
