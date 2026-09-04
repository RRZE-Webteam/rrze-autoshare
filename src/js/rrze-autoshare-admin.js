/* global autoshareObject, wp */

(function registerAutosharePanel(wordpress) {
    'use strict';

    if (!wordpress || !autoshareObject) {
        return;
    }

    var registerPlugin = wordpress.plugins.registerPlugin;
    var PluginDocumentSettingPanel = wordpress.editPost.PluginDocumentSettingPanel;
    var FormToggle = wordpress.components.FormToggle;
    var createElement = wordpress.element.createElement;
    var useDispatch = wordpress.data.useDispatch;
    var useEffect = wordpress.element.useEffect;
    var useSelect = wordpress.data.useSelect;
    var useState = wordpress.element.useState;

    function selectEditedPostMeta(select) {
        return select('core/editor').getEditedPostAttribute('meta') || {};
    }

    function AutoshareSettingsPanel() {
        var meta = useSelect(selectEditedPostMeta, []);
        var editPost = useDispatch('core/editor').editPost;
        var enabledState = useState(autoshareObject.autoshareEnabled);
        var isAutoshareEnabled = enabledState[0];
        var setAutoshareEnabled = enabledState[1];

        function synchronizePostMeta() {
            if (isAutoshareEnabled !== !!meta[autoshareObject.metaKey]) {
                editPost({
                    meta: {
                        [autoshareObject.metaKey]: !!isAutoshareEnabled
                    }
                });
            }
        }

        useEffect(synchronizePostMeta, [
            editPost,
            meta,
            isAutoshareEnabled
        ]);

        function updateAutoshareEnabled(event) {
            setAutoshareEnabled(event.target.checked);
        }

        return createElement(
            PluginDocumentSettingPanel,
            {
                name: 'rrze-autoshare-panel',
                title: autoshareObject.labels.panelTitle,
                className: 'rrze-autoshare-panel'
            },
            createElement(
                'div',
                { className: 'rrze-autoshare-enabled-control' },
                createElement(FormToggle, {
                    checked: isAutoshareEnabled,
                    onChange: updateAutoshareEnabled
                }),
                createElement('span', null, autoshareObject.labels.autoshareEnabled)
            )
        );
    }

    registerPlugin('rrze-autoshare-panel', {
        render: AutoshareSettingsPanel
    });
}(window.wp));
