'use strict';
import observer from "../observer";


const DATA_KEY = 'dv.mask';
const Events = {
    INPUT: 'input',
};


class Mask {
    constructor(el) {
        this.$el = el;
        this.$el.setAttribute('pattern', '^\\+7 \\([0-9]{3}\\) [0-9]{3}-[0-9]{2}-[0-9]{2}$');
        this.$el.setAttribute('placeholder', '+7 (___) ___-__-__');
        this._parse();
        this._attachEvents();
    }

    _attachEvents() {
        this.$el.addEventListener(Events.INPUT, (e) => this._parse());
    }

    _parse() {
        let value = this.$el.value;
        value = value.replace(/[^\d\+]/g, '');
        if (!value) {
            this.$el.value = value;
            return;
        }

        let check = value.replace(/^(\+7)/, '');
        if (!check) {
            this.$el.value = '';
            return;
        }

        const match = value.match(/(?<country>\+?7|8)?(?<code>\d{0,3})(?<part0>\d{0,3})(?<part1>\d{0,2})(?<part2>\d{0,2})/);
        const groups = match.groups;

        value = '+7 ('
            + groups.code
            + (groups.part0 ? ') ' : '')
            + groups.part0
            + (groups.part1 ? '-' + groups.part1 : '')
            + (groups.part2 ? '-' + groups.part2 : '');

        this.$el.value = value;
    }

}

observer.observe('input[type=tel]', (elem) => {
    if (!elem[DATA_KEY]) {
        elem[DATA_KEY] = new Mask(elem);
    }
});
