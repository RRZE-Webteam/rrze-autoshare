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

    const isBlueskyEnableByDefault = autoshareObject.blueskyEnableByDefault;
    const isBlueskyEnabled = autoshareObject.blueskyEnabled;
    const isBlueskyPublished = autoshareObject.blueskyPublished;
    const isMastodonEnableByDefault = autoshareObject.mastodonEnableByDefault;
    const isMastodonEnabled = autoshareObject.mastodonEnabled;
    const isMastodonPublished = autoshareObject.mastodonPublished;
    const isTwitterEnableByDefault = autoshareObject.twitterEnableByDefault;
    const isTwitterEnabled = autoshareObject.twitterEnabled;
    const isTwitterPublished = autoshareObject.twitterPublished;

    const [isBlueskyChecked, setBlueskyIsChecked] = useState(
        isBlueskyEnabled && !isBlueskyPublished && isBlueskyEnableByDefault
    );
    const [isMastodonChecked, setMastodonIsChecked] = useState(
        isMastodonEnabled && !isMastodonPublished && isMastodonEnableByDefault
    );
    const [isTwitterChecked, setTwitterIsChecked] = useState(
        isTwitterEnabled && !isTwitterPublished && isTwitterEnableByDefault
    );

    useEffect(() => {
        if (isBlueskyEnabled && !isBlueskyPublished) {
            setBlueskyIsChecked(meta["rrze_autoshare_bluesky_enabled"]);
        }
        if (isMastodonEnabled && !isMastodonPublished) {
            setMastodonIsChecked(meta["rrze_autoshare_mastodon_enabled"]);
        }
        if (isTwitterEnabled && !isTwitterPublished) {
            setTwitterIsChecked(meta["rrze_autoshare_twitter_enabled"]);
        }
    }, [
        meta,
        isBlueskyEnabled,
        isBlueskyPublished,
        isMastodonEnabled,
        isMastodonPublished,
        isTwitterEnabled,
        isTwitterPublished,
    ]);

    useEffect(() => {
        if (isBlueskyChecked !== meta["rrze_autoshare_bluesky_enabled"]) {
            wp.data.dispatch("core/editor").editPost({
                meta: { rrze_autoshare_bluesky_enabled: isBlueskyChecked },
            });
        }
        if (isMastodonChecked !== meta["rrze_autoshare_mastodon_enabled"]) {
            wp.data.dispatch("core/editor").editPost({
                meta: {
                    rrze_autoshare_mastodon_enabled: isMastodonChecked,
                },
            });
        }
        if (isTwitterChecked !== meta["rrze_autoshare_twitter_enabled"]) {
            wp.data.dispatch("core/editor").editPost({
                meta: { rrze_autoshare_twitter_enabled: isTwitterChecked },
            });
        }
    }, [isBlueskyChecked, isMastodonChecked, isTwitterChecked]);

    const updateMeta = (metaKey, newValue) => {
        editPost({
            meta: {
                ...meta,
                [metaKey]: newValue,
            },
        });
    };

    let blueskyCheckboxLabel = __("Share on Bluesky", "rrze-autoshare");
    if (!isBlueskyEnabled) {
        blueskyCheckboxLabel = __(
            "Share on Bluesky is disabled",
            "rrze-autoshare"
        );
    } else if (isBlueskyPublished) {
        blueskyCheckboxLabel = __(
            "It is published on Bluesky",
            "rrze-autoshare"
        );
    }

    let mastodonCheckboxLabel = __("Share on Mastodon", "rrze-autoshare");
    if (!isMastodonEnabled) {
        mastodonCheckboxLabel = __(
            "Share on Mastodon is disabled",
            "rrze-autoshare"
        );
    } else if (isMastodonPublished) {
        mastodonCheckboxLabel = __(
            "It is published on Mastodon",
            "rrze-autoshare"
        );
    }

    let twitterCheckboxLabel = __("Share on X (Twitter)", "rrze-autoshare");
    if (!isTwitterEnabled) {
        twitterCheckboxLabel = __("Share on X is disabled", "rrze-autoshare");
    } else if (isTwitterPublished) {
        twitterCheckboxLabel = __("It is published on X", "rrze-autoshare");
    }

    const blueskyCheckboxClass =
        isBlueskyEnabled && !isBlueskyPublished
            ? ""
            : "checkbox-control-disabled";
    const mastodonCheckboxClass =
        isMastodonEnabled && !isMastodonPublished
            ? ""
            : "checkbox-control-disabled";
    const twitterCheckboxClass =
        isTwitterEnabled && !isTwitterPublished
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
                    onChange={(value) =>
                        updateMeta("rrze_autoshare_bluesky_enabled", value)
                    }
                    disabled={!isBlueskyEnabled}
                />
            </div>
            <div className={mastodonCheckboxClass}>
                <CheckboxControl
                    label={mastodonCheckboxLabel}
                    checked={isMastodonChecked}
                    onChange={(value) =>
                        updateMeta("rrze_autoshare_mastodon_enabled", value)
                    }
                    disabled={!isMastodonEnabled}
                />
            </div>
            <div className={twitterCheckboxClass}>
                <CheckboxControl
                    label={twitterCheckboxLabel}
                    checked={isTwitterChecked}
                    onChange={(value) =>
                        updateMeta("rrze_autoshare_twitter_enabled", value)
                    }
                    disabled={!isTwitterEnabled}
                />
            </div>
        </PluginDocumentSettingPanel>
    );
};

registerPlugin("rrze-autoshare-panel", {
    render: AutoshareSettingsPanel,
});
