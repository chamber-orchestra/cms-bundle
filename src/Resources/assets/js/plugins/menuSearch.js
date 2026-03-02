'use strict';
import '../jquery-global';
import $ from 'jquery';
import 'select2/dist/js/select2';
import observer from "../../component/observer";

$.fn.select2.defaults.set("theme", "bootstrap-5");

const Select = {
    ASIDE: '#aside',
    INPUT: '#entity-search',
};

class EntitySearch {
    constructor(el) {
        this.$el = el;
        this.input = this.$el.querySelector(Select.INPUT);
        this._initSelect2();
    }

    _initSelect2() {
        $(this.input).select2({
            placeholder: 'Search...',
            minimumInputLength: 2,
            ajax: {
                url: '/abc/search/entities',
                dataType: 'json',
                delay: 250,
                data: params => ({ q: params.term }),
                processResults: data => ({ results: data }),
            },
        });

        $(this.input).on('select2:select', function (e) {
            let data = e.params.data;
            if (data.id) {
                window.location.href = data.id;
            }
        });
    }
}

observer.observe(Select.ASIDE, (el) => {
    new EntitySearch(el);
});
