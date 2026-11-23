import { Controller } from '@hotwired/stimulus';
import { Editor } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import { TableKit } from '@tiptap/extension-table';
import Image from '@tiptap/extension-image';

// A light rich-text editor (Tiptap) on a <textarea data-controller="wysiwyg">: the textarea keeps the HTML and is what the form submits. Loaded only on pages that
// have such a field. The "HTML" button edits the source (the old content has tables and images; nothing is lost when a manager prefers the raw markup).
// Allowed markup is what the old editor allowed: headings, paragraphs, bold/italic, lists, quotes, links, tables, images.
const BUTTONS = [
    ['bold', 'fa-bold', (e) => e.chain().focus().toggleBold().run(), (e) => e.isActive('bold')],
    ['italic', 'fa-italic', (e) => e.chain().focus().toggleItalic().run(), (e) => e.isActive('italic')],
    ['h2', 'fa-heading', (e) => e.chain().focus().toggleHeading({ level: 2 }).run(), (e) => e.isActive('heading', { level: 2 })],
    ['h3', 'fa-text-height', (e) => e.chain().focus().toggleHeading({ level: 3 }).run(), (e) => e.isActive('heading', { level: 3 })],
    ['ul', 'fa-list-ul', (e) => e.chain().focus().toggleBulletList().run(), (e) => e.isActive('bulletList')],
    ['ol', 'fa-list-ol', (e) => e.chain().focus().toggleOrderedList().run(), (e) => e.isActive('orderedList')],
    ['quote', 'fa-quote-right', (e) => e.chain().focus().toggleBlockquote().run(), (e) => e.isActive('blockquote')],
    ['link', 'fa-link', 'link', (e) => e.isActive('link')],
    ['table', 'fa-table', (e) => (e.isActive('table') ? e.chain().focus().deleteTable().run() : e.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run()), (e) => e.isActive('table')],
    ['image', 'fa-image', 'image', () => false],
    ['file', 'fa-paperclip', 'file', () => false],
    ['undo', 'fa-rotate-left', (e) => e.chain().focus().undo().run(), () => false],
    ['redo', 'fa-rotate-right', (e) => e.chain().focus().redo().run(), () => false],
];

export default class extends Controller {
    connect() {
        this.source = false;
        this.wrapper = document.createElement('div');
        this.wrapper.className = 'wysiwyg';
        this.toolbar = document.createElement('div');
        this.toolbar.className = 'wysiwyg-toolbar btn-group';
        this.toolbar.setAttribute('role', 'toolbar');
        this.content = document.createElement('div');
        this.content.className = 'wysiwyg-content form-control';
        this.wrapper.append(this.toolbar, this.content);
        this.element.after(this.wrapper);
        this.element.hidden = true;

        this.editor = new Editor({
            element: this.content,
            extensions: [StarterKit.configure({ heading: { levels: [2, 3, 4] } }), TableKit, Image],
            content: this.element.value,
            editorProps: {
                // A picture pasted or dropped into the editor is uploaded like one chosen with the button.
                handlePaste: (view, event) => this.take(event.clipboardData?.files),
                handleDrop: (view, event) => this.take(event.dataTransfer?.files),
            },
            onUpdate: () => this.sync(),
            onSelectionUpdate: () => this.refresh(),
            onTransaction: () => this.refresh(),
        });

        this.buttons = BUTTONS.map(([name, icon, action, active]) => this.button(name, icon, () => this.run(action), active));
        this.sourceButton = this.button('source', 'fa-code', () => this.toggleSource(), () => this.source);
        this.sourceButton.title = 'HTML';
        this.refresh();
        this.onSubmit = () => this.sync();
        this.element.form?.addEventListener('submit', this.onSubmit);
    }

    disconnect() {
        this.element.form?.removeEventListener('submit', this.onSubmit);
        this.editor?.destroy();
        this.wrapper?.remove();
        this.element.hidden = false;
    }

    button(name, icon, onClick, active) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-sm btn-outline theme-secondary';
        button.dataset.name = name;
        button.title = name;
        button.innerHTML = `<i class="fas ${icon}"></i>`;
        button.addEventListener('click', onClick);
        button.isActive = active;
        this.toolbar.append(button);

        return button;
    }

    run(action) {
        if (action === 'link') return this.link();
        if (action === 'image') return this.image();
        if (action === 'file') return this.attach();
        return action(this.editor);
    }

    link() {
        const previous = this.editor.getAttributes('link').href ?? '';
        const href = window.prompt('URL', previous);
        if (href === null) return;
        const chain = this.editor.chain().focus().extendMarkRange('link');
        href === '' ? chain.unsetLink().run() : chain.setLink({ href }).run();
    }

    // A picture: chosen from the computer and uploaded (when the field has `data-wysiwyg-upload-url`), otherwise given by its URL.
    image() {
        const url = this.element.dataset.wysiwygUploadUrl;
        if (!url) {
            const src = window.prompt('Image URL');
            if (src) this.editor.chain().focus().setImage({ src }).run();
            return;
        }
        const input = document.createElement('input');
        input.type = 'file';
        input.accept = 'image/jpeg,image/png,image/webp,image/avif,image/gif';
        input.addEventListener('change', () => input.files[0] && this.upload(input.files[0]));
        input.click();
    }

    take(files) {
        const image = [...(files ?? [])].find((file) => file.type.startsWith('image/'));
        if (!image || !this.element.dataset.wysiwygUploadUrl) return false;
        this.upload(image);

        return true;
    }

    // A plain file (not a picture): uploaded, and a link to it with the name of the file is inserted.
    attach() {
        if (!this.element.dataset.wysiwygFileUrl) return window.alert('No upload');
        const input = document.createElement('input');
        input.type = 'file';
        input.addEventListener('change', async () => {
            const file = input.files[0];
            if (!file) return;
            const body = new FormData();
            body.append('file', file);
            try {
                const response = await fetch(this.element.dataset.wysiwygFileUrl, { method: 'POST', body, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                const data = await response.json();
                if (!response.ok || !data.location) throw new Error(data.error ?? response.statusText);
                this.editor.chain().focus().insertContent({ type: 'text', text: data.name ?? file.name, marks: [{ type: 'link', attrs: { href: data.location } }] }).run();
            } catch (error) {
                window.alert(error.message);
            }
        });
        input.click();
    }

    async upload(file) {
        const body = new FormData();
        body.append('file', file);
        try {
            const response = await fetch(this.element.dataset.wysiwygUploadUrl, { method: 'POST', body, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const data = await response.json();
            if (!response.ok || !data.location) throw new Error(data.error ?? response.statusText);
            this.editor.chain().focus().setImage({ src: data.location }).run();
        } catch (error) {
            window.alert(error.message);
        }
    }

    toggleSource() {
        this.source = !this.source;
        if (this.source) {
            this.sync();
            this.element.hidden = false;
            this.content.hidden = true;
        } else {
            this.editor.commands.setContent(this.element.value);
            this.element.hidden = true;
            this.content.hidden = false;
        }
        this.refresh();
    }

    sync() {
        if (this.source) return;
        this.element.value = this.editor.isEmpty ? '' : this.editor.getHTML();
        this.element.dispatchEvent(new Event('input', { bubbles: true }));
    }

    refresh() {
        this.buttons?.forEach((button) => button.classList.toggle('active', Boolean(button.isActive(this.editor))));
        this.sourceButton?.classList.toggle('active', this.source);
        this.buttons?.forEach((button) => (button.disabled = this.source));
    }
}
