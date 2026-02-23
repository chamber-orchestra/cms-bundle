'use strict';

class Observer {

    constructor() {
        this.mutationObserver = [];
    }

    disconnect() {
        if (!this.mutationObserver.length) {
            return;
        }
        this.mutationObserver.each((el) => el.disconnect());
        this.mutationObserver = [];
    }

    toArray(obj) {
        const array = [];
        for (let i = (obj || []).length >>> 0; i--;) {
            array[i] = obj[i];
        }
        return array;
    }


    tryFindByTree(observer, parent, selector, callback) {
        parent.querySelectorAll(selector).forEach((node) => callback(node, observer));
    }


    once(selector, callback) {

        let found = false;
        this.observe(selector, (elem, observer) => {
            if (found) {
                return;
            }

            found = true;
            observer ? observer.disconnect() : null;
            callback(elem, observer);
        });
    }

    observe(selector, callback) {
        this.tryFindByTree(null, document, selector, callback);

        const observerCallback = (mutations, observer) => {
            this.toArray(mutations).forEach((mutation) => {
                if (mutation.type === 'childList'
                    && mutation.addedNodes.length > 0) {
                    for (let i = mutation.addedNodes.length >>> 0; i--;) {
                        const node = mutation.addedNodes[i];
                        if ((node.nodeType !== 1 && node.nodeType !== 9)) {
                            continue;
                        }
                        if (node.matches(selector)) {
                            callback(mutation.addedNodes[0]);
                        }
                        this.tryFindByTree(observer, node, selector, callback);
                    }
                }
            });
        };

        (new MutationObserver(observerCallback)).observe(document.documentElement, {
            childList: true,
            subtree: true,
            characterData: false
        });

    }
}


const observer = new Observer();
export default observer;
