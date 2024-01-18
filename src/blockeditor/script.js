import { registerPlugin } from "@wordpress/plugins";
import { PluginDocumentSettingPanel } from "@wordpress/edit-post";
import { CheckboxControl } from "@wordpress/components";
import { useState, useEffect } from "@wordpress/element";
import { useSelect, useDispatch } from "@wordpress/data";
import { __ } from "@wordpress/i18n";

const AutoshareSettingsPanel = () => {
    const meta = useSelect((select) =>
        select("core/editor").getEditedPostAttribute("meta")
    );
    const { editPost } = useDispatch("core/editor");

    const isBlueskyEnabled = autoshareObject.blueskyEnabled;
    const isMastodonEnabled = autoshareObject.mastodonEnabled;

    const [isBlueskyChecked, setBlueskyIsChecked] = useState(isBlueskyEnabled);
    const [isMastodonChecked, setMastodonIsChecked] =
        useState(isMastodonEnabled);

    useEffect(() => {
        if (isBlueskyEnabled) {
            setBlueskyIsChecked(meta["rrze_autoshare_bluesky_enabled"]);
        } else {
            setBlueskyIsChecked(false);
        }

        if (isMastodonEnabled) {
            setMastodonIsChecked(meta["rrze_autoshare_mastodon_enabled"]);
        } else {
            setMastodonIsChecked(false);
        }
    }, [meta, isBlueskyEnabled, isMastodonEnabled]);

    useEffect(() => {
        editPost({
            meta: {
                ...meta,
                rrze_autoshare_bluesky_enabled: isBlueskyChecked ? true : false,
                rrze_autoshare_mastodon_enabled: isMastodonChecked ? true : false,
            },
        });
    }, [isBlueskyChecked, isMastodonChecked]);

    const onBlueskyChangeCheckbox = (newValue) => {
        setBlueskyIsChecked(newValue);
        editPost({
            meta: { ...meta, rrze_autoshare_bluesky_enabled: newValue },
        });
    };

    const onMastodonChangeCheckbox = (newValue) => {
        setMastodonIsChecked(newValue);
        editPost({
            meta: { ...meta, rrze_autoshare_mastodon_enabled: newValue },
        });
    };

    let blueskyCheckboxLabel = __(
        "Share on Bluesky when published",
        "rrze-autoshare"
    );
    if (!isBlueskyEnabled) {
        blueskyCheckboxLabel = __(
            "Share on Bluesky is disabled",
            "rrze-autoshare"
        );
    } else if (!isBlueskyChecked) {
        blueskyCheckboxLabel = __("Don't share on Bluesky", "rrze-autoshare");
    }

    let mastodonCheckboxLabel = __(
        "Share on Mastodon when published",
        "rrze-autoshare"
    );
    if (!isMastodonEnabled) {
        mastodonCheckboxLabel = __(
            "Share on Mastodon is disabled",
            "rrze-autoshare"
        );
    } else if (!isMastodonChecked) {
        mastodonCheckboxLabel = __("Don't share on Mastodon", "rrze-autoshare");
    }

    const blueskyCheckboxClass = isBlueskyEnabled
        ? ""
        : "checkbox-control-disabled";
    const mastodonCheckboxClass = isMastodonEnabled
        ? ""
        : "checkbox-control-disabled";

    return (
        <PluginDocumentSettingPanel
            name="mi-panel-de-configuracion"
            title={__("Autoshare", "rrze-autoshare")}
            className="rrze-autoshare-panel"
        >
            <div className={blueskyCheckboxClass}>
                <CheckboxControl
                    label={blueskyCheckboxLabel}
                    checked={isBlueskyChecked}
                    onChange={onBlueskyChangeCheckbox}
                    disabled={!isBlueskyEnabled}
                />
            </div>
            <div className={mastodonCheckboxClass}>
                <CheckboxControl
                    label={mastodonCheckboxLabel}
                    checked={isMastodonChecked}
                    onChange={onMastodonChangeCheckbox}
                    disabled={!isMastodonEnabled}
                />
            </div>
        </PluginDocumentSettingPanel>
    );
};

registerPlugin("rrze-autoshare-panel", {
    render: AutoshareSettingsPanel,
});
