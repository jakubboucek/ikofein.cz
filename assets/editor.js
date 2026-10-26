import { Jodit } from 'jodit/esm/index.js';
import cs from 'jodit/esm/langs/cs_cz.js';
import 'jodit/esm/plugins/clean-html/clean-html.js';
import 'jodit/esm/plugins/justify/justify.js';
import 'jodit/esm/plugins/paste/paste.js';
import 'jodit/esm/plugins/paste-from-word/paste-from-word.js';
import 'jodit/esm/plugins/resize-cells/resize-cells.js';
import 'jodit/esm/plugins/select-cells/select-cells.js';
import 'jodit/esm/plugins/table-keyboard-navigation/table-keyboard-navigation.js';
import 'jodit/esm/plugins/source/source.js';
import 'jodit/esm/plugins/tab/tab.js';
import 'jodit/es2021/jodit.min.css';

Jodit.lang.cs_cz = cs;

const buttons = ['paragraph', 'bold', 'italic', 'ul', 'ol', 'align', 'link', 'table', 'undo', 'redo', 'eraser', 'source'];

export function initializeEditors(textareas) {
    const instances = [];
    try {
        for (const textarea of textareas) {
            const original = textarea.value;
            const editor = Jodit.make(textarea, {
                language: 'cs_cz',
                buttons,
                buttonsMD: buttons,
                buttonsSM: buttons,
                buttonsXS: buttons,
                height: 400,
                sourceEditor: 'area',
                beautifyHTML: false,
                cleanHTML: {
                    fillEmptyParagraph: false,
                    removeEmptyElements: false,
                    replaceOldTags: false,
                    replaceNBSP: false,
                },
            });
            instances.push({ editor, textarea, original, initialValue: editor.value });
            editor.editor.setAttribute('role', 'textbox');
            editor.editor.setAttribute('aria-multiline', 'true');
            const label = textarea.labels[0];
            if (label) {
                label.id ||= `${textarea.id}-label`;
                editor.editor.setAttribute('aria-labelledby', label.id);
                label.addEventListener('click', () => editor.s.focus());
            }
        }
    } catch (error) {
        for (const { editor, textarea, original } of instances) {
            editor.destruct();
            textarea.value = original;
        }
        throw error;
    }

    const valueFor = ({ editor, original, initialValue }) => {
        editor.synchronizeValues();
        return editor.value === initialValue ? original : editor.value;
    };
    for (const form of new Set(textareas.map(textarea => textarea.form))) {
        const controls = instances.filter(instance => instance.textarea.form === form);
        form.addEventListener('submit', () => {
            for (const instance of controls) {
                instance.textarea.value = valueFor(instance);
            }
        }, { capture: true });
        form.addEventListener('formdata', event => {
            for (const instance of controls) {
                event.formData.set(instance.textarea.name, valueFor(instance));
            }
        });
    }
}
