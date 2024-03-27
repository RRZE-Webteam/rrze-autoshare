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

    const isBlueskyConnected = autoshareObject.blueskyConnected;
    const isBlueskyEnabled = autoshareObject.blueskyEnabled;
    const isBlueskyPublished = autoshareObject.blueskyPublished;
    const isMastodonConnected = autoshareObject.mastodonConnected;
    const isMastodonEnabled = autoshareObject.mastodonEnabled;
    const isMastodonPublished = autoshareObject.mastodonPublished;
    const isTwitterConnected = autoshareObject.twitterConnected;
    const isTwitterEnabled = autoshareObject.twitterEnabled;
    const isTwitterPublished = autoshareObject.twitterPublished;

    const [isBlueskyChecked, setBlueskyIsChecked] = useState(
        isBlueskyConnected && isBlueskyEnabled && !isBlueskyPublished
    );
    const [isMastodonChecked, setMastodonIsChecked] = useState(
        isMastodonConnected && isMastodonEnabled && !isMastodonPublished
    );
    const [isTwitterChecked, setTwitterIsChecked] = useState(
        isTwitterConnected && isTwitterEnabled && !isTwitterPublished
    );

    useEffect(() => {
        if (isBlueskyChecked !== !!meta["rrze_autoshare_bluesky_enabled"]) {
            wp.data.dispatch("core/editor").editPost({
                meta: { rrze_autoshare_bluesky_enabled: isBlueskyChecked },
            });
        }
        if (isMastodonChecked !== !!meta["rrze_autoshare_mastodon_enabled"]) {
            wp.data.dispatch("core/editor").editPost({
                meta: {
                    rrze_autoshare_mastodon_enabled: isMastodonChecked,
                },
            });
        }
        if (isTwitterChecked !== !!meta["rrze_autoshare_twitter_enabled"]) {
            wp.data.dispatch("core/editor").editPost({
                meta: { rrze_autoshare_twitter_enabled: isTwitterChecked },
            });
        }
    }, [isBlueskyChecked, isMastodonChecked, isTwitterChecked]);

    let blueskyCheckboxLabel = __("Share on Bluesky", "rrze-autoshare");
    if (!isBlueskyConnected) {
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
    if (!isMastodonConnected) {
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
    if (!isTwitterConnected) {
        twitterCheckboxLabel = __("Share on X is disabled", "rrze-autoshare");
    } else if (isTwitterPublished) {
        twitterCheckboxLabel = __("It is published on X", "rrze-autoshare");
    }

    const blueskyCheckboxClass =
        isBlueskyConnected && !isBlueskyPublished
            ? ""
            : "checkbox-control-disabled";
    const mastodonCheckboxClass =
        isMastodonConnected && !isMastodonPublished
            ? ""
            : "checkbox-control-disabled";
    const twitterCheckboxClass =
        isTwitterConnected && !isTwitterPublished
            ? ""
            : "checkbox-control-disabled";

    return (
        <PluginDocumentSettingPanel
            name="rrze-autoshare-panel"
            title={__("Autoshare", "rrze-autoshare")}
            className="rrze-autoshare-panel"
        >
            <div className={blueskyCheckboxClass}>
                <CheckboxControl
                    label={blueskyCheckboxLabel}
                    checked={isBlueskyChecked}
                    disabled={!isBlueskyConnected || isBlueskyPublished}
                    onChange={(checked) => setBlueskyIsChecked(checked)}
                />
            </div>
            <div className={mastodonCheckboxClass}>
                <CheckboxControl
                    label={mastodonCheckboxLabel}
                    checked={isMastodonChecked}
                    disabled={!isMastodonConnected || isMastodonPublished}
                    onChange={(checked) => setMastodonIsChecked(checked)}
                />
            </div>
            <div className={twitterCheckboxClass}>
                <CheckboxControl
                    label={twitterCheckboxLabel}
                    checked={isTwitterChecked}
                    disabled={!isTwitterConnected || isTwitterPublished}
                    onChange={(checked) => setTwitterIsChecked(checked)}
                />
            </div>
        </PluginDocumentSettingPanel>
    );
};

registerPlugin("rrze-autoshare-panel", {
    render: AutoshareSettingsPanel,
});
