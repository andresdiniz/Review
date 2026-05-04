/**
 * easymde-init.js
 * Inicializa o EasyMDE com ícones SVG próprios (sem dependência de Font Awesome)
 */
(function () {

    // SVGs minimalistas para cada botão da toolbar
    var icons = {
        bold:             '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="16" height="16"><path d="M6 4h8a4 4 0 0 1 0 8H6z"/><path d="M6 12h9a4 4 0 0 1 0 8H6z"/></svg>',
        italic:           '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><line x1="19" y1="4" x2="10" y2="4"/><line x1="14" y1="20" x2="5" y2="20"/><line x1="15" y1="4" x2="9" y2="20"/></svg>',
        heading:          '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M4 12h16M4 6h8m-8 12h8"/></svg>',
        quote:            '<svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16"><path d="M3 21c3 0 7-1 7-8V5c0-1.25-.756-2.017-2-2H4c-1.25 0-2 .75-2 1.972V11c0 1.25.75 2 2 2 1 0 1 0 1 1v1c0 1-1 2-2 2s-1 .008-1 1.031V20c0 1 0 1 1 1zm12 0c3 0 7-1 7-8V5c0-1.25-.757-2.017-2-2h-4c-1.25 0-2 .75-2 1.972V11c0 1.25.75 2 2 2h.75c0 2.25.25 4-2.75 4v3c0 1 0 1 1 1z"/></svg>',
        'unordered-list': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><line x1="9" y1="6" x2="20" y2="6"/><line x1="9" y1="12" x2="20" y2="12"/><line x1="9" y1="18" x2="20" y2="18"/><circle cx="4" cy="6" r="1.5" fill="currentColor" stroke="none"/><circle cx="4" cy="12" r="1.5" fill="currentColor" stroke="none"/><circle cx="4" cy="18" r="1.5" fill="currentColor" stroke="none"/></svg>',
        'ordered-list':   '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><line x1="10" y1="6" x2="21" y2="6"/><line x1="10" y1="12" x2="21" y2="12"/><line x1="10" y1="18" x2="21" y2="18"/><text x="2" y="8" font-size="6" fill="currentColor" stroke="none">1.</text><text x="2" y="14" font-size="6" fill="currentColor" stroke="none">2.</text><text x="2" y="20" font-size="6" fill="currentColor" stroke="none">3.</text></svg>',
        link:             '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>',
        image:            '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>',
        preview:          '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>',
        'side-by-side':   '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><rect x="3" y="3" width="8" height="18" rx="1"/><rect x="13" y="3" width="8" height="18" rx="1"/></svg>',
        fullscreen:       '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><polyline points="15 3 21 3 21 9"/><polyline points="9 21 3 21 3 15"/><line x1="21" y1="3" x2="14" y2="10"/><line x1="3" y1="21" x2="10" y2="14"/></svg>',
        guide:            '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>',
    };

    var toolbar = [
        { name: 'bold',           title: 'Negrito (Ctrl+B)',      action: EasyMDE.toggleBold,            icon: icons['bold'] },
        { name: 'italic',         title: 'Itálico (Ctrl+I)',      action: EasyMDE.toggleItalic,          icon: icons['italic'] },
        { name: 'heading',        title: 'Título',                action: EasyMDE.toggleHeadingSmaller,  icon: icons['heading'] },
        '|',
        { name: 'quote',          title: 'Citação',              action: EasyMDE.toggleBlockquote,      icon: icons['quote'] },
        { name: 'unordered-list', title: 'Lista com marcadores',  action: EasyMDE.toggleUnorderedList,   icon: icons['unordered-list'] },
        { name: 'ordered-list',   title: 'Lista numerada',        action: EasyMDE.toggleOrderedList,     icon: icons['ordered-list'] },
        '|',
        { name: 'link',           title: 'Inserir link',          action: EasyMDE.drawLink,              icon: icons['link'] },
        { name: 'image',          title: 'Inserir imagem',        action: EasyMDE.drawImage,             icon: icons['image'] },
        '|',
        { name: 'preview',        title: 'Preview',               action: EasyMDE.togglePreview,         icon: icons['preview'],      className: 'no-disable' },
        { name: 'side-by-side',   title: 'Preview lado a lado',   action: EasyMDE.toggleSideBySide,      icon: icons['side-by-side'], className: 'no-disable no-mobile' },
        { name: 'fullscreen',     title: 'Tela cheia',            action: EasyMDE.toggleFullScreen,      icon: icons['fullscreen'],   className: 'no-disable no-mobile' },
        '|',
        { name: 'guide',          title: 'Guia Markdown',         action: 'https://www.markdownguide.org/basic-syntax/', icon: icons['guide'] },
    ];

    function initEditors() {
        document.querySelectorAll('textarea[data-easymde="true"]').forEach(function (el) {
            if (el._easymde) return;

            var mde = new EasyMDE({
                element:      el,
                spellChecker: false,
                autosave:     { enabled: false },
                lineWrapping: true,
                tabSize:      2,
                placeholder:  '## Introdução\n\nDescreva o produto...\n\n## Desempenho\n\n## Conclusão',
                toolbar:      toolbar,
                status:       ['lines', 'words', 'cursor'],
            });

            el._easymde = mde;

            var form = el.closest('form');
            if (form) {
                form.addEventListener('submit', function () {
                    mde.codemirror.save();
                });
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initEditors);
    } else {
        initEditors();
    }

    document.addEventListener('turbo:load',   initEditors);
    document.addEventListener('turbo:render', initEditors);
})();
