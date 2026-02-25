'use strict';
import $ from "jquery";
import '../nestable/index'
import {button, State} from '../../component/button'

const NAMESPACE = '.dv.nestable';
const $list = $(".nestable");
$list
    .nestable()
    .on("change" + NAMESPACE, function (e, $el) {

        const action = $list.data("action") || false;
        if (!action) console.error("data-action url must be set on '.nest-list'");
        if ($list.data("disabled")) return;

        const $parent = $el.parent().closest("[data-id]");
        const parent = $parent.length ? $parent.data("id") : 0;

        const json = {
            id: $el.data("id"),
            parentId: parent,
            sortOrder: parseInt($el.index()) + 1
        };

        const $btn = $el.find('.nestable-handle:eq(0)');
        button($btn[0], State.LOADING)

        $list
            .addClass("disabled")
            .data("disabled", true);

        $.ajax({
            url: action,
            data: json,
            dataType: "json",
            type: "post",
            success: function (response) {
                $list.removeClass("disabled").data("disabled", false);
                button($btn[0], State.RESET)
            }
        })
    });
