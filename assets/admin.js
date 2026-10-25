import 'bootstrap/dist/css/bootstrap.min.css';
import 'bootstrap/js/dist/collapse';
import 'bootstrap/js/dist/dropdown';
import netteForms from 'nette-forms';
import './scss/admin.scss';

netteForms.initOnLoad();

const textareas = [...document.querySelectorAll('textarea[data-editor]')];
if (textareas.length) {
    const buttons = [...new Set(textareas.map(textarea => textarea.form))]
        .flatMap(form => [...form.querySelectorAll('[type="submit"]')]);
    const disabledStates = buttons.map(button => button.disabled);
    buttons.forEach(button => { button.disabled = true; });
    import('./editor.js')
        .then(({ initializeEditors }) => initializeEditors(textareas))
        .catch(error => {
            const message = document.createElement('p');
            message.className = 'alert alert-warning';
            message.setAttribute('role', 'alert');
            message.textContent = 'Editor se nepodařilo načíst. Text můžete upravit jako HTML nebo stránku znovu načíst.';
            textareas[0].form.prepend(message);
            console.error('Editor could not be loaded.', error);
        })
        .finally(() => buttons.forEach((button, index) => { button.disabled = disabledStates[index]; }));
}
