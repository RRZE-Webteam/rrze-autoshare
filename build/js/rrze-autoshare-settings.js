(() => {
  // src/js/rrze-autoshare-settings.js
  (function initializeTransmissionTestPostSearch(wordpress) {
    "use strict";
    if (!wordpress || !wordpress.apiFetch || !autoshareSettingsObject) {
      return;
    }
    var searchInput = document.getElementById(autoshareSettingsObject.searchInputId);
    var postIdInput = document.getElementById(autoshareSettingsObject.postIdInputId);
    var resultsElement = document.getElementById(autoshareSettingsObject.resultsId);
    var statusElement = document.getElementById(autoshareSettingsObject.statusId);
    var searchTimeout;
    var postsById = {};
    function clearResults() {
      resultsElement.replaceChildren();
      postsById = {};
    }
    function selectPost(event) {
      var postId = event.currentTarget.dataset.postId;
      var post = postsById[postId];
      if (!post) {
        return;
      }
      postIdInput.value = post.id;
      searchInput.value = post.title;
      statusElement.textContent = autoshareSettingsObject.selectedPost + " " + post.title + " (" + post.id + ")";
      clearResults();
    }
    function renderResults(posts) {
      var i;
      clearResults();
      if (!posts.length) {
        statusElement.textContent = autoshareSettingsObject.noResults;
        return;
      }
      for (i = 0; i < posts.length; i++) {
        var post = posts[i];
        var resultButton = document.createElement("button");
        postsById[post.id] = post;
        resultButton.type = "button";
        resultButton.className = "button-link rrze-autoshare-post-search-result";
        resultButton.dataset.postId = post.id;
        resultButton.textContent = post.title + " (" + post.id + ")";
        resultButton.setAttribute("role", "option");
        resultButton.addEventListener("click", selectPost);
        resultsElement.appendChild(resultButton);
      }
    }
    function handleSearchSuccess(posts) {
      renderResults(Array.isArray(posts) ? posts : []);
    }
    function handleSearchFailure() {
      clearResults();
      statusElement.textContent = autoshareSettingsObject.searchFailed;
    }
    function searchPosts(searchTerm) {
      var query = new URLSearchParams({
        search: searchTerm,
        per_page: autoshareSettingsObject.searchLimit,
        type: autoshareSettingsObject.searchType,
        subtype: autoshareSettingsObject.searchSubtype
      });
      wordpress.apiFetch({
        path: autoshareSettingsObject.searchEndpoint + "?" + query.toString()
      }).then(handleSearchSuccess).catch(handleSearchFailure);
    }
    function runSearch() {
      var searchTerm = searchInput.value.trim();
      if (searchTerm.length < autoshareSettingsObject.searchMinimumLength) {
        clearResults();
        return;
      }
      searchPosts(searchTerm);
    }
    function handleSearchInput() {
      postIdInput.value = "";
      statusElement.textContent = "";
      window.clearTimeout(searchTimeout);
      searchTimeout = window.setTimeout(runSearch, 250);
    }
    function updatePublicationRulesVisibility(event) {
      var container = event.currentTarget.closest(".rrze-autoshare-publication-rules");
      var advancedRules;
      if (!container) {
        return;
      }
      advancedRules = container.querySelector(".rrze-autoshare-publication-rules-advanced");
      if (advancedRules) {
        advancedRules.hidden = event.currentTarget.value !== "advanced";
      }
    }
    function initializePublicationRules() {
      var ruleModes = document.querySelectorAll(".rrze-autoshare-publication-rule-mode");
      var serviceRules = document.querySelectorAll(".rrze-autoshare-publication-service-rule");
      var i;
      for (i = 0; i < ruleModes.length; i++) {
        ruleModes[i].addEventListener("change", updatePublicationRulesVisibility);
      }
      for (i = 0; i < serviceRules.length; i++) {
        serviceRules[i].addEventListener("change", updatePublicationRuleSummary);
      }
    }
    function replacePlaceholder(template, value) {
      return template.replace("%s", value);
    }
    function getPublicationStatusLabel(serviceRule) {
      var statusInput = serviceRule.querySelector(".rrze-autoshare-publication-status:checked") || serviceRule.querySelector('.rrze-autoshare-publication-status[type="hidden"]');
      var statusLabel;
      if (!statusInput) {
        return "";
      }
      if (statusInput.dataset.statusLabel) {
        return statusInput.dataset.statusLabel;
      }
      statusLabel = statusInput.closest("label");
      return statusLabel ? statusLabel.textContent.trim() : "";
    }
    function getSelectedTermNames(select) {
      var names = [];
      var i;
      for (i = 0; i < select.options.length; i++) {
        if (select.options[i].selected) {
          names.push(select.options[i].textContent.trim());
        }
      }
      return names;
    }
    function getPublicationTermSummary(serviceRule, type) {
      var termRule = serviceRule.querySelector('[data-rule-type="' + type + '"]');
      var modeInput = termRule.querySelector(".rrze-autoshare-publication-term-mode:checked");
      var select = termRule.querySelector(".rrze-autoshare-publication-term-ids");
      var terms = getSelectedTermNames(select);
      var strings = autoshareSettingsObject.publicationRules;
      if (!modeInput || modeInput.value === "all") {
        return type === "category" ? strings.categoryAll : strings.tagAll;
      }
      if (!terms.length) {
        return type === "category" ? strings.categoryNone : strings.tagNone;
      }
      if (terms.length === 1) {
        return replacePlaceholder(
          type === "category" ? strings.categorySingle : strings.tagSingle,
          terms[0]
        );
      }
      return replacePlaceholder(
        type === "category" ? strings.categoryMultiple : strings.tagMultiple,
        terms.join(", ")
      );
    }
    function updatePublicationRuleSummary(event) {
      var serviceRule = event.currentTarget.closest(".rrze-autoshare-publication-service-rule");
      var statusElement2;
      var categoriesElement;
      var tagsElement;
      if (!serviceRule) {
        return;
      }
      statusElement2 = serviceRule.querySelector(".rrze-autoshare-publication-summary-status");
      categoriesElement = serviceRule.querySelector(".rrze-autoshare-publication-summary-categories");
      tagsElement = serviceRule.querySelector(".rrze-autoshare-publication-summary-tags");
      statusElement2.textContent = getPublicationStatusLabel(serviceRule);
      categoriesElement.textContent = getPublicationTermSummary(serviceRule, "category");
      tagsElement.textContent = getPublicationTermSummary(serviceRule, "tag");
    }
    initializePublicationRules();
    if (!searchInput || !postIdInput || !resultsElement || !statusElement) {
      return;
    }
    searchInput.addEventListener("input", handleSearchInput);
  })(window.wp);
})();
//# sourceMappingURL=rrze-autoshare-settings.js.map
