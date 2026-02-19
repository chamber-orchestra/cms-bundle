'use strict';
import './index.scss'
import $ from "jquery";
import Collapse from 'bootstrap/js/src/collapse'

const NAMESPACE = ".dv.nestable";
const $doc = $(document);
const hasTouch = 'ontouchstart' in window;

const hasPointerEvents = (function () {
    const el = document.createElement('div'),
        docEl = document.documentElement;
    if (!('pointerEvents' in el.style)) {
        return false;
    }
    el.style.pointerEvents = 'auto';
    el.style.pointerEvents = 'x';
    docEl.appendChild(el);
    const supports = window.getComputedStyle && window.getComputedStyle(el, '').pointerEvents === 'auto';
    docEl.removeChild(el);
    return !!supports;
})();

const
    eStart = hasTouch ? 'touchstart' : 'mousedown',
    eMove = hasTouch ? 'touchmove' : 'mousemove',
    eEnd = hasTouch ? 'touchend' : 'mouseup',
    eCancel = hasTouch ? 'touchcancel' : 'mouseup';


const ClassName = {
    SHOW: 'show',
};

const Defaults = {
    handleNodeName: 'div',
    contentNodeName: 'span',
    rootClass: 'nestable',
    listClass: 'nestable-list',
    itemClass: 'nestable-item',
    dragClass: 'nestable-drag',
    createControlClass: 'nestable-create-control',
    toggleControlClass: 'nestable-toggle-control',
    handleClass: 'nestable-handle',
    expandedClass: "nestable-expanded",
    placeClass: 'nestable-placeholder',
    noChildrenClass: 'nestable-nochildren',
    emptyClass: 'nestable-empty',
    group: 0,
    maxDepth: 5,
    threshold: 30,
    fixedDepth: false, //fixed item's depth
    fixed: false,
};

class Nestable {

    constructor(element, options) {

        this.w = $doc;
        this.el = $(element);

        if (!options) {
            options = Defaults;
        }

        this.options = $.extend({}, Defaults, options);

        this.init();
    }


    init() {
        const list = this;

        list.reset();
        list.el.data('nestable-group', this.options.group);
        list.placeEl = $('<div class="' + list.options.placeClass + '"/>');


        // toggle
        list.el.on('click', '.' + list.options.toggleControlClass, function () {
            const $$ = $(this);
            const $item = $$.closest('.' + list.options.itemClass);

            $item.toggleControlClass(list.options.expandedClass)
                .find('>.' + list.options.listClass + '>.' + list.options.itemClass)
                .each(function () { Collapse.getOrCreateInstance(this).toggle(); });
        });

        // process depth creation leafs
        list.el.on('change' + NAMESPACE, function (e, $el) {
            $el.parent('.' + list.options.listClass).find('.' + list.options.itemClass).each((ind, el) => {
                const $el = $(el);
                const depth = $el.parents("." + list.options.listClass).length;
                const $control = $el.find('.' + list.options.createControlClass);
                depth >= list.options.maxDepth ? $control.removeClass(ClassName.SHOW) : $control.addClass(ClassName.SHOW);
            });

        });


        const onStartEvent = function (e) {
            let handle = $(e.target);
            if (!handle.hasClass(list.options.handleClass)) {
                handle = handle.closest('.' + list.options.handleClass);
            }

            if (!handle.length || list.dragEl || (!hasTouch && e.which !== 1) || (hasTouch && e.touches.length !== 1)) {
                return;
            }

            e.preventDefault();
            list.dragStart(hasTouch ? e.touches[0] : e);
        };

        const onMoveEvent = function (e) {
            if (list.dragEl) {
                e.preventDefault();
                list.dragMove(hasTouch ? e.touches[0] : e);
            }
        };

        const onEndEvent = function (e) {
            if (list.dragEl) {
                e.preventDefault();
                list.dragStop(hasTouch ? e.touches[0] : e);
            }
        };

        if (hasTouch) {
            list.el[0].addEventListener(eStart, onStartEvent, false);
            window.addEventListener(eMove, onMoveEvent, false);
            window.addEventListener(eEnd, onEndEvent, false);
            window.addEventListener(eCancel, onEndEvent, false);
        } else {
            list.el.on(eStart, onStartEvent);
            list.w.on(eMove, onMoveEvent);
            list.w.on(eEnd, onEndEvent);
        }

        const destroyNestable = function () {
            if (hasTouch) {
                list.el[0].removeEventListener(eStart, onStartEvent, false);
                window.removeEventListener(eMove, onMoveEvent, false);
                window.removeEventListener(eEnd, onEndEvent, false);
                window.removeEventListener(eCancel, onEndEvent, false);
            } else {
                list.el.off(eStart, onStartEvent);
                list.w.off(eMove, onMoveEvent);
                list.w.off(eEnd, onEndEvent);
            }

            list.el.off('click');
            list.el.off('destroy' + NAMESPACE);
            list.el.data("nestable", null);
        };

        list.el.on('destroy' + NAMESPACE, destroyNestable);
        list.el.trigger("init" + NAMESPACE);
    }

    destroy() {
        this.el.trigger('destroy' + NAMESPACE);
    }

    reset() {
        this.mouse = {
            offsetX: 0,
            offsetY: 0,
            startX: 0,
            startY: 0,
            lastX: 0,
            lastY: 0,
            nowX: 0,
            nowY: 0,
            distX: 0,
            distY: 0,
            dirAx: 0,
            dirX: 0,
            dirY: 0,
            lastDirX: 0,
            lastDirY: 0,
            distAxX: 0,
            distAxY: 0
        };
        this.dragEl = null;
        this.dragRootEl = null;
        this.dragDepth = 0;
        this.hasNewRoot = false;
        this.pointEl = null;
    }


    constructParent(li) {
        if (li.children("." + this.options.listClass).length) {
            li.find("." + this.options.toggleControlClass).addClass(ClassName.SHOW);
        }
    }

    destructParent(li) {
        li.addClass(this.options.expandedClass);
        li.children("." + this.options.listClass).remove();
        li.find("." + this.options.toggleControlClass).removeClass(ClassName.SHOW);
    }

    dragStart(e) {
        const
            mouse = this.mouse,
            target = $(e.target),
            dragItem = target.closest("." + this.options.itemClass);

        const position = {};
        position.top = e.pageY;
        position.left = e.pageX;

        // this.options.onDragStart.call(this, this.el, dragItem, position);
        this.placeEl.css('height', dragItem.height());

        mouse.offsetX = e.pageX - dragItem.offset().left;
        mouse.offsetY = e.pageY - dragItem.offset().top;
        mouse.startX = mouse.lastX = e.pageX;
        mouse.startY = mouse.lastY = e.pageY;

        this.dragRootEl = this.el;
        // this.dragEl = $(document.createElement("ol")).addClass(this.options.listClass + ' ' + this.options.dragClass);
        this.dragEl = $("<ol>").addClass(this.options.listClass + ' ' + this.options.dragClass);
        this.dragEl.css('width', dragItem.outerWidth());

        this.setIndexOfItem(dragItem);

        // fix for zepto.js
        //dragItem.after(this.placeEl).detach().appendTo(this.dragEl);
        dragItem.after(this.placeEl);
        dragItem[0].parentNode.removeChild(dragItem[0]);
        dragItem.appendTo(this.dragEl);

        $(document.body).append(this.dragEl);
        this.dragEl.css({
            'left': e.pageX - mouse.offsetX,
            'top': e.pageY - mouse.offsetY
        });
        // total depth of dragging item
        let i, depth;
        const items = this.dragEl.find("." + this.options.itemClass);
        for (i = 0; i < items.length; i++) {
            depth = $(items[i]).parents("." + this.options.listClass).length;
            if (depth > this.dragDepth) {
                this.dragDepth = depth;
            }
        }
    }

    setIndexOfItem(item, index) {
        if ((typeof index) === 'undefined') {
            index = [];
        }

        index.unshift(item.index());

        if ($(item[0].parentNode)[0] !== this.dragRootEl[0]) {
            this.setIndexOfItem($(item[0].parentNode), index);
        } else {
            this.dragEl.data('indexOfItem', index);
        }
    }

    restoreItemAtIndex(dragElement) {
        const indexArray = this.dragEl.data('indexOfItem');
        let currentEl = this.el;

        for (i = 0; i < indexArray.length; i++) {
            if ((indexArray.length - 1) === parseInt(i)) {
                placeElement(currentEl, dragElement);
                return
            }
            currentEl = currentEl[0].children[indexArray[i]];
        }

        function placeElement(currentEl, dragElement) {
            if (indexArray[indexArray.length - 1] === 0) {
                $(currentEl).prepend(dragElement.clone());
            } else {
                $(currentEl.children[indexArray[indexArray.length - 1] - 1]).after(dragElement.clone());
            }
        }
    }

    dragStop(e) {

        const el = this.dragEl.children("." + this.options.itemClass).first();
        el[0].parentNode.removeChild(el[0]);
        this.placeEl.replaceWith(el);


        const position = {};
        position.top = e.pageY;
        position.left = e.pageX;

        if (this.hasNewRoot) {
            if (this.options.fixed === true) {
                this.restoreItemAtIndex(el);

            }

            this.dragEl.remove();
            this.reset();

            return;
        }

        const event = $.Event("change" + NAMESPACE);
        this.el.trigger(event, [el]);
        this.dragEl.remove();

        this.reset();

    }

    dragMove(e) {
        let list, parent, prev, next, depth;
        const opt = this.options;
        const mouse = this.mouse;

        this.dragEl.css({
            'left': e.pageX - mouse.offsetX,
            'top': e.pageY - mouse.offsetY
        });

        // mouse position last events
        mouse.lastX = mouse.nowX;
        mouse.lastY = mouse.nowY;
        // mouse position this events
        mouse.nowX = e.pageX;
        mouse.nowY = e.pageY;
        // distance mouse moved between events
        mouse.distX = mouse.nowX - mouse.lastX;
        mouse.distY = mouse.nowY - mouse.lastY;
        // direction mouse was moving
        mouse.lastDirX = mouse.dirX;
        mouse.lastDirY = mouse.dirY;
        // direction mouse is now moving (on both axis)
        mouse.dirX = mouse.distX === 0 ? 0 : mouse.distX > 0 ? 1 : -1;
        mouse.dirY = mouse.distY === 0 ? 0 : mouse.distY > 0 ? 1 : -1;
        // axis mouse is now moving on
        const newAx = Math.abs(mouse.distX) > Math.abs(mouse.distY) ? 1 : 0;

        // do nothing on first move
        if (!mouse.moving) {
            mouse.dirAx = newAx;
            mouse.moving = true;
            return;
        }

        // calc distance moved on this axis (and direction)
        if (mouse.dirAx !== newAx) {
            mouse.distAxX = 0;
            mouse.distAxY = 0;
        } else {
            mouse.distAxX += Math.abs(mouse.distX);
            if (mouse.dirX !== 0 && mouse.dirX !== mouse.lastDirX) {
                mouse.distAxX = 0;
            }
            mouse.distAxY += Math.abs(mouse.distY);
            if (mouse.dirY !== 0 && mouse.dirY !== mouse.lastDirY) {
                mouse.distAxY = 0;
            }
        }
        mouse.dirAx = newAx;

        /**
         * move horizontal
         */
        if (mouse.dirAx && mouse.distAxX >= opt.threshold) {
            // reset move distance on x-axis for new phase
            mouse.distAxX = 0;
            prev = this.placeEl.prev("." + opt.itemClass);

            // increase horizontal level if previous sibling exists, is not collapsed, and can have children
            if (mouse.distX > 0 && prev.length && prev.hasClass(opt.expandedClass)) {
                // cannot increase level when item above is collapsed
                list = prev.find("." + opt.listClass).last();
                // check if depth limit has reached
                depth = this.placeEl.parents("." + opt.listClass).length;

                if (depth + this.dragDepth <= opt.maxDepth) {
                    // create new sub-level if one doesn't exist
                    if (!list.length) {
                        list = $('<ol/>').addClass(opt.listClass);
                        list.append(this.placeEl);
                        prev.append(list);

                        this.constructParent(prev);
                    } else {
                        // else append to next level up
                        list = prev.children("." + opt.listClass).last();
                        list.append(this.placeEl);
                    }
                }
            }
            // decrease horizontal level
            else if (mouse.distX < 0) {

                // we can't decrease a level if an item preceeds the current one
                next = this.placeEl.next("." + opt.itemClass);
                if (!next.length) {
                    parent = this.placeEl.parent();
                    this.placeEl.closest("." + opt.itemClass).after(this.placeEl);
                    if (!parent.children().length) {
                        this.destructParent(parent.parent());
                    }
                }
            }
        }

        let isEmpty = false;

        // find list item under cursor
        if (!hasPointerEvents) {
            this.dragEl[0].style.visibility = 'hidden';
        }

        this.pointEl = $(document.elementFromPoint(e.pageX - document.body.scrollLeft, e.pageY - (window.pageYOffset || document.documentElement.scrollTop)));
        if (!hasPointerEvents) {
            this.dragEl[0].style.visibility = 'visible';
        }
        if (this.pointEl.hasClass(opt.handleClass)) {
            this.pointEl = this.pointEl.closest("." + opt.itemClass);
        }
        if (this.pointEl.hasClass(opt.emptyClass)) {
            isEmpty = true;
        } else if (!this.pointEl.length || !this.pointEl.hasClass(opt.itemClass)) {
            return;
        }

        // find parent list of item under cursor
        const pointElRoot = this.pointEl.closest('.' + opt.rootClass);
        const isNewRoot = this.dragRootEl.data('nestable-id') !== pointElRoot.data('nestable-id');

        /**
         * move vertical
         */
        if (!mouse.dirAx || isNewRoot || isEmpty) {
            // check if groups match if dragging over new root
            if (isNewRoot && opt.group !== pointElRoot.data('nestable-group')) {
                return;
            }

            // fixed item's depth, use for some list has specific type, eg:'Volume, Section, Chapter ...'
            if (this.options.fixedDepth && this.dragDepth + 1 !== this.pointEl.parents("." + opt.listClass).length) {
                return;
            }

            // check depth limit
            depth = this.dragDepth - 1 + this.pointEl.parents("." + opt.listClass).length;
            if (depth > opt.maxDepth) {
                return;
            }
            const before = e.pageY < (this.pointEl.offset().top + this.pointEl.height() / 2);
            parent = this.placeEl.parent();
            // if empty create new list to replace empty placeholder
            if (isEmpty) {
                list = $("<ol>").addClass(opt.listClass);
                list.append(this.placeEl);
                this.pointEl.replaceWith(list);
            } else if (before) {
                this.pointEl.before(this.placeEl);
            } else {
                this.pointEl.after(this.placeEl);
            }
            if (!parent.children().length) {
                this.destructParent(parent.parent());
            }
            if (!this.dragRootEl.find("." + opt.itemClass).length) {
                this.dragRootEl.append('<div class="' + opt.emptyClass + '"/>');
            }
            // parent root list has changed
            this.dragRootEl = pointElRoot;
            if (isNewRoot) {
                this.hasNewRoot = this.el[0] !== this.dragRootEl[0];
            }
        }
    }
}

Nestable.counter = 0;

$.fn.nestable = function (params, arg) {
    const lists = this;
    let retval = this;

    lists.each(function () {
        const $$ = $(this);
        const plugin = $$.data("nestable");

        if (!plugin) {
            Nestable.counter++;

            $$.data("nestable", new Nestable(this, $.extend({}, $$.data(), params)));
            $$.data("nestable-id", Nestable.counter);

        } else {
            if (typeof params === 'string' && typeof plugin[params] === 'function') {
                retval = plugin[params](arg);
            }
        }
    });

    return retval || lists;
};

