(function (wp) {
    'use strict';
    var __ = wp.i18n.__;
    var e = wp.element.createElement;
    var Fragment = wp.element.Fragment;
    var TextControl = wp.components.TextControl;
    var TextareaControl = wp.components.TextareaControl;
    var SelectControl = wp.components.SelectControl;
    var Button = wp.components.Button;
    var PanelBody = wp.components.PanelBody;
    var InspectorControls = wp.blockEditor.InspectorControls;
    var kinds = [
        ['summary', __('Summary', 'ai0-content-refresh')], ['faq', __('FAQ', 'ai0-content-refresh')], ['checklist', __('Checklist', 'ai0-content-refresh')], ['table', __('Table', 'ai0-content-refresh')],
        ['callout', __('Callout', 'ai0-content-refresh')], ['cta', __('Contact box', 'ai0-content-refresh')], ['related', __('Related links', 'ai0-content-refresh')]
    ].map(function (v) { return { value: v[0], label: v[1] }; });
    wp.blocks.registerBlockType('ai0-refresh/section', {
        apiVersion: 3,
        title: __('Content Refresh — content element', 'ai0-content-refresh'), icon: 'editor-table', category: 'text',
        description: __('Editable summary, FAQ, checklist, table or link box.', 'ai0-content-refresh'),
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
                e(InspectorControls, null, e(PanelBody, { title: __('Element type', 'ai0-content-refresh'), initialOpen: true },
                    e(SelectControl, { label: __('Type', 'ai0-content-refresh'), value: a.kind, options: kinds, onChange: function (v) { set({ kind: v }); } })
                )),
                e('section', blockProps,
                    e('p', { style: { fontSize: '12px', color: '#526375' } }, 'Content Refresh · ' + (kinds.find(function (k) { return k.value === a.kind; }) || kinds[0]).label),
                    e(TextControl, { label: __('Heading', 'ai0-content-refresh'), value: a.heading, onChange: function (v) { set({ heading: v }); } }),
                    e(TextareaControl, { label: __('Text', 'ai0-content-refresh'), value: a.text, onChange: function (v) { set({ text: v }); } }),
                    (a.items || []).map(function (item, index) {
                        return e('div', { key: index, style: { borderTop: '1px solid #dbe4ec', paddingTop: '14px', marginTop: '14px' } },
                            e(TextControl, { label: a.kind === 'faq' ? __('Question', 'ai0-content-refresh') : __('Item / left table column', 'ai0-content-refresh'), value: item.label || '', onChange: function (v) { itemChange(index, 'label', v); } }),
                            e(TextareaControl, { label: a.kind === 'faq' ? __('Answer', 'ai0-content-refresh') : __('Explanation / right table column', 'ai0-content-refresh'), value: item.text || '', onChange: function (v) { itemChange(index, 'text', v); } }),
                            e(TextControl, { label: __('Link (optional)', 'ai0-content-refresh'), type: 'url', value: item.url || '', onChange: function (v) { itemChange(index, 'url', v); } }),
                            e(Button, { variant: 'tertiary', isDestructive: true, onClick: function () { set({ items: a.items.filter(function (_, i) { return i !== index; }) }); } }, __('Remove item', 'ai0-content-refresh'))
                        );
                    }),
                    (a.items || []).length < 12 && e(Button, { variant: 'secondary', onClick: function () { set({ items: (a.items || []).concat([{ label: '', text: '', url: '' }]) }); } }, __('Add item', 'ai0-content-refresh'))
                )
            );
        },
        save: function () { return null; }
    });
}(window.wp));
