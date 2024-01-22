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
    const isBlueskyPublished = autoshareObject.blueskyPublished;
    const isMastodonEnabled = autoshareObject.mastodonEnabled;
    const isMastodonPublished = autoshareObject.mastodonPublished;

    const [isBlueskyChecked, setBlueskyIsChecked] = useState(
        isBlueskyEnabled &&
            !isBlueskyPublished &&
            (meta["rrze_autoshare_bluesky_enabled"] !== undefined
                ? meta["rrze_autoshare_bluesky_enabled"]
                : false)
    );
    const [isMastodonChecked, setMastodonIsChecked] = useState(
        isMastodonEnabled &&
            !isMastodonPublished &&
            (meta["rrze_autoshare_mastodon_enabled"] !== undefined
                ? meta["rrze_autoshare_mastodon_enabled"]
                : false)
    );

    useEffect(() => {
        if (isBlueskyEnabled && !isBlueskyPublished) {
            setBlueskyIsChecked(meta["rrze_autoshare_bluesky_enabled"]);
        }

        if (isMastodonEnabled && !isMastodonPublished) {
            setMastodonIsChecked(meta["rrze_autoshare_mastodon_enabled"]);
        }
    }, [
        meta,
        isBlueskyEnabled,
        isBlueskyPublished,
        isMastodonEnabled,
        isMastodonPublished,
    ]);

    const updateMeta = (metaKey, newValue) => {
        editPost({
            meta: {
                ...meta,
                [metaKey]: newValue,
            },
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
    } else if (isBlueskyPublished) {
        blueskyCheckboxLabel = __(
            "Share on Bluesky is published",
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
    } else if (isMastodonPublished) {
        mastodonCheckboxLabel = __(
            "Share on Mastodon is published",
            "rrze-autoshare"
        );
    } else if (!isMastodonChecked) {
        mastodonCheckboxLabel = __("Don't share on Mastodon", "rrze-autoshare");
    }

    const blueskyCheckboxClass =
        isBlueskyEnabled && !isBlueskyPublished
            ? ""
            : "checkbox-control-disabled";
    const mastodonCheckboxClass =
        isMastodonEnabled && !isMastodonPublished
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
                    onChange={(isChecked) =>
                        updateMeta("rrze_autoshare_bluesky_enabled", isChecked)
                    }
                    disabled={!isBlueskyEnabled}
                />
            </div>
            <div className={mastodonCheckboxClass}>
                <CheckboxControl
                    label={mastodonCheckboxLabel}
                    checked={isMastodonChecked}
                    onChange={(isChecked) =>
                        updateMeta("rrze_autoshare_mastodon_enabled", isChecked)
                    }
                    disabled={!isMastodonEnabled}
                />
            </div>
        </PluginDocumentSettingPanel>
    );
};

registerPlugin("rrze-autoshare-panel", {
    render: AutoshareSettingsPanel,
});
