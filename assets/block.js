(function (wp) {
    'use strict';
    var e = wp.element.createElement;
    var Fragment = wp.element.Fragment;
    var TextControl = wp.components.TextControl;
    var TextareaControl = wp.components.TextareaControl;
    var SelectControl = wp.components.SelectControl;
    var Button = wp.components.Button;
    var PanelBody = wp.components.PanelBody;
    var InspectorControls = wp.blockEditor.InspectorControls;
    var kinds = [
        ['summary', 'Resumé'], ['faq', 'FAQ'], ['checklist', 'Tjekliste'], ['table', 'Tabel'],
        ['callout', 'Faktaboks'], ['cta', 'Kontaktboks'], ['related', 'Relaterede links']
    ].map(function (v) { return { value: v[0], label: v[1] }; });
    wp.blocks.registerBlockType('ai0-refresh/section', {
        apiVersion: 3,
        title: 'Content Refresh – indholdselement', icon: 'editor-table', category: 'text',
        description: 'Redigerbart resumé, FAQ, tjekliste, tabel eller linkboks.',
        attributes: {
            kind: { type: 'string', default: 'callout' }, heading: { type: 'string', default: '' },
            text: { type: 'string', default: '' }, items: { type: 'array', default: [] }
        },
        supports: { html: false },
        edit: function (props) {
            var a = props.attributes;
            var set = props.setAttributes;
            function itemChange(index, key, value) {
                var next = (a.items || []).map(function (v) { return Object.assign({}, v); });
                next[index][key] = value;
                set({ items: next });
            }
            var blockProps = wp.blockEditor.useBlockProps({ className: 'ai0r-section ai0r-section--' + a.kind });
            return e(Fragment, null,
                e(InspectorControls, null, e(PanelBody, { title: 'Elementtype', initialOpen: true },
                    e(SelectControl, { label: 'Type', value: a.kind, options: kinds, onChange: function (v) { set({ kind: v }); } })
                )),
                e('section', blockProps,
                    e('p', { style: { fontSize: '12px', color: '#526375' } }, 'Content Refresh · ' + (kinds.find(function (k) { return k.value === a.kind; }) || kinds[0]).label),
                    e(TextControl, { label: 'Overskrift', value: a.heading, onChange: function (v) { set({ heading: v }); } }),
                    e(TextareaControl, { label: 'Tekst', value: a.text, onChange: function (v) { set({ text: v }); } }),
                    (a.items || []).map(function (item, index) {
                        return e('div', { key: index, style: { borderTop: '1px solid #dbe4ec', paddingTop: '14px', marginTop: '14px' } },
                            e(TextControl, { label: a.kind === 'faq' ? 'Spørgsmål' : 'Punkt / venstre tabelkolonne', value: item.label || '', onChange: function (v) { itemChange(index, 'label', v); } }),
                            e(TextareaControl, { label: a.kind === 'faq' ? 'Svar' : 'Forklaring / højre tabelkolonne', value: item.text || '', onChange: function (v) { itemChange(index, 'text', v); } }),
                            e(TextControl, { label: 'Link (valgfrit)', type: 'url', value: item.url || '', onChange: function (v) { itemChange(index, 'url', v); } }),
                            e(Button, { variant: 'tertiary', isDestructive: true, onClick: function () { set({ items: a.items.filter(function (_, i) { return i !== index; }) }); } }, 'Fjern punkt')
                        );
                    }),
                    (a.items || []).length < 12 && e(Button, { variant: 'secondary', onClick: function () { set({ items: (a.items || []).concat([{ label: '', text: '', url: '' }]) }); } }, 'Tilføj punkt')
                )
            );
        },
        save: function () { return null; }
    });
}(window.wp));
