(() => {
  // src/js/rrze-autoshare-admin.js
  (function registerAutosharePanel(wordpress) {
    "use strict";
    if (!wordpress || !autoshareObject) {
      return;
    }
    var registerPlugin = wordpress.plugins.registerPlugin;
    var PluginDocumentSettingPanel = wordpress.editPost.PluginDocumentSettingPanel;
    var Button = wordpress.components.Button;
    var CheckboxControl = wordpress.components.CheckboxControl;
    var FormToggle = wordpress.components.FormToggle;
    var Notice = wordpress.components.Notice;
    var createElement = wordpress.element.createElement;
    var useDispatch = wordpress.data.useDispatch;
    var useEffect = wordpress.element.useEffect;
    var useSelect = wordpress.data.useSelect;
    var useState = wordpress.element.useState;
    var apiFetch = wordpress.apiFetch;
    function selectEditedPostMeta(select) {
      return select("core/editor").getEditedPostAttribute("meta") || {};
    }
    function selectEditedPostStatus(select) {
      return select("core/editor").getEditedPostAttribute("status");
    }
    function getSelectedServices(services) {
      var selectedServices = [];
      var i;
      for (i = 0; i < services.length; i++) {
        if (services[i].selected) {
          selectedServices.push(services[i].slug);
        }
      }
      return selectedServices;
    }
    function getServiceLabel(serviceSlug) {
      var i;
      for (i = 0; i < autoshareObject.services.length; i++) {
        if (autoshareObject.services[i].slug === serviceSlug) {
          return autoshareObject.services[i].label;
        }
      }
      return serviceSlug;
    }
    function getFailedServiceLabels(results, selectedServiceSlugs) {
      var failedServices = [];
      var i;
      for (i = 0; i < selectedServiceSlugs.length; i++) {
        if (!results[selectedServiceSlugs[i]]) {
          failedServices.push(getServiceLabel(selectedServiceSlugs[i]));
        }
      }
      return failedServices;
    }
    function getShareMessage(results, selectedServiceSlugs) {
      var failedServices = getFailedServiceLabels(results, selectedServiceSlugs);
      if (failedServices.length) {
        return autoshareObject.labels.shareFailed.replace("%s", failedServices.join(", "));
      }
      return autoshareObject.labels.shareSucceeded;
    }
    function AutoshareSettingsPanel() {
      var meta = useSelect(selectEditedPostMeta, []);
      var postStatus = useSelect(selectEditedPostStatus, []);
      var editPost = useDispatch("core/editor").editPost;
      var enabledState = useState(autoshareObject.autoshareEnabled);
      var selectedServicesState = useState(autoshareObject.services.map(function selectService(service) {
        return {
          slug: service.slug,
          label: service.label,
          selected: true
        };
      }));
      var sharingState = useState(false);
      var shareNoticeState = useState(null);
      var isAutoshareEnabled = enabledState[0];
      var setAutoshareEnabled = enabledState[1];
      var selectedServices = selectedServicesState[0];
      var setSelectedServices = selectedServicesState[1];
      var isSharing = sharingState[0];
      var setSharing = sharingState[1];
      var shareNotice = shareNoticeState[0];
      var setShareNotice = shareNoticeState[1];
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
      function updateSelectedService(serviceSlug, selected) {
        var updatedServices = [];
        var i;
        for (i = 0; i < selectedServices.length; i++) {
          updatedServices.push({
            slug: selectedServices[i].slug,
            label: selectedServices[i].label,
            selected: selectedServices[i].slug === serviceSlug ? selected : selectedServices[i].selected
          });
        }
        setSelectedServices(updatedServices);
      }
      function sharePost() {
        var services = getSelectedServices(selectedServices);
        if (!services.length) {
          setShareNotice({
            status: "warning",
            message: autoshareObject.labels.selectService
          });
          return;
        }
        setSharing(true);
        setShareNotice(null);
        apiFetch({
          path: autoshareObject.shareRoute,
          method: "POST",
          data: {
            services
          }
        }).then(function handleShareResponse(response) {
          setShareNotice({
            status: Object.keys(response.results).every(function serviceSucceeded(service) {
              return !!response.results[service];
            }) ? "success" : "error",
            message: getShareMessage(response.results, services)
          });
        }).catch(function handleShareFailure() {
          setShareNotice({
            status: "error",
            message: getShareMessage({}, services)
          });
        }).finally(function finishSharing() {
          setSharing(false);
        });
      }
      return createElement(
        PluginDocumentSettingPanel,
        {
          name: "rrze-autoshare-panel",
          title: autoshareObject.labels.panelTitle,
          className: "rrze-autoshare-panel"
        },
        createElement(
          "div",
          { className: "rrze-autoshare-enabled-control" },
          createElement(FormToggle, {
            checked: isAutoshareEnabled,
            onChange: updateAutoshareEnabled
          }),
          createElement("span", null, autoshareObject.labels.autoshareEnabled)
        ),
        postStatus === "publish" && createElement(
          "div",
          { className: "rrze-autoshare-manual-share" },
          selectedServices.map(function renderServiceControl(service) {
            return createElement(CheckboxControl, {
              key: service.slug,
              label: service.label,
              checked: service.selected,
              onChange: function updateServiceSelection(selected) {
                updateSelectedService(service.slug, selected);
              }
            });
          }),
          createElement(Button, {
            variant: "secondary",
            isBusy: isSharing,
            disabled: isSharing,
            onClick: sharePost
          }, isSharing ? autoshareObject.labels.sharingPost : autoshareObject.labels.sharePost),
          shareNotice && createElement(Notice, {
            status: shareNotice.status,
            isDismissible: false
          }, shareNotice.message)
        )
      );
    }
    registerPlugin("rrze-autoshare-panel", {
      render: AutoshareSettingsPanel
    });
  })(window.wp);
})();
//# sourceMappingURL=rrze-autoshare-admin.js.map
