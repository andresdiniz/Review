/**
 * easymde-init.js
 * Inicializa o EasyMDE em todos os textarea com data-easymde="true"
 * Carregado via configureAssets() no ProductCrudController
 */
(function () {
    function initEditors() {
        document.querySelectorAll('textarea[data-easymde="true"]').forEach(function (el) {
            if (el._easymde) return; // já inicializado

            var mde = new EasyMDE({
                element: el,
                spellChecker: false,
                autosave: { enabled: false },
                lineWrapping: true,
                tabSize: 2,
                placeholder: 'Escreva o review completo em Markdown...\n\n## Introdução\n\nDescreva o produto...\n\n## Desempenho\n\n## Conclusão',
                toolbar: [
                    'bold', 'italic', 'heading', '|',
                    'quote', 'unordered-list', 'ordered-list', '|',
                    'link', 'image', '|',
                    'preview', 'side-by-side', 'fullscreen', '|',
                    'guide'
                ],
                previewRender: function (plainText) {
                    // Preview simples via marked (já incluído no EasyMDE)
                    return EasyMDE.prototype.markdown(plainText);
                },
                status: ['autosave', 'lines', 'words', 'cursor'],
            });

            el._easymde = mde;

            // Garante que o valor do editor é sincronizado antes do submit
            var form = el.closest('form');
            if (form) {
                form.addEventListener('submit', function () {
                    mde.codemirror.save();
                }, { once: false });
            }
        });
    }

    // Inicializa após o DOM estar pronto
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initEditors);
    } else {
        initEditors();
    }

    // Re-inicializa se a página usar turbo/ajax navigation do EasyAdmin
    document.addEventListener('turbo:load',     initEditors);
    document.addEventListener('turbo:render',   initEditors);
    document.addEventListener('ea.collection.item-added', initEditors);
})();
