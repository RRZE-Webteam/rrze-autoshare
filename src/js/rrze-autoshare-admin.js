/* global autoshareObject, wp */

(function registerAutosharePanel(wordpress) {
    'use strict';

    if (!wordpress || !autoshareObject) {
        return;
    }

    var registerPlugin = wordpress.plugins.registerPlugin;
    var PluginDocumentSettingPanel = wordpress.editPost.PluginDocumentSettingPanel;
    var CheckboxControl = wordpress.components.CheckboxControl;
    var createElement = wordpress.element.createElement;
    var useDispatch = wordpress.data.useDispatch;
    var useEffect = wordpress.element.useEffect;
    var useSelect = wordpress.data.useSelect;

    function selectEditedPostMeta(select) {
        return select('core/editor').getEditedPostAttribute('meta') || {};
    }

    function getBlueskyLabel() {
        if (!autoshareObject.blueskyConnected) {
            return autoshareObject.labels.blueskyDisabled;
        }

        if (autoshareObject.blueskyPublished) {
            return autoshareObject.labels.blueskyPublished;
        }

        return autoshareObject.labels.blueskyShare;
    }

    function getMastodonLabel() {
        if (!autoshareObject.mastodonConnected) {
            return autoshareObject.labels.mastodonDisabled;
        }

        if (autoshareObject.mastodonPublished) {
            return autoshareObject.labels.mastodonPublished;
        }

        return autoshareObject.labels.mastodonShare;
    }

    function AutoshareSettingsPanel() {
        var meta = useSelect(selectEditedPostMeta, []);
        var editPost = useDispatch('core/editor').editPost;
        var blueskyMetaKey = autoshareObject.metaKeys.blueskyEnabled;
        var mastodonMetaKey = autoshareObject.metaKeys.mastodonEnabled;
        var isBlueskyDisabled = !autoshareObject.blueskyConnected ||
            autoshareObject.blueskyPublished;
        var isMastodonDisabled = !autoshareObject.mastodonConnected ||
            autoshareObject.mastodonPublished;
        var useState = wordpress.element.useState;
        var blueskyState = useState(
            autoshareObject.blueskyConnected &&
            autoshareObject.blueskyEnabled &&
            !autoshareObject.blueskyPublished
        );
        var mastodonState = useState(
            autoshareObject.mastodonConnected &&
            autoshareObject.mastodonEnabled &&
            !autoshareObject.mastodonPublished
        );
        var isBlueskyChecked = blueskyState[0];
        var setBlueskyChecked = blueskyState[1];
        var isMastodonChecked = mastodonState[0];
        var setMastodonChecked = mastodonState[1];

        function synchronizePostMeta() {
            if (autoshareObject.blueskyActive && isBlueskyChecked !== !!meta[blueskyMetaKey]) {
                editPost({
                    meta: {
                        [blueskyMetaKey]: !!isBlueskyChecked
                    }
                });
            }

            if (autoshareObject.mastodonActive && isMastodonChecked !== !!meta[mastodonMetaKey]) {
                editPost({
                    meta: {
                        [mastodonMetaKey]: !!isMastodonChecked
                    }
                });
            }
        }

        useEffect(synchronizePostMeta, [
            editPost,
            meta,
            blueskyMetaKey,
            mastodonMetaKey,
            isBlueskyChecked,
            isMastodonChecked
        ]);

        function updateBlueskyEnabled(checked) {
            setBlueskyChecked(checked);
        }

        function updateMastodonEnabled(checked) {
            setMastodonChecked(checked);
        }

        return createElement(
            PluginDocumentSettingPanel,
            {
                name: 'rrze-autoshare-panel',
                title: autoshareObject.labels.panelTitle,
                className: 'rrze-autoshare-panel'
            },
            autoshareObject.blueskyActive && createElement(
                'div',
                { className: isBlueskyDisabled ? 'checkbox-control-disabled' : '' },
                createElement(CheckboxControl, {
                    label: getBlueskyLabel(),
                    checked: isBlueskyChecked,
                    disabled: isBlueskyDisabled,
                    onChange: updateBlueskyEnabled
                })
            ),
            autoshareObject.mastodonActive && createElement(
                'div',
                { className: isMastodonDisabled ? 'checkbox-control-disabled' : '' },
                createElement(CheckboxControl, {
                    label: getMastodonLabel(),
                    checked: isMastodonChecked,
                    disabled: isMastodonDisabled,
                    onChange: updateMastodonEnabled
                })
            )
        );
    }

    registerPlugin('rrze-autoshare-panel', {
        render: AutoshareSettingsPanel
    });
}(window.wp));
