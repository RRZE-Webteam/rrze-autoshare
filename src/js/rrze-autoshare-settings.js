/* global autoshareSettingsObject, wp */

(function initializeTransmissionTestPostSearch(wordpress) {
    'use strict';

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
        statusElement.textContent = autoshareSettingsObject.selectedPost + ' ' + post.title + ' (' + post.id + ')';
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
            var resultButton = document.createElement('button');

            postsById[post.id] = post;
            resultButton.type = 'button';
            resultButton.className = 'button-link rrze-autoshare-post-search-result';
            resultButton.dataset.postId = post.id;
            resultButton.textContent = post.title + ' (' + post.id + ')';
            resultButton.setAttribute('role', 'option');
            resultButton.addEventListener('click', selectPost);
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
            path: autoshareSettingsObject.searchEndpoint + '?' + query.toString()
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
        postIdInput.value = '';
        statusElement.textContent = '';
        window.clearTimeout(searchTimeout);
        searchTimeout = window.setTimeout(runSearch, 250);
    }

    function setPublicationRulesVisibility(advanced) {
        var sectionMarkers = document.querySelectorAll('.rrze-autoshare-publication-service-section-marker');
        var i;
        var heading;
        var table;

        for (i = 0; i < sectionMarkers.length; i++) {
            heading = sectionMarkers[i].previousElementSibling;
            table = sectionMarkers[i].nextElementSibling;
            sectionMarkers[i].hidden = !advanced;

            if (heading && heading.tagName === 'H2') {
                heading.hidden = !advanced;
            }

            if (table && table.tagName === 'TABLE') {
                table.hidden = !advanced;
            }
        }
    }

    function updatePublicationRulesVisibility(event) {
        setPublicationRulesVisibility(event.currentTarget.value === 'advanced');
    }

    function initializePublicationRules() {
        var ruleModes = document.querySelectorAll('.rrze-autoshare-publication-rule-mode');
        var serviceRules = document.querySelectorAll('.rrze-autoshare-publication-service-rule');
        var addRuleButtons = document.querySelectorAll('.rrze-autoshare-add-publication-rule');
        var removeRuleButtons = document.querySelectorAll('.rrze-autoshare-remove-publication-rule');
        var i;

        for (i = 0; i < ruleModes.length; i++) {
            ruleModes[i].addEventListener('change', updatePublicationRulesVisibility);
        }

        if (ruleModes.length) {
            setPublicationRulesVisibility(
                document.querySelector('.rrze-autoshare-publication-rule-mode:checked').value === 'advanced'
            );
        }

        for (i = 0; i < serviceRules.length; i++) {
            initializePublicationServiceRule(serviceRules[i]);
        }

        for (i = 0; i < addRuleButtons.length; i++) {
            addRuleButtons[i].addEventListener('click', addPublicationRule);
        }

        for (i = 0; i < removeRuleButtons.length; i++) {
            removeRuleButtons[i].addEventListener('click', removePublicationRule);
        }

        updatePublicationRuleRemoveButtons();
    }

    function initializePublicationServiceRule(serviceRule) {
        var termModes = serviceRule.querySelectorAll('.rrze-autoshare-publication-term-mode');
        var i;

        serviceRule.addEventListener('change', updatePublicationRuleSummary);
        for (i = 0; i < termModes.length; i++) {
            syncPublicationTermRule(termModes[i]);
            termModes[i].addEventListener('change', updatePublicationTermRule);
        }
    }

    function addPublicationRule(event) {
        var serviceRules = event.currentTarget.closest('.rrze-autoshare-publication-service-rules');
        var list;
        var template;
        var index;
        var markup;
        var fragment;
        var newRule;

        if (!serviceRules) {
            return;
        }

        list = serviceRules.querySelector('.rrze-autoshare-publication-service-rule-list');
        template = serviceRules.querySelector('.rrze-autoshare-publication-rule-template');
        index = serviceRules.dataset.nextRuleIndex;
        if (!list || !template || index === undefined) {
            return;
        }

        markup = template.innerHTML.replace(/__rule_index__/g, index);
        fragment = document.createRange().createContextualFragment(markup);
        newRule = fragment.querySelector('.rrze-autoshare-publication-service-rule');
        if (!newRule) {
            return;
        }

        list.appendChild(fragment);
        serviceRules.dataset.nextRuleIndex = String(Number(index) + 1);
        initializePublicationServiceRule(newRule);
        newRule.querySelector('.rrze-autoshare-remove-publication-rule').addEventListener('click', removePublicationRule);
        updatePublicationRuleRemoveButtons();
    }

    function removePublicationRule(event) {
        var serviceRule = event.currentTarget.closest('.rrze-autoshare-publication-service-rule');
        if (!serviceRule) {
            return;
        }

        serviceRule.remove();
        updatePublicationRuleRemoveButtons();
    }

    function updatePublicationRuleRemoveButtons() {
        var serviceRuleLists = document.querySelectorAll('.rrze-autoshare-publication-service-rule-list');
        var i;
        var rules;
        var removeButtons;
        var j;

        for (i = 0; i < serviceRuleLists.length; i++) {
            rules = serviceRuleLists[i].querySelectorAll('.rrze-autoshare-publication-service-rule');
            removeButtons = serviceRuleLists[i].querySelectorAll('.rrze-autoshare-remove-publication-rule');
            for (j = 0; j < removeButtons.length; j++) {
                removeButtons[j].hidden = rules.length <= 1;
            }
        }
    }

    function updatePublicationTermRule(event) {
        syncPublicationTermRule(event.currentTarget);
        updatePublicationRuleSummary(event);
    }

    function syncPublicationTermRule(modeInput) {
        var termRule = modeInput.closest('.rrze-autoshare-publication-term-rule');
        var selectedMode;
        var select;
        var i;

        if (!termRule) {
            return;
        }

        selectedMode = termRule.querySelector('.rrze-autoshare-publication-term-mode:checked');
        select = termRule.querySelector('.rrze-autoshare-publication-term-ids');
        if (!selectedMode || !select) {
            return;
        }

        select.disabled = selectedMode.value === 'all';
        if (!select.disabled) {
            return;
        }

        for (i = 0; i < select.options.length; i++) {
            select.options[i].selected = false;
        }
    }

    function replacePlaceholder(template, value) {
        return template.replace('%s', value);
    }

    function getPublicationStatusLabel(serviceRule) {
        var statusInput = serviceRule.querySelector('.rrze-autoshare-publication-status:checked') ||
            serviceRule.querySelector('.rrze-autoshare-publication-status[type="hidden"]');
        var statusLabel;

        if (!statusInput) {
            return '';
        }

        if (statusInput.dataset.statusLabel) {
            return statusInput.dataset.statusLabel;
        }

        statusLabel = statusInput.closest('label');

        return statusLabel ? statusLabel.textContent.trim() : '';
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
        var modeInput = termRule.querySelector('.rrze-autoshare-publication-term-mode:checked');
        var select = termRule.querySelector('.rrze-autoshare-publication-term-ids');
        var terms = getSelectedTermNames(select);
        var strings = autoshareSettingsObject.publicationRules;

        if (!modeInput || modeInput.value === 'all') {
            return type === 'category' ? strings.categoryAll : strings.tagAll;
        }

        if (!terms.length) {
            return type === 'category' ? strings.categoryNone : strings.tagNone;
        }

        if (terms.length === 1) {
            return replacePlaceholder(
                type === 'category' ? strings.categorySingle : strings.tagSingle,
                terms[0]
            );
        }

        return replacePlaceholder(
            type === 'category' ? strings.categoryMultiple : strings.tagMultiple,
            terms.join(', ')
        );
    }

    function getPublicationTargets(serviceRule) {
        var select = serviceRule.querySelector('.rrze-autoshare-publication-targets');
        var summary = serviceRule.querySelector('.rrze-autoshare-publication-summary-targets');

        if (select) {
            return getSelectedTermNames(select);
        }

        return summary && summary.dataset.targets
            ? summary.dataset.targets.split('|')
            : [];
    }

    function updatePublicationRuleSummary(event) {
        var serviceRule = event.currentTarget.closest('.rrze-autoshare-publication-service-rule');
        var statusElement;
        var categoriesElement;
        var tagsElement;
        var targetsElement;
        var targetPrefixElement;
        var targetValuesElement;
        var whenElement;
        var targets;
        var strings = autoshareSettingsObject.publicationRules;

        if (!serviceRule) {
            return;
        }

        statusElement = serviceRule.querySelector('.rrze-autoshare-publication-summary-status');
        categoriesElement = serviceRule.querySelector('.rrze-autoshare-publication-summary-categories');
        tagsElement = serviceRule.querySelector('.rrze-autoshare-publication-summary-tags');
        targetsElement = serviceRule.querySelector('.rrze-autoshare-publication-summary-targets');
        targetPrefixElement = serviceRule.querySelector('.rrze-autoshare-publication-summary-target-prefix');
        targetValuesElement = serviceRule.querySelector('.rrze-autoshare-publication-summary-target-values');
        whenElement = serviceRule.querySelector('.rrze-autoshare-publication-summary-when');

        statusElement.textContent = getPublicationStatusLabel(serviceRule);
        categoriesElement.textContent = getPublicationTermSummary(serviceRule, 'category');
        tagsElement.textContent = getPublicationTermSummary(serviceRule, 'tag');

        if (targetsElement && targetPrefixElement && targetValuesElement && whenElement) {
            targets = getPublicationTargets(serviceRule);
            targetsElement.hidden = !targets.length;
            targetPrefixElement.textContent = targets.length === 1
                ? strings.targetSingle
                : strings.targetMultiple;
            targetValuesElement.textContent = targets.join(', ');
            whenElement.textContent = targets.length
                ? strings.withTargetWhen
                : strings.withoutTargetWhen;
        }
    }

    initializePublicationRules();

    if (!searchInput || !postIdInput || !resultsElement || !statusElement) {
        return;
    }

    searchInput.addEventListener('input', handleSearchInput);
}(window.wp));
