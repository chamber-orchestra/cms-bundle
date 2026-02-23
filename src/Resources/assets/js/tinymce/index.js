"use strict";
import './theme.scss'
import observer from "../../component/observer";

// TinyMCE assets are always served by the Symfony server from public/cms/tinymce/
// (not the Vite dev server) because TinyMCE loads plugins/themes via <script> tags.
const assetBase = '/cms';

// Load TinyMCE core via script tag — Vite can't pre-bundle TinyMCE plugins
// because they are IIFEs that access bare `tinymce` global, not ES modules.
// TinyMCE loads its own plugins/themes via script tags using base_url.
const tinymceReady = new Promise((resolve, reject) => {
    if (window.tinymce) {
        resolve(window.tinymce);
        return;
    }
    const script = document.createElement('script');
    script.src = `${assetBase}/tinymce/tinymce.min.js`;
    script.onload = () => resolve(window.tinymce);
    script.onerror = (e) => reject(new Error('Failed to load TinyMCE: ' + e));
    document.head.appendChild(script);
});

const LANGUAGE = 'en';

const VALID_ATTR = 'title,alt,src,href,target';
const VALID_ELEMENTS = 'a,p,div,h1,h2,h3,h4,h5,h6,table,thead,tfoot,tr,td,caption,img,figure,figcaption,strong,em,b,ul,li,ol,section,main,aside,blockquote';
const ELEMENTS_MAP = {
    'section,main,aside': 'div',
};


const isValidElement = (el) => {
    return el.nodeType === 1 && VALID_ELEMENTS.split(',').indexOf(el.nodeName.toLowerCase()) >= 0;
};

const createElement = (el) => {

    for (let key in ELEMENTS_MAP) {
        if (key.split(',').indexOf(el.nodeName.toLowerCase()) >= 0) {
            return document.createElement(ELEMENTS_MAP[key]);
        }
    }

    return document.createElement(el.nodeName);
};


const isValidAttr = (attr) => {
    return VALID_ATTR.split(',').indexOf(attr.name.toLowerCase()) >= 0;
};


function isTextNode(a) {
    return a.nodeType === 3 || a.nodeType === 4;
}


function toDataURL(url, callback) {
    const xhr = new XMLHttpRequest();
    xhr.open('get', url);
    xhr.responseType = 'blob';
    xhr.onload = function () {
        const reader = new FileReader();
        reader.onload = function () {
            callback(this.result);
        };

        reader.readAsDataURL(xhr.response); // async call
    };

    xhr.send();
}

function setBase64ImageUrl(node) {

    toDataURL(node.src, function (dataURL) {
        node.src = dataURL;
    });

}


const parse = (root, node) => {

    if (!isValidElement(node)) {
        const value = isTextNode(node) ? node.nodeValue : node.textContent;
        if (value) {
            root.appendChild(document.createTextNode(value));
        }
        return;
    }

    const parent = createElement(node);
    [...node.attributes]
        .filter((attr) => isValidAttr(attr))
        .forEach((attr) => parent.setAttribute(attr.name, attr.value));


    // check data img
    if (parent.nodeName.toLowerCase() === 'img') {
        setBase64ImageUrl(parent);
        root.appendChild(parent);
        return;
    }

    for (let n = node.firstChild; n; n = n.nextSibling) {
        parse(parent, n);
    }

    root.appendChild(parent);
};

const clean = (el) => {
    const root = document.createElement('div');
    for (let n = el.firstChild; n; n = n.nextSibling) {
        parse(root, n);
    }
    el.innerHTML = root.innerHTML;
};

const NAMESPACE = 'wysiwyg';

const init = ((el) => {
    const tinymce = window.tinymce;
    tinymce.init({
        target: el,
        base_url: `${assetBase}/tinymce`,
        suffix: '.min',
        language: LANGUAGE,
        language_url: `${assetBase}/tinymce/langs/${LANGUAGE}.js`,
        height: 450,
        setup: function (editor) {
            editor.on('change', () => editor.save());
        },
        skin_url: `${assetBase}/tinymce/skins/ui/oxide`,
        theme: 'silver',
        mobile: {
            theme: 'mobile',
        },
        content_css: `${assetBase}/tinymce/skins/content/default/content.css`,
        browser_spellcheck: true,
        plugins: [
            'advlist autolink lists link image charmap preview hr anchor',
            'searchreplace wordcount visualblocks visualchars code fullscreen',
            'insertdatetime media nonbreaking table directionality',
            'paste textpattern imagetools'
        ],
        toolbar1: 'undo redo | styleselect | bold italic | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image',
        toolbar2: 'media | forecolor backcolor',
        image_advtab: true,
        menubar: 'edit insert view format table tools',
        visualblocks_default_state: true,
        end_container_on_empty_block: true,
        relative_urls: false,

        image_uploadtab: true,
        automatic_uploads: true,
        images_upload_url: el.dataset.imageUploadUrl,
        images_upload_credentials: true,
        //
        paste_data_images: true,
        paste_as_text: false,
        paste_postprocess: function (plugin, args) {
            clean(args.node);
        }
    });

    const parent = el.closest('[data-collection-element]');
    if (parent) {
        parent.addEventListener('before_update.dev.sort', function () {
            tinymce.get(el.id).remove();
        })
        parent.addEventListener('after_update.dev.sort', function () {
            init(el)
        })
    }

});

observer.observe('[data-wysiwyg]', (el) => {
    if (!el[NAMESPACE]) {
        tinymceReady.then(() => {
            el[NAMESPACE] = init(el);
        });
    }
})
