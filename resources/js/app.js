//
document.addEventListener('click', (event) => {
    const form = event.target.closest('[data-quotation-form]');
    if (!form) return;
    if (event.target.closest('[data-add-line]')) {
        const lines = form.querySelector('[data-lines]');
        const template = form.querySelector('[data-line-template]');
        lines.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', String(lines.children.length)));
    }
    const remove = event.target.closest('[data-remove-line]');
    if (remove && form.querySelectorAll('[data-line]').length > 1) remove.closest('[data-line]').remove();
});
